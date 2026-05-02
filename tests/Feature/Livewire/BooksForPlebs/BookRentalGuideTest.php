<?php

use App\Livewire\BooksForPlebs\BookRentalGuide;
use Illuminate\View\ViewException;
use Livewire\Livewire;

it('mounts the BookRentalGuide component but its view references a route that is currently commented out in routes/web.php', function () {
    expect(fn () => Livewire::test(BookRentalGuide::class)->assertStatus(200))
        ->toThrow(ViewException::class, 'Route [buecherverleih.download] not defined.');
})->skip('Component is unreachable: /buecherverleih route is commented out in routes/web.php — view references the missing buecherverleih.download route.');

it('confirms the BookRentalGuide component class still exists', function () {
    expect(class_exists(BookRentalGuide::class))->toBeTrue();
});
