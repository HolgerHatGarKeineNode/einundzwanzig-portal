<?php

it('renders the login page with QR code and language selector', function () {
    $page = visit('/login');

    $page->assertSee('Login with lightning')
        ->assertSee('Bitcoin, not blockchain')
        ->assertNoJavaScriptErrors();
});

it('renders the registration page', function () {
    $page = visit('/register');

    $page->assertNoJavaScriptErrors();
});
