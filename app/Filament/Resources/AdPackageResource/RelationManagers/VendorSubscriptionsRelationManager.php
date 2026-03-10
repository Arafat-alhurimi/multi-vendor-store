<?php

namespace App\Filament\Resources\AdPackageResource\RelationManagers;

use App\Models\Ad;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class VendorSubscriptionsRelationManager extends RelationManager
{
    protected static string $relationship = 'vendorSubscriptions';

    protected static ?string $title = 'اشتراكات الباقة';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['vendor.stores']))
            ->columns([
                Tables\Columns\TextColumn::make('store_name')
                    ->label('اسم المتجر')
                    ->state(fn ($record): string => (string) ($record?->vendor?->stores?->first()?->name ?? '-'))
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('store_city')
                    ->label('المدينة')
                    ->state(fn ($record): string => (string) ($record?->vendor?->stores?->first()?->city ?? '-'))
                    ->visible(fn (): bool => $this->isRequestsTab())
                    ->placeholder('-'),
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->formatStateUsing(fn (string $state, $record): string => match ($state) {
                        'pending' => ($record?->request_type === 'renewal' ? 'طلب تجديد' : 'طلب اشتراك'),
                        'active' => 'مشترك',
                        'expired' => 'منتهي',
                        default => $state,
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'danger' => 'expired',
                    ]),
                Tables\Columns\TextColumn::make('starts_at')
                    ->label('البداية')
                    ->dateTime('Y-m-d H:i')
                    ->visible(fn (): bool => ! $this->isRequestsTab())
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('ends_at')
                    ->label('النهاية')
                    ->dateTime('Y-m-d H:i')
                    ->visible(fn (): bool => ! $this->isRequestsTab())
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('used_images')
                    ->label('صور مستخدمة')
                    ->visible(fn (): bool => ! $this->isRequestsTab()),
                Tables\Columns\TextColumn::make('used_videos')
                    ->label('فيديو مستخدم')
                    ->visible(fn (): bool => ! $this->isRequestsTab()),
                Tables\Columns\TextColumn::make('used_promotions')
                    ->label('عروض مستخدمة')
                    ->visible(fn (): bool => ! $this->isRequestsTab()),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                Tables\Columns\TextColumn::make('request_type')
                    ->label('نوع الطلب')
                    ->state(fn ($record): string => $record?->request_type === 'renewal' ? 'تجديد' : 'اشتراك جديد')
                    ->visible(fn (): bool => $this->isRequestsTab()),
            ])
            ->defaultSort('created_at', 'desc')
            ->headerActions([])
            ->actions([
                Action::make('approve')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record): bool => $this->isRequestsTab() && $record?->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        $this->approveSubscription($record);

                        Notification::make()
                            ->title('تمت الموافقة على الطلب')
                            ->success()
                            ->send();
                    }),
                Action::make('reject')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn ($record): bool => $this->isRequestsTab() && $record?->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function ($record): void {
                        if ($record?->request_type === 'renewal') {
                            $record->update([
                                'status' => 'expired',
                                'request_type' => 'new',
                            ]);
                        } else {
                            $record?->delete();
                        }

                        Notification::make()
                            ->title('تم رفض الطلب')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public function getTabs(): array
    {
        return [
            'subscriptions' => Tab::make('الاشتراكات')
                ->badge((string) $this->getOwnerRecord()->vendorSubscriptions()->where('status', '!=', 'pending')->count())
                ->badgeColor('success')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', '!=', 'pending')),
            'requests' => Tab::make('طلبات الاشتراك')
                ->badge((string) $this->getOwnerRecord()->vendorSubscriptions()->where('status', 'pending')->count())
                ->badgeColor('warning')
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('status', 'pending')),
        ];
    }

    private function isRequestsTab(): bool
    {
        return ($this->activeTab ?? 'subscriptions') === 'requests';
    }

    private function approveSubscription($record): void
    {
        $isRenewal = $record?->request_type === 'renewal';

        $record->update([
            'status' => 'active',
            'used_images' => $isRenewal ? 0 : $record->used_images,
            'used_videos' => $isRenewal ? 0 : $record->used_videos,
            'used_promotions' => $isRenewal ? 0 : $record->used_promotions,
        ]);

        if (! $isRenewal) {
            return;
        }

        $record->refresh();

        Ad::withoutGlobalScopes()
            ->where('vendor_ad_subscription_id', $record->id)
            ->update([
                'is_active' => true,
                'starts_at' => $record->starts_at,
                'ends_at' => $record->ends_at,
            ]);
    }
}
