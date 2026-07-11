<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MergeAudit extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'loser_snapshot' => 'array',
            'moved_counts' => 'array',
        ];
    }

    public function survivor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'survivor_id');
    }
}
