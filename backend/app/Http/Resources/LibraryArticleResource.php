<?php

namespace App\Http\Resources;

use App\Models\LibraryArticle;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Library guide card for the technician app list, "related" rows and the top of the detail
 * response. Expects the guide to be loaded with category and resources so the counts and the
 * video cover need no extra queries.
 *
 * @mixin LibraryArticle
 */
class LibraryArticleResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $category = $this->category;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'summary' => $this->excerpt(),
            'category' => $category ? $category->only(['id', 'name', 'slug', 'icon', 'color']) : null,
            'device_type' => $this->codeLabel($this->device_type, $this->deviceTypeLabel()),
            'manufacturer' => $this->codeLabel($this->manufacturer, $this->manufacturerLabel()),
            'model_numbers' => $this->model_numbers,
            'tags' => $this->tagList(),
            'status' => $this->status,
            'is_featured' => (bool) $this->is_featured,
            'has_steps' => $this->hasWrittenSteps(),
            'counts' => $this->resourceCounts(),
            'cover_url' => $this->coverUrl(),
            'views_count' => (int) $this->views_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /** @return array{code: string, label: string}|null */
    private function codeLabel(?string $code, ?string $label): ?array
    {
        return $code === null || $code === '' ? null : ['code' => $code, 'label' => $label ?? $code];
    }
}
