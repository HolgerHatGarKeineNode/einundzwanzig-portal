<?php

namespace Database\Factories;

use App\Models\LoginKey;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class LoginKeyFactory extends Factory
{
    protected $model = LoginKey::class;

    public function definition(): array
    {
        return [
            'k1' => str()->random(64),
            'user_id' => User::factory(),
        ];
    }
}
