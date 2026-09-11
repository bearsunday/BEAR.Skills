#!/usr/bin/env bash
# One flow per iteration: a worker extends cache coverage, the gate judges it, a second model
# judges the gate. Stops on a clean sweep, a repeated failure, or the iteration ceiling.
#
#   var/loop/run-loop.sh [max-iterations]
#
# The gate (var/loop/verify-all.sh) is the only thing that decides "it works": every flow's log
# has to prove store/hit/invalidation, and the application suite has to stay green.
set -uo pipefail

REPO=$(cd "$(dirname "$0")/../.." && pwd)
LOOP_DIR="$REPO/var/loop"
MAX_ITERATIONS=${1:-3}
WORKER_PROVIDER="<provider/model>"  # machine-specific — must be set
WORKSPACE="<workspace-id>"          # machine-specific — must be set
JUDGE_MODEL="<judge-model>"         # machine-specific — must be set
WORKER_PROMPT=$(cat "$LOOP_DIR/worker.md")
JUDGE_PROMPT_FILE="$LOOP_DIR/verifier.md"

cd "$REPO" || exit 2

log() { printf '[%s] %s\n' "$(date +%H:%M:%S)" "$*" | tee -a "$LOOP_DIR/loop.log"; }

remaining_flows() {
    grep -rln "QueryInterface" src/Resource/Page 2>/dev/null | grep -v Admin | wc -l | tr -d ' '
}

# `paseo wait` returns as soon as the agent is idle, and a freshly created agent is idle before it
# starts: the first run judged its own machinery commit because the wait fell through in seconds.
# Wait for the transition into running, then for the return to idle.
await_agent() {
    local id="$1" limit="$2" waited=0 seen_running=0 status activity last_activity="" stalled=0
    while [ "$waited" -lt "$limit" ]; do
        status=$(paseo inspect "$id" 2>/dev/null | awk '/^Status/ {print $2; exit}')
        case "$status" in
            running) seen_running=1 ;;
            idle|completed) [ "$seen_running" = 1 ] && return 0 ;;
            error|closed) return 1 ;;
        esac

        # A worker that stops acting is not working: the first run sat "running" for twenty
        # minutes with its activity count frozen. Treat a still count as a stall, not as thinking.
        activity=$(paseo logs "$id" 2>/dev/null | wc -l | tr -d ' ')
        if [ "$activity" = "$last_activity" ]; then
            stalled=$((stalled + 10))
            if [ "$stalled" -ge 600 ]; then
                log "await: $id has not acted for 10 minutes - stopping it"
                paseo stop "$id" >/dev/null 2>&1

                return 1
            fi
        else
            stalled=0
            last_activity="$activity"
        fi

        sleep 10
        waited=$((waited + 10))
    done

    log "await: $id did not finish within ${limit}s"

    return 1
}

judge() {
    # The gate says pass/fail; this asks a second model whether the evidence matches the checklist.
    #
    # Both the checklist and the evidence arrive as files read by python, never as heredoc text: an
    # unquoted heredoc expands its body, and the checklist is full of backticked commands
    # (`./var/loop/verify-all.sh`, `git log …`). The shell ran them, the gate ran a second time per
    # judge call, and what reached the judge was command output instead of the checklist.
    local evidence="$1"
    local evidence_file
    evidence_file=$(mktemp)
    printf '%s' "$evidence" > "$evidence_file"
    python3 - "$JUDGE_MODEL" "$JUDGE_PROMPT_FILE" "$evidence_file" <<'PY'
import json, sys, urllib.request

with open(sys.argv[2]) as f:
    checklist = f.read()
with open(sys.argv[3]) as f:
    evidence = f.read()
prompt = checklist + "\n\n--- evidence ---\n" + evidence
req = urllib.request.Request(
    "http://localhost:11434/api/chat",
    data=json.dumps({"model": sys.argv[1], "messages": [{"role": "user", "content": prompt}], "stream": False}).encode(),
    headers={"Content-Type": "application/json"},
)
try:
    print(json.loads(urllib.request.urlopen(req, timeout=300).read())["message"]["content"][:1200])
except Exception as e:
    print(f"JUDGE UNAVAILABLE: {e}")
PY
    rm -f "$evidence_file"
}

log "loop start: max=$MAX_ITERATIONS, pages still holding a query: $(remaining_flows)"

for iteration in $(seq 1 "$MAX_ITERATIONS"); do
    before=$(git rev-parse --short HEAD)
    if [ "$(remaining_flows)" = "0" ]; then
        log "iteration $iteration: no page holds a query any more - done"
        break
    fi

    log "iteration $iteration: starting worker ($WORKER_PROVIDER)"
    # --workspace, not --cwd: the daemon resolves an agent's workspace from the caller unless told,
    # and a worker pointed at the wrong repository follows a prompt about files it cannot see.
    agent=$(paseo run --background --quiet --provider "$WORKER_PROVIDER" --mode acceptEdits \
        --workspace "$WORKSPACE" --title "cache loop $iteration" "$WORKER_PROMPT" 2>&1 | tail -1)
    log "iteration $iteration: agent $agent"
    await_agent "$agent" 3600

    gate_output=$("$LOOP_DIR/verify-all.sh" 2>&1)
    gate_status=$?
    printf '%s\n' "$gate_output" | tee -a "$LOOP_DIR/loop.log"

    if [ "$gate_status" -ne 0 ]; then
        log "iteration $iteration: gate failed - sending it back once"
        paseo send "$agent" "The gate failed. Fix the cause, not the assertion. Do not extend KNOWN.

$gate_output" >/dev/null 2>&1
        await_agent "$agent" 1800
        gate_output=$("$LOOP_DIR/verify-all.sh" 2>&1)
        gate_status=$?
        printf '%s\n' "$gate_output" | tee -a "$LOOP_DIR/loop.log"
    fi

    after=$(git rev-parse --short HEAD)
    # Matched on the issue reference every KNOWN entry carries, not on the quoting around it: the
    # old pattern looked for a literal "(bearsunday" that appears nowhere in the file, so an
    # iteration that added a KNOWN entry reached the judge as an empty diff.
    known_diff=$(git diff HEAD~1 -- var/loop/verify-cache.php | grep -E '^[+-].*(KNOWN|bearsunday/BEAR)' | head -5)
    evidence="gate exit=$gate_status
$gate_output

branch: $(git rev-parse --abbrev-ref HEAD)
git log: $(git log --oneline -1)
commit moved: $before -> $after
page resources still injecting a query interface:
$(grep -rln 'QueryInterface' src/Resource/Page 2>/dev/null | grep -v Admin | head -8)
KNOWN diff:
$known_diff"

    verdict=$(judge "$evidence")
    printf 'judge: %s\n' "$verdict" | tee -a "$LOOP_DIR/loop.log"

    if [ "$gate_status" -ne 0 ]; then
        log "iteration $iteration: stopping - the gate is still red after one repair pass"
        exit 1
    fi

    if [ "$before" = "$after" ]; then
        log "iteration $iteration: stopping - the worker committed nothing"
        exit 1
    fi

    # A judge that cannot answer must not pass the iteration, which is what the comment in judge()
    # claimed while nothing read its verdict. `done=true` is the only sentence that continues.
    case "$verdict" in
        *JUDGE\ UNAVAILABLE*)
            log "iteration $iteration: stopping - the judge could not answer, so nothing here is verified"
            exit 1
            ;;
        *done=true*) ;;
        *)
            log "iteration $iteration: stopping - the judge did not answer done=true"
            exit 1
            ;;
    esac

    log "iteration $iteration: green, pages still holding a query: $(remaining_flows)"
done

log "loop end"
