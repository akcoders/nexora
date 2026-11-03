<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LibraryArticle;
use App\Models\LibraryResource;
use App\Models\User;
use App\Services\LibraryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Mime\MimeTypes;

/**
 * Streams a library document to the technician app. The route is reached through the temporary
 * signed URL in the guide detail response (the app's file viewer cannot send the bearer token),
 * so the signature carries the reader: the link only works while that user can still see the guide
 * (published, not deleted, library access kept). PDFs, images and text open inline; other documents
 * download.
 */
class LibraryFileController extends Controller
{
    public function __invoke(Request $request, LibraryResource $resource): StreamedResponse
    {
        $reader = User::query()->active()->find($request->integer('user'));
        abort_unless($resource->isDocument() && filled($resource->file_path) && $reader !== null, 404, 'Document not found.');
        abort_unless(LibraryArticle::query()->visibleTo($reader)->whereKey($resource->library_article_id)->exists(), 404, 'Document not found.');
        $disk = Storage::disk(LibraryService::DISK);
        abort_unless($disk->exists($resource->file_path), 404, 'Document not found.');

        return $disk->response(
            $resource->file_path,
            $resource->file_name ?: basename($resource->file_path),
            [
                'Content-Type' => $this->contentType($resource),
                'X-Content-Type-Options' => 'nosniff',
                'Cache-Control' => 'private, max-age=3600',
            ],
            $resource->opensInline() ? 'inline' : 'attachment',
        );
    }

    /**
     * The standard type for the file extension (uploads are limited to known document and image
     * extensions), falling back to the type detected at upload.
     */
    private function contentType(LibraryResource $resource): string
    {
        $extension = $resource->extension();
        $type = $extension ? (MimeTypes::getDefault()->getMimeTypes($extension)[0] ?? null) : null;

        return $type ?? $resource->mime_type ?: 'application/octet-stream';
    }
}
