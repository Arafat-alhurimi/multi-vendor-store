<?php

namespace App\Livewire\Filament;

use App\Filament\Resources\Users\UserResource;
use Filament\Livewire\DatabaseNotifications as BaseDatabaseNotifications;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Str;

class DatabaseNotifications extends BaseDatabaseNotifications
{
    private const CATEGORIES = ['all', 'report', 'customer', 'store', 'subscription', 'promotion'];

    public string $notificationCategory = 'all';

    public function setNotificationCategory(string $category): void
    {
        $this->notificationCategory = in_array($category, self::CATEGORIES, true)
            ? $category
            : 'all';

        $this->resetPage(pageName: 'database-notifications-page');
    }

    public function deleteNotification(string $id): void
    {
        $this->getNotificationsQuery()->whereKey($id)->delete();

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
        if ($this->notificationCategory !== 'all') {
            return $this->applyCategoryFilter($query, $this->notificationCategory);
        }

        return $query;
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        return view('filament.notifications.database-notifications');
    }

    public function getNotification(DatabaseNotification $notification): Notification
    {
        return parent::getNotification($notification);
    }

    public function resolveNotificationCategory(array $data): string
    {
        $category = (string) ($data['notification_category'] ?? '');
        if (in_array($category, self::CATEGORIES, true) && $category !== 'all') {
            return $category;
        }

        $title = (string) ($data['title'] ?? '');

        if (Str::contains($title, 'بلاغ')) {
            return 'report';
        }

        if (Str::contains($title, ['اشتراك', 'تجديد'])) {
            return 'subscription';
        }

        if (Str::contains($title, ['انضمام', 'عرض'])) {
            return 'promotion';
        }

        if (Str::contains($title, ['متجر', 'بائع'])) {
            return 'store';
        }

        if ($category === 'account' || Str::contains($title, ['مستخدم جديد', 'عميل'])) {
            return 'customer';
        }

        return 'all';
    }

    public function getCategoryLabel(array $data): string
    {
        return match ($this->resolveNotificationCategory($data)) {
            'report' => 'بلاغات',
            'customer' => 'عملاء',
            'store' => 'متاجر',
            'subscription' => 'اشتراكات',
            'promotion' => 'عروض',
            default => 'تنبيهات عامة',
        };
    }

    public function getCategoryColor(array $data): string
    {
        return match ($this->resolveNotificationCategory($data)) {
            'report' => 'danger',
            'customer' => 'info',
            'store' => 'warning',
            'subscription' => 'success',
            'promotion' => 'primary',
            default => 'gray',
        };
    }

    public function getNotificationAction(array $data): ?array
    {
        return $this->resolveNotificationAction($data);
    }

    private function applyCategoryFilter(Builder | Relation $query, string $category): Builder | Relation
    {
        return $query->where(function (Builder $nested) use ($category): void {
            if ($category === 'report') {
                $nested
                    ->where('data->notification_category', 'report')
                    ->orWhere('data->title', 'like', '%بلاغ%');

                return;
            }

            if ($category === 'customer') {
                $nested
                    ->where('data->notification_category', 'customer')
                    ->orWhere('data->notification_category', 'account')
                    ->orWhere('data->title', 'like', '%مستخدم جديد%')
                    ->orWhere('data->title', 'like', '%عميل%');

                return;
            }

            if ($category === 'store') {
                $nested
                    ->where('data->notification_category', 'store')
                    ->orWhere('data->title', 'like', '%متجر%')
                    ->orWhere('data->title', 'like', '%بائع%');

                return;
            }

            if ($category === 'subscription') {
                $nested
                    ->where('data->notification_category', 'subscription')
                    ->orWhere('data->title', 'like', '%اشتراك%')
                    ->orWhere('data->title', 'like', '%تجديد%');

                return;
            }

            $nested
                ->where('data->notification_category', 'promotion')
                ->orWhere('data->title', 'like', '%انضمام%')
                ->orWhere('data->title', 'like', '%عرض%');
        });
    }

    private function resolveNotificationAction(array $data): ?array
    {
        $category = $this->resolveNotificationCategory($data);
        $targetUrl = $data['target_url'] ?? null;

        if ($category === 'customer' && blank($targetUrl) && filled($data['user_id'] ?? null)) {
            $targetUrl = UserResource::getUrl('view', ['record' => $data['user_id']]);
        }

        if (blank($targetUrl)) {
            return null;
        }

        return [
            'label' => match ($category) {
                'customer' => 'عرض العميل',
                'store' => 'عرض المتجر',
                'subscription' => 'فتح طلبات الاشتراكات',
                'promotion' => 'فتح العرض',
                'report' => 'فتح البلاغ',
                default => 'فتح التنبيه',
            },
            'url' => (string) $targetUrl,
        ];
    }
}
