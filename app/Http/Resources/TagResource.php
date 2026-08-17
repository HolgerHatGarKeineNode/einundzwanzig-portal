<?php

namespace App\Http\Resources;

use App\Models\Tag;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tag
 */
class TagResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            // Resolved through the display chain, so a consumer asking in Czech gets
            // the German name rather than an empty string when only German exists.
            'name' => $this->displayName(),
            'name_locale' => $this->displayLocale(),
            'slug' => $this->getTranslation('slug', $this->displayLocale() ?? app()->getLocale(), false),
            'featured' => $this->featured,
            'approved' => $this->isApproved(),
            // Every translation, for clients that render their own language switcher.
            'translations' => $this->getTranslations('name'),
        ];
    }
}
