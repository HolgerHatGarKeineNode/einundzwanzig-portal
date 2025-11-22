<?php

namespace App\Attributes;

use RalphJSmit\Laravel\SEO\Support\SEOData;

#[\Attribute(\Attribute::TARGET_METHOD | \Attribute::TARGET_CLASS)]
class SeoDataAttribute
{
    public function __construct(
        public ?string $key = null, // e.g., 'meetups_index', 'event_show', etc.
    ) {}

    // Centralized SEO data definitions by key as SEOData instances (lazy initialized)
    private static array $seoDefinitions;

    private static function initDefinitions(): void
    {
        self::$seoDefinitions = [
            'meetups_index' => new SEOData(
                title: __('Meetups - Übersicht'),
                description: __('Entdecke alle verfügbaren Meetups in deiner Region.'),
            ),
            // Add more as needed
            'default' => new SEOData(
                title: __('Willkommen'),
                description: __('Toximalistisches Infotainment für bullische Bitcoiner.'),
            ),
        ];
    }

    // Static method to get SEO data by key as SEOData instance
    public static function getData(string $key): SEOData
    {
        if (empty(self::$seoDefinitions)) {
            self::initDefinitions();
        }
        return self::$seoDefinitions[$key] ?? self::$seoDefinitions['default'];
    }

    // If direct SEOData is provided, return it; else fetch by key as SEOData
    public function resolve(): SEOData
    {
        if ($this->key) {
            return self::getData($this->key);
        }
        return self::getData('default'); // Fallback
    }
}
