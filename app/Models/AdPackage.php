<?php

namespace App\Models;

use App\Models\VendorAdSubscription;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdPackage extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'price',
        'duration_days',
        'max_images',
        'max_videos',
        'max_promotions',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'duration_days' => 'integer',
        'max_images' => 'integer',
        'max_videos' => 'integer',
        'max_promotions' => 'integer',
        'is_active' => 'boolean',
    ];

    public function vendorSubscriptions(): HasMany
    {
        return $this->hasMany(VendorAdSubscription::class);
    }
}
