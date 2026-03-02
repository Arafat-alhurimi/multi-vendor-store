<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\PriceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ProductController extends Controller
{
    public function show(Request $request, Product $product, PriceService $priceService)
    {
        $product->load([
            'store:id,user_id,name,logo',
            'subcategory:id,category_id,name_ar,name_en',
            'media:id,mediable_id,mediable_type,file_type,url',
            'variants:id,product_id,sku,price,stock,image',
            'variants.attributeValues:id,attribute_id,product_id,value_ar,value_en',
            'variants.attributeValues.attribute:id,name_ar,name_en',
            'attributeValues:id,attribute_id,product_id,value_ar,value_en',
            'attributeValues.attribute:id,name_ar,name_en',
        ]);

        $averageRating = round((float) $product->ratings()->avg('value'), 2);
        $ratingsCount = (int) $product->ratings()->count();

        $commentsPerPage = min(max((int) $request->query('comments_per_page', 20), 1), 100);
        $comments = $product->comments()
            ->with([
                'user:id,name,avatar',
                'replies.user:id,name,avatar',
            ])
            ->latest()
            ->paginate($commentsPerPage);

        $basePrice = (float) $product->base_price;
        $finalPrice = (float) $priceService->resolveFinalPrice($product);

        $groupedAttributes = $product->attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values) {
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

        $variants = $product->variants->map(function ($variant) use ($product, $priceService) {
            $variantOriginalPrice = (float) ($variant->price ?? $product->base_price);
            $variantFinalPrice = (float) $priceService->resolveFinalPriceForVariant($product, $variant);

            return [
                'id' => $variant->id,
                'sku' => $variant->sku,
                'price' => number_format($variantFinalPrice, 2, '.', ''),
                'price_original' => number_format($variantOriginalPrice, 2, '.', ''),
                'price_final' => number_format($variantFinalPrice, 2, '.', ''),
                'has_discount_or_promotion' => $variantFinalPrice < $variantOriginalPrice,
                'discount_amount' => number_format(max($variantOriginalPrice - $variantFinalPrice, 0), 2, '.', ''),
                'stock' => (int) $variant->stock,
                'image' => $variant->image,
                'attributes' => $variant->attributeValues
                    ->map(function ($attributeValue) {
                        return [
                            'attribute_id' => $attributeValue->attribute?->id,
                            'attribute_name_ar' => $attributeValue->attribute?->name_ar,
                            'attribute_name_en' => $attributeValue->attribute?->name_en,
                            'value_id' => $attributeValue->id,
                            'value_ar' => $attributeValue->value_ar,
                            'value_en' => $attributeValue->value_en,
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name_ar' => $product->name_ar,
                'name_en' => $product->name_en,
                'description_ar' => $product->description_ar,
                'description_en' => $product->description_en,
                'stock' => (int) $product->stock,
                'is_active' => (bool) $product->is_active,
                'store' => $product->store,
                'subcategory' => $product->subcategory,
                'media' => $product->media,
                'pricing' => [
                    'base_price' => number_format($basePrice, 2, '.', ''),
                    'final_price' => number_format($finalPrice, 2, '.', ''),
                    'has_discount_or_promotion' => $finalPrice < $basePrice,
                    'discount_amount' => number_format(max($basePrice - $finalPrice, 0), 2, '.', ''),
                ],
                'rating' => [
                    'average' => $averageRating,
                    'count' => $ratingsCount,
                ],
                'has_variants' => count($variants) > 0,
                'selection_mode' => count($variants) > 0
                    ? 'variant_or_attribute_by_attribute'
                    : 'single_product',
                'variants' => $variants,
                'attributes' => $groupedAttributes,
            ],
            'comments' => $comments,
        ]);
    }

    public function store(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'subcategory_id' => 'required|exists:subcategories,id',
            'name_ar' => 'required|string|max:255',
            'name_en' => 'required|string|max:255',
            'description_ar' => 'nullable|string',
            'description_en' => 'nullable|string',
            'base_price' => 'required|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'media' => 'nullable|array',
            'media.*.url' => 'required_with:media|url',
            'media.*.file_type' => 'nullable|in:image,video',
            'media.*.file_name' => 'nullable|string|max:255',
            'media.*.mime_type' => 'nullable|string|max:255',
        ]);

        $store = $user?->stores()->first();

        if (! $store) {
            return response()->json(['message' => 'لا يوجد متجر مرتبط بهذا الحساب.'], 403);
        }

        $data['store_id'] = $store->id;

        $product = DB::transaction(function () use ($data) {
            $mediaItems = $data['media'] ?? [];
            unset($data['media']);

            $product = Product::create($data);

            foreach ($mediaItems as $mediaItem) {
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
                    $fileType = str_contains($mediaItem['url'], '/videos/') ? 'video' : 'image';
                }

                $product->media()->create([
                    'file_name' => $mediaItem['file_name']
                        ?? basename(parse_url($mediaItem['url'], PHP_URL_PATH) ?: 'media-file'),
                    'file_type' => $fileType,
                    'mime_type' => $mimeType,
                    'url' => $mediaItem['url'],
                ]);
            }

            return $product;
        });

        return response()->json([
            'message' => 'تم إنشاء المنتج بنجاح',
            'product' => $product->load(['store', 'subcategory', 'variants', 'media', 'attributeValues.attribute']),
        ], 201);
    }
}
