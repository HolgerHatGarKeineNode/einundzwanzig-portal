<?php

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

it('returns 503 with Retry-After for missing compiled view files instead of 500', function () {
    Route::get('/_test/stale-compiled-view', function () {
        throw new FileNotFoundException(
            'File does not exist at path /var/www/html/storage/framework/views/livewire/views/abc123.blade.php.'
        );
    });

    $response = $this->get('/_test/stale-compiled-view');

    $response->assertStatus(503);
    expect($response->headers->get('Retry-After'))->toBe('5');
});

it('does not report missing compiled view exceptions to the logs', function () {
    Log::spy();

    Route::get('/_test/stale-compiled-view-log', function () {
        throw new FileNotFoundException(
            'File does not exist at path /var/www/html/storage/framework/views/livewire/views/abc123.blade.php.'
        );
    });

    $this->get('/_test/stale-compiled-view-log')->assertStatus(503);

    Log::shouldNotHaveReceived('error');
    Log::shouldNotHaveReceived('critical');
    Log::shouldNotHaveReceived('emergency');
});

it('still reports FileNotFoundException for unrelated paths', function () {
    Route::get('/_test/unrelated-missing-file', function () {
        throw new FileNotFoundException(
            'File does not exist at path /var/www/html/storage/app/some-upload.pdf.'
        );
    });

    $response = $this->get('/_test/unrelated-missing-file');

    expect($response->status())->not->toBe(503);
});
