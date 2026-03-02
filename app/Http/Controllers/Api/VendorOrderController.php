<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorOrderController extends Controller
{
    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user || $user->role !== 'vendor') {
            return response()->json(['message' => 'غير مصرح لك بتحديث الطلبات.'], 403);
        }

        $data = $request->validate([
            'status' => 'required|in:processing,delivered',
        ]);

        $order = Order::query()
            ->whereKey($id)
            ->where('vendor_id', $user->id)
            ->first();

        if (! $order) {
            return response()->json(['message' => 'الطلب غير موجود.'], 404);
        }

        $allowedTransitions = [
            Order::STATUS_PENDING => [Order::STATUS_PROCESSING],
            Order::STATUS_PROCESSING => [Order::STATUS_DELIVERED],
        ];

        $targetStatus = $data['status'];
        $currentStatus = $order->status;

        if (! in_array($targetStatus, $allowedTransitions[$currentStatus] ?? [], true)) {
            return response()->json([
                'message' => 'الانتقال المطلوب في حالة الطلب غير مسموح.',
            ], 422);
        }

        $order->update([
            'status' => $targetStatus,
        ]);

        return response()->json([
            'message' => 'تم تحديث حالة الطلب بنجاح.',
            'order' => $order->refresh(),
        ]);
    }
}
