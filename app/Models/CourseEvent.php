<?php

namespace App\Models;

use App\Models\Concerns\SetsCreatedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Tags\HasTags;

class CourseEvent extends Model
{
    use HasFactory;
    use HasTags;
    use SetsCreatedBy;

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
        'course_id' => 'integer',
        'osm_id' => 'integer',
        'osm_lat' => 'decimal:7',
        'osm_lon' => 'decimal:7',
        'city_id' => 'integer',
        'from' => 'datetime',
        'to' => 'datetime',
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * The city stands on its own since the venue was removed; the exact spot lives in the
     * `osm_*` columns when it is known, and in `location` as free text when it is not.
     */
    public function city(): BelongsTo
    {
        return $this->belongsTo(City::class);
    }

    public function registrations(): HasMany
    {
        return $this->hasMany(Registration::class);
    }
}
