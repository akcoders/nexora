<?php

namespace App\Services;

use App\Models\ServiceCatalogItem;
use Illuminate\Support\Arr;

class ServiceEstimateCalculator
{
    public function calculate(array $items, float $discountAmount = 0): array
    {
        $calculatedItems = collect($items)->map(function (array $item, int $index): array {
            $catalogItem = filled($item['service_catalog_item_id'] ?? null)
                ? ServiceCatalogItem::query()->where('active', true)->findOrFail($item['service_catalog_item_id'])
                : null;
            $quantity = max(0.01, (float) ($item['quantity'] ?? 1));
            $unitRate = $catalogItem ? (float) $catalogItem->standard_price : max(0, (float) ($item['unit_rate'] ?? 0));
            $taxPercent = $catalogItem ? (float) $catalogItem->tax_percent : max(0, (float) ($item['tax_percent'] ?? 0));
            $lineSubtotal = round($quantity * $unitRate, 2);
            $taxAmount = round($lineSubtotal * $taxPercent / 100, 2);

            return [
                'service_catalog_item_id' => $catalogItem?->id,
                'product_id' => Arr::get($item, 'product_id'),
                'type' => $catalogItem ? 'service' : $item['type'],
                'description' => $catalogItem?->name ?? $item['description'],
                'quantity' => $quantity,
                'unit' => $item['unit'] ?? ($catalogItem ? 'service' : 'item'),
                'unit_rate' => $unitRate,
                'tax_percent' => $taxPercent,
                'line_subtotal' => $lineSubtotal,
                'tax_amount' => $taxAmount,
                'line_total' => round($lineSubtotal + $taxAmount, 2),
                'sort_order' => $index,
            ];
        });

        $subtotal = round((float) $calculatedItems->sum('line_subtotal'), 2);
        $taxAmount = round((float) $calculatedItems->sum('tax_amount'), 2);
        $discountAmount = min(max(0, $discountAmount), $subtotal + $taxAmount);

        return [
            'items' => $calculatedItems->all(),
            'subtotal' => $subtotal,
            'tax_amount' => $taxAmount,
            'discount_amount' => $discountAmount,
            'final_amount' => round($subtotal + $taxAmount - $discountAmount, 2),
        ];
    }
}
