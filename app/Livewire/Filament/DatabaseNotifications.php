<?php

namespace App\Livewire\Filament;

use App\Filament\Resources\Users\UserResource;
use Filament\Actions\Action;
use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class DatabaseNotifications extends BaseDatabaseNotifications
{
    public string $notificationCategory = 'all';

    public function setNotificationCategory(string $category): void
    {
        $this->notificationCategory = in_array($category, ['all', 'report', 'account'], true)
            ? $category
            : 'all';

        $this->resetPage(pageName: 'database-notifications-page');
    }

    public function getNotificationsQuery(): Builder | Relation
    {
        $user = $this->getUser();

        if (! $user) {
            abort(401);
        }

        /** @phpstan-ignore-next-line */
        $query = $user->notifications()
            ->where(function (Builder $nested): void {
                $nested
                    ->where('data->format', 'filament')
                    ->orWhereNull('data->format');
            });

        return $this->applyNotificationCategoryFilter($query);
    }

    protected function applyNotificationCategoryFilter(Builder | Relation $query): Builder | Relation
    {
        if ($this->notificationCategory === 'report') {
            return $query->where(function (Builder $nested): void {
                $nested
                    ->where('data->notification_category', 'report')
                    ->orWhere('data->title', 'like', '%بلاغ%');
            });
        }

        if ($this->notificationCategory === 'account') {
            return $query->where(function (Builder $nested): void {
                $nested
                    ->where('data->notification_category', 'account')
                    ->orWhere('data->title', 'like', '%مستخدم جديد%');
            });
        }

        return $query;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.notifications.database-notifications');
    }

    public function getNotification(DatabaseNotification $notification): Notification
    {
        $filamentNotification = parent::getNotification($notification);

        $data = is_array($notification->data) ? $notification->data : [];
        $title = (string) ($data['title'] ?? '');
        $category = (string) ($data['notification_category'] ?? '');
        $isAccount = $category === 'account' || Str::contains($title, 'مستخدم جديد');

        if (! $isAccount) {
            return $filamentNotification;
        }

        $userUrl = $data['target_url'] ?? null;

        if (blank($userUrl) && filled($data['user_id'] ?? null)) {
            $userUrl = UserResource::getUrl('view', ['record' => $data['user_id']]);
        }

        if (blank($userUrl)) {
            return $filamentNotification;
        }

        $hasOpenUserAction = collect($filamentNotification->getActions())
            ->contains(fn ($action) => method_exists($action, 'getLabel') && $action->getLabel() === 'فتح المستخدم');

        if (! $hasOpenUserAction) {
            $existingActions = $filamentNotification->getActions();

            $filamentNotification->actions([
                ...$existingActions,
                Action::make('openUserInline')
                    ->label('فتح المستخدم')
                    ->url($userUrl),
            ]);
        }

        return $filamentNotification;
    }
}
