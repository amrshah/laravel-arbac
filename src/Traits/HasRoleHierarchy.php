<?php

namespace Amrshah\Arbac\Traits;

trait HasRoleHierarchy
{
    /**
     * Check if user has a role or a higher role in the hierarchy
     */
    public function hasRoleOrHigher(string $role): bool
    {
        $hierarchy = config('arbac.role_hierarchy', []);
        
        foreach ($this->roles as $userRole) {
            // Direct match
            if ($userRole->name === $role) {
                return true;
            }
            
            // Check if user's role is higher in hierarchy
            $subordinates = $hierarchy[$userRole->name] ?? [];
            if (in_array($role, $subordinates)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get all roles including inherited ones from hierarchy
     */
    public function getAllRolesWithHierarchy(): array
    {
        $hierarchy = config('arbac.role_hierarchy', []);
        $allRoles = [];
        
        foreach ($this->roles as $userRole) {
            $allRoles[] = $userRole->name;
            
            // Add subordinate roles
            $subordinates = $hierarchy[$userRole->name] ?? [];
            $allRoles = array_merge($allRoles, $subordinates);
        }
        
        return array_unique($allRoles);
    }
}
