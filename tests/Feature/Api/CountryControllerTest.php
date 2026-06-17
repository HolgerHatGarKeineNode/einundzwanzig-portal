<?php

use App\Models\Country;

it('does not crash when selected contains non-numeric codes', function () {
    Country::factory()->create(['code' => 'CH']);

    $response = $this->getJson('/api/countries?'.http_build_query([
        'selected' => ['CH', 'de', '1'],
    ]));

    $response->assertSuccessful();
});
