<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdSubscriptionResource\Pages;
use App\Filament\Resources\AdSubscriptionResource\RelationManagers\AdsRelationManager;
use App\Models\VendorAdSubscription;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdSubscriptionResource extends Resource
{
    protected static ?string $model = VendorAdSubscription::class;

    protected static ?string $navigationLabel = 'اشتراكات الإعلانات';

    protected static string | \UnitEnum | null $navigationGroup = 'الإعلانات';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            Select::make('vendor_id')
                ->label('البائع')
                ->relationship('vendor', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('ad_package_id')
                ->label('الباقة')
                ->relationship('adPackage', 'name')
                ->searchable()
                ->preload()
                ->required(),
            Select::make('status')
                ->label('الحالة')
                ->options([
                    'pending' => 'Pending',
                    'active' => 'Active',
                    'expired' => 'Expired',
                ])
                ->required(),
            DateTimePicker::make('starts_at')->label('يبدأ في'),
            DateTimePicker::make('ends_at')->label('ينتهي في'),
            TextInput::make('used_images')->label('الصور المستخدمة')->numeric()->required(),
            TextInput::make('used_videos')->label('الفيديوهات المستخدمة')->numeric()->required(),
            TextInput::make('used_promotions')->label('العروض المستخدمة')->numeric()->required(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vendor.name')->label('البائع')->searchable(),
                TextColumn::make('adPackage.name')->label('الباقة')->searchable(),
                BadgeColumn::make('status')->label('الحالة')
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'danger' => 'expired',
                    ]),
                TextColumn::make('starts_at')->label('يبدأ')->dateTime('Y-m-d H:i'),
                TextColumn::make('ends_at')->label('ينتهي')->dateTime('Y-m-d H:i'),
                TextColumn::make('used_images')->label('صور'),
                TextColumn::make('used_videos')->label('فيديو'),
                TextColumn::make('used_promotions')->label('عروض'),
            ])
            ->actions([
                Action::make('approveSubscription')
                    ->label('قبول الاشتراك')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (VendorAdSubscription $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (VendorAdSubscription $record): void {
                        $record->update(['status' => 'active']);

                        Notification::make()
                            ->title('تم قبول الاشتراك بنجاح')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            AdsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdSubscriptions::route('/'),
            'create' => Pages\CreateAdSubscription::route('/create'),
            'edit' => Pages\EditAdSubscription::route('/{record}/edit'),
        ];
    }
}
