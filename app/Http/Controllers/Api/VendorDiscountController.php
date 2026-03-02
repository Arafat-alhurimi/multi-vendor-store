<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDiscount;
use App\Models\Promotion;
use App\Models\Store;
use App\Models\Subcategory;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorDiscountController extends Controller
{
    public function upsert(Request $request): JsonResponse
    {
        $store = $this->resolveVendorStore($request);
        if (! $store) {
            return response()->json(['message' => 'غير مصرح لك بإدارة الخصومات.'], 403);
        }

        $data = $request->validate([
            'product_id' => 'required|integer|exists:products,id',
            'type' => 'required|in:percentage,fixed',
            'value' => 'required|numeric|min:0',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);

        $product = Product::query()
            ->whereKey($data['product_id'])
            ->where('store_id', $store->id)
            ->first();

        if (! $product) {
            return response()->json(['message' => 'لا يمكنك إدارة خصم هذا المنتج.'], 403);
        }

        $hasActivePromotion = Promotion::query()
            ->currentlyActive(now())
            ->whereHas('items', function ($query) use ($product): void {
                $query
                    ->approved()
                    ->where(function ($matchQuery) use ($product): void {
                        $categoryId = $product->subcategory?->category_id;
                        $subcategoryId = $product->subcategory_id;

                        $matchQuery
                            ->where(function ($innerMatch) use ($product): void {
                                $innerMatch
                                    ->where('promotable_type', Product::class)
                                    ->where('promotable_id', $product->id)
                                    ->where(function ($storeContext) use ($product): void {
                                        $storeContext->whereNull('store_id')->orWhere('store_id', $product->store_id);
                                    });
                            })
                            ->orWhere(function ($innerMatch) use ($product): void {
                                $innerMatch
                                    ->where('promotable_type', Store::class)
                                    ->where('promotable_id', $product->store_id);
                            });

                        if ($subcategoryId) {
                            $matchQuery->orWhere(function ($innerMatch) use ($subcategoryId, $product): void {
                                $innerMatch
                                    ->where('promotable_type', Subcategory::class)
                                    ->where('promotable_id', $subcategoryId)
                                    ->where(function ($storeContext) use ($product): void {
                                        $storeContext->whereNull('store_id')->orWhere('store_id', $product->store_id);
                                    });
                            });
                        }

                        if ($categoryId) {
                            $matchQuery->orWhere(function ($innerMatch) use ($categoryId, $product): void {
                                $innerMatch
                                    ->where('promotable_type', Category::class)
                                    ->where('promotable_id', $categoryId)
                                    ->where(function ($storeContext) use ($product): void {
                                        $storeContext->whereNull('store_id')->orWhere('store_id', $product->store_id);
                                    });
                            });
                        }
                    });
            })
            ->exists();

        if ($hasActivePromotion) {
            return response()->json([
                'message' => 'لا يمكن إنشاء خصم مباشر لأن المنتج منضم حاليًا إلى عرض نشط.',
            ], 422);
        }

        $discount = ProductDiscount::query()->updateOrCreate(
            ['product_id' => $product->id],
            [
                'type' => $data['type'],
                'value' => $data['value'],
                'starts_at' => $data['starts_at'] ?? null,
                'ends_at' => $data['ends_at'] ?? null,
                'is_active' => (bool) ($data['is_active'] ?? true),
            ]
        );

        $product->load(['productDiscount', 'store', 'subcategory.category']);
        $finalPrice = app(PriceService::class)->resolveFinalPrice($product);

        return response()->json([
            'message' => 'تم حفظ خصم المنتج بنجاح.',
            'discount' => $discount,
            'final_price' => $finalPrice,
        ]);
    }

    private function resolveVendorStore(Request $request): ?Store
    {
        $user = $request->user();

        if (! $user || $user->role !== 'vendor') {
            return null;
        }

        return $user->stores()->first();
    }
}
