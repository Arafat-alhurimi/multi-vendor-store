<?php

namespace App\Filament\Resources\Stores\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    protected static ?string $title = 'طلبات المتجر';

    public function form(Schema $schema): Schema
    {
        return $schema->schema([]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('order_number')->label('رقم الطلب')->searchable(),
                Tables\Columns\TextColumn::make('user.name')->label('العميل')->searchable(),
                Tables\Columns\TextColumn::make('product.name_ar')->label('المنتج')->searchable(),
                Tables\Columns\TextColumn::make('quantity')->label('الكمية'),
                Tables\Columns\TextColumn::make('total_price')->label('الإجمالي'),
                Tables\Columns\BadgeColumn::make('status')->label('الحالة'),
                Tables\Columns\BadgeColumn::make('payment_status')->label('حالة الدفع'),
                Tables\Columns\TextColumn::make('created_at')->label('التاريخ')->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('حالة الطلب')
                    ->options([
                        'pending' => 'pending',
                        'processing' => 'processing',
                        'delivered' => 'delivered',
                        'cancelled' => 'cancelled',
                    ]),
                SelectFilter::make('payment_status')
                    ->label('حالة الدفع')
                    ->options([
                        'pending' => 'pending',
                        'verified' => 'verified',
                    ]),
            ])
            ->headerActions([])
            ->actions([]);
    }
}
