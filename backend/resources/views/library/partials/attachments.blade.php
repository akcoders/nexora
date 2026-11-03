@php
    $fileTone = fn (?string $extension) => match ($extension) {
        'pdf' => 'is-pdf',
        'xls', 'xlsx', 'csv' => 'is-sheet',
        'ppt', 'pptx' => 'is-slides',
        'jpg', 'jpeg', 'png', 'webp' => 'is-image',
        default => '',
    };
@endphp
@if($documents->isNotEmpty())
    <section class="nx-card lib-anchor" id="libDocuments">
        <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="file-text"></i>Documents<span class="nx-count notranslate">{{ $documents->count() }}</span></h3></div>
        <ul class="lib-files">
            @foreach($documents as $document)
                @php $fileUrl = route('library.files.show', [$article, $document]); $inline = $document->opensInline(); @endphp
                <li class="lib-file">
                    <span class="lib-file-icon {{ $fileTone($document->extension()) }}"><i data-lucide="{{ $document->fileIcon() }}"></i></span>
                    <div class="lib-file-text">
                        <a class="lib-file-name" href="{{ $fileUrl }}" @if($inline) target="_blank" rel="noopener" @endif title="{{ $document->displayTitle() }}">{{ $document->displayTitle() }}</a>
                        <small class="notranslate">{{ collect([$document->extension() ? strtoupper($document->extension()) : null, $document->readableSize(), $document->title && $document->file_name !== $document->title ? $document->file_name : null])->filter()->implode(' · ') }}</small>
                    </div>
                    <a class="btn btn-sm btn-light border" href="{{ $fileUrl }}" @if($inline) target="_blank" rel="noopener" @endif aria-label="{{ $inline ? 'Open' : 'Download' }} {{ $document->displayTitle() }}">
                        <i data-lucide="{{ $inline ? 'external-link' : 'download' }}"></i>{{ $inline ? 'Open' : 'Download' }}
                    </a>
                </li>
            @endforeach
        </ul>
    </section>
@endif

@if($links->isNotEmpty())
    <section class="nx-card lib-anchor" id="libLinks">
        <div class="nx-card-header"><h3 class="nx-card-title"><i data-lucide="link"></i>Useful links<span class="nx-count notranslate">{{ $links->count() }}</span></h3></div>
        <ul class="lib-files">
            @foreach($links as $link)
                @php $host = preg_replace('/^www\./i', '', (string) parse_url((string) $link->url, PHP_URL_HOST)); @endphp
                <li class="lib-file">
                    <span class="lib-file-icon is-link"><i data-lucide="globe"></i></span>
                    <div class="lib-file-text">
                        <a class="lib-file-name" href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" title="{{ $link->displayTitle() }}">{{ $link->displayTitle() }}</a>
                        <small class="notranslate">{{ $host ?: $link->url }}</small>
                    </div>
                    <a class="btn btn-sm btn-light border" href="{{ $link->url }}" target="_blank" rel="noopener noreferrer" aria-label="Open {{ $link->displayTitle() }} in a new tab"><i data-lucide="arrow-up-right"></i>Open</a>
                </li>
            @endforeach
        </ul>
    </section>
@endif
