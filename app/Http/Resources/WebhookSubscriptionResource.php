<?php

namespace App\Http\Resources;

use App\Models\WebhookSubscription;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A webhook subscription as seen by its owner — never the secret again after
 * creation (Issue #36); see WebhookSubscriptionController::store() for the
 * one response that does include it.
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
            'status' => $this->status(),
            'active' => $this->resource->active,
            'consecutive_failures' => $this->resource->consecutive_failures,
            'disabled_at' => $this->resource->disabled_at?->toIso8601String(),
            'created_at' => $this->resource->created_at?->toIso8601String(),
        ];
    }

    /**
     * A single, human-facing label for the three states that matter to an
     * owner: pending approval, running, or stopped (whichever way it stopped).
     */
    private function status(): string
    {
        if ($this->resource->approved_at === null) {
            return 'pending';
        }

        if ($this->resource->disabled_at !== null) {
            return 'disabled';
        }

        return $this->resource->active ? 'active' : 'paused';
    }
}
