<?php

namespace App\Http\Resources;

use App\Models\Lecturer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Lecturer
 */
class LecturerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'subtitle' => $this->subtitle,
            'intro' => $this->intro,
            'description' => $this->description,
            'active' => $this->active,
            'website' => $this->website,
            'twitter_username' => $this->twitter_username,
            'nostr' => $this->nostr,
            'lightning_address' => $this->lightning_address,
            'lnurl' => $this->lnurl,
            'node_id' => $this->node_id,
            'paynym' => $this->paynym,
            'team_id' => $this->team_id,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
