@php
    /**
     * Guide card for the library grid. $wide gives the horizontal "featured" layout.
     *
     * @var \App\Models\LibraryArticle $article
     */
    $wide ??= false;
    $category = $article->category;
    $tone = $category?->color ?: 'primary';
    $counts = $article->resourceCounts();
    $cover = $article->coverUrl();
    $excerpt = $article->excerpt($wide ? 260 : 150);
@endphp
<article class="lib-card tone-{{ $tone }} {{ $wide ? 'lib-card--wide' : '' }}">
    <div class="lib-cover">
        @if($cover)
            <img src="{{ $cover }}" alt="" loading="lazy" referrerpolicy="no-referrer">
            <span class="lib-play" aria-hidden="true"><i data-lucide="play"></i></span>
        @else
            <span class="lib-cover-panel" aria-hidden="true"><span><i data-lucide="{{ $category?->icon ?: 'book-open' }}"></i></span></span>
        @endif
        <span class="lib-cover-badges">
            @if($article->is_featured)<span class="badge lib-badge-featured"><i data-lucide="star"></i>Featured</span>@endif
            @if(($canEdit ?? false) && ! $article->isPublished())<span class="badge lib-badge-draft"><i data-lucide="pencil-line"></i>Draft</span>@endif
        </span>
        @if($counts['video'] > 1)<span class="lib-cover-count"><i data-lucide="square-play"></i>{{ $counts['video'] }} videos</span>@endif
    </div>
    <div class="lib-card-main">
        <div class="lib-card-body">
            @if($category)<x-pill :tone="$tone" :label="$category->name" class="lib-card-cat" />@endif
            <h3 class="lib-card-title"><a href="{{ route('library.show', $article) }}">{{ $article->title }}</a></h3>
            @if($excerpt !== '')<p class="lib-card-excerpt">{{ $excerpt }}</p>@endif
            @if($article->device_type || $article->manufacturer || $article->model_numbers)
                <div class="lib-card-tags">
                    @if($article->device_type)<span class="nx-tag"><i data-lucide="air-vent"></i>{{ $article->deviceTypeLabel() }}</span>@endif
                    @if($article->manufacturer)<span class="nx-tag"><i data-lucide="factory"></i>{{ $article->manufacturerLabel() }}</span>@endif
                    @if($article->model_numbers && ($wide || ! ($article->device_type && $article->manufacturer)))<span class="nx-tag notranslate"><i data-lucide="hash"></i>{{ \Illuminate\Support\Str::limit($article->model_numbers, $wide ? 40 : 24) }}</span>@endif
                </div>
            @endif
        </div>
        <div class="lib-card-foot">
            <div class="lib-counts">
                @if($article->hasWrittenSteps())<span class="is-steps" title="Written steps"><i data-lucide="notebook-text"></i><span class="lib-steps-label">Steps</span></span>@endif
                @if($counts['video'])<span class="is-video" title="{{ $counts['video'] }} {{ \Illuminate\Support\Str::plural('video', $counts['video']) }}"><i data-lucide="square-play"></i>{{ $counts['video'] }}</span>@endif
                @if($counts['document'])<span class="is-document" title="{{ $counts['document'] }} {{ \Illuminate\Support\Str::plural('document', $counts['document']) }}"><i data-lucide="file-text"></i>{{ $counts['document'] }}</span>@endif
                @if($counts['link'])<span class="is-link" title="{{ $counts['link'] }} {{ \Illuminate\Support\Str::plural('link', $counts['link']) }}"><i data-lucide="link"></i>{{ $counts['link'] }}</span>@endif
            </div>
            <span class="lib-card-date" title="Updated {{ $article->updated_at->format('d M Y, h:i A') }}">{{ $article->updated_at->format('d M Y') }}</span>
        </div>
    </div>
</article>
