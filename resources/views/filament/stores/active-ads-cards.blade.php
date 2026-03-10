@php
    $recordId = request()->route('record');

    $store = \App\Models\Store::query()->whereKey($recordId)->first();
    $vendorId = (int) ($store?->user_id ?? 0);

    $subscription = null;

    if ($vendorId > 0) {
        $subscription = \App\Models\VendorAdSubscription::query()
            ->where('vendor_id', $vendorId)
            ->where('status', 'active')
            ->where(function ($query): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', now());
            })
            ->where(function ($query): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', now());
            })
            ->latest('ends_at')
            ->first();

        if (! $subscription) {
            $subscription = \App\Models\VendorAdSubscription::query()
                ->where('vendor_id', $vendorId)
                ->latest('created_at')
                ->first();
        }
    }

    $items = collect();

    if ($subscription) {
        $now = now();

        $items = $subscription->ads()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->latest('id')
            ->get()
            ->map(function ($ad) use ($store) {
                $mediaPath = (string) ($ad->media_path ?? '');
                $mediaUrl = str_starts_with($mediaPath, 'http://') || str_starts_with($mediaPath, 'https://')
                    ? $mediaPath
                    : \Illuminate\Support\Facades\Storage::disk('s3')->url($mediaPath);

                $transitionType = match ($ad->click_action) {
                    'promotion' => 'عرض',
                    'product' => 'منتج',
                    'store' => 'متجر',
                    'url' => 'رابط',
                    default => 'غير محدد',
                };

                $transitionTarget = match ($ad->click_action) {
                    'promotion' => (string) (\App\Models\Promotion::query()->whereKey((int) $ad->action_id)->value('title') ?? 'عرض غير موجود'),
                    'product' => (string) (\App\Models\Product::query()->whereKey((int) $ad->action_id)->value('name_ar') ?? 'منتج غير موجود'),
                    'store' => (string) (\App\Models\Store::query()->whereKey((int) $ad->action_id)->value('name') ?? 'متجر غير موجود'),
                    'url' => (string) ($ad->action_id ?? '-'),
                    default => (string) ($ad->action_id ?? '-'),
                };

                return [
                    'id' => (int) $ad->id,
                    'media_type' => (string) $ad->media_type,
                    'media_url' => $mediaUrl,
                    'transition_type' => $transitionType,
                    'transition_target' => $transitionTarget,
                    'starts_at' => $ad->starts_at?->format('Y-m-d H:i') ?? '-',
                    'ends_at' => $ad->ends_at?->format('Y-m-d H:i') ?? '-',
                    'store_name' => (string) ($store?->name ?? '-'),
                ];
            });
    }
@endphp

@if (empty($items))
    <div style="padding:14px;border:1px dashed #d1d5db;border-radius:10px;background:#f9fafb;color:#6b7280;">
        لا يوجد محتوى إعلاني نشط حالياً.
    </div>
@else
    <div style="display:grid;gap:12px;">
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:12px;">
            @foreach ($items as $ad)
                <a
                    href="#store-ad-modal-{{ $ad['id'] }}"
                    style="text-align:right;border:1px solid #e5e7eb;border-radius:12px;background:#fff;padding:10px;display:grid;gap:8px;cursor:pointer;"
                >
                    @if (($ad['media_type'] ?? null) === 'video')
                        <video src="{{ $ad['media_url'] }}" preload="metadata" style="width:100%;height:140px;object-fit:cover;border-radius:8px;" muted></video>
                    @else
                        <img src="{{ $ad['media_url'] }}" alt="محتوى" style="width:100%;height:140px;object-fit:cover;border-radius:8px;" />
                    @endif

                    <div style="display:flex;justify-content:space-between;align-items:center;gap:8px;">
                        <span style="font-size:12px;padding:2px 8px;border-radius:9999px;background:#eef2ff;color:#3730a3;">{{ $ad['transition_type'] }}</span>
                        <span style="font-size:12px;color:#6b7280;">#{{ $ad['id'] }}</span>
                    </div>

                    <div style="font-size:13px;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        إلى: {{ $ad['transition_target'] }}
                    </div>
                </a>
            @endforeach
        </div>
    </div>

    @foreach ($items as $ad)
        <div id="store-ad-modal-{{ $ad['id'] }}" class="store-ad-modal-overlay" style="position:fixed;inset:0;background:rgba(17,24,39,.55);padding:16px;z-index:100;display:none;align-items:center;justify-content:center;">
            <a href="#" class="store-ad-modal-close-area" style="position:absolute;inset:0;"></a>
            <div style="position:relative;width:min(760px,100%);max-height:92vh;overflow:auto;background:#fff;border-radius:14px;padding:14px;display:grid;gap:12px;z-index:101;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <strong>تفاصيل المحتوى الإعلاني</strong>
                    <a href="#" style="border:1px solid #e5e7eb;border-radius:8px;padding:4px 10px;background:#fff;">إغلاق</a>
                </div>

                <div style="display:grid;gap:12px;">
                    <div style="padding:10px;border:1px solid #e5e7eb;border-radius:10px;background:#f8fafc;">
                        @if (($ad['media_type'] ?? null) === 'video')
                            <video src="{{ $ad['media_url'] }}" controls preload="metadata" style="width:100%;max-height:320px;border-radius:8px;"></video>
                        @else
                            <img src="{{ $ad['media_url'] }}" alt="المحتوى" style="width:100%;max-height:320px;object-fit:cover;border-radius:8px;" />
                        @endif
                    </div>
                    <div style="display:grid;gap:8px;">
                        <div><strong>المتجر:</strong> {{ $ad['store_name'] }}</div>
                        <div><strong>نوع الانتقال:</strong> {{ $ad['transition_type'] }}</div>
                        <div><strong>ينتقل إلى:</strong> {{ $ad['transition_target'] }}</div>
                        <div><strong>يبدأ:</strong> {{ $ad['starts_at'] }}</div>
                        <div><strong>ينتهي:</strong> {{ $ad['ends_at'] }}</div>
                    </div>
                </div>
            </div>
        </div>
    @endforeach

    <style>
        .store-ad-modal-overlay:target {
            display: flex !important;
        }
    </style>
@endif
