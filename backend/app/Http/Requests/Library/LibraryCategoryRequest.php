<?php

namespace App\Http\Requests\Library;

use App\Models\LibraryCategory;
use App\Services\LibraryOptions;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Add or edit a knowledge library category (library.manage).
 */
class LibraryCategoryRequest extends FormRequest
{
    /** Icons offered by the category icon picker (Lucide names). */
    public const ICONS = [
        'book-open', 'hammer', 'wrench', 'shield-check', 'stethoscope', 'hard-hat', 'gauge', 'graduation-cap',
        'snowflake', 'fan', 'thermometer', 'wind', 'air-vent', 'droplets', 'zap', 'plug',
        'cog', 'cpu', 'clipboard-check', 'triangle-alert', 'lightbulb', 'flame', 'factory', 'circle-help',
    ];

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('library.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $category = $this->route('category');
        $currentIcon = $category instanceof LibraryCategory ? [$category->icon] : [];

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('library_categories', 'name')->ignore($category instanceof LibraryCategory ? $category->id : null)],
            'description' => ['nullable', 'string', 'max:500'],
            'icon' => ['required', Rule::in([...self::ICONS, ...$currentIcon])],
            'color' => ['required', Rule::in(array_keys(LibraryOptions::TONES))],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name for the category.',
            'name.unique' => 'A category with this name already exists.',
            'icon.required' => 'Pick an icon for the category.',
            'icon.in' => 'Pick one of the icons shown.',
            'color.required' => 'Pick a colour for the category.',
            'color.in' => 'Pick one of the colours shown.',
            'sort_order.integer' => 'Sort order must be a whole number, e.g. 10.',
            'sort_order.min' => 'Sort order cannot be negative.',
            'sort_order.max' => 'Sort order can be up to 999.',
        ];
    }
}
