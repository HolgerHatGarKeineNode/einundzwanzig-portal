<?php

/**
 * Guards against the regression where uploading an .avif file in any of the
 * Livewire file-upload forms (meetups/courses/lecturers) throws
 * FileNotPreviewableException because livewire's preview_mimes did not include
 * avif. See https://livewire.laravel.com/docs/uploads#configuring-temporary-upload-previews
 */
it('lists avif in livewire preview_mimes config', function () {
    expect(config('livewire.temporary_file_upload.preview_mimes'))
        ->toContain('avif');
});

it('allows avif in the mimes validation of every image-upload component', function (string $relativePath) {
    $contents = file_get_contents(resource_path($relativePath));

    expect($contents)->toMatch('/#\[Validate\([^)]*mimes:[^|]*avif[^|]*[^)]*\)\]/');
})->with([
    'views/livewire/meetups/create.blade.php',
    'views/livewire/meetups/edit.blade.php',
    'views/livewire/courses/create.blade.php',
    'views/livewire/courses/edit.blade.php',
    'views/livewire/lecturers/create.blade.php',
    'views/livewire/lecturers/edit.blade.php',
]);
