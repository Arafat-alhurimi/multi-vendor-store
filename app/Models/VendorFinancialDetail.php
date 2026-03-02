<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VendorFinancialDetail extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'card_image',
        'back_card_image',
        'kuraimi_account_number',
        'kuraimi_account_name',
        'jeeb_id',
        'jeeb_name',
        'total_commission_owed',
    ];

    protected $casts = [
        'total_commission_owed' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
