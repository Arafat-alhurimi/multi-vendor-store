<?php

namespace App\Notifications;

use App\Models\Promotion;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewAppPromotionNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly Promotion $promotion)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'حملة جديدة متاحة',
            'message' => sprintf('تم إطلاق حملة جديدة: %s', $this->promotion->title),
            'promotion_id' => $this->promotion->id,
            'level' => $this->promotion->level,
            'starts_at' => $this->promotion->starts_at,
            'ends_at' => $this->promotion->ends_at,
        ];
    }
}
