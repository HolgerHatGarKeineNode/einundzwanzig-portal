<?php

it('offers Google, Nostr and Lightning on /login without a nested picker', function () {
    $this->get(route('login'))
        ->assertSuccessful()
        ->assertSee(__('Log in mit Google'), false)
        ->assertSee(__('Log in mit Nostr'), false)
        ->assertSee(__('Log in mit Lightning'), false)
        ->assertDontSee('window.nostr.js', false)
        ->assertDontSee('wnjParams', false)
        ->assertDontSee(__('Lightning-Login anzeigen'), false);
});

it('keeps Google on the login view after the lightning panel is in the markup', function () {
    $html = $this->get(route('login'))->getContent();

    $googlePos = strpos($html, __('Log in mit Google'));
    $nostrPos = strpos($html, __('Log in mit Nostr'));
    $lightningPos = strpos($html, __('Log in mit Lightning'));

    expect($googlePos)->toBeInt()->not->toBeFalse()
        ->and($nostrPos)->toBeInt()->not->toBeFalse()
        ->and($lightningPos)->toBeInt()->not->toBeFalse()
        ->and($googlePos)->toBeLessThan($nostrPos);
});

it('does not load window.nostr.js from any layout used by login', function () {
    $html = $this->get(route('login'))->getContent();

    expect($html)
        ->not->toContain('cdn.jsdelivr.net/npm/window.nostr.js')
        ->not->toContain('window.wnjParams');
});

/**
 * Brace-matched body of a JS function so a nested object literal cannot
 * truncate the extract. Used to pin the Google/mill contracts without
 * executing mill in PHP.
 */
function millJsFunctionBody(string $source, string $signature): string
{
    $start = strpos($source, $signature);
    expect($start)->not->toBeFalse("missing JS signature [{$signature}]");

    $brace = strpos($source, '{', $start);
    expect($brace)->not->toBeFalse("missing opening brace for [{$signature}]");

    $depth = 0;
    $length = strlen($source);

    for ($i = $brace; $i < $length; $i++) {
        $character = $source[$i];

        if ($character === '{') {
            $depth++;

            continue;
        }

        if ($character === '}') {
            $depth--;

            if ($depth === 0) {
                return substr($source, $brace + 1, $i - $brace - 1);
            }
        }
    }

    expect(false)->toBeTrue("unbalanced braces for [{$signature}]");

    return '';
}

it('wires Google through mill pomegranate then kind 22242 without a second Nostr click', function () {
    $source = file_get_contents(resource_path('js/nostrLogin.js'));
    $google = millJsFunctionBody($source, 'async loginWithGoogle()');
    $open = millJsFunctionBody($source, 'async openNostrLogin()');

    expect($google)
        ->toContain("connectMill({ methods: ['pomegranate'], pomegranate: true })")
        ->toContain('openNostrLogin')
        ->not->toContain("methods: ['google']")
        ->not->toContain('loginWithNostr');

    $connectAt = strpos($google, 'connectMill');
    $openAt = strpos($google, 'openNostrLogin');

    expect($connectAt)->toBeInt()->not->toBeFalse()
        ->and($openAt)->toBeInt()->not->toBeFalse()
        ->and($connectAt)->toBeLessThan($openAt);

    expect($open)
        ->toContain('kind: 22242')
        ->toContain("this.\$dispatch('nostrLoggedIn'");
});

it('does not pass methods google anywhere under resources/js', function () {
    $hits = [];
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator(resource_path('js'), FilesystemIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'js') {
            continue;
        }

        if (preg_match("/methods:\\s*\\['google'\\]/", (string) file_get_contents($file->getPathname())) === 1) {
            $hits[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $file->getPathname());
        }
    }

    expect($hits)->toBeEmpty();
});

it('clears mill nip46 bunker state from sessionStorage, not localStorage', function () {
    $source = file_get_contents(resource_path('js/millAuth.js'));
    $drop = millJsFunctionBody($source, 'export function dropFailedBunker()');
    $connect = millJsFunctionBody($source, 'export async function connectMill(options)');

    expect($source)->toContain("const BUNKER_STORAGE_KEY = 'mill:nip46:state'");

    expect($drop)
        ->toMatch("/sessionStorage\\.removeItem\\(\\s*(BUNKER_STORAGE_KEY|['\"]mill:nip46:state['\"])/")
        ->not->toMatch("/localStorage\\.removeItem\\(\\s*(BUNKER_STORAGE_KEY|['\"]mill:nip46:state['\"])/");

    expect($connect)->toMatch('/if \\(methods\\.includes\\([\'"]nip46[\'"]\\)\\) \\{\\s*dropFailedBunker\\(\\);/s');
});
