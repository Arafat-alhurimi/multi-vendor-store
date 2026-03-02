<?php

namespace App\Services;

use App\Models\Ad;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorAdSubscription;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

class AdValidationService
{
    public function createVendorAd(User $vendor, array $data): Ad
    {
        $now = now();
        $data = $this->normalizeVendorAdPayload($vendor, $data);
        $subscription = $this->resolveActiveSubscription($vendor, $now);

        $this->validatePackageLimits($subscription, $data);
        [$startsAt, $endsAt] = $this->resolveAdDateRange($subscription, $data, $now);

        $ad = Ad::withoutGlobalScopes()->create([
            'vendor_id' => $vendor->id,
            'vendor_ad_subscription_id' => $subscription->id,
            'media_type' => $data['media_type'],
            'media_path' => $data['media_path'],
            'click_action' => $data['click_action'],
            'action_id' => $data['action_id'] ?? null,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'is_active' => true,
        ]);

        $this->incrementCounters($subscription, $data);

        return $ad;
    }

    private function resolveActiveSubscription(User $vendor, CarbonInterface $at): VendorAdSubscription
    {
        $subscription = VendorAdSubscription::query()
            ->with('adPackage')
            ->where('vendor_id', $vendor->id)
            ->active($at)
            ->latest('id')
            ->first();

        if (! $subscription) {
            throw ValidationException::withMessages([
                'subscription' => 'لا يوجد اشتراك إعلاني نشط حالياً.',
            ]);
        }

        if ($subscription->ends_at && $subscription->ends_at->lt($at)) {
            throw ValidationException::withMessages([
                'subscription' => 'انتهت صلاحية الاشتراك الإعلاني.',
            ]);
        }

        return $subscription;
    }

    private function validatePackageLimits(VendorAdSubscription $subscription, array $data): void
    {
        $package = $subscription->adPackage;

        if ($data['click_action'] !== 'promotion' && $data['media_type'] === 'image' && $subscription->used_images >= $package->max_images) {
            throw ValidationException::withMessages([
                'media_type' => 'تم تجاوز الحد الأقصى للصور في الباقة.',
            ]);
        }

        if ($data['click_action'] !== 'promotion' && $data['media_type'] === 'video' && $subscription->used_videos >= $package->max_videos) {
            throw ValidationException::withMessages([
                'media_type' => 'تم تجاوز الحد الأقصى للفيديوهات في الباقة.',
            ]);
        }

        if ($data['click_action'] === 'promotion' && $subscription->used_promotions >= $package->max_promotions) {
            throw ValidationException::withMessages([
                'click_action' => 'تم تجاوز الحد الأقصى لربط الإعلانات بالعروض في الباقة.',
            ]);
        }
    }

    private function incrementCounters(VendorAdSubscription $subscription, array $data): void
    {
        if ($data['click_action'] === 'promotion') {
            $subscription->increment('used_promotions');

            return;
        }

        if ($data['media_type'] === 'image') {
            $subscription->increment('used_images');
        }

        if ($data['media_type'] === 'video') {
            $subscription->increment('used_videos');
        }

    }

    private function normalizeVendorAdPayload(User $vendor, array $data): array
    {
        $targetId = isset($data['action_id']) ? (int) $data['action_id'] : null;

        if (! $targetId) {
            throw ValidationException::withMessages([
                'action_id' => 'يجب إرسال معرف العنصر المستهدف للإعلان.',
            ]);
        }

        $storeIds = $vendor->stores()->pluck('id');

        if ($data['click_action'] === 'store') {
            $storeExists = Store::query()
                ->whereKey($targetId)
                ->where('user_id', $vendor->id)
                ->exists();

            if (! $storeExists) {
                throw ValidationException::withMessages([
                    'action_id' => 'لا يمكنك الإعلان إلا لمتجرك.',
                ]);
            }
        }

        if ($data['click_action'] === 'product') {
            $productExists = Product::query()
                ->whereKey($targetId)
                ->whereIn('store_id', $storeIds)
                ->exists();

            if (! $productExists) {
                throw ValidationException::withMessages([
                    'action_id' => 'لا يمكنك الإعلان إلا لمنتجات متجرك.',
                ]);
            }
        }

        if ($data['click_action'] === 'promotion') {
            $promotion = Promotion::query()
                ->whereKey($targetId)
                ->whereIn('store_id', $storeIds)
                ->first();

            if (! $promotion) {
                throw ValidationException::withMessages([
                    'action_id' => 'لا يمكنك الإعلان إلا لعروض متجرك.',
                ]);
            }

            $data['media_type'] = 'image';
            $data['media_path'] = $data['media_path'] ?? $promotion->image;

            if (empty($data['media_path'])) {
                throw ValidationException::withMessages([
                    'media_url' => 'العرض المختار لا يحتوي صورة، أرسل media_url أو أضف صورة للعرض.',
                ]);
            }
        }

        if ($data['click_action'] !== 'promotion') {
            if (empty($data['media_type'])) {
                throw ValidationException::withMessages([
                    'media_type' => 'نوع الوسائط مطلوب لهذا النوع من الإعلانات.',
                ]);
            }

            if (empty($data['media_path'])) {
                throw ValidationException::withMessages([
                    'media_url' => 'رابط الوسائط مطلوب لهذا النوع من الإعلانات.',
                ]);
            }
        }

        return $data;
    }

    private function resolveAdDateRange(VendorAdSubscription $subscription, array $data, CarbonInterface $now): array
    {
        $subscriptionStartsAt = $subscription->starts_at ?? $now;
        $subscriptionEndsAt = $subscription->ends_at;

        $startsAt = isset($data['starts_at'])
            ? Carbon::parse($data['starts_at'])
            : $subscriptionStartsAt;

        $endsAt = isset($data['ends_at'])
            ? Carbon::parse($data['ends_at'])
            : $subscriptionEndsAt;

        if ($startsAt->lt($subscriptionStartsAt)) {
            throw ValidationException::withMessages([
                'starts_at' => 'تاريخ بداية الإعلان لا يمكن أن يكون قبل تاريخ بداية الاشتراك.',
            ]);
        }

        if ($subscriptionEndsAt && $endsAt && $endsAt->gt($subscriptionEndsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'تاريخ نهاية الإعلان لا يمكن أن يتجاوز تاريخ نهاية الاشتراك.',
            ]);
        }

        if ($endsAt && $endsAt->lt($startsAt)) {
            throw ValidationException::withMessages([
                'ends_at' => 'تاريخ نهاية الإعلان يجب أن يكون بعد أو يساوي تاريخ البداية.',
            ]);
        }

        return [$startsAt, $endsAt];
    }
}
