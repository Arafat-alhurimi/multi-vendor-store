<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdSubscriptionResource\Pages;
use App\Models\Ad;
use App\Models\VendorAdSubscription;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdSubscriptionResource extends Resource
{
    protected static ?string $model = VendorAdSubscription::class;

    protected static ?string $modelLabel = 'طلب اشتراك';

    protected static ?string $pluralModelLabel = 'طلبات الاشتراك';

    protected static ?string $navigationLabel = 'طلبات الاشتراك';

    protected static string | \UnitEnum | null $navigationGroup = 'إدارة الباقات';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-clipboard-document-list';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

    public static function canDelete(\Illuminate\Database\Eloquent\Model $record): bool
    {
        return false;
    }

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
            Select::make('request_type')
                ->label('نوع الطلب')
                ->options([
                    'new' => 'اشتراك جديد',
                    'renewal' => 'تجديد',
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
                TextColumn::make('store_name')
                    ->label('اسم المتجر')
                    ->state(fn (VendorAdSubscription $record): string => (string) ($record->vendor?->stores?->first()?->name ?? '-'))
                    ->searchable(),
                TextColumn::make('store_city')
                    ->label('المدينة')
                    ->state(fn (VendorAdSubscription $record): string => (string) ($record->vendor?->stores?->first()?->city ?? '-')),
                TextColumn::make('adPackage.name')->label('الباقة')->searchable(),
                TextColumn::make('package_price')
                    ->label('سعر الباقة')
                    ->state(fn (VendorAdSubscription $record): float => (float) ($record->adPackage?->price ?? 0))
                    ->numeric(2),
                TextColumn::make('allowed_count')
                    ->label('العدد المسموح')
                    ->state(function (VendorAdSubscription $record): string {
                        $images = (int) ($record->adPackage?->max_images ?? 0);
                        $videos = (int) ($record->adPackage?->max_videos ?? 0);
                        $promotions = (int) ($record->adPackage?->max_promotions ?? 0);

                        return "صور {$images} | فيديو {$videos} | عروض {$promotions}";
                    })
                    ->wrap(),
                TextColumn::make('created_at')->label('تاريخ الطلب')->dateTime('Y-m-d H:i')->sortable(),
                BadgeColumn::make('request_type')
                    ->label('نوع الطلب')
                    ->formatStateUsing(fn (?string $state): string => $state === 'renewal' ? 'تجديد' : 'اشتراك جديد')
                    ->colors([
                        'info' => 'new',
                        'primary' => 'renewal',
                    ]),
                BadgeColumn::make('status')->label('الحالة')
                    ->formatStateUsing(fn (string $state, VendorAdSubscription $record): string => $state === 'pending'
                        ? ($record->request_type === 'renewal' ? 'طلب تجديد' : 'قيد الانتظار')
                        : $state)
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'active',
                        'danger' => 'expired',
                    ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Action::make('approveSubscription')
                    ->label('موافقة')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (VendorAdSubscription $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (VendorAdSubscription $record): void {
                        $isRenewal = $record->request_type === 'renewal';

                        $record->update([
                            'status' => 'active',
                            'used_images' => $isRenewal ? 0 : $record->used_images,
                            'used_videos' => $isRenewal ? 0 : $record->used_videos,
                            'used_promotions' => $isRenewal ? 0 : $record->used_promotions,
                        ]);

                        if ($isRenewal) {
                            $record->refresh();

                            Ad::withoutGlobalScopes()
                                ->where('vendor_ad_subscription_id', $record->id)
                                ->update([
                                    'is_active' => true,
                                    'starts_at' => $record->starts_at,
                                    'ends_at' => $record->ends_at,
                                ]);
                        }

                        Notification::make()
                            ->title('تم قبول الاشتراك بنجاح')
                            ->success()
                            ->send();
                    }),
                Action::make('rejectSubscription')
                    ->label('رفض')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (VendorAdSubscription $record): bool => $record->status === 'pending')
                    ->requiresConfirmation()
                    ->action(function (VendorAdSubscription $record): void {
                        if ($record->request_type === 'renewal') {
                            $record->update([
                                'status' => 'expired',
                                'request_type' => 'new',
                            ]);
                        } else {
                            $record->delete();
                        }

                        Notification::make()
                            ->title('تم رفض الطلب')
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAdSubscriptions::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with(['vendor.stores', 'adPackage'])
            ->where('status', 'pending');
    }

    public static function getNavigationBadge(): ?string
    {
        $count = VendorAdSubscription::query()->where('status', 'pending')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}
