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
        // تحديد اللغة من الطلب أو افتراضية عربية
        $lang = $request->query('lang', 'ar');
        $lang = in_array($lang, ['ar', 'en'], true) ? $lang : 'ar';

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

        // دالة مساعدة للتعريب
        $localize = function ($ar, $en) use ($lang) {
            return $lang === 'en' ? ($en ?: $ar) : ($ar ?: $en);
        };

        $groupedAttributes = $product->attributeValues
            ->groupBy('attribute_id')
            ->map(function ($values) use ($lang, $localize) {
                $attribute = $values->first()?->attribute;
                return [
                    'attribute_id' => $attribute?->id,
                    'name' => $localize($attribute?->name_ar, $attribute?->name_en),
                    'values' => $values
                        ->unique('id')
                        ->values()
                        ->map(fn ($value) => [
                            'id' => $value->id,
                            'value' => $localize($value->value_ar, $value->value_en),
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
                'has_offer' => $variantFinalPrice < $variantOriginalPrice,
                'stock' => (int) $variant->stock,
                'image' => $variant->image,
                // فقط عرض attribute_id و value_id
                'attribute_values' => $variant->attributeValues
                    ->map(function ($attributeValue) {
                        return [
                            'attribute_id' => $attributeValue->attribute?->id,
                            'value_id' => $attributeValue->id,
                        ];
                    })
                    ->values()
                    ->all(),
            ];
        })->values()->all();

        return response()->json([
            'product' => [
                'id' => $product->id,
                'name' => $localize($product->name_ar, $product->name_en),
                'description' => $localize($product->description_ar, $product->description_en),
                'stock' => (int) $product->stock,
                'is_active' => (bool) $product->is_active,
                'base_price' => number_format($basePrice, 2, '.', ''),
                'final_price' => number_format($finalPrice, 2, '.', ''),
                'has_offer' => $finalPrice < $basePrice,
                'store' => [
                    'id' => $product->store->id ?? null,
                    'name' => $product->store->name ?? null,
                    'logo' => $product->store->logo ?? null,
                ],
                'subcategory' => $product->subcategory ? [
                    'id' => $product->subcategory->id,
                    'category_id' => $product->subcategory->category_id,
                    'name' => $localize($product->subcategory->name_ar, $product->subcategory->name_en),
                ] : null,
                'media' => $product->media,
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
            'lang' => $lang,
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
