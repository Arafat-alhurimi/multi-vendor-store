<?php

namespace App\Filament\Resources\Promotions;

use App\Filament\Resources\Promotions\Pages\CreatePromotion;
use App\Filament\Resources\Promotions\Pages\EditPromotion;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Resources\Promotions\Pages\ViewPromotion;
use App\Filament\Resources\Promotions\RelationManagers\PromotionItemsRelationManager;
use App\Models\Promotion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-megaphone';

    protected static ?string $navigationLabel = 'الحملات';

    protected static string | \UnitEnum | null $navigationGroup = 'المتاجر';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                TextInput::make('title')
                    ->label('عنوان الحملة')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label('صورة العرض')
                    ->image()
                    ->disk('s3')
                    ->directory('promotions')
                    ->visibility('public')
                    ->imagePreviewHeight(140),

                Hidden::make('level')
                    ->default('app'),

                Select::make('discount_type')
                    ->label('نوع الخصم')
                    ->options([
                        'percentage' => 'Percentage',
                        'fixed' => 'Fixed',
                    ])
                    ->required(),

                TextInput::make('discount_value')
                    ->label('قيمة الخصم')
                    ->numeric()
                    ->required(),

                DateTimePicker::make('starts_at')
                    ->label('تاريخ البدء')
                    ->timezone(config('app.timezone'))
                    ->required(),

                DateTimePicker::make('ends_at')
                    ->label('تاريخ الانتهاء')
                    ->timezone(config('app.timezone'))
                    ->required()
                    ->after('starts_at'),

            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image')
                    ->label('الصورة')
                    ->disk('s3')
                    ->circular()
                    ->defaultImageUrl(null),

                TextColumn::make('title')
                    ->label('العنوان')
                    ->searchable(),

                BadgeColumn::make('level')
                    ->label('المستوى'),

                TextColumn::make('discount_type')
                    ->label('نوع الخصم'),

                TextColumn::make('discount_value')
                    ->label('القيمة')
                    ->sortable(),

                TextColumn::make('starts_at')
                    ->label('يبدأ')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                TextColumn::make('ends_at')
                    ->label('ينتهي')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),

                IconColumn::make('is_active')
                    ->label('نشط')
                    ->boolean(),
            ])
            ->defaultSort('starts_at', 'desc')
            ->recordUrl(fn ($record) => static::getUrl('view', ['record' => $record]))
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PromotionItemsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPromotions::route('/'),
            'create' => CreatePromotion::route('/create'),
            'view' => ViewPromotion::route('/{record}'),
            'edit' => EditPromotion::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('level', 'app');
    }
}
