<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\FilamentDatabaseNotification;
use BackedEnum;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class NotificationsCenter extends Page
{
    private const CATEGORIES = ['all', 'report', 'customer', 'store', 'subscription', 'promotion'];

    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'إدارة التنبيهات';

    protected static ?string $slug = 'notifications-center';

    protected string $view = 'filament.pages.notifications-center';

    public string $category = 'all';

    public string $search = '';

    public function setCategory(string $category): void
    {
        $this->category = in_array($category, self::CATEGORIES, true) ? $category : 'all';
    }

    public function markAsRead(string $id): void
    {
        $this->baseQuery()->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markAsUnread(string $id): void
    {
        $this->baseQuery()->whereKey($id)->whereNotNull('read_at')->update(['read_at' => null]);
    }

    public function deleteNotification(string $id): void
    {
        $this->baseQuery()->whereKey($id)->delete();
    }

    public function markAllAsRead(): void
    {
        $this->filteredQuery()->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function getNotificationsProperty()
    {
        return $this->filteredQuery()
            ->latest('created_at')
            ->limit(200)
            ->get();
    }

    public function getUnreadCountProperty(): int
    {
        return (clone $this->baseQuery())->whereNull('read_at')->count();
    }

    public function getReportCountProperty(): int
    {
        return (clone $this->baseQuery())
            ->where(function (Builder $query): void {
                $query
                    ->where('data->notification_category', 'report')
                    ->orWhere('data->title', 'like', '%بلاغ%');
            })
            ->count();
    }

    public function getCustomerCountProperty(): int
    {
        return $this->countByCategory('customer');
    }

    public function getStoreCountProperty(): int
    {
        return $this->countByCategory('store');
    }

    public function getSubscriptionCountProperty(): int
    {
        return $this->countByCategory('subscription');
    }

    public function getPromotionCountProperty(): int
    {
        return $this->countByCategory('promotion');
    }

    public function getUserUrl(array $data): ?string
    {
        $targetUrl = $data['target_url'] ?? null;

        if (filled($targetUrl)) {
            return (string) $targetUrl;
        }

        if (filled($data['user_id'] ?? null)) {
            return UserResource::getUrl('view', ['record' => $data['user_id']]);
        }

        return null;
    }

    protected function filteredQuery(): Builder
    {
        $query = $this->baseQuery();

        if ($this->category !== 'all') {
            $query = $this->applyCategoryFilter($query, $this->category);
        }

        $search = trim($this->search);

        if ($search !== '') {
            $query->where(function (Builder $nested) use ($search): void {
                $nested
                    ->where('data->title', 'like', '%' . $search . '%')
                    ->orWhere('data->body', 'like', '%' . $search . '%');
            });
        }

        return $query;
    }

    protected function baseQuery(): Builder
    {
        $user = Auth::user();

        return FilamentDatabaseNotification::query()
            ->when(! $user, fn (Builder $query): Builder => $query->whereRaw('1 = 0'))
            ->when($user, function (Builder $query) use ($user): Builder {
                return $query
                    ->where('notifiable_type', $user::class)
                    ->where('notifiable_id', $user->getKey());
            })
            ->where(function (Builder $nested): void {
                $nested
                    ->where('data->format', 'filament')
                    ->orWhereNull('data->format');
            });
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
            'report' => 'بلاغ',
            'customer' => 'عميل',
            'store' => 'متجر',
            'subscription' => 'اشتراك',
            'promotion' => 'عرض',
            default => 'تنبيه',
        };
    }

    public function getNotificationAction(array $data): ?array
    {
        $targetUrl = (string) ($data['target_url'] ?? '');

        if ($targetUrl === '') {
            return null;
        }

        $category = $this->resolveNotificationCategory($data);

        $label = match ($category) {
            'customer' => 'عرض العميل',
            'store' => 'عرض المتجر',
            'report' => 'فتح البلاغ',
            'subscription' => 'فتح طلبات الاشتراكات',
            'promotion' => 'فتح العرض',
            default => 'فتح التنبيه',
        };

        $color = match ($category) {
            'report' => 'danger',
            'customer' => 'info',
            'store' => 'warning',
            'subscription' => 'success',
            'promotion' => 'primary',
            default => 'gray',
        };

        return [
            'label' => $label,
            'color' => $color,
            'url' => $targetUrl,
        ];
    }

    private function applyCategoryFilter(Builder $query, string $category): Builder
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

    private function countByCategory(string $category): int
    {
        return $this->applyCategoryFilter(clone $this->baseQuery(), $category)->count();
    }

    public function isAccountNotification(array $data): bool
    {
        return $this->resolveNotificationCategory($data) === 'customer';
    }

    public function isReportNotification(array $data): bool
    {
        return $this->resolveNotificationCategory($data) === 'report';
    }
}
