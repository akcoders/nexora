<?php

namespace App\Http\Controllers;

use App\Http\Requests\Library\LibraryArticleRequest;
use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Models\LibraryResource;
use App\Models\User;
use App\Services\LibraryOptions;
use App\Services\LibraryService;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Knowledge library in the web admin: browse and read how-to guides, and (for editors) write
 * them with the rich text editor, attach videos, documents and links, and manage the list.
 */
class LibraryController extends Controller
{
    public const PER_PAGE = 18;

    /** @var array<string, string> */
    public const SORTS = ['latest' => 'Latest', 'views' => 'Most viewed', 'title' => 'A–Z'];

    /** @var list<string> */
    private const EDITOR_PERMISSIONS = ['library.create', 'library.update', 'library.delete'];

    /** Manage guides lists the most recently updated guides up to this many rows. */
    private const MANAGE_LIMIT = 1000;

    /** Content types a document is served as when it opens in the browser. */
    private const INLINE_TYPES = [
        'pdf' => 'application/pdf',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'txt' => 'text/plain; charset=UTF-8',
    ];

    public function __construct(private LibraryService $library) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->can('library.read'), 403);

        $filters = $this->filters($request);
        $sort = array_key_exists((string) $request->query('sort'), self::SORTS) ? (string) $request->query('sort') : 'latest';
        $hasFilters = collect($filters)->filter()->isNotEmpty();

        $query = LibraryArticle::query()->visibleTo($user)->filter($filters)->with(['category', 'resources']);
        match ($sort) {
            'views' => $query->orderByDesc('views_count')->orderByDesc('updated_at'),
            'title' => $query->orderBy('title'),
            default => $query->orderByDesc('updated_at'),
        };
        $articles = $query->orderByDesc('id')->paginate(self::PER_PAGE)->withQueryString();

        $categories = LibraryCategory::query()->active()->ordered()->get();
        $activeCategory = $filters['category'] === '' ? null : ($categories->first(fn (LibraryCategory $category) => (string) $category->id === $filters['category'] || $category->slug === $filters['category'])
            ?? LibraryCategory::query()->where(is_numeric($filters['category']) ? 'id' : 'slug', $filters['category'])->first());

        $featured = ! $hasFilters && $articles->onFirstPage()
            ? LibraryArticle::query()->visibleTo($user)->published()->where('is_featured', true)->with(['category', 'resources'])->latestFirst()->limit(3)->get()
            : collect();

        return view('library.index', [
            'articles' => $articles,
            'featured' => $featured,
            'filters' => $filters,
            'sort' => $sort,
            'hasFilters' => $hasFilters,
            'categories' => $categories,
            'activeCategory' => $activeCategory,
            'categoryCounts' => LibraryArticle::query()->visibleTo($user)->filter([...$filters, 'category' => null])
                ->selectRaw('library_category_id, count(*) as aggregate')->groupBy('library_category_id')->pluck('aggregate', 'library_category_id'),
            'totals' => $this->totals($user),
            'popularTags' => $this->popularTags($user),
            'deviceTypes' => LibraryOptions::deviceTypes(),
            'manufacturers' => LibraryOptions::manufacturers(),
            'canEdit' => $user->canAny(self::EDITOR_PERMISSIONS),
        ]);
    }

    public function manage(Request $request): View
    {
        $user = $request->user();
        abort_unless($user->canAny(self::EDITOR_PERMISSIONS), 403);
        $status = in_array($request->query('status'), ['published', 'draft', 'featured'], true) ? $request->query('status') : 'all';

        $base = LibraryArticle::query();
        $counts = [
            'all' => (clone $base)->count(),
            'published' => (clone $base)->published()->count(),
            'draft' => (clone $base)->where('status', LibraryOptions::STATUS_DRAFT)->count(),
            'featured' => (clone $base)->where('is_featured', true)->count(),
        ];

        $articles = LibraryArticle::query()
            ->with(['category', 'resources', 'editor'])
            ->when($status === 'published', fn (Builder $query) => $query->published())
            ->when($status === 'draft', fn (Builder $query) => $query->where('status', LibraryOptions::STATUS_DRAFT))
            ->when($status === 'featured', fn (Builder $query) => $query->where('is_featured', true))
            ->orderByDesc('updated_at')
            ->limit(self::MANAGE_LIMIT)
            ->get();

        return view('library.manage', [
            'articles' => $articles,
            'status' => $status,
            'counts' => $counts,
            'views' => (int) LibraryArticle::query()->sum('views_count'),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->can('library.create'), 403);

        return view('library.form', $this->formData(new LibraryArticle([
            'status' => LibraryOptions::STATUS_PUBLISHED,
            'library_category_id' => LibraryCategory::query()->active()->where('slug', (string) $request->query('category'))->value('id'),
        ])));
    }

    public function store(LibraryArticleRequest $request): RedirectResponse
    {
        $article = $this->library->save(null, $request->validated(), $request->user());

        return redirect()->route('library.show', $article)->with('success', $article->isPublished() ? 'Guide published to the library.' : 'Guide saved as a draft.');
    }

    public function show(Request $request, LibraryArticle $article): View
    {
        $user = $request->user();
        $this->ensureVisible($user, $article);
        $this->library->recordView($article, $user);
        $article->refresh()->load(['category', 'resources', 'creator', 'editor']);

        $related = LibraryArticle::query()
            ->visibleTo($user)
            ->whereKeyNot($article->id)
            ->where(fn (Builder $query) => $query
                ->where('library_category_id', $article->library_category_id)
                ->when($article->device_type, fn (Builder $query) => $query->orWhere('device_type', $article->device_type)))
            ->with(['category', 'resources'])
            ->orderByRaw('case when library_category_id = ? then 0 else 1 end', [$article->library_category_id])
            ->orderByDesc('views_count')
            ->limit(5)
            ->get();

        return view('library.show', [
            'article' => $article,
            'related' => $related,
            'videos' => $article->resources->filter->isVideo()->values(),
            'documents' => $article->resources->filter->isDocument()->values(),
            'links' => $article->resources->filter->isLink()->values(),
            'canUpdate' => $user->can('library.update'),
            'canDelete' => $user->can('library.delete'),
            'canEdit' => $user->canAny(self::EDITOR_PERMISSIONS),
        ]);
    }

    public function edit(Request $request, LibraryArticle $article): View
    {
        abort_unless($request->user()->can('library.update'), 403);

        return view('library.form', $this->formData($article->load(['category', 'resources', 'creator', 'editor'])));
    }

    public function update(LibraryArticleRequest $request, LibraryArticle $article): RedirectResponse
    {
        $article = $this->library->save($article, $request->validated(), $request->user());

        return redirect()->route('library.show', $article)->with('success', 'Guide updated.');
    }

    public function destroy(Request $request, LibraryArticle $article): RedirectResponse
    {
        abort_unless($request->user()->can('library.delete'), 403);
        $this->library->delete($article);

        return redirect()->route('library.manage')->with('success', '"'.$article->title.'" was deleted.');
    }

    /** Stream an attached document: PDFs, images and text open in the browser, the rest download. */
    public function file(Request $request, LibraryArticle $article, LibraryResource $resource): StreamedResponse
    {
        $this->ensureVisible($request->user(), $article);
        $disk = Storage::disk(LibraryService::DISK);
        abort_unless($resource->isDocument() && $resource->file_path && $disk->exists($resource->file_path), 404);

        $name = $resource->file_name ?: basename($resource->file_path);
        $inlineType = self::INLINE_TYPES[$resource->extension()] ?? null;

        return $resource->opensInline() && $inlineType
            ? $disk->response($resource->file_path, $name, ['Content-Type' => $inlineType, 'X-Content-Type-Options' => 'nosniff'], 'inline')
            : $disk->download($resource->file_path, $name, ['Content-Type' => $resource->mime_type ?: 'application/octet-stream', 'X-Content-Type-Options' => 'nosniff']);
    }

    /** Image picked in the editor toolbar (or pasted / dropped): stored publicly, the URL goes into the guide. */
    public function uploadImage(Request $request): JsonResponse
    {
        abort_unless($request->user()->canAny(['library.create', 'library.update']), 403);
        $request->validate(
            ['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,gif', 'max:'.LibraryOptions::MAX_IMAGE_KB]],
            [
                'image.required' => 'Choose an image to upload.',
                'image.image' => 'Only JPG, PNG, WebP or GIF images can be added to a guide.',
                'image.mimes' => 'Only JPG, PNG, WebP or GIF images can be added to a guide.',
                'image.max' => 'Images can be up to '.(LibraryOptions::MAX_IMAGE_KB / 1024).' MB.',
            ],
        );

        $path = $request->file('image')->store('library/images', 'public');

        // A path relative to the site (not an absolute URL) keeps working if the domain changes.
        return response()->json(['url' => parse_url(asset('storage/'.$path), PHP_URL_PATH)], 201);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(LibraryArticle $article): array
    {
        $categories = LibraryCategory::query()->ordered()->get()
            ->filter(fn (LibraryCategory $category) => $category->is_active || $category->id === $article->library_category_id)
            ->values();

        return [
            'article' => $article,
            'categories' => $categories,
            'deviceTypes' => $this->withCurrent(LibraryOptions::deviceTypes(), $article->device_type, 'library_device_type'),
            'manufacturers' => $this->withCurrent(LibraryOptions::manufacturers(), $article->manufacturer, 'library_manufacturer'),
        ];
    }

    /**
     * Active master entries, plus the guide's current (possibly deactivated) value.
     *
     * @param  array<string, string>  $options
     * @return array<string, string>
     */
    private function withCurrent(array $options, ?string $current, string $type): array
    {
        if ($current && ! array_key_exists($current, $options)) {
            $options[$current] = LibraryOptions::label($type, $current).' (inactive)';
        }

        return $options;
    }

    /**
     * @return array{q: string, category: string, device_type: string, manufacturer: string, content: string}
     */
    private function filters(Request $request): array
    {
        $text = fn (string $key, int $max): string => is_string($value = $request->query($key)) ? mb_substr(trim($value), 0, $max) : '';
        $content = $text('content', 20);

        return [
            'q' => $text('q', 120),
            'category' => $text('category', 140),
            'device_type' => $text('device_type', 60),
            'manufacturer' => $text('manufacturer', 60),
            'content' => array_key_exists($content, LibraryOptions::CONTENT_FILTERS) ? $content : '',
        ];
    }

    /**
     * Guide, video, document and link totals the user can see.
     *
     * @return array{guides: int, video: int, document: int, link: int}
     */
    private function totals(User $user): array
    {
        $kinds = LibraryResource::query()
            ->whereHas('article', fn (Builder $query) => $query->visibleTo($user))
            ->selectRaw('kind, count(*) as aggregate')
            ->groupBy('kind')
            ->pluck('aggregate', 'kind');

        return [
            'guides' => LibraryArticle::query()->visibleTo($user)->count(),
            'video' => (int) ($kinds[LibraryResource::KIND_VIDEO] ?? 0),
            'document' => (int) ($kinds[LibraryResource::KIND_DOCUMENT] ?? 0),
            'link' => (int) ($kinds[LibraryResource::KIND_LINK] ?? 0),
        ];
    }

    /**
     * The most used tags across the guides the user can see, for one-tap searches.
     *
     * @return Collection<int, string>
     */
    private function popularTags(User $user, int $limit = 6): Collection
    {
        return LibraryArticle::query()->visibleTo($user)->whereNotNull('tags')->latest('updated_at')->limit(300)->pluck('tags')
            ->flatMap(fn (string $tags) => explode(',', $tags))
            ->map(fn (string $tag) => trim($tag))
            ->filter()
            ->groupBy(fn (string $tag) => mb_strtolower($tag))
            ->sortByDesc(fn ($group) => $group->count())
            ->take($limit)
            ->map(fn ($group) => $group->first())
            ->values();
    }

    /** Readers see published guides; drafts are only visible to people who can edit the library. */
    private function ensureVisible(User $user, LibraryArticle $article): void
    {
        abort_unless($user->can('library.read'), 403);
        abort_unless($article->isPublished() || $user->canAny(self::EDITOR_PERMISSIONS), 404);
    }
}
