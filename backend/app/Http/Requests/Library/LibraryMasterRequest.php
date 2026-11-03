<?php

namespace App\Http\Requests\Library;

use App\Models\ServiceMasterOption;
use App\Services\LibraryOptions;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Add a device type / manufacturer, or rename one (library.manage). The code stays the same on
 * rename because guides store it.
 */
class LibraryMasterRequest extends FormRequest
{
    public function authorize(): bool
    {
        $option = $this->route('option');

        return (bool) $this->user()?->can('library.manage')
            && (! $option instanceof ServiceMasterOption || array_key_exists($option->type, LibraryOptions::masterGroups()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $option = $this->route('option');
        $type = $option instanceof ServiceMasterOption ? $option->type : $this->input('type');

        return [
            'type' => $option instanceof ServiceMasterOption ? ['prohibited'] : ['required', Rule::in(array_keys(LibraryOptions::masterGroups()))],
            'label' => ['required', 'string', 'max:120', function (string $attribute, mixed $value, Closure $fail) use ($option, $type): void {
                if (! is_string($type) || ! is_string($value)) {
                    return;
                }
                $label = trim($value);
                $duplicate = ServiceMasterOption::query()
                    ->where('type', $type)
                    ->when($option instanceof ServiceMasterOption, fn ($query) => $query->whereKeyNot($option->id))
                    ->where(fn ($query) => $query->whereRaw('lower(label) = ?', [mb_strtolower($label)])
                        ->when(! $option instanceof ServiceMasterOption, fn ($query) => $query->orWhere('code', $this->codeFor($label))))
                    ->exists();
                if ($duplicate) {
                    $fail('"'.$label.'" is already in this list.');
                } elseif (! $option instanceof ServiceMasterOption && $this->codeFor($label) === '') {
                    $fail('Use letters or numbers in the name.');
                }
            }],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'type.required' => 'Choose the list to add to.',
            'type.in' => 'Choose device types or manufacturers.',
            'label.required' => 'Enter a name.',
            'label.max' => 'Keep the name under 120 characters.',
        ];
    }

    /** Stored code for a new entry, e.g. "Blue Star" becomes blue_star. */
    public function codeFor(string $label): string
    {
        return Str::limit(Str::slug($label, '_'), 50, '');
    }
}
