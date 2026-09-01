<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An outbound webhook subscription for meetup/meetup-event changes (Issue #36).
 *
 * `approved_at` and `active` are independent on purpose: an operator-controlled
 * approval gate and an owner-controlled pause switch. See the migration's
 * docblock for the full reasoning and {@see self::eligibleForDelivery()} for
 * where the two (plus `disabled_at`) are combined into "may receive a delivery".
 *
 * @property int $id
 * @property int $user_id
 * @property string $url
 * @property string $secret
 * @property bool $reveal_secret
 * @property array<int, string> $resources
 * @property Carbon|null $approved_at
 * @property Carbon|null $rejected_at
 * @property bool $active
 * @property int $consecutive_failures
 * @property Carbon|null $disabled_at
 */
class WebhookSubscription extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'url',
        'secret',
        'reveal_secret',
        'resources',
        'approved_at',
        'rejected_at',
        'active',
        'consecutive_failures',
        'disabled_at',
    ];

    /**
     * @var array<int, string>
     */
    protected $hidden = [
        'secret',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'resources' => 'array',
            // Laravel's built-in encrypted cast (Crypt facade) — the issue's
            // "secret ... stored encrypted (Crypt)" requirement, without a bespoke
            // encryption path to get subtly wrong.
            'secret' => 'encrypted',
            'reveal_secret' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'active' => 'boolean',
            'consecutive_failures' => 'integer',
            'disabled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'subscription_id');
    }

    /**
     * Subscriptions that may currently receive a delivery: approved by an
     * operator, not paused by the owner, and not auto-disabled by the system.
     */
    public function scopeEligibleForDelivery(Builder $query): Builder
    {
        return $query
            ->whereNotNull('approved_at')
            ->where('active', true)
            ->whereNull('disabled_at');
    }

    /**
     * Awaiting an operator's decision: neither approved nor rejected yet. What
     * `webhook:approve --list` shows — a rejected subscription must not
     * resurface here, or "reject" would be indistinguishable from "not yet
     * looked at" (Issue #36 follow-up).
     */
    public function scopePending(Builder $query): Builder
    {
        return $query
            ->whereNull('approved_at')
            ->whereNull('rejected_at');
    }
}
