<?php

namespace App\Models;

use Database\Factories\MeetupSlugRedirectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A slug a meetup used to have (issue #167).
 *
 * Written by {@see Meetup} whenever its slug changes; read by
 * {@see Meetup::resolveRouteBinding()} to redirect an old URL to the current one.
 */
class MeetupSlugRedirect extends Model
{
    /** @use HasFactory<MeetupSlugRedirectFactory> */
    use HasFactory;

    protected $fillable = [
        'meetup_id',
        'slug',
    ];

    /**
     * @return BelongsTo<Meetup, $this>
     */
    public function meetup(): BelongsTo
    {
        return $this->belongsTo(Meetup::class);
    }
}
