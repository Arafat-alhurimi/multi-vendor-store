<?php

namespace App\Filament\Resources\VendorOnboardingResource\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\VendorOnboardingResource;
use App\Models\Media;
use App\Models\Store;
use App\Models\User;
use App\Models\VendorFinancialDetail;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class CreateVendorOnboarding extends CreateRecord
{
    protected static string $resource = VendorOnboardingResource::class;

    protected static bool $canCreateAnother = false;

    protected ?Store $createdStore = null;

    protected function handleRecordCreation(array $data): User
    {
        return DB::transaction(function () use ($data): User {
            $vendorData = $data['vendor'] ?? [];
            $storeData = $data['store'] ?? [];
            $financialData = $data['financial'] ?? [];

            $vendor = User::query()->create([
                'name' => (string) ($vendorData['name'] ?? ''),
                'phone' => (string) ($vendorData['phone'] ?? ''),
                'email' => (string) ($vendorData['email'] ?? ''),
                'password' => Hash::make((string) ($vendorData['password'] ?? '')),
                'role' => 'vendor',
                'is_active' => true,
                'otp_verified_at' => now(),
            ]);

            $store = Store::query()->create([
                'user_id' => $vendor->id,
                'name' => (string) ($storeData['name'] ?? ''),
                'description' => $storeData['description'] ?? null,
                'city' => (string) ($storeData['city'] ?? ''),
                'address' => $storeData['address'] ?? null,
                'latitude' => $storeData['latitude'] ?? null,
                'longitude' => $storeData['longitude'] ?? null,
                'logo' => $storeData['logo'] ?? null,
                'is_active' => true,
            ]);

            $categoryIds = $storeData['categories'] ?? [];
            if (is_array($categoryIds) && ! empty($categoryIds)) {
                $store->categories()->sync($categoryIds);
            }

            $this->createStoreMedia($store, $storeData['images'] ?? [], 'image');
            $this->createStoreMedia($store, $storeData['videos'] ?? [], 'video');

            VendorFinancialDetail::query()->create([
                'user_id' => $vendor->id,
                'card_image' => '',
                'back_card_image' => '',
                'kuraimi_account_number' => $financialData['kuraimi_account_number'] ?? null,
                'kuraimi_account_name' => $financialData['kuraimi_account_name'] ?? null,
                'jeeb_id' => $financialData['jeeb_id'] ?? null,
                'jeeb_name' => $financialData['jeeb_name'] ?? null,
                'total_commission_owed' => 0,
            ]);

            $this->createdStore = $store;

            return $vendor;
        });
    }

    protected function afterCreate(): void
    {
        Notification::make()
            ->title('تم إنشاء البائع والمتجر بنجاح')
            ->body('تم حفظ البيانات المالية والوسائط في نفس العملية.')
            ->success()
            ->send();
    }

    protected function getRedirectUrl(): string
    {
        if ($this->createdStore) {
            return StoreResource::getUrl('view', ['record' => $this->createdStore]);
        }

        return StoreResource::getUrl('index');
    }

    private function createStoreMedia(Store $store, mixed $paths, string $fileType): void
    {
        if (! is_array($paths) || empty($paths)) {
            return;
        }

        foreach ($paths as $path) {
            if (! is_string($path) || trim($path) === '') {
                continue;
            }

            $trimmedPath = trim($path);
            /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
            $disk = Storage::disk('s3');
            $url = $disk->url($trimmedPath);

            Media::query()->create([
                'file_name' => basename($trimmedPath),
                'file_type' => $fileType,
                'mime_type' => null,
                'url' => $url,
                'mediable_id' => $store->id,
                'mediable_type' => Store::class,
            ]);
        }
    }
}
