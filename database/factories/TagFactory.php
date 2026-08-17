<?php

namespace Database\Factories;

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Tag>
 */
class TagFactory extends Factory
{
    protected $model = Tag::class;

    /**
     * Translatable attributes are passed as arrays, never as json_encode()'d strings.
     *
     * Spatie's Tag::setAttribute() routes any non-array value for a translatable key
     * through setTranslation() using the *current* locale — so a JSON string lands
     * whole inside the active language. That is what produced the nested wreckage in
     * the existing data: name became {"de":"{\"en\":\"x\",\"de\":\"x\"}"}, and the
     * slug generator then slugified the JSON braces along with it.
     *
     * The slug is intentionally not set here: the model derives one per locale on
     * save and then leaves it alone.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->word().'-'.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => ['en' => $name, 'de' => $name],
            'description' => null,
            'type' => fake()->randomElement(['topic', 'category', null]),
            'featured' => false,
            'approved_at' => now(),
            'created_by' => null,
            'order_column' => null,
            'icon' => 'tag',
        ];
    }

    /**
     * A tag offered in the picker before the user types anything.
     */
    public function featured(): static
    {
        return $this->state(fn (): array => ['featured' => true]);
    }

    /**
     * A tag suggested by a non-editor, awaiting review.
     */
    public function pending(?User $suggestedBy = null): static
    {
        return $this->state(fn (): array => [
            'approved_at' => null,
            'created_by' => $suggestedBy?->id,
        ]);
    }

    /**
     * Give the tag a name in each of the given locales, so tests can exercise the
     * cross-language search without hand-building translation arrays.
     *
     * @param  array<string, string>  $names  locale => name
     */
    public function named(array $names): static
    {
        return $this->state(fn (): array => ['name' => $names]);
    }

    public function ofType(?string $type): static
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
