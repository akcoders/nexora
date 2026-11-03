@php
    /**
     * One row of the editor's "Videos, documents & links" builder. Also rendered inside
     * <template> tags (index "__INDEX__") so new rows use the same markup.
     *
     * @var string|int $index
     * @var array{id?: mixed, kind: string, title?: mixed, url?: mixed} $row
     * @var \App\Models\LibraryResource|null $resource  the saved resource for rows with an id
     * @var bool $fileLost  a new document row coming back after a validation error
     */
    $kind = $row['kind'];
    $meta = [
        'video' => ['label' => 'YouTube video', 'icon' => 'square-play'],
        'document' => ['label' => 'Document', 'icon' => 'file-text'],
        'link' => ['label' => 'Web link', 'icon' => 'link'],
    ][$kind] ?? ['label' => 'Resource', 'icon' => 'paperclip'];
    $name = fn (string $field) => "resources[{$index}][{$field}]";
    $id = fn (string $field) => "res-{$index}-{$field}";
    $error = fn (string $field) => is_numeric($index) ? $errors->first("resources.{$index}.{$field}") : '';
    $hasError = collect(['id', 'kind', 'title', 'url', 'file'])->contains(fn ($field) => $error($field) !== '');
    $videoId = $kind === 'video' ? \App\Services\LibraryOptions::youtubeId((string) ($row['url'] ?? '')) : null;
    $resource ??= null;
    $fileLost ??= false;
@endphp
<div class="lib-res-row {{ $hasError ? 'has-error' : '' }}" data-kind="{{ $kind }}" data-res-row>
    <input type="hidden" name="{{ $name('id') }}" value="{{ $row['id'] ?? '' }}" data-res-name="id">
    @if($error('id'))<div class="invalid-feedback d-block px-3 pt-2">{{ $error('id') }}</div>@endif
    <input type="hidden" name="{{ $name('kind') }}" value="{{ $kind }}" data-res-name="kind">
    <div class="lib-res-head">
        <span class="lib-res-kind"><span><i data-lucide="{{ $meta['icon'] }}"></i></span>{{ $meta['label'] }}</span>
        <div class="lib-res-tools">
            <button class="btn" type="button" data-res-move="up" title="Move up" aria-label="Move up"><i data-lucide="arrow-up"></i></button>
            <button class="btn" type="button" data-res-move="down" title="Move down" aria-label="Move down"><i data-lucide="arrow-down"></i></button>
            <button class="btn" type="button" data-res-remove title="Remove" aria-label="Remove this {{ strtolower($meta['label']) }}"><i data-lucide="trash-2"></i></button>
        </div>
    </div>
    <div class="lib-res-body {{ $kind === 'video' ? 'has-thumb' : '' }}">
        @if($kind === 'video')
            <div class="lib-res-thumb" data-res-thumb>
                @if($videoId)
                    <img src="{{ \App\Services\LibraryOptions::youtubeThumbnail($videoId) }}" alt="" referrerpolicy="no-referrer"><span class="lib-res-thumb-play"><i data-lucide="play"></i></span>
                @else
                    <i data-lucide="square-play"></i>
                @endif
            </div>
        @endif
        <div class="lib-res-fields">
            @if($kind === 'video' || $kind === 'link')
                <div>
                    <label class="form-label" for="{{ $id('url') }}">{{ $kind === 'video' ? 'YouTube link' : 'Web address' }} *</label>
                    <input class="form-control @if($error('url')) is-invalid @endif" type="url" id="{{ $id('url') }}" name="{{ $name('url') }}" value="{{ $row['url'] ?? '' }}" maxlength="2048" required inputmode="url"
                        placeholder="{{ $kind === 'video' ? 'https://www.youtube.com/watch?v=…' : 'https://…' }}" @if($kind === 'video') data-res-youtube @endif data-res-name="url">
                    @if($error('url'))<div class="invalid-feedback d-block">{{ $error('url') }}</div>@endif
                </div>
                <div>
                    <label class="form-label" for="{{ $id('title') }}">Title <span class="text-secondary fw-normal">(optional)</span></label>
                    <input class="form-control @if($error('title')) is-invalid @endif" type="text" id="{{ $id('title') }}" name="{{ $name('title') }}" value="{{ $row['title'] ?? '' }}" maxlength="180" placeholder="{{ $kind === 'video' ? 'e.g. Indoor unit cleaning demo' : 'e.g. Daikin error code list' }}" data-res-name="title">
                    @if($error('title'))<div class="invalid-feedback d-block">{{ $error('title') }}</div>@endif
                </div>
            @else
                @if($resource?->file_path)
                    <div class="lib-res-file" data-res-current>
                        <span class="lib-file-icon"><i data-lucide="{{ $resource->fileIcon() }}"></i></span>
                        <div class="min-w-0 flex-grow-1"><strong class="notranslate">{{ $resource->file_name }}</strong><small class="notranslate">{{ collect([$resource->extension() ? strtoupper($resource->extension()) : null, $resource->readableSize()])->filter()->implode(' · ') }}</small></div>
                        <button class="btn btn-sm btn-light border" type="button" data-res-replace hidden><i data-lucide="refresh-cw"></i>Replace</button>
                    </div>
                @endif
                <div>
                    <label class="form-label" for="{{ $id('title') }}">Title <span class="text-secondary fw-normal">(optional)</span></label>
                    <input class="form-control @if($error('title')) is-invalid @endif" type="text" id="{{ $id('title') }}" name="{{ $name('title') }}" value="{{ $row['title'] ?? '' }}" maxlength="180" placeholder="e.g. Installation manual" data-res-name="title">
                    @if($error('title'))<div class="invalid-feedback d-block">{{ $error('title') }}</div>@endif
                </div>
                <div data-res-file-field @if($resource?->file_path && ! $error('file')) data-res-collapsible @endif>
                    <label class="form-label" for="{{ $id('file') }}">{{ $resource?->file_path ? 'Replace with a new file' : 'File *' }}</label>
                    <input class="form-control @if($error('file')) is-invalid @endif" type="file" id="{{ $id('file') }}" name="{{ $name('file') }}" @unless($resource?->file_path) required @endunless
                        accept=".{{ implode(',.', \App\Services\LibraryOptions::DOCUMENT_EXTENSIONS) }}" data-max-size="{{ \App\Services\LibraryOptions::MAX_DOCUMENT_KB }}" data-res-name="file">
                    @if($error('file'))<div class="invalid-feedback d-block">{{ $error('file') }}</div>@endif
                </div>
                @if($fileLost && ! $error('file'))<div class="lib-res-note"><i data-lucide="info" style="width:13px;height:13px"></i> Files are not kept when the form has errors — choose the file again.</div>@endif
            @endif
        </div>
    </div>
</div>
