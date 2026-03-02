<?php

namespace App\Filament\Resources\Stores\Tables;

use App\Models\Store;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

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
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('البائع')
                    ->searchable(),
                TextColumn::make('city')
                    ->label('المدينة')
                    ->searchable(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),
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
