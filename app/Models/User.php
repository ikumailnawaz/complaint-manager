<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'phone_whatsapp',
        'base_city',
        'current_city',
        'home_coordinates',
        'home_address',
        'specialization',
        'is_available',
        'is_on_leave',
        'fcm_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'is_available' => 'boolean',
        'is_on_leave' => 'boolean',
    ];

    public function isSuperAdmin(): bool
    {
        return $this->role === 'super_admin';
    }

    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isSuperior(): bool
    {
        return in_array($this->role, [
            'superior', 'admin', 'super_admin',
            'manager', 'operations_manager', 'operation_manager',
            'ops', 'supervisor', 'operations_admin', 'staff', 'office_staff',
        ]);
    }

    public function isOfficeStaff(): bool
    {
        return in_array($this->role, ['office_staff', 'staff']);
    }

    public function isOperationsManager(): bool
    {
        return $this->isSuperior();
    }

    /** Only managers/admins may reopen a resolved or closed ticket (never engineers or office staff). */
    public function canReopenTickets(): bool
    {
        return in_array($this->role, [
            'super_admin', 'admin', 'manager', 'operations_manager', 'operation_manager',
            'operations_admin', 'ops', 'supervisor', 'superior',
        ], true);
    }

    public function isEngineer(): bool
    {
        return $this->role === 'engineer';
    }

    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assigned_engineer_id');
    }

    public function feedbacks(): HasMany
    {
        return $this->hasMany(TicketFeedback::class, 'engineer_id');
    }

    public function expenseClaims(): HasMany
    {
        return $this->hasMany(ExpenseClaim::class, 'engineer_id');
    }

    public function engineerInventories(): HasMany
    {
        return $this->hasMany(EngineerInventory::class, 'engineer_id');
    }

    public function advanceTransactions(): HasMany
    {
        return $this->hasMany(EngineerAdvanceTransaction::class, 'engineer_id');
    }
}
