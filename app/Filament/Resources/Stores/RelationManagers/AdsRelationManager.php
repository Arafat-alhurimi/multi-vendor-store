<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdsRelationManager extends RelationManager
{
    protected static string $relationship = 'ads';

    protected static ?string $title = 'المحتوى الإعلاني النشط';

    protected static array $productNamesCache = [];

    protected static array $storeNamesCache = [];

    protected static array $promotionTitlesCache = [];

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        $now = now();

        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query
                ->withoutGlobalScopes()
                ->where('is_active', true)
                ->where(function (Builder $inner) use ($now): void {
                    $inner->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function (Builder $inner) use ($now): void {
                    $inner->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                }))
            ->columns([
                Tables\Columns\BadgeColumn::make('media_type')
                    ->label('النوع')
                    ->formatStateUsing(fn (?string $state): string => $state === 'video' ? 'فيديو' : 'صورة')
                    ->colors([
                        'info' => 'image',
                        'warning' => 'video',
                    ]),
                Tables\Columns\TextColumn::make('transition_type')
                    ->label('نوع الانتقال')
                    ->state(fn ($record): string => $this->translateActionType($record->click_action))
                    ->badge()
                    ->color('primary'),
                Tables\Columns\TextColumn::make('transition_target')
                    ->label('ينتقل إلى')
                    ->state(fn ($record): string => $this->resolveTransitionTarget($record))
                    ->wrap(),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('يبدأ')
                    ->dateTime('Y-m-d H:i'),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('ينتهي')
                    ->dateTime('Y-m-d H:i'),
            ])
            ->recordAction('previewContent')
            ->actions([
                Action::make('previewContent')
                    ->label('تفاصيل')
                    ->icon('heroicon-o-eye')
                    ->modalHeading('تفاصيل المحتوى الإعلاني')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn ($record) => view('filament.ads.store-content-preview', [
                        'record' => $record,
                        'storeName' => (string) ($this->getOwnerRecord()->name ?? '-'),
                        'transitionType' => $this->translateActionType($record->click_action),
                        'transitionTarget' => $this->resolveTransitionTarget($record),
                    ])),
            ])
            ->headerActions([]);
    }

    private function translateActionType(?string $action): string
    {
        return match ($action) {
            'promotion' => 'عرض',
            'product' => 'منتج',
            'store' => 'متجر',
            'url' => 'رابط',
            default => 'غير محدد',
        };
    }

    private function resolveTransitionTarget($record): string
    {
        $actionId = $record->action_id;

        if (! filled($actionId)) {
            return '-';
        }

        return match ($record->click_action) {
            'promotion' => $this->resolvePromotionTitle((int) $actionId),
            'product' => $this->resolveProductName((int) $actionId),
            'store' => $this->resolveStoreName((int) $actionId),
            'url' => (string) $actionId,
            default => (string) $actionId,
        };
    }

    private function resolveProductName(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        if (isset(static::$productNamesCache[$id])) {
            return static::$productNamesCache[$id];
        }

        $name = Product::query()->whereKey($id)->value('name_ar');

        return static::$productNamesCache[$id] = (string) ($name ?? 'منتج غير موجود');
    }

    private function resolveStoreName(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        if (isset(static::$storeNamesCache[$id])) {
            return static::$storeNamesCache[$id];
        }

        $name = Store::query()->whereKey($id)->value('name');

        return static::$storeNamesCache[$id] = (string) ($name ?? 'متجر غير موجود');
    }

    private function resolvePromotionTitle(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        if (isset(static::$promotionTitlesCache[$id])) {
            return static::$promotionTitlesCache[$id];
        }

        $title = Promotion::query()->whereKey($id)->value('title');

        return static::$promotionTitlesCache[$id] = (string) ($title ?? 'عرض غير موجود');
    }
}
