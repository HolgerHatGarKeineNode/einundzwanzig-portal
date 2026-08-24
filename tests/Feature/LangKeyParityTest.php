<?php

/*
|--------------------------------------------------------------------------
| Alle Sprach-JSONs muessen dieselbe Schluesselmenge tragen. Ein Vergleich
| der reinen Anzahl faengt nicht, wenn ein Callout nur auf Englisch existiert
| und dafuer ein anderer Schluessel in dieser Datei fehlt — zwei Luecken, die
| sich in der Summe aufheben. Nur der Mengenvergleich faengt beides.
|--------------------------------------------------------------------------
*/

it('has the exact same set of translation keys in every lang/*.json file', function () {
    $files = collect(glob(lang_path('*.json')))->sort()->values();

    expect($files)->not->toBeEmpty();

    $reference = $files->first();
    $referenceKeys = collect(json_decode(file_get_contents($reference), true))->keys()->sort()->values();

    foreach ($files as $file) {
        $keys = collect(json_decode(file_get_contents($file), true))->keys()->sort()->values();

        $missing = $referenceKeys->diff($keys)->values();
        $extra = $keys->diff($referenceKeys)->values();

        expect($missing->all())->toBe([], basename($file).' is missing keys present in '.basename($reference).': '.$missing->implode(', '));
        expect($extra->all())->toBe([], basename($file).' has keys not present in '.basename($reference).': '.$extra->implode(', '));
    }
});
