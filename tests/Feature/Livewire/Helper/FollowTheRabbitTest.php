<?php

use App\Livewire\Helper\FollowTheRabbit;
use Livewire\Livewire;

it('mounts the FollowTheRabbit component', function () {
    Livewire::test(FollowTheRabbit::class)->assertStatus(200);
});

it('is referenced by the /kaninchenbau route', function () {
    $this->get('/kaninchenbau')->assertSuccessful();
});
