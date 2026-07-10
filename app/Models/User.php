<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Lab404\Impersonate\Models\Impersonate;
class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable, HasRoles, Impersonate;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'provider',
        'provider_id',
        'provider_avatar',
    ];

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
            'password' => 'hashed',
        ];
    }

    public function cases()
    {
        return $this->hasMany(Cases::class);
    }

    public function trips()
    {
        return $this->hasMany(Trip::class);
    }

    /** Any trip found eligible for compensation whose claim isn't filed yet. */
    public function hasTripsAwaitingClaim(): bool
    {
        return $this->trips()
            ->where('eligibility_status', 'eligible')
            ->whereDoesntHave('claims')
            ->exists();
    }

    public function emailConfig()
    {
        return $this->hasOne(UserEmailConfig::class);
    }

    public function canImpersonate()
    {
        return $this->hasRole('admin'); 
    }

    /**
     * Optional: Control who can be impersonated
     */
    public function canBeImpersonated()
    {
        // Prevent admins from impersonating other admins
        return !$this->hasRole('admin'); 
    }

    public function scopeCustomers($query)
    {
        return $query->role('user');
    }
    public function scopeAdmin($query)
    {
        return $query->where('role', 'admin');
    }
    
    /**
     * Check if the user is an Administrator
     */
    public function isAdmin(): bool
    {
        // This compares the user's role_id to the ID defined in your config
        return $this->role_id === config('roles.admin.id');
    }

    /**
     * Check if the user is a standard User
     */
    public function isUser(): bool
    {
        return $this->role_id === config('roles.user.id');
    }

    /**
     * Get the user's primary active subscription to display.
     * Automatically prioritizes yearly plans and cleans up expired ones.
     */
    public function getCurrentSubscription()
    {
        $subscriptions = \App\Models\UserSubscription::with('plan')
            ->where('user_id', $this->id)
            ->whereIn('status', ['active', 'canceled'])
            ->get();

        $currentSubscription = null;

        foreach ($subscriptions as $sub) {
            // 1. Auto-clean expired/exhausted plans in the background
            if (!$sub->isValid()) {
                if ($sub->status === 'active') {
                    $sub->update([
                        'status' => $sub->expires_at && now()->greaterThan($sub->expires_at) 
                                    ? 'expired' 
                                    : 'exhausted'
                    ]);
                }
                continue; // Skip invalid plans
            }

            // 2. Find the best plan to show in the UI
            // Prioritizes 'recurring_yearly'. If they don't have one, it grabs their valid one-time pack.
            if (!$currentSubscription || $sub->plan->type === 'recurring_yearly') {
                $currentSubscription = $sub;
            }
        }

        return $currentSubscription;
    }
    
    /**
     * Check if the user has an active subscription or remaining cases to create a new dispute.
     */
    public function canCreateCase(): bool
    {
        $status = $this->getCaseStatus();

        return $status->has_unlimited || $status->total_remaining > 0;
    }

    /**
     * Get the aggregated case status across all active subscriptions.
     */
    public function getCaseStatus(): object
    {
        // 1. Fetch BOTH active and canceled subscriptions
        // We exclude 'expired' or 'failed' records assuming those shouldn't grant cases.
        $activeSubscriptions = \App\Models\UserSubscription::with('plan')
            ->where('user_id', $this->id)
            ->whereIn('status', ['active', 'canceled']) 
            ->get();

        $hasUnlimited = false;
        $totalCasesAllowed = 0;
        $totalCasesUsed = 0;

        foreach ($activeSubscriptions as $sub) {
            
            // 2. Logic for Unlimited / Yearly Plans
            if ($sub->plan->type === 'recurring_yearly') {
                // A yearly plan only grants unlimited if it is strictly 'active'
                // OR if it was 'canceled' but the expiration date hasn't passed yet.
                $isStillValidYearly = $sub->status === 'active' || 
                                     ($sub->status === 'canceled' && $sub->expires_at && now()->lessThan($sub->expires_at));
                
                if ($isStillValidYearly) {
                    $hasUnlimited = true;
                }
            } 
            
            // 3. Logic for One-Time Packs (Stackable Consumables)
            else {
                // For one-time packs, the user keeps the cases they paid for regardless
                // of whether the "subscription" status says active or canceled. 
                // As long as they haven't used them all, they count towards the total.
                $totalCasesAllowed += $sub->cases_allowed;
                $totalCasesUsed += $sub->cases_used;
            }
        }

        // Return as an object so it's clean to use in Blade
        return (object) [
            'has_unlimited' => $hasUnlimited,
            'total_remaining' => max(0, $totalCasesAllowed - $totalCasesUsed),
            'total_allowed' => $totalCasesAllowed,
        ];
    }
    
}
