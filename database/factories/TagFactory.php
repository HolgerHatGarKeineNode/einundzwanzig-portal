<?php

namespace Database\Factories;

use App\Models\Tag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    public function definition(): array
    {
        $name = fake()->unique()->word().'-'.fake()->unique()->numberBetween(1, 9999);
        $slug = Str::slug($name);

        return [
            'name' => json_encode(['en' => $name, 'de' => $name]),
            'slug' => json_encode(['en' => $slug, 'de' => $slug]),
            'type' => fake()->randomElement(['topic', 'category', null]),
            'order_column' => null,
            'icon' => 'tag',
        ];
    }
}
