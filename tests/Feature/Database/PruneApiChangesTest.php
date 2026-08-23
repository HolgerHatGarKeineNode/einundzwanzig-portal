<?php

use App\Models\ApiChange;
use Illuminate\Console\Scheduling\Schedule;

it('deletes changes older than the retention window and keeps younger ones', function (): void {
    $stale = ApiChange::factory()->create(['occurred_at' => now()->subDays(31)]);
    $edge = ApiChange::factory()->create(['occurred_at' => now()->subDays(30)->addMinute()]);
    $fresh = ApiChange::factory()->create(['occurred_at' => now()->subDay()]);

    $this->artisan('api-changes:prune')->assertSuccessful();

    expect(ApiChange::query()->whereKey($stale->id)->exists())->toBeFalse()
        ->and(ApiChange::query()->whereKey($edge->id)->exists())->toBeTrue()
        ->and(ApiChange::query()->whereKey($fresh->id)->exists())->toBeTrue();
});

it('honours an explicit retention window', function (): void {
    $old = ApiChange::factory()->create(['occurred_at' => now()->subDays(10)]);
    $recent = ApiChange::factory()->create(['occurred_at' => now()->subDays(2)]);

    $this->artisan('api-changes:prune', ['--days' => 7])->assertSuccessful();

    expect(ApiChange::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(ApiChange::query()->whereKey($recent->id)->exists())->toBeTrue();
});

it('refuses a retention window below one day', function (): void {
    // Ein Fenster von 0 Tagen wuerde das Log beim naechsten Lauf leeren und damit den
    // Resync-Weg abschalten — lieber ein Fehlschlag als eine stille Loeschung.
    $change = ApiChange::factory()->create(['occurred_at' => now()->subYear()]);

    $this->artisan('api-changes:prune', ['--days' => 0])->assertFailed();

    expect(ApiChange::query()->whereKey($change->id)->exists())->toBeTrue();
});

it('is scheduled daily', function (): void {
    $events = collect(app(Schedule::class)->events())
        ->filter(fn ($event) => str_contains((string) $event->command, 'api-changes:prune'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 4 * * *');
});
