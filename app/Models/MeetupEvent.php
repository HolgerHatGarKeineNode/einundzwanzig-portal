<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'id' => 'integer',
            'meetup_id' => 'integer',
            'start' => 'datetime',
            'attendees' => 'array',
            'might_attendees' => 'array',
        ];
    }

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
