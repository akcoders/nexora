<x-app-layout>
    <x-slot name="title">Manage guides</x-slot>
    <x-slot name="pageTitle">Manage guides</x-slot>
    <x-slot name="breadcrumb">Knowledge / Manage guides</x-slot>
    @push('head')@vite('resources/css/library.css')@endpush
    @php
        $pills = ['all' => 'All guides', 'published' => 'Published', 'draft' => 'Drafts', 'featured' => 'Featured'];
        $canUpdate = auth()->user()->can('library.update');
        $canDelete = auth()->user()->can('library.delete');
    @endphp

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Knowledge library</div>
            <h2>Manage guides</h2>
            <p>Every guide including drafts. Search, export, edit or remove guides; readers only see published ones.</p>
        </div>
        <div class="nx-page-actions">
            <a class="btn btn-light border" href="{{ route('library.index') }}"><i data-lucide="book-open"></i>Open library</a>
            @can('library.manage')<a class="btn btn-light border" href="{{ route('library.settings') }}"><i data-lucide="settings-2"></i>Settings</a>@endcan
            @can('library.create')<a class="btn btn-primary" href="{{ route('library.create') }}"><i data-lucide="plus"></i>Add guide</a>@endcan
        </div>
    </div>

    <div class="row g-3 mb-3">
        <div class="col-6 col-xl-3"><x-kpi label="Published guides" :value="$counts['published']" icon="book-open-check" tone="success" :href="route('library.manage', ['status' => 'published'])" :active="$status === 'published'" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Drafts" :value="$counts['draft']" icon="pencil-line" tone="secondary" :href="route('library.manage', ['status' => 'draft'])" :active="$status === 'draft'" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Featured" :value="$counts['featured']" icon="star" tone="warning" :href="route('library.manage', ['status' => 'featured'])" :active="$status === 'featured'" /></div>
        <div class="col-6 col-xl-3"><x-kpi label="Total views" :value="number_format($views)" icon="eye" tone="purple" /></div>
    </div>

    <section class="nx-card nx-datatable-card">
        <div class="nx-datatable-filters">
            <nav class="nx-filter-pills" aria-label="Status">
                @foreach($pills as $key => $label)
                    <a class="{{ $status === $key ? 'active' : '' }}" href="{{ route('library.manage', $key === 'all' ? [] : ['status' => $key]) }}">{{ $label }}<span class="nx-count notranslate">{{ $counts[$key] }}</span></a>
                @endforeach
            </nav>
        </div>
        @if($articles->count() < ($status === 'all' ? $counts['all'] : $counts[$status]))
            <div class="alert alert-info d-flex align-items-center gap-2 mx-3 mt-3 mb-0"><i data-lucide="info"></i><span>Showing the {{ number_format($articles->count()) }} most recently updated guides. Use the library search to find older ones.</span></div>
        @endif
        <table class="table nx-table" data-datatable data-dt-title="Knowledge library guides" data-dt-order='[]' data-dt-page-length="25" data-dt-empty="{{ $status === 'all' ? 'No guides yet. Add the first one.' : 'No guides in this list.' }}">
            <thead>
                <tr>
                    <th data-priority="1">Guide</th>
                    <th>Category</th>
                    <th>Applies to</th>
                    <th>Content</th>
                    <th data-priority="3">Status</th>
                    <th>Views</th>
                    <th>Updated</th>
                    <th class="no-export no-sort text-end" data-orderable="false" data-priority="2"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($articles as $article)
                    @php $kindCounts = $article->resourceCounts(); $statusMeta = $article->statusMeta(); @endphp
                    <tr>
                        <td class="lib-manage-title" data-search="{{ $article->title }} {{ $article->summary }} {{ $article->tags }} {{ $article->is_featured ? 'featured' : '' }}">
                            <a class="nx-row-title" href="{{ route('library.show', $article) }}">{{ $article->title }}@if($article->is_featured)<i data-lucide="star" class="lib-star" role="img" aria-label="Featured"></i>@endif</a>
                            @if($article->summary || $article->body_text)<span class="nx-row-sub">{{ $article->excerpt(110) }}</span>@endif
                        </td>
                        <td>@if($article->category)<x-pill :tone="$article->category->color" :label="$article->category->name" :dot="false" />@else — @endif</td>
                        <td data-search="{{ $article->deviceTypeLabel() }} {{ $article->manufacturerLabel() }} {{ $article->model_numbers }}">
                            @if($article->device_type || $article->manufacturer)
                                <span class="d-block">{{ $article->deviceTypeLabel() ?? 'Any device' }}</span><span class="nx-row-sub">{{ $article->manufacturerLabel() ?? 'Any brand' }}</span>
                            @else
                                <span class="text-secondary">General</span>
                            @endif
                        </td>
                        <td data-order="{{ array_sum($kindCounts) + ($article->hasWrittenSteps() ? 1 : 0) }}" data-search="{{ $article->hasWrittenSteps() ? 'steps' : '' }} {{ $kindCounts['video'] ? 'video' : '' }} {{ $kindCounts['document'] ? 'document' : '' }} {{ $kindCounts['link'] ? 'link' : '' }}">
                            <div class="lib-counts">
                                @if($article->hasWrittenSteps())<span class="is-steps" title="Written steps"><i data-lucide="notebook-text"></i><span class="visually-hidden">Steps</span></span>@endif
                                @if($kindCounts['video'])<span class="is-video" title="Videos"><i data-lucide="square-play"></i>{{ $kindCounts['video'] }}</span>@endif
                                @if($kindCounts['document'])<span class="is-document" title="Documents"><i data-lucide="file-text"></i>{{ $kindCounts['document'] }}</span>@endif
                                @if($kindCounts['link'])<span class="is-link" title="Links"><i data-lucide="link"></i>{{ $kindCounts['link'] }}</span>@endif
                            </div>
                        </td>
                        <td data-order="{{ $article->status }}"><x-pill :tone="$statusMeta['tone']" :label="$statusMeta['label']" /></td>
                        <td data-order="{{ $article->views_count }}" class="notranslate">{{ number_format($article->views_count) }}</td>
                        <td class="text-nowrap" data-order="{{ $article->updated_at->timestamp }}"><span class="d-block">{{ $article->updated_at->format('d M Y') }}</span>@if($article->editor)<span class="nx-row-sub notranslate">{{ $article->editor->name }}</span>@endif</td>
                        <td class="text-end">
                            <div class="d-inline-flex gap-1">
                                <a class="btn btn-sm btn-light border btn-icon" href="{{ route('library.show', $article) }}" title="View" aria-label="View {{ $article->title }}"><i data-lucide="eye"></i></a>
                                @if($canUpdate)<a class="btn btn-sm btn-light border btn-icon" href="{{ route('library.edit', $article) }}" title="Edit" aria-label="Edit {{ $article->title }}"><i data-lucide="pencil"></i></a>@endif
                                @if($canDelete)
                                    <form method="POST" action="{{ route('library.destroy', $article) }}" data-confirm="Delete “{{ $article->title }}”? It will be removed from the library for everyone.">
                                        @csrf @method('DELETE')
                                        <button class="btn btn-sm btn-light border btn-icon text-danger" type="submit" title="Delete" aria-label="Delete {{ $article->title }}"><i data-lucide="trash-2"></i></button>
                                    </form>
                                @endif
                            </div>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </section>
</x-app-layout>
