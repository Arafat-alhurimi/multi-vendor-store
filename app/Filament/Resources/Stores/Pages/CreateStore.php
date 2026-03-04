<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Models\User;
use App\Models\VendorFinancialDetail;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateStore extends CreateRecord
{
    protected static string $resource = StoreResource::class;

    protected static bool $canCreateAnother = false;

    protected ?int $lastCreatedStoreId = null;

    protected function getSubmitFormLivewireMethodName(): string
    {
        return 'createAnother';
    }

    protected function getRedirectUrl(): string
    {
        return url()->current();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $requestedUserId = (int) request()->query('user_id', 0);

        if ($requestedUserId > 0) {
            $vendor = User::query()
                ->whereKey($requestedUserId)
                ->where('role', 'vendor')
                ->first();

            if (! $vendor) {
                throw ValidationException::withMessages([
                    'user_id' => 'المستخدم المحدد ليس بائعًا صالحًا.',
                ]);
            }

            if ($vendor->store) {
                throw ValidationException::withMessages([
                    'user_id' => 'هذا البائع لديه متجر بالفعل.',
                ]);
            }

            $data['user_id'] = $vendor->id;
        }

        $vendorId = $data['user_id'] ?? null;

        if (! $vendorId || ! User::query()->whereKey($vendorId)->where('role', 'vendor')->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'لا يمكن إنشاء متجر إلا لحساب بائع.',
            ]);
        }

        if (User::query()->whereKey($vendorId)->whereHas('stores')->exists()) {
            throw ValidationException::withMessages([
                'user_id' => 'لا يمكن إنشاء أكثر من متجر لهذا البائع.',
            ]);
        }

        $data['logo'] = is_string($data['logo'] ?? null) ? $data['logo'] : null;

        return $data;
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();

        if (! $record) {
            return;
        }

        $financial = Arr::get($this->data, 'financial', []);
        $existingFinancial = VendorFinancialDetail::query()->where('user_id', $record->user_id)->first();

        VendorFinancialDetail::query()->updateOrCreate(
            ['user_id' => $record->user_id],
            [
                'card_image' => $existingFinancial?->card_image ?? '',
                'back_card_image' => $existingFinancial?->back_card_image ?? '',
                'kuraimi_account_number' => $financial['kuraimi_account_number'] ?? null,
                'kuraimi_account_name' => $financial['kuraimi_account_name'] ?? null,
                'jeeb_id' => $financial['jeeb_id'] ?? null,
                'jeeb_name' => $financial['jeeb_name'] ?? null,
                'total_commission_owed' => $existingFinancial?->total_commission_owed ?? 0,
            ],
        );

        $this->lastCreatedStoreId = (int) $record->id;
        $this->dispatch(
            'store-created',
            storeId: $this->lastCreatedStoreId,
            storeUrl: StoreResource::getUrl('view', ['record' => $record]),
        );

        Notification::make()
            ->title('تم إنشاء المتجر وحفظ البيانات. جاري رفع الوسائط بالخلفية...')
            ->success()
            ->send();
    }
}
