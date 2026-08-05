<?php

namespace App\Traits;

use App\Attributes\SeoDataAttribute;
use Illuminate\Support\Facades\View;

trait SeoTrait
{
    public function mountSeoTrait(): void
    {
        $this->setupSeo();
    }

    /**
     * Setup SEO data from attributes and share it globally.
     */
    protected function setupSeo(): void
    {
        $reflection = new \ReflectionClass($this);
        $attributes = $reflection->getAttributes(SeoDataAttribute::class);

        if (! empty($attributes)) {
            $seoDataAttribute = $attributes[0]->newInstance();
            $seoData = $seoDataAttribute->resolve();
        } else {
            $seoData = SeoDataAttribute::getData('default');
        }

        View::share('SEOData', $seoData);
    }
}
