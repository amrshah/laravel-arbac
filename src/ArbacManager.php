<?php
namespace Amrshah\Arbac;

use Spatie\Permission\Models\Role;
use Spatie\Permission\Models\Permission;
use Illuminate\Contracts\Auth\Authenticatable;
use Amrshah\Arbac\Contracts\AttributeRuleInterface;
use Amrshah\Arbac\Traits\HasCache;
use Amrshah\Arbac\Traits\TenantAware;
use Amrshah\Arbac\Models\ArbacAuditLog;

class ArbacManager
{
    use HasCache, TenantAware;

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
     * Determine if tenant check should be bypassed for user
     *
     * @param Authenticatable $user
     * @return bool
     */
    public function shouldBypassTenantFor(Authenticatable $user): bool
    {
        $bypassRoles = config('arbac.multi_tenancy.bypass_roles', ['super_admin']);
        
        foreach ($bypassRoles as $role) {
            if ($user->hasRole($role)) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Check if a user has a permission (RBAC + ABAC).
     *
     * Behavior:
     *  - Check cache first (if enabled)
     *  - If RBAC (spatie) allows => grant.
     *  - Otherwise iterate registered ABAC rules that "support" this permission.
     *    If any rule returns true => grant. Otherwise deny.
     *  - Log the result (if audit enabled)
     *  - Cache the result (if caching enabled)
     *
     * @param Authenticatable $user
     * @param string $permission
     * @param array $attributes
     * @return bool
     */
    public function check(Authenticatable $user, string $permission, array $context = []): bool
    {
        // Add tenant context automatically if multi-tenancy is enabled
        // UNLESS user should bypass tenant checks
        if ($this->isMultiTenancyEnabled() 
            && !$this->shouldBypassTenantFor($user)
            && function_exists('tenant') 
            && tenant()) {
            $context['tenant_id'] = tenant('id');
        }

        // Check cache first
        $cached = $this->getCachedPermissionCheck($user, $permission);
        if ($cached !== null) {
            $this->logPermissionCheck($user, $permission, $cached, 'cache', $context);
            return $cached;
        }

        $granted = false;
        $method = 'denied';

        // Find applicable rules
        $applicableRules = [];
        foreach ($this->attributeRuleInstances as $rule) {
            if ($rule->supports($permission)) {
                $applicableRules[] = $rule;
            }
        }

        if (empty($applicableRules)) {
            // No rules -> Pure RBAC
            if ($user->can($permission)) {
                $granted = true;
                $method = 'rbac';
            }
        } else {
            // Rules exist -> OR logic
            foreach ($applicableRules as $rule) {
                if ($rule->check($user, $permission, $context)) {
                    $granted = true;
                    $method = get_class($rule);
                    break;
                }
            }
        }

        // Cache the result
        $this->cachePermissionCheck($user, $permission, $granted);

        // Log the permission check
        $this->logPermissionCheck($user, $permission, $granted, $method, $context);

        return $granted;
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


    /**
     * Log permission check for audit trail
     */
    protected function logPermissionCheck($user, string $permission, bool $granted, string $method, array $context = []): void
    {
        if (!config('arbac.audit.enabled', false)) {
            return;
        }

        // Skip logging granted permissions if configured
        if ($granted && !config('arbac.audit.log_granted', true)) {
            return;
        }

        // Skip logging denied permissions if configured
        if (!$granted && !config('arbac.audit.log_denied', true)) {
            return;
        }

        try {
            ArbacAuditLog::create([
                'tenant_id' => $this->getCurrentTenantId(),
                'user_id' => $user->getAuthIdentifier(),
                'permission' => $permission,
                'action' => $granted ? 'granted' : 'denied',
                'method' => $method,
                'context' => $context,
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);
        } catch (\Exception $e) {
            // Silently fail to not break the application
            if (config('app.debug')) {
                logger()->error('ARBAC audit log failed: ' . $e->getMessage());
            }
        }
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
