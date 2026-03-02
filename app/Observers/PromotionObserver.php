<?php

namespace App\Observers;

use App\Models\Promotion;
use App\Models\User;
use App\Notifications\NewAppPromotionNotification;
use Illuminate\Support\Facades\Notification;

class PromotionObserver
{
    public function created(Promotion $promotion): void
    {
        if ($promotion->level !== 'app') {
            return;
        }

        User::query()
            ->where('role', 'vendor')
            ->where('is_active', true)
            ->chunkById(200, function ($vendors) use ($promotion): void {
                Notification::send($vendors, new NewAppPromotionNotification($promotion));
            });
    }
}
