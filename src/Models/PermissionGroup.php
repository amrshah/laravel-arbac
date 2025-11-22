<?php

namespace Amrshah\Arbac\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class PermissionGroup extends Model
{
    protected $fillable = [
        'name',
        'description',
        'permissions',
    ];
    
    protected $casts = [
        'permissions' => 'array',
    ];

    /**
     * Assign all permissions in this group to a role
     */
    public function assignToRole($role): void
    {
        foreach ($this->permissions as $permission) {
            $role->givePermissionTo($permission);
        }
    }

    /**
     * Remove all permissions in this group from a role
     */
    public function removeFromRole($role): void
    {
        foreach ($this->permissions as $permission) {
            $role->revokePermissionTo($permission);
        }
    }

    /**
     * Get all permissions as Permission models
     */
    public function getPermissionModels()
    {
        $permissionClass = config('arbac.models.permission', \Spatie\Permission\Models\Permission::class);
        
        return $permissionClass::whereIn('name', $this->permissions)->get();
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
