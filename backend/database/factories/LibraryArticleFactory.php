<?php

namespace Database\Factories;

use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Services\LibraryOptions;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryArticle>
 */
class LibraryArticleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $steps = fake()->sentences(3);

        return [
            'library_category_id' => LibraryCategory::factory(),
            'title' => 'How to '.fake()->words(4, true),
            'summary' => fake()->sentence(12),
            'body' => '<h2>Steps</h2><ol>'.collect($steps)->map(fn (string $step) => "<li>{$step}</li>")->implode('').'</ol>',
            'body_text' => "Steps\n".implode("\n", $steps),
            'status' => LibraryOptions::STATUS_PUBLISHED,
            'is_featured' => false,
            'published_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (): array => ['status' => LibraryOptions::STATUS_DRAFT, 'published_at' => null]);
    }

    public function featured(): static
    {
        return $this->state(fn (): array => ['is_featured' => true]);
    }
}
