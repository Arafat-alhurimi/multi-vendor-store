@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\Support\Facades\Route;
    use Illuminate\View\ComponentAttributeBag;

    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $hasNotifications = $notifications->count();
    $isPaginated = $notifications instanceof \Illuminate\Contracts\Pagination\Paginator && $notifications->hasPages();
    $pollingInterval = $this->getPollingInterval();
    $notificationsCenterRoute = 'filament.' . filament()->getCurrentPanel()->getId() . '.pages.notifications-center';
    $notificationsCenterUrl = Route::has($notificationsCenterRoute)
        ? route($notificationsCenterRoute)
        : url(filament()->getCurrentPanel()->getPath() . '/notifications');
@endphp

<div class="fi-no-database nm-shell">
    <style>
        .nm-shell {
            --nm-text: #0f172a;
            --nm-muted: #64748b;
            --nm-soft-border: #dbe2ea;
        }

        .nm-header {
            border: 1px solid #dbe2ea;
            border-radius: 18px;
            padding: 14px;
            color: #0f172a;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .06);
        }

        .nm-chip {
            border: 1px solid #cbd5e1;
            background: #f8fafc;
            color: #334155;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 800;
            padding: 4px 10px;
        }

        .nm-toolbar {
            margin-top: 12px;
            padding: 10px;
            border: 1px solid var(--nm-soft-border);
            border-radius: 14px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 8px;
        }

        .nm-filter-btn {
            border-radius: 10px;
            border: 1px solid #cbd5e1;
            padding: 8px 10px;
            font-size: 12px;
            font-weight: 800;
            line-height: 1;
            transition: all .16s ease;
            cursor: pointer;
        }

        .nm-card {
            border: 1px solid #e2e8f0;
            border-radius: 16px;
            background: #ffffff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, .08);
            padding: 14px;
            margin-bottom: 12px;
        }

        .nm-primary-btn {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            color: #ffffff;
        }

        .nm-pill {
            border-radius: 999px;
            padding: 4px 10px;
            font-size: 11px;
            font-weight: 800;
        }

        .nm-time {
            color: var(--nm-muted);
            font-size: 11px;
            font-weight: 600;
        }

        .nm-title {
            color: var(--nm-text);
            font-size: 14px;
            font-weight: 800;
            line-height: 1.4;
        }

        .nm-body {
            color: #475569;
            font-size: 13px;
            line-height: 1.7;
        }

        .nm-action {
            display: inline-flex;
            align-items: center;
            border-radius: 10px;
            padding: 7px 12px;
            color: #fff;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
        }

        .nm-toggle {
            border: 0;
            background: transparent;
            color: #0f766e;
            font-size: 12px;
            font-weight: 700;
            cursor: pointer;
        }

        .nm-delete {
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            width: 24px;
            height: 24px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: #ffffff;
            color: #475569;
            font-size: 14px;
            line-height: 1;
            font-weight: 800;
            cursor: pointer;
        }

        .nm-delete:hover {
            background: #fee2e2;
            border-color: #fca5a5;
            color: #b91c1c;
        }
    </style>

    <x-filament::modal
        :alignment="$hasNotifications ? null : Alignment::Center"
        close-button
        :description="$hasNotifications ? null : __('filament-notifications::database.modal.empty.description')"
        :heading="$hasNotifications ? null : __('filament-notifications::database.modal.empty.heading')"
        :icon="$hasNotifications ? null : \Filament\Support\Icons\Heroicon::OutlinedBellSlash"
        :icon-alias="
            $hasNotifications
            ? null
            : \Filament\Notifications\View\NotificationsIconAlias::DATABASE_MODAL_EMPTY_STATE
        "
        :icon-color="$hasNotifications ? null : 'gray'"
        id="database-notifications"
        slide-over
        :sticky-header="$hasNotifications"
        teleport="body"
        width="2xl"
        class="fi-no-database"
        :attributes="
            new \Illuminate\View\ComponentAttributeBag([
                'wire:poll.' . $pollingInterval => $pollingInterval ? '' : false,
            ])
        "
    >
        @if ($trigger = $this->getTrigger())
            <x-slot name="trigger">
                {{ $trigger->with(['unreadNotificationsCount' => $unreadNotificationsCount]) }}
            </x-slot>
        @endif

        @if ($hasNotifications)
            <x-slot name="header">
                <div class="nm-header">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <h2 class="fi-modal-heading" style="color:#0f172a;">مركز التنبيهات</h2>
                        </div>

                        <div class="flex items-center gap-2">
                            <span class="nm-chip">غير مقروء {{ $unreadNotificationsCount }}</span>
                            <span class="nm-chip">إجمالي {{ $notifications->count() }}</span>
                        </div>
                    </div>

                    <div class="fi-ac mt-3" style="justify-content:flex-start;">
                        <x-filament::button
                            size="xs"
                            color="primary"
                            tag="a"
                            :href="$notificationsCenterUrl"
                            icon="heroicon-o-eye"
                        >
                            كل التنبيهات
                        </x-filament::button>

                        @if ($unreadNotificationsCount && $this->markAllNotificationsAsReadAction?->isVisible())
                            {{ $this->markAllNotificationsAsReadAction }}
                        @endif

                        @if ($this->clearNotificationsAction?->isVisible())
                            {{ $this->clearNotificationsAction }}
                        @endif
                    </div>
                </div>
            </x-slot>
        @endif

        <div class="nm-toolbar">
            <button class="nm-filter-btn" type="button" wire:click="setNotificationCategory('all')" style="border-color:{{ $this->notificationCategory === 'all' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->notificationCategory === 'all' ? '#2563eb' : '#fff' }};color:{{ $this->notificationCategory === 'all' ? '#fff' : '#334155' }};">الكل</button>
            <button class="nm-filter-btn" type="button" wire:click="setNotificationCategory('report')" style="border-color:{{ $this->notificationCategory === 'report' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->notificationCategory === 'report' ? '#2563eb' : '#fff' }};color:{{ $this->notificationCategory === 'report' ? '#fff' : '#334155' }};">بلاغات</button>
            <button class="nm-filter-btn" type="button" wire:click="setNotificationCategory('customer')" style="border-color:{{ $this->notificationCategory === 'customer' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->notificationCategory === 'customer' ? '#2563eb' : '#fff' }};color:{{ $this->notificationCategory === 'customer' ? '#fff' : '#334155' }};">عملاء</button>
            <button class="nm-filter-btn" type="button" wire:click="setNotificationCategory('store')" style="border-color:{{ $this->notificationCategory === 'store' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->notificationCategory === 'store' ? '#2563eb' : '#fff' }};color:{{ $this->notificationCategory === 'store' ? '#fff' : '#334155' }};">متاجر</button>
            <button class="nm-filter-btn" type="button" wire:click="setNotificationCategory('subscription')" style="border-color:{{ $this->notificationCategory === 'subscription' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->notificationCategory === 'subscription' ? '#2563eb' : '#fff' }};color:{{ $this->notificationCategory === 'subscription' ? '#fff' : '#334155' }};">اشتراكات</button>
            <button class="nm-filter-btn" type="button" wire:click="setNotificationCategory('promotion')" style="border-color:{{ $this->notificationCategory === 'promotion' ? '#2563eb' : '#cbd5e1' }};background:{{ $this->notificationCategory === 'promotion' ? '#2563eb' : '#fff' }};color:{{ $this->notificationCategory === 'promotion' ? '#fff' : '#334155' }};">عروض</button>
        </div>

        @if ($hasNotifications)
            @foreach ($notifications as $notification)
                @php
                    $data = is_array($notification->data) ? $notification->data : [];
                    $action = $this->getNotificationAction($data);
                    $pillColor = $this->getCategoryColor($data);
                    $title = (string) ($data['title'] ?? 'تنبيه جديد');
                    $body = (string) ($data['body'] ?? '');
                @endphp

                <div class="nm-card" style="border-color:{{ $notification->unread() ? '#93c5fd' : '#e2e8f0' }};background:{{ $notification->unread() ? 'linear-gradient(180deg,#eff6ff 0%,#ffffff 100%)' : '#fff' }};">
                    <div class="mb-2 flex items-center justify-between gap-2">
                        <span class="nm-pill" style="background:#eef2ff;color:#1e40af;">{{ $this->getCategoryLabel($data) }}</span>

                        <div class="flex items-center gap-2">
                            <span class="nm-time">{{ optional($notification->created_at)->diffForHumans() }}</span>
                            <button
                                type="button"
                                class="nm-delete"
                                title="حذف التنبيه"
                                aria-label="حذف التنبيه"
                                wire:click="deleteNotification('{{ $notification->id }}')"
                            >
                                ×
                            </button>
                        </div>
                    </div>

                    <div>
                        <h4 class="nm-title">{{ $title }}</h4>
                        @if ($body !== '')
                            <p class="nm-body">{{ $body }}</p>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap items-center justify-between gap-2">
                        @if ($action)
                            <a class="nm-action nm-primary-btn" href="{{ $action['url'] }}">
                                {{ $action['label'] }}
                            </a>
                        @endif

                        @if ($notification->unread())
                            <button type="button" wire:click="markNotificationAsRead('{{ $notification->id }}')" class="nm-toggle">تمييز كمقروء</button>
                        @else
                            <button type="button" wire:click="markNotificationAsUnread('{{ $notification->id }}')" class="nm-toggle" style="color:#475569;">تمييز كغير مقروء</button>
                        @endif
                    </div>
                </div>
            @endforeach

            @if ($broadcastChannel = $this->getBroadcastChannel())
                @script
                    <script>
                        window.addEventListener('EchoLoaded', () => {
                            window.Echo.private(@js($broadcastChannel)).listen(
                                '.database-notifications.sent',
                                () => {
                                    setTimeout(
                                        () => $wire.call('$refresh'),
                                        500,
                                    )
                                },
                            )
                        })

                        if (window.Echo) {
                            window.dispatchEvent(new CustomEvent('EchoLoaded'))
                        }
                    </script>
                @endscript
            @endif

            @if ($isPaginated)
                <x-slot name="footer">
                    <x-filament::pagination :paginator="$notifications" />
                </x-slot>
            @endif
        @else
            <div class="mt-3 text-sm text-gray-500">لا توجد تنبيهات ضمن هذا التصنيف.</div>
        @endif
    </x-filament::modal>
</div>
