<?php

use Illuminate\Support\Facades\Gate;

it('exposes the api docs route publicly', function () {
    expect(Gate::forUser(null)->allows('viewApiDocs'))->toBeTrue();
});

it('allows guests to open the api documentation', function () {
    $this->get(route('scramble.docs.ui'))->assertSuccessful();
});

it('serves the openapi document to guests', function () {
    $this->get(route('scramble.docs.document'))->assertSuccessful();
});
