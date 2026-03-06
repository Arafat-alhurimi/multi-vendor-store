<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListPendingUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'المتاجر بحاجة للموافقة';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'طلبات إنشاء متاجر';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clock';

    protected static string | \UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 3;

    public static function getNavigationBadge(): ?string
    {
        $count = User::query()
            ->where('role', 'vendor')
            ->where('is_active', false)
            ->whereNotNull('otp_verified_at')
            ->whereHas('stores')
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public function getTableQuery(): Builder
    {
        return parent::getTableQuery()
            ->where('role', 'vendor')
            ->where('is_active', false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (User $record): string => $record->store
                ? StoreResource::getUrl('view', ['record' => $record->store])
                : UserResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('name')
                    ->label('اسم البائع')
                    ->searchable(),
                TextColumn::make('phone')
                    ->label('هاتف البائع')
                    ->placeholder('-')
                    ->searchable(),
                IconColumn::make('otp_verified_at')
                    ->label('تم التحقق OTP')
                    ->boolean()
                    ->state(fn (User $record): bool => filled($record->otp_verified_at)),
                TextColumn::make('store_name')
                    ->label('المتجر')
                    ->state(fn (User $record): string => $record->store?->name ?: 'لم ينشئ متجر بعد')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('stores', fn (Builder $storesQuery): Builder => $storesQuery->where('name', 'like', "%{$search}%"))),
                TextColumn::make('store_city')
                    ->label('مدينة المتجر')
                    ->state(fn (User $record): string => $record->store?->city ?: '-')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas('stores', fn (Builder $storesQuery): Builder => $storesQuery->where('city', 'like', "%{$search}%"))),
                TextColumn::make('store_status')
                    ->label('حالة المتجر')
                    ->badge()
                    ->state(fn (User $record): string => match (true) {
                        ! $record->store => 'لا يوجد متجر',
                        (bool) $record->store?->is_active => 'نشط',
                        default => 'غير نشط',
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'نشط' => 'success',
                        'غير نشط' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('approval_status')
                    ->label('حالة الطلب')
                    ->badge()
                    ->state(fn (User $record): string => $this->getApprovalStatusLabel($record))
                    ->color(fn (User $record): string => $this->getApprovalStatusColor($record)),
            ])
            ->actions([
                Action::make('approveVendor')
                    ->label('موافقة وتفعيل')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->visible(fn (User $record): bool => $this->canApprove($record))
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $this->approveVendor($record);
                    }),
            ])
            ->bulkActions([]);
    }

    protected function canApprove(User $record): bool
    {
        return $record->role === 'vendor'
            && ! $record->is_active
            && filled($record->otp_verified_at)
            && (bool) $record->store;
    }

    protected function getApprovalStatusLabel(User $record): string
    {
        return match (true) {
            $record->is_active => 'تمت الموافقة',
            ! filled($record->otp_verified_at) => 'بانتظار التحقق من OTP',
            ! $record->store => 'لم ينشئ متجر بعد',
            default => 'جاهز للموافقة',
        };
    }

    protected function getApprovalStatusColor(User $record): string
    {
        return match (true) {
            $record->is_active => 'success',
            ! filled($record->otp_verified_at) => 'warning',
            ! $record->store => 'gray',
            default => 'success',
        };
    }

    protected function approveVendor(User $record): void
    {
        if (! $this->canApprove($record)) {
            Notification::make()
                ->title('لا يمكن الموافقة')
                ->body($this->getApprovalStatusLabel($record))
                ->danger()
                ->send();

            return;
        }

        $record->update(['is_active' => true]);
        $record->store?->update(['is_active' => true]);

        Notification::make()
            ->title('تمت الموافقة')
            ->body('تم تفعيل حساب البائع والمتجر بنجاح.')
            ->success()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pendingStoresInfo')
                ->label(fn (): string => 'المتاجر الجاهزة للموافقة: '.(string) (User::query()
                    ->where('role', 'vendor')
                    ->where('is_active', false)
                    ->whereNotNull('otp_verified_at')
                    ->whereHas('stores')
                    ->count()))
                ->icon('heroicon-o-bell-alert')
                ->color('warning')
                ->disabled(),
        ];
    }
}
