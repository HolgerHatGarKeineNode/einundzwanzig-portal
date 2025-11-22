<?php

namespace App\Livewire\BooksForPlebs;

use App\Traits\SeoTrait;
use Livewire\Component;
use RalphJSmit\Laravel\SEO\Support\SEOData;

class BookRentalGuide extends Component
{
    use SeoTrait;

    public function render()
    {
        return view('livewire.books-for-plebs.book-rental-guide')->with( [
            'SEOData' => new SEOData(
                title: __('BooksForPlebs'),
                description: __('Lokale Buchausleihe für Bitcoin-Meetups.'),
                image: asset('img/book-rental.jpg')
            ),
        ]);
    }
}
