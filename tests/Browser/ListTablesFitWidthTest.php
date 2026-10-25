<?php

use App\Models\City;
use App\Models\Course;
use App\Models\CourseEvent;
use App\Models\Lecturer;
use App\Models\Region;
use App\Models\SelfHostedService;
use App\Models\User;

/*
 * The list tables must never scroll sideways. Flux renders them
 * `whitespace-nowrap` inside an `overflow-auto` scroll area; measured on
 * production as guest on 2026-10-25, before `data-table-stack`:
 *
 *   overflow in px    390   820  1100  1280
 *   courses           544   114   138     0
 *   lecturers         319     0     0     0
 *   cities            597   167   191    11
 *   services          692   262   286   106
 *
 * Every page gets long content (the course description is a bare URL: one
 * stuck out 19px at 390px on production after the first fix), and the logged-in creator sees the edit
 * buttons, which are the widest fixed content in a row. The meetup list has
 * its own test (MeetupTableFitsWidthTest).
 */

beforeEach(function () {
    $this->creator = User::factory()->create(['name' => 'Satoshi Nakamoto der Allererste Plebejer']);

    $country = portalCountry();
    $region = Region::factory()->create(['country_id' => $country->id, 'name' => 'Mecklenburg-Vorpommern-Nordwestküste']);
    $city = City::factory()->create([
        'country_id' => $country->id,
        'region_id' => $region->id,
        'name' => 'Neumarkt in der Oberpfalz an der Sulz und Umgebung',
        'created_by' => $this->creator->id,
    ]);

    $lecturer = Lecturer::factory()->create([
        'name' => 'Dr. Friedrich August von Hayek-Rothbard Mises',
        'created_by' => $this->creator->id,
    ]);
    $course = Course::factory()->create([
        'lecturer_id' => $lecturer->id,
        'name' => 'Bitcoin für Fortgeschrittene: Lightning, Multisig und Verwahrung',
        'description' => 'https://vhskurse.paderborn.de/webbasys/index.php?kathaupt=11&knr=bitcoin',
        'created_by' => $this->creator->id,
    ]);
    CourseEvent::factory()->create([
        'course_id' => $course->id,
        'city_id' => $city->id,
        'from' => now()->addWeek(),
        'to' => now()->addWeek()->addHours(3),
    ]);

    SelfHostedService::factory()->create([
        'name' => 'Einundzwanzig Mempool Explorer mit Lightning Node und BTCPay',
        'url_clearnet' => 'https://mempool.einundzwanzig-neumarkt-oberpfalz.example.org',
        'url_onion' => str_repeat('a', 56).'.onion',
        'url_i2p' => 'http://'.str_repeat('b', 52).'.b32.i2p',
        'url_pkdns' => 'https://pkdns.example.org',
        'ip' => '203.0.113.254',
        'created_by' => $this->creator->id,
    ]);

    $this->measure = function ($page, int $width): array {
        $page->resize($width, 900)->wait(1.0);
        $page->script('document.documentElement.getBoundingClientRect().width');
        $page->wait(0.4);

        return $page->script(<<<'JS'
            (() => {
              const area = document.querySelector('ui-table-scroll-area');
              return {
                found: !! area,
                areaScroll: area ? area.scrollWidth : 0,
                areaClient: area ? area.clientWidth : 0,
                docScroll: document.documentElement.scrollWidth,
                docClient: document.documentElement.clientWidth,
              };
            })()
        JS);
    };
});

it('fits the list table into the viewport without horizontal scrolling', function (string $path, string $marker, bool $asCreator) {
    if ($asCreator) {
        $this->actingAs($this->creator);
    }

    $page = visit('/de/'.$path)->assertSee($marker);

    foreach ([390, 820, 1100, 1280, 1600] as $width) {
        $m = ($this->measure)($page, $width);

        expect($m['found'])->toBeTrue("{$path} at {$width}px: no table");
        expect($m['areaScroll'])->toBeLessThanOrEqual($m['areaClient'], sprintf(
            '%s at %spx: the table scrolls sideways (%s > %s)', $path, $width, $m['areaScroll'], $m['areaClient']
        ));
        expect($m['docScroll'])->toBeLessThanOrEqual($m['docClient'], sprintf(
            '%s at %spx: the page scrolls sideways (%s > %s)', $path, $width, $m['docScroll'], $m['docClient']
        ));
    }

    $page->assertNoJavaScriptErrors();
})->with([
    'courses' => ['courses', 'Bitcoin für Fortgeschrittene'],
    'lecturers' => ['lecturers', 'Hayek-Rothbard'],
    'cities' => ['cities', 'Neumarkt in der Oberpfalz'],
    'services' => ['services', 'Mempool Explorer'],
])->with(['guest' => false, 'creator' => true]);

it('fits the api token table into the viewport without horizontal scrolling', function () {
    $this->actingAs($this->creator);
    $this->creator->createToken('Externer Kurs-Sync für die Volkshochschule Paderborn und Umgebung');
    $this->creator->createToken('Laptop');

    $page = visit('/de/settings/api-tokens')->assertSee('Volkshochschule');

    foreach ([390, 820, 1100, 1280, 1600] as $width) {
        $m = ($this->measure)($page, $width);

        expect($m['found'])->toBeTrue("api-tokens at {$width}px: no table");
        expect($m['areaScroll'])->toBeLessThanOrEqual($m['areaClient'], sprintf(
            'api-tokens at %spx: the table scrolls sideways (%s > %s)', $width, $m['areaScroll'], $m['areaClient']
        ));
        expect($m['docScroll'])->toBeLessThanOrEqual($m['docClient'], sprintf(
            'api-tokens at %spx: the page scrolls sideways (%s > %s)', $width, $m['docScroll'], $m['docClient']
        ));
    }

    $page->assertNoJavaScriptErrors();
});
