<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use LangCountry;

/**
 * Bewahrt die Sprachwahl, die ein Gast VOR dem Anmelden getroffen hat.
 *
 * `stefro/laravel-lang-country` haengt einen eigenen Listener an dasselbe Login-Event
 * (`LaravelLangCountryServiceProvider::boot()`), der die Session bedingungslos auf
 * `users.lang_country` zurueckstellt. Wer sich abmeldet, auf Tschechisch stellt und
 * sich wieder anmeldet, landete dadurch wieder in der Sprache, die irgendwann einmal
 * am Konto gespeichert wurde — meist die, die `LangCountrySession` beim allerersten
 * Besuch aus `HTTP_ACCEPT_LANGUAGE` geraten und ungefragt persistiert hatte.
 *
 * Dieser Listener laeuft danach — Laravels Event-Discovery findet ihn in app/Listeners
 * und haengt ihn hinter den des Pakets (der Test haelt diese Reihenfolge fest, weil der
 * Fix an ihr haengt). Er dreht die Reihenfolge der Wirkung um: eine ausdrueckliche Wahl schlaegt den gespeicherten Wert
 * und wird zugleich am Konto festgeschrieben, damit sie den naechsten Login ueberlebt.
 *
 * Nur eine ausdrueckliche Wahl zaehlt — `lang_country_chosen` setzt allein der
 * Sprachwaehler. Eine von der Middleware geratene Sprache greift hier nicht ein.
 */
class ApplyChosenLanguageAfterLogin
{
    public function handle(Login $event): void
    {
        $chosen = session('lang_country_chosen');

        if (! is_string($chosen) || ! in_array($chosen, config('lang-country.allowed', []), true)) {
            return;
        }

        LangCountry::setAllSessions($chosen);

        $user = $event->user;

        if (array_key_exists('lang_country', $user->getAttributes()) && $user->lang_country !== $chosen) {
            $user->lang_country = $chosen;
            $user->save();
        }
    }
}
