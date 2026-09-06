<?php

declare(strict_types=1);

return [
    'next' => 'Következő &raquo;',
    'previous' => '&laquo; Előző',

    // 'showing' is DELIBERATELY absent, not forgotten (#126).
    //
    // The line it feeds used to be assembled from four separate keys, each translated on its
    // own: "Bemutató 1 hogy 15 a 42 eredmények" — "a demonstration 1 that 15 the 42 results".
    // Hungarian cannot be built from those fragments in that order, so the four values were not
    // a translation to preserve. Replacing them with a fifth machine translation is the defect
    // #126 reports, so this key waits for a native speaker instead.
    //
    // A missing key in a group file falls back to the fallback locale per key (Translator::get()
    // walks localeArray(), unlike a JSON key, which has no cross-locale fallback at all), so a
    // Hungarian visitor reads the English sentence until someone reviews a Hungarian one.
];
