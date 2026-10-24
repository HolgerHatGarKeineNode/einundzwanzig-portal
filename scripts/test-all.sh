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
# everything it started (paratest workers, the Playwright server, Chromium) and
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

# timeout runs the command in its own process group and signals the whole
# group, which is what reaches the grandchildren. -k escalates to SIGKILL.
timeout --kill-after=15 "$BUDGET" bash -c run_all
status=$?
elapsed=$((SECONDS - STARTED))

if [ "$status" -eq 124 ] || [ "$status" -eq 137 ]; then
    echo "" >&2
    echo "TEST BUDGET EXCEEDED: the suite was still running after ${BUDGET}s and was killed (${elapsed}s elapsed)." >&2
    echo "A suite over budget is a bug before it is a feature: profile it, then optimise, merge or delete tests." >&2
    exit 124
fi

echo ""
echo "Suite finished in ${elapsed}s of a ${BUDGET}s budget (exit ${status})."

exit "$status"
