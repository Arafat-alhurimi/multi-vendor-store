<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;

class AdminsOnlineNow extends Widget
{
    protected string $view = 'filament.widgets.admins-online-now';

    protected int | string | array $columnSpan = 'full';

    protected static ?string $pollingInterval = '30s';

    public function getViewData(): array
    {
        $cacheKey = 'filament_dashboard_active_admins';
        $ttlSeconds = 120;
        $nowTs = now()->timestamp;

        $active = Cache::get($cacheKey, []);
        if (! is_array($active)) {
            $active = [];
        }

        $user = Auth::user();
        if ($user && $user->role === 'admin') {
            $active[(string) $user->id] = [
                'id' => (int) $user->id,
                'name' => (string) $user->name,
                'last_seen' => $nowTs,
            ];
        }

        // Keep only admins seen recently (dashboard open now).
        $active = array_filter($active, fn (array $item): bool => (($item['last_seen'] ?? 0) >= ($nowTs - $ttlSeconds)));

        $allAdmins = User::query()
            ->where('role', 'admin')
            ->orderBy('name')
            ->get(['id', 'name']);

        $activeById = collect($active)
            ->keyBy(fn (array $item) => (int) ($item['id'] ?? 0));

        $admins = $allAdmins->map(function (User $admin) use ($activeById, $nowTs, $ttlSeconds): array {
            $lastSeen = (int) ($activeById->get($admin->id)['last_seen'] ?? 0);
            $isOnline = $lastSeen >= ($nowTs - $ttlSeconds);

            return [
                'id' => (int) $admin->id,
                'name' => (string) $admin->name,
                'is_online' => $isOnline,
                'status_label' => $isOnline ? 'متصل' : 'غير متصل',
            ];
        })->all();

        $connectedCount = count(array_filter($admins, fn (array $admin): bool => (bool) $admin['is_online']));

        // Persist only currently active admins in cache.
        $cachePayload = collect($admins)
            ->filter(fn (array $admin): bool => (bool) $admin['is_online'])
            ->mapWithKeys(fn (array $admin): array => [
                (string) $admin['id'] => [
                    'id' => $admin['id'],
                    'name' => $admin['name'],
                    'last_seen' => $nowTs,
                ],
            ])
            ->all();

        Cache::put($cacheKey, $cachePayload, now()->addMinutes(10));

        return [
            'connectedCount' => $connectedCount,
            'totalCount' => count($admins),
            'admins' => $admins,
        ];
    }
}
