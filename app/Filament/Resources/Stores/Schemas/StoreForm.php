<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\User;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('بيانات المتجر')
                    ->schema([
                        Select::make('user_id')
                            ->label('البائع')
                            ->relationship('user', 'name', modifyQueryUsing: fn ($query) => $query
                                ->where('role', 'vendor')
                                ->whereDoesntHave('stores'))
                            ->default(fn () => request()->query('user_id'))
                            ->hidden(fn (?string $operation): bool => $operation === 'create' && filled(request()->query('user_id')))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function ($state, callable $set): void {
                                $user = $state ? User::find($state) : null;
                                if ($user && ! $user->is_active) {
                                    $set('is_active', false);
                                }
                            })
                            ->rule(function (): callable {
                                return function (string $attribute, $value, callable $fail): void {
                                    if ($value && ! User::whereKey($value)->where('role', 'vendor')->exists()) {
                                        $fail('لا يمكن إنشاء متجر إلا لبائع.');
                                    }
                                };
                            }),
                        TextInput::make('name')
                            ->label('اسم المتجر')
                            ->required(),
                        Textarea::make('description')
                            ->label('الوصف')
                            ->columnSpanFull(),
                        Select::make('city')
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
                        TextInput::make('address')
                            ->label('العنوان'),
                        Hidden::make('logo'),
                        Select::make('categories')
                            ->label('الأقسام')
                            ->relationship('categories', 'name_ar')
                            ->multiple()
                            ->preload()
                            ->searchable(),
                        Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->disabled(function (callable $get): bool {
                                $userId = $get('user_id');
                                if (! $userId) {
                                    return false;
                                }
                                $user = User::find($userId);
                                return $user && ! $user->is_active;
                            }),
                    ])
                    ->columns(2),
                Section::make('التفاصيل المالية')
                    ->schema([
                        TextInput::make('financial.kuraimi_account_number')
                            ->label('رقم حساب الكريمي')
                            ->maxLength(255),
                        TextInput::make('financial.kuraimi_account_name')
                            ->label('اسم حساب الكريمي')
                            ->maxLength(255),
                        TextInput::make('financial.jeeb_id')
                            ->label('معرّف جيب')
                            ->maxLength(255),
                        TextInput::make('financial.jeeb_name')
                            ->label('اسم حساب جيب')
                            ->maxLength(255),
                    ])
                    ->columns(2),
                Section::make('وسائط المتجر')
                    ->schema([
                        Hidden::make('store_images')
                            ->default([]),
                        Hidden::make('store_videos')
                            ->default([]),
                        View::make('filament.stores.direct-media-upload')
                            ->columnSpanFull(),
                    ])
                    ->columns(1),
            ]);
    }
}
