<?php

namespace App\Models;

use App\Services\LibraryOptions;
use Database\Factories\LibraryArticleFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * A "how to" guide in the knowledge library: written steps (sanitised HTML) plus any number of
 * YouTube videos, documents and web links, tagged by category, device type and manufacturer.
 */
class LibraryArticle extends Model
{
    /** @use HasFactory<LibraryArticleFactory> */
    use HasFactory, SoftDeletes;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'is_featured' => 'boolean',
            'published_at' => 'datetime',
            'views_count' => 'integer',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LibraryCategory::class, 'library_category_id');
    }

    public function resources(): HasMany
    {
        return $this->hasMany(LibraryResource::class)->orderBy('sort_order')->orderBy('id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopePublished(Builder $query): void
    {
        $query->where($this->qualifyColumn('status'), LibraryOptions::STATUS_PUBLISHED);
    }

    /** Everyone with library.read sees published guides; editors also see drafts. */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->can('library.read')) {
            $query->whereRaw('1 = 0');

            return;
        }
        if (! $user->canAny(['library.create', 'library.update', 'library.delete'])) {
            $query->published();
        }
    }

    /**
     * Every word must appear in the title, summary, written steps, tags, model numbers or the
     * category name.
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $words = collect(preg_split('/\s+/', trim((string) $term)))->filter(fn (string $word) => $word !== '')->take(8);
        foreach ($words as $word) {
            $like = '%'.$word.'%';
            $query->where(fn (Builder $query) => $query
                ->where($this->qualifyColumn('title'), 'like', $like)
                ->orWhere($this->qualifyColumn('summary'), 'like', $like)
                ->orWhere($this->qualifyColumn('body_text'), 'like', $like)
                ->orWhere($this->qualifyColumn('tags'), 'like', $like)
                ->orWhere($this->qualifyColumn('model_numbers'), 'like', $like)
                ->orWhereHas('category', fn (Builder $category) => $category->where('name', 'like', $like))
                ->orWhereHas('resources', fn (Builder $resource) => $resource->where('title', 'like', $like)->orWhere('file_name', 'like', $like)));
        }
    }

    /**
     * Browse filters: category (id or slug), device_type, manufacturer and content
     * (article, video, document or link).
     *
     * @param  array{category?: mixed, device_type?: mixed, manufacturer?: mixed, content?: mixed, q?: mixed}  $filters
     */
    public function scopeFilter(Builder $query, array $filters): void
    {
        $query->search(is_string($filters['q'] ?? null) ? $filters['q'] : null);

        $category = $filters['category'] ?? null;
        if (is_numeric($category)) {
            $query->where($this->qualifyColumn('library_category_id'), (int) $category);
        } elseif (is_string($category) && $category !== '') {
            $query->whereHas('category', fn (Builder $builder) => $builder->where('slug', $category));
        }
        foreach (['device_type', 'manufacturer'] as $field) {
            if (is_string($filters[$field] ?? null) && $filters[$field] !== '') {
                $query->where($this->qualifyColumn($field), $filters[$field]);
            }
        }

        match ($filters['content'] ?? null) {
            'article' => $query->whereNotNull($this->qualifyColumn('body_text'))->where($this->qualifyColumn('body_text'), '!=', ''),
            LibraryResource::KIND_VIDEO, LibraryResource::KIND_DOCUMENT, LibraryResource::KIND_LINK => $query->whereHas('resources', fn (Builder $resource) => $resource->where('kind', $filters['content'])),
            default => null,
        };
    }

    /** Featured guides first, then the most recently published or updated. */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc($this->qualifyColumn('is_featured'))
            ->orderByDesc($this->qualifyColumn('published_at'))
            ->orderByDesc($this->qualifyColumn('updated_at'))
            ->orderByDesc($this->qualifyColumn('id'));
    }

    public function isPublished(): bool
    {
        return $this->status === LibraryOptions::STATUS_PUBLISHED;
    }

    public function hasWrittenSteps(): bool
    {
        return trim((string) $this->body_text) !== '';
    }

    /** @return array{label: string, tone: string} */
    public function statusMeta(): array
    {
        return LibraryOptions::STATUSES[$this->status] ?? ['label' => Str::headline((string) $this->status), 'tone' => 'secondary'];
    }

    public function deviceTypeLabel(): ?string
    {
        return LibraryOptions::label('library_device_type', $this->device_type);
    }

    public function manufacturerLabel(): ?string
    {
        return LibraryOptions::label('library_manufacturer', $this->manufacturer);
    }

    /** @return list<string> */
    public function tagList(): array
    {
        return collect(explode(',', (string) $this->tags))->map(fn (string $tag) => trim($tag))->filter()->unique()->values()->all();
    }

    /** Short text for cards: the summary, or the start of the written steps. */
    public function excerpt(int $limit = 160): string
    {
        return Str::limit($this->summary ?: (string) $this->body_text, $limit);
    }

    /**
     * Counts of attached resources per kind, from a loaded resources relation.
     *
     * @return array{video: int, document: int, link: int}
     */
    public function resourceCounts(): array
    {
        $counts = ['video' => 0, 'document' => 0, 'link' => 0];
        foreach ($this->resources as $resource) {
            if (isset($counts[$resource->kind])) {
                $counts[$resource->kind]++;
            }
        }

        return $counts;
    }

    /** First video's thumbnail, used as the card cover. */
    public function coverUrl(): ?string
    {
        return $this->resources->first(fn (LibraryResource $resource) => $resource->isVideo())?->thumbnailUrl();
    }
}
