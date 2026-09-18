<?php

namespace App\Support;

use App\Models\MeetupEvent;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

/**
 * How much a tag is actually used, and whether that amount is remarkable —
 * issue #149's evidence base for the tags.moderation vocabulary screen.
 *
 * The whole vocabulary is fetched at once because the moderation screen shows
 * every row anyway: two queries plus one created_at lookup per taggable type,
 * regardless of how many tags there are, instead of a count subquery per tag.
 * At production size (91 tags, 746 taggables) this is one screen full of data
 * either way.
 *
 * THE HEURISTIC THRESHOLDS are display-only judgement calls, not rules:
 *
 *   - "too broad" fires when a tag sticks to more than 80 % of all events of
 *     type meetup_event — a tag that is on nearly everything filters nothing.
 *     It only fires from 5 events upwards, because 3 of 4 events is 75 % today
 *     and a rounding artefact tomorrow; a young community must not be told its
 *     vocabulary is degenerate. The comparison is strictly GREATER than, so
 *     exactly 80 % (4 of 5) does not fire.
 *   - "too rare" fires when a tag has been used at all, but fewer than 2 of
 *     its usages fall inside the last 12 months. Zero usages do NOT fire: a
 *     fresh tag is a plan, not a corpse.
 *
 * What "recent" means had to be approximated: the taggables pivot carries no
 * timestamps (spatie/laravel-tags never adds them), so the window is taken
 * from the tagged model's own created_at — a usage counts as recent when the
 * thing it was attached to is younger than the window. A usage whose model
 * cannot be resolved (unknown morph type in the pivot) counts as old rather
 * than recent: pessimistic, and the badge is advice, never an action.
 *
 * Both numbers are culturally sensitive (a small country legitimately runs
 * "rare" tags — that is in the plan), which is why they render as badges on a
 * moderator's screen and feed nothing else.
 */
class TagUsageStats
{
    /** A tag on more than this share of all events is "too broad". */
    public const TOO_BROAD_RATIO = 0.80;

    /** Below this many events the ratio says nothing and the badge stays off. */
    public const TOO_BROAD_MIN_EVENTS = 5;

    /** The recency window for "too rare", in months. */
    public const TOO_RARE_WINDOW_MONTHS = 12;

    /** "Fewer than 2 usages" inside the window — at most this many. */
    public const TOO_RARE_MAX_RECENT = 1;

    /** @var array<int, array{usage: int, event_usage: int, recent: int}> */
    private array $stats = [];

    private int $totalEvents = 0;

    /**
     * Read the pivot once and index it in PHP.
     */
    public static function load(): self
    {
        $stats = new self();
        $cutoff = Date::now()->subMonths(self::TOO_RARE_WINDOW_MONTHS);

        /** @var array<string, array<int, string|null>> $created type => id => created_at */
        $created = [];

        $rows = DB::table('taggables')->get(['tag_id', 'taggable_type', 'taggable_id']);

        foreach ($rows as $row) {
            $tagId = (int) $row->tag_id;
            $type = (string) $row->taggable_type;
            $id = (int) $row->taggable_id;

            $stats->stats[$tagId] ??= ['usage' => 0, 'event_usage' => 0, 'recent' => 0];

            if (! array_key_exists($type, $created)) {
                $created[$type] = is_a($type, Model::class, true) && class_exists($type)
                    ? $type::query()->pluck('created_at', 'id')->all()
                    : [];
            }

            $stats->stats[$tagId]['usage']++;

            if ($type === MeetupEvent::class) {
                $stats->stats[$tagId]['event_usage']++;
            }

            $createdAt = $created[$type][$id] ?? null;

            if ($createdAt !== null && Date::parse($createdAt)->gte($cutoff)) {
                $stats->stats[$tagId]['recent']++;
            }
        }

        $stats->totalEvents = MeetupEvent::query()->count();

        return $stats;
    }

    /**
     * @return array{usage: int, event_usage: int, recent: int}
     */
    public function for(Tag $tag): array
    {
        return $this->stats[$tag->id] ?? ['usage' => 0, 'event_usage' => 0, 'recent' => 0];
    }

    /**
     * Total usages across every taggable type.
     */
    public function usageCount(Tag $tag): int
    {
        return $this->for($tag)['usage'];
    }

    public function isTooBroad(Tag $tag): bool
    {
        if ($tag->type !== 'meetup_event' || $this->totalEvents < self::TOO_BROAD_MIN_EVENTS) {
            return false;
        }

        // Strictly greater than: 4 of 5 events is exactly 80 % and does not fire.
        return $this->for($tag)['event_usage'] > self::TOO_BROAD_RATIO * $this->totalEvents;
    }

    public function isTooRare(Tag $tag): bool
    {
        $usage = $this->for($tag);

        // Zero usages are a plan, not a corpse — the badge would read as a
        // deletion suggestion.
        return $usage['usage'] > 0 && $usage['recent'] <= self::TOO_RARE_MAX_RECENT;
    }

    /**
     * The share of all meetup events this tag sticks to, for the tooltip.
     */
    public function eventSharePercent(Tag $tag): int
    {
        if ($this->totalEvents === 0) {
            return 0;
        }

        return (int) round(100 * $this->for($tag)['event_usage'] / $this->totalEvents);
    }

    public function recentCount(Tag $tag): int
    {
        return $this->for($tag)['recent'];
    }

    public function totalEvents(): int
    {
        return $this->totalEvents;
    }
}
