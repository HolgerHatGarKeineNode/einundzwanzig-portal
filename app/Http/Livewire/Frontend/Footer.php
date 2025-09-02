<?php

namespace App\Http\Livewire\Frontend;

use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

class Footer extends Component
{
    public function render()
    {
        $l = Cookie::get('lang', config('app.locale'));
        $language = null;
        $translated = 0;
        $toTranslate = 0;
        $toTranslate = $toTranslate > 0 ? $toTranslate : 1;

        return view('livewire.frontend.footer', [
            'percentTranslated' => $l === 'en' ? 100 : round(($translated / $toTranslate) * 100),
            'language' => $language,
        ]);
    }
}
