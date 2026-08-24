<?php

/*
 * Die statische Gegenprobe zu CityFormRedirectStaysRegionFreeTest und
 * CityFormCancelLinkStaysRegionFreeTest. Jene beiden fahren die vier heute
 * vorhandenen Aufrufe in cities/create und cities/edit verhaltensmaessig durch —
 * dieser hier deckt den Fall ab, den sie nicht sehen koennen: dass jemand einen
 * FUENFTEN, region-bewussten Aufruf ERGAENZT, ohne einen bestehenden zu aendern.
 * Ein neuer Aufruf hat keinen Test, der ihn durchfaehrt; er faellt nur auf, wenn
 * die Datei als Ganzes geprueft wird.
 *
 * Warum die Region hier schaedlich waere: bekaeme ein Ziel in diesen beiden
 * Formularen eine Region, wuerde auf portal.bitcoindiana.org jedes Speichern einer
 * US-Stadt nach /us/in/cities fuehren — und cities/index.blade.php filtert hart auf
 * cities.region_id. Wer eine Stadt ohne Region oder in einem anderen Bundesstaat
 * anlegt, saehe sie danach nicht mehr.
 *
 * Geprueft wird auf ABWESENHEIT der regionsfaehigen Formen, nicht auf die Zahl vier:
 * ein fuenfter regionsFREIER Aufruf ist erlaubt und soll den Test nicht roeten.
 */
$formulare = [
    'resources/views/livewire/cities/create.blade.php',
    'resources/views/livewire/cities/edit.blade.php',
];

it('ruft in den Stadt-Formularen keinen regionsfaehigen Routen-Helfer auf', function (string $pfad) {
    $quelle = file_get_contents(base_path($pfad));

    expect($quelle)->not->toContain('country_or_region_route');
})->with($formulare);

it('nennt in den Stadt-Formularen keine Regionsroute direkt beim Namen', function (string $pfad) {
    $quelle = file_get_contents(base_path($pfad));

    expect($quelle)
        ->not->toContain('cities.index-region')
        ->not->toContain('meetups.index-region')
        ->not->toContain('meetups.map-region');
})->with($formulare);
