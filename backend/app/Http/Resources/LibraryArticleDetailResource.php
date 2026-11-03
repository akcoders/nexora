<?php

namespace App\Http\Resources;

use App\Models\LibraryArticle;
use App\Models\LibraryResource;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\URL;

/**
 * One library guide for the technician app: the card fields plus the written steps as HTML with
 * absolute URLs, its videos, documents (temporary signed download links) and web links, related
 * guides, and who wrote and last edited it. Expects category, resources, creator and editor to be
 * loaded.
 *
 * @mixin LibraryArticle
 */
class LibraryArticleDetailResource extends LibraryArticleResource
{
    /** Minutes a document download link stays valid. */
    public const FILE_LINK_MINUTES = 60;

    /**
     * @param  Collection<int, LibraryArticle>  $relatedGuides  loaded with category and resources
     */
    public function __construct(LibraryArticle $article, private readonly Collection $relatedGuides)
    {
        parent::__construct($article);
    }

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $resources = $this->resources;

        return [
            ...parent::toArray($request),
            'full_summary' => $this->summary,
            'body_html' => $this->absoluteBody(),
            'videos' => $resources->filter(fn (LibraryResource $resource) => $resource->isVideo())->map(fn (LibraryResource $video) => [
                'id' => $video->id,
                'title' => $video->displayTitle(),
                'video_id' => $video->video_id,
                'url' => $video->watchUrl(),
                'embed_url' => $video->embedUrl(),
                'thumbnail_url' => $video->thumbnailUrl(),
            ])->values()->all(),
            'documents' => $resources->filter(fn (LibraryResource $resource) => $resource->isDocument())->map(fn (LibraryResource $document) => [
                'id' => $document->id,
                'title' => $document->displayTitle(),
                'file_name' => $document->file_name,
                'extension' => $document->extension(),
                'size_label' => $document->readableSize(),
                'mime_type' => $document->mime_type,
                'url' => URL::temporarySignedRoute('api.library.files', now()->addMinutes(self::FILE_LINK_MINUTES), ['resource' => $document->id, 'user' => $request->user()?->id]),
            ])->values()->all(),
            'links' => $resources->filter(fn (LibraryResource $resource) => $resource->isLink())->map(fn (LibraryResource $link) => [
                'id' => $link->id,
                'title' => $link->displayTitle(),
                'url' => $link->url,
                'host' => preg_replace('/^www\./i', '', (string) parse_url((string) $link->url, PHP_URL_HOST)) ?: null,
            ])->values()->all(),
            'related' => LibraryArticleResource::collection($this->relatedGuides)->resolve($request),
            'author' => $this->creator ? ['name' => $this->creator->name] : null,
            'updated_by' => $this->editor ? ['name' => $this->editor->name] : null,
        ];
    }

    /**
     * The sanitised body with site-relative src / href values ("/storage/library/images/…")
     * made absolute, so images load inside the app's HTML renderer. Protocol-relative and
     * absolute URLs are left alone.
     */
    private function absoluteBody(): ?string
    {
        $html = trim((string) $this->body);
        if ($html === '') {
            return null;
        }

        return preg_replace_callback(
            '#\b(src|href)="(/(?!/)[^"]*)"#i',
            fn (array $match): string => $match[1].'="'.url($match[2]).'"',
            $html,
        ) ?? $html;
    }
}
