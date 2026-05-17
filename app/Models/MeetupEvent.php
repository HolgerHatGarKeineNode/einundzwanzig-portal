<?php

namespace App\Models;

use App\Enums\RecurrenceType;
use App\Observers\MeetupEventObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([MeetupEventObserver::class])]
class MeetupEvent extends Model
{
    use HasFactory;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'integer',
        'meetup_id' => 'integer',
        'start' => 'datetime',
        'recurrence_end_date' => 'datetime',
        'attendees' => 'array',
        'might_attendees' => 'array',
    ];

    /**
     * The attributes that should be cast to enums.
     *
     * @var array
     */
    protected $enumCasts = [
        'recurrence_type' => RecurrenceType::class,
    ];

    protected static function booted()
    {
        static::creating(function ($model) {
            if (! $model->created_by) {
                $model->created_by = auth()->id();
            }
        });
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function meetup(): BelongsTo
    {
        return $this->belongsTo(Meetup::class);
    }
}
