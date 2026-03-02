<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\CartItem;
use App\Models\Order;
use App\Services\InventoryService;
use App\Services\PriceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    public function checkout(Request $request, InventoryService $inventoryService, PriceService $priceService): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'cart_item_id' => 'required|integer|exists:cart_items,id',
            'payment_method' => 'required|in:Kuraimi,Jeeb',
            'transaction_reference' => 'required|string|max:255|unique:orders,transaction_reference',
        ]);

        $order = DB::transaction(function () use ($data, $user, $inventoryService, $priceService) {
            $cartItem = CartItem::query()
                ->whereKey($data['cart_item_id'])
                ->where('user_id', $user->id)
                ->with(['product.store', 'variation'])
                ->lockForUpdate()
                ->first();

            if (! $cartItem) {
                throw new HttpResponseException(response()->json(['message' => 'عنصر السلة غير موجود.'], 404));
            }

            $product = $cartItem->product;
            $variation = $cartItem->variation;

            if (! $product || ! $product->store) {
                throw new HttpResponseException(response()->json(['message' => 'بيانات المنتج غير مكتملة.'], 422));
            }

            if (! $inventoryService->checkAvailability($product, $variation, (int) $cartItem->quantity, true)) {
                throw new HttpResponseException(response()->json(['message' => 'الكمية المطلوبة غير متوفرة في المخزون.'], 422));
            }

            $unitPrice = (float) (
                $variation
                    ? $priceService->resolveFinalPriceForVariant($product, $variation)
                    : $priceService->resolveFinalPrice($product)
            );
            $quantity = (int) $cartItem->quantity;
            $totalPrice = number_format($unitPrice * $quantity, 2, '.', '');

            $order = Order::query()->create([
                'order_number' => $this->generateOrderNumber(),
                'user_id' => $user->id,
                'vendor_id' => $product->store->user_id,
                'product_id' => $product->id,
                'product_variation_id' => $variation?->id,
                'quantity' => $quantity,
                'unit_price' => number_format($unitPrice, 2, '.', ''),
                'total_price' => $totalPrice,
                'payment_method' => $data['payment_method'],
                'transaction_reference' => $data['transaction_reference'],
                'payment_status' => Order::PAYMENT_PENDING,
                'status' => Order::STATUS_PENDING,
            ]);

            $cartItem->delete();

            return $order;
        });

        return response()->json([
            'message' => 'تم إنشاء الطلب بنجاح وبانتظار التحقق من الدفع.',
            'order' => $order->load(['product', 'variation', 'vendor']),
        ], 201);
    }

    private function generateOrderNumber(): string
    {
        do {
            $orderNumber = 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
        } while (Order::query()->where('order_number', $orderNumber)->exists());

        return $orderNumber;
    }
}
