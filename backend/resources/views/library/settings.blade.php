<x-app-layout>
    <x-slot name="title">Library settings</x-slot>
    <x-slot name="pageTitle">Library settings</x-slot>
    <x-slot name="breadcrumb">Knowledge / Library settings</x-slot>
    @push('head')@vite('resources/css/library.css')@endpush
    @php
        $oldModal = old('_modal');
        $tab = match ($oldModal) {
            'categoryModal' => 'categories',
            'masterModal' => array_key_exists((string) old('type'), $groups) ? old('type') : $tab,
            'masterRenameModal' => array_key_exists((string) old('tab'), $tabs) ? old('tab') : $tab,
            default => $tab,
        };
        $editingCategory = $oldModal === 'categoryModal' && old('category_id') ? $categories->firstWhere('id', (int) old('category_id')) : null;
        $renamingOption = $oldModal === 'masterRenameModal' && old('option_id') ? $masters->flatten()->firstWhere('id', (int) old('option_id')) : null;
        $tabIcons = ['categories' => 'layers', 'library_device_type' => 'air-vent', 'library_manufacturer' => 'factory'];
        $defaultIcon = old('icon', $editingCategory?->icon ?? 'book-open');
        $defaultColor = old('color', $editingCategory?->color ?? 'primary');
    @endphp

    <div class="nx-page-header">
        <div>
            <div class="nx-page-kicker">Knowledge library</div>
            <h2>Library settings</h2>
            <p>Categories group guides on the library home. Device types and manufacturers are the filters technicians use to find the right guide.</p>
        </div>
        <div class="nx-page-actions">
            <a class="btn btn-light border" href="{{ route('library.index') }}"><i data-lucide="book-open"></i>Open library</a>
            <a class="btn btn-light border" href="{{ route('library.manage') }}"><i data-lucide="notebook-pen"></i>Manage guides</a>
        </div>
    </div>

    <section class="nx-card nx-datatable-card" data-lib-settings>
        <div class="nx-card-tabs">
            <ul class="nav nav-tabs nx-tabs-scroll border-0" role="tablist">
                @foreach($tabs as $key => $label)
                    @php $count = $key === 'categories' ? $categories->count() : ($masters[$key] ?? collect())->count(); @endphp
                    <li class="nav-item" role="presentation"><button class="nav-link {{ $tab === $key ? 'active' : '' }}" data-bs-toggle="tab" data-bs-target="#lib-tab-{{ $key }}" data-lib-tab="{{ $key }}" type="button" role="tab" aria-controls="lib-tab-{{ $key }}" aria-selected="{{ $tab === $key ? 'true' : 'false' }}"><i data-lucide="{{ $tabIcons[$key] ?? 'list' }}"></i>{{ $label }}<span class="nx-count notranslate">{{ $count }}</span></button></li>
                @endforeach
            </ul>
        </div>
        <div class="tab-content">
            <div class="tab-pane fade {{ $tab === 'categories' ? 'show active' : '' }}" id="lib-tab-categories" role="tabpanel">
                <div class="nx-datatable-filters justify-content-between align-items-center">
                    <div class="small text-secondary">Hidden categories disappear from the library home and filters; their guides stay readable.</div>
                    <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#categoryModal" data-lib-category-add style="min-height:34px"><i data-lucide="plus"></i>Add category</button>
                </div>
                <table class="table nx-table" data-datatable data-dt-title="Library categories" data-dt-order='[[3,"asc"]]' data-dt-page-length="50" data-dt-empty="No categories yet.">
                    <thead><tr><th data-priority="1">Category</th><th>Slug</th><th>Guides</th><th>Order</th><th data-priority="3">Status</th><th class="no-export no-sort text-end" data-orderable="false" data-priority="2"></th></tr></thead>
                    <tbody>
                        @foreach($categories as $category)
                            <tr>
                                <td data-search="{{ $category->name }} {{ $category->description }}">
                                    <div class="lib-cat-cell">
                                        <span class="lib-cat-icon tone-{{ $category->color }}"><i data-lucide="{{ $category->icon }}"></i></span>
                                        <div class="min-w-0"><strong>{{ $category->name }}</strong>@if($category->description)<small>{{ $category->description }}</small>@endif</div>
                                    </div>
                                </td>
                                <td class="nx-code notranslate">{{ $category->slug }}</td>
                                <td data-order="{{ $category->articles_count }}"><a href="{{ route('library.index', ['category' => $category->slug]) }}">{{ $category->articles_count }} {{ \Illuminate\Support\Str::plural('guide', $category->articles_count) }}</a></td>
                                <td data-order="{{ $category->sort_order }}" class="notranslate">{{ $category->sort_order }}</td>
                                <td><x-pill :tone="$category->is_active ? 'success' : 'secondary'" :label="$category->is_active ? 'Active' : 'Hidden'" /></td>
                                <td class="text-end">
                                    <div class="d-inline-flex gap-1">
                                        <button class="btn btn-sm btn-light border lib-row-btn" type="button" data-bs-toggle="modal" data-bs-target="#categoryModal" title="Edit category" aria-label="Edit {{ $category->name }}"
                                            data-lib-category-edit="{{ json_encode(['id' => $category->id, 'name' => $category->name, 'description' => $category->description, 'icon' => $category->icon, 'color' => $category->color, 'sort_order' => $category->sort_order, 'action' => route('library.categories.update', $category), 'count' => $category->articles_count]) }}"><i data-lucide="pencil"></i><span>Edit</span></button>
                                        <form method="POST" action="{{ route('library.categories.toggle', $category) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-light border lib-row-btn" type="submit" title="{{ $category->is_active ? 'Hide from the library' : 'Show in the library' }}" aria-label="{{ $category->is_active ? 'Hide' : 'Show' }} {{ $category->name }}"><i data-lucide="{{ $category->is_active ? 'eye-off' : 'eye' }}"></i><span>{{ $category->is_active ? 'Hide' : 'Show' }}</span></button></form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @foreach($groups as $key => $group)
                <div class="tab-pane fade {{ $tab === $key ? 'show active' : '' }}" id="lib-tab-{{ $key }}" role="tabpanel">
                    <div class="nx-datatable-filters justify-content-between align-items-center">
                        <div class="small text-secondary">{{ $group['hint'] }}. Inactive entries stay on existing guides but are no longer offered.</div>
                        <button class="btn btn-primary btn-sm" type="button" data-bs-toggle="modal" data-bs-target="#masterModal" data-lib-master-add="{{ $key }}" style="min-height:34px"><i data-lucide="plus"></i>Add {{ $key === 'library_device_type' ? 'device type' : 'manufacturer' }}</button>
                    </div>
                    <table class="table nx-table" data-datatable data-dt-title="{{ $group['label'] }}" data-dt-page-length="50" data-dt-empty="No entries yet.">
                        <thead><tr><th data-priority="1">Name</th><th>Code</th><th>Guides</th><th data-priority="3">Status</th><th class="no-export no-sort text-end" data-orderable="false" data-priority="2"></th></tr></thead>
                        <tbody>
                            @foreach($masters[$key] ?? [] as $option)
                                @php $used = (int) ($usage[$key][$option->code] ?? 0); @endphp
                                <tr>
                                    <td class="fw-semibold">{{ $option->label }}</td>
                                    <td class="nx-code notranslate">{{ $option->code }}</td>
                                    <td data-order="{{ $used }}">@if($used)<a href="{{ route('library.index', [$key === 'library_device_type' ? 'device_type' : 'manufacturer' => $option->code]) }}">{{ $used }} {{ \Illuminate\Support\Str::plural('guide', $used) }}</a>@else<span class="text-secondary">—</span>@endif</td>
                                    <td><x-pill :tone="$option->active ? 'success' : 'secondary'" :label="$option->active ? 'Active' : 'Inactive'" /></td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <button class="btn btn-sm btn-light border lib-row-btn" type="button" data-bs-toggle="modal" data-bs-target="#masterRenameModal" title="Rename" aria-label="Rename {{ $option->label }}" data-lib-master-rename="{{ json_encode(['id' => $option->id, 'label' => $option->label, 'tab' => $key, 'action' => route('library.masters.update', $option)]) }}"><i data-lucide="pencil"></i><span>Rename</span></button>
                                            <form method="POST" action="{{ route('library.masters.toggle', $option) }}">@csrf @method('PATCH')<button class="btn btn-sm btn-light border lib-row-btn" type="submit" title="{{ $option->active ? 'Stop offering this entry' : 'Offer this entry again' }}" aria-label="{{ $option->active ? 'Deactivate' : 'Activate' }} {{ $option->label }}"><i data-lucide="{{ $option->active ? 'circle-off' : 'circle-check' }}"></i><span>{{ $option->active ? 'Deactivate' : 'Activate' }}</span></button></form>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endforeach
        </div>
    </section>

    {{-- Add / edit category --}}
    <div class="modal fade" id="categoryModal" tabindex="-1" aria-labelledby="categoryModalTitle" data-lib-category-modal data-store-url="{{ route('library.categories.store') }}">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <form class="modal-content" method="POST" action="{{ $editingCategory ? route('library.categories.update', $editingCategory) : route('library.categories.store') }}">
                @csrf
                <input type="hidden" name="_method" value="PUT" @disabled(! $editingCategory) data-lib-method>
                <input type="hidden" name="category_id" value="{{ $editingCategory?->id }}" data-lib-category-id>
                <div class="modal-header"><h5 class="modal-title" id="categoryModalTitle" data-lib-category-title>{{ $editingCategory ? 'Edit category' : 'Add category' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-7 d-grid gap-3 align-content-start">
                            <div><label class="form-label" for="catName">Name *</label><input class="form-control" id="catName" name="name" value="{{ old('name', $editingCategory?->name) }}" maxlength="120" required data-lib-preview-name placeholder="e.g. Gas charging"></div>
                            <div><label class="form-label" for="catDescription">Description</label><textarea class="form-control" id="catDescription" name="description" rows="2" maxlength="500" placeholder="What kind of guides belong here?">{{ old('description', $editingCategory?->description) }}</textarea></div>
                            <div><label class="form-label" for="catOrder">Sort order</label><input class="form-control" type="number" id="catOrder" name="sort_order" value="{{ old('sort_order', $editingCategory?->sort_order) }}" min="0" max="999" step="1" placeholder="Next in line"><div class="form-text">Lower numbers come first on the library home.</div></div>
                        </div>
                        <div class="col-md-5">
                            <div class="lib-cat-preview">
                                <small class="lib-preview-label">Preview</small>
                                <div class="lib-cat tone-{{ $defaultColor }}" data-lib-preview>
                                    <span class="lib-cat-icon" data-lib-preview-icon><i data-lucide="{{ $defaultIcon }}"></i></span>
                                    <span class="lib-cat-text"><strong data-lib-preview-label>{{ old('name', $editingCategory?->name) ?: 'Category name' }}</strong><small data-lib-preview-count>{{ $editingCategory ? $editingCategory->articles_count.' '.\Illuminate\Support\Str::plural('guide', $editingCategory->articles_count) : '0 guides' }}</small></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block" id="catIconLabel">Icon *</span>
                            <div class="lib-icon-grid" role="radiogroup" aria-labelledby="catIconLabel">
                                @foreach(collect($icons)->push($editingCategory?->icon)->filter()->unique() as $icon)
                                    <label title="{{ \Illuminate\Support\Str::headline($icon) }}"><input type="radio" name="icon" value="{{ $icon }}" @checked($defaultIcon === $icon) data-lib-icon><span><i data-lucide="{{ $icon }}"></i><span class="visually-hidden">{{ \Illuminate\Support\Str::headline($icon) }}</span></span></label>
                                @endforeach
                            </div>
                        </div>
                        <div class="col-12">
                            <span class="form-label d-block" id="catColorLabel">Colour *</span>
                            <div class="lib-swatches" role="radiogroup" aria-labelledby="catColorLabel">
                                @foreach($tones as $tone => $label)
                                    <label class="tone-{{ $tone }}"><input type="radio" name="color" value="{{ $tone }}" @checked($defaultColor === $tone) data-lib-color><span>{{ $label }}</span></label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i data-lucide="save"></i><span data-lib-category-submit>{{ $editingCategory ? 'Save changes' : 'Add category' }}</span></button></div>
            </form>
        </div>
    </div>

    {{-- Add device type / manufacturer --}}
    <div class="modal fade" id="masterModal" tabindex="-1" aria-labelledby="masterModalTitle">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ route('library.masters.store') }}">
                @csrf
                <div class="modal-header"><h5 class="modal-title" id="masterModalTitle">Add list entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label" for="masterType">List *</label><select class="form-select" id="masterType" name="type" required data-lib-master-type>@foreach($groups as $key => $group)<option value="{{ $key }}" @selected(old('type', $tab === 'categories' ? 'library_device_type' : $tab) === $key)>{{ $group['label'] }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label" for="masterLabel">Name *</label><input class="form-control" id="masterLabel" name="label" value="{{ $oldModal === 'masterModal' ? old('label') : '' }}" maxlength="120" required placeholder="e.g. Hisense"></div>
                    </div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Add</button></div>
            </form>
        </div>
    </div>

    {{-- Rename device type / manufacturer --}}
    <div class="modal fade" id="masterRenameModal" tabindex="-1" aria-labelledby="masterRenameTitle">
        <div class="modal-dialog modal-dialog-centered">
            <form class="modal-content" method="POST" action="{{ $renamingOption ? route('library.masters.update', $renamingOption) : '#' }}" data-lib-rename-form>
                @csrf @method('PUT')
                <input type="hidden" name="option_id" value="{{ $renamingOption?->id }}" data-lib-option-id>
                <input type="hidden" name="tab" value="{{ $renamingOption?->type }}" data-lib-option-tab>
                <div class="modal-header"><h5 class="modal-title" id="masterRenameTitle">Rename entry</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label class="form-label" for="renameLabel">Name *</label>
                    <input class="form-control" id="renameLabel" name="label" value="{{ $renamingOption ? old('label') : '' }}" maxlength="120" required data-lib-option-label>
                    <div class="form-text">Guides already tagged with this entry keep it and show the new name.</div>
                </div>
                <div class="modal-footer"><button type="button" class="btn btn-light border" data-bs-dismiss="modal">Cancel</button><button class="btn btn-primary" type="submit"><i data-lucide="save"></i>Save</button></div>
            </form>
        </div>
    </div>

    @push('scripts')@vite('resources/js/library.js')@endpush
</x-app-layout>
