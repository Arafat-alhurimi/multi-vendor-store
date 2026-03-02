<?php

namespace App\Models;

use App\Models\PromotionItem;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Filesystem\FilesystemAdapter;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name_ar',
        'name_en',
        'description_ar',
        'description_en',
        'image',
        'is_active',
        'order',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'order' => 'integer',
    ];

    // Helper to get full S3 URL for the image
    public function getImageUrlAttribute(): ?string
    {
        if (! $this->image) {
            return null;
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($this->image);
    }

    public function subcategories()
    {
        return $this->hasMany(Subcategory::class);
    }

    public function stores()
    {
        return $this->belongsToMany(Store::class, 'category_store')->withTimestamps();
    }

    public function promotionItems(): MorphMany
    {
        return $this->morphMany(PromotionItem::class, 'promotable');
    }
}
