<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ProductVariantController extends Controller
{
    public function store(Request $request, Product $product)
    {
        $user = $request->user();

        $ownsProductStore = $user
            ? $user->stores()->whereKey($product->store_id)->exists()
            : false;

        if (! $ownsProductStore) {
            return response()->json([
                'message' => 'لا يمكنك إضافة منتجات فرعية إلا لمنتجات متجرك أنت.',
            ], 403);
        }

        $data = $request->validate([
            'variants' => 'required|array|min:1',
            'variants.*.attribute_value_ids' => 'required|array|min:1',
            'variants.*.attribute_value_ids.*' => 'required|integer|exists:attribute_values,id',
            'variants.*.price' => 'nullable|numeric|min:0',
            'variants.*.stock' => 'nullable|integer|min:0',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.image' => 'nullable|url|max:2048',
        ]);

        $created = DB::transaction(function () use ($data, $product) {
            $createdVariants = [];

            foreach ($data['variants'] as $variantInput) {
                $valueIds = collect($variantInput['attribute_value_ids'])->unique()->values();

                $validCount = $product->attributeValues()
                    ->whereIn('id', $valueIds)
                    ->count();

                if ($validCount !== $valueIds->count()) {
                    abort(422, 'بعض قيم الخصائص لا تنتمي لهذا المنتج.');
                }

                $valueLabels = $product->attributeValues()
                    ->whereIn('id', $valueIds)
                    ->pluck('value_ar')
                    ->values()
                    ->all();

                $sku = $variantInput['sku'] ?? $this->generateSku($product->name_en ?: $product->name_ar, $valueLabels);

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->ensureUniqueSku($sku),
                    'price' => $variantInput['price'] ?? $product->base_price,
                    'stock' => $variantInput['stock'] ?? 0,
                    'image' => $variantInput['image'] ?? null,
                ]);

                $variant->attributeValues()->sync($valueIds->all());
                $createdVariants[] = $variant->load('attributeValues.attribute');
            }

            $firstCreatedVariant = $createdVariants[0] ?? null;

            if ($firstCreatedVariant) {
                $product->update([
                    'base_price' => (float) $firstCreatedVariant->price,
                    'stock' => (int) $product->variants()->sum('stock'),
                ]);
            }

            return $createdVariants;
        });

        return response()->json([
            'message' => 'تم حفظ النسخ الفرعية بنجاح',
            'variants' => $created,
            'product' => $product->fresh(['variants']),
        ], 201);
    }

    private function generateSku(string $productName, array $valueAr): string
    {
        $parts = [Str::upper(Str::slug($productName, '-'))];

        foreach ($valueAr as $value) {
            $parts[] = Str::upper(Str::slug($value, '-'));
        }

        return trim(implode('-', array_filter($parts)), '-');
    }

    private function ensureUniqueSku(string $sku): string
    {
        $candidate = $sku;
        $counter = 1;

        while (ProductVariant::where('sku', $candidate)->exists()) {
            $candidate = $sku . '-' . $counter;
            $counter++;
        }

        return $candidate;
    }
}
