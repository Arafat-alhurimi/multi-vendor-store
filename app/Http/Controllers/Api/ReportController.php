<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Product;
use App\Models\Store;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReportController extends Controller
{
    public function store(Request $request, ?Product $product = null, ?Store $store = null, ?Comment $comment = null)
    {
        $user = $request->user();

        if (! $user || $user->role !== 'customer') {
            return response()->json(['message' => 'التبليغ متاح للعملاء فقط.'], 403);
        }

        $target = $product ?? $store ?? $comment;

        if (! $target) {
            return response()->json(['message' => 'العنصر غير صالح للتبليغ.'], 422);
        }

        $data = $request->validate([
            'reason' => 'required|string',
        ]);

        $existing = $target->reports()->where('user_id', $user->id)->first();

        if ($existing) {
            return response()->json(['message' => 'تم إرسال تبليغ مسبقاً على هذا العنصر.'], 422);
        }

        $report = $target->reports()->create([
            'user_id' => $user->id,
            'reason' => $data['reason'],
        ]);

        $targetUrl = null;

        if ($target instanceof Product) {
            $targetUrl = ProductResource::getUrl('view', ['record' => $target]);
        } elseif ($target instanceof Store) {
            $targetUrl = StoreResource::getUrl('view', ['record' => $target]);
        } elseif ($target instanceof Comment) {
            $commentable = $target->commentable;

            if ($commentable instanceof Product) {
                $targetUrl = ProductResource::getUrl('view', ['record' => $commentable]);
            } elseif ($commentable instanceof Store) {
                $targetUrl = StoreResource::getUrl('view', ['record' => $commentable]);
            }
        }

        $admins = User::query()->where('role', 'admin')->get();

        foreach ($admins as $admin) {
            $notification = Notification::make()
                ->title('بلاغ جديد')
                ->body('تم استلام بلاغ جديد ويتطلب المراجعة.')
                ->warning();

            if ($targetUrl) {
                $notification->actions([
                    Action::make('open')
                        ->label('فتح العنصر')
                        ->url($targetUrl),
                ]);
            }

            $payload = $notification->toArray();
            unset($payload['id']);
            $payload['format'] = 'filament';
            $payload['duration'] = 'persistent';
            $payload['notification_category'] = 'report';

            if ($targetUrl) {
                $payload['target_url'] = $targetUrl;
            }

            DB::table('filament_notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\ReportCreated',
                'notifiable_type' => get_class($admin),
                'notifiable_id' => $admin->getKey(),
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'message' => 'تم إرسال التبليغ بنجاح',
            'report' => $report,
        ], 201);
    }
}
