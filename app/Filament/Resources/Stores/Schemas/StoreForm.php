<?php

namespace App\Filament\Resources\Stores\Schemas;

use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StoreForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('البائع')
                    ->relationship('user', 'name', modifyQueryUsing: fn ($query) => $query->where('role', 'vendor'))
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
                TextInput::make('city')
                    ->label('المدينة')
                    ->required(),
                TextInput::make('address')
                    ->label('العنوان'),
                TextInput::make('latitude')
                    ->label('خط العرض')
                    ->numeric(),
                TextInput::make('longitude')
                    ->label('خط الطول')
                    ->numeric(),
                FileUpload::make('logo')
                    ->label('شعار المتجر')
                    ->image()
                    ->disk('s3')
                    ->directory('stores')
                    ->visibility('public')
                    ->imageAspectRatio('1:1')
                    ->automaticallyCropImagesToAspectRatio()
                    ->automaticallyResizeImagesToWidth(600)
                    ->automaticallyResizeImagesToHeight(600)
                    ->imagePreviewHeight(120),
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
            ]);
    }
}
