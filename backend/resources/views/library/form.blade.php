@use('App\Services\LibraryOptions')
@use('App\Support\HtmlSanitizer')
<x-app-layout>
    @php
        $isEdit = $article->exists;
        $hasOld = session()->hasOldInput();
        $heading = $isEdit ? 'Edit guide' : 'Add a guide';
        $status = old('status', $article->status ?: LibraryOptions::STATUS_PUBLISHED);
        $featured = (bool) old('is_featured', $article->is_featured);
        $categoryId = (string) old('library_category_id', $article->library_category_id);
        // Old input comes straight from the browser, so it is cleaned again before it goes back into the editor.
        $bodyHtml = $hasOld ? app(HtmlSanitizer::class)->clean((string) old('body')) : (string) $article->body;
        $existing = $isEdit ? $article->resources->keyBy('id') : collect();
        $rows = $hasOld
            ? collect(old('resources', []))->filter(fn ($row) => is_array($row) && array_key_exists((string) ($row['kind'] ?? ''), LibraryOptions::KINDS))
            : $article->resources->values()->map(fn ($resource) => ['id' => $resource->id, 'kind' => $resource->kind, 'title' => $resource->title, 'url' => $resource->kind === 'document' ? null : $resource->url]);
        // Back to the library page the editor came from; the Referer is only trusted on this site.
        $previousUrl = url()->previous();
        $cancelUrl = $isEdit ? route('library.show', $article) : ($previousUrl !== url()->current() && str_starts_with($previousUrl, route('library.index')) ? $previousUrl : route('library.index'));
        $nextIndex = $rows->keys()->filter(fn ($key) => is_numeric($key))->map(fn ($key) => (int) $key)->max() + 1;
        $submitLabel = $status === 'draft' ? 'Save draft' : ($isEdit ? 'Save changes' : 'Publish guide');
    @endphp
    <x-slot name="title">{{ $heading }}</x-slot>
    <x-slot name="pageTitle">{{ $heading }}</x-slot>
    <x-slot name="breadcrumb">Knowledge / Library / {{ $isEdit ? 'Edit' : 'New guide' }}</x-slot>
    @push('head')@vite('resources/css/library.css')@endpush

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Knowledge library</div>
            <h2>{{ $isEdit ? $article->title : 'Add a how-to guide' }}</h2>
            <p>Write the steps, add YouTube videos, manuals and useful links, then tag the guide so technicians can find it.</p>
        </div>
        <div class="nx-page-actions">
            @if($isEdit)<a class="btn btn-light border" href="{{ route('library.show', $article) }}" target="_blank" rel="noopener"><i data-lucide="eye"></i>View guide</a>@endif
        </div>
    </div>

    @if($errors->any())
        <div class="alert alert-danger d-flex gap-2" role="alert">
            <i data-lucide="circle-alert" style="width:18px;flex:0 0 auto;margin-top:1px"></i>
            <div><strong>The guide was not saved.</strong> Please fix the highlighted {{ \Illuminate\Support\Str::plural('field', $errors->count()) }}:
                <ul class="mb-0 mt-1 ps-3" style="list-style:disc">@foreach(collect($errors->all())->unique()->take(6) as $message)<li>{{ $message }}</li>@endforeach</ul>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ $isEdit ? route('library.update', $article) : route('library.store') }}" enctype="multipart/form-data" data-lib-form>
        @csrf
        @if($isEdit) @method('PUT') @endif

        <div class="lib-form-layout">
            <div class="lib-form-main">
                <section class="nx-card">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="notebook-pen"></i>Guide details</h3></div>
                    <div class="nx-card-body d-grid gap-3">
                        <div>
                            <label class="form-label" for="libTitle">Title *</label>
                            <input class="form-control lib-title-input @error('title') is-invalid @enderror" id="libTitle" name="title" value="{{ old('title', $article->title) }}" maxlength="180" required placeholder="e.g. How to clean the indoor unit of a split AC" autocomplete="off">
                            @error('title')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="libSummary">Short summary</label>
                            <textarea class="form-control @error('summary') is-invalid @enderror" id="libSummary" name="summary" rows="2" maxlength="500" placeholder="One or two lines shown on the guide card and in search results.">{{ old('summary', $article->summary) }}</textarea>
                            @error('summary')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>

                <section class="nx-card">
                    <div class="nx-card-header">
                        <div>
                            <h3 class="nx-card-title"><i data-lucide="list-checks"></i>Written steps</h3>
                            <p class="nx-card-subtitle">Use headings for each stage, numbered lists for steps and the checklist for checks.</p>
                        </div>
                    </div>
                    <div class="nx-card-body">
                        <div class="lib-editor @error('body') is-invalid @enderror" data-lib-editor data-upload-url="{{ route('library.uploads.image') }}" data-max-image-kb="{{ LibraryOptions::MAX_IMAGE_KB }}">
                            <div data-lib-editor-mount>{!! $bodyHtml !!}</div>
                            <div class="lib-editor-busy" aria-live="polite"><div>Uploading image…</div></div>
                        </div>
                        <textarea name="body" hidden data-v-skip data-lib-body>{{ $bodyHtml }}</textarea>
                        @error('body')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <noscript><div class="alert alert-warning mt-2 mb-0">Turn on JavaScript to use the editor.</div></noscript>
                        <div class="lib-editor-hint"><i data-lucide="lightbulb"></i><span>Tip: use <strong>Quote</strong> for safety notes and tips, the <strong>image</strong> button for photos (up to {{ LibraryOptions::MAX_IMAGE_KB / 1024 }} MB) and the <strong>video</strong> button to place a YouTube video between steps.</span></div>
                    </div>
                </section>

                <section class="nx-card">
                    <div class="nx-card-header">
                        <div>
                            <h3 class="nx-card-title"><i data-lucide="paperclip"></i>Videos, documents &amp; links</h3>
                            <p class="nx-card-subtitle">Videos play inside the guide. Documents up to {{ LibraryOptions::MAX_DOCUMENT_KB / 1024 }} MB: PDF, Word, Excel, PowerPoint, text, CSV or images.</p>
                        </div>
                    </div>
                    <div class="nx-card-body d-grid gap-3" data-res-builder data-next-index="{{ $nextIndex }}">
                        <div class="lib-add-buttons">
                            <button class="btn btn-light border" type="button" data-res-add="video" style="--tone:#dc2626" aria-label="Add YouTube video"><i data-lucide="square-play"></i><span class="lib-add-long">Add YouTube video</span><span class="lib-add-short" aria-hidden="true">Video</span></button>
                            <button class="btn btn-light border" type="button" data-res-add="document" aria-label="Add document"><i data-lucide="file-up"></i><span class="lib-add-long">Add document</span><span class="lib-add-short" aria-hidden="true">Document</span></button>
                            <button class="btn btn-light border" type="button" data-res-add="link" style="--tone:var(--tone-info)" aria-label="Add web link"><i data-lucide="link"></i><span class="lib-add-long">Add web link</span><span class="lib-add-short" aria-hidden="true">Link</span></button>
                        </div>
                        @error('resources')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        <div class="lib-res-list" data-res-list>@foreach($rows as $index => $row)@include('library.partials.resource-row', ['index' => $index, 'row' => $row, 'resource' => isset($row['id']) && $row['id'] ? $existing->get((int) $row['id']) : null, 'fileLost' => $hasOld && $row['kind'] === 'document' && empty($row['id'])])@endforeach</div>
                        <div class="lib-res-empty"><i data-lucide="clapperboard"></i><span>No videos, documents or links yet. A guide needs written steps or at least one of these.</span></div>
                        @foreach(array_keys(LibraryOptions::KINDS) as $kind)
                            <template data-res-template="{{ $kind }}">@include('library.partials.resource-row', ['index' => '__INDEX__', 'row' => ['kind' => $kind], 'resource' => null, 'fileLost' => false])</template>
                        @endforeach
                    </div>
                </section>
            </div>

            <aside class="lib-form-side">
                <section class="nx-card lib-publish-card">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="send"></i>Publish</h3></div>
                    <div class="nx-card-body d-grid gap-3">
                        <div>
                            <div class="nx-segment lib-publish-status" role="radiogroup" aria-label="Status">
                                <input type="radio" name="status" value="published" id="libStatusPublished" @checked($status === 'published') data-lib-status><label for="libStatusPublished"><i data-lucide="globe"></i>Published</label>
                                <input type="radio" name="status" value="draft" id="libStatusDraft" @checked($status === 'draft') data-lib-status><label for="libStatusDraft"><i data-lucide="pencil-line"></i>Draft</label>
                            </div>
                            <div class="form-text mt-2" data-lib-status-hint>{{ $status === 'draft' ? 'Only people who can edit the library see drafts.' : 'Visible to everyone with library access, on the web and in the mobile app.' }}</div>
                            @error('status')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <label class="lib-switch" for="libFeatured">
                            <span><strong><i data-lucide="star"></i>Featured guide</strong><small>Pinned to the top of the library home.</small></span>
                            <span class="form-check form-switch m-0"><input type="hidden" name="is_featured" value="0"><input class="form-check-input" type="checkbox" role="switch" id="libFeatured" name="is_featured" value="1" @checked($featured)></span>
                        </label>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" type="submit" data-lib-submit><i data-lucide="save"></i><span data-lib-submit-label>{{ $submitLabel }}</span></button>
                            <a class="btn btn-light border" href="{{ $cancelUrl }}">Cancel</a>
                        </div>
                        @if($isEdit)
                            <ul class="lib-publish-meta">
                                <li><span>Created</span><strong>{{ $article->created_at?->format('d M Y') }}@if($article->creator) · <span class="notranslate">{{ $article->creator->name }}</span>@endif</strong></li>
                                <li><span>Last updated</span><strong>{{ $article->updated_at?->format('d M Y, h:i A') }}</strong></li>
                                <li><span>Views</span><strong>{{ number_format($article->views_count) }}</strong></li>
                            </ul>
                        @endif
                    </div>
                </section>

                <section class="nx-card lib-class-card">
                    <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="tags"></i>Classification</h3></div>
                    <div class="nx-card-body d-grid gap-3">
                        <div>
                            <label class="form-label" for="libCategory">Category *</label>
                            <select class="form-select @error('library_category_id') is-invalid @enderror" id="libCategory" name="library_category_id" required>
                                <option value="">Choose a category</option>
                                @foreach($categories as $category)<option value="{{ $category->id }}" @selected($categoryId === (string) $category->id)>{{ $category->name }}{{ $category->is_active ? '' : ' (hidden)' }}</option>@endforeach
                            </select>
                            @error('library_category_id')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="libDeviceType">Device type</label>
                            <select class="form-select @error('device_type') is-invalid @enderror" id="libDeviceType" name="device_type">
                                <option value="">Any / not specific</option>
                                @foreach($deviceTypes as $code => $label)<option value="{{ $code }}" @selected(old('device_type', $article->device_type) === $code)>{{ $label }}</option>@endforeach
                            </select>
                            @error('device_type')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="libManufacturer">Manufacturer</label>
                            <select class="form-select @error('manufacturer') is-invalid @enderror" id="libManufacturer" name="manufacturer">
                                <option value="">Any / not specific</option>
                                @foreach($manufacturers as $code => $label)<option value="{{ $code }}" @selected(old('manufacturer', $article->manufacturer) === $code)>{{ $label }}</option>@endforeach
                            </select>
                            @error('manufacturer')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="libModels">Model numbers</label>
                            <input class="form-control @error('model_numbers') is-invalid @enderror" id="libModels" name="model_numbers" value="{{ old('model_numbers', $article->model_numbers) }}" maxlength="255" placeholder="e.g. FTKF50, RKF50" autocomplete="off">
                            @error('model_numbers')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                        <div>
                            <label class="form-label" for="libTags">Tags</label>
                            <input class="form-control @error('tags') is-invalid @enderror" id="libTags" name="tags" value="{{ old('tags', $article->tags) }}" maxlength="255" placeholder="e.g. gas leak, E4, outdoor unit" autocomplete="off">
                            <div class="form-text">Separate tags with commas. Technicians can search by these words.</div>
                            @error('tags')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                        </div>
                    </div>
                </section>
            </aside>
        </div>

        {{-- Shown by library-editor.js while the Publish card's Save button is out of view. --}}
        <div class="lib-savebar" data-lib-savebar hidden>
            <span class="lib-savebar-state" data-lib-savebar-state>{{ $hasOld ? 'Unsaved changes' : ($isEdit ? 'Editing guide' : 'New guide') }}</span>
            <a class="btn btn-light border" href="{{ $cancelUrl }}">Cancel</a>
            <button class="btn btn-primary" type="submit"><i data-lucide="save"></i><span data-lib-submit-label>{{ $submitLabel }}</span></button>
        </div>
    </form>

    @push('scripts')@vite('resources/js/library-editor.js')@endpush
</x-app-layout>
