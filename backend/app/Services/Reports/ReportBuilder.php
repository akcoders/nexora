<?php

namespace App\Services\Reports;

use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Runs a custom report: either a summary (group by a field, optionally split by a second
 * field, with a count / sum / average / min / max) or a plain list of chosen columns.
 * Only whitelisted dataset fields can be referenced; values always go through bindings.
 */
class ReportBuilder
{
    public const GRANULARITIES = ['day' => 'Day', 'week' => 'Week', 'month' => 'Month', 'quarter' => 'Quarter', 'year' => 'Year'];

    public const MEASURES = ['count' => 'Count', 'sum' => 'Total', 'avg' => 'Average', 'min' => 'Minimum', 'max' => 'Maximum'];

    public const CHARTS = ['bar' => 'Column', 'hbar' => 'Bar', 'stacked' => 'Stacked', 'line' => 'Line', 'area' => 'Area', 'pie' => 'Pie', 'donut' => 'Donut', 'table' => 'Table'];

    public const PRESETS = [
        'all' => 'All time', 'today' => 'Today', 'yesterday' => 'Yesterday', 'last_7' => 'Last 7 days', 'last_30' => 'Last 30 days',
        'this_month' => 'This month', 'last_month' => 'Last month', 'this_quarter' => 'This quarter', 'this_year' => 'This year',
        'last_year' => 'Last year', 'custom' => 'Custom range',
    ];

    public const OPERATORS = [
        'string' => ['contains' => 'contains', 'eq' => 'is', 'neq' => 'is not', 'empty' => 'is empty', 'not_empty' => 'is not empty'],
        'enum' => ['in' => 'is any of', 'neq' => 'is not', 'empty' => 'is empty', 'not_empty' => 'is not empty'],
        'number' => ['eq' => '=', 'gt' => '>', 'gte' => '≥', 'lt' => '<', 'lte' => '≤', 'empty' => 'is empty'],
        'date' => ['on' => 'on', 'after' => 'after', 'before' => 'before', 'empty' => 'is empty', 'not_empty' => 'is not empty'],
        'bool' => ['is_true' => 'is yes', 'is_false' => 'is no'],
    ];

    private const MAX_ROWS = 2000;

    /**
     * Validates and fills defaults for a report configuration.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, mixed>
     */
    public function normalize(array $input, User $user): array
    {
        $datasets = ReportDatasets::availableFor($user);
        $datasetKey = (string) ($input['dataset'] ?? '');
        if (! isset($datasets[$datasetKey])) {
            throw ValidationException::withMessages(['dataset' => 'Choose a data source you have access to.']);
        }
        $fields = $datasets[$datasetKey]['fields'];
        $field = fn (?string $key): ?array => $key !== null && isset($fields[$key]) ? $fields[$key] : null;
        $errors = [];

        $mode = in_array($input['mode'] ?? null, ['summary', 'list'], true) ? $input['mode'] : 'summary';
        $config = ['dataset' => $datasetKey, 'mode' => $mode];

        if ($mode === 'summary') {
            $groupBy = $input['group_by'] ?? null;
            if (! $field($groupBy)) {
                $errors['group_by'] = 'Choose what to group the report by.';
            }
            $config['group_by'] = $groupBy;
            $config['granularity'] = ($field($groupBy)['type'] ?? null) === 'date'
                ? (array_key_exists($input['granularity'] ?? '', self::GRANULARITIES) ? $input['granularity'] : 'month')
                : null;
            $splitBy = filled($input['split_by'] ?? null) ? $input['split_by'] : null;
            if ($splitBy !== null && (! $field($splitBy) || $splitBy === $groupBy || $field($splitBy)['type'] === 'date')) {
                $errors['split_by'] = 'Choose a different, non-date field to split by.';
            }
            $config['split_by'] = $splitBy;
            $measure = array_key_exists($input['measure'] ?? '', self::MEASURES) ? $input['measure'] : 'count';
            $config['measure'] = $measure;
            $config['measure_field'] = null;
            if ($measure !== 'count') {
                $measureField = $input['measure_field'] ?? null;
                if (($field($measureField)['type'] ?? null) !== 'number') {
                    $errors['measure_field'] = 'Choose a number field to '.strtolower(self::MEASURES[$measure]).'.';
                }
                $config['measure_field'] = $measureField;
            }
            $config['sort'] = in_array($input['sort'] ?? null, ['value_desc', 'value_asc', 'label_asc', 'label_desc'], true)
                ? $input['sort']
                : (($field($groupBy)['type'] ?? null) === 'date' ? 'label_asc' : 'value_desc');
            $config['limit'] = max(3, min(100, (int) ($input['limit'] ?? 20)));
            $config['chart'] = array_key_exists($input['chart'] ?? '', self::CHARTS) ? $input['chart'] : 'bar';
        } else {
            $columns = array_values(array_unique(array_filter((array) ($input['columns'] ?? []), fn ($key) => is_string($key) && $field($key))));
            if ($columns === []) {
                $errors['columns'] = 'Pick at least one column to list.';
            }
            $config['columns'] = array_slice($columns, 0, 15);
            $config['list_sort'] = $field($input['list_sort'] ?? null) ? $input['list_sort'] : ($columns[0] ?? null);
            $config['list_dir'] = ($input['list_dir'] ?? 'desc') === 'asc' ? 'asc' : 'desc';
            $config['limit'] = max(10, min(self::MAX_ROWS, (int) ($input['limit'] ?? 500)));
            $config['chart'] = 'table';
        }

        $dateField = filled($input['date_field'] ?? null) ? $input['date_field'] : $datasets[$datasetKey]['default_date'];
        $config['date_field'] = ($field($dateField)['type'] ?? null) === 'date' ? $dateField : null;
        $config['preset'] = array_key_exists($input['preset'] ?? '', self::PRESETS) ? $input['preset'] : 'all';
        $config['from'] = $config['preset'] === 'custom' && filled($input['from'] ?? null) ? Carbon::parse($input['from'])->toDateString() : null;
        $config['to'] = $config['preset'] === 'custom' && filled($input['to'] ?? null) ? Carbon::parse($input['to'])->toDateString() : null;
        if ($config['from'] && $config['to'] && $config['from'] > $config['to']) {
            $errors['to'] = 'The end date must be on or after the start date.';
        }

        $config['filters'] = [];
        foreach (array_slice((array) ($input['filters'] ?? []), 0, 10) as $index => $filter) {
            $definition = $field($filter['field'] ?? null);
            if (! $definition) {
                continue;
            }
            $operator = $filter['op'] ?? null;
            if (! array_key_exists((string) $operator, self::OPERATORS[$definition['type']])) {
                $errors["filters.$index.op"] = 'Choose a valid condition for '.$definition['label'].'.';

                continue;
            }
            $value = $filter['value'] ?? null;
            $needsValue = ! in_array($operator, ['empty', 'not_empty', 'is_true', 'is_false'], true);
            if ($needsValue && (blank($value) || (is_array($value) && array_filter($value, 'filled') === []))) {
                $errors["filters.$index.value"] = 'Enter a value for '.$definition['label'].'.';

                continue;
            }
            if ($needsValue && $definition['type'] === 'number' && ! is_numeric($value)) {
                $errors["filters.$index.value"] = $definition['label'].' must be a number.';

                continue;
            }
            if ($needsValue && $definition['type'] === 'date' && strtotime((string) $value) === false) {
                $errors["filters.$index.value"] = $definition['label'].' must be a date.';

                continue;
            }
            $config['filters'][] = [
                'field' => $filter['field'],
                'op' => $operator,
                'value' => $operator === 'in' ? array_values(array_map('strval', (array) $value)) : ($needsValue ? (string) $value : null),
            ];
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $config;
    }

    /**
     * @param  array<string, mixed>  $config  a normalized configuration
     * @return array<string, mixed>
     */
    public function run(array $config, User $user): array
    {
        $dataset = ReportDatasets::availableFor($user)[$config['dataset']] ?? throw ValidationException::withMessages(['dataset' => 'You no longer have access to this data source.']);
        $query = ($dataset['query'])($user)->toBase();
        $used = array_filter([
            $config['group_by'] ?? null, $config['split_by'] ?? null, $config['measure_field'] ?? null, $config['date_field'] ?? null,
            ...($config['columns'] ?? []), ...array_column($config['filters'], 'field'), $config['list_sort'] ?? null,
        ]);
        $this->applyJoins($query, $dataset, $used);
        $this->applyDateRange($query, $dataset, $config);
        foreach ($config['filters'] as $filter) {
            $this->applyFilter($query, $dataset['fields'][$filter['field']], $filter);
        }

        $result = $config['mode'] === 'list' ? $this->runList($query, $dataset, $config) : $this->runSummary($query, $dataset, $config);
        $result['meta'] = array_merge($result['meta'] ?? [], [
            'dataset' => $dataset['label'],
            'period' => $this->periodLabel($config),
            'filters' => $this->filterSummary($dataset, $config),
            'generated_at' => now()->format('d M Y, h:i A'),
        ]);

        return $result;
    }

    /** @return array<string, mixed> */
    private function runSummary(Builder $query, array $dataset, array $config): array
    {
        $groupField = $dataset['fields'][$config['group_by']];
        $groupExpr = $groupField['type'] === 'date' ? $this->dateBucket($groupField['expr'], $config['granularity']) : $groupField['expr'];
        $splitField = $config['split_by'] ? $dataset['fields'][$config['split_by']] : null;
        $measureExpr = match ($config['measure']) {
            'count' => 'COUNT(*)',
            default => strtoupper($config['measure']).'('.$dataset['fields'][$config['measure_field']]['expr'].')',
        };

        $query->selectRaw("{$groupExpr} as report_group")->selectRaw("{$measureExpr} as report_value")->groupByRaw($groupExpr);
        if ($splitField) {
            $query->selectRaw("{$splitField['expr']} as report_split")->groupByRaw($splitField['expr']);
        }
        $rows = $query->limit(5000)->get();

        $totals = [];
        foreach ($rows as $row) {
            $key = $this->key($row->report_group);
            $totals[$key] = ($totals[$key] ?? 0) + (float) $row->report_value;
        }
        $groupKeys = array_keys($totals);
        usort($groupKeys, function ($a, $b) use ($config, $totals, $groupField): int {
            return match ($config['sort']) {
                'value_asc' => $totals[$a] <=> $totals[$b],
                'label_asc' => $this->sortValue($a, $groupField) <=> $this->sortValue($b, $groupField),
                'label_desc' => $this->sortValue($b, $groupField) <=> $this->sortValue($a, $groupField),
                default => $totals[$b] <=> $totals[$a],
            };
        });
        $truncated = count($groupKeys) > $config['limit'];
        $groupKeys = array_slice($groupKeys, 0, $config['limit']);
        $labels = array_map(fn ($key) => $this->label($key, $groupField, $config['granularity']), $groupKeys);
        $measureLabel = $this->measureLabel($dataset, $config);

        if (! $splitField) {
            $values = array_map(fn ($key) => $this->round($totals[$key]), $groupKeys);
            $tableRows = array_map(fn ($label, $value) => [$label, $value], $labels, $values);
            $series = [['name' => $measureLabel, 'data' => $values]];
            $columns = [$groupField['label'], $measureLabel];
        } else {
            $splitTotals = [];
            $matrix = [];
            foreach ($rows as $row) {
                $groupKey = $this->key($row->report_group);
                if (! in_array($groupKey, $groupKeys, true)) {
                    continue;
                }
                $splitKey = $this->key($row->report_split);
                $matrix[$groupKey][$splitKey] = ($matrix[$groupKey][$splitKey] ?? 0) + (float) $row->report_value;
                $splitTotals[$splitKey] = ($splitTotals[$splitKey] ?? 0) + (float) $row->report_value;
            }
            arsort($splitTotals);
            $splitKeys = array_slice(array_keys($splitTotals), 0, 10);
            $series = [];
            foreach ($splitKeys as $splitKey) {
                $series[] = ['name' => $this->label($splitKey, $splitField, null), 'data' => array_map(fn ($groupKey) => $this->round($matrix[$groupKey][$splitKey] ?? 0), $groupKeys)];
            }
            $columns = [$groupField['label'], ...array_column($series, 'name'), 'Total'];
            $tableRows = [];
            foreach ($groupKeys as $index => $groupKey) {
                $cells = array_map(fn ($serie) => $serie['data'][$index], $series);
                $tableRows[] = [$labels[$index], ...$cells, $this->round(array_sum($matrix[$groupKey] ?? []))];
            }
        }

        $grandTotal = in_array($config['measure'], ['count', 'sum'], true) ? $this->round(array_sum(array_map(fn ($key) => $totals[$key], $groupKeys))) : null;

        return [
            'mode' => 'summary',
            'chart' => $config['chart'],
            'labels' => $labels,
            'series' => $series,
            'columns' => $columns,
            'rows' => $tableRows,
            'total' => $grandTotal,
            'meta' => ['measure' => $measureLabel, 'group' => $groupField['label'].($config['granularity'] ? ' ('.strtolower(self::GRANULARITIES[$config['granularity']]).')' : ''), 'split' => $splitField['label'] ?? null, 'truncated' => $truncated, 'row_count' => count($tableRows)],
        ];
    }

    /** @return array<string, mixed> */
    private function runList(Builder $query, array $dataset, array $config): array
    {
        foreach ($config['columns'] as $index => $key) {
            $query->selectRaw($dataset['fields'][$key]['expr']." as report_col_{$index}");
        }
        if ($config['list_sort']) {
            $query->orderByRaw($dataset['fields'][$config['list_sort']]['expr'].' '.$config['list_dir']);
        }
        $rows = $query->limit($config['limit'] + 1)->get();
        $truncated = $rows->count() > $config['limit'];
        $rows = $rows->take($config['limit']);

        return [
            'mode' => 'list',
            'chart' => 'table',
            'labels' => [],
            'series' => [],
            'columns' => array_map(fn ($key) => $dataset['fields'][$key]['label'], $config['columns']),
            'rows' => $rows->map(fn ($row) => array_map(fn ($index, $key) => $this->cell($row->{"report_col_{$index}"}, $dataset['fields'][$key]), array_keys($config['columns']), $config['columns']))->values()->all(),
            'total' => null,
            'meta' => ['row_count' => $rows->count(), 'truncated' => $truncated],
        ];
    }

    /** @param list<string> $fieldKeys */
    private function applyJoins(Builder $query, array $dataset, array $fieldKeys): void
    {
        $needed = [];
        foreach (array_unique($fieldKeys) as $key) {
            if ($join = $dataset['fields'][$key]['join'] ?? null) {
                $needed[] = $join;
                while ($dependency = $dataset['joins'][$join][3] ?? null) {
                    array_unshift($needed, $dependency);
                    $join = $dependency;
                }
            }
        }
        foreach (array_unique($needed) as $join) {
            [$table, $first, $second] = $dataset['joins'][$join];
            $query->leftJoin($table, $first, '=', $second);
        }
    }

    private function applyDateRange(Builder $query, array $dataset, array $config): void
    {
        if (! $config['date_field'] || $config['preset'] === 'all') {
            return;
        }
        [$from, $to] = $this->presetRange($config);
        $expr = $dataset['fields'][$config['date_field']]['expr'];
        if ($from) {
            $query->whereRaw("{$expr} >= ?", [$from->format('Y-m-d H:i:s')]);
        }
        if ($to) {
            $query->whereRaw("{$expr} <= ?", [$to->format('Y-m-d H:i:s')]);
        }
    }

    /** @return array{0: ?Carbon, 1: ?Carbon} */
    private function presetRange(array $config): array
    {
        $today = now()->startOfDay();

        return match ($config['preset']) {
            'today' => [$today, $today->copy()->endOfDay()],
            'yesterday' => [$today->copy()->subDay(), $today->copy()->subDay()->endOfDay()],
            'last_7' => [$today->copy()->subDays(6), now()->endOfDay()],
            'last_30' => [$today->copy()->subDays(29), now()->endOfDay()],
            'this_month' => [$today->copy()->startOfMonth(), now()->endOfMonth()],
            'last_month' => [$today->copy()->subMonthNoOverflow()->startOfMonth(), $today->copy()->subMonthNoOverflow()->endOfMonth()],
            'this_quarter' => [$today->copy()->startOfQuarter(), now()->endOfQuarter()],
            'this_year' => [$today->copy()->startOfYear(), now()->endOfYear()],
            'last_year' => [$today->copy()->subYear()->startOfYear(), $today->copy()->subYear()->endOfYear()],
            'custom' => [$config['from'] ? Carbon::parse($config['from'])->startOfDay() : null, $config['to'] ? Carbon::parse($config['to'])->endOfDay() : null],
            default => [null, null],
        };
    }

    /** @param array<string, mixed> $filter */
    private function applyFilter(Builder $query, array $field, array $filter): void
    {
        $expr = $field['expr'];
        $value = $filter['value'];

        match ($filter['op']) {
            'eq' => $field['type'] === 'number' ? $query->whereRaw("{$expr} = ?", [(float) $value]) : $query->whereRaw("LOWER({$expr}) = ?", [mb_strtolower($value)]),
            'neq' => $query->where(fn (Builder $inner) => $inner->whereRaw($field['type'] === 'enum' ? "{$expr} <> ?" : "LOWER({$expr}) <> ?", [$field['type'] === 'enum' ? $value : mb_strtolower($value)])->orWhereRaw("{$expr} IS NULL")),
            'contains' => $query->whereRaw("LOWER({$expr}) LIKE ?", ['%'.str_replace(['%', '_'], ['\\%', '\\_'], mb_strtolower($value)).'%']),
            'in' => $query->whereRaw("{$expr} IN (".implode(',', array_fill(0, count($value), '?')).')', $value),
            'empty' => in_array($field['type'], ['string', 'enum'], true) ? $query->whereRaw("({$expr} IS NULL OR {$expr} = '')") : $query->whereRaw("{$expr} IS NULL"),
            'not_empty' => in_array($field['type'], ['string', 'enum'], true) ? $query->whereRaw("({$expr} IS NOT NULL AND {$expr} <> '')") : $query->whereRaw("{$expr} IS NOT NULL"),
            'gt' => $query->whereRaw("{$expr} > ?", [(float) $value]),
            'gte' => $query->whereRaw("{$expr} >= ?", [(float) $value]),
            'lt' => $query->whereRaw("{$expr} < ?", [(float) $value]),
            'lte' => $query->whereRaw("{$expr} <= ?", [(float) $value]),
            'on' => $query->whereRaw("DATE({$expr}) = ?", [Carbon::parse($value)->toDateString()]),
            'after' => $query->whereRaw("{$expr} > ?", [Carbon::parse($value)->endOfDay()->format('Y-m-d H:i:s')]),
            'before' => $query->whereRaw("{$expr} < ?", [Carbon::parse($value)->startOfDay()->format('Y-m-d H:i:s')]),
            'is_true' => $query->whereRaw("{$expr} = 1"),
            'is_false' => $query->whereRaw("({$expr} = 0 OR {$expr} IS NULL)"),
        };
    }

    private function dateBucket(string $expr, string $granularity): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => match ($granularity) {
                'day' => "strftime('%Y-%m-%d', {$expr})",
                'week' => "strftime('%Y-W%W', {$expr})",
                'quarter' => "(strftime('%Y', {$expr}) || '-Q' || ((CAST(strftime('%m', {$expr}) AS INTEGER) + 2) / 3))",
                'year' => "strftime('%Y', {$expr})",
                default => "strftime('%Y-%m', {$expr})",
            },
            'pgsql' => match ($granularity) {
                'day' => "to_char({$expr}, 'YYYY-MM-DD')",
                'week' => "to_char({$expr}, 'IYYY-\"W\"IW')",
                'quarter' => "to_char({$expr}, 'YYYY-\"Q\"Q')",
                'year' => "to_char({$expr}, 'YYYY')",
                default => "to_char({$expr}, 'YYYY-MM')",
            },
            default => match ($granularity) {
                'day' => "DATE_FORMAT({$expr}, '%Y-%m-%d')",
                'week' => "DATE_FORMAT({$expr}, '%x-W%v')",
                'quarter' => "CONCAT(YEAR({$expr}), '-Q', QUARTER({$expr}))",
                'year' => "DATE_FORMAT({$expr}, '%Y')",
                default => "DATE_FORMAT({$expr}, '%Y-%m')",
            },
        };
    }

    private function key(mixed $value): string
    {
        return $value === null || $value === '' ? '__none__' : (string) $value;
    }

    private function sortValue(string $key, array $field): string|float
    {
        if ($key === '__none__') {
            return $field['type'] === 'number' ? PHP_FLOAT_MAX : "\u{10FFFF}";
        }

        return $field['type'] === 'number' ? (float) $key : mb_strtolower($key);
    }

    private function label(string $key, array $field, ?string $granularity): string
    {
        if ($key === '__none__') {
            return '(Not set)';
        }

        return match ($field['type']) {
            'enum' => (string) ($field['options'][$key] ?? str($key)->replace('_', ' ')->headline()),
            'bool' => in_array($key, ['1', 'true'], true) ? 'Yes' : 'No',
            'date' => $this->dateLabel($key, $granularity),
            'number' => (string) $this->round((float) $key),
            default => $key,
        };
    }

    private function dateLabel(string $key, ?string $granularity): string
    {
        try {
            return match ($granularity) {
                'day' => Carbon::createFromFormat('Y-m-d', $key)->format('d M Y'),
                'month' => Carbon::createFromFormat('Y-m-d', $key.'-01')->format('M Y'),
                default => $key,
            };
        } catch (\Throwable) {
            return $key;
        }
    }

    private function cell(mixed $value, array $field): string|float|null
    {
        if ($value === null || $value === '') {
            return '';
        }

        return match ($field['type']) {
            'enum' => (string) ($field['options'][(string) $value] ?? str((string) $value)->replace('_', ' ')->headline()),
            'bool' => (string) $value === '1' ? 'Yes' : 'No',
            'number' => $this->round((float) $value),
            'date' => Carbon::parse($value)->format(strlen((string) $value) > 10 && ! str_ends_with((string) $value, '00:00:00') ? 'd M Y, h:i A' : 'd M Y'),
            default => (string) $value,
        };
    }

    private function round(float $value): float|int
    {
        return fmod($value, 1.0) === 0.0 ? (int) $value : round($value, 2);
    }

    private function measureLabel(array $dataset, array $config): string
    {
        return $config['measure'] === 'count'
            ? 'Number of '.strtolower($dataset['label'])
            : self::MEASURES[$config['measure']].' '.strtolower($dataset['fields'][$config['measure_field']]['label']);
    }

    private function periodLabel(array $config): string
    {
        if ($config['preset'] === 'custom') {
            return collect([$config['from'] ? Carbon::parse($config['from'])->format('d M Y') : 'Start', $config['to'] ? Carbon::parse($config['to'])->format('d M Y') : 'Today'])->join(' – ');
        }

        return self::PRESETS[$config['preset']];
    }

    /** @return list<string> */
    private function filterSummary(array $dataset, array $config): array
    {
        return array_map(function (array $filter) use ($dataset): string {
            $field = $dataset['fields'][$filter['field']];
            $value = is_array($filter['value'])
                ? implode(', ', array_map(fn ($item) => $field['options'][$item] ?? $item, $filter['value']))
                : (string) $filter['value'];

            return trim($field['label'].' '.self::OPERATORS[$field['type']][$filter['op']].' '.$value);
        }, $config['filters']);
    }

    /** Shared lists for the builder screen. @return array<string, mixed> */
    public static function uiOptions(): array
    {
        return [
            'granularities' => self::GRANULARITIES,
            'measures' => self::MEASURES,
            'charts' => self::CHARTS,
            'presets' => self::PRESETS,
            'operators' => self::OPERATORS,
        ];
    }
}
