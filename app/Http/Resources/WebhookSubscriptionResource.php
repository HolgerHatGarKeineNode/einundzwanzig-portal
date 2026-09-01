<?php

namespace App\Http\Resources;

use App\Models\WebhookSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A webhook subscription as seen by its owner — the secret is included again
 * only when the subscription's own `reveal_secret` flag is on (Issue #36
 * follow-up); otherwise it is shown once, at creation — see
 * WebhookSubscriptionController::store().
 *
 * @property-read WebhookSubscription $resource
 */
class WebhookSubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'url' => $this->resource->url,
            'resources' => $this->resource->resources,
            'reveal_secret' => $this->resource->reveal_secret,
            'status' => $this->status(),
            'active' => $this->resource->active,
            'consecutive_failures' => $this->resource->consecutive_failures,
            'disabled_at' => $this->resource->disabled_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
            // Belt and suspenders on top of index()'s user_id scope and
            // update()'s ownership policy check: this resource never exposes
            // the secret to anyone but the subscription's own owner, even if
            // it were ever resolved from a different context.
            $this->mergeWhen(
                $this->resource->reveal_secret && $this->resource->user_id === $request->user()?->id,
                ['secret' => $this->resource->secret]
            ),
        ];
    }

    /**
     * A single, human-facing label for the states that matter to an owner:
     * pending or rejected approval, running, or stopped (whichever way it
     * stopped). Checked before the `approved_at === null` branch below, since
     * a rejected subscription also has a null `approved_at` — the two only
     * differ in `rejected_at` (Issue #36 follow-up).
     */
    private function status(): string
    {
        if ($this->resource->rejected_at !== null) {
            return 'rejected';
        }

        if ($this->resource->approved_at === null) {
            return 'pending';
        }

        if ($this->resource->disabled_at !== null) {
            return 'disabled';
        }

        return $this->resource->active ? 'active' : 'paused';
    }
}
