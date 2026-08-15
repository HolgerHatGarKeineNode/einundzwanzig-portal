<?php

/**
 * Guards against the regression where any Livewire upload form renders
 * ->temporaryUrl() unguarded: uploading a non-previewable file (e.g. .psd)
 * threw FileNotPreviewableException during re-render (500 on livewire/update),
 * even though the #[Validate] attribute had already put the validation error
 * into the error bag. Every temporaryUrl() call must sit behind an
 * isPreviewable() guard, and the error must be surfaced via flux:error.
 */
it('guards every temporaryUrl() call behind isPreviewable() and shows the upload error', function (string $relativePath, string $uploadField) {
    $contents = file_get_contents(resource_path($relativePath));

    expect($contents)->toContain('temporaryUrl()')
        ->toContain('isPreviewable()')
        ->toContain('flux:error name="'.$uploadField.'"');

    // Kein unbewachter Aufruf: temporaryUrl() darf nur im Zweig unter dem Wächter stehen.
    $lines = collect(explode("\n", $contents));
    $guardLine = $lines->search(fn (string $line) => str_contains($line, 'isPreviewable()'));
    $urlLine = $lines->search(fn (string $line) => str_contains($line, 'temporaryUrl()'));

    expect($guardLine)->not->toBeFalse()
        ->and($urlLine)->not->toBeFalse()
        ->and($urlLine)->toBeGreaterThan($guardLine);
})->with([
    'views/livewire/meetups/create.blade.php' => ['views/livewire/meetups/create.blade.php', 'logo'],
    'views/livewire/meetups/edit.blade.php' => ['views/livewire/meetups/edit.blade.php', 'logo'],
    'views/livewire/courses/create.blade.php' => ['views/livewire/courses/create.blade.php', 'logo'],
    'views/livewire/courses/edit.blade.php' => ['views/livewire/courses/edit.blade.php', 'logo'],
    'views/livewire/lecturers/create.blade.php' => ['views/livewire/lecturers/create.blade.php', 'avatar'],
    'views/livewire/lecturers/edit.blade.php' => ['views/livewire/lecturers/edit.blade.php', 'avatar'],
]);
