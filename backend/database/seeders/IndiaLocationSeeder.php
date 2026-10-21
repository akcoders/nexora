<?php

namespace Database\Seeders;

use App\Services\IndiaLocations;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Upserts every Indian state/UT and its districts from
 * database/data/india_states_districts.php. Safe to run repeatedly: new
 * entries are added and existing ones updated, nothing is deleted.
 */
class IndiaLocationSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();
        $states = require database_path('data/india_states_districts.php');

        DB::table('states')->upsert(array_map(fn (array $state): array => [
            'name' => $state['name'],
            'code' => $state['code'],
            'gst_code' => $state['gst_code'],
            'type' => $state['type'],
            'country' => 'India',
            'created_at' => $now,
            'updated_at' => $now,
        ], $states), ['name'], ['code', 'gst_code', 'type', 'country', 'updated_at']);

        $stateIds = DB::table('states')->pluck('id', 'name');
        $districts = [];
        foreach ($states as $state) {
            foreach ($state['districts'] as $district) {
                $districts[] = ['state_id' => $stateIds[$state['name']], 'name' => $district, 'created_at' => $now, 'updated_at' => $now];
            }
        }
        DB::table('districts')->upsert($districts, ['state_id', 'name'], ['updated_at']);

        Cache::forget(IndiaLocations::CACHE_KEY);
    }
}
