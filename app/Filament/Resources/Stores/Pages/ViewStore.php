<?php

namespace App\Filament\Resources\Stores\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Stores\StoreResource;
use App\Models\Ad;
use App\Models\Product;
use App\Models\Promotion;
use App\Models\Report;
use App\Models\VendorAdSubscription;
use Filament\Actions\Action;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class ViewStore extends ViewRecord
{
    protected static string $resource = StoreResource::class;

    private ?VendorAdSubscription $latestSubscriptionCache = null;

    private bool $hasResolvedLatestSubscription = false;

    private ?array $storeAndProductReportsCache = null;

    private bool $hasResolvedStoreAndProductReports = false;

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
                        TextEntry::make('categories_count')
                            ->label('عدد الفئات الرئيسية')
                            ->state(fn (): int => (int) $this->getRecord()->categories()->count())
                            ->badge()
                            ->color('info'),
                        RepeatableEntry::make('categories')
                            ->label('')
                            ->schema([
                                TextEntry::make('name_ar')
                                    ->hiddenLabel()
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('-'),
                            ])
                            ->contained(false)
                            ->columns(4)
                            ->placeholder('لا توجد فئات رئيسية')
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
                        TextEntry::make('reports_count')
                            ->label('عدد البلاغات (المتجر + المنتجات)')
                            ->state(fn (): int => $this->getStoreAndProductReportsCount())
                            ->badge()
                            ->color(fn (): string => $this->getStoreAndProductReportsCount() > 0 ? 'danger' : 'success')
                            ->icon(fn (): string => $this->getStoreAndProductReportsCount() > 0 ? 'heroicon-o-flag' : 'heroicon-o-check-circle'),
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
                Section::make('تفاصيل الاشتراك الإعلاني')
                    ->schema([
                        TextEntry::make('subscription_package_name')
                            ->label('اسم الباقة')
                            ->state(fn (): string => $this->getLatestSubscription()?->adPackage?->name ?? 'لا يوجد اشتراك')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('subscription_status')
                            ->label('حالة الاشتراك')
                            ->state(fn (): string => match ($this->getLatestSubscription()?->status) {
                                'active' => 'نشط',
                                'pending' => 'قيد الانتظار',
                                'expired' => 'منتهي',
                                default => 'لا يوجد',
                            })
                            ->badge()
                            ->color(fn (): string => match ($this->getLatestSubscription()?->status) {
                                'active' => 'success',
                                'pending' => 'warning',
                                'expired' => 'danger',
                                default => 'gray',
                            }),
                        TextEntry::make('subscription_allowed_limits')
                            ->label('الحد المسموح')
                            ->state(function (): string {
                                $package = $this->getLatestSubscription()?->adPackage;

                                if (! $package) {
                                    return 'لا يوجد';
                                }

                                return sprintf(
                                    'صور %d | فيديو %d | عروض %d',
                                    (int) $package->max_images,
                                    (int) $package->max_videos,
                                    (int) $package->max_promotions,
                                );
                            })
                            ->badge()
                            ->color('info'),
                        TextEntry::make('subscription_starts_at')
                            ->label('بداية الاشتراك')
                            ->state(fn (): ?string => $this->getLatestSubscription()?->starts_at?->format('Y-m-d H:i'))
                            ->placeholder('-')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('subscription_ends_at')
                            ->label('نهاية الاشتراك')
                            ->state(fn (): ?string => $this->getLatestSubscription()?->ends_at?->format('Y-m-d H:i'))
                            ->placeholder('-')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('subscription_used_limits')
                            ->label('المستخدم من الباقة')
                            ->state(function (): string {
                                $subscription = $this->getLatestSubscription();

                                if (! $subscription) {
                                    return 'لا يوجد';
                                }

                                return sprintf(
                                    'صور %d | فيديو %d | عروض %d',
                                    $this->countUsedContentForLatestSubscription('image'),
                                    $this->countUsedContentForLatestSubscription('video'),
                                    $this->countUsedContentForLatestSubscription('promotion'),
                                );
                            })
                            ->badge()
                            ->color('warning'),
                        TextEntry::make('subscription_active_content_count')
                            ->label('المحتوى النشط')
                            ->state(fn (): int => $this->countActiveContentForLatestSubscription())
                            ->badge()
                            ->color('success'),
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
                                        TextEntry::make('body')
                                            ->label('التعليق')
                                            ->placeholder('-')
                                            ->columnSpanFull()
                                            ->suffixAction(
                                                Action::make('deleteStoreComment')
                                                    ->icon('heroicon-o-x-mark')
                                                    ->tooltip('حذف التعليق')
                                                    ->color('danger')
                                                    ->requiresConfirmation()
                                                    ->action(function (?\App\Models\Comment $record): void {
                                                        if (! $record) {
                                                            return;
                                                        }

                                                        $this->deleteComment($record->id);
                                                    })
                                            ),
                                        TextEntry::make('comment_reports_count')
                                            ->label('بلاغات على التعليق')
                                            ->state(fn (?\App\Models\Comment $record): int => (int) ($record?->reports()->count() ?? 0))
                                            ->badge()
                                            ->color(fn (?\App\Models\Comment $record): string => (int) ($record?->reports()->count() ?? 0) > 0 ? 'danger' : 'success'),
                                        TextEntry::make('created_at')->label('التاريخ')->since()->placeholder('-'),
                                    ])
                                    ->columns(2),
                            ]),
                        Tab::make('البلاغات')
                            ->badge(fn (): int => $this->getStoreAndProductReportsCount())
                            ->schema([
                                RepeatableEntry::make('store_and_product_reports')
                                    ->label('بلاغات المتجر والمنتجات')
                                    ->state(fn (): array => $this->resolveStoreAndProductReports())
                                    ->placeholder('لا توجد بلاغات')
                                    ->schema([
                                        TextEntry::make('reporter_name')->label('المبلّغ')->placeholder('-'),
                                        TextEntry::make('reason')->label('سبب البلاغ')->placeholder('-')->columnSpanFull(),
                                        TextEntry::make('report_target_type')->label('نوع الهدف')->badge()->placeholder('-'),
                                        TextEntry::make('report_target_name')
                                            ->label('تم الإبلاغ عن')
                                            ->url(fn ($record): ?string => $this->extractReportTargetUrl($record))
                                            ->icon('heroicon-o-arrow-top-right-on-square')
                                            ->color('primary')
                                            ->placeholder('-'),
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
                        Tab::make('المحتوى الإعلاني')
                            ->badge(fn (): int => $this->getActiveAdsForLatestSubscription()->count())
                            ->schema([
                                View::make('filament.stores.active-ads-cards')
                                    ->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
            ])
            ->columns(2);
    }

    private function getStoreAndProductReportsCount(): int
    {
        return count($this->resolveStoreAndProductReports());
    }

    public function deleteComment(int|string $commentId): void
    {
        $this->getRecord()->comments()->whereKey($commentId)->delete();
    }

    private function resolveStoreAndProductReports(): array
    {
        if ($this->hasResolvedStoreAndProductReports) {
            return $this->storeAndProductReportsCache ?? [];
        }

        $storeId = (int) $this->getRecord()->getKey();
        $productIds = $this->getRecord()->products()->pluck('id');

        $reports = Report::query()
            ->with(['user', 'reportable'])
            ->where(function ($query) use ($storeId, $productIds): void {
                $query
                    ->where(function ($nested) use ($storeId): void {
                        $nested
                            ->where('reportable_type', \App\Models\Store::class)
                            ->where('reportable_id', $storeId);
                    })
                    ->orWhere(function ($nested) use ($productIds): void {
                        $nested
                            ->where('reportable_type', Product::class)
                            ->whereIn('reportable_id', $productIds);
                    });
            })
            ->latest('created_at')
            ->get();

        $this->storeAndProductReportsCache = $reports
            ->map(function (Report $report): array {
                $target = $report->reportable;

                $targetType = match ($report->reportable_type) {
                    Product::class => 'منتج',
                    \App\Models\Store::class => 'متجر',
                    default => class_basename((string) $report->reportable_type),
                };

                $targetName = match (true) {
                    $target instanceof Product => (string) ($target->name_ar ?: $target->name_en ?: ('#' . $target->id)),
                    $target instanceof \App\Models\Store => (string) ($target->name ?: ('#' . $target->id)),
                    default => '#' . (string) $report->reportable_id,
                };

                $targetUrl = match ($report->reportable_type) {
                    Product::class => ProductResource::getUrl('view', ['record' => $report->reportable_id]),
                    \App\Models\Store::class => StoreResource::getUrl('view', ['record' => $report->reportable_id]),
                    default => null,
                };

                return [
                    'reporter_name' => (string) ($report->user?->name ?? '-'),
                    'reason' => (string) ($report->reason ?? '-'),
                    'report_target_type' => $targetType,
                    'report_target_name' => $targetName,
                    'target_url' => $targetUrl,
                    'created_at' => $report->created_at,
                ];
            })
            ->all();

        $this->hasResolvedStoreAndProductReports = true;

        return $this->storeAndProductReportsCache;
    }

    private function extractReportTargetUrl(mixed $record): ?string
    {
        $url = data_get($record, 'target_url');

        return filled($url) ? (string) $url : null;
    }

    private function getLatestSubscription(): ?VendorAdSubscription
    {
        if ($this->hasResolvedLatestSubscription) {
            return $this->latestSubscriptionCache;
        }

        $vendorId = (int) ($this->getRecord()->user_id ?? 0);

        if ($vendorId <= 0) {
            $this->hasResolvedLatestSubscription = true;

            return null;
        }

        $active = VendorAdSubscription::query()
            ->with('adPackage')
            ->where('vendor_id', $vendorId)
            ->active(now())
            ->latest('ends_at')
            ->first();

        if ($active) {
            $this->latestSubscriptionCache = $active;
            $this->hasResolvedLatestSubscription = true;

            return $this->latestSubscriptionCache;
        }

        $this->latestSubscriptionCache = VendorAdSubscription::query()
            ->with('adPackage')
            ->where('vendor_id', $vendorId)
            ->latest('created_at')
            ->first();

        $this->hasResolvedLatestSubscription = true;

        return $this->latestSubscriptionCache;
    }

    private function countActiveContentForLatestSubscription(): int
    {
        $subscription = $this->getLatestSubscription();

        if (! $subscription) {
            return 0;
        }

        $now = now();

        return $subscription->ads()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->count();
    }

    private function countUsedContentForLatestSubscription(string $type): int
    {
        $subscription = $this->getLatestSubscription();

        if (! $subscription) {
            return 0;
        }

        $query = $subscription->ads()->withoutGlobalScopes();

        if ($type === 'promotion') {
            return $query->where('click_action', 'promotion')->count();
        }

        return $query
            ->where('media_type', $type)
            ->where(function ($inner): void {
                $inner->whereNull('click_action')->orWhere('click_action', '!=', 'promotion');
            })
            ->count();
    }

    private function getActiveAdsForLatestSubscription(): Collection
    {
        $subscription = $this->getLatestSubscription();

        if (! $subscription) {
            return collect();
        }

        $now = now();

        return $subscription->ads()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->latest('id')
            ->get()
            ->map(function (Ad $ad): array {
                $mediaPath = (string) ($ad->media_path ?? '');
                $s3BaseUrl = rtrim((string) config('filesystems.disks.s3.url', ''), '/');
                $mediaUrl = str_starts_with($mediaPath, 'http://') || str_starts_with($mediaPath, 'https://')
                    ? $mediaPath
                    : ($s3BaseUrl !== '' ? $s3BaseUrl.'/'.ltrim($mediaPath, '/') : $mediaPath);

                return [
                    'id' => (int) $ad->id,
                    'media_type' => (string) $ad->media_type,
                    'media_url' => $mediaUrl,
                    'transition_type' => $this->translateTransitionType($ad->click_action),
                    'transition_target' => $this->resolveTransitionTarget($ad),
                    'starts_at' => $ad->starts_at?->format('Y-m-d H:i') ?? '-',
                    'ends_at' => $ad->ends_at?->format('Y-m-d H:i') ?? '-',
                    'store_name' => (string) ($this->getRecord()->name ?? '-'),
                ];
            });
    }

    private function translateTransitionType(?string $action): string
    {
        return match ($action) {
            'promotion' => 'عرض',
            'product' => 'منتج',
            'store' => 'متجر',
            'url' => 'رابط',
            default => 'غير محدد',
        };
    }

    private function resolveTransitionTarget(Ad $ad): string
    {
        $actionId = $ad->action_id;

        if (! filled($actionId)) {
            return '-';
        }

        return match ($ad->click_action) {
            'promotion' => (string) (Promotion::query()->whereKey((int) $actionId)->value('title') ?? 'عرض غير موجود'),
            'product' => (string) (Product::query()->whereKey((int) $actionId)->value('name_ar') ?? 'منتج غير موجود'),
            'store' => (string) (\App\Models\Store::query()->whereKey((int) $actionId)->value('name') ?? 'متجر غير موجود'),
            'url' => (string) $actionId,
            default => (string) $actionId,
        };
    }
}
