<?php

namespace App\Filament\Resources\Promotions\Pages;

use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\Promotions\Widgets\PromotionsStatsOverview;
use App\Models\Promotion;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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

    protected function getHeaderWidgets(): array
    {
        return [
            PromotionsStatsOverview::class,
        ];
    }

    public function getTabs(): array
    {
        return [
            'all' => Tab::make('الكل')
                ->badge((string) Promotion::query()->where('level', 'app')->count()),
            'active' => Tab::make('النشطة')
                ->badge((string) Promotion::query()->where('level', 'app')->where('is_active', true)->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', true)),
            'inactive' => Tab::make('غير النشطة')
                ->badge((string) Promotion::query()->where('level', 'app')->where('is_active', false)->count())
                ->badgeColor('danger')
                ->modifyQueryUsing(fn ($query) => $query->where('is_active', false)),
        ];
    }
}
