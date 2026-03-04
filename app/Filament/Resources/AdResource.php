<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdResource\Pages;
use App\Models\Ad;
use App\Models\Promotion;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AdResource extends Resource
{
    protected static ?string $model = Ad::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $navigationLabel = 'الإعلانات';

    protected static string | \UnitEnum | null $navigationGroup = 'الإعلانات';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    public static function canViewAny(): bool
    {
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('media_type')
                ->label('نوع الوسائط')
                ->options([
                    'image' => 'Image',
                    'video' => 'Video',
                ])
                ->required(),
            FileUpload::make('media_path')
                ->label('الوسائط')
                ->disk('s3')
                ->directory('ads-media')
                ->visibility('public')
                ->required(),
            Select::make('click_action')
                ->label('إجراء النقر')
                ->options([
                    'promotion' => 'Promotion',
                    'product' => 'Product',
                    'store' => 'Store',
                    'url' => 'URL',
                ])
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set): void {
                    if ($state !== 'promotion') {
                        $set('promotion_id', null);
                    }
                })
                ->required(),
            Select::make('promotion_id')
                ->label('العرض')
                ->options(fn () => Promotion::query()
                    ->currentlyActive(now())
                    ->orderBy('title')
                    ->pluck('title', 'id')
                    ->toArray())
                ->searchable()
                ->preload()
                ->reactive()
                ->visible(fn (callable $get): bool => $get('click_action') === 'promotion')
                ->required(fn (callable $get): bool => $get('click_action') === 'promotion')
                ->afterStateUpdated(function ($state, callable $set): void {
                    $set('action_id', $state ? (string) $state : null);

                    if (! $state) {
                        return;
                    }

                    $promotionImage = Promotion::query()->whereKey($state)->value('image');

                    if ($promotionImage) {
                        $set('media_type', 'image');
                        $set('media_path', $promotionImage);
                    }
                })
                ->dehydrated(false),
            TextInput::make('action_id')
                ->label('قيمة الإجراء')
                ->visible(fn (callable $get): bool => $get('click_action') !== 'promotion')
                ->required(fn (callable $get): bool => $get('click_action') !== 'promotion'),
            DateTimePicker::make('starts_at')->label('يبدأ في')->required(),
            DateTimePicker::make('ends_at')->label('ينتهي في')->required(),
            Toggle::make('is_active')->label('نشط')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('vendor.name')->label('البائع')->searchable(),
                TextColumn::make('subscription.id')->label('الاشتراك'),
                BadgeColumn::make('media_type')->label('النوع')
                    ->colors([
                        'info' => 'image',
                        'warning' => 'video',
                    ]),
                BadgeColumn::make('click_action')->label('الإجراء'),
                TextColumn::make('starts_at')->label('يبدأ')->dateTime('Y-m-d H:i'),
                TextColumn::make('ends_at')->label('ينتهي')->dateTime('Y-m-d H:i'),
                IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->actions([
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAds::route('/'),
            'create' => Pages\CreateAd::route('/create'),
            'edit' => Pages\EditAd::route('/{record}/edit'),
        ];
    }
}
