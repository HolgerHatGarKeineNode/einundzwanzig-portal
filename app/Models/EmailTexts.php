<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EmailTexts extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function emailCampaign(): BelongsTo
    {
        return $this->belongsTo(EmailCampaign::class);
    }
}
