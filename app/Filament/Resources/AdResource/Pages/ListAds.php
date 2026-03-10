<?php

namespace App\Filament\Resources\AdResource\Pages;

use App\Filament\Resources\AdResource;
use App\Models\Ad;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Artisan;

class ListAds extends ListRecords
{
    protected static string $resource = AdResource::class;

    public string $adminContentType = 'image';

    public string $storeContentType = 'image';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncSubscriptions')
                ->label('مزامنة الاشتراكات')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->action(function (): void {
                    Artisan::call('ads:expire-subscriptions');

                    $output = trim(Artisan::output());

                    Notification::make()
                        ->title('تمت مزامنة الاشتراكات')
                        ->body($output !== '' ? $output : 'اكتملت المزامنة بنجاح.')
                        ->success()
                        ->send();
                }),
            CreateAction::make()->label('إضافة محتوى'),
        ];
    }

    public function getTabs(): array
    {
        $this->ensureValidContentTypes();

        return [
            'admin_content' => Tab::make('محتوى الأدمن')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applySelectedTypeFilter($this->applyContentSourceFilter($query, false), 'admin')),
            'store_content' => Tab::make('محتوى المتاجر')
                ->modifyQueryUsing(fn (Builder $query): Builder => $this->applySelectedTypeFilter($this->applyContentSourceFilter($query, true), 'store')),
        ];
    }

    public function setCurrentTabContentType(string $type): string
    {
        return $this->setContentType($this->isStoreTabActive() ? 'store' : 'admin', $type);
    }

    public function isCurrentType(string $type): bool
    {
        return $this->getCurrentTabContentType() === $type;
    }

    public function getCountForType(string $type): int
    {
        return $this->countByType($this->isStoreTabActive(), $type);
    }

    private function setContentType(string $scope, string $type): string
    {
        if ($scope === 'admin') {
            $this->adminContentType = $type;

            return $type;
        }

        $this->storeContentType = $type;

        return $type;
    }

    private function countByType(bool $isStoreContent, string $type): int
    {
        $query = $this->baseActiveQuery();
        $this->applyContentSourceFilter($query, $isStoreContent);
        $this->applyTypeFilter($query, $type);

        return $query->count();
    }

    private function countTotal(bool $isStoreContent): int
    {
        $query = $this->baseActiveQuery();
        $this->applyContentSourceFilter($query, $isStoreContent);

        return $query->count();
    }

    private function baseActiveQuery(): Builder
    {
        return Ad::query()
            ->withoutGlobalScopes()
            ->where('is_active', true);
    }

    private function applySelectedTypeFilter(Builder $query, string $scope): Builder
    {
        $type = $scope === 'admin' ? $this->adminContentType : $this->storeContentType;

        return $this->applyTypeFilter($query, $type);
    }

    private function ensureValidContentTypes(): void
    {
        $this->adminContentType = $this->resolveBestType(false, $this->adminContentType);
        $this->storeContentType = $this->resolveBestType(true, $this->storeContentType);
    }

    private function resolveBestType(bool $isStoreContent, string $currentType): string
    {
        if ($this->countByType($isStoreContent, $currentType) > 0) {
            return $currentType;
        }

        foreach (['image', 'video', 'promotion'] as $type) {
            if ($this->countByType($isStoreContent, $type) > 0) {
                return $type;
            }
        }

        return 'image';
    }

    private function getCurrentTabContentType(): string
    {
        return $this->isStoreTabActive() ? $this->storeContentType : $this->adminContentType;
    }

    public function isAdminTabActive(): bool
    {
        return ($this->activeTab ?? 'admin_content') === 'admin_content';
    }

    public function isStoreTabActive(): bool
    {
        return ($this->activeTab ?? 'admin_content') === 'store_content';
    }

    private function applyContentSourceFilter(Builder $query, bool $isStoreContent): Builder
    {
        if ($isStoreContent) {
            return $query->where(function (Builder $sourceQuery): void {
                $sourceQuery
                    ->whereNotNull('vendor_id')
                    ->orWhereNotNull('vendor_ad_subscription_id');
            });
        }

        return $query
            ->whereNull('vendor_id')
            ->whereNull('vendor_ad_subscription_id');
    }

    private function applyTypeFilter(Builder $query, string $type): Builder
    {
        if ($type === 'promotion') {
            return $query->where('click_action', 'promotion');
        }

        return $query
            ->where('media_type', $type)
            ->where(function (Builder $typeQuery): void {
                $typeQuery
                    ->whereNull('click_action')
                    ->orWhere('click_action', '!=', 'promotion');
            });
    }
}
