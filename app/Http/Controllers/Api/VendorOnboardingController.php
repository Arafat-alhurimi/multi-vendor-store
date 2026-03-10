<?php

namespace App\Http\Controllers\Api;

use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\UserResource;
use App\Http\Controllers\Controller;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorFinancialDetail;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorOnboardingController extends Controller
{
    public function presignBatch(Request $request)
    {
        $data = $request->validate([
            'files' => 'required|array|min:1',
            'files.*.file_name' => 'required|string|max:255',
            'files.*.mime_type' => 'required|string|max:255',
            'files.*.kind' => 'required|in:store_logo,store_image,store_video,id_front,id_back',
            'files.*.expires_in' => 'nullable|integer|min:120|max:7200',
        ]);

        $uploads = collect($data['files'])->map(function (array $file) {
            $request = new Request($file);

            return $this->presign($request)->getData(true);
        })->values()->all();

        return response()->json([
            'uploads' => $uploads,
        ]);
    }

    public function presign(Request $request)
    {
        $data = $request->validate([
            'file_name' => 'required|string|max:255',
            'mime_type' => 'required|string|max:255',
            'kind' => 'required|in:store_logo,store_image,store_video,id_front,id_back',
            'expires_in' => 'nullable|integer|min:120|max:7200',
        ]);

        $directory = match ($data['kind']) {
            'store_logo' => 'stores/logos',
            'store_image' => 'stores/media/images',
            'store_video' => 'stores/media/videos',
            'id_front', 'id_back' => 'vendors/ids',
        };

        $mimeType = $data['mime_type'];
        $expiresIn = (int) ($data['expires_in'] ?? 1200);

        $originalName = basename($data['file_name']);
        $extension = pathinfo($originalName, PATHINFO_EXTENSION);
        $baseName = pathinfo($originalName, PATHINFO_FILENAME);
        $safeName = trim(Str::slug($baseName)) ?: 'file';
        $finalName = $safeName . ($extension ? '.' . $extension : '');
        $key = $directory . '/' . Str::uuid() . '-' . $finalName;

        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::disk('s3');
        $client = $disk->getClient();
        $bucket = config('filesystems.disks.s3.bucket');

        $command = $client->getCommand('PutObject', [
            'Bucket' => $bucket,
            'Key' => $key,
            'ContentType' => $mimeType,
        ]);

        $presigned = $client->createPresignedRequest($command, now()->addSeconds($expiresIn));

        return response()->json([
            'upload_url' => (string) $presigned->getUri(),
            'file_url' => $disk->url($key),
            'key' => $key,
            'kind' => $data['kind'],
            'mime_type' => $mimeType,
            'expires_in' => $expiresIn,
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:30|unique:users,phone',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',

            'store' => 'required|array',
            'store.name' => 'required|string|max:255',
            'store.description' => 'nullable|string',
            'store.city' => 'required|string|max:255',
            'store.address' => 'nullable|string|max:255',
            'store.latitude' => 'nullable|numeric',
            'store.longitude' => 'nullable|numeric',
            'store.logo_url' => 'nullable|url|max:2048',
            'store.categories' => 'nullable|array',
            'store.categories.*' => 'integer|exists:categories,id',
            'store.media' => 'nullable|array',
            'store.media.*.url' => 'required_with:store.media|url|max:2048',
            'store.media.*.file_type' => 'nullable|in:image,video',
            'store.media.*.file_name' => 'nullable|string|max:255',
            'store.media.*.mime_type' => 'nullable|string|max:255',

            'financial' => 'required|array',
            'financial.card_image' => 'nullable|url|max:2048',
            'financial.back_card_image' => 'nullable|url|max:2048',
            'financial.kuraimi_account_number' => 'nullable|string|max:50',
            'financial.kuraimi_account_name' => 'nullable|string|max:255',
            'financial.jeeb_id' => 'nullable|string|max:100',
            'financial.jeeb_name' => 'nullable|string|max:255',
        ]);

        $result = DB::transaction(function () use ($data) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password' => Hash::make($data['password']),
                'role' => 'vendor',
                'is_active' => false,
                'otp_verified_at' => null,
            ]);

                $storeData = $data['store'];
                $categories = $storeData['categories'] ?? [];
                $mediaItems = $storeData['media'] ?? [];

                $store = Store::create([
                    'user_id' => $user->id,
                    'name' => $storeData['name'],
                    'description' => $storeData['description'] ?? null,
                    'city' => $storeData['city'],
                    'address' => $storeData['address'] ?? null,
                    'latitude' => $storeData['latitude'] ?? null,
                    'longitude' => $storeData['longitude'] ?? null,
                    'logo' => $storeData['logo_url'] ?? null,
                    'is_active' => false,
                ]);

                if (! empty($categories)) {
                    $store->categories()->sync($categories);
                }

                foreach ($mediaItems as $mediaItem) {
                    $mimeType = $mediaItem['mime_type'] ?? null;
                    $fileType = $this->resolveFileType(
                        $mediaItem['file_type'] ?? null,
                        $mimeType,
                        $mediaItem['url']
                    );

                    $store->media()->create([
                        'file_name' => $mediaItem['file_name']
                            ?? basename(parse_url($mediaItem['url'], PHP_URL_PATH) ?: 'store-media-file'),
                        'file_type' => $fileType,
                        'mime_type' => $mimeType,
                        'url' => $mediaItem['url'],
                    ]);
                }

                $financialData = $data['financial'];
                $financial = VendorFinancialDetail::create([
                    'user_id' => $user->id,
                    'card_image' => $financialData['card_image'] ?? '',
                    'back_card_image' => $financialData['back_card_image'] ?? '',
                    'kuraimi_account_number' => $financialData['kuraimi_account_number'] ?? null,
                    'kuraimi_account_name' => $financialData['kuraimi_account_name'] ?? null,
                    'jeeb_id' => $financialData['jeeb_id'] ?? null,
                    'jeeb_name' => $financialData['jeeb_name'] ?? null,
                    'total_commission_owed' => 0,
                ]);

                return [
                    'user' => $user,
                    'store' => $store,
                    'financial' => $financial,
                ];
        });

            $this->notifyAdminsAboutStoreRequest($result['user'], $result['store']);

        return response()->json([
            'status' => 'success',
            'message' => 'تم حفظ بيانات البائع. أكمل التحقق عبر /verify-otp لتأكيد الحساب.',
            'user' => $result['user'],
            'store' => $result['store']->load('categories', 'media'),
            'financial_detail' => $result['financial'],
            'approval_status' => 'بانتظار التحقق من OTP',
        ], 201);
    }

    private function notifyAdminsAboutStoreRequest(User $user, Store $store): void
    {
        $admins = User::query()->where('role', 'admin')->get();
        $storeUrl = StoreResource::getUrl('view', ['record' => $store]);
        $pendingStoresUrl = UserResource::getUrl('pending');
        $targetUrl = $storeUrl ?: $pendingStoresUrl;

        foreach ($admins as $admin) {
            $notification = Notification::make()
                ->title('طلب متجر جديد')
                ->body("تم تسجيل متجر جديد: {$store->name} بواسطة {$user->name} وهو بانتظار المراجعة.")
                ->warning()
                ->actions([
                    Action::make('openStore')
                        ->label('عرض المتجر')
                        ->url($targetUrl),
                ]);

            $payload = $notification->toArray();
            unset($payload['id']);
            $payload['format'] = 'filament';
            $payload['duration'] = 'persistent';
            $payload['notification_category'] = 'store';
            $payload['target_url'] = $targetUrl;
            $payload['user_id'] = $user->id;
            $payload['store_id'] = $store->id;

            DB::table('filament_notifications')->insert([
                'id' => (string) Str::uuid(),
                'type' => 'App\\Notifications\\StoreOnboardingRequested',
                'notifiable_type' => get_class($admin),
                'notifiable_id' => $admin->getKey(),
                'data' => json_encode($payload, JSON_UNESCAPED_UNICODE),
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function resolveFileType(?string $fileType, ?string $mimeType, string $url): string
    {
        if ($fileType && in_array($fileType, ['image', 'video'], true)) {
            return $fileType;
        }

        if ($mimeType) {
            if (str_starts_with($mimeType, 'video/')) {
                return 'video';
            }

            if (str_starts_with($mimeType, 'image/')) {
                return 'image';
            }
        }

        return str_contains(strtolower($url), '/videos/') ? 'video' : 'image';
    }

}
