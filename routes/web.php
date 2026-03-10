<?php

use App\Http\Controllers\S3DirectUploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('filament.admin.auth.login');
});

// Simple admin notifications page for viewing details
Route::get('/admin/notifications', function () {
    $query = \Illuminate\Support\Facades\DB::table('filament_notifications')->orderBy('created_at', 'desc');

    if ($userId = request('user_id')) {
        $query->where('data', 'like', '%"user_id":'. $userId .'%');
    }

    $rows = $query->limit(200)->get()->map(function ($row) {
        $data = json_decode($row->data, true) ?: [];

        return [
            'id' => $row->id,
            'type' => $row->type,
            'notifiable_type' => $row->notifiable_type,
            'notifiable_id' => $row->notifiable_id,
            'data' => $data,
            'created_at' => $row->created_at,
            'read_at' => $row->read_at,
        ];
    });

    return view('admin.notifications', [
        'rows' => $rows,
        'user_id' => request('user_id'),
    ]);
});

// Delete notification (simple POST endpoint)
Route::post('/admin/notifications/delete/{id}', function ($id) {
    \Illuminate\Support\Facades\DB::table('filament_notifications')->where('id', $id)->delete();

    return back();
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::post('/admin/presence/ping', function () {
        $user = request()->user();

        if (! $user || $user->role !== 'admin') {
            return response()->json(['ok' => false], 403);
        }

        $cacheKey = 'filament_dashboard_active_admins';
        $ttlSeconds = 120;
        $nowTs = now()->timestamp;

        $active = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        if (! is_array($active)) {
            $active = [];
        }

        // Keep active admins only within the recent heartbeat window.
        $active = array_filter($active, fn (array $item): bool => (($item['last_seen'] ?? 0) >= ($nowTs - $ttlSeconds)));

        $active[(string) $user->id] = [
            'id' => (int) $user->id,
            'name' => (string) $user->name,
            'last_seen' => $nowTs,
        ];

        \Illuminate\Support\Facades\Cache::put($cacheKey, $active, now()->addMinutes(10));

        return response()->json(['ok' => true]);
    })->name('admin.presence.ping');

    Route::post('/admin/presence/offline', function () {
        $user = request()->user();

        if (! $user || $user->role !== 'admin') {
            return response()->json(['ok' => false], 403);
        }

        $cacheKey = 'filament_dashboard_active_admins';
        $active = \Illuminate\Support\Facades\Cache::get($cacheKey, []);

        if (! is_array($active)) {
            $active = [];
        }

        unset($active[(string) $user->id]);

        \Illuminate\Support\Facades\Cache::put($cacheKey, $active, now()->addMinutes(10));

        return response()->json(['ok' => true]);
    })->name('admin.presence.offline');

    Route::post('/s3-direct/sign-put', [S3DirectUploadController::class, 'signPut'])
        ->name('s3-direct.sign-put');
    Route::post('/s3-direct/attach-uploaded', [S3DirectUploadController::class, 'attachUploaded'])
        ->name('s3-direct.attach-uploaded');
});
