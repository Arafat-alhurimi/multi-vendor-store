<?php

namespace App\Filament\Resources\StorePromotions\Pages;

use App\Filament\Resources\StorePromotions\StorePromotionResource;
use App\Filament\Resources\StorePromotions\Widgets\StorePromotionsStatsOverview;
use App\Models\Promotion;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Support\Facades\Artisan;

class ListStorePromotions extends ListRecords
{
    protected static string $resource = StorePromotionResource::class;

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
                        ->title('تمت مزامنة عروض المتاجر بنجاح')
                        ->body($output !== '' ? $output : 'اكتملت المزامنة.')
                        ->success()
                        ->send();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            StorePromotionsStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        $baseQuery = Promotion::query()->where('level', 'store');

        return [
            'all' => Tab::make('الكل')
                ->badge((string) (clone $baseQuery)->count()),
            'active' => Tab::make('النشطة')
                ->badge((string) (clone $baseQuery)->where('is_active', true)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)),
            'inactive' => Tab::make('غير النشطة')
                ->badge((string) (clone $baseQuery)->where('is_active', false)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', false)),
        ];
    }
}
