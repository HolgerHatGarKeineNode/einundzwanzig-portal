<?php

$themeProbe = <<<'JS'
    (() => {
      const host = document.querySelector('nostr-signer');
      if (! host) {
        return { present: false };
      }
      const style = getComputedStyle(host);
      const rect = host.getBoundingClientRect();
      return {
        present: true,
        font: style.getPropertyValue('--mill-font').trim(),
        accent: style.getPropertyValue('--mill-accent').trim(),
        bg: style.getPropertyValue('--mill-bg').trim(),
        surface: style.getPropertyValue('--mill-surface').trim(),
        radius: style.getPropertyValue('--mill-radius').trim(),
        width: Math.round(rect.width * 100) / 100,
        height: Math.round(rect.height * 100) / 100,
        right: Math.round(rect.right * 100) / 100,
        viewport: window.innerWidth,
        scrollWidth: document.documentElement.scrollWidth,
      };
    })()
JS;

$overflowProbe = <<<'JS'
    (() => ({
      viewport: window.innerWidth,
      scrollWidth: document.documentElement.scrollWidth,
      clientWidth: document.documentElement.clientWidth,
    }))()
JS;

it('opens mill from the Nostr button without a second method picker and matches portal tokens', function () use ($themeProbe) {
    $page = visit('/login');

    $page->assertSee(__('Log in mit Google'))
        ->assertSee(__('Log in mit Nostr'))
        ->click(__('Log in mit Nostr'))
        ->wait(2.5)
        ->assertNoJavaScriptErrors();

    $theme = $page->script($themeProbe);

    expect($theme['present'])->toBeTrue('nostr-signer host missing after Nostr click');
    expect($theme['font'])->toContain('Inconsolata');
    expect($theme['font'])->not->toContain('Space Grotesk');
    expect($theme['accent'])->toBe('#f59e0b');
    expect($theme['bg'])->toBe('#09090b');
    expect($theme['surface'])->toBe('#18181b');
    expect($theme['radius'])->toBe('8px');
    expect($theme['scrollWidth'])->toBeLessThanOrEqual($theme['viewport']);

    $page->screenshot(filename: 'login-mill-nostr-modal');
});

it('keeps the login page inside the viewport on mobile and desktop', function () use ($overflowProbe) {
    $page = visit('/login');

    foreach ([[375, 812], [1280, 800]] as [$width, $height]) {
        $page->resize($width, $height)->wait(0.8);
        $m = $page->script($overflowProbe);

        expect($m['scrollWidth'])->toBeLessThanOrEqual($m['viewport'] + 1, sprintf(
            'viewport %s: scrollWidth %s overflows',
            $m['viewport'],
            $m['scrollWidth'],
        ));
    }

    $page->assertSee(__('Log in mit Google'))
        ->screenshot(filename: 'login-mill-buttons');
});

it('still shows Google after opening then closing the Lightning panel', function () {
    $page = visit('/login');

    $page->click(__('Log in mit Lightning'))
        ->assertSee(__('Click to connect'))
        ->click(__('Log in mit Lightning'))
        ->assertSee(__('Log in mit Google'))
        ->assertSee(__('Log in mit Nostr'))
        ->assertNoJavaScriptErrors();
});

$googleMillProbe = <<<'JS'
    (() => {
      const host = document.querySelector('nostr-signer');
      if (! host) {
        return { present: false };
      }
      const root = host.shadowRoot || host._shadow;
      const body = root?.querySelector('.mill-body') || root?.querySelector('.mill-modal');
      const text = body ? (body.textContent || '') : '';
      const oauthShim = host.getAttribute('oauth-shim');

      return {
        present: true,
        method: host._state?.method ?? null,
        pomegranate: host._state?.pomegranate === true,
        oauthShim: oauthShim === null ? '' : oauthShim,
        unavailable: text.includes('Google Sign-In Unavailable'),
        notConfigured: /not configured/i.test(text),
        oauthShimCopy: text.includes('oauth-shim'),
        enterPin: text.includes('Enter your PIN'),
        choosePin: text.includes('Choose a PIN'),
        splitKey: text.includes('split across independent servers'),
        continueGoogle: text.includes('Continue with Google'),
        text: text.slice(0, 800),
      };
    })()
JS;

it('opens mill from the Google button via pomegranate without the Drive+PIN oauth-shim screen', function () use ($googleMillProbe) {
    $page = visit('/login');

    $page->assertSee(__('Log in mit Google'))
        ->click(__('Log in mit Google'))
        ->wait(2.5)
        ->assertNoJavaScriptErrors();

    $probe = $page->script($googleMillProbe);

    $page->screenshot(filename: 'login-mill-google-modal');

    expect($probe['present'])->toBeTrue('nostr-signer host missing after Google click');
    expect($probe['pomegranate'])->toBeTrue();
    expect($probe['oauthShim'])->toBe('');
    expect($probe['unavailable'])->toBeFalse();
    expect($probe['notConfigured'])->toBeFalse();
    expect($probe['oauthShimCopy'])->toBeFalse();
    expect($probe['enterPin'])->toBeFalse();
    expect($probe['choosePin'])->toBeFalse();
    expect($probe['continueGoogle'] || $probe['splitKey'])->toBeTrue(
        'Drive+PIN / unconfigured mill screen, not pomegranate. mill text: '.($probe['text'] ?? '')
    );
});

it('leaves Google, Nostr and Lightning standing when the mill Google flow is cancelled', function () use ($googleMillProbe) {
    $page = visit('/login');

    $page->click(__('Log in mit Google'))
        ->wait(2.5);

    $probe = $page->script($googleMillProbe);
    expect($probe['present'])->toBeTrue('nostr-signer host missing after Google click');
    expect($probe['unavailable'])->toBeFalse();

    $closed = $page->script(<<<'JS'
        (() => {
          const host = document.querySelector('nostr-signer');
          const root = host?.shadowRoot || host?._shadow;
          const close = root?.querySelector('.mill-close');
          if (! close) {
            return false;
          }
          close.click();
          return true;
        })()
    JS);

    expect($closed)->toBeTrue('mill close control missing after Google click');

    $page->wait(0.8)
        ->assertSee(__('Log in mit Google'))
        ->assertSee(__('Log in mit Nostr'))
        ->assertSee(__('Log in mit Lightning'))
        ->assertNoJavaScriptErrors();
});

it('keeps Google and Lightning reachable after leftover mill nip46 state and a reload', function () {
    $page = visit('/login');

    $seeded = $page->script(<<<'JS'
        (() => {
          sessionStorage.setItem('mill:nip46:state', JSON.stringify({
            url: 'wss://relay.example.invalid',
            pubkey: '00'.repeat(32),
            clientSecretKey: '11'.repeat(32),
            remotePubkey: '22'.repeat(32),
          }));
          return sessionStorage.getItem('mill:nip46:state') !== null;
        })()
    JS);

    expect($seeded)->toBeTrue();

    $page->refresh()
        ->assertSee(__('Log in mit Google'))
        ->assertSee(__('Log in mit Lightning'))
        ->assertSee(__('Log in mit Nostr'))
        ->assertDontSee('Google Sign-In Unavailable')
        ->assertNoJavaScriptErrors();
});
