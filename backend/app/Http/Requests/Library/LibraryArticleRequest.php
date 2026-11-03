<?php

namespace App\Http\Requests\Library;

use App\Models\LibraryArticle;
use App\Models\LibraryResource;
use App\Services\LibraryOptions;
use App\Support\HtmlSanitizer;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Create / update a library guide. Rows in resources[] are the full list of videos, documents
 * and links to keep: rows with an id update an existing resource, rows without one are new.
 */
class LibraryArticleRequest extends FormRequest
{
    public function authorize(): bool
    {
        $article = $this->route('article');

        return $article instanceof LibraryArticle
            ? (bool) $this->user()?->can('library.update')
            : (bool) $this->user()?->can('library.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $article = $this->route('article');
        $current = fn (string $field): array => $article instanceof LibraryArticle && $article->{$field} ? [$article->{$field}] : [];

        return [
            'title' => ['required', 'string', 'max:180'],
            // Hidden categories are only kept by guides already filed under them.
            'library_category_id' => ['required', 'integer', Rule::exists('library_categories', 'id')->where(fn ($query) => $query->where('is_active', true)->orWhere('id', $article instanceof LibraryArticle ? $article->library_category_id : 0))],
            'summary' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:300000'],
            'device_type' => ['nullable', 'string', Rule::in([...array_keys(LibraryOptions::deviceTypes()), ...$current('device_type')])],
            'manufacturer' => ['nullable', 'string', Rule::in([...array_keys(LibraryOptions::manufacturers()), ...$current('manufacturer')])],
            'model_numbers' => ['nullable', 'string', 'max:255'],
            'tags' => ['nullable', 'string', 'max:255'],
            'status' => ['required', Rule::in(array_keys(LibraryOptions::STATUSES))],
            'is_featured' => ['nullable', 'boolean'],
            'resources' => ['nullable', 'array', 'max:30'],
            'resources.*.id' => ['nullable', 'integer'],
            'resources.*.kind' => ['required', Rule::in(array_keys(LibraryOptions::KINDS))],
            'resources.*.title' => ['nullable', 'string', 'max:180'],
            'resources.*.url' => ['nullable', 'required_if:resources.*.kind,video,link', 'string', 'max:2048', 'url:http,https'],
            'resources.*.file' => ['nullable', 'file', 'extensions:'.implode(',', LibraryOptions::DOCUMENT_EXTENSIONS), 'mimes:'.implode(',', LibraryOptions::DOCUMENT_EXTENSIONS), 'max:'.LibraryOptions::MAX_DOCUMENT_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'library_category_id.required' => 'Choose a category for this guide.',
            'resources.*.url.required_if' => 'Paste the link for this resource.',
            'resources.*.url.url' => 'Enter a full link starting with http:// or https://.',
            'resources.*.file.mimes' => 'Upload a PDF, Word, Excel, PowerPoint, text, CSV or image file.',
            'resources.*.file.extensions' => 'Upload a PDF, Word, Excel, PowerPoint, text, CSV or image file.',
            'resources.*.file.max' => 'Documents can be up to '.(LibraryOptions::MAX_DOCUMENT_KB / 1024).' MB.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'library_category_id' => 'category',
            'resources.*.kind' => 'resource type',
            'resources.*.title' => 'resource title',
            'resources.*.url' => 'link',
            'resources.*.file' => 'document',
        ];
    }

    /**
     * YouTube rows need a real video link, new document rows need a file, existing rows must
     * belong to this guide, and the guide needs at least written steps or one resource.
     *
     * These run together with the field rules (skipping fields that already have an error), so
     * every problem is reported on the first save instead of one round at a time.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            $errors = $validator->errors();
            $resources = $this->input('resources', []);
            if (! is_array($resources) || $errors->has('resources')) {
                return;
            }
            $article = $this->route('article');
            $existing = $article instanceof LibraryArticle ? $article->resources()->get()->keyBy('id') : collect();
            $existingIds = $existing->keys()->all();

            foreach ($resources as $index => $row) {
                if (! is_array($row)) {
                    continue;
                }
                $id = $row['id'] ?? null;
                if ($id && ! $errors->has("resources.{$index}.id") && ! in_array((int) $id, $existingIds, true)) {
                    $errors->add("resources.{$index}.id", 'This resource does not belong to the guide. Reload the page and try again.');
                }
                $url = $row['url'] ?? null;
                if (($row['kind'] ?? null) === LibraryResource::KIND_VIDEO && ! $errors->has("resources.{$index}.url") && ! LibraryOptions::youtubeId(is_string($url) ? $url : null)) {
                    $errors->add("resources.{$index}.url", 'Paste a YouTube video link, e.g. https://www.youtube.com/watch?v=… or https://youtu.be/….');
                }
                // A new document row, or a saved video/link switched to a document, needs a file.
                $hasStoredFile = $id && $existing->get((int) $id)?->isDocument() && filled($existing->get((int) $id)?->file_path);
                if (($row['kind'] ?? null) === LibraryResource::KIND_DOCUMENT && ! $hasStoredFile && ! $errors->has("resources.{$index}.file") && ! $this->file("resources.{$index}.file")) {
                    $errors->add("resources.{$index}.file", 'Choose the document to upload.');
                }
            }

            $body = $this->input('body');
            if ($resources === [] && ! $errors->has('body') && app(HtmlSanitizer::class)->clean(is_string($body) ? $body : '') === '') {
                $errors->add('body', 'Write the steps, or add a video, document or link.');
            }
        }];
    }
}
