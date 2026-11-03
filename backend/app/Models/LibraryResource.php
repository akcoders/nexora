<?php

namespace App\Models;

use App\Services\LibraryOptions;
use Database\Factories\LibraryResourceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A YouTube video, uploaded document or web link attached to a library guide. Documents are
 * stored on the private local disk and served through permission-checked or signed routes.
 */
class LibraryResource extends Model
{
    /** @use HasFactory<LibraryResourceFactory> */
    use HasFactory;

    public const KIND_VIDEO = 'video';

    public const KIND_DOCUMENT = 'document';

    public const KIND_LINK = 'link';

    protected $guarded = [];

    protected function casts(): array
    {
        return ['file_size' => 'integer', 'sort_order' => 'integer'];
    }

    public function article(): BelongsTo
    {
        return $this->belongsTo(LibraryArticle::class, 'library_article_id');
    }

    public function isVideo(): bool
    {
        return $this->kind === self::KIND_VIDEO;
    }

    public function isDocument(): bool
    {
        return $this->kind === self::KIND_DOCUMENT;
    }

    public function isLink(): bool
    {
        return $this->kind === self::KIND_LINK;
    }

    /** Title to show, falling back to the file name, the link host or the kind. */
    public function displayTitle(): string
    {
        return $this->title
            ?: $this->file_name
            ?: ($this->isLink() ? (parse_url((string) $this->url, PHP_URL_HOST) ?: $this->url) : null)
            ?: LibraryOptions::KINDS[$this->kind]['label'] ?? 'Resource';
    }

    public function thumbnailUrl(): ?string
    {
        return $this->video_id ? LibraryOptions::youtubeThumbnail($this->video_id) : null;
    }

    public function embedUrl(): ?string
    {
        return $this->video_id ? LibraryOptions::youtubeEmbedUrl($this->video_id) : null;
    }

    public function watchUrl(): ?string
    {
        return $this->video_id ? LibraryOptions::youtubeWatchUrl($this->video_id) : $this->url;
    }

    /** Lower-case file extension of an uploaded document, e.g. "pdf". */
    public function extension(): ?string
    {
        $name = $this->file_name ?: $this->file_path;

        return $name ? strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: null : null;
    }

    /** Lucide icon matching the document type. */
    public function fileIcon(): string
    {
        return match ($this->extension()) {
            'pdf' => 'file-text',
            'doc', 'docx', 'txt' => 'file-type',
            'xls', 'xlsx', 'csv' => 'file-spreadsheet',
            'ppt', 'pptx' => 'presentation',
            'jpg', 'jpeg', 'png', 'webp' => 'file-image',
            default => 'file',
        };
    }

    public function readableSize(): ?string
    {
        return LibraryOptions::fileSize($this->file_size);
    }

    /** PDFs and images open in the browser; other documents download. */
    public function opensInline(): bool
    {
        return in_array($this->extension(), ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'txt'], true);
    }
}
