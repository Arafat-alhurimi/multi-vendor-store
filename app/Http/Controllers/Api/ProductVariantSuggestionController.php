<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use Illuminate\Support\Str;

class ProductVariantSuggestionController extends Controller
{
    public function suggest(\Illuminate\Http\Request $request, Product $product)
    {
        $lang = $request->query('lang', 'ar');
        $lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';

        $user = $request->user();

        $ownsProductStore = $user
            ? $user->stores()->whereKey($product->store_id)->exists()
            : false;

        if (! $ownsProductStore) {
            return response()->json([
                'message' => 'لا يمكنك عرض المقترحات إلا لمنتجات متجرك أنت.',
            ], 403);
        }

        $grouped = $product->attributeValues()
            ->with('attribute')
            ->get()
            ->groupBy('attribute_id');

        if ($grouped->isEmpty()) {
            return response()->json([
                'product_id' => $product->id,
                'lang' => $lang,
                'suggestions' => [],
            ]);
        }

        $valueSets = $grouped
            ->values()
            ->map(fn ($collection) => $collection->values()->all())
            ->all();

        $combinations = $this->cartesianProduct($valueSets);

        $suggestions = collect($combinations)->map(function (array $combination) use ($product, $lang) {
            $valueIds = array_map(fn ($value) => $value->id, $combination);
            $valueAr = array_map(fn ($value) => $value->value_ar, $combination);

            $attributeNameKey = $lang === 'en' ? 'name_en' : 'name_ar';
            $valueKey = $lang === 'en' ? 'value_en' : 'value_ar';

            $localizedProductName = $lang === 'en'
                ? ($product->name_en ?: $product->name_ar)
                : ($product->name_ar ?: $product->name_en);

            $localizedValues = array_map(
                fn ($value) => $value->{$valueKey} ?: ($lang === 'en' ? $value->value_ar : $value->value_en),
                $combination,
            );

            $suggestedName = trim($localizedProductName . ' - ' . implode(' - ', array_filter($localizedValues)), ' -');

            return [
                'attribute_value_ids' => $valueIds,
                'attribute_values' => collect($combination)->map(fn ($value) => [
                    'id' => $value->id,
                    'attribute_id' => $value->attribute_id,
                    'attribute_name' => $value->attribute?->{$attributeNameKey},
                    'value' => $value->{$valueKey},
                ])->values()->all(),
                'suggested_name' => $suggestedName,
                'suggested_sku' => $this->generateSku($product->name_en ?: $product->name_ar, $valueAr),
            ];
        })->values();

        return response()->json([
            'product_id' => $product->id,
            'lang' => $lang,
            'suggestions' => $suggestions,
        ]);
    }

    private function cartesianProduct(array $sets): array
    {
        if (! count($sets)) {
            return [];
        }

        $result = [[]];

        foreach ($sets as $set) {
            $append = [];

            foreach ($result as $current) {
                foreach ($set as $item) {
                    $new = $current;
                    $new[] = $item;
                    $append[] = $new;
                }
            }

            $result = $append;
        }

        return $result;
    }

    private function generateSku(string $productName, array $valueAr): string
    {
        $parts = [Str::upper(Str::slug($productName, '-'))];

        foreach ($valueAr as $value) {
            $parts[] = Str::upper(Str::slug($value, '-'));
        }

        return trim(implode('-', array_filter($parts)), '-');
    }
}
