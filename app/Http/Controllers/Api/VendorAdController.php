<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Ad;
use App\Models\User;
use App\Models\VendorAdSubscription;
use App\Services\AdValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VendorAdController extends Controller
{
    public function active(): JsonResponse
    {
        $ads = Ad::query()
            ->select([
                'id',
                'vendor_id',
                'media_type',
                'media_path',
                'click_action',
                'action_id',
                'starts_at',
                'ends_at',
            ])
            ->latest('id')
            ->get();

        return response()->json([
            'message' => 'تم جلب الإعلانات الفعالة بنجاح.',
            'count' => $ads->count(),
            'ads' => $ads,
        ]);
    }

    public function store(Request $request, AdValidationService $adValidationService): JsonResponse
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'غير مصرح لك بإنشاء إعلان.'], 403);
        }

        if ($user->role === 'admin') {
            return $this->createAsAdmin($request);
        }

        if ($user->role !== 'vendor' || ! $user->is_seller) {
            return response()->json(['message' => 'فقط أصحاب المتاجر يمكنهم إنشاء إعلان.'], 403);
        }

        $data = $request->validate([
            'media_type' => 'nullable|in:image,video|required_unless:click_action,promotion',
            'media_url' => 'nullable|url|max:2048',
            'click_action' => 'required|in:store,product,promotion',
            'action_id' => 'required|integer',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
        ]);

        if (! empty($data['media_url'])) {
            $data['media_path'] = $data['media_url'];
        }

        $ad = $adValidationService->createVendorAd($user, $data);

        return response()->json([
            'message' => 'تم إنشاء الإعلان بنجاح.',
            'ad' => Ad::withoutGlobalScopes()->find($ad->id),
        ], 201);
    }

    private function createAsAdmin(Request $request): JsonResponse
    {
        $data = $request->validate([
            'vendor_id' => 'nullable|integer|exists:users,id',
            'vendor_ad_subscription_id' => 'nullable|integer|exists:vendor_ad_subscriptions,id',
            'media_type' => 'required|in:image,video',
            'media_url' => 'required|url|max:2048',
            'click_action' => 'required|in:store,product,promotion,url',
            'action_id' => 'nullable|string|max:2048',
            'starts_at' => 'nullable|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'is_active' => 'nullable|boolean',
        ]);

        $data['media_path'] = $data['media_url'];

        if (isset($data['vendor_id'])) {
            $vendor = User::query()->find($data['vendor_id']);
            if (! $vendor || ! $vendor->is_seller) {
                return response()->json(['message' => 'المستخدم المحدد ليس صاحب متجر.'], 422);
            }
        }

        if (isset($data['vendor_ad_subscription_id'])) {
            $subscription = VendorAdSubscription::query()->find($data['vendor_ad_subscription_id']);

            if (! $subscription) {
                return response()->json(['message' => 'الاشتراك المحدد غير موجود.'], 422);
            }

            if (isset($data['vendor_id']) && (int) $subscription->vendor_id !== (int) $data['vendor_id']) {
                return response()->json(['message' => 'الاشتراك لا يتبع البائع المحدد.'], 422);
            }
        }

        $ad = Ad::withoutGlobalScopes()->create([
            'vendor_id' => $data['vendor_id'] ?? null,
            'vendor_ad_subscription_id' => $data['vendor_ad_subscription_id'] ?? null,
            'media_type' => $data['media_type'],
            'media_path' => $data['media_path'],
            'click_action' => $data['click_action'],
            'action_id' => $data['action_id'] ?? null,
            'starts_at' => $data['starts_at'] ?? now(),
            'ends_at' => $data['ends_at'] ?? null,
            'is_active' => $data['is_active'] ?? true,
        ]);

        return response()->json([
            'message' => 'تم إنشاء الإعلان بنجاح بواسطة الأدمن.',
            'ad' => $ad,
        ], 201);
    }
}
