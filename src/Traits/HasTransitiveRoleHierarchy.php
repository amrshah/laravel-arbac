<?php

namespace Amrshah\Arbac\Traits;

/**
 * Transitive Role Hierarchy Trait
 * 
 * Provides transitive role hierarchy checking, allowing deep
 * hierarchy traversal (e.g., super_admin > admin > manager > member).
 * 
 * Usage:
 * class User extends Authenticatable
 * {
 *     use HasTransitiveRoleHierarchy;
 * }
 * 
 * Then use: $user->hasRoleOrHigherTransitive('member');
 */
trait HasTransitiveRoleHierarchy
{
    /**
     * Check if user has role or higher (transitive)
     * 
     * This method walks the full role hierarchy tree to determine
     * if the user has the specified role or any role higher in the hierarchy.
     * 
     * @param string $role Role to check
     * @param int $maxDepth Maximum recursion depth (prevents infinite loops)
     * @return bool
     */
    public function hasRoleOrHigherTransitive(string $role, int $maxDepth = 10): bool
    {
        return $this->hasRoleOrHigherRecursive($role, [], 0, $maxDepth);
    }

    /**
     * Recursive helper for transitive role checking
     * 
     * @param string $targetRole Target role to find
     * @param array $visited Roles already visited (cycle detection)
     * @param int $depth Current recursion depth
     * @param int $maxDepth Maximum allowed depth
     * @return bool
     */
    protected function hasRoleOrHigherRecursive(
        string $targetRole, 
        array $visited, 
        int $depth,
        int $maxDepth
    ): bool {
        // Prevent infinite recursion
        if ($depth >= $maxDepth) {
            return false;
        }

        $hierarchy = config('arbac.role_hierarchy', []);
        
        foreach ($this->roles as $userRole) {
            $roleName = $userRole->name;
            
            // Direct match
            if ($roleName === $targetRole) {
                return true;
            }
            
            // Prevent cycles
            if (in_array($roleName, $visited)) {
                continue;
            }
            
            $visited[] = $roleName;
            $subordinates = $hierarchy[$roleName] ?? [];
            
            // Check direct subordinates
            if (in_array($targetRole, $subordinates)) {
                return true;
            }
            
            // Check transitive subordinates (recursive)
            foreach ($subordinates as $subordinate) {
                // Create temporary user with subordinate role for recursion
                $tempUser = new static();
                $tempUser->setRelation('roles', collect([
                    (object)['name' => $subordinate]
                ]));
                
                if ($tempUser->hasRoleOrHigherRecursive($targetRole, $visited, $depth + 1, $maxDepth)) {
                    return true;
                }
            }
        }
        
        return false;
    }
}
