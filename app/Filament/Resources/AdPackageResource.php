<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdPackageResource\Pages;
use App\Filament\Resources\AdPackageResource\RelationManagers\VendorSubscriptionsRelationManager;
use App\Models\AdPackage;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdPackageResource extends Resource
{
    protected static ?string $model = AdPackage::class;

    protected static ?string $modelLabel = 'باقة';

    protected static ?string $pluralModelLabel = 'الباقات';

    protected static ?string $navigationLabel = 'الباقات';

    protected static string | \UnitEnum | null $navigationGroup = 'إدارة الباقات';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            TextInput::make('name')->label('اسم الباقة')->required()->maxLength(255),
            TextInput::make('price')->label('السعر')->numeric()->required(),
            TextInput::make('duration_days')->label('مدة الباقة بالأيام')->numeric()->required(),
            TextInput::make('max_images')->label('الحد الأقصى للصور')->numeric()->required(),
            TextInput::make('max_videos')->label('الحد الأقصى للفيديو')->numeric()->required(),
            TextInput::make('max_promotions')->label('الحد الأقصى لنقرات العروض')->numeric()->required(),
            Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('الباقة')->searchable(),
                TextColumn::make('price')->label('السعر')->numeric(2)->sortable(),
                TextColumn::make('duration_days')->label('المدة')->sortable(),
                TextColumn::make('max_images')->label('صور')->sortable(),
                TextColumn::make('max_videos')->label('فيديو')->sortable(),
                TextColumn::make('max_promotions')->label('عروض')->sortable(),
                TextColumn::make('subscribers_count')
                    ->label('المشتركين')
                    ->badge()
                    ->color('info')
                    ->sortable(),
                TextColumn::make('pending_requests_count')
                    ->label('طلبات الاشتراك')
                    ->badge()
                    ->color('warning')
                    ->sortable(),
                IconColumn::make('is_active')->label('نشط')->boolean(),
                TextColumn::make('created_at')
                    ->label('تاريخ الإضافة')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('الحالة')
                    ->trueLabel('نشطة')
                    ->falseLabel('غير نشطة')
                    ->placeholder('الكل'),
                TernaryFilter::make('has_subscribers')
                    ->label('يوجد مشتركين')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('vendorSubscriptions'),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('vendorSubscriptions'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('has_pending_requests')
                    ->label('يوجد طلبات اشتراك')
                    ->trueLabel('نعم')
                    ->falseLabel('لا')
                    ->placeholder('الكل')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereHas('vendorSubscriptions', fn (Builder $subscriptionQuery): Builder => $subscriptionQuery->where('status', 'pending')),
                        false: fn (Builder $query): Builder => $query->whereDoesntHave('vendorSubscriptions', fn (Builder $subscriptionQuery): Builder => $subscriptionQuery->where('status', 'pending')),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                Filter::make('price_range')
                    ->label('نطاق السعر')
                    ->form([
                        TextInput::make('min_price')->label('من')->numeric(),
                        TextInput::make('max_price')->label('إلى')->numeric(),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['min_price'] ?? null, fn (Builder $builder, $value): Builder => $builder->where('price', '>=', $value))
                            ->when($data['max_price'] ?? null, fn (Builder $builder, $value): Builder => $builder->where('price', '<=', $value));
                    }),
                Filter::make('created_between')
                    ->label('تاريخ الإضافة')
                    ->form([
                        DatePicker::make('from')->label('من'),
                        DatePicker::make('until')->label('إلى'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('created_at', '>=', $date))
                            ->when($data['until'] ?? null, fn (Builder $builder, $date): Builder => $builder->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            VendorSubscriptionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdPackages::route('/'),
            'create' => Pages\CreateAdPackage::route('/create'),
            'view' => Pages\ViewAdPackage::route('/{record}'),
            'edit' => Pages\EditAdPackage::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount([
                'vendorSubscriptions as subscribers_count',
                'vendorSubscriptions as pending_requests_count' => fn (Builder $query): Builder => $query->where('status', 'pending'),
            ]);
    }
}
