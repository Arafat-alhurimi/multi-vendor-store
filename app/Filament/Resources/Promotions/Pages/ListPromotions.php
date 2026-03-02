<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Artisan;

class ListPromotions extends ListRecords
{
    protected static string $resource = PromotionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncPromotions')
                ->label('مزامنة العروض')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('promotions:deactivate-expired');

                    $output = trim(Artisan::output());

                    Notification::make()
                        ->title('تمت مزامنة العروض بنجاح')
                        ->body($output !== '' ? $output : 'اكتملت المزامنة.')
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
