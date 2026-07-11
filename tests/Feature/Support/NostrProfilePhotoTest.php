<?php

declare(strict_types=1);

use App\Support\NostrProfilePhoto;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

it('refuses private, loopback and metadata addresses (SSRF)', function (string $url) {
    Http::fake();

    expect(NostrProfilePhoto::store($url))->toBeNull();

    Http::assertNothingSent();
})->with([
    'loopback' => 'http://127.0.0.1/evil.png',
    'private 10/8' => 'http://10.0.0.5/evil.png',
    'private 192.168' => 'http://192.168.1.1/evil.png',
    'link-local metadata' => 'http://169.254.169.254/latest/meta-data/',
]);

it('refuses non-http schemes', function () {
    Http::fake();

    expect(NostrProfilePhoto::store('file:///etc/passwd'))->toBeNull()
        ->and(NostrProfilePhoto::store('gopher://1.2.3.4/'))->toBeNull();
});

it('stores a valid image from a public host and names it by content-type', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('binary-image-bytes', 200, ['Content-Type' => 'image/png'])]);

    $path = NostrProfilePhoto::store('http://1.2.3.4/whatever.gif');

    expect($path)->toStartWith('profile-photos/')
        ->and($path)->toEndWith('.png'); // extension from content-type, NOT the .gif in the url
    Storage::disk('public')->assertExists($path);
});

it('refuses a non-image content-type so no .php/.html lands in the web root (RCE/XSS)', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('<?php system($_GET[0]); ?>', 200, ['Content-Type' => 'application/octet-stream'])]);

    expect(NostrProfilePhoto::store('http://1.2.3.4/shell.php'))->toBeNull();
    expect(Storage::disk('public')->allFiles('profile-photos'))->toBeEmpty();
});

it('does not follow redirects', function () {
    Storage::fake('public');
    Http::fake(['*' => Http::response('', 302, ['Location' => 'http://127.0.0.1/'])]);

    expect(NostrProfilePhoto::store('http://1.2.3.4/redir.png'))->toBeNull();
});
