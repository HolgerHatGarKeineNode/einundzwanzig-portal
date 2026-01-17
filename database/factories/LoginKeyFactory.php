<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class LoginKeyFactory extends Factory
{
    protected $model = \App\Models\LoginKey::class;

    public function definition(): array
    {
        return [
            'k1' => str()->random(64),
            'user_id' => \App\Models\User::factory(),
        ];
    }
}
