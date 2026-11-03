<x-app-layout>
    <x-slot name="title">{{ $article->title }}</x-slot>
    <x-slot name="pageTitle">{{ $article->title }}</x-slot>
    <x-slot name="breadcrumb">Knowledge / Library / {{ str_replace(' / ', ' - ', $article->category?->name ?? 'Guide') }}</x-slot>
    @push('head')@vite('resources/css/library.css')@endpush
    @php
        $category = $article->category;
        $tone = $category?->color ?: 'primary';
        $status = $article->statusMeta();
        $firstVideo = $videos->first();
        $tags = $article->tagList();
        $hasSteps = $article->hasWrittenSteps();
        // A guide made only of documents / links shows them in the main column instead of the side.
        $attachmentsInMain = ! $hasSteps && $videos->isEmpty();
        $author = $article->creator ?? $article->editor;
        $sideEmpty = preg_match_all('#<h[1-3][\s>]#i', (string) $article->body) < 2
            && ($attachmentsInMain || ($documents->isEmpty() && $links->isEmpty())) && ! $tags && $related->isEmpty();
    @endphp

    <nav class="lib-crumbs" aria-label="Library">
        <a href="{{ route('library.index') }}"><i data-lucide="arrow-left"></i>Knowledge library</a>
        @if($category)<span class="sep">/</span><a href="{{ route('library.index', ['category' => $category->slug]) }}">{{ $category->name }}</a>@endif
    </nav>

    <section class="nx-card lib-head">
        <div class="lib-head-main">
            <div class="lib-head-pills">
                @if($category)<x-pill :tone="$tone" :label="$category->name" :icon="$category->icon" />@endif
                @if($article->device_type)<a class="nx-tag" href="{{ route('library.index', ['device_type' => $article->device_type]) }}" title="More guides for this device type"><i data-lucide="air-vent"></i>{{ $article->deviceTypeLabel() }}</a>@endif
                @if($article->manufacturer)<a class="nx-tag" href="{{ route('library.index', ['manufacturer' => $article->manufacturer]) }}" title="More guides for this manufacturer"><i data-lucide="factory"></i>{{ $article->manufacturerLabel() }}</a>@endif
                @if($article->model_numbers)<span class="nx-tag notranslate" title="Model numbers"><i data-lucide="hash"></i>{{ $article->model_numbers }}</span>@endif
                @if($canEdit)<x-pill :tone="$status['tone']" :label="$status['label']" />@endif
                @if($article->is_featured)<span class="badge lib-badge-featured"><i data-lucide="star"></i>Featured</span>@endif
            </div>
            <h2>{{ $article->title }}</h2>
            @if($article->summary)<p class="lib-head-summary">{{ $article->summary }}</p>@endif
            <div class="lib-head-meta">
                @if($author)<span><span class="nx-avatar notranslate" aria-hidden="true">{{ strtoupper(mb_substr($author->name, 0, 1)) }}</span><span>By <span class="notranslate">{{ $author->name }}</span></span></span>@endif
                <span title="{{ $article->updated_at->format('d M Y, h:i A') }}"><i data-lucide="calendar-clock"></i>Updated {{ $article->updated_at->format('d M Y') }}@if($article->editor && $article->editor->isNot($author)) by <span class="notranslate">{{ $article->editor->name }}</span>@endif</span>
                <span><i data-lucide="eye"></i>{{ number_format($article->views_count) }} {{ \Illuminate\Support\Str::plural('view', $article->views_count) }}</span>
                @if($videos->isNotEmpty())<a href="#libVideos"><i data-lucide="square-play"></i>{{ $videos->count() }} {{ \Illuminate\Support\Str::plural('video', $videos->count()) }}</a>@endif
                @if($documents->isNotEmpty())<a href="#libDocuments"><i data-lucide="file-text"></i>{{ $documents->count() }} {{ \Illuminate\Support\Str::plural('document', $documents->count()) }}</a>@endif
                @if($links->isNotEmpty())<a href="#libLinks"><i data-lucide="link"></i>{{ $links->count() }} {{ \Illuminate\Support\Str::plural('link', $links->count()) }}</a>@endif
            </div>
        </div>
        <div class="lib-head-actions">
            <button class="btn btn-light border" type="button" data-lib-print hidden><i data-lucide="printer"></i>Print</button>
            @if($canUpdate)<a class="btn btn-light border" href="{{ route('library.edit', $article) }}"><i data-lucide="pencil"></i>Edit</a>@endif
            @if($canDelete)
                <form method="POST" action="{{ route('library.destroy', $article) }}" data-confirm="Delete “{{ $article->title }}”? It will be removed from the library for everyone.">
                    @csrf @method('DELETE')
                    <button class="btn btn-outline-danger" type="submit"><i data-lucide="trash-2"></i>Delete</button>
                </form>
            @endif
        </div>
    </section>

    <div class="lib-layout {{ $sideEmpty ? 'is-single' : '' }}">
        <div class="lib-main">
            @if($firstVideo)
                <section class="nx-card overflow-hidden lib-anchor" id="libVideos" data-lib-player aria-label="Videos">
                    <div class="lib-player">
                        <iframe src="{{ $firstVideo->embedUrl() }}" title="{{ $firstVideo->displayTitle() }}" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin" data-lib-frame></iframe>
                    </div>
                    <div class="lib-player-bar">
                        <strong data-lib-now>{{ $firstVideo->displayTitle() }}</strong>
                        <a href="{{ $firstVideo->watchUrl() }}" target="_blank" rel="noopener noreferrer" data-lib-watch><i data-lucide="external-link"></i>Watch on YouTube</a>
                    </div>
                    @if($videos->count() > 1)
                        <div class="lib-playlist" role="group" aria-label="Videos in this guide">
                            @foreach($videos as $index => $video)
                                <a class="{{ $index === 0 ? 'is-active' : '' }}" href="{{ $video->watchUrl() }}" target="_blank" rel="noopener noreferrer" data-lib-video="{{ $video->embedUrl() }}" data-lib-watch-url="{{ $video->watchUrl() }}" data-lib-title="{{ $video->displayTitle() }}" @if($index === 0) aria-current="true" @endif>
                                    <span class="lib-thumb"><img src="{{ $video->thumbnailUrl() }}" alt="" loading="lazy" referrerpolicy="no-referrer"><i data-lucide="play"></i></span>
                                    <span class="lib-thumb-title">{{ $video->displayTitle() }}</span>
                                </a>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            @if($hasSteps)
                <section class="nx-card">
                    <div class="nx-card-header lib-steps-head"><h3 class="nx-card-title"><i data-lucide="list-checks"></i>Step-by-step guide</h3></div>
                    <div class="nx-prose lib-steps-body" data-lib-prose>{!! $article->body !!}</div>
                </section>
            @elseif($attachmentsInMain && $documents->isEmpty() && $links->isEmpty())
                <section class="nx-card">
                    <div class="lib-empty-steps"><i data-lucide="file-question"></i><div>This guide has no content yet.</div></div>
                </section>
            @endif

            @if($attachmentsInMain)
                @include('library.partials.attachments')
            @endif
        </div>

        <aside class="lib-side" aria-label="Guide details">
            <section class="nx-card" data-lib-toc-card hidden>
                <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="list-tree"></i>On this page</h3></div>
                <ol class="lib-toc" data-lib-toc></ol>
            </section>

            @unless($attachmentsInMain)
                @include('library.partials.attachments')
            @endunless

            @if($tags)
                <section class="nx-card">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="tags"></i>Tags</h3></div>
                    <div class="lib-tag-cloud">
                        @foreach($tags as $tag)<a href="{{ route('library.index', ['q' => $tag]) }}">#{{ $tag }}</a>@endforeach
                    </div>
                </section>
            @endif

            @if($related->isNotEmpty())
                <section class="nx-card">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="book-marked"></i>Related guides</h3></div>
                    <ul class="lib-related">
                        @foreach($related as $item)
                            @php $cover = $item->coverUrl(); @endphp
                            <li>
                                <a href="{{ route('library.show', $item) }}">
                                    <span class="lib-related-thumb tone-{{ $item->category?->color ?: 'primary' }}">@if($cover)<img src="{{ $cover }}" alt="" loading="lazy" referrerpolicy="no-referrer">@else<i data-lucide="{{ $item->category?->icon ?: 'book-open' }}"></i>@endif</span>
                                    <span class="min-w-0"><strong>{{ $item->title }}</strong><small>{{ $item->category?->name }}@if($canEdit && ! $item->isPublished()) · Draft @endif</small></span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </section>
            @endif
        </aside>
    </div>

    @push('scripts')@vite('resources/js/library.js')@endpush
</x-app-layout>
