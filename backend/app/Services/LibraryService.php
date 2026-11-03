<?php

namespace App\Services;

use App\Models\LibraryArticle;
use App\Models\LibraryResource;
use App\Models\User;
use App\Support\HtmlSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Saves library guides with their videos, documents and links. Rich text is sanitised before it
 * is stored, and documents live on the private local disk under library/{article id}.
 */
class LibraryService
{
    public const DISK = 'local';

    /** Repeat views of a guide by the same user within this window count once. */
    public const VIEW_WINDOW_HOURS = 6;

    public function __construct(private HtmlSanitizer $sanitizer) {}

    /**
     * Create or update a guide from validated LibraryArticleRequest data. resources[] is the full
     * list to keep: rows with an id update that resource, rows without one are created, and
     * resources missing from the list are deleted together with their files.
     *
     * @param  array<string, mixed>  $data
     */
    public function save(?LibraryArticle $article, array $data, User $user): LibraryArticle
    {
        $body = $this->sanitizer->clean($data['body'] ?? null);
        $article ??= new LibraryArticle(['created_by' => $user->id]);
        $wasPublished = $article->exists && $article->isPublished();

        $article->fill([
            'library_category_id' => (int) $data['library_category_id'],
            'title' => trim($data['title']),
            'summary' => $this->nullableText($data['summary'] ?? null),
            'body' => $body !== '' ? $body : null,
            'body_text' => $body !== '' ? $this->sanitizer->toPlainText($body) : null,
            'device_type' => $data['device_type'] ?? null ?: null,
            'manufacturer' => $data['manufacturer'] ?? null ?: null,
            'model_numbers' => $this->nullableText($data['model_numbers'] ?? null),
            'tags' => $this->normalizeTags($data['tags'] ?? null),
            'status' => $data['status'],
            'is_featured' => (bool) ($data['is_featured'] ?? false),
            'updated_by' => $user->id,
        ]);
        if ($article->isPublished() && (! $wasPublished || ! $article->published_at)) {
            $article->published_at = now();
        }

        $storedFiles = [];
        $removedFiles = [];

        try {
            DB::transaction(function () use ($article, $data, &$storedFiles, &$removedFiles): void {
                $article->save();
                $existing = $article->resources()->get()->keyBy('id');
                $keep = [];

                foreach (array_values($data['resources'] ?? []) as $order => $row) {
                    $resource = isset($row['id']) && $row['id'] ? $existing->get((int) $row['id']) : null;
                    $resource ??= new LibraryResource(['library_article_id' => $article->id]);
                    $attributes = $this->resourceAttributes($row, $order);

                    $file = $row['file'] ?? null;
                    if ($attributes['kind'] === LibraryResource::KIND_DOCUMENT && $file instanceof UploadedFile) {
                        if ($resource->file_path) {
                            $removedFiles[] = $resource->file_path;
                        }
                        $path = $file->store("library/{$article->id}", self::DISK);
                        $storedFiles[] = $path;
                        $attributes += [
                            'file_path' => $path,
                            'file_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                            'mime_type' => $file->getMimeType(),
                            'file_size' => $file->getSize(),
                        ];
                    } elseif ($attributes['kind'] !== LibraryResource::KIND_DOCUMENT && $resource->file_path) {
                        $removedFiles[] = $resource->file_path;
                        $attributes += ['file_path' => null, 'file_name' => null, 'mime_type' => null, 'file_size' => null];
                    }

                    $resource->fill($attributes)->save();
                    $keep[] = $resource->id;
                }

                $existing->except($keep)->each(function (LibraryResource $resource) use (&$removedFiles): void {
                    if ($resource->file_path) {
                        $removedFiles[] = $resource->file_path;
                    }
                    $resource->delete();
                });
            });
        } catch (\Throwable $exception) {
            Storage::disk(self::DISK)->delete($storedFiles);

            throw $exception;
        }

        Storage::disk(self::DISK)->delete($removedFiles);

        return $article->load(['category', 'resources']);
    }

    /** Soft-delete a guide. Files are kept so the guide can be restored from the database. */
    public function delete(LibraryArticle $article): void
    {
        $article->delete();
    }

    /**
     * Count a view at most once per user and guide every few hours (web and app alike). The
     * counter is raised on the base query so the guide's updated_at keeps the last edit.
     */
    public function recordView(LibraryArticle $article, User $user): void
    {
        if (Cache::add("library_viewed.{$user->id}.{$article->id}", true, now()->addHours(self::VIEW_WINDOW_HOURS))) {
            $article->newQuery()->whereKey($article->id)->toBase()->increment('views_count');
            $article->views_count++;
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function resourceAttributes(array $row, int $order): array
    {
        $kind = $row['kind'];
        $url = $kind === LibraryResource::KIND_DOCUMENT ? null : trim((string) ($row['url'] ?? ''));
        $videoId = $kind === LibraryResource::KIND_VIDEO ? LibraryOptions::youtubeId($url) : null;

        return [
            'kind' => $kind,
            'title' => $this->nullableText($row['title'] ?? null),
            'url' => $videoId ? LibraryOptions::youtubeWatchUrl($videoId) : ($url ?: null),
            'video_id' => $videoId,
            'sort_order' => $order,
        ];
    }

    private function nullableText(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** "ac, Gas leak ,, AC" becomes "ac, Gas leak". */
    private function normalizeTags(mixed $tags): ?string
    {
        $list = collect(explode(',', (string) $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->unique(fn (string $tag) => mb_strtolower($tag))
            ->take(15);

        return $list->isEmpty() ? null : mb_substr($list->implode(', '), 0, 255);
    }
}
