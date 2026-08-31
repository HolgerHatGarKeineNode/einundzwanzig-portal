<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One queued outbound delivery of a change to a webhook subscription (Issue #36).
 *
 * `payload` is a standalone copy of the api_changes envelope at queue time, see
 * the migration's docblock — App\Jobs\DeliverWebhookJob reads only this column,
 * never the source model or the ApiChange row, so a deletion delivers correctly
 * even after the source record (and later, the ApiChange row itself) is gone.
 *
 * @property int $id
 * @property int $subscription_id
 * @property int|null $api_change_id
 * @property array<string, mixed> $payload
 * @property int $attempts
 * @property int|null $last_response_code
 * @property Carbon|null $delivered_at
 * @property Carbon|null $failed_at
 */
class WebhookDelivery extends Model
{
    use HasFactory;

    /**
     * @var array<int, string>
     */
    protected $fillable = [
        'subscription_id',
        'api_change_id',
        'payload',
        'attempts',
        'last_response_code',
        'delivered_at',
        'failed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'attempts' => 'integer',
            'last_response_code' => 'integer',
            'delivered_at' => 'datetime',
            'failed_at' => 'datetime',
        ];
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
