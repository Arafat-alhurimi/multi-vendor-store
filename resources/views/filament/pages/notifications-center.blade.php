<x-filament-panels::page>
    <div class="space-y-4">
        <div class="grid gap-3 md:grid-cols-3">
            <div class="rounded-xl border p-4">
                <div class="text-sm text-gray-500">غير مقروءة</div>
                <div class="mt-1 text-2xl font-semibold">{{ $this->unreadCount }}</div>
            </div>
            <div class="rounded-xl border p-4">
                <div class="text-sm text-gray-500">بلاغات</div>
                <div class="mt-1 text-2xl font-semibold">{{ $this->reportCount }}</div>
            </div>
            <div class="rounded-xl border p-4">
                <div class="text-sm text-gray-500">حسابات</div>
                <div class="mt-1 text-2xl font-semibold">{{ $this->accountCount }}</div>
            </div>
        </div>

        <div class="flex flex-wrap items-center gap-2 rounded-xl border p-3">
            <x-filament::button size="sm" :color="$this->category === 'all' ? 'primary' : 'gray'" wire:click="setCategory('all')">الكل</x-filament::button>
            <x-filament::button size="sm" :color="$this->category === 'report' ? 'danger' : 'gray'" wire:click="setCategory('report')">بلاغات</x-filament::button>
            <x-filament::button size="sm" :color="$this->category === 'account' ? 'info' : 'gray'" wire:click="setCategory('account')">حسابات</x-filament::button>

            <div class="ms-auto flex items-center gap-2">
                <x-filament::input.wrapper>
                    <x-filament::input wire:model.live.debounce.300ms="search" placeholder="ابحث في العنوان أو المحتوى" />
                </x-filament::input.wrapper>
                <x-filament::button size="sm" color="success" wire:click="markAllAsRead">تمييز الكل كمقروء</x-filament::button>
            </div>
        </div>

        <div class="space-y-3">
            @forelse($this->notifications as $notification)
                @php
                    $data = $notification->data ?? [];
                    $title = $data['title'] ?? 'تنبيه';
                    $body = $data['body'] ?? null;
                    $isAccount = $this->isAccountNotification($data);
                    $isReport = $this->isReportNotification($data);
                    $targetUrl = $data['target_url'] ?? null;
                    $userUrl = $this->getUserUrl($data);
                @endphp

                <div class="rounded-xl border p-4 {{ $notification->read_at ? 'bg-white border-gray-200' : 'bg-amber-200/80 border-amber-400 ring-1 ring-amber-400/70' }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold">{{ $title }}</h3>
                                @if($isReport)
                                    <span class="rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-700">بلاغ</span>
                                @elseif($isAccount)
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs text-blue-700">حساب</span>
                                @endif
                            </div>
                            @if($body)
                                <p class="text-sm text-gray-600">{{ $body }}</p>
                            @endif
                            <p class="text-xs text-gray-400">{{ optional($notification->created_at)->diffForHumans() }}</p>
                        </div>

                        <div class="flex flex-wrap items-center gap-2">
                            @if($isAccount && filled($userUrl))
                                <x-filament::button size="xs" color="info" tag="a" :href="$userUrl">فتح المستخدم</x-filament::button>
                            @endif

                            @if($isReport && filled($targetUrl))
                                <x-filament::button size="xs" color="danger" tag="a" :href="$targetUrl">فتح العنصر</x-filament::button>
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
