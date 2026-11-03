<?php

namespace Database\Factories;

use App\Models\LibraryArticle;
use App\Models\LibraryResource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LibraryResource>
 */
class LibraryResourceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'library_article_id' => LibraryArticle::factory(),
            'kind' => LibraryResource::KIND_LINK,
            'title' => fake()->sentence(3),
            'url' => fake()->url(),
            'sort_order' => 0,
        ];
    }

    public function video(string $videoId = 'dQw4w9WgXcQ'): static
    {
        return $this->state(fn (): array => [
            'kind' => LibraryResource::KIND_VIDEO,
            'url' => "https://www.youtube.com/watch?v={$videoId}",
            'video_id' => $videoId,
        ]);
    }

    public function document(string $path = 'library/1/manual.pdf', string $name = 'manual.pdf'): static
    {
        return $this->state(fn (): array => [
            'kind' => LibraryResource::KIND_DOCUMENT,
            'url' => null,
            'file_path' => $path,
            'file_name' => $name,
            'mime_type' => 'application/pdf',
            'file_size' => 125000,
        ]);
    }
}
