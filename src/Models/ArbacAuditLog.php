<?php

namespace Amrshah\Arbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ArbacAuditLog extends Model
{
    protected $fillable = [
        'external_id',
        'tenant_id',
        'user_id',
        'permission',
        'action',
        'method',
        'context',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'context' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->external_id)) {
                $model->external_id = 'AUD_' . self::generateNanoId();
            }
        });
    }

    /**
     * Generate a simple unique ID (fallback if nanoid not available)
     */
    protected static function generateNanoId(): string
    {
        if (class_exists('\Hidehalo\Nanoid\Client')) {
            return \Hidehalo\Nanoid\Client::generateId(14);
        }
        
        // Fallback to a simple unique ID
        return strtoupper(substr(uniqid(), -14));
    }

    /**
     * Get the user that performed the action
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(config('arbac.models.user', \App\Models\User::class));
    }

    /**
     * Scope to granted actions
     */
    public function scopeGranted($query)
    {
        return $query->where('action', 'granted');
    }

    /**
     * Scope to denied actions
     */
    public function scopeDenied($query)
    {
        return $query->where('action', 'denied');
    }

    /**
     * Scope to specific permission
     */
    public function scopeForPermission($query, string $permission)
    {
        return $query->where('permission', $permission);
    }

    /**
     * Scope to specific user
     */
    public function scopeForUser($query, $user)
    {
        $userId = is_object($user) ? $user->id : $user;
        return $query->where('user_id', $userId);
    }

    /**
     * Scope to current tenant
     */
    public function scopeTenant($query)
    {
        if (function_exists('tenant') && tenant()) {
            return $query->where('tenant_id', tenant('id'));
        }
        
        return $query;
    }
}
