<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CartController extends Controller
{
    public function index(Request $request, PriceService $priceService): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'غير مصرح. يرجى تسجيل الدخول أولاً.',
            ], 401);
        }

        $cartItems = CartItem::query()
            ->where('user_id', $user->id)
            ->with([
                'product:id,store_id,subcategory_id,name_ar,name_en,base_price,stock,is_active',
                'product.media:id,mediable_id,mediable_type,file_type,url',
                'variation:id,product_id,sku,price,stock,image',
                'variation.attributeValues:id,attribute_id,product_id,value_ar,value_en',
                'variation.attributeValues.attribute:id,name_ar,name_en',
            ])
            ->get();

        $items = $cartItems->map(function (CartItem $item) use ($priceService): array {
            $product = $item->product;
            $variant = $item->variation;

            $lockedUnitPrice = (float) $item->price_at_add;
            $currentUnitPrice = (float) (
                $variant
                    ? $priceService->resolveFinalPriceForVariant($product, $variant)
                    : $priceService->resolveFinalPrice($product)
            );

            $quantity = (int) $item->quantity;
            $lineTotalLocked = $lockedUnitPrice * $quantity;
            $lineTotalCurrent = $currentUnitPrice * $quantity;

            return [
                'id' => $item->id,
                'quantity' => $quantity,
                'unit_price_locked' => number_format($lockedUnitPrice, 2, '.', ''),
                'unit_price_current' => number_format($currentUnitPrice, 2, '.', ''),
                'line_total_locked' => number_format($lineTotalLocked, 2, '.', ''),
                'line_total_current' => number_format($lineTotalCurrent, 2, '.', ''),
                'product' => [
                    'id' => $product->id,
                    'name_ar' => $product->name_ar,
                    'name_en' => $product->name_en,
                    'base_price' => number_format((float) $product->base_price, 2, '.', ''),
                    'stock' => (int) $product->stock,
                    'is_active' => (bool) $product->is_active,
                    'media' => $product->media,
                ],
                'variation' => $variant ? [
                    'id' => $variant->id,
                    'sku' => $variant->sku,
                    'price' => number_format((float) ($variant->price ?? 0), 2, '.', ''),
                    'stock' => (int) $variant->stock,
                    'image' => $variant->image,
                    'attributes' => $variant->attributeValues
                        ->map(fn ($value) => [
                            'attribute_id' => (int) $value->attribute_id,
                            'attribute_name_ar' => $value->attribute?->name_ar,
                            'attribute_name_en' => $value->attribute?->name_en,
                            'value_id' => (int) $value->id,
                            'value_ar' => $value->value_ar,
                            'value_en' => $value->value_en,
                        ])
                        ->values()
                        ->all(),
                ] : null,
            ];
        })->values();

        $totalLocked = $items->sum(fn (array $item) => (float) $item['line_total_locked']);
        $totalCurrent = $items->sum(fn (array $item) => (float) $item['line_total_current']);

        return response()->json([
            'items_count' => $items->count(),
            'items' => $items,
            'summary' => [
                'total_locked' => number_format($totalLocked, 2, '.', ''),
                'total_current' => number_format($totalCurrent, 2, '.', ''),
            ],
        ]);
    }

    public function add(Request $request, InventoryService $inventoryService, PriceService $priceService): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'غير مصرح. يرجى تسجيل الدخول أولاً.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'product_id' => 'required|integer|exists:products,id',
            'product_variation_id' => 'nullable|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'البيانات المدخلة غير صحيحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $product = Product::query()->with('store')->findOrFail($data['product_id']);
        $productHasVariants = $product->variants()->exists();
        $variant = null;

        if ($productHasVariants && empty($data['product_variation_id'])) {
            return response()->json([
                'message' => 'يجب تحديد المنتج الفرعي قبل الإضافة إلى السلة.',
            ], 422);
        }

        if (! $productHasVariants && ! empty($data['product_variation_id'])) {
            return response()->json([
                'message' => 'هذا المنتج لا يحتوي على منتجات فرعية.',
            ], 422);
        }

        if (! empty($data['product_variation_id'])) {
            $variant = ProductVariant::query()
                ->whereKey($data['product_variation_id'])
                ->where('product_id', $product->id)
                ->first();

            if (! $variant) {
                return response()->json(['message' => 'النسخة المختارة لا تنتمي لهذا المنتج.'], 422);
            }
        }

        $cartItem = DB::transaction(function () use ($user, $product, $variant, $data, $inventoryService, $priceService) {
            $existingCartItem = CartItem::query()
                ->where('user_id', $user->id)
                ->where('product_id', $product->id)
                ->where('product_variation_id', $variant?->id)
                ->lockForUpdate()
                ->first();

            $requestedQuantity = (int) $data['quantity'] + (int) ($existingCartItem?->quantity ?? 0);

            if (! $inventoryService->checkAvailability($product, $variant, $requestedQuantity)) {
                return null;
            }

            $unitPrice = $variant
                ? $priceService->resolveFinalPriceForVariant($product, $variant)
                : $priceService->resolveFinalPrice($product);

            if ($existingCartItem) {
                $existingCartItem->update([
                    'quantity' => $requestedQuantity,
                    'price_at_add' => $unitPrice,
                ]);

                return $existingCartItem->refresh()->load(['product', 'variation']);
            }

            return CartItem::query()->create([
                'user_id' => $user->id,
                'product_id' => $product->id,
                'product_variation_id' => $variant?->id,
                'quantity' => (int) $data['quantity'],
                'price_at_add' => $unitPrice,
            ])->load(['product', 'variation']);
        });

        if (! $cartItem) {
            return response()->json(['message' => 'الكمية المطلوبة غير متوفرة في المخزون.'], 422);
        }

        return response()->json([
            'message' => 'تمت إضافة المنتج إلى السلة بنجاح.',
            'cart_item' => $cartItem,
        ], 201);
    }

    public function remove(Request $request, int $id): JsonResponse
    {
        $cartItem = CartItem::query()
            ->whereKey($id)
            ->where('user_id', $request->user()->id)
            ->first();

        if (! $cartItem) {
            return response()->json(['message' => 'عنصر السلة غير موجود.'], 404);
        }

        $cartItem->delete();

        return response()->json([
            'message' => 'تم حذف العنصر من السلة بنجاح.',
        ]);
    }

    public function clear(Request $request): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'غير مصرح. يرجى تسجيل الدخول أولاً.',
            ], 401);
        }

        $deletedCount = CartItem::query()
            ->where('user_id', $user->id)
            ->delete();

        return response()->json([
            'message' => 'تم تفريغ السلة بنجاح.',
            'deleted_items_count' => $deletedCount,
        ]);
    }

    public function update(Request $request, int $id, InventoryService $inventoryService, PriceService $priceService): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json([
                'message' => 'غير مصرح. يرجى تسجيل الدخول أولاً.',
            ], 401);
        }

        $validator = Validator::make($request->all(), [
            'quantity' => 'required|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'البيانات المدخلة غير صحيحة.',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();

        $cartItem = DB::transaction(function () use ($id, $user, $data, $inventoryService, $priceService) {
            $item = CartItem::query()
                ->whereKey($id)
                ->where('user_id', $user->id)
                ->with(['product.store', 'variation'])
                ->lockForUpdate()
                ->first();

            if (! $item) {
                return 'NOT_FOUND';
            }

            $product = $item->product;
            $variant = $item->variation;
            $quantity = (int) $data['quantity'];

            if ($quantity === 0) {
                $item->delete();

                return 'DELETED';
            }

            if (! $product) {
                return 'PRODUCT_MISSING';
            }

            if (! $inventoryService->checkAvailability($product, $variant, $quantity)) {
                return 'OUT_OF_STOCK';
            }

            $unitPrice = $variant
                ? $priceService->resolveFinalPriceForVariant($product, $variant)
                : $priceService->resolveFinalPrice($product);

            $item->update([
                'quantity' => $quantity,
                'price_at_add' => $unitPrice,
            ]);

            return $item->refresh()->load(['product', 'variation']);
        });

        if ($cartItem === 'NOT_FOUND') {
            return response()->json(['message' => 'عنصر السلة غير موجود.'], 404);
        }

        if ($cartItem === 'PRODUCT_MISSING') {
            return response()->json(['message' => 'بيانات المنتج غير مكتملة.'], 422);
        }

        if ($cartItem === 'OUT_OF_STOCK') {
            return response()->json(['message' => 'الكمية المطلوبة غير متوفرة في المخزون.'], 422);
        }

        if ($cartItem === 'DELETED') {
            return response()->json([
                'message' => 'تم حذف العنصر من السلة لأن الكمية أصبحت صفر.',
            ]);
        }

        return response()->json([
            'message' => 'تم تحديث كمية العنصر في السلة بنجاح.',
            'cart_item' => $cartItem,
        ]);
    }
}
