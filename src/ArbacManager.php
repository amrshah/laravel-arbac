<?php
namespace Amrshah\Arbac;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Amrshah\Arbac\Contracts\AttributeRuleInterface;

class ArbacManager
{

    /**
     * @var AttributeRuleInterface[]
     */
    protected array $attributeRuleInstances = [];

    public static function hello()
    {
        return "Hello from ARbac package!";
    }

    public static function assignRole($user, $role)
    {
        return $user->assignRole($role);
    }

    public static function removeRole($user, $role)
    {
        return $user->removeRole($role);
    }

        /**
     * Check if a user has a permission (RBAC + ABAC).
     *
     * Behavior:
     *  - If RBAC (spatie) allows => grant.
     *  - Otherwise iterate registered ABAC rules that "support" this permission.
     *    If any rule returns true => grant. Otherwise deny.
     *
     * @param Authenticatable $user
     * @param string $permission
     * @param array $attributes
     * @return bool
     */
    public function check(Authenticatable $user, string $permission, array $attributes = []): bool
    {
        // Fast path: roles/permissions via spatie
        if ($user->can($permission)) {
            return true;
        }

        // Evaluate attribute rules (ABAC)
        foreach ($this->attributeRuleInstances as $rule) {
            if ($rule->supports($permission)) {
                if ($rule->check($user, $permission, $attributes)) {
                    return true;
                }
            }
        }

        // default deny
        return false;
    }

    /**
     * Register an attribute rule class or instance.
     *
     * Accepts FQCN or an instantiated rule.
     */
    public function registerAttributeRule(string|AttributeRuleInterface $rule): void
    {
        $instance = null;

        if (is_string($rule)) {
            $instance = app($rule);
        } else {
            $instance = $rule;
        }

        if (! $instance instanceof AttributeRuleInterface) {
            throw new \InvalidArgumentException('Attribute rule must implement AttributeRuleInterface');
        }

        $this->attributeRuleInstances[] = $instance;
    }

    /**
     * Load attribute rules from config('arbac.attribute_rules').
     */
    public function loadAttributeRulesFromConfig(): void
    {
        $list = config('arbac.attribute_rules', []);
        foreach ($list as $class) {
            // skip invalid entries safely
            if (! is_string($class) || $class === '') {
                continue;
            }
            $this->registerAttributeRule($class);
        }
    }


    protected static function checkAttributes($user, string $permission, array $attributes)
    {
        // ABAC logic placeholder
        return false;
    }   

    /**
     * Sync permissions for a given role.
     */
    public function syncPermissions(Role|string $role, array $permissions): void
    {
        if (is_string($role)) {
            $role = Role::findByName($role);
        }

        $role->syncPermissions($permissions);
    }

    /**
     * Check if a user has a permission (with optional ABAC attributes).
     * For now it only checks RBAC. Later you’ll plug ABAC logic here.
     */
   

    /**
     * Convenience: create a permission if it doesn’t exist yet.
     */
    public function ensurePermissionExists(string $permission): Permission
    {
        return Permission::findOrCreate($permission);
    }

}
