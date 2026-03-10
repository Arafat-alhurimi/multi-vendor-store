<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\AdSubscriptionResource;
use App\Http\Controllers\Controller;
use App\Models\AdPackage;
use App\Models\User;
use App\Models\VendorAdSubscription;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
            'request_type' => 'new',
            'used_images' => 0,
            'used_videos' => 0,
            'used_promotions' => 0,
        ]);

        $this->notifyAdminsAboutSubscriptionRequest($subscription, 'new');

        return response()->json([
            'message' => 'تم إرسال طلب الاشتراك بنجاح وبانتظار الموافقة.',
            'subscription' => $subscription,
        ], 201);
    }

    public function requestRenewal(Request $request, int $id): JsonResponse
    {
        $user = $request->user();

        if (! $user || $user->role !== 'vendor' || ! $user->is_seller) {
            return response()->json(['message' => 'غير مصرح لك بطلب تجديد الاشتراك الإعلاني.'], 403);
        }

        $subscription = VendorAdSubscription::query()
            ->whereKey($id)
            ->where('vendor_id', $user->id)
            ->with('adPackage')
            ->first();

        if (! $subscription) {
            return response()->json(['message' => 'الاشتراك غير موجود.'], 404);
        }

        if ($subscription->status === 'pending') {
            return response()->json(['message' => 'يوجد طلب قيد المراجعة بالفعل لهذا الاشتراك.'], 422);
        }

        if ($subscription->status === 'active') {
            return response()->json(['message' => 'الاشتراك نشط حالياً ولا يحتاج تجديد الآن.'], 422);
        }

        if (! $subscription->adPackage || ! $subscription->adPackage->is_active) {
            return response()->json(['message' => 'لا يمكن تجديد الاشتراك لأن الباقة غير متاحة حالياً.'], 422);
        }

        $subscription->forceFill([
            'status' => 'pending',
            'request_type' => 'renewal',
            'created_at' => now(),
        ])->save();

        $this->notifyAdminsAboutSubscriptionRequest($subscription, 'renewal');

        return response()->json([
            'message' => 'تم إرسال طلب تجديد الاشتراك بنجاح وبانتظار الموافقة.',
            'subscription' => $subscription->fresh(),
        ]);
    }

    private function notifyAdminsAboutSubscriptionRequest(VendorAdSubscription $subscription, string $requestType): void
    {
        $admins = User::query()->where('role', 'admin')->get();
        $targetUrl = AdSubscriptionResource::getUrl('index');
        $storeName = $subscription->vendor?->stores?->first()?->name;

        $title = $requestType === 'renewal' ? 'طلب تجديد اشتراك إعلاني' : 'طلب اشتراك إعلاني جديد';
        $body = $requestType === 'renewal'
            ? 'تم استلام طلب تجديد اشتراك' . ($storeName ? " من متجر {$storeName}." : '.')
            : 'تم استلام طلب اشتراك جديد' . ($storeName ? " من متجر {$storeName}." : '.');

        foreach ($admins as $admin) {
            $notification = Notification::make()
                ->title($title)
                ->body($body)
                ->warning()
                ->actions([
                    Action::make('openSubscriptions')
                        ->label('فتح طلبات الاشتراكات')
                        ->url($targetUrl),
                ]);

            $payload = $notification->toArray();
            unset($payload['id']);
            $payload['format'] = 'filament';
            $payload['duration'] = 'persistent';
            $payload['notification_category'] = 'subscription';
            $payload['target_url'] = $targetUrl;
            $payload['subscription_id'] = $subscription->id;
            $payload['request_type'] = $requestType;

            DB::table('filament_notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\VendorSubscriptionRequested',
                'notifiable_type' => get_class($admin),
                'notifiable_id' => $admin->getKey(),
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
