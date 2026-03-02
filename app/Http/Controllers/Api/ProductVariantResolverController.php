<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductVariantResolverController extends Controller
{
    public function resolve(Request $request, Product $product, PriceService $priceService): JsonResponse
    {
        $rawAttributeValueIds = $request->input('attribute_value_ids');

        if (is_string($rawAttributeValueIds) && trim($rawAttributeValueIds) !== '') {
            $request->merge([
                'attribute_value_ids' => collect(explode(',', $rawAttributeValueIds))
                    ->map(fn ($value) => trim($value))
                    ->filter(fn ($value) => $value !== '')
                    ->values()
                    ->all(),
            ]);
        }

        $data = $request->validate([
            'attribute_value_ids' => 'nullable|array',
            'attribute_value_ids.*' => 'integer|distinct|exists:attribute_values,id',
        ]);

        $attributeValues = $product->attributeValues()
            ->with('attribute:id,name_ar,name_en')
            ->get(['id', 'attribute_id', 'product_id', 'value_ar', 'value_en']);

        $requiredAttributesCount = (int) $attributeValues
            ->pluck('attribute_id')
            ->unique()
            ->count();

        $attributeValuesById = $attributeValues->keyBy('id');

        $selectedIds = collect($data['attribute_value_ids'] ?? [])->map(fn ($id) => (int) $id)->values();

        $selectedValues = $selectedIds
            ->map(fn (int $id) => $attributeValuesById->get($id))
            ->filter()
            ->values();

        if ($selectedIds->count() !== $selectedValues->count()) {
            return response()->json([
                'message' => 'بعض قيم الخصائص لا تنتمي لهذا المنتج.',
            ], 422);
        }

        $selectedByAttribute = collect();
        foreach ($selectedValues as $selectedValue) {
            $selectedByAttribute->put((int) $selectedValue->attribute_id, (int) $selectedValue->id);
        }

        $normalizedSelectedIds = $selectedByAttribute->values();

        $variants = $product->variants()
            ->with('attributeValues:id,attribute_id,value_ar,value_en')
            ->get(['id', 'product_id', 'sku', 'price', 'stock', 'image']);

        $variantIndex = $variants->map(function ($variant) {
            return [
                'variant' => $variant,
                'value_ids' => $variant->attributeValues->pluck('id')->map(fn ($id) => (int) $id)->values()->all(),
            ];
        });

        $matchingVariants = $variantIndex->filter(function ($entry) use ($normalizedSelectedIds) {
            if ($normalizedSelectedIds->isEmpty()) {
                return true;
            }

            return $normalizedSelectedIds->every(fn ($id) => in_array($id, $entry['value_ids'], true));
        })->values();

        $matchedVariant = null;
        $isFullySelected = $requiredAttributesCount > 0 && $normalizedSelectedIds->count() === $requiredAttributesCount;
        $variantExists = false;
        $isInStock = false;

        if ($isFullySelected) {
            $exact = $matchingVariants->first(function ($entry) use ($normalizedSelectedIds) {
                $valueIds = collect($entry['value_ids']);

                return $normalizedSelectedIds->count() === $valueIds->count()
                    && $normalizedSelectedIds->every(fn ($id) => $valueIds->containsStrict($id));
            });

            if ($exact) {
                $matchedVariant = $this->formatVariant($exact['variant'], $product, $priceService);
                $variantExists = true;
                $isInStock = ((int) ($exact['variant']->stock ?? 0)) > 0;
            }
        }

        $availabilityStatus = ! $isFullySelected
            ? 'incomplete_selection'
            : ($variantExists
                ? ($isInStock ? 'available' : 'out_of_stock')
                : 'not_found');

        $allAttributeMeta = $attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values, $attributeId) {
                $attribute = $values->first()?->attribute;

                return [
                    'attribute_id' => (int) $attributeId,
                    'name_ar' => $attribute?->name_ar,
                    'name_en' => $attribute?->name_en,
                ];
            })
            ->values();

        $missingAttributes = $allAttributeMeta
            ->reject(fn ($attribute) => $selectedByAttribute->has((int) $attribute['attribute_id']))
            ->values();

        $message = null;
        if ($availabilityStatus === 'incomplete_selection') {
            $missingNames = $missingAttributes
                ->pluck('name_ar')
                ->filter()
                ->values()
                ->all();

            $message = empty($missingNames)
                ? 'يرجى استكمال اختيار كل الخصائص.'
                : 'يرجى استكمال اختيار الخصائص التالية: ' . implode('، ', $missingNames);
        }

        if (! $isFullySelected) {
            $matchingVariants = collect();
        }

        $attributes = $attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values, $attributeId) use ($selectedByAttribute, $variantIndex) {
                $first = $values->first();
                $attribute = $first?->attribute;

                $options = $values->map(function ($value) use ($selectedByAttribute, $attributeId, $variantIndex) {
                    $candidateSelection = $selectedByAttribute->except((int) $attributeId)->values()->all();
                    $candidateSelection[] = (int) $value->id;

                    $isAvailable = $variantIndex->contains(function ($entry) use ($candidateSelection) {
                        return collect($candidateSelection)
                            ->every(fn ($candidateId) => in_array($candidateId, $entry['value_ids'], true));
                    });

                    return [
                        'id' => (int) $value->id,
                        'value_ar' => $value->value_ar,
                        'value_en' => $value->value_en,
                        'is_selected' => $selectedByAttribute->get((int) $attributeId) === (int) $value->id,
                        'is_available' => $isAvailable,
                    ];
                })->values()->all();

                return [
                    'attribute_id' => (int) $attributeId,
                    'name_ar' => $attribute?->name_ar,
                    'name_en' => $attribute?->name_en,
                    'options' => $options,
                ];
            })
            ->values()
            ->all();

        return response()->json([
            'product_id' => $product->id,
            'message' => $message,
            'selected_attribute_value_ids' => $normalizedSelectedIds->all(),
            'input_mode' => 'attribute_value_ids',
            'required_attributes_count' => $requiredAttributesCount,
            'selected_attributes_count' => $normalizedSelectedIds->count(),
            'is_fully_selected' => $isFullySelected,
            'matched_variant' => $matchedVariant,
            'has_exact_match' => $matchedVariant !== null,
            'variant_exists' => $variantExists,
            'is_in_stock' => $isInStock,
            'availability_status' => $availabilityStatus,
            'missing_attribute_ids' => $missingAttributes->pluck('attribute_id')->all(),
            'missing_attributes' => $missingAttributes->all(),
            'available_matching_variants_count' => $matchingVariants->count(),
            'attributes' => $attributes,
            'matching_variants' => $matchingVariants
                ->take(20)
                ->map(fn ($entry) => $this->formatVariant($entry['variant'], $product, $priceService))
                ->values()
                ->all(),
        ]);
    }

    private function formatVariant($variant, Product $product, PriceService $priceService): array
    {
        $originalPrice = (float) ($variant->price ?? $product->base_price);
        $finalPrice = (float) $priceService->resolveFinalPriceForVariant($product, $variant);

        return [
            'id' => (int) $variant->id,
            'sku' => $variant->sku,
            'price' => number_format($finalPrice, 2, '.', ''),
            'price_original' => number_format($originalPrice, 2, '.', ''),
            'price_final' => number_format($finalPrice, 2, '.', ''),
            'has_discount_or_promotion' => $finalPrice < $originalPrice,
            'discount_amount' => number_format(max($originalPrice - $finalPrice, 0), 2, '.', ''),
            'stock' => (int) $variant->stock,
            'available_quantity' => (int) $variant->stock,
            'image' => $variant->image,
            'attributes' => $variant->attributeValues
                ->map(fn ($value) => [
                    'attribute_id' => (int) $value->attribute_id,
                    'value_id' => (int) $value->id,
                    'value_ar' => $value->value_ar,
                    'value_en' => $value->value_en,
                ])
                ->values()
                ->all(),
        ];
    }
}
