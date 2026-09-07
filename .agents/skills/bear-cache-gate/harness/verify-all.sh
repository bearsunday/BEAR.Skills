#!/usr/bin/env bash
# The cache gate: every flow's log must prove itself, and the app suite must stay green.
set -uo pipefail
cd "$(cd "$(dirname "$0")/../.." && pwd)" || exit 2

# Overridable, because a cache oracle that only runs on one machine proves nothing anywhere else.
export DATABASE_URL=${DATABASE_URL:-'mysql://root@127.0.0.1:3306/eccubedb_test?charset=utf8mb4&serverVersion=8.0.0'}
export CACHE_DSN=${CACHE_DSN:-'redis://127.0.0.1:6379'}
export APP_CONTEXT=${APP_CONTEXT:-cli-fake-hal-app}

PHP=${PHP:-php}
command -v "$PHP" > /dev/null || { echo "no php at $PHP"; exit 2; }

# Say what was judged, all of it. A cache verdict belongs to one interpreter, one context and - when
# the package is a path repository - one library commit. A green run against the wrong checkout is
# how a fixed defect appears to come back.
printf 'php            %s\n' "$("$PHP" -r 'echo PHP_VERSION;')"
printf 'context        %s (cache %s)\n' "$APP_CONTEXT" "${CACHE_DSN:-filesystem}"

# -f, because a Composer path repository usually links relatively (../../../BEAR.QueryRepository):
# bare readlink returns that string, the -d test fails against the repository root, and the stamp
# this block exists to print disappears without saying so.
LIB=${QUERY_REPOSITORY_PATH:-$(readlink -f vendor/bear/query-repository 2> /dev/null)}
if [ -n "$LIB" ] && [ -d "$LIB/.git" ]; then
    lib_branch=$(git -C "$LIB" branch --show-current)
    lib_sha=$(git -C "$LIB" rev-parse --short HEAD)
    lib_dirty=$(git -C "$LIB" status --porcelain -- src src-annotation | head -1)
    printf 'library        %s %s%s\n' "$lib_branch" "$lib_sha" "${lib_dirty:+ (uncommitted src changes)}"
else
    printf 'library        NOT A CHECKOUT - the verdict cannot be attributed to a library commit\n'
fi

status=0
for flow in help help-revalidate help-cdn help-cache-down agent-catalog agent-product products-app products-page product-stock products-corpus-tag shopping-complete customer-profile; do
    if "$PHP" var/loop/verify-cache.php "$flow" > "var/loop/last-$flow.txt" 2>&1; then
        printf 'oracle %-14s ok\n' "$flow"
    else
        printf 'oracle %-14s FAIL\n' "$flow"
        tail -5 "var/loop/last-$flow.txt"
        status=1
    fi
done

# CI runs the suite as its own step, with its own memory limit. A second run judges nothing new.
if [ "${GATE_SUITE:-1}" != 1 ]; then
    printf 'phpunit        skipped (GATE_SUITE=%s)\n' "$GATE_SUITE"
elif "$PHP" ./vendor/bin/phpunit --no-coverage > var/loop/last-phpunit.txt 2>&1; then
    printf 'phpunit        ok  %s\n' "$(tail -1 var/loop/last-phpunit.txt)"
else
    printf 'phpunit        FAIL\n'
    grep -E "^[0-9]+\)|Tests:" var/loop/last-phpunit.txt | head -6
    status=1
fi

exit $status
