# Laravel ARBAC – Advanced Role & Attribute-Based Access Control

[![Tests](https://github.com/amrshah/arbac/actions/workflows/run-tests.yml/badge.svg)](https://github.com/amrshah/arbac/actions)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/amrshah/laravel-arbac.svg?style=flat-square)](https://packagist.org/packages/amrshah/laravel-arbac)
[![Total Downloads](https://img.shields.io/packagist/dt/amrshah/laravel-arbac.svg?style=flat-square)](https://packagist.org/packages/amrshah/laravel-arbac)

> **ARBAC** is a production-ready Laravel package that combines classic Role-Based Access Control (RBAC) with Attribute-Based Access Control (ABAC) and enterprise features — while remaining simple enough for small projects.

---

## ✨ Features

- ✅ **RBAC** - Traditional role-based permissions (built on [spatie/laravel-permission](https://github.com/spatie/laravel-permission))
- ✅ **ABAC** - Attribute-based access control with custom rules
- ✅ **Multi-Tenancy** - Tenant-aware permissions out of the box
- ✅ **High Performance** - Built-in caching for permission checks
- ✅ **Audit Logging** - Track all permission checks for compliance
- ✅ **Middleware** - Protect routes with ease
- ✅ **Blade Directives** - Control view rendering
- ✅ **Time-Based Permissions** - Restrict access by time/date
- ✅ **IP-Based Permissions** - Whitelist/blacklist IPs
- ✅ **Hierarchical Roles** - Role inheritance support
- ✅ **Permission Groups** - Bulk assign permissions
- ✅ **Laravel 10 & 11** - Full support for modern Laravel
- ✅ **PHP 8.1+** - Modern PHP features

---

## 📦 Installation

```bash
composer require amrshah/laravel-arbac
```

### Local Development

If you're developing locally with a path repository:

```json
// composer.json
"repositories": [
  {
    "type": "path",
    "url": "packages/amrshah/arbac"
  }
]
```

Then:

```bash
composer require amrshah/laravel-arbac:dev-main
```

### Configuration

Publish the config and migrations:

```bash
php artisan vendor:publish --tag="arbac-config"
php artisan vendor:publish --tag="arbac-migrations"
php artisan migrate
```

This creates `config/arbac.php` where you can configure models, cache, multi-tenancy, audit logging, and more.

---

## 🚀 Quick Start

### Basic RBAC

```php
use Amrshah\Arbac\Facades\Arbac;

// Assign role to user
Arbac::assignRole($user, 'editor');

// Check permission
if (Arbac::check($user, 'edit posts')) {
    // User can edit posts
}

// Remove role
Arbac::removeRole($user, 'editor');
```

### Attribute-Based Rules

Create custom rules for complex authorization logic:

```bash
php artisan arbac:make-rule PostOwnerRule
```

This creates a rule class in `app/Arbac/Rules/PostOwnerRule.php`:

```php
namespace App\Arbac\Rules;

use Amrshah\Arbac\Contracts\AttributeRuleInterface;
use Illuminate\Contracts\Auth\Authenticatable;

class PostOwnerRule implements AttributeRuleInterface
{
    public function supports(string $permission): bool
    {
        return $permission === 'edit post';
    }

    public function check(Authenticatable $user, string $permission, array $context = []): bool
    {
        $post = $context['post'] ?? null;
        return $post && $post->user_id === $user->id;
    }
}
```

Register in `config/arbac.php`:

```php
'attribute_rules' => [
    \App\Arbac\Rules\PostOwnerRule::class,
],
```

Use it:

```php
// User can edit their own posts even without explicit permission
Arbac::check($user, 'edit post', ['post' => $post]);
```

---

## ⚠️ Important: Cache Invalidation

ARBAC caches permission checks for performance. **Cache is automatically invalidated** when roles or permissions change:

```php
// Automatic invalidation (enabled by default)
$user->assignRole('editor');        // ✅ Cache invalidated
$role->givePermissionTo('edit');    // ✅ Cache invalidated
$permission->update([...]);         // ✅ Cache invalidated
```

**Disable automatic invalidation:**
```php
// config/arbac.php
'cache' => [
    'auto_invalidate' => false, // Manual invalidation only
],
```

**⚠️ Warning:** Direct database updates bypass observers and require manual cache flush:
```php
// Direct DB update - cache NOT invalidated
DB::table('model_has_roles')->insert([...]);

// Manual flush required
Arbac::flushUserPermissions($user);
// or
Arbac::flushAllCache();
```

**Optional: Add to User Model**
```php
use Amrshah\Arbac\Traits\InvalidatesArbacCache;

class User extends Authenticatable
{
    use InvalidatesArbacCache; // Auto-invalidate on user changes
}
```

---

## 🛡️ Middleware

### Basic Middleware

Protect routes with ARBAC middleware:

```php
// routes/web.php
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('arbac:edit post');

Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');
```

### 🌐 IP-Restricted Routes

Protect routes by IP address:

```php
// config/arbac.php
'ip_whitelist' => ['192.168.1.0/24', '10.0.0.1'],

// routes/web.php
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('arbac.ip:ip-restricted.admin');

// Custom IP list
Route::get('/secure', [SecureController::class, 'index'])
    ->middleware('arbac.ip:ip-restricted.secure,custom.secure_ips');
```

**Environment Configuration:**
```env
ARBAC_IP_WHITELIST=192.168.1.0/24,10.0.0.1
```

### ⏰ Time-Restricted Routes

Restrict access by time window:

```php
// config/arbac.php
'time_window' => [
    'start_time' => '09:00',
    'end_time' => '17:00',
    'timezone' => 'America/New_York',
],

// routes/web.php
Route::get('/business-hours', [Controller::class, 'index'])
    ->middleware('arbac.time:time-restricted.access');

// Custom time window
Route::get('/special', [Controller::class, 'index'])
    ->middleware('arbac.time:time-restricted.special,custom.special_hours');
```

**Environment Configuration:**
```env
ARBAC_TIME_START=09:00
ARBAC_TIME_END=17:00
ARBAC_TIMEZONE=America/New_York
```

### 🔧 Context-Aware Middleware

Pass context from config or request:

```php
// With config-based context
Route::post('/api/resource', [ApiController::class, 'store'])
    ->middleware('arbac.context:create resource,api.resource_context');

// Context from request (default)
Route::post('/posts', [PostController::class, 'store'])
    ->middleware('arbac.context:create post');
```

---

## 🎨 Blade Directives

Control what users see in your views:

```blade
@arbac('edit post', ['post' => $post])
    <button>Edit Post</button>
@endarbac

@hasrole('admin')
    <a href="/admin">Admin Panel</a>
@endhasrole

@haspermission('users.create')
    <a href="/users/create">Create User</a>
@endhaspermission

@unlessrole('guest')
    <p>Welcome back!</p>
@endunlessrole
```

---

## 🏢 Multi-Tenancy

### What ARBAC Provides

ARBAC is **tenant-aware**, not a full tenant isolation framework:

✅ **What ARBAC Does:**
- Automatically adds `tenant_id` to permission checks
- Provides `scopeTenant()` for queries
- Caches are tenant-scoped
- Audit logs are tenant-scoped

❌ **What ARBAC Does NOT Do:**
- Enforce tenant isolation at database level
- Prevent cross-tenant data access
- Manage user-tenant relationships

### Configuration

Enable in `config/arbac.php`:

```php
'multi_tenancy' => [
    'enabled' => true,
    'bypass_roles' => ['super_admin'], // Roles that bypass tenant checks
],
```

### Usage

Permissions are automatically scoped to the current tenant:

```php
tenancy()->initialize($tenant);

$user->givePermissionTo('edit posts'); // Scoped to current tenant

Arbac::check($user, 'edit posts'); // Checks within tenant context
```

### Super Admin Bypass

**✅ SAFE - Role-based bypass:**
```php
// config/arbac.php
'multi_tenancy' => [
    'bypass_roles' => ['super_admin', 'global_admin'],
],

// Super admins automatically bypass tenant checks
$superAdmin->assignRole('super_admin');
Arbac::check($superAdmin, 'edit posts'); // No tenant_id required
```

**❌ DANGEROUS - Do NOT toggle config per request:**
```php
// ❌ DON'T DO THIS (leaks in Octane/Swoole)
if ($user->hasRole('super_admin')) {
    config(['arbac.multi_tenancy.enabled' => false]);
}
```

### Your Responsibilities

1. **Add `tenant_id` to your models**
2. **Use `scopeTenant()` in queries**
3. **Implement tenant switching logic**
4. **Enforce isolation at application level**

### Best Practices

```php
// ✅ GOOD - Explicit tenant scoping
$posts = Post::tenant()->where('user_id', $user->id)->get();

// ❌ BAD - No tenant scope
$posts = Post::where('user_id', $user->id)->get(); // May leak across tenants
```

---

## ⚡ Caching

ARBAC caches permission checks for optimal performance:

```php
// config/arbac.php
'cache' => [
    'enabled' => true,
    'auto_invalidate' => true, // Automatic cache invalidation (recommended)
    'store'   => 'redis', // or 'default'
    'ttl'     => 3600,    // seconds
],
```

**Automatic Invalidation (Enabled by Default):**
Cache is automatically cleared when roles/permissions change. See the [Cache Invalidation](#️-important-cache-invalidation) section above for details.

**Manual Cache Management:**
```php
$manager = app(ArbacManager::class);
$manager->flushUserPermissions($user);
$manager->flushAllCache();
```

---

## 📊 Audit Logging

Track all permission checks for compliance and debugging:

```php
// config/arbac.php
'audit' => [
    'enabled'     => true,
    'log_granted' => true,
    'log_denied'  => true,
],
```

### ⚠️ Performance Impact

**Warning:** Audit logging adds **10-20ms per permission check** (synchronous write).

**For high-traffic applications:**

1. **Log denied only:**
```php
'audit' => [
    'log_granted' => false,
    'log_denied' => true,
],
```

2. **Use async logging (custom implementation):**
```php
// Extend ArbacManager
protected function logPermissionCheck(...$args): void
{
    dispatch(new LogPermissionCheck(...$args));
}
```

### Query Audit Logs

```php
use Amrshah\Arbac\Models\ArbacAuditLog;

// Get all denied access attempts
$deniedLogs = ArbacAuditLog::denied()->get();

// Get logs for specific user
$userLogs = ArbacAuditLog::forUser($user)->get();

// Get logs for specific permission
$permissionLogs = ArbacAuditLog::forPermission('edit posts')->get();

// Tenant-scoped logs
$tenantLogs = ArbacAuditLog::tenant()->get();
```

---

## ⏰ Time-Based Permissions

Restrict access by time windows:

```php
use Amrshah\Arbac\Rules\TimeBasedRule;

// Register in config
'attribute_rules' => [
    \Amrshah\Arbac\Rules\TimeBasedRule::class,
],

// Check permission with time constraints
Arbac::check($user, 'time-restricted.access', [
    'start_time' => '09:00',
    'end_time'   => '17:00',
    'timezone'   => 'America/New_York',
]);
```

---

## 🌐 IP-Based Permissions

Whitelist IPs for sensitive operations:

```php
use Amrshah\Arbac\Rules\IpWhitelistRule;

// Register in config
'attribute_rules' => [
    \Amrshah\Arbac\Rules\IpWhitelistRule::class,
],

// Check permission with IP whitelist
Arbac::check($user, 'ip-restricted.admin', [
    'allowed_ips' => ['192.168.1.100', '10.0.0.0/24'], // Supports CIDR
]);
```

---

## 🔄 Hierarchical Roles

### Non-Transitive (Default)

Define role inheritance in `config/arbac.php`:

```php
'role_hierarchy' => [
    'admin' => ['manager', 'member'],
    'manager' => ['member'],
],
```

Use in your User model:

```php
use Amrshah\Arbac\Traits\HasRoleHierarchy;

class User extends Authenticatable
{
    use HasRoleHierarchy;
}

// Check if user has role or higher
if ($user->hasRoleOrHigher('manager')) {
    // User is manager or admin
}
```

**⚠️ Important:** `hasRoleOrHigher()` is **one-level only**, not transitive.

### Transitive (Optional)

For deep hierarchies, use the `HasTransitiveRoleHierarchy` trait:

```php
use Amrshah\Arbac\Traits\HasTransitiveRoleHierarchy;

class User extends Authenticatable
{
    use HasTransitiveRoleHierarchy;
}

// Config
'role_hierarchy' => [
    'super_admin' => ['admin'],
    'admin' => ['manager'],
    'manager' => ['member'],
],

// Now supports transitive checks
$user->hasRoleOrHigherTransitive('member'); // Walks full hierarchy tree
```

---

## 📦 Permission Groups

Bulk manage permissions:

```php
use Amrshah\Arbac\Models\PermissionGroup;

$adminGroup = PermissionGroup::create([
    'name'        => 'admin_permissions',
    'description' => 'All admin permissions',
    'permissions' => ['users.*', 'roles.*', 'permissions.*'],
]);

// Assign all permissions to a role
$adminGroup->assignToRole($adminRole);

// Remove all permissions from a role
$adminGroup->removeFromRole($adminRole);
```

---

## 🧪 Testing

```bash
composer test
composer test:coverage
```

---

## 📚 Documentation

- [Installation Guide](docs/installation.md)
- [Configuration](docs/configuration.md)
- [Creating Rules](docs/creating-rules.md)
- [Multi-Tenancy](docs/multi-tenancy.md)
- [Performance](docs/performance.md)
- [API Reference](docs/api-reference.md)

---

## 🤝 Contributing

Contributions are welcome! Please see [CONTRIBUTING.md](CONTRIBUTING.md) for details.

---

## 🔒 Security

If you discover any security-related issues, please email amrshah@gmail.com instead of using the issue tracker.

---

## 📄 License

MIT © Ali Raza (Amr Shah)

---

## 🙏 Credits

- Built on top of [spatie/laravel-permission](https://github.com/spatie/laravel-permission)
- Inspired by enterprise authorization systems
- Made with ❤️ for the Laravel community
