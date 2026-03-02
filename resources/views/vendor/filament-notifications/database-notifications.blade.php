@php
    use Filament\Support\Enums\Alignment;
    use Filament\Support\View\Components\BadgeComponent;
    use Illuminate\View\ComponentAttributeBag;
    use Filament\Support\Icons\Heroicon;
    use function Filament\Support\generate_icon_html;

    $notifications = $this->getNotifications();
    $unreadNotificationsCount = $this->getUnreadNotificationsCount();
    $hasNotifications = $notifications->count();
    $isPaginated = $notifications instanceof \Illuminate\Contracts\Pagination\Paginator && $notifications->hasPages();
    $pollingInterval = $this->getPollingInterval();
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

                    <div class="fi-ac">
                        @if ($unreadNotificationsCount && $this->markAllNotificationsAsReadAction?->isVisible())
                            {{ $this->markAllNotificationsAsReadAction }}
                        @endif

                        @if ($this->clearNotificationsAction?->isVisible())
                            {{ $this->clearNotificationsAction }}
                        @endif
                    </div>
                </div>
            </x-slot>

            <div style="display:flex;flex-direction:column;gap:12px;">
            @foreach ($notifications as $notification)
                @php
                    $data = $notification->data;
                    if (is_string($data)) {
                        $payload = json_decode($data, true) ?? [];
                    } else {
                        $payload = (array) $data;
                    }

                    $relatedUser = null;
                    if (! empty($payload['user_id'])) {
                        $relatedUser = \App\Models\User::find($payload['user_id']);
                    }

                    $isUnread = $notification->unread();

                    $containerStyle = $isUnread
                        ? 'background:linear-gradient(90deg, rgba(79,70,229,0.06), rgba(99,102,241,0.03));border-left:4px solid #6366f1;padding:12px;border-radius:8px;'
                        : 'background:#ffffff;border:1px solid #e6e7ea;padding:12px;border-radius:8px;';
                @endphp

                <div
                    @class([
                        'fi-no-notification-read-ctn' => ! $isUnread,
                        'fi-no-notification-unread-ctn' => $isUnread,
                    ])
                    style="direction:rtl;width:100%;{{ $containerStyle }}"
                >
                    <div style="display:flex;gap:12px;align-items:flex-start;">
                        <div style="flex:1;min-width:0;">
                            @if (! empty($payload['title']))
                                <h3 class="fi-no-notification-title" style="margin:0 0 6px 0;font-weight:600;color:#111827;">{{ $payload['title'] }}</h3>
                            @endif

                            @if (! empty($payload['body']))
                                <div class="fi-no-notification-body" style="direction:rtl;text-align:right;white-space:pre-wrap;color:#374151;">{!! \Illuminate\Support\Str::of($payload['body'])->sanitizeHtml() !!}</div>
                            @endif

                            <div class="fi-no-notification-meta" style="margin-top:8px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <time class="fi-no-notification-date" style="color:#6b7280;">{{ \Illuminate\Support\Carbon::parse($notification->created_at)->diffForHumans() }}</time>

                                @if ($relatedUser)
                                    <span class="fi-badge fi-size-xs" style="text-transform:capitalize;background:#7c3aed;color:#fff;padding:4px 8px;border-radius:999px;font-size:12px;">{{ $relatedUser->role }}</span>

                                                            <a href="/admin/users/{{ $relatedUser->getKey() }}/edit" class="fi-link" target="_blank" style="color:#4f46e5;">{{ __('عرض المستخدم') }}</a>
                                @endif
                            </div>
                        </div>

                        <div style="display:flex;flex-direction:column;gap:8px;align-items:center;justify-content:flex-start;">
                            @if ($isUnread)
                                <button
                                    wire:click="markNotificationAsRead('{{ $notification->getKey() }}')"
                                    type="button"
                                    title="تمييز كمقروء"
                                    class="fi-icon-btn transition transform hover:scale-105 active:scale-95"
                                    style="background:#eef2ff;border-radius:8px;padding:8px;border:none;color:#4f46e5;"
                                >
                                    {!! generate_icon_html(Heroicon::Check)->toHtml() !!}
                                </button>
                            @else
                                <button
                                    wire:click="markNotificationAsUnread('{{ $notification->getKey() }}')"
                                    type="button"
                                    title="تمييز كغير مقروء"
                                    class="fi-icon-btn transition transform hover:scale-105 active:scale-95"
                                    style="background:transparent;border-radius:8px;padding:8px;border:1px solid #e6e7ea;color:#374151;"
                                >
                                    {!! generate_icon_html(Heroicon::ArrowPath)->toHtml() !!}
                                </button>
                            @endif

                                @if ($relatedUser)
                                <a
                                    href="/admin/notifications"
                                    target="_blank"
                                    title="عرض كل الإشعارات"
                                    class="fi-icon-btn transition transform hover:scale-105 active:scale-95"
                                    style="background:transparent;padding:8px;border-radius:8px;color:#4f46e5;border:1px solid transparent;"
                                >
                                    {!! generate_icon_html(Heroicon::Eye)->toHtml() !!}
                                </a>
                            @endif

                            <button
                                wire:click="removeNotification('{{ $notification->getKey() }}')"
                                type="button"
                                title="حذف الإشعار"
                                class="fi-icon-btn transition transform hover:scale-105 active:scale-95"
                                style="background:transparent;padding:8px;border-radius:8px;color:#ef4444;border:1px solid transparent;"
                            >
                                {!! generate_icon_html(Heroicon::Trash)->toHtml() !!}
                            </button>
                        </div>
                    </div>
                </div>
            @endforeach
            </div>

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
        @endif
    </x-filament::modal>
</div>
