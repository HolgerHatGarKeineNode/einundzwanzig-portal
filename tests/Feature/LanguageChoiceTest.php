<?php

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Livewire\Livewire;

it('keeps the language a guest picked before logging in', function () {
    /*
     * Der gemeldete Ablauf: abmelden, Sprache umstellen, anmelden — und die Sprache
     * springt auf die zurueck, die am Konto gespeichert ist. Der Listener des
     * lang-country-Pakets stellt sie beim Login bedingungslos zurueck.
     */
    $user = User::factory()->create(['lang_country' => 'en-US']);

    session(['lang_country_chosen' => 'cs-CZ']);

    $this->actingAs($user);
    event(new Login('web', $user, false));

    expect(session('lang_country'))->toBe('cs-CZ')
        ->and(session('locale'))->toBe('cs')
        // Damit die Wahl auch den naechsten Login ueberlebt.
        ->and($user->fresh()->lang_country)->toBe('cs-CZ');
});

it('leaves the stored language alone when the guest picked nothing', function () {
    $user = User::factory()->create(['lang_country' => 'en-US']);

    $this->actingAs($user);
    event(new Login('web', $user, false));

    expect($user->fresh()->lang_country)->toBe('en-US');
});

it('ignores a language that is not in the allowed list', function () {
    // Der Wert stammt aus der Session und darf nie ungeprueft in die Datenbank.
    $user = User::factory()->create(['lang_country' => 'en-US']);

    session(['lang_country_chosen' => 'xx-XX']);

    $this->actingAs($user);
    event(new Login('web', $user, false));

    expect($user->fresh()->lang_country)->toBe('en-US');
});

it('switches the language in a single request instead of racing two', function () {
    /*
     * Vorher standen wire:model.live und wire:change nebeneinander. Livewire 4 faehrt
     * Property-Updates parallel, also lief updateLanguage() mit dem Snapshot VOR der
     * Aenderung und leitete auf die alte Sprache um. Der Lifecycle-Hook sieht den
     * neuen Wert, weil er im selben Request laeuft.
     */
    Livewire::test('language.selector')
        ->set('langCountry', 'cs-CZ')
        ->assertRedirect(route('lang_country.switch', ['lang_country' => 'cs-CZ']));

    expect(session('lang_country_chosen'))->toBe('cs-CZ');
});

it('no longer carries a wire:change handler on the language select', function () {
    /*
     * Regressionsanker: die zweite Anfrage war die Ursache, nicht ein Symptom.
     * Geprueft wird das gerenderte HTML, nicht die Quelldatei — deren PHP-Doc nennt
     * `wire:change` absichtlich, um zu erklaeren, warum es weg ist.
     */
    Livewire::test('language.selector')
        ->assertDontSee('wire:change', false);
});
