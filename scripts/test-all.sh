#!/usr/bin/env bash
#
# The whole suite (Unit, Feature, Components, Browser) in one command, under a
# wall-clock budget that is ENFORCED, not hoped for.
#
#   composer test:all
#   TEST_BUDGET_SECONDS=5 composer test:all     # proves the guard: exits 124
#
# Budget: 600 s (user rule of 2026-10-23: the whole suite stays under ten
# minutes). A run that is still going at the limit is killed together with
# everything it started (paratest workers, the Playwright server, Chromium; the
# run lives in its own session and the script reaps that session by id) and
# the script exits 124 with a message on stderr, so a CI step or an agent cannot
# mistake it for a pass. A failing test exits with that test's own status.
#
# Two pest runs, because the two halves want different parallelism:
#   - Unit + Feature + Components: CPU-bound, one worker per core;
#   - Browser: every worker drives its own Chromium against one shared
#     Playwright server. At 24 workers on a 24-thread host 6 extra browser tests
#     failed with "Timeout 5000ms exceeded" that pass at 8 (measured
#     2026-10-24), so the Browser run is capped at TEST_BROWSER_PROCESSES.
#
# The Components suite is empty today (no resources/views/**/*.test.php); the
# non-browser run still passes because the other suites carry tests.
#
set -uo pipefail

cd "$(dirname "$0")/.."

BUDGET="${TEST_BUDGET_SECONDS:-600}"
BROWSER_PROCESSES="${TEST_BROWSER_PROCESSES:-8}"
STARTED=$SECONDS

run_all() {
    # Not `set -e` and no early exit between the two runs: a red first half
    # must not hide a red second half, and the exit status is the worst of both.
    local status=0

    vendor/bin/pest --parallel --exclude-testsuite=Browser --compact || status=$?
    vendor/bin/pest --parallel --processes="$BROWSER_PROCESSES" --testsuite=Browser --compact || status=$?

    return "$status"
}

export -f run_all
export BROWSER_PROCESSES

# THE RUN GETS ITS OWN SESSION so that everything it starts can be found again
# by session id. `timeout` signals its process group, and that is not enough:
# measured 2026-10-25, after exit 124 the Playwright server
# (`node ./node_modules/.bin/playwright run-server`) outlived the run, reparented
# to systemd, because `timeout --kill-after` only escalates while its direct
# child is alive. Chromium dies, the node server does not. So the budget is
# still enforced by timeout, and afterwards (also on Ctrl-C, on a red run and on
# a green one) everything left in the run's session is reaped by session id.
SESSION_FILE="$(mktemp)"
SESSION=""

reap_session() {
    [ -z "$SESSION" ] && [ -s "$SESSION_FILE" ] && SESSION="$(cat "$SESSION_FILE")"

    if [ -n "$SESSION" ]; then
        pkill -TERM -s "$SESSION" 2>/dev/null
        sleep 2
        pkill -KILL -s "$SESSION" 2>/dev/null
    fi

    rm -f "$SESSION_FILE"
}

trap 'reap_session; exit 130' INT TERM
trap reap_session EXIT

# setsid --wait: waits and passes the status through even when it has to fork
# (it does when the caller is a process group leader). The inner bash is a
# session leader, so its pid is the session id.
# `exec timeout` keeps that pid; timeout's own setpgid stays inside the session.
setsid --wait bash -c 'echo $$ > "$0"; exec timeout --kill-after=15 "$1" bash -c run_all' "$SESSION_FILE" "$BUDGET"
status=$?
elapsed=$((SECONDS - STARTED))

reap_session

# The budget message is only true if the budget is what ended the run: 124 is
# timeout's own status, and 137 (SIGKILL) only counts when the clock ran out.
# A 137 or 143 before that is somebody else's kill (OOM, a manual kill) and is
# reported as what it is.
if [ "$status" -eq 124 ] || { [ "$status" -ge 128 ] && [ "$elapsed" -ge "$BUDGET" ]; }; then
    echo "" >&2
    echo "TEST BUDGET EXCEEDED: the suite was still running after ${BUDGET}s and was killed (${elapsed}s elapsed)." >&2
    echo "A suite over budget is a bug before it is a feature: profile it, then optimise, merge or delete tests." >&2
    exit 124
fi

if [ "$status" -ge 128 ]; then
    echo "" >&2
    echo "TEST RUN KILLED BY SIGNAL $((status - 128)) after ${elapsed}s of a ${BUDGET}s budget: not a budget overrun (OOM killer or a manual kill?)." >&2
    exit "$status"
fi

echo ""
echo "Suite finished in ${elapsed}s of a ${BUDGET}s budget (exit ${status})."

exit "$status"
