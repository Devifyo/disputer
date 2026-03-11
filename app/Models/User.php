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
        'status'
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
     * Check if the user has an active subscription or remaining cases to create a new dispute.
     */
    public function canCreateCase(): bool
    {
        $sub = \App\Models\UserSubscription::with('plan')
            ->where('user_id', $this->id)
            ->where('status', 'active')
            ->latest()
            ->first();

        if (!$sub) {
            return false;
        }

        // Yearly plans have unlimited cases
        if ($sub->plan->type === 'recurring_yearly') {
            return true;
        }

        // One-time plans must have cases remaining
        return $sub->cases_used < $sub->cases_allowed;
    }
    
}
