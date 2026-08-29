<?php

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Auth;
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

it('ignores an empty selection from the searchable listbox instead of throwing', function () {
    /*
     * Gemeldeter Fehler (ErrorException: Array to string conversion): die durchsuchbare
     * flux:select-Listbox sendet beim Leeren der Auswahl `[]` statt eines Strings.
     * Ungeprueft landete das als Routen-Parameter in redirectRoute() und
     * RouteUrlGenerator versuchte, das Array in die URL einzusetzen.
     */
    Livewire::test('language.selector')
        ->set('langCountry', [])
        ->assertNoRedirect();

    expect(session('lang_country_chosen'))->toBeNull();
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

it('runs after the package listener that would reset the language', function () {
    /*
     * Der Fix haengt an dieser Reihenfolge: der Listener des lang-country-Pakets setzt
     * die Session auf den Kontowert, unserer korrigiert danach. Kehrt sich das um,
     * gewinnt wieder der Kontowert — und der Bug ist zurueck, ohne dass ein anderer
     * Test etwas merkt.
     */
    $listeners = app('events')->getRawListeners()[Login::class] ?? [];

    $positionOf = function (array $listeners, string $needle): ?int {
        foreach (array_values($listeners) as $index => $listener) {
            if (is_string($listener) && str_contains($listener, $needle)) {
                return $index;
            }
        }

        return null;
    };

    $package = $positionOf($listeners, 'Stefro\\LaravelLangCountry');
    $ours = $positionOf($listeners, 'ApplyChosenLanguageAfterLogin');

    expect($package)->not->toBeNull()
        ->and($ours)->not->toBeNull()
        ->and($ours)->toBeGreaterThan($package);
});

it('survives the session id migration that Auth::login performs', function () {
    /*
     * Auth::login() ruft Session::migrate(destroy: true). Der Kommentar im
     * Lightning-Controller nennt das einen Wipe des Payloads — das stimmt nicht:
     * migrate() zerstoert nur den alten Eintrag im Store und vergibt eine neue ID,
     * die Attribute bleiben. Gemessen, weil der ganze Fix daran haengt.
     */
    $user = User::factory()->create(['lang_country' => 'en-US']);

    session(['lang_country_chosen' => 'de-DE']);
    LangCountry::setAllSessions('de-DE');

    Auth::login($user);

    expect(session('lang_country_chosen'))->toBe('de-DE')
        ->and(session('lang_country'))->toBe('de-DE')
        ->and(session('locale'))->toBe('de')
        ->and($user->fresh()->lang_country)->toBe('de-DE');
});

it('never lets the browser header decide the language on an unknown domain', function () {
    /*
     * Lokal, hinter einem CNAME oder auf einem Vorschau-Host griff DomainMiddleware
     * nicht — und LangCountrySession riet die Sprache aus HTTP_ACCEPT_LANGUAGE. Beim
     * ersten Login schrieb sie den geratenen Wert ungefragt ins Konto, und ab da holte
     * der Login-Listener des Pakets ihn jedes Mal zurueck. Das ist die Herkunft eines
     * en-US-Kontos, das nie jemand gewaehlt hat.
     */
    $user = User::factory()->create(['lang_country' => null]);

    $this->withServerVariables(['HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9'])
        ->actingAs($user)
        // '/' leitet auf die laenderpraefixierte Startseite um; die Middleware ist
        // auf beiden Requests dieselbe, gepruefte Wirkung ist die Session danach.
        ->get('/')
        ->assertRedirect();

    expect(session('lang_country'))->toBe('de-DE')
        ->and(session('locale'))->toBe('de')
        /*
         * Und das Konto bleibt leer: LangCountrySession schreibt nur, wenn sie selbst
         * raten musste. Weil die Session jetzt schon gefuellt ist, laeuft sie gar nicht
         * erst in diesen Zweig — nichts Geratenes bleibt haengen. Ein Wert steht dort
         * erst, wenn jemand bewusst waehlt.
         */
        ->and($user->fresh()->lang_country)->toBeNull();
});
