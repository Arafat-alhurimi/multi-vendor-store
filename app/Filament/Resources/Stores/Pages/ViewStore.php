<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Stores\StoreResource;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class ViewStore extends ViewRecord
{
    protected static string $resource = StoreResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('toggleStoreActive')
                ->label(fn (): string => $this->getRecord()->is_active ? 'إلغاء تفعيل المتجر' : 'تفعيل المتجر')
                ->icon(fn (): string => $this->getRecord()->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                ->color(fn (): string => $this->getRecord()->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->action(function (): void {
                    $store = $this->getRecord();

                    if (! $store->is_active) {
                        $user = $store->user;

                        if (! $user || ! $user->is_active) {
                            Notification::make()
                                ->title('لا يمكن التفعيل')
                                ->body('لا يمكن تفعيل المتجر لأن حساب البائع غير مفعل.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $store->update(['is_active' => true]);

                        Notification::make()
                            ->title('تم تفعيل المتجر')
                            ->success()
                            ->send();

                        return;
                    }

                    $store->update(['is_active' => false]);

                    Notification::make()
                        ->title('تم إلغاء تفعيل المتجر')
                        ->warning()
                        ->send();
                }),
            Action::make('approveVendor')
                ->label('موافقة وتفعيل البائع والمتجر')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->canApprove())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->approveVendorAndStore();
                }),
        ];
    }

    protected function canApprove(): bool
    {
        $store = $this->getRecord();
        $user = $store->user;

        return (bool) $user
            && $user->role === 'vendor'
            && ! $user->is_active
            && filled($user->otp_verified_at)
            && ! $store->is_active;
    }

    protected function approvalStatusLabel(): string
    {
        $store = $this->getRecord();
        $user = $store->user;

        if (! $user) {
            return 'لا يوجد بائع مرتبط بالمتجر';
        }

        if ($user->is_active && $store->is_active) {
            return 'تمت الموافقة والتفعيل';
        }

        if (! filled($user->otp_verified_at)) {
            return 'بانتظار التحقق من OTP';
        }

        if (! $user->is_active) {
            return 'جاهز للموافقة';
        }

        return 'حساب البائع مفعل والمتجر غير مفعل';
    }

    protected function approveVendorAndStore(): void
    {
        if (! $this->canApprove()) {
            Notification::make()
                ->title('لا يمكن الموافقة')
                ->body($this->approvalStatusLabel())
                ->danger()
                ->send();

            return;
        }

        $store = $this->getRecord();
        $user = $store->user;

        $user?->update(['is_active' => true]);
        $store->update(['is_active' => true]);

        Notification::make()
            ->title('تمت الموافقة')
            ->body('تم تفعيل حساب البائع والمتجر بنجاح.')
            ->success()
            ->send();
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->schema([
                Section::make('الهوية')
                    ->schema([
                        ImageEntry::make('logo')
                            ->disk('s3')
                            ->label('شعار المتجر'),
                        TextEntry::make('name')
                            ->label('اسم المتجر')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('معلومات المتجر')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('البائع')
                            ->placeholder('-'),
                        TextEntry::make('user.phone')
                            ->label('هاتف البائع')
                            ->placeholder('-'),
                        TextEntry::make('user.email')
                            ->label('بريد البائع')
                            ->placeholder('-'),
                        TextEntry::make('user.otp_verified_at')
                            ->label('توثيق OTP')
                            ->formatStateUsing(fn ($state): string => filled($state) ? 'موثّق' : 'غير موثّق')
                            ->badge()
                            ->color(fn ($state): string => filled($state) ? 'success' : 'warning')
                            ->placeholder('-'),
                        TextEntry::make('user.created_at')
                            ->label('تاريخ انضمام البائع')
                            ->dateTime('Y-m-d h:i A')
                            ->placeholder('-'),
                        TextEntry::make('approval_status')
                            ->label('حالة الموافقة')
                            ->badge()
                            ->state(fn (): string => $this->approvalStatusLabel())
                            ->color(fn (): string => match ($this->approvalStatusLabel()) {
                                'تمت الموافقة والتفعيل' => 'success',
                                'جاهز للموافقة' => 'warning',
                                'بانتظار التحقق من OTP' => 'warning',
                                default => 'gray',
                            }),
                        TextEntry::make('city')
                            ->label('المدينة')
                            ->placeholder('-'),
                        TextEntry::make('address')
                            ->label('العنوان')
                            ->placeholder('-'),
                        TextEntry::make('is_active')
                            ->label('الحالة')
                            ->badge()
                            ->formatStateUsing(fn (bool $state): string => $state ? 'نشط' : 'غير نشط')
                            ->color(fn (bool $state): string => $state ? 'success' : 'danger'),
                        TextEntry::make('categories.name_ar')
                            ->label('الفئات الرئيسية')
                            ->listWithLineBreaks()
                            ->placeholder('-')
                            ->columnSpanFull(),
                        TextEntry::make('description')
                            ->label('الوصف')
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Section::make('إحصائيات المتجر')
                    ->schema([
                        TextEntry::make('products_count')
                            ->label('عدد المنتجات')
                            ->state(fn () => (int) $this->getRecord()->products()->count()),
                        TextEntry::make('cart_items_count')
                            ->label('عدد المنتجات المضافة للسلة')
                            ->state(fn () => (int) $this->getRecord()->cartItems()->count()),
                        TextEntry::make('orders_count')
                            ->label('عدد الطلبات')
                            ->state(fn () => (int) $this->getRecord()->orders()->count()),
                        TextEntry::make('favorites_count')
                            ->label('عدد الإضافات للمفضلة')
                            ->state(fn () => (int) $this->getRecord()->favorites()->count()),
                        TextEntry::make('avg_rating')
                            ->label('متوسط التقييم')
                            ->state(fn () => number_format((float) ($this->getRecord()->ratings()->avg('value') ?? 0), 2)),
                    ])
                    ->columns(5)
                    ->columnSpanFull(),
                Section::make('التفاصيل المالية')
                    ->schema([
                        TextEntry::make('user.vendorFinancialDetail.kuraimi_account_number')
                            ->label('رقم حساب الكريمي')
                            ->placeholder('-'),
                        TextEntry::make('user.vendorFinancialDetail.kuraimi_account_name')
                            ->label('اسم حساب الكريمي')
                            ->placeholder('-'),
                        TextEntry::make('user.vendorFinancialDetail.jeeb_id')
                            ->label('معرّف جيب')
                            ->placeholder('-'),
                        TextEntry::make('user.vendorFinancialDetail.jeeb_name')
                            ->label('اسم حساب جيب')
                            ->placeholder('-'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
                Tabs::make('التفاعل')
                    ->tabs([
                        Tab::make('التعليقات')
                            ->badge(fn (): int => (int) $this->getRecord()->comments()->count())
                            ->schema([
                                RepeatableEntry::make('comments')
                                    ->label('تعليقات المتجر')
                                    ->placeholder('لا توجد تعليقات')
                                    ->schema([
                                        TextEntry::make('user.name')->label('المستخدم')->placeholder('-'),
                                        TextEntry::make('body')->label('التعليق')->placeholder('-')->columnSpanFull(),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('الوسائط')
                            ->badge(fn (): int => (int) $this->getRecord()->media()->count())
                            ->schema([
                                RepeatableEntry::make('media')
                                    ->label('وسائط المتجر')
                                    ->placeholder('لا توجد وسائط')
                                    ->schema([
                                        TextEntry::make('file_name')->label('اسم الملف')->placeholder('-'),
                                        TextEntry::make('file_type')->label('نوع الملف')->badge()->placeholder('-'),
                                        ImageEntry::make('url')
                                            ->label('المعاينة')
                                            ->visible(fn ($record): bool => $record?->file_type === 'image'),
                                        TextEntry::make('video_preview')
                                            ->label('معاينة الفيديو')
                                            ->state(fn ($record): ?string => filled($record?->url)
                                                ? '<video src="'.$record->url.'" controls preload="metadata" width="180" style="max-height:120px;border-radius:8px;"></video>'
                                                : null)
                                            ->html()
                                            ->visible(fn ($record): bool => $record?->file_type === 'video')
                                            ->placeholder(''),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }
}
