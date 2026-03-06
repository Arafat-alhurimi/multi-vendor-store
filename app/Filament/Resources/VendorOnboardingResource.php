<?php

namespace App\Filament\Resources;

use App\Filament\Resources\VendorOnboardingResource\Pages\CreateVendorOnboarding;
use App\Models\Category;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class VendorOnboardingResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationLabel = 'إنشاء بائع ومتجر';

    protected static bool $shouldRegisterNavigation = false;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('بيانات البائع')
                    ->schema([
                        TextInput::make('vendor.name')
                            ->label('اسم البائع')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('vendor.phone')
                            ->label('رقم الهاتف')
                            ->tel()
                            ->required()
                            ->maxLength(30)
                            ->unique(table: 'users', column: 'phone'),
                        TextInput::make('vendor.email')
                            ->label('البريد الإلكتروني')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(table: 'users', column: 'email'),
                        TextInput::make('vendor.password')
                            ->label('كلمة المرور')
                            ->password()
                            ->revealable()
                            ->required()
                            ->minLength(8),
                    ])
                    ->columns(2),

                Section::make('التفاصيل المالية')
                    ->schema([
                        TextInput::make('financial.kuraimi_account_number')
                            ->label('رقم حساب الكريمي')
                            ->maxLength(50),
                        TextInput::make('financial.kuraimi_account_name')
                            ->label('اسم حساب الكريمي')
                            ->maxLength(255),
                        TextInput::make('financial.jeeb_id')
                            ->label('معرّف جيب')
                            ->maxLength(100),
                        TextInput::make('financial.jeeb_name')
                            ->label('اسم حساب جيب')
                            ->maxLength(255),
                    ])
                    ->columns(2),

                Section::make('بيانات المتجر')
                    ->schema([
                        TextInput::make('store.name')
                            ->label('اسم المتجر')
                            ->required()
                            ->maxLength(255),
                        Textarea::make('store.description')
                            ->label('الوصف')
                            ->columnSpanFull(),
                        Select::make('store.city')
                            ->label('المدينة')
                            ->options([
                                'صنعاء' => 'صنعاء',
                                'عدن' => 'عدن',
                                'تعز' => 'تعز',
                                'الحديدة' => 'الحديدة',
                                'إب' => 'إب',
                                'ذمار' => 'ذمار',
                                'المكلا' => 'المكلا',
                                'سيئون' => 'سيئون',
                                'مأرب' => 'مأرب',
                                'صعدة' => 'صعدة',
                                'البيضاء' => 'البيضاء',
                                'عمران' => 'عمران',
                                'حجة' => 'حجة',
                                'المحويت' => 'المحويت',
                                'ريمة' => 'ريمة',
                                'الضالع' => 'الضالع',
                                'لحج' => 'لحج',
                                'أبين' => 'أبين',
                                'شبوة' => 'شبوة',
                                'حضرموت' => 'حضرموت',
                                'المهرة' => 'المهرة',
                                'الجوف' => 'الجوف',
                                'سقطرى' => 'سقطرى',
                            ])
                            ->searchable()
                            ->required(),
                        TextInput::make('store.address')
                            ->label('العنوان')
                            ->maxLength(255),
                        TextInput::make('store.latitude')
                            ->label('خط العرض')
                            ->numeric(),
                        TextInput::make('store.longitude')
                            ->label('خط الطول')
                            ->numeric(),
                        Select::make('store.categories')
                            ->label('الفئات الرئيسية')
                            ->options(fn (): array => Category::query()->orderBy('name_ar')->pluck('name_ar', 'id')->toArray())
                            ->multiple()
                            ->searchable()
                            ->preload(),
                        FileUpload::make('store.logo')
                            ->label('شعار المتجر')
                            ->image()
                            ->disk('s3')
                            ->directory('stores/logos')
                            ->visibility('public')
                            ->imagePreviewHeight(150),
                        FileUpload::make('store.images')
                            ->label('صور المتجر')
                            ->image()
                            ->multiple()
                            ->disk('s3')
                            ->directory('stores/media/images')
                            ->visibility('public'),
                        FileUpload::make('store.videos')
                            ->label('فيديوهات المتجر')
                            ->multiple()
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/quicktime'])
                            ->disk('s3')
                            ->directory('stores/media/videos')
                            ->visibility('public'),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table;
    }

    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return static::getUrl('create', $parameters, $isAbsolute, $panel, $tenant, $shouldGuessMissingParameters);
    }

    public static function getPages(): array
    {
        return [
            'create' => CreateVendorOnboarding::route('/create'),
        ];
    }
}
