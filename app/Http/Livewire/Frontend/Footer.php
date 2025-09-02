<?php

namespace App\Http\Livewire\Frontend;

use Illuminate\Support\Facades\Cookie;
use Livewire\Component;

class Footer extends Component
{
    public function render()
    {
        return view('livewire.frontend.footer', [
//            'percentTranslated' => $l === 'en' ? 100 : round(($translated / $toTranslate) * 100),
//            'language' => $language,
        ]);
    }
}
