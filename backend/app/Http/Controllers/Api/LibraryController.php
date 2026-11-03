<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\LibraryArticleDetailResource;
use App\Http\Resources\LibraryArticleResource;
use App\Models\LibraryArticle;
use App\Models\LibraryCategory;
use App\Models\User;
use App\Services\LibraryOptions;
use App\Services\LibraryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/**
 * Knowledge library for the technician app: the filter lists, a searchable paginated list of
 * guides and one guide with its written steps, videos, documents and links. Everyone with
 * library.read sees published guides; users who can create, update or delete guides also see
 * drafts.
 */
class LibraryController extends Controller
{
    public const PER_PAGE = 20;

    public const MAX_PER_PAGE = 50;

    public const RELATED_LIMIT = 5;

    public function __construct(private readonly LibraryService $library) {}

    public function filters(Request $request): JsonResponse
    {
        $user = $this->reader($request);

        $categories = LibraryCategory::query()
            ->active()
            ->ordered()
            ->withCount(['articles' => fn (Builder $articles) => $articles->visibleTo($user)])
            ->get();

        return response()->json([
            'categories' => $categories->map(fn (LibraryCategory $category) => [
                ...$category->only(['id', 'name', 'slug', 'icon', 'color', 'description']),
                'count' => (int) $category->articles_count,
            ])->values(),
            'device_types' => $this->codeLabels(LibraryOptions::deviceTypes()),
            'manufacturers' => $this->codeLabels(LibraryOptions::manufacturers()),
            'content_types' => collect(LibraryOptions::CONTENT_FILTERS)
                ->map(fn (array $filter, string $key) => ['key' => $key, 'label' => $filter['label'], 'icon' => $filter['icon']])
                ->values(),
        ]);
    }

    public function index(Request $request): JsonResponse
    {
        $user = $this->reader($request);
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'category' => ['nullable', 'string', 'max:140'],
            'device_type' => ['nullable', 'string', 'max:60'],
            'manufacturer' => ['nullable', 'string', 'max:60'],
            'content' => ['nullable', Rule::in(array_keys(LibraryOptions::CONTENT_FILTERS))],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1'],
        ]);
        $term = trim((string) ($filters['q'] ?? ''));

        $guides = LibraryArticle::query()
            ->visibleTo($user)
            ->filter($filters)
            ->with(['category', 'resources'])
            ->when($term !== '', fn (Builder $query) => $query->orderByRaw('CASE WHEN library_articles.title LIKE ? THEN 0 ELSE 1 END', ['%'.$term.'%']))
            ->latestFirst()
            ->paginate(min((int) ($filters['per_page'] ?? self::PER_PAGE), self::MAX_PER_PAGE));

        return response()->json([
            'data' => LibraryArticleResource::collection($guides->getCollection())->resolve($request),
            'meta' => [
                'current_page' => $guides->currentPage(),
                'last_page' => $guides->lastPage(),
                'per_page' => $guides->perPage(),
                'total' => $guides->total(),
            ],
        ]);
    }

    public function show(Request $request, LibraryArticle $article): JsonResponse
    {
        $user = $this->reader($request);
        abort_unless(LibraryArticle::query()->visibleTo($user)->whereKey($article->getKey())->exists(), 404, 'Guide not found.');

        $this->library->recordView($article, $user);
        $article->refresh()->load(['category', 'resources', 'creator:id,name', 'editor:id,name']);

        return response()->json([
            'data' => (new LibraryArticleDetailResource($article, $this->related($article, $user)))->resolve($request),
        ]);
    }

    /** The signed-in user, after checking they may read the library. */
    private function reader(Request $request): User
    {
        $user = $request->user();
        abort_unless($user->can('library.read'), 403, 'You do not have access to the library.');

        return $user;
    }

    /**
     * Up to five other visible guides from the same category or for the same device type; guides
     * matching both come first.
     *
     * @return Collection<int, LibraryArticle>
     */
    private function related(LibraryArticle $article, User $user): Collection
    {
        return LibraryArticle::query()
            ->visibleTo($user)
            ->whereKeyNot($article->getKey())
            ->where(fn (Builder $query) => $query
                ->where('library_articles.library_category_id', $article->library_category_id)
                ->when($article->device_type, fn (Builder $query, string $deviceType) => $query->orWhere('library_articles.device_type', $deviceType)))
            ->with(['category', 'resources'])
            ->orderByRaw(
                '(CASE WHEN library_articles.library_category_id = ? THEN 0 ELSE 1 END) + (CASE WHEN library_articles.device_type = ? THEN 0 ELSE 1 END)',
                [$article->library_category_id, (string) $article->device_type],
            )
            ->latestFirst()
            ->limit(self::RELATED_LIMIT)
            ->get();
    }

    /**
     * @param  array<string, string>  $options  code => label
     * @return list<array{code: string, label: string}>
     */
    private function codeLabels(array $options): array
    {
        return collect($options)->map(fn (string $label, string $code) => ['code' => (string) $code, 'label' => $label])->values()->all();
    }
}
