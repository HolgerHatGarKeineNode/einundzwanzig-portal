<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

/*
|--------------------------------------------------------------------------
| Wait for a real readiness condition, not for the load event
|--------------------------------------------------------------------------
|
| A page is "loaded" when its resources have arrived; an Alpine component is
| usable when Alpine has initialised its x-data. Between the two the markup the
| server rendered is already visible and clickable, and a click in that gap
| lands on an element that has no handler yet. Under full-suite load the gap is
| long enough to matter: CalendarStreamPickerTest clicked the trigger right after
| a reload and failed with "Timeout 1000ms exceeded" in 3 of 6 full runs, never
| in a serial run (2026-10-25).
|
| Alpine sets `_x_dataStack` on an element once it has initialised its scope, so
| that is what is awaited — polled per animation frame, bounded, and failing
| with a message instead of timing out somewhere downstream.
*/
final class Ready
{
    /**
     * Waits until the x-data scope that contains the element is initialised.
     */
    public static function alpine(object $page, string $selector): void
    {
        $selectorJson = json_encode($selector, JSON_THROW_ON_ERROR);

        $ready = $page->script(<<<JS
            (async () => {
                for (let frame = 0; frame < 600; frame++) {
                    const element = document.querySelector({$selectorJson});
                    const scope = element ? element.closest('[x-data]') : null;

                    if (scope && scope._x_dataStack !== undefined) {
                        return true;
                    }

                    await new Promise((resolve) => requestAnimationFrame(resolve));
                }

                return false;
            })()
        JS);

        expect($ready)->toBeTrue("Alpine never initialised the x-data scope around {$selector}.");
    }
}
