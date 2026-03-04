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
    Route::post('/s3-direct/sign-put', [S3DirectUploadController::class, 'signPut'])
        ->name('s3-direct.sign-put');
    Route::post('/s3-direct/attach-uploaded', [S3DirectUploadController::class, 'attachUploaded'])
        ->name('s3-direct.attach-uploaded');
});
