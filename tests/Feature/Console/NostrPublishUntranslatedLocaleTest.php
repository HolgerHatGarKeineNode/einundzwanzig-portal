<?php

use App\Models\City;
use App\Models\Country;
use App\Models\Meetup;
use App\Models\MeetupEvent;
use App\Traits\NostrTrait;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

/*
 * `nostr:publish` used the country code as the locale. Laravel does not fall back for
 * JSON translations, so every country without a lang/<code>.json — France, the US,
 * the UK, Italy, and Czechia (code `cz`, file `cs.json`) — published the bare key
 * `nostr.meetup_event_text` as its Nostr note.
 */

function untranslatedLocaleMeetupEvent(string $countryCode): MeetupEvent
{
    $city = City::factory()->create([
        'country_id' => Country::factory()->create(['code' => $countryCode])->id,
    ]);

    return MeetupEvent::factory()->create([
        'meetup_id' => Meetup::factory()->create(['city_id' => $city->id])->id,
        'start' => now()->addDay(),
        // The factory sets a status in 10 % of calls, which would take the record out of the queue.
        'nostr_status' => null,
    ]);
}

function publishedNoteText(): ?string
{
    $published = null;

    // `noscl publish <text>` is invoked with an argument array; the note text is its third element.
    Process::fake(function (PendingProcess $process) use (&$published): string {
        $published = (string) ($process->command[2] ?? '');

        return 'note1'.str_repeat('a', 58);
    });

    test()->artisan('nostr:publish', ['--model' => 'MeetupEvent'])->assertExitCode(0);

    return $published;
}

it('publishes a translated note for a country without a language file of its own', function (string $countryCode, string $expectedOpening) {
    $event = untranslatedLocaleMeetupEvent($countryCode);

    $text = publishedNoteText();

    expect($text)->not->toContain('nostr.')
        ->and($text)->toStartWith($event->meetup->name)
        ->and($text)->toContain($expectedOpening);
})->with([
    'France falls back to English' => ['FR', 'has scheduled a new event:'],
    'United States falls back to English' => ['us', 'has scheduled a new event:'],
    'Czechia maps to cs.json' => ['CZ', 'vypisuje nový termín:'],
    'Austria maps to de.json' => ['AT', 'hat einen neuen Termin eingestellt:'],
]);

it('returns no text, and so publishes nothing, when the locale has no translation', function () {
    $event = untranslatedLocaleMeetupEvent('de');
    $publisher = new class {
        use NostrTrait;
    };

    app()->setLocale('xx');

    expect($publisher->getText($event, 'de'))->toBeNull();

    app()->setLocale('de');

    expect($publisher->getText($event, 'de'))->toContain('hat einen neuen Termin eingestellt:');
});
