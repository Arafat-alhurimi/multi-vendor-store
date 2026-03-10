<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Resources\VendorOnboardingResource;
use App\Models\Comment;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewUser extends ViewRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createStore')
                ->label('إنشاء متجر')
                ->icon('heroicon-o-building-storefront')
                ->color('primary')
                ->visible(fn (): bool => $this->getRecord()->role === 'vendor' && ! $this->getRecord()->store)
                ->url(fn (): string => VendorOnboardingResource::getUrl('create')),
            Action::make('approveVendor')
                ->label('موافقة وتفعيل البائع')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool =>
                    $this->getRecord()->role === 'vendor'
                    && ! $this->getRecord()->is_active
                    && filled($this->getRecord()->otp_verified_at)
                    && (bool) $this->getRecord()->store
                )
                ->action(function (): void {
                    $this->approveVendorAccount();
                })
                ->requiresConfirmation(),
            Action::make('toggleActive')
                ->label(fn (): string => $this->getRecord()->is_active ? 'إلغاء التفعيل' : 'تفعيل الحساب')
                ->color(fn (): string => $this->getRecord()->is_active ? 'danger' : 'success')
                ->icon(fn (): string => $this->getRecord()->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->visible(fn (): bool => $this->getRecord()->role !== 'vendor' || $this->getRecord()->is_active)
                ->action(function (): void {
                    $this->toggleActivation();
                })
                ->requiresConfirmation(),
        ];
    }

    protected function approveVendorAccount(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof User || $record->role !== 'vendor') {
            return;
        }

        if (! $record->otp_verified_at) {
            Notification::make()
                ->title('لا يمكن الموافقة')
                ->body('يجب أن يتم التحقق من OTP أولاً.')
                ->danger()
                ->send();

            return;
        }

        $store = $record->store;

        if (! $store) {
            Notification::make()
                ->title('لا يمكن الموافقة')
                ->body('لا يوجد متجر مرتبط بهذا البائع.')
                ->danger()
                ->send();

            return;
        }

        $record->update(['is_active' => true]);
        $store->update(['is_active' => true]);

        Notification::make()
            ->title('تمت الموافقة')
            ->body('تم تفعيل حساب البائع والمتجر بنجاح.')
            ->success()
            ->send();
    }

    protected function toggleActivation(): void
    {
        $record = $this->getRecord();

        if (! $record instanceof User) {
            return;
        }

        if ($record->is_active) {
            if ($record->role === 'vendor' && $record->store) {
                $record->store->update(['is_active' => false]);
            }

            $record->update(['is_active' => false]);

            Notification::make()
                ->title('تم إلغاء التفعيل')
                ->body('تم إلغاء تفعيل الحساب بنجاح.')
                ->warning()
                ->send();

            return;
        }

        if (! $record->otp_verified_at) {
            Notification::make()
                ->title('لا يمكن التفعيل')
                ->body('يجب أن يتم التحقق من OTP أولاً.')
                ->danger()
                ->send();

            return;
        }

        if ($record->role === 'vendor') {
            $store = $record->store;

            if (! $store) {
                Notification::make()
                    ->title('لا يمكن التفعيل')
                    ->body('لا يوجد متجر مرتبط بهذا البائع.')
                    ->danger()
                    ->send();

                return;
            }

            $store->update(['is_active' => true]);
        }

        $record->update(['is_active' => true]);

        Notification::make()
            ->title('تم التفعيل')
            ->body('تم تفعيل الحساب بنجاح.')
            ->success()
            ->send();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('معلومات الحساب')
                    ->description('ملخص الحالة العامة وبيانات التواصل')
                    ->schema([
                        TextEntry::make('name')
                            ->label('الاسم')
                            ->weight('bold')
                            ->icon('heroicon-o-user'),
                        TextEntry::make('email')
                            ->label('البريد الإلكتروني')
                            ->icon('heroicon-o-envelope')
                            ->placeholder('-'),
                        TextEntry::make('phone')
                            ->label('رقم الهاتف')
                            ->icon('heroicon-o-phone')
                            ->placeholder('-'),
                        TextEntry::make('role')
                            ->label('الدور')
                            ->badge()
                            ->color(fn (?string $state): string => match ($state) {
                                'admin' => 'danger',
                                'vendor' => 'warning',
                                default => 'info',
                            }),
                        TextEntry::make('otp_verified_at')
                            ->label('التحقق')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state ? 'تم التحقق' : 'غير متحقق')
                            ->color(fn ($state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('is_active')
                            ->label('حالة الحساب')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'غير نشط')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('approval_status')
                            ->label('حالة الموافقة')
                            ->visible(fn (?User $record): bool => $record?->role === 'vendor')
                            ->badge()
                            ->state(function (?User $record): string {
                                if (! $record) {
                                    return '-';
                                }

                                if ($record->is_active) {
                                    return 'تمت الموافقة';
                                }

                                if (! filled($record->otp_verified_at)) {
                                    return 'بانتظار التحقق من OTP';
                                }

                                if (! $record->store) {
                                    return 'لم ينشئ متجر بعد';
                                }

                                return 'جاهز للموافقة';
                            })
                            ->color(function (?User $record): string {
                                if (! $record) {
                                    return 'gray';
                                }

                                if ($record->is_active) {
                                    return 'success';
                                }

                                if (! filled($record->otp_verified_at)) {
                                    return 'warning';
                                }

                                if (! $record->store) {
                                    return 'gray';
                                }

                                return 'warning';
                            }),
                    ])
                    ->columns(3)
                    ->columnSpanFull(),
                Section::make('الملخص السريع')
                    ->description('أرقام مختصرة لنشاط العميل')
                    ->visible(fn (?User $record): bool => $record?->role === 'customer')
                    ->schema([
                        TextEntry::make('cart_count')
                            ->label('السلة')
                            ->state(fn (): int => (int) $this->getRecord()->cartItems()->count())
                            ->badge()
                            ->color('info'),
                        TextEntry::make('orders_count')
                            ->label('الطلبات')
                            ->state(fn (): int => (int) $this->getRecord()->orders()->count())
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('comments_count')
                            ->label('التعليقات')
                            ->state(fn (): int => (int) $this->getRecord()->comments()->count())
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('favorites_count')
                            ->label('المفضلة')
                            ->state(fn (): int => (int) $this->getRecord()->favorites()->count())
                            ->badge()
                            ->color('success'),
                        TextEntry::make('reports_count')
                            ->label('البلاغات')
                            ->state(fn (): int => (int) $this->getRecord()->reports()->count())
                            ->badge()
                            ->color('danger'),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),
                Section::make('البيانات المالية')
                    ->description('تظهر فقط للبائعين')
                    ->visible(fn (?User $record): bool => $record?->role !== 'customer')
                    ->schema([
                        TextEntry::make('vendorFinancialDetail.kuraimi_account_number')
                            ->label('رقم حساب الكريمي')
                            ->placeholder('-'),
                        TextEntry::make('vendorFinancialDetail.kuraimi_account_name')
                            ->label('اسم حساب الكريمي')
                            ->placeholder('-'),
                        TextEntry::make('vendorFinancialDetail.jeeb_id')
                            ->label('معرّف جيب')
                            ->placeholder('-'),
                        TextEntry::make('vendorFinancialDetail.jeeb_name')
                            ->label('اسم حساب جيب')
                            ->placeholder('-'),
                        TextEntry::make('vendorFinancialDetail.total_commission_owed')
                            ->label('إجمالي العمولات المستحقة')
                            ->badge()
                            ->color('warning')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Tabs::make('البيانات الشرائية')
                    ->visible(fn (?User $record): bool => $record?->role === 'customer')
                    ->tabs([
                        Tab::make('السلة')
                            ->badge(fn (): int => (int) $this->getRecord()->cartItems()->count())
                            ->schema([
                                RepeatableEntry::make('cartItems')
                                    ->label('عناصر السلة')
                                    ->placeholder('لا توجد عناصر في السلة')
                                    ->schema([
                                        TextEntry::make('product.name_ar')->label('المنتج')->placeholder('-'),
                                        TextEntry::make('quantity')->label('الكمية')->placeholder('-'),
                                        TextEntry::make('price_at_add')->label('السعر عند الإضافة')->placeholder('-'),
                                        TextEntry::make('created_at')->label('تاريخ الإضافة')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('الطلبات')
                            ->badge(fn (): int => (int) $this->getRecord()->orders()->count())
                            ->schema([
                                RepeatableEntry::make('orders')
                                    ->label('الطلبات')
                                    ->placeholder('لا توجد طلبات')
                                    ->schema([
                                        TextEntry::make('order_number')->label('رقم الطلب')->placeholder('-'),
                                        TextEntry::make('product.name_ar')->label('المنتج')->placeholder('-'),
                                        TextEntry::make('quantity')->label('الكمية')->placeholder('-'),
                                        TextEntry::make('total_price')->label('الإجمالي')->placeholder('-'),
                                        TextEntry::make('status')->label('حالة الطلب')->placeholder('-'),
                                        TextEntry::make('created_at')->label('تاريخ الطلب')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
                Tabs::make('التفاعل')
                    ->visible(fn (?User $record): bool => $record?->role === 'customer')
                    ->tabs([
                        Tab::make('التعليقات')
                            ->badge(fn (): int => (int) $this->getRecord()->comments()->count())
                            ->schema([
                                RepeatableEntry::make('comments')
                                    ->label('التعليقات')
                                    ->placeholder('لا توجد تعليقات')
                                    ->schema([
                                        TextEntry::make('body')
                                            ->label('التعليق')
                                            ->placeholder('-')
                                            ->columnSpanFull()
                                            ->extraAttributes(fn (?Comment $record): array => $record ? ['id' => 'comment-' . $record->id] : [])
                                            ->suffixAction(
                                                Action::make('deleteUserComment')
                                                    ->icon('heroicon-o-x-mark')
                                                    ->tooltip('حذف التعليق')
                                                    ->color('danger')
                                                    ->requiresConfirmation()
                                                    ->action(function (?Comment $record): void {
                                                        if (! $record) {
                                                            return;
                                                        }

                                                        $this->deleteComment($record->id);
                                                    })
                                            ),
                                        TextEntry::make('comment_reports_count')
                                            ->label('بلاغات على التعليق')
                                            ->state(fn (?Comment $record): int => (int) ($record?->reports()->count() ?? 0))
                                            ->badge()
                                            ->color(fn (?Comment $record): string => (int) ($record?->reports()->count() ?? 0) > 0 ? 'danger' : 'success'),
                                        TextEntry::make('comment_target_type')
                                            ->label('نوع الهدف')
                                            ->state(function (?Comment $record): string {
                                                if (! $record?->commentable_type) {
                                                    return '-';
                                                }

                                                return match ($record->commentable_type) {
                                                    \App\Models\Product::class => 'منتج',
                                                    \App\Models\Store::class => 'متجر',
                                                    default => class_basename($record->commentable_type),
                                                };
                                            })
                                            ->placeholder('-'),
                                        TextEntry::make('comment_target_name')
                                            ->label('تم التعليق على')
                                            ->state(function (?Comment $record): string {
                                                $target = $record?->commentable;

                                                if (! $target) {
                                                    return '-';
                                                }

                                                if ($target instanceof \App\Models\Product) {
                                                    return $target->name_ar ?: $target->name_en ?: ('#' . $target->id);
                                                }

                                                if ($target instanceof \App\Models\Store) {
                                                    return $target->name ?: ('#' . $target->id);
                                                }

                                                return '#' . $target->id;
                                            })
                                            ->url(function (?Comment $record): ?string {
                                                if (! $record?->commentable_id || ! $record?->commentable_type) {
                                                    return null;
                                                }

                                                if ($record->commentable_type === \App\Models\Product::class) {
                                                    return ProductResource::getUrl('view', ['record' => $record->commentable_id]);
                                                }

                                                if ($record->commentable_type === \App\Models\Store::class) {
                                                    return StoreResource::getUrl('view', ['record' => $record->commentable_id]);
                                                }

                                                return null;
                                            })
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->color('primary')
                                            ->placeholder('-'),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('المفضلة')
                            ->badge(fn (): int => (int) $this->getRecord()->favorites()->count())
                            ->schema([
                                RepeatableEntry::make('favorites')
                                    ->label('العناصر المفضلة')
                                    ->placeholder('لا توجد عناصر مفضلة')
                                    ->schema([
                                        TextEntry::make('favorite_target_type')
                                            ->label('نوع الهدف')
                                            ->state(function (?\App\Models\Favorite $record): string {
                                                if (! $record?->favoritable_type) {
                                                    return '-';
                                                }

                                                return match ($record->favoritable_type) {
                                                    \App\Models\Product::class => 'منتج',
                                                    \App\Models\Store::class => 'متجر',
                                                    default => class_basename($record->favoritable_type),
                                                };
                                            })
                                            ->placeholder('-'),
                                        TextEntry::make('favorite_target_name')
                                            ->label('العنصر المفضّل')
                                            ->state(function (?\App\Models\Favorite $record): string {
                                                $target = $record?->favoritable;

                                                if (! $target) {
                                                    return '-';
                                                }

                                                if ($target instanceof \App\Models\Product) {
                                                    return $target->name_ar ?: $target->name_en ?: ('#' . $target->id);
                                                }

                                                if ($target instanceof \App\Models\Store) {
                                                    return $target->name ?: ('#' . $target->id);
                                                }

                                                return '#' . $target->id;
                                            })
                                            ->url(function (?\App\Models\Favorite $record): ?string {
                                                if (! $record?->favoritable_id || ! $record?->favoritable_type) {
                                                    return null;
                                                }

                                                if ($record->favoritable_type === \App\Models\Product::class) {
                                                    return ProductResource::getUrl('view', ['record' => $record->favoritable_id]);
                                                }

                                                if ($record->favoritable_type === \App\Models\Store::class) {
                                                    return StoreResource::getUrl('view', ['record' => $record->favoritable_id]);
                                                }

                                                return null;
                                            })
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->color('primary')
                                            ->placeholder('-'),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('البلاغات')
                            ->badge(fn (): int => (int) $this->getRecord()->reports()->count())
                            ->schema([
                                RepeatableEntry::make('reports')
                                    ->label('البلاغات')
                                    ->placeholder('لا توجد بلاغات')
                                    ->schema([
                                        TextEntry::make('reason')->label('السبب')->placeholder('-')->columnSpanFull(),
                                        TextEntry::make('report_target_type')
                                            ->label('نوع الهدف')
                                            ->state(function (?\App\Models\Report $record): string {
                                                if (! $record?->reportable_type) {
                                                    return '-';
                                                }

                                                return match ($record->reportable_type) {
                                                    \App\Models\Product::class => 'منتج',
                                                    \App\Models\Store::class => 'متجر',
                                                    \App\Models\Comment::class => 'تعليق',
                                                    default => class_basename($record->reportable_type),
                                                };
                                            })
                                            ->placeholder('-'),
                                        TextEntry::make('report_target_name')
                                            ->label('تم الإبلاغ عن')
                                            ->state(function (?\App\Models\Report $record): string {
                                                $target = $record?->reportable;

                                                if (! $target) {
                                                    return '-';
                                                }

                                                if ($target instanceof \App\Models\Product) {
                                                    return $target->name_ar ?: $target->name_en ?: ('#' . $target->id);
                                                }

                                                if ($target instanceof \App\Models\Store) {
                                                    return $target->name ?: ('#' . $target->id);
                                                }

                                                if ($target instanceof \App\Models\Comment) {
                                                    return 'تعليق #' . $target->id;
                                                }

                                                return '#' . $target->id;
                                            })
                                            ->url(function (?\App\Models\Report $record): ?string {
                                                if (! $record?->reportable_id || ! $record?->reportable_type) {
                                                    return null;
                                                }

                                                if ($record->reportable_type === \App\Models\Product::class) {
                                                    return ProductResource::getUrl('view', ['record' => $record->reportable_id]);
                                                }

                                                if ($record->reportable_type === \App\Models\Store::class) {
                                                    return StoreResource::getUrl('view', ['record' => $record->reportable_id]);
                                                }

                                                return null;
                                            })
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->color('primary')
                                            ->placeholder('-'),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('تفاصيل المتجر')
                    ->visible(fn (?User $record): bool => $record?->role === 'vendor')
                    ->schema([
                        TextEntry::make('store.name')
                            ->label('اسم المتجر')
                            ->visible(fn (?User $record): bool => filled($record?->store))
                            ->color('primary')
                            ->icon('heroicon-o-arrow-top-right-on-square')
                            ->url(fn (?User $record): ?string => $record?->store ? StoreResource::getUrl('view', ['record' => $record->store]) : null),
                        TextEntry::make('store_link')
                            ->label('الانتقال')
                            ->state('فتح صفحة المتجر')
                            ->badge()
                            ->color('primary')
                            ->visible(fn (?User $record): bool => filled($record?->store))
                            ->url(fn (?User $record): ?string => $record?->store ? StoreResource::getUrl('view', ['record' => $record->store]) : null),
                        TextEntry::make('store.city')
                            ->label('مدينة المتجر')
                            ->visible(fn (?User $record): bool => filled($record?->store))
                            ->placeholder('-'),
                        TextEntry::make('store.address')
                            ->label('عنوان المتجر')
                            ->visible(fn (?User $record): bool => filled($record?->store))
                            ->placeholder('-'),
                        TextEntry::make('store.is_active')
                            ->label('حالة المتجر')
                            ->visible(fn (?User $record): bool => filled($record?->store))
                            ->formatStateUsing(fn (?bool $state): string => $state ? 'نشط' : 'غير نشط')
                            ->badge(),
                        TextEntry::make('store_status')
                            ->label('المتجر')
                            ->state('لا يوجد متجر لهذا البائع')
                            ->badge()
                            ->visible(fn (?User $record): bool => blank($record?->store)),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    public function deleteComment(int|string $commentId): void
    {
        $this->getRecord()->comments()->whereKey($commentId)->delete();
    }
}
