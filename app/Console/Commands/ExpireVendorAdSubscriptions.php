<?php

namespace App\Console\Commands;

use App\Models\Ad;
use App\Models\VendorAdSubscription;
use Illuminate\Console\Command;

class ExpireVendorAdSubscriptions extends Command
{
    protected $signature = 'ads:expire-subscriptions';

    protected $description = 'Expire vendor ad subscriptions and deactivate related ads.';

    public function handle(): int
    {
        $now = now();

        $expiredSubscriptions = VendorAdSubscription::query()
            ->where('status', 'active')
            ->whereNotNull('ends_at')
            ->where('ends_at', '<=', $now)
            ->get();

        $subscriptionIds = $expiredSubscriptions->pluck('id');

        foreach ($expiredSubscriptions as $subscription) {
            $subscription->update(['status' => 'expired']);
        }

        if ($subscriptionIds->isNotEmpty()) {
            Ad::withoutGlobalScopes()
                ->whereIn('vendor_ad_subscription_id', $subscriptionIds)
                ->update(['is_active' => false]);
        }

        $this->info('Expired subscriptions: '.$expiredSubscriptions->count());

        return self::SUCCESS;
    }
}
