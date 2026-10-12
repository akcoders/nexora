<?php

namespace App\Services;

use App\Models\State;
use App\Rules\IndianDistrict;
use App\Rules\IndianState;
use Illuminate\Support\Facades\Cache;

/**
 * Cached lookup of Indian states and their districts, shared by the address
 * dropdowns and the server-side address validation.
 */
class IndiaLocations
{
    public const COUNTRY = 'India';

    public const CACHE_KEY = 'india_locations.v1';

    /** @return array<int, array{name: string, code: string, gst_code: ?string, type: string, districts: array<int, string>}> */
    public function all(): array
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), fn (): array => State::query()
            ->where('active', true)
            ->with(['districts' => fn ($query) => $query->where('active', true)])
            ->orderBy('name')
            ->get()
            ->map(fn (State $state): array => [
                'name' => $state->name,
                'code' => $state->code,
                'gst_code' => $state->gst_code,
                'type' => $state->type,
                'districts' => $state->districts->pluck('name')->all(),
            ])
            ->all());
    }

    /**
     * Validation rules for an optional state + district pair stored under a key
     * prefix such as "address." or "sites.*.address.". A district is required
     * once a state is chosen.
     *
     * @return array<string, array<int, mixed>>
     */
    public static function optionalAddressRules(string $prefix): array
    {
        return [
            "{$prefix}state" => ['nullable', 'string', 'max:120', new IndianState],
            "{$prefix}district" => ["required_with:{$prefix}state", 'nullable', 'string', 'max:120', new IndianDistrict],
        ];
    }

    /**
     * Stamps India as the country on an address that has any details filled in.
     *
     * @param  array<string, mixed>|null  $address
     * @return array<string, mixed>
     */
    public static function withCountry(?array $address): array
    {
        $address ??= [];
        $filled = collect($address)->except('country')->filter(fn (mixed $value): bool => filled($value))->isNotEmpty();

        return $filled ? array_merge($address, ['country' => self::COUNTRY]) : $address;
    }

    public function hasState(?string $state): bool
    {
        return $this->find($state) !== null;
    }

    public function hasDistrict(?string $state, ?string $district): bool
    {
        return in_array($district, $this->find($state)['districts'] ?? [], true);
    }

    /** @return array{name: string, code: string, gst_code: ?string, type: string, districts: array<int, string>}|null */
    private function find(?string $name): ?array
    {
        foreach ($this->all() as $state) {
            if ($state['name'] === $name) {
                return $state;
            }
        }

        return null;
    }
}
