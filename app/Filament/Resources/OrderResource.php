<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use App\Services\InventoryService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static ?string $navigationLabel = 'الطلبات';

    protected static string | \UnitEnum | null $navigationGroup = 'إدارة التجارة';

    public static function canViewAny(): bool
    {
        $role = Auth::user()?->role;

        return in_array($role, ['admin', 'vendor'], true);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        $user = Auth::user();

        if (! $user) {
            return false;
        }

        if ($user->role === 'admin') {
            return true;
        }

        return $user->role === 'vendor' && (int) $record->vendor_id === (int) $user->id;
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();

        if ($user?->role === 'vendor') {
            return $query->where('vendor_id', $user->id);
        }

        return $query;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('order_number')->label('رقم الطلب')->searchable(),
                TextColumn::make('user.name')->label('العميل')->searchable(),
                TextColumn::make('vendor.name')->label('البائع')->searchable(),
                TextColumn::make('product.name_ar')->label('المنتج')->searchable(),
                TextColumn::make('quantity')->label('الكمية'),
                TextColumn::make('unit_price')->label('سعر الوحدة'),
                TextColumn::make('total_price')->label('الإجمالي'),
                BadgeColumn::make('payment_status')
                    ->label('حالة الدفع')
                    ->formatStateUsing(fn (string $state): string => $state === Order::PAYMENT_VERIFIED ? 'تم التحقق' : 'بانتظار التحقق')
                    ->colors([
                        'warning' => Order::PAYMENT_PENDING,
                        'success' => Order::PAYMENT_VERIFIED,
                    ]),
                BadgeColumn::make('status')
                    ->label('حالة الطلب')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        Order::STATUS_PENDING => 'تم الطلب',
                        Order::STATUS_PROCESSING => 'تم التجهيز',
                        Order::STATUS_DELIVERED => 'تم التسليم',
                        Order::STATUS_CANCELLED => 'ملغي',
                        default => $state,
                    })
                    ->colors([
                        'warning' => Order::STATUS_PENDING,
                        'info' => Order::STATUS_PROCESSING,
                        'success' => Order::STATUS_DELIVERED,
                        'danger' => Order::STATUS_CANCELLED,
                    ]),
                TextColumn::make('created_at')->label('تاريخ الطلب')->dateTime('Y-m-d H:i'),
            ])
            ->actions([
                Action::make('verifyPayment')
                    ->label('Verify Payment')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Order $record): bool => Auth::user()?->role === 'admin' && $record->payment_status === Order::PAYMENT_PENDING)
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        DB::transaction(function () use ($record): void {
                            $lockedOrder = Order::query()
                                ->whereKey($record->id)
                                ->with(['product', 'variation'])
                                ->lockForUpdate()
                                ->firstOrFail();

                            if ($lockedOrder->payment_status === Order::PAYMENT_VERIFIED) {
                                return;
                            }

                            app(InventoryService::class)->decrementStock(
                                $lockedOrder->product,
                                $lockedOrder->variation,
                                (int) $lockedOrder->quantity
                            );

                            $lockedOrder->update([
                                'payment_status' => Order::PAYMENT_VERIFIED,
                            ]);
                        });

                        Notification::make()
                            ->title('تم التحقق من الدفع وتحديث المخزون.')
                            ->success()
                            ->send();
                    }),
                Action::make('markProcessing')
                    ->label('تم التجهيز')
                    ->icon('heroicon-o-cog-6-tooth')
                    ->color('info')
                    ->visible(function (Order $record): bool {
                        $user = Auth::user();

                        if (! $user || $record->status !== Order::STATUS_PENDING) {
                            return false;
                        }

                        if ($user->role === 'admin') {
                            return true;
                        }

                        return $user->role === 'vendor' && (int) $record->vendor_id === (int) $user->id;
                    })
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        $record->update(['status' => Order::STATUS_PROCESSING]);
                    }),
                Action::make('markDelivered')
                    ->label('تم التسليم')
                    ->icon('heroicon-o-truck')
                    ->color('success')
                    ->visible(function (Order $record): bool {
                        $user = Auth::user();

                        if (! $user || $record->status !== Order::STATUS_PROCESSING) {
                            return false;
                        }

                        if ($user->role === 'admin') {
                            return true;
                        }

                        return $user->role === 'vendor' && (int) $record->vendor_id === (int) $user->id;
                    })
                    ->requiresConfirmation()
                    ->action(function (Order $record): void {
                        $record->update(['status' => Order::STATUS_DELIVERED]);
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
        ];
    }
}
