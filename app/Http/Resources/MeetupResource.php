<?php

namespace App\Http\Resources;

use App\Models\Meetup;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Meetup
 */
class MeetupResource extends JsonResource
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
            'city_id' => $this->city_id,
            'intro' => $this->intro,
            'telegram_link' => $this->telegram_link,
            'webpage' => $this->webpage,
            'twitter_username' => $this->twitter_username,
            'matrix_group' => $this->matrix_group,
            'nostr' => $this->nostr,
            'simplex' => $this->simplex,
            'signal' => $this->signal,
            'community' => $this->community,
            'visible_on_map' => $this->visible_on_map,
            'is_active' => $this->is_active,
            'rsvp_enabled' => $this->rsvp_enabled,
            'attendees_public' => $this->attendees_public,
            'nostr_publishing_enabled' => $this->nostr_publishing_enabled,
            // Only set when the meetup_user pivot is loaded (e.g. via
            // /api/my-meetups). Tells the app whether the token holder is a leader of
            // this meetup (may edit + manage leaders).
            'is_leader' => $this->whenPivotLoaded('meetup_user', fn (): bool => (bool) $this->pivot->is_leader),
            'logo' => $this->getFirstMediaUrl('logo', 'thumb'),
            'last_event_at' => $this->last_event_at,
            'created_by' => $this->created_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
