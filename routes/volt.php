<?php

use Livewire\Volt\Volt;

Volt::route('/', 'frontend.welcome')->name('welcome');

// Meetups Overview (Volt)
Volt::route('/meetup/overview', 'meetup.overview')->name('meetup.table.meetup');
