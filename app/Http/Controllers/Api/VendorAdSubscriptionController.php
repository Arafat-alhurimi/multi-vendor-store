<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use App\Models\VendorAdSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAdSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        if (! $user || $user->role !== 'vendor' || ! $user->is_seller) {
            return response()->json(['message' => 'غير مصرح لك بطلب اشتراك إعلاني.'], 403);
        }

        $data = $request->validate([
            'ad_package_id' => 'required|integer|exists:ad_packages,id',
        ]);

        $package = AdPackage::query()->where('is_active', true)->find($data['ad_package_id']);
        if (! $package) {
            return response()->json(['message' => 'الباقة غير متاحة حالياً.'], 422);
        }

        $subscription = VendorAdSubscription::query()->create([
            'vendor_id' => $user->id,
            'ad_package_id' => $package->id,
            'status' => 'pending',
            'used_images' => 0,
            'used_videos' => 0,
            'used_promotions' => 0,
        ]);

        return response()->json([
            'message' => 'تم إرسال طلب الاشتراك بنجاح وبانتظار الموافقة.',
            'subscription' => $subscription,
        ], 201);
    }
}
