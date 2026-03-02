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
    protected static BackedEnum|string|null $navigationIcon = 'heroicon-o-bell-alert';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $title = 'إدارة التنبيهات';

    protected static ?string $slug = 'notifications-center';

    protected string $view = 'filament.pages.notifications-center';

    public string $category = 'all';

    public string $search = '';

    public function setCategory(string $category): void
    {
        $this->category = in_array($category, ['all', 'report', 'account'], true) ? $category : 'all';
    }

    public function markAsRead(string $id): void
    {
        $this->baseQuery()->whereKey($id)->whereNull('read_at')->update(['read_at' => now()]);
    }

    public function markAsUnread(string $id): void
    {
        $this->baseQuery()->whereKey($id)->whereNotNull('read_at')->update(['read_at' => null]);
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

    public function getAccountCountProperty(): int
    {
        return (clone $this->baseQuery())
            ->where(function (Builder $query): void {
                $query
                    ->where('data->notification_category', 'account')
                    ->orWhere('data->title', 'like', '%مستخدم جديد%');
            })
            ->count();
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

        if ($this->category === 'report') {
            $query->where(function (Builder $nested): void {
                $nested
                    ->where('data->notification_category', 'report')
                    ->orWhere('data->title', 'like', '%بلاغ%');
            });
        } elseif ($this->category === 'account') {
            $query->where(function (Builder $nested): void {
                $nested
                    ->where('data->notification_category', 'account')
                    ->orWhere('data->title', 'like', '%مستخدم جديد%');
            });
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

    public function isAccountNotification(array $data): bool
    {
        $category = (string) ($data['notification_category'] ?? '');
        $title = (string) ($data['title'] ?? '');

        return $category === 'account' || Str::contains($title, 'مستخدم جديد');
    }

    public function isReportNotification(array $data): bool
    {
        $category = (string) ($data['notification_category'] ?? '');
        $title = (string) ($data['title'] ?? '');

        return $category === 'report' || Str::contains($title, 'بلاغ');
    }
}
