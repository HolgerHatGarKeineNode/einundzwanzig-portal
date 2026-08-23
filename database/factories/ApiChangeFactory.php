<?php

namespace Database\Factories;

use App\Models\ApiChange;
use App\Support\Broadcasting\ChangeRecorder;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiChange>
 */
class ApiChangeFactory extends Factory
{
    protected $model = ApiChange::class;

    public function definition(): array
    {
        $resource = fake()->randomElement([
            'meetup', 'meetup-event', 'city', 'course', 'course-event', 'lecturer',
        ]);
        $action = fake()->randomElement(['created', 'updated', 'deleted']);
        $resourceId = fake()->numberBetween(1, 9999);
        $occurredAt = fake()->dateTimeBetween('-60 days', 'now');

        return [
            'resource' => $resource,
            'resource_id' => $resourceId,
            'action' => $action,
            'country_code' => null,
            'city_id' => null,
            'payload' => [
                'action' => $action,
                'resource' => $resource,
                'id' => $resourceId,
                'sequence' => null,
                'occurred_at' => $occurredAt->format(DATE_ATOM),
                'api_version' => (string) config('scramble.info.version'),
                'data' => $action === 'deleted' ? null : ['id' => $resourceId],
                'links' => ['self' => null],
            ],
            'occurred_at' => $occurredAt,
        ];
    }

    /**
     * Eine Zeile zu einer bestimmten Ressource — Spalte UND Payload.
     *
     * Nur die Spalte zu ueberschreiben reichte nicht: der Filter von
     * `GET /api/changes` liest die Spalte, der Konsument liest das Payload. Fielen die
     * beiden auseinander, waere ein Test ueber den Filter gruen, waehrend die Antwort
     * Eintraege einer anderen Ressource ausweist.
     */
    public function forResource(string $resource): static
    {
        return $this->state(fn (array $attributes): array => [
            'resource' => $resource,
            'payload' => [...$attributes['payload'], 'resource' => $resource],
        ]);
    }

    /**
     * Traegt `sequence` nach, so wie es der Recorder tut.
     *
     * Die Sequenz IST die Zeilen-ID und steht erst nach dem Insert fest
     * ({@see ChangeRecorder::record()}). Ohne diesen Schritt
     * erzeugte die Factory Zeilen, die es in Wirklichkeit nicht gibt: mit
     * `payload['sequence'] === null` — und jeder Test, der den Resync-Cursor aus dem
     * ausgelieferten Payload liest, liefe gegen genau dieses Loch.
     */
    public function configure(): static
    {
        return $this->afterCreating(function (ApiChange $change): void {
            if (($change->payload['sequence'] ?? null) !== null) {
                return;
            }

            $change->forceFill([
                'payload' => [...$change->payload, 'sequence' => $change->id],
            ])->save();
        });
    }

    /**
     * Eine Zeile, die der Prune-Lauf abraeumen soll.
     */
    public function olderThan(int $days): static
    {
        return $this->state(fn (array $attributes): array => [
            'occurred_at' => now()->subDays($days)->subHour(),
        ]);
    }
}
