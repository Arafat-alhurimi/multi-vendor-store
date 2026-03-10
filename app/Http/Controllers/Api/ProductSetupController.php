<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ProductSetupController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        $store = $user?->stores()->first();

        if (! $store) {
            return response()->json(['message' => 'لا يوجد متجر مرتبط بهذا الحساب.'], 403);
        }

        $data = $request->validate([
            'subcategory_id' => 'required|integer|exists:subcategories,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'media' => 'nullable|array',
            'media.*.url' => 'required_with:media|url|max:2048',
            'media.*.file_url' => 'nullable|url',
            'media.*.key' => 'nullable|string|max:2048',
            'media.*.file_type' => 'nullable|in:image,video',
            'media.*.file_name' => 'nullable|string|max:255',
            'media.*.mime_type' => 'nullable|string|max:255',
            'attributes' => 'nullable|array',
            'attributes.*.name_ar' => 'required_with:attributes|string|max:255',
            'attributes.*.name_en' => 'required_with:attributes|string|max:255',
            'attributes.*.values' => 'required_with:attributes|array|min:1',
            'attributes.*.values.*.value_ar' => 'required|string|max:255',
            'attributes.*.values.*.value_en' => 'required|string|max:255',
            'lang' => 'nullable|in:ar,en',
        ]);

        $lang = $data['lang'] ?? 'ar';

        $result = DB::transaction(function () use ($data, $store, $lang): array {
            $mediaItems = $data['media'] ?? [];
            $attributesInput = $data['attributes'] ?? [];

            unset($data['media'], $data['attributes'], $data['lang']);

            $data['store_id'] = $store->id;

            $product = Product::query()->create($data);

            foreach ($mediaItems as $mediaItem) {
                $resolvedUrl = null;

                if (! empty($mediaItem['url'])) {
                    $resolvedUrl = $mediaItem['url'];
                } elseif (! empty($mediaItem['file_url'])) {
                    $resolvedUrl = $mediaItem['file_url'];
                } elseif (! empty($mediaItem['key'])) {
                    /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
                    $disk = Storage::disk('s3');
                    $resolvedUrl = $disk->url($mediaItem['key']);
                }

                if (! $resolvedUrl) {
                    abort(422, 'يجب تزويد رابط الوسيط عبر file_url أو url أو key من presign.');
                }

                $mimeType = $mediaItem['mime_type'] ?? null;
                $fileType = $mediaItem['file_type'] ?? null;

                if (! $fileType && $mimeType) {
                    if (str_starts_with($mimeType, 'image/')) {
                        $fileType = 'image';
                    } elseif (str_starts_with($mimeType, 'video/')) {
                        $fileType = 'video';
                    }
                }

                if (! $fileType) {
                    $fileType = str_contains($resolvedUrl, '/videos/') ? 'video' : 'image';
                }

                $product->media()->create([
                    'file_name' => $mediaItem['file_name']
                        ?? basename(parse_url($resolvedUrl, PHP_URL_PATH) ?: 'media-file'),
                    'file_type' => $fileType,
                    'mime_type' => $mimeType,
                    'url' => $resolvedUrl,
                ]);
            }

            if (! empty($attributesInput)) {
                foreach ($attributesInput as $attributeInput) {
                    $attribute = Attribute::query()->firstOrCreate([
                        'name_ar' => $attributeInput['name_ar'],
                        'name_en' => $attributeInput['name_en'],
                    ]);

                    foreach ($attributeInput['values'] as $valueInput) {
                        $attribute->values()->firstOrCreate([
                            'product_id' => $product->id,
                            'value_ar' => $valueInput['value_ar'],
                            'value_en' => $valueInput['value_en'],
                        ]);
                    }
                }
            }

            $product->load([
                'store:id,user_id,name,logo',
                'subcategory:id,category_id,name_ar,name_en',
                'media:id,mediable_id,mediable_type,file_type,url',
                'attributeValues:id,attribute_id,product_id,value_ar,value_en',
                'attributeValues.attribute:id,name_ar,name_en',
            ]);

            $availableAttributes = $product->attributeValues
                ->groupBy('attribute_id')
                ->map(function (Collection $values) {
                    $attribute = $values->first()?->attribute;

                    return [
                        'attribute_id' => $attribute?->id,
                        'name_ar' => $attribute?->name_ar,
                        'name_en' => $attribute?->name_en,
                        'values' => $values
                            ->unique('id')
                            ->values()
                            ->map(fn ($value) => [
                                'id' => $value->id,
                                'value_ar' => $value->value_ar,
                                'value_en' => $value->value_en,
                            ])
                            ->all(),
                    ];
                })
                ->values()
                ->all();

            $suggestedVariants = $this->buildVariantSuggestions($product, $lang);

            return [
                'product' => $product,
                'available_attributes' => $availableAttributes,
                'suggested_variants' => $suggestedVariants,
            ];
        });

        return response()->json([
            'message' => 'تم إنشاء المنتج مع الوسائط والخصائص بنجاح.',
            'product' => $result['product'],
            'available_attributes' => $result['available_attributes'],
            'suggested_variants' => $result['suggested_variants'],
        ], 201);
    }

    private function buildVariantSuggestions(Product $product, string $lang): array
    {
        $grouped = $product->attributeValues()
            ->with('attribute')
            ->get()
            ->groupBy('attribute_id');

        if ($grouped->isEmpty()) {
            return [];
        }

        $valueSets = $grouped
            ->values()
            ->map(fn (Collection $collection) => $collection->values()->all())
            ->all();

        $combinations = $this->cartesianProduct($valueSets);

        return collect($combinations)
            ->map(function (array $combination) use ($product, $lang): array {
                $attributeNameKey = $lang === 'en' ? 'name_en' : 'name_ar';
                $valueKey = $lang === 'en' ? 'value_en' : 'value_ar';

                $localizedProductName = $lang === 'en'
                    ? ($product->name_en ?: $product->name_ar)
                    : ($product->name_ar ?: $product->name_en);

                $localizedValues = array_map(
                    fn ($value) => $value->{$valueKey} ?: ($lang === 'en' ? $value->value_ar : $value->value_en),
                    $combination,
                );

                $valueArForSku = array_map(fn ($value) => $value->value_ar, $combination);

                return [
                    'attribute_value_ids' => array_map(fn ($value) => $value->id, $combination),
                    'attribute_values' => collect($combination)->map(fn ($value) => [
                        'id' => $value->id,
                        'attribute_id' => $value->attribute_id,
                        'attribute_name' => $value->attribute?->{$attributeNameKey},
                        'value' => $value->{$valueKey},
                    ])->values()->all(),
                    'suggested_name' => trim($localizedProductName.' - '.implode(' - ', array_filter($localizedValues)), ' -'),
                    'suggested_sku' => $this->generateSku($product->name_en ?: $product->name_ar, $valueArForSku),
                    'suggested_price' => number_format((float) $product->base_price, 2, '.', ''),
                    'suggested_stock' => (int) $product->stock,
                ];
            })
            ->values()
            ->all();
    }

    private function cartesianProduct(array $sets): array
    {
        if (empty($sets)) {
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
