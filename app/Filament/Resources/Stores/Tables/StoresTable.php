<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Models\Store;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class StoresTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('logo')
                    ->disk('s3')
                    ->label('شعار')
                    ->circular()
                    ->imageHeight(60),
                TextColumn::make('name')
                    ->label('اسم المتجر')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label('البائع')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('city')
                    ->label('المدينة')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('حالة المتجر')
                    ->trueLabel('نشط')
                    ->falseLabel('غير نشط')
                    ->placeholder('الكل'),
                TernaryFilter::make('has_logo')
                    ->label('الشعار')
                    ->trueLabel('يوجد شعار')
                    ->falseLabel('بدون شعار')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('logo')->where('logo', '!=', ''),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                            $inner->whereNull('logo')->orWhere('logo', '');
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_products')
                    ->label('المنتجات')
                    ->trueLabel('لديه منتجات')
                    ->falseLabel('بدون منتجات')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('products'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('products'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_media')
                    ->label('الوسائط')
                    ->trueLabel('لديه وسائط')
                    ->falseLabel('بدون وسائط')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('media'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('media'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('created_last_week')
                    ->label('أضيف آخر أسبوع')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subWeek()),
                        false: fn (Builder $query): Builder => $query->where('created_at', '<', now()->subWeek()),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('created_last_month')
                    ->label('أضيف آخر شهر')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('created_at', '>=', now()->subMonth()),
                        false: fn (Builder $query): Builder => $query->where('created_at', '<', now()->subMonth()),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                SelectFilter::make('city')
                    ->label('المدينة')
                    ->searchable()
                    ->options(fn (): array => Store::query()
                        ->whereNotNull('city')
                        ->where('city', '!=', '')
                        ->distinct()
                        ->orderBy('city')
                        ->pluck('city', 'city')
                        ->toArray()),
            ])
            ->recordActions([
                \Filament\Actions\ViewAction::make(),
                Action::make('approveStore')
                    ->label('تفعيل المتجر')
                    ->color('success')
                    ->icon('heroicon-o-check-circle')
                    ->visible(fn (Store $record) => ! $record->is_active)
                    ->requiresConfirmation()
                    ->action(function (Store $record): void {
                        if (! $record->user?->otp_verified_at) {
                            Notification::make()
                                ->title('لا يمكن التفعيل')
                                ->body('لم يتم التحقق من OTP لصاحب المتجر.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $record->user?->is_active) {
                            Notification::make()
                                ->title('لا يمكن التفعيل')
                                ->body('حساب البائع غير مفعل.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if (! $record->logo) {
                            Notification::make()
                                ->title('لا يمكن التفعيل')
                                ->body('يجب إضافة صورة/شعار للمتجر أولاً.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $record->update(['is_active' => true]);

                        Notification::make()
                            ->title('تم التفعيل')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
