<x-filament-panels::page>
    @php
        $tabCounts = [
            'all' => (int) ($this->reportCount + $this->customerCount + $this->storeCount + $this->subscriptionCount + $this->promotionCount),
            'report' => (int) $this->reportCount,
            'customer' => (int) $this->customerCount,
            'store' => (int) $this->storeCount,
            'subscription' => (int) $this->subscriptionCount,
            'promotion' => (int) $this->promotionCount,
        ];
    @endphp

    <style>
        .np-hero {
            border: 1px solid #dbe2ea;
            border-radius: 18px;
            padding: 16px;
            color: #0f172a;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        }

        .np-stat {
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #ffffff;
            padding: 10px;
        }

        .np-filters {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: linear-gradient(180deg, #fff 0%, #f8fafc 100%);
            padding: 12px;
            display: grid;
            grid-template-columns: repeat(6, minmax(0, 1fr));
            gap: 8px;
        }

        .np-filter-btn {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            transition: all .16s ease;
            cursor: pointer;
        }

        .np-search {
            border-radius: 14px;
            border: 1px solid #e2e8f0;
            background: #fff;
            padding: 10px;
        }

        .np-entry {
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            background: #fff;
            box-shadow: 0 10px 24px rgba(15, 23, 42, .07);
            padding: 14px;
        }

        .np-tag {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
        }
    </style>

    <div class="space-y-4">
        <div class="np-hero">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 style="font-size:18px;font-weight:800;line-height:1.3;">لوحة كل التنبيهات</h2>
                </div>

                <x-filament::button size="sm" color="success" wire:click="markAllAsRead">تمييز الكل كمقروء</x-filament::button>
            </div>

            <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-6" style="margin-top:12px;">
                <div class="np-stat" style="max-width:260px;"><div style="font-size:11px;color:#64748b;">غير مقروءة</div><div style="font-size:24px;font-weight:900;color:#0f172a;">{{ $this->unreadCount }}</div></div>
            </div>
        </div>

        <div class="space-y-3 rounded-2xl border border-slate-200 bg-white p-4">
            <div class="np-filters">
                <button class="np-filter-btn" type="button" wire:click="setCategory('all')" style="border-color:{{ $this->category === 'all' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->category === 'all' ? '#2563eb' : '#fff' }};color:{{ $this->category === 'all' ? '#fff' : '#334155' }};"><span style="display:block;font-size:14px;font-weight:900;line-height:1.1;">{{ $tabCounts['all'] }}</span><span style="display:block;margin-top:2px;">الكل</span></button>
                <button class="np-filter-btn" type="button" wire:click="setCategory('report')" style="border-color:{{ $this->category === 'report' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->category === 'report' ? '#2563eb' : '#fff' }};color:{{ $this->category === 'report' ? '#fff' : '#334155' }};"><span style="display:block;font-size:14px;font-weight:900;line-height:1.1;">{{ $tabCounts['report'] }}</span><span style="display:block;margin-top:2px;">بلاغات</span></button>
                <button class="np-filter-btn" type="button" wire:click="setCategory('customer')" style="border-color:{{ $this->category === 'customer' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->category === 'customer' ? '#2563eb' : '#fff' }};color:{{ $this->category === 'customer' ? '#fff' : '#334155' }};"><span style="display:block;font-size:14px;font-weight:900;line-height:1.1;">{{ $tabCounts['customer'] }}</span><span style="display:block;margin-top:2px;">عملاء</span></button>
                <button class="np-filter-btn" type="button" wire:click="setCategory('store')" style="border-color:{{ $this->category === 'store' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->category === 'store' ? '#2563eb' : '#fff' }};color:{{ $this->category === 'store' ? '#fff' : '#334155' }};"><span style="display:block;font-size:14px;font-weight:900;line-height:1.1;">{{ $tabCounts['store'] }}</span><span style="display:block;margin-top:2px;">متاجر</span></button>
                <button class="np-filter-btn" type="button" wire:click="setCategory('subscription')" style="border-color:{{ $this->category === 'subscription' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->category === 'subscription' ? '#2563eb' : '#fff' }};color:{{ $this->category === 'subscription' ? '#fff' : '#334155' }};"><span style="display:block;font-size:14px;font-weight:900;line-height:1.1;">{{ $tabCounts['subscription'] }}</span><span style="display:block;margin-top:2px;">اشتراكات</span></button>
                <button class="np-filter-btn" type="button" wire:click="setCategory('promotion')" style="border-color:{{ $this->category === 'promotion' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->category === 'promotion' ? '#2563eb' : '#fff' }};color:{{ $this->category === 'promotion' ? '#fff' : '#334155' }};"><span style="display:block;font-size:14px;font-weight:900;line-height:1.1;">{{ $tabCounts['promotion'] }}</span><span style="display:block;margin-top:2px;">عروض</span></button>
            </div>

            <div class="np-search">
                <x-filament::input.wrapper>
                    <x-filament::input wire:model.live.debounce.300ms="search" placeholder="ابحث في العنوان أو المحتوى" />
                </x-filament::input.wrapper>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($this->notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $title = $data['title'] ?? 'تنبيه';
                    $body = $data['body'] ?? null;
                    $categoryLabel = $this->getCategoryLabel($data);
                    $action = $this->getNotificationAction($data);
                    $category = $this->resolveNotificationCategory($data);
                @endphp

                <div class="np-entry" style="border-color:{{ $notification->read_at ? '#e2e8f0' : '#93c5fd' }};background:{{ $notification->read_at ? '#fff' : 'linear-gradient(180deg,#eff6ff,#ffffff)' }};">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 style="font-size:14px;font-weight:800;color:#0f172a;">{{ $title }}</h3>
                                <span class="np-tag" style="background:#eef2ff;color:#1e40af;">{{ $categoryLabel }}</span>
                            </div>
                            @if($body)
                                <p style="font-size:13px;line-height:1.7;color:#475569;">{{ $body }}</p>
                            @endif
                            <p style="font-size:11px;color:#64748b;font-weight:600;">{{ optional($notification->created_at)->diffForHumans() }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            <button
                                type="button"
                                wire:click="deleteNotification('{{ $notification->id }}')"
                                title="حذف التنبيه"
                                aria-label="حذف التنبيه"
                                style="display:inline-flex;align-items:center;justify-content:center;width:24px;height:24px;border-radius:999px;border:1px solid #cbd5e1;background:#fff;color:#475569;font-size:14px;font-weight:900;line-height:1;"
                            >
                                ×
                            </button>

                            @if($action)
                                <a href="{{ $action['url'] }}" style="display:inline-flex;align-items:center;border-radius:10px;padding:7px 12px;font-size:12px;font-weight:800;color:#fff;text-decoration:none;background:linear-gradient(135deg,#2563eb,#1d4ed8);">{{ $action['label'] }}</a>
                            @endif
                        </div>
                    </div>

                    <div class="mt-3">
                        @if(blank($notification->read_at))
                            <x-filament::button size="xs" color="success" wire:click="markAsRead('{{ $notification->id }}')">تمييز كمقروء</x-filament::button>
                        @else
                            <x-filament::button size="xs" color="gray" wire:click="markAsUnread('{{ $notification->id }}')">تمييز كغير مقروء</x-filament::button>
                        @endif
                    </div>
                </div>
            @empty
                <div class="rounded-xl border border-dashed p-8 text-center text-sm text-gray-500">
                    لا توجد تنبيهات ضمن هذا التصنيف.
                </div>
            @endforelse
        </div>
    </div>
</x-filament-panels::page>
