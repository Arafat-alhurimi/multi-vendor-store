<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->unique(ignoreRecord: true)
                    ->default(null),
                TextInput::make('phone')
                    ->tel()
                    ->unique(ignoreRecord: true)
                    ->default(null),
                TextInput::make('password')
                    ->password()
                    ->required(fn (?string $operation): bool => $operation === 'create')
                    ->minLength(6),
                Select::make('role')
                    ->options(['admin' => 'Admin', 'vendor' => 'Vendor', 'customer' => 'Customer'])
                    ->default(function () {
                        $forcedRole = request()->query('role');

                        return in_array($forcedRole, ['admin', 'vendor', 'customer'], true)
                            ? $forcedRole
                            : 'customer';
                    })
                    ->disabled(function (?string $operation): bool {
                        return $operation === 'create' && in_array((string) request()->query('role'), ['admin', 'vendor', 'customer'], true);
                    })
                    ->dehydrated()
                    ->required(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
