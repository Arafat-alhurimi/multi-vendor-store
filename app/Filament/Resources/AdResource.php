<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdResource\Pages;
use App\Filament\Resources\AdResource\Pages\ListAds;
use App\Models\Ad;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Store;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

class AdResource extends Resource
{
    protected static array $productNamesCache = [];

    protected static array $storeNamesByVendorCache = [];

    protected static array $storeNamesByIdCache = [];

    protected static array $promotionTitlesCache = [];

    protected static ?string $model = Ad::class;

    protected static ?string $navigationLabel = 'محتوى الباقات';

    protected static ?string $modelLabel = 'إعلان';

    protected static ?string $pluralModelLabel = 'محتوى الباقات';

    protected static string | \UnitEnum | null $navigationGroup = 'إدارة الباقات';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-megaphone';

    public static function getEloquentQuery(): Builder
    {
        // In this admin page, "active" means enabled flag only.
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with(['vendor.stores:id,user_id,name', 'subscription.adPackage:id,name'])
            ->where('is_active', true);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->schema([
            ToggleButtons::make('content_type')
                ->label('نوع المحتوى')
                ->options([
                    'image' => 'صورة',
                    'video' => 'فيديو',
                    'promotion' => 'عرض',
                ])
                ->inline()
                ->default('image')
                ->live()
                ->dehydrated(false)
                ->afterStateHydrated(function ($state, callable $set, callable $get): void {
                    if (filled($state)) {
                        return;
                    }

                    if ($get('click_action') === 'promotion') {
                        $set('content_type', 'promotion');

                        return;
                    }

                    $set('content_type', $get('media_type') === 'video' ? 'video' : 'image');
                })
                ->afterStateUpdated(function ($state, callable $set, callable $get): void {
                    if ($state === 'promotion') {
                        $set('media_type', 'image');
                        $set('click_action', 'promotion');
                        $set('product_action_id', null);
                        $set('store_action_id', null);

                        return;
                    }

                    $set('media_type', $state === 'video' ? 'video' : 'image');

                    if (($get('click_action') ?? null) === 'promotion') {
                        $set('click_action', null);
                        $set('promotion_id', null);
                        $set('action_id', null);
                    }
                })
                ->columnSpanFull(),
            Hidden::make('media_type')->default('image')->required(),
            Section::make()
                ->schema([
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
                        ->visible(fn (callable $get): bool => $get('content_type') === 'promotion')
                        ->required(fn (callable $get): bool => $get('content_type') === 'promotion')
                        ->afterStateUpdated(function ($state, callable $set): void {
                            $set('action_id', $state ? (string) $state : null);
                            $set('click_action', 'promotion');
                            $set('media_type', 'image');

                            if (! $state) {
                                return;
                            }

                            $promotion = Promotion::query()
                                ->select(['id', 'image', 'starts_at', 'ends_at'])
                                ->whereKey($state)
                                ->first();

                            if (! $promotion) {
                                return;
                            }

                            if ($promotion->image) {
                                $set('media_type', 'image');
                                $set('media_path', $promotion->image);
                            }

                            if ($promotion->starts_at) {
                                $set('starts_at', $promotion->starts_at);
                            }

                            if ($promotion->ends_at) {
                                $set('ends_at', $promotion->ends_at);
                            }
                        }),
                    FileUpload::make('media_path')
                        ->label('الوسائط')
                        ->disk('s3')
                        ->directory('ads-media')
                        ->visibility('public')
                        ->required(),
                    Select::make('click_action')
                        ->label('إجراء النقر')
                        ->options([
                            'product' => 'منتج',
                            'store' => 'متجر',
                            'url' => 'رابط',
                        ])
                        ->reactive()
                        ->visible(fn (callable $get): bool => $get('content_type') !== 'promotion')
                        ->afterStateUpdated(function ($state, callable $set): void {
                            if ($state !== 'promotion') {
                                $set('promotion_id', null);
                            }

                            if ($state !== 'product') {
                                $set('product_action_id', null);
                            }

                            if ($state !== 'store') {
                                $set('store_action_id', null);
                            }
                        })
                        ->required(fn (callable $get): bool => $get('content_type') !== 'promotion'),
                    Select::make('product_action_id')
                        ->label('اختر المنتج')
                        ->options(fn (): array => Product::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray())
                        ->searchable()
                        ->preload()
                        ->visible(fn (callable $get): bool => $get('content_type') !== 'promotion' && $get('click_action') === 'product')
                        ->required(fn (callable $get): bool => $get('content_type') !== 'promotion' && $get('click_action') === 'product'),
                    Select::make('store_action_id')
                        ->label('اختر المتجر')
                        ->options(fn (): array => Store::query()->orderBy('name')->pluck('name', 'id')->toArray())
                        ->searchable()
                        ->preload()
                        ->visible(fn (callable $get): bool => $get('content_type') !== 'promotion' && $get('click_action') === 'store')
                        ->required(fn (callable $get): bool => $get('content_type') !== 'promotion' && $get('click_action') === 'store'),
                    TextInput::make('action_id')
                        ->label('قيمة الإجراء')
                        ->visible(fn (callable $get): bool => $get('content_type') !== 'promotion' && $get('click_action') === 'url')
                        ->required(fn (callable $get): bool => $get('content_type') !== 'promotion' && $get('click_action') === 'url'),
                    DateTimePicker::make('starts_at')->label('يبدأ في')->required(),
                    DateTimePicker::make('ends_at')->label('ينتهي في')->required(),
                    Toggle::make('is_active')->label('نشط')->default(true),
                ])
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')->label('ID'),
                TextColumn::make('admin_creator')
                    ->label('المنشئ')
                    ->state('أنشأه الأدمن')
                    ->badge()
                    ->color('gray')
                    ->visible(fn (ListAds $livewire): bool => $livewire->isAdminTabActive()),
                TextColumn::make('store_name')
                    ->label('اسم المتجر')
                    ->state(fn (Ad $record): string => static::resolveStoreName($record))
                    ->searchable()
                    ->visible(fn (ListAds $livewire): bool => $livewire->isStoreTabActive()),
                TextColumn::make('package_name')
                    ->label('اسم الباقة')
                    ->state(fn (Ad $record): string => static::resolvePackageName($record))
                    ->badge()
                    ->color('info')
                    ->visible(fn (ListAds $livewire): bool => $livewire->isStoreTabActive()),
                BadgeColumn::make('media_type')->label('النوع')
                    ->formatStateUsing(fn (?string $state): string => $state === 'video' ? 'فيديو' : 'صورة')
                    ->colors([
                        'info' => 'image',
                        'warning' => 'video',
                    ]),
                TextColumn::make('transition')
                    ->label('الانتقال')
                    ->html()
                    ->state(fn (Ad $record): HtmlString => new HtmlString(static::formatTransitionHtml($record)))
                    ->wrap(),
                TextColumn::make('starts_at')->label('يبدأ')->dateTime('Y-m-d H:i'),
                TextColumn::make('ends_at')->label('ينتهي')->dateTime('Y-m-d H:i'),
                IconColumn::make('is_active')->label('نشط')->boolean(),
            ])
            ->headerActions([
                Action::make('subtab_images')
                    ->label(fn (ListAds $livewire): string => 'صور ('.$livewire->getCountForType('image').')')
                    ->color(fn (ListAds $livewire): string => $livewire->isCurrentType('image') ? 'primary' : 'gray')
                    ->action(fn (ListAds $livewire): string => $livewire->setCurrentTabContentType('image')),
                Action::make('subtab_videos')
                    ->label(fn (ListAds $livewire): string => 'فيديوهات ('.$livewire->getCountForType('video').')')
                    ->color(fn (ListAds $livewire): string => $livewire->isCurrentType('video') ? 'warning' : 'gray')
                    ->action(fn (ListAds $livewire): string => $livewire->setCurrentTabContentType('video')),
                Action::make('subtab_offers')
                    ->label(fn (ListAds $livewire): string => 'عروض ('.$livewire->getCountForType('promotion').')')
                    ->color(fn (ListAds $livewire): string => $livewire->isCurrentType('promotion') ? 'success' : 'gray')
                    ->action(fn (ListAds $livewire): string => $livewire->setCurrentTabContentType('promotion')),
            ])
            ->recordUrl(null)
            ->recordAction(fn (ListAds $livewire): ?string => $livewire->isStoreTabActive() ? 'previewStoreContent' : null)
            ->actions([
                Action::make('previewStoreContent')
                    ->label('تفاصيل')
                    ->icon('heroicon-o-eye')
                    ->visible(fn (ListAds $livewire): bool => $livewire->isStoreTabActive())
                    ->modalHeading('تفاصيل محتوى المتجر')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Ad $record) => view('filament.ads.store-content-preview', [
                        'record' => $record,
                        'storeName' => static::resolveStoreName($record),
                        'transitionType' => static::translateActionType($record->click_action),
                        'transitionTarget' => static::resolveTransitionTarget($record),
                    ])),
                ViewAction::make()->visible(fn (ListAds $livewire): bool => $livewire->isAdminTabActive()),
                EditAction::make()->visible(fn (ListAds $livewire): bool => $livewire->isAdminTabActive()),
                DeleteAction::make()->visible(fn (ListAds $livewire): bool => $livewire->isAdminTabActive()),
            ]);
    }

    private static function resolveStoreName(Ad $record): string
    {
        if ($record->vendor?->stores?->isNotEmpty()) {
            return (string) $record->vendor->stores->first()->name;
        }

        if (! $record->vendor_id) {
            return '-';
        }

        if (isset(static::$storeNamesByVendorCache[$record->vendor_id])) {
            return static::$storeNamesByVendorCache[$record->vendor_id];
        }

        $storeName = Store::query()->where('user_id', $record->vendor_id)->value('name');

        return static::$storeNamesByVendorCache[$record->vendor_id] = (string) ($storeName ?? '-');
    }

    private static function resolvePackageName(Ad $record): string
    {
        return (string) ($record->subscription?->adPackage?->name ?? 'غير محدد');
    }

    private static function translateActionType(?string $action): string
    {
        return match ($action) {
            'promotion' => 'عرض',
            'product' => 'منتج',
            'store' => 'متجر',
            'url' => 'رابط',
            default => 'غير محدد',
        };
    }

    private static function resolveTransitionTarget(Ad $record): string
    {
        $actionId = $record->action_id;

        if (! filled($actionId)) {
            return '-';
        }

        return match ($record->click_action) {
            'promotion' => static::resolvePromotionTitle((int) $actionId),
            'product' => static::resolveProductName((int) $actionId),
            'store' => static::resolveStoreNameById((int) $actionId),
            'url' => (string) $actionId,
            default => (string) $actionId,
        };
    }

    private static function formatTransitionHtml(Ad $record): string
    {
        $type = static::translateActionType($record->click_action);
        $target = static::resolveTransitionTarget($record);

        return '<span style="display:inline-block;padding:2px 8px;border-radius:9999px;background:#eef2ff;color:#3730a3;font-weight:600;">'
            .$type
            .'</span> '
            .'<span style="font-weight:600;">إلى:</span> '
            .e($target);
    }

    private static function resolveProductName(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        if (isset(static::$productNamesCache[$id])) {
            return static::$productNamesCache[$id];
        }

        $name = Product::query()->whereKey($id)->value('name_ar');

        return static::$productNamesCache[$id] = (string) ($name ?? 'منتج غير موجود');
    }

    private static function resolveStoreNameById(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        if (isset(static::$storeNamesByIdCache[$id])) {
            return static::$storeNamesByIdCache[$id];
        }

        $name = Store::query()->whereKey($id)->value('name');

        return static::$storeNamesByIdCache[$id] = (string) ($name ?? 'متجر غير موجود');
    }

    private static function resolvePromotionTitle(int $id): string
    {
        if ($id <= 0) {
            return '-';
        }

        if (isset(static::$promotionTitlesCache[$id])) {
            return static::$promotionTitlesCache[$id];
        }

        $title = Promotion::query()->whereKey($id)->value('title');

        return static::$promotionTitlesCache[$id] = (string) ($title ?? 'عرض غير موجود');
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
