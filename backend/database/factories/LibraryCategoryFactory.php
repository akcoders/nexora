<?php

namespace Database\Factories;

use App\Models\LibraryCategory;
use App\Services\LibraryOptions;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<LibraryCategory>
 */
class LibraryCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = Str::title(fake()->unique()->words(2, true));

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => fake()->sentence(),
            'icon' => fake()->randomElement(['book-open', 'wrench', 'hammer', 'shield-check']),
            'color' => fake()->randomElement(array_keys(LibraryOptions::TONES)),
            'sort_order' => fake()->numberBetween(20, 90),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
