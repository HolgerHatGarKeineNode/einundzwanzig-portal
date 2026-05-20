<?php

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\MissingFileUploadsTraitException;

function throwMissingFileUploadsTraitException(): never
{
    $component = new class extends Component
    {
        public function getName(): string
        {
            return 'language.selector';
        }

        public function render(): string
        {
            return '<div></div>';
        }
    };

    throw new MissingFileUploadsTraitException($component);
}

it('returns 400 for MissingFileUploadsTraitException instead of 500', function () {
    Route::get('/_test/missing-file-uploads-trait', function () {
        throwMissingFileUploadsTraitException();
    });

    $response = $this->get('/_test/missing-file-uploads-trait');

    expect($response->status())->toBe(400);
});

it('does not report MissingFileUploadsTraitException to the logs', function () {
    Log::spy();

    Route::get('/_test/missing-file-uploads-trait-log', function () {
        throwMissingFileUploadsTraitException();
    });

    $this->get('/_test/missing-file-uploads-trait-log')
        ->assertStatus(400);

    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('critical');
    Log::shouldNotHaveReceived('emergency');
});
