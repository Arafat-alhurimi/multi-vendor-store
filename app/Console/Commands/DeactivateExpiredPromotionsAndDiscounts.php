<?php

namespace App\Console\Commands;

use App\Models\ProductDiscount;
use App\Models\Promotion;
use Illuminate\Console\Command;

class DeactivateExpiredPromotionsAndDiscounts extends Command
{
    protected $signature = 'promotions:deactivate-expired';

    protected $description = 'Synchronize promotions and discounts active state based on start/end dates';

    public function handle(): int
    {
        $now = now();

        $activatedPromotionsCount = Promotion::query()
            ->where('is_active', false)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->update(['is_active' => true]);

        $expiredPromotionsCount = Promotion::query()
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update(['is_active' => false]);

        $activatedDiscountsCount = ProductDiscount::query()
            ->where('is_active', false)
            ->where(function ($query) use ($now): void {
                $query->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($query) use ($now): void {
                $query->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
            })
            ->update(['is_active' => true]);

        $expiredDiscountsCount = ProductDiscount::query()
            ->where('is_active', true)
            ->whereNotNull('ends_at')
            ->where('ends_at', '<', $now)
            ->update(['is_active' => false]);

        $this->info("Promotions activated: {$activatedPromotionsCount}");
        $this->info("Expired promotions deactivated: {$expiredPromotionsCount}");
        $this->info("Discounts activated: {$activatedDiscountsCount}");
        $this->info("Expired product discounts deactivated: {$expiredDiscountsCount}");

        return self::SUCCESS;
    }
}
