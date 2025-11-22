<?php

namespace App\Livewire\Helper;

use App\Traits\SeoTrait;
use Livewire\Component;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class FollowTheRabbit extends Component
{
    use SeoTrait;

    public function render()
    {
        return view('livewire.helper.follow-the-rabbit')->with([
            'SEOData' => new SEOData(
                title: __('Bitcoin - Rabbit Hole'),
                description: __('Dies ist ein großartiger Überblick über die Bitcoin-Kaninchenhöhle mit Zugängen zu Bereichen, die Bitcoin umfasst. Jedes Thema hat seine eigene Kaninchenhöhle, die durch Infografiken auf einfache und verständliche Weise visualisiert wird, mit QR-Codes, die zu erklärenden Videos und Artikeln führen. Viel Spaß auf Ihrer Entdeckungsreise!'),
                image: asset('img/kaninchenbau.png')
            ),
        ]);
    }
}
