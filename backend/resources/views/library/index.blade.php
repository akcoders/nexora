@use('App\Http\Controllers\LibraryController')
@use('App\Services\LibraryOptions')
<x-app-layout>
    <x-slot name="title">Knowledge library</x-slot>
    <x-slot name="pageTitle">Knowledge library</x-slot>
    <x-slot name="breadcrumb">Knowledge / Library</x-slot>
    @push('head')@vite('resources/css/library.css')@endpush
    @php
        $current = array_filter([...$filters, 'sort' => $sort === 'latest' ? null : $sort], fn ($value) => $value !== null && $value !== '');
        $url = fn (array $changes = []) => route('library.index', array_filter([...$current, ...$changes], fn ($value) => $value !== null && $value !== ''));
        $contentIcons = ['article' => 'notebook-text', 'video' => 'square-play', 'document' => 'file-text', 'link' => 'link'];
        $contentShort = ['article' => 'Steps', 'video' => 'Videos', 'document' => 'Docs', 'link' => 'Links'];
        $selectFilters = count(array_filter([$filters['category'], $filters['device_type'], $filters['manufacturer']], fn ($value) => $value !== ''));
        $chips = array_filter([
            'q' => $filters['q'] !== '' ? ['Search', '“'.$filters['q'].'”'] : null,
            'category' => $filters['category'] !== '' ? ['Category', $activeCategory?->name ?? $filters['category']] : null,
            'device_type' => $filters['device_type'] !== '' ? ['Device', LibraryOptions::label('library_device_type', $filters['device_type'])] : null,
            'manufacturer' => $filters['manufacturer'] !== '' ? ['Brand', LibraryOptions::label('library_manufacturer', $filters['manufacturer'])] : null,
            'content' => $filters['content'] !== '' ? ['Has', LibraryOptions::CONTENT_FILTERS[$filters['content']]['label']] : null,
        ]);
        $allCount = $categoryCounts->sum();
        $resultsTitle = match (true) {
            $filters['q'] !== '' => 'Search results',
            $activeCategory !== null && count($chips) === 1 => $activeCategory->name,
            $hasFilters => 'Matching guides',
            default => 'All guides',
        };
    @endphp

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Knowledge</div>
            <h2>Knowledge library</h2>
            <p>How-to guides, videos, manuals and checklists for installation, service and repair work.</p>
        </div>
        <div class="nx-page-actions">
            @if($canEdit)<a class="btn btn-light border" href="{{ route('library.manage') }}"><i data-lucide="notebook-pen"></i>Manage guides</a>@endif
            @can('library.manage')<a class="btn btn-light border" href="{{ route('library.settings') }}" title="Library settings"><i data-lucide="settings-2"></i>Settings</a>@endcan
            @can('library.create')<a class="btn btn-primary" href="{{ route('library.create', array_filter(['category' => $activeCategory?->slug])) }}"><i data-lucide="plus"></i>Add guide</a>@endcan
        </div>
    </div>

    <form method="GET" action="{{ route('library.index') }}" data-lib-filters data-v-ignore role="search">
        <section class="lib-hero">
            <label class="lib-hero-label" for="libSearch">What do you need help with?</label>
            <div class="lib-search">
                <div class="lib-search-field">
                    <i data-lucide="search" aria-hidden="true"></i>
                    <input class="form-control" type="search" id="libSearch" name="q" value="{{ $filters['q'] }}" maxlength="120" autocomplete="off" placeholder="Search guides, error codes, model numbers, tags…" data-short-placeholder="Search guides, codes…" data-lib-search>
                    <a class="lib-search-clear" href="{{ $url(['q' => null]) }}" aria-label="Clear search" title="Clear search" data-lib-clear @if($filters['q'] === '') hidden @endif><i data-lucide="x"></i></a>
                </div>
                <button class="btn btn-primary" type="submit"><i data-lucide="search"></i><span>Search</span></button>
            </div>
            <div class="lib-hero-meta">
                <div class="lib-hero-stats">
                    <span><i data-lucide="book-open"></i><strong>{{ number_format($totals['guides']) }}</strong> {{ \Illuminate\Support\Str::plural('guide', $totals['guides']) }}</span>
                    <span><i data-lucide="square-play"></i><strong>{{ number_format($totals['video']) }}</strong> {{ \Illuminate\Support\Str::plural('video', $totals['video']) }}</span>
                    <span><i data-lucide="file-text"></i><strong>{{ number_format($totals['document']) }}</strong> {{ \Illuminate\Support\Str::plural('document', $totals['document']) }}</span>
                    <span><i data-lucide="link"></i><strong>{{ number_format($totals['link']) }}</strong> {{ \Illuminate\Support\Str::plural('link', $totals['link']) }}</span>
                </div>
                @if($popularTags->isNotEmpty())
                    <div class="lib-popular" aria-label="Popular searches"><span>Popular:</span>@foreach($popularTags as $tag)<a href="{{ route('library.index', ['q' => $tag]) }}">{{ $tag }}</a>@endforeach</div>
                @endif
            </div>
        </section>

        @if($categories->isNotEmpty())
            <nav class="lib-cats" aria-label="Categories">
                <a class="lib-cat tone-primary {{ $filters['category'] === '' ? 'is-active' : '' }}" href="{{ $url(['category' => null]) }}" @if($filters['category'] === '') aria-current="page" @endif>
                    <span class="lib-cat-icon"><i data-lucide="layers"></i></span>
                    <span class="lib-cat-text"><strong>All categories</strong><small>{{ $allCount }} {{ \Illuminate\Support\Str::plural('guide', $allCount) }}</small></span>
                </a>
                @foreach($categories as $category)
                    @php $count = (int) ($categoryCounts[$category->id] ?? 0); $isActive = $activeCategory?->is($category); @endphp
                    <a class="lib-cat tone-{{ $category->color }} {{ $isActive ? 'is-active' : '' }} {{ $count === 0 && ! $isActive ? 'is-empty' : '' }}" href="{{ $url(['category' => $isActive ? null : $category->slug]) }}" title="{{ $category->description }}" @if($isActive) aria-current="page" @endif>
                        <span class="lib-cat-icon"><i data-lucide="{{ $category->icon }}"></i></span>
                        <span class="lib-cat-text"><strong>{{ $category->name }}</strong><small>{{ $count }} {{ \Illuminate\Support\Str::plural('guide', $count) }}</small></span>
                    </a>
                @endforeach
            </nav>
        @endif

        <section class="nx-card lib-filterbar" aria-label="Filters" data-lib-filterbar>
            <div class="lib-filterbar-top">
                <div class="nx-segment lib-segment" role="radiogroup" aria-label="Content type">
                    <input type="radio" name="content" value="" id="libContentAll" @checked($filters['content'] === '')><label for="libContentAll"><i data-lucide="layout-grid"></i>All</label>
                    @foreach(LibraryOptions::CONTENT_FILTERS as $key => $content)
                        <input type="radio" name="content" value="{{ $key }}" id="libContent{{ $key }}" @checked($filters['content'] === $key)><label for="libContent{{ $key }}" title="{{ $content['label'] }}"><i data-lucide="{{ $contentIcons[$key] ?? $content['icon'] }}"></i><span class="d-none d-sm-inline">{{ $content['label'] }}</span><span class="d-sm-none">{{ $contentShort[$key] ?? $content['label'] }}</span></label>
                    @endforeach
                </div>
                <label class="lib-sort" for="libSort">Sort by
                    <select class="form-select" id="libSort" name="sort">
                        @foreach(LibraryController::SORTS as $key => $label)<option value="{{ $key }}" @selected($sort === $key)>{{ $label }}</option>@endforeach
                    </select>
                </label>
                <button class="btn btn-light border lib-filter-toggle" type="button" data-lib-filter-toggle aria-expanded="true" aria-controls="libFilterFields" hidden><i data-lucide="sliders-horizontal"></i>Filters @if($selectFilters)<span class="nx-count notranslate">{{ $selectFilters }}</span>@endif</button>
            </div>
            <div class="lib-filterbar-fields" id="libFilterFields">
                <div>
                    <label class="form-label" for="libCategory">Category</label>
                    <select class="form-select" id="libCategory" name="category">
                        <option value="">All categories</option>
                        @foreach($categories as $category)<option value="{{ $category->slug }}" @selected($activeCategory?->is($category))>{{ $category->name }}</option>@endforeach
                        @if($activeCategory && ! $categories->contains($activeCategory))<option value="{{ $activeCategory->slug }}" selected>{{ $activeCategory->name }}</option>@endif
                    </select>
                </div>
                <div>
                    <label class="form-label" for="libDevice">Device type</label>
                    <select class="form-select" id="libDevice" name="device_type">
                        <option value="">All device types</option>
                        @foreach($deviceTypes as $code => $label)<option value="{{ $code }}" @selected($filters['device_type'] === $code)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div>
                    <label class="form-label" for="libManufacturer">Manufacturer</label>
                    <select class="form-select" id="libManufacturer" name="manufacturer">
                        <option value="">All manufacturers</option>
                        @foreach($manufacturers as $code => $label)<option value="{{ $code }}" @selected($filters['manufacturer'] === $code)>{{ $label }}</option>@endforeach
                    </select>
                </div>
                <div class="lib-filterbar-actions">
                    <button class="btn btn-primary" type="submit" data-lib-apply><i data-lucide="filter"></i>Apply</button>
                    <a class="btn btn-light border lib-reset" href="{{ route('library.index') }}" @if(! $hasFilters && $sort === 'latest') aria-disabled="true" tabindex="-1" @endif><i data-lucide="rotate-ccw"></i>Reset</a>
                </div>
            </div>
        </section>
    </form>

    @if($featured->isNotEmpty())
        <section class="lib-featured" aria-labelledby="libFeaturedTitle">
            <div class="lib-section-head"><h3 id="libFeaturedTitle"><i data-lucide="star"></i>Featured guides</h3></div>
            <div class="lib-grid {{ $featured->count() === 1 ? 'is-single' : '' }}">
                @foreach($featured as $article)
                    @include('library.partials.card', ['article' => $article, 'canEdit' => $canEdit, 'wide' => $featured->count() === 1])
                @endforeach
            </div>
        </section>
    @endif

    <section aria-labelledby="libResultsTitle">
        <div class="lib-results-head">
            <h3 class="lib-results-title" id="libResultsTitle">{{ $resultsTitle }}
                <small>
                    @if($articles->total() === 0)
                        No guides
                    @elseif($articles->lastPage() > 1)
                        Showing {{ $articles->firstItem() }}–{{ $articles->lastItem() }} of {{ $articles->total() }} guides
                    @else
                        {{ $articles->total() }} {{ \Illuminate\Support\Str::plural('guide', $articles->total()) }}
                    @endif
                </small>
            </h3>
            @if($chips)
                <div class="lib-chips" aria-label="Active filters">
                    @foreach($chips as $key => [$label, $value])
                        <span class="lib-chip"><small>{{ $label }}:</small><span>{{ $value }}</span><a href="{{ $url([$key => null]) }}" aria-label="Remove {{ strtolower($label) }} filter" title="Remove"><i data-lucide="x"></i></a></span>
                    @endforeach
                    @if(count($chips) > 1)<a class="lib-clear-all" href="{{ route('library.index', array_filter(['sort' => $sort === 'latest' ? null : $sort])) }}">Clear all</a>@endif
                </div>
            @endif
        </div>

        @if($articles->isEmpty())
            <div class="lib-empty">
                @if($hasFilters)
                    <div class="lib-empty-icon"><i data-lucide="search-x"></i></div>
                    <h3>No guides match your search</h3>
                    <p>Try fewer words, check the spelling of a model number, or remove a filter to see more guides.</p>
                    <a class="btn btn-light border" href="{{ route('library.index') }}"><i data-lucide="rotate-ccw"></i>Clear filters</a>
                    @can('library.create')<a class="btn btn-primary" href="{{ route('library.create', array_filter(['category' => $activeCategory?->slug])) }}"><i data-lucide="plus"></i>Add a guide</a>@endcan
                @else
                    <div class="lib-empty-icon"><i data-lucide="book-open"></i></div>
                    <h3>The library is empty</h3>
                    <p>Guides with written steps, YouTube videos, manuals and useful links will appear here for the whole team.</p>
                    @can('library.create')<a class="btn btn-primary" href="{{ route('library.create') }}"><i data-lucide="plus"></i>Add the first guide</a>@endcan
                @endif
            </div>
        @else
            <div class="lib-grid">
                @foreach($articles as $article)
                    @include('library.partials.card', ['article' => $article, 'canEdit' => $canEdit])
                @endforeach
            </div>
            @if($articles->hasPages())
                <div class="lib-pagination">{{ $articles->onEachSide(1)->links('pagination::bootstrap-5') }}</div>
            @endif
        @endif
    </section>

    @push('scripts')@vite('resources/js/library.js')@endpush
</x-app-layout>
