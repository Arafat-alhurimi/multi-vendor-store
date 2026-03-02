<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use App\Models\FilamentDatabaseNotification;
use Laravel\Sanctum\HasApiTokens;
use Filament\Panel;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Models\VendorAdSubscription;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    protected static function booted(): void
    {
        static::updated(function (User $user): void {
            if (! $user->is_active) {
                $user->stores()->where('is_active', true)->update(['is_active' => false]);
            }
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = ['name', 'email', 'password', 'phone', 'role', 'avatar', 'is_active', 'otp_verified_at'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'otp_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }
    
    /**
     * Override notifications relation to point to filament_notifications table
     */
    public function notifications()
    {
        return $this->morphMany(FilamentDatabaseNotification::class, 'notifiable')->latest();
    }

    public function readNotifications()
    {
        return $this->notifications()->read();
    }

    public function unreadNotifications()
    {
        return $this->notifications()->unread();
    }
    
    public function canAccessPanel(Panel $panel): bool
    {
        // السماح فقط للأدمن بدخول لوحة التحكم
        return $this->role === 'admin';
    }
    
    /**
     * Get the stores that belong to the user.
     */
    public function stores()
    {
        return $this->hasMany(Store::class);
    }
    
    /**
     * Check if the user is a seller (has at least one store).
     */
    public function getIsSellerAttribute(): bool
    {
        return $this->stores()->exists();
    }
    
    /**
     * Get the first store of the user.
     */
    public function getStoreAttribute(): ?Store
    {
        return $this->stores()->first();
    }

    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    public function commentReplies()
    {
        return $this->hasMany(CommentReply::class);
    }

    public function ratings()
    {
        return $this->hasMany(Rating::class);
    }

    public function favorites()
    {
        return $this->hasMany(Favorite::class);
    }

    public function reports()
    {
        return $this->hasMany(Report::class);
    }

    public function vendorFinancialDetail(): HasOne
    {
        return $this->hasOne(VendorFinancialDetail::class);
    }

    public function adSubscriptions()
    {
        return $this->hasMany(VendorAdSubscription::class, 'vendor_id');
    }

    public function cartItems()
    {
        return $this->hasMany(CartItem::class);
    }

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    public function vendorOrders()
    {
        return $this->hasMany(Order::class, 'vendor_id');
    }
}



