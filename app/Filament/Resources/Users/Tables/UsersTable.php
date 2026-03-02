<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\Action;
use Filament\Tables\Columns\BooleanColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use App\Models\User;
use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\UserResource;

class UsersTable
{
    public static function configure(Table $table): Table
    {
    return $table
        ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record]))
        ->columns([
            TextColumn::make('name')->label('الاسم'),
            TextColumn::make('phone')->label('رقم الهاتف')->placeholder('-'),
            TextColumn::make('role')
                ->label('نوع المستخدم')
                ->formatStateUsing(fn (string $state): string => match ($state) {
                    'admin' => 'مدير',
                    'vendor' => 'بائع',
                    'customer' => 'عميل',
                    default => $state,
                }),
            IconColumn::make('otp_verified_at')
                ->label('تم التحقق')
                ->boolean()
                ->state(fn (User $record): bool => filled($record->otp_verified_at)),
            BooleanColumn::make('is_active')->label('نشط'),
        ])
        ->filters([
            SelectFilter::make('role')
                ->label('نوع المستخدم')
                ->options([
                    'admin' => 'مدير',
                    'vendor' => 'بائع',
                    'customer' => 'عميل',
                ]),
        ])
        ->actions([
            Action::make('viewStore')
                ->label('المتجر')
                ->icon('heroicon-o-building-storefront')
                ->url(fn (User $record) => $record->store
                    ? StoreResource::getUrl('view', ['record' => $record->store])
                    : null)
                ->visible(fn (User $record) => $record->is_seller),
        ])
        ->bulkActions([]);

        }
}
