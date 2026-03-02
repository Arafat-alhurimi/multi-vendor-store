<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditPromotion extends EditRecord
{
    protected static string $resource = PromotionResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        unset($data['is_active']);

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('deactivatePromotion')
                ->label('إلغاء التنشيط')
                ->color('danger')
                ->icon('heroicon-o-pause-circle')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record->update([
                        'is_active' => false,
                        'ends_at' => now(),
                    ]);

                    Notification::make()
                        ->title('تم إلغاء تنشيط العرض')
                        ->success()
                        ->send();

                    $this->refreshFormData(['is_active', 'ends_at']);
                }),
            DeleteAction::make(),
        ];
    }
}
