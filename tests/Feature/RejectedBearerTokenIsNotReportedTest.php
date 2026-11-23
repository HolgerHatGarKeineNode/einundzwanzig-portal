<?php

use Illuminate\Support\Facades\Log;
use League\OAuth2\Server\Exception\OAuthServerException;

it('answers an invalid bearer token on /mcp with 401 without reporting it', function () {
    Log::spy();

    $this->withToken('not-a-valid-access-token')->postJson('/mcp', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'ping',
    ])->assertUnauthorized();

    Log::shouldNotHaveReceived('error');
});

it('does not report an access_denied OAuthServerException', function () {
    Log::spy();

    report(OAuthServerException::accessDenied('Access token has been revoked'));

    Log::shouldNotHaveReceived('error');
});

it('still reports any other OAuthServerException', function () {
    Log::spy();

    report(OAuthServerException::serverError('Key file is not readable'));

    Log::shouldHaveReceived('error')->once();
});
