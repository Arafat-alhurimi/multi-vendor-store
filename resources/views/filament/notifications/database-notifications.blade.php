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

<div class="fi-no-database">
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
        width="md"
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
                <div>
                    <h2 class="fi-modal-heading">
                        {{ __('filament-notifications::database.modal.heading') }}

                        @if ($unreadNotificationsCount)
                            <span
                                {{
                                    (new ComponentAttributeBag)->color(BadgeComponent::class, 'primary')->class([
                                        'fi-badge fi-size-xs',
                                    ])
                                }}
                            >
                                {{ $unreadNotificationsCount }}
                            </span>
                        @endif
                    </h2>

                    <div class="fi-ac mt-2">
                        <x-filament::button
                            size="xs"
                            color="gray"
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

        <div class="mt-2 flex flex-wrap items-center gap-2">
            <x-filament::button
                size="xs"
                :color="$this->notificationCategory === 'all' ? 'primary' : 'gray'"
                wire:click="setNotificationCategory('all')"
            >
                الكل
            </x-filament::button>

            <x-filament::button
                size="xs"
                :color="$this->notificationCategory === 'report' ? 'danger' : 'gray'"
                wire:click="setNotificationCategory('report')"
            >
                بلاغات
            </x-filament::button>

            <x-filament::button
                size="xs"
                :color="$this->notificationCategory === 'account' ? 'info' : 'gray'"
                wire:click="setNotificationCategory('account')"
            >
                حسابات
            </x-filament::button>
        </div>

        @if ($hasNotifications)
            @foreach ($notifications as $notification)
                <div
                    @class([
                        'mb-3 rounded-xl border p-3',
                        'fi-no-notification-read-ctn' => ! $notification->unread(),
                        'fi-no-notification-unread-ctn' => $notification->unread(),
                        'border-gray-200 bg-white' => ! $notification->unread(),
                        'border-amber-400 bg-amber-200/80 ring-1 ring-amber-400/70' => $notification->unread(),
                    ])
                >
                    {{ $this->getNotification($notification)->inline() }}

                    <div class="mt-2 flex justify-center">
                        @if ($notification->unread())
                            <button
                                type="button"
                                wire:click="markNotificationAsRead('{{ $notification->id }}')"
                                class="text-sm font-semibold text-amber-700 hover:underline"
                            >
                                تمييز كمقروء
                            </button>
                        @else
                            <button
                                type="button"
                                wire:click="markNotificationAsUnread('{{ $notification->id }}')"
                                class="text-sm font-medium text-blue-700 hover:underline"
                            >
                                تمييز كغير مقروء
                            </button>
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
