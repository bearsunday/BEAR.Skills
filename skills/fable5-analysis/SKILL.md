---
user-invocable: true
name: fable5-analysis
description: Reproduce Fable 5-grade analytical rigor while executing on Opus. Runs an explicit workflow — frame & hypothesize, gather evidence (no guessing), then independent Opus analyst subagents cross-checked by fresh-context adversarial Opus verifier subagents — and synthesizes only verified conclusions. All heavy analysis and verification run on model:opus subagents; the invoking session (any model, incl. Fable 5) only orchestrates and synthesizes. Use when user says "deep analysis", "徹底分析", "Fable5級の分析", "Opusで分析", "analyze deeply", "分析して" (hard/ambiguous cases), "root cause", "根本原因", "second opinion", "セカンドオピニオン", "high-confidence analysis", or wants rigorous analysis of a hard or high-stakes question without depending on Fable 5's latency.
---

# Fable 5-grade analysis, executed on Opus

## What this skill is for

Fable 5's analytical edge is that it frames a problem, grounds every claim in
evidence, and self-verifies **in a single pass**. Opus does each of those steps
well, but less reliably in one shot. This skill recovers Fable-5-grade quality on
Opus by **externalizing** the steps Fable 5 does internally, and — the key move —
replacing fragile single-pass self-critique with:

- an **ensemble** of independent Opus analysts (diversity catches what one pass misses), and
- an **adversarial fresh-context verification** pass by separate Opus subagents whose only job is to refute.

Diversity plus refutation on a weaker-per-call model beats one weaker pass. That
is the whole thesis. Do not shortcut it into "just ask Opus once."

## Where it runs

- The analysis and verification **must run on Opus subagents**: `subagent_type: general-purpose`, `model: opus`, each with fresh context.
- The **invoking session can be any model** (including your Fable 5 default). It only frames, orchestrates, and synthesizes — it does not do the analysis itself.
- To run the *entire* flow on Opus (orchestration included), switch the session to Opus first (`/model` or fast mode). The subagents stay pinned to Opus either way.
- Never run a verifier in an analyst's context. Fresh eyes are the point.

## When NOT to use

Right-size the effort. For a trivial lookup, a well-specified mechanical task, or a
question you can already answer from evidence in hand, **skip this skill and answer
directly**. Spinning up subagents for a question that does not need them is the
failure mode this skill is most likely to cause. Use it for hard, ambiguous, or
high-stakes analysis where being wrong is expensive: root-cause investigations,
design trade-offs, decisions, audits, "is this claim actually true?".

## Workflow

### Phase 0 — Frame (orchestrator, inline)

Do this yourself before dispatching anything:

1. Restate the question in one precise sentence.
2. Define success: what a complete, correct answer must contain.
3. List 2–5 candidate answers or hypotheses up front. This prevents anchoring on the first plausible answer.
4. Choose depth, which sets the fan-out:

   | Depth | Analysts | Verifiers | When |
   |-------|----------|-----------|------|
   | Light | 1 | 1 | Narrow question, low stakes |
   | Standard (default) | 2–3 | 2 | Most real analysis |
   | Deep | 3–5 | 3 | High stakes, or answers disagree |

5. If the question turns out trivial or already answered, stop here and answer directly.

### Phase 1 — Independent analysis (Opus subagents, parallel)

Dispatch all analysts **in one message** so they run concurrently. Each is
`subagent_type: general-purpose`, `model: opus`, fresh context. Give each a
**distinct lens** so they do not converge trivially — mechanism-first,
evidence-first, counterexample-first, or one hypothesis per analyst. Each analyst:

- Gathers evidence with tools **before** asserting anything. No guessing.
- Labels every claim `[evidenced: <file:line / command / source>]` or `[inferred]`.
- Returns a structured conclusion: answer, key evidence, confidence, open questions.

Ask for conclusions and the evidence behind them — **never ask a subagent to
transcribe or echo its chain of thought** (see the pitfall note below). The
ready-to-paste analyst prompt is in `references/subagent-prompts.md`.

### Phase 2 — Adversarial verification (Opus subagents, parallel)

Collect the analysts' conclusions. Dispatch fresh Opus verifiers whose **sole job
is to refute** each surviving conclusion: find the strongest counter-evidence, the
missed case, the wrong assumption, the unverified leap. Verifiers must check
against the **actual artifacts**, not just re-read the analyst's text. Default to
skeptical: if a verifier cannot confirm a claim, it marks it *unverified*, not
*true*. A claim survives only if no verifier refutes it (with multiple verifiers,
require a majority to uphold). The verifier prompt is in
`references/subagent-prompts.md`.

### Phase 3 — Synthesize (orchestrator)

- Keep only conclusions that survived Phase 2.
- Report **outcome first**: the answer in one or two sentences, then supporting detail.
- State confidence per claim, with evidence pointers.
- List explicitly what remains **unverified or unknown**. Do not launder inferred claims into confident ones.
- Write for a reader who did not watch the run: complete sentences, spelled-out terms, no working shorthand, no invented labels.

## Rules to bake into every subagent prompt

- Evidence before assertion; label evidenced vs. inferred.
- Before submitting, try to disprove your own conclusion.
- Return conclusions and evidence, not a narration of your reasoning.
- Match effort to stakes; don't pad a small finding.

## Anti-patterns

- Running verifiers inside an analyst's context (no fresh eyes → no real check).
- Giving every analyst the same prompt — they converge and the ensemble adds nothing. Vary the lens.
- Reporting an analyst's own confidence as the final answer without Phase 2.
- Firing up the full fan-out for a question that a direct answer would settle.
- Letting the invoking model do the analysis instead of Opus subagents.

## Maintainer notes

- **Subagents are pinned to `model: opus`** on purpose — that is the "executed on Opus" contract. If you drop the override, verifiers inherit the orchestrator's model (a fork inherits the parent; only a fresh `general-purpose` agent honors a `model` override), and the guarantee is lost.
- **Do not add "show your reasoning / explain your thinking" instructions** to any prompt here. When the orchestrator is Fable 5, instructions to reproduce internal reasoning can trigger the `reasoning_extraction` refusal category and elevate fallbacks to Opus 4.8. Ask for conclusions plus evidence instead. This is why every template asks for structured output, never a thought transcript.
