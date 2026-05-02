<?php

use Livewire\Livewire;

it('rejects non-string updates to currentCountry to prevent TypeError on string-typed property', function () {
    Livewire::test('country.chooser')
        ->set('currentCountry', [])
        ->assertStatus(422);
});
