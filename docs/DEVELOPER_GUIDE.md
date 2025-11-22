# Laravel ARBAC v2.0 - Developer Guide

**Technical documentation for developers implementing and extending ARBAC**

---

## Table of Contents

1. [Multi-Tenancy](#1-multi-tenancy)
2. [Caching](#2-caching)
3. [Audit Logging](#3-audit-logging)
4. [Middleware](#4-middleware)
5. [Blade Directives](#5-blade-directives)
6. [Advanced Rules](#6-advanced-rules)
7. [Role Hierarchy](#7-role-hierarchy)
8. [Permission Groups](#8-permission-groups)
9. [Performance & Indexing](#9-performance--indexing)
10. [Upgrade & Compatibility](#10-upgrade--compatibility)

---

## 1. Multi-Tenancy

### How Tenant is Determined

ARBAC uses a **function-based detection** strategy that integrates with popular multi-tenancy packages:

```php
// TenantAware trait
public function getCurrentTenantId(): ?string
{
    if (function_exists('tenant') && tenant()) {
        return tenant('id');
    }
    
    return null;
}
```

**Supported Strategies:**
- **Stancl/Tenancy** - Uses `tenant()` helper function
- **Custom Implementation** - Define your own `tenant()` function
- **Disabled** - Set `multi_tenancy.enabled => false` in config

### Opt-In vs Default

Multi-tenancy is **opt-in** and disabled by default:

```php
// config/arbac.php
'multi_tenancy' => [
    'enabled' => env('ARBAC_MULTI_TENANCY_ENABLED', false),
],
```

### What TenantAware Trait Does

The `TenantAware` trait provides:

1. **Scope Method** - Query scope for tenant filtering:
```php
public function scopeTenant($query)
{
    if (function_exists('tenant') && tenant()) {
        return $query->where('tenant_id', tenant('id'));
    }
    
    return $query;
}
```

2. **Helper Methods** - Tenant detection utilities
3. **NO Global Scopes** - Does not automatically add global scopes to avoid conflicts

### Database Requirements

**Required Fields:**
```php
// In migrations
$table->unsignedBigInteger('tenant_id')->nullable();
$table->index(['tenant_id', 'user_id']); // Composite index
```

**Tables with tenant_id:**
- `arbac_audit_logs` - Tenant-scoped audit logs
- `permission_groups` - Tenant-specific permission groups

**Note:** Spatie's `roles` and `permissions` tables are NOT automatically tenant-scoped. You must handle this separately if needed.

### Handling Special Cases

#### Tenant-Agnostic Data (Global Roles/Permissions)

Use `tenant_id = NULL` for global data:

```php
// Global permission (available to all tenants)
Permission::create([
    'name' => 'global.admin',
    'tenant_id' => null,
]);

// Tenant-specific permission
Permission::create([
    'name' => 'edit.posts',
    'tenant_id' => tenant('id'),
]);
```

#### Users Belonging to Multiple Tenants

ARBAC doesn't manage user-tenant relationships. Implement this in your application:

```php
// Your User model
public function tenants()
{
    return $this->belongsToMany(Tenant::class);
}

// Switch tenant context
tenancy()->initialize($tenant);
```

#### Super Admin Bypass

Disable tenant checking for super admins:

```php
// In your application
if ($user->hasRole('super_admin')) {
    // Bypass tenant checks
    config(['arbac.multi_tenancy.enabled' => false]);
}
```

### Switching Tenant Context

#### Web Requests
```php
// Middleware or controller
tenancy()->initialize($tenant);
```

#### Jobs/Queues
```php
class ProcessData implements ShouldQueue
{
    public function __construct(public $tenantId) {}
    
    public function handle()
    {
        tenancy()->initialize($this->tenantId);
        // Your logic
    }
}
```

#### CLI/Artisan Commands
```php
class MyCommand extends Command
{
    public function handle()
    {
        $tenant = Tenant::find($this->argument('tenant'));
        tenancy()->initialize($tenant);
        // Your logic
    }
}
```

### Common Gotchas

**1. Seeding Per Tenant**
```php
// Wrong - seeds to current tenant only
Permission::create(['name' => 'edit.posts']);

// Right - seed for each tenant
Tenant::all()->each(function ($tenant) {
    tenancy()->initialize($tenant);
    Permission::create(['name' => 'edit.posts']);
});
```

**2. Cache Keys Include Tenant**
```php
// Cache keys are automatically tenant-scoped
"arbac:{tenant_id}:permission:{user_id}:{permission}"
```

**3. Audit Logs Are Tenant-Scoped**
```php
// Automatically includes tenant_id
ArbacAuditLog::tenant()->get(); // Only current tenant's logs
```

---

## 2. Caching

### Enabling/Disabling

```php
// config/arbac.php
'cache' => [
    'enabled' => env('ARBAC_CACHE_ENABLED', true),
    'store'   => env('ARBAC_CACHE_STORE', 'default'),
    'ttl'     => env('ARBAC_CACHE_TTL', 3600), // seconds
],
```

### Recommended Cache Store

**Production:** Use **Redis** for best performance and features:
```env
ARBAC_CACHE_STORE=redis
```

**Why Redis:**
- Supports cache tags (for bulk invalidation)
- Fast in-memory storage
- Distributed caching support
- TTL management

**Development:** `array` or `file` driver is fine.

### Cache Key Structure

```php
// Format
"arbac:{tenant_id}:{type}:{identifier}"

// Examples
"arbac:global:permission:123:edit posts"
"arbac:tenant_5:permission:456:delete posts"
```

**Scoping:**
- User ID
- Permission name
- Tenant ID (if multi-tenancy enabled)

### Automatic Invalidation

Cache is **NOT automatically invalidated** by ARBAC. You must manually invalidate when:

1. **Roles/Permissions Change**
```php
// After updating user roles
$manager = app(ArbacManager::class);
$manager->flushUserPermissions($user);
```

2. **Permission Updates**
```php
// After modifying permissions
$manager->flushAllCache();
```

3. **Tenant Context Changes**
```php
// Cache keys include tenant, so switching tenant naturally separates cache
```

### Manual Invalidation

```php
use Amrshah\Arbac\ArbacManager;

$manager = app(ArbacManager::class);

// Flush specific user
$manager->flushUserPermissions($user);

// Flush all ARBAC cache
$manager->flushAllCache();
```

**Note:** `flushAllCache()` requires cache tags support (Redis, Memcached).

### Measuring Performance

**Benchmark Example:**
```php
use Illuminate\Support\Benchmark;

$user = User::find(1);

$result = Benchmark::measure([
    'Without Cache' => function() use ($user) {
        config(['arbac.cache.enabled' => false]);
        return Arbac::check($user, 'edit posts');
    },
    'With Cache (First)' => function() use ($user) {
        config(['arbac.cache.enabled' => true]);
        Cache::flush();
        return Arbac::check($user, 'edit posts');
    },
    'With Cache (Cached)' => function() use ($user) {
        return Arbac::check($user, 'edit posts');
    },
], iterations: 1000);

// Expected results:
// Without Cache: ~50-100ms
// With Cache (First): ~50-100ms (cache miss)
// With Cache (Cached): ~5-10ms (10x faster)
```

---

## 3. Audit Logging

### Enabling/Disabling

```php
// config/arbac.php
'audit' => [
    'enabled'     => env('ARBAC_AUDIT_ENABLED', false),
    'log_granted' => env('ARBAC_AUDIT_LOG_GRANTED', true),
    'log_denied'  => env('ARBAC_AUDIT_LOG_DENIED', true),
],
```

**Granular Control:**
- `log_granted` - Log successful permission checks
- `log_denied` - Log failed permission checks

### What Gets Logged

Every call to `ArbacManager::check()` logs:

```php
[
    'external_id'  => 'AUD_abc123...',      // Unique identifier
    'tenant_id'    => 5,                     // Current tenant (or null)
    'user_id'      => 123,                   // User being checked
    'permission'   => 'edit posts',          // Permission name
    'action'       => 'granted',             // 'granted' or 'denied'
    'method'       => 'rbac',                // 'rbac', 'abac', 'cache', or rule class
    'context'      => ['post_id' => 456],    // Additional context
    'ip_address'   => '192.168.1.1',         // Client IP
    'user_agent'   => 'Mozilla/5.0...',      // User agent string
    'created_at'   => '2025-11-22 12:00:00', // Timestamp
]
```

### Logging Behavior

**Synchronous** - Logs are written immediately (blocking).

**Location:** All checks in `ArbacManager::check()` are logged, including:
- Direct facade calls: `Arbac::check()`
- Middleware checks: `arbac:permission`
- Blade directive checks: `@arbac()`

**Error Handling:** Logging failures are caught and logged to Laravel's logger (won't break your app).

### Log Retention Strategy

**Recommended Approach:**

1. **Prune Old Logs** (Laravel Scheduler)
```php
// app/Console/Kernel.php
protected function schedule(Schedule $schedule)
{
    $schedule->call(function () {
        ArbacAuditLog::where('created_at', '<', now()->subDays(90))
            ->delete();
    })->daily();
}
```

2. **Archive to S3/External Storage**
```php
// Monthly archival job
ArbacAuditLog::where('created_at', '<', now()->subMonth())
    ->chunk(1000, function ($logs) {
        Storage::disk('s3')->put(
            'audit-logs/' . now()->format('Y-m') . '.json',
            $logs->toJson()
        );
    });
```

### Privacy/GDPR Considerations

**Anonymize IP Addresses:**
```php
// Extend ArbacAuditLog model
protected static function boot()
{
    parent::boot();
    
    static::creating(function ($log) {
        // Anonymize last octet
        if ($log->ip_address) {
            $parts = explode('.', $log->ip_address);
            $parts[3] = '0';
            $log->ip_address = implode('.', $parts);
        }
    });
}
```

**Truncate User Agent:**
```php
// In config
'audit' => [
    'anonymize_ip' => true,
    'truncate_user_agent' => 100, // characters
],
```

### Querying Logs

**Built-in Scopes:**
```php
use Amrshah\Arbac\Models\ArbacAuditLog;

// Denied access attempts
ArbacAuditLog::denied()->get();

// Granted access
ArbacAuditLog::granted()->get();

// For specific user
ArbacAuditLog::forUser($user)->get();

// For specific permission
ArbacAuditLog::forPermission('edit posts')->get();

// Tenant-scoped
ArbacAuditLog::tenant()->get();

// Combined
ArbacAuditLog::denied()
    ->forUser($user)
    ->where('created_at', '>', now()->subDays(7))
    ->get();
```

**Common Queries:**
```php
// Failed login attempts
ArbacAuditLog::denied()
    ->where('permission', 'like', 'login%')
    ->where('created_at', '>', now()->subHour())
    ->count();

// User activity report
ArbacAuditLog::forUser($user)
    ->selectRaw('DATE(created_at) as date, COUNT(*) as checks')
    ->groupBy('date')
    ->get();
```

### External ID Generation

Uses **Nano ID** if available, falls back to `uniqid()`:

```php
protected static function generateNanoId(): string
{
    if (class_exists('\Hidehalo\Nanoid\Client')) {
        return \Hidehalo\Nanoid\Client::generateId(14);
    }
    
    // Fallback
    return strtoupper(substr(uniqid(), -14));
}
```

**Optional Dependency:**
```bash
composer require hidehalo/nanoid
```

### Database Indexes

```php
// Migration includes these indexes
$table->index(['tenant_id', 'user_id']);
$table->index(['permission', 'action']);
$table->index('created_at');
```

**For High-Volume Logs:** Consider partitioning by date or tenant.

---

## 4. Middleware

### Usage & Signatures

**CheckPermission Middleware:**
```php
// Alias: 'arbac'
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('arbac:edit post');

// With custom guard
Route::put('/posts/{post}', [PostController::class, 'update'])
    ->middleware('arbac:edit post,api');
```

**CheckRole Middleware:**
```php
// Alias: 'role'
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin');

// With custom guard
Route::get('/admin', [AdminController::class, 'index'])
    ->middleware('role:admin,web');
```

### Signature Details

```php
// CheckPermission
public function handle(Request $request, Closure $next, string $permission, string $guard = null): Response

// CheckRole
public function handle(Request $request, Closure $next, string $role, string $guard = null): Response
```

### Relationship with Spatie Middleware

**ARBAC middleware is SEPARATE from Spatie's middleware:**

- Spatie: `permission:edit posts`, `role:admin`
- ARBAC: `arbac:edit post`, `role:admin`

**Naming Conflict:** The `role` alias conflicts with Spatie's. Choose one:

**Option 1:** Use ARBAC's role middleware (recommended)
```php
// ARBAC's role middleware is registered
Route::get('/admin')->middleware('role:admin');
```

**Option 2:** Rename ARBAC's alias
```php
// In ArbacServiceProvider
$router->aliasMiddleware('arbac-role', CheckRole::class);

// Usage
Route::get('/admin')->middleware('arbac-role:admin');
```

### Failure Behavior

**Default:** Returns `403 Forbidden` with message "Unauthorized action."

```php
// CheckPermission
if (!Arbac::check($user, $permission, $context)) {
    abort(403, 'Unauthorized action.');
}
```

**Customization:** Extend the middleware:
```php
namespace App\Http\Middleware;

use Amrshah\Arbac\Http\Middleware\CheckPermission as BaseCheckPermission;

class CheckPermission extends BaseCheckPermission
{
    public function handle(Request $request, Closure $next, string $permission, string $guard = null): Response
    {
        if (!Arbac::check(auth($guard)->user(), $permission, $request->all())) {
            // Custom behavior
            return redirect()->route('unauthorized')
                ->with('error', 'You do not have permission to access this resource.');
        }

        return $next($request);
    }
}
```

### Configuration Options

**No built-in config** for middleware behavior. Customize by:

1. **Extending middleware classes**
2. **Publishing and modifying** the middleware
3. **Using route-level logic**

### Integration with Tenant, Cache, and Logging

**Yes**, middleware uses the same `ArbacManager::check()` method, so:

- ✅ Tenant context is respected
- ✅ Caching is used (if enabled)
- ✅ Audit logging occurs (if enabled)

```php
// CheckPermission middleware
if (!Arbac::check(auth($guard)->user(), $permission, $context)) {
    // This check is cached, logged, and tenant-aware
    abort(403);
}
```

---

## 5. Blade Directives

### Available Directives

```blade
{{-- Check ARBAC permission --}}
@arbac('edit post', ['post' => $post])
    <button>Edit</button>
@endarbac

{{-- Check role --}}
@hasrole('admin')
    <a href="/admin">Admin</a>
@endhasrole

{{-- Check permission (Spatie-style) --}}
@haspermission('users.create')
    <a href="/users/create">Create User</a>
@endhaspermission

{{-- Inverse role check --}}
@unlessrole('guest')
    <p>Welcome back!</p>
@endunlessrole
```

### Arguments Accepted

**@arbac Directive:**
```blade
{{-- Single permission --}}
@arbac('edit post')

{{-- With context (attribute-based) --}}
@arbac('edit post', ['post' => $post])

{{-- Note: Does NOT support arrays of permissions --}}
```

**@hasrole / @haspermission:**
```blade
{{-- Single role/permission only --}}
@hasrole('admin')
@haspermission('edit posts')
```

### Implementation

Directives are **syntactic sugar** around underlying methods:

```php
// @arbac directive
Blade::directive('arbac', function ($expression) {
    return "<?php if(app('arbac')->check(auth()->user(), {$expression})): ?>";
});

// Compiles to:
<?php if(app('arbac')->check(auth()->user(), 'edit post', ['post' => $post])): ?>
```

**Underlying Calls:**
- `@arbac` → `ArbacManager::check()`
- `@hasrole` → `User::hasRole()` (Spatie)
- `@haspermission` → `User::can()` (Spatie)

### Performance Considerations

**Caching Applies:** Directive checks use the same caching as direct calls.

**Loop Usage:**
```blade
{{-- ❌ BAD: N+1 permission checks --}}
@foreach($posts as $post)
    @arbac('edit post', ['post' => $post])
        <button>Edit</button>
    @endarbac
@endforeach

{{-- ✅ BETTER: Pre-filter in controller --}}
{{-- Controller: $editablePosts = $posts->filter(fn($p) => Arbac::check($user, 'edit post', ['post' => $p])) --}}
@foreach($editablePosts as $post)
    <button>Edit</button>
@endforeach

{{-- ✅ BEST: Use policy-based filtering --}}
{{-- Controller: $editablePosts = $posts->filter(fn($p) => $user->can('update', $p)) --}}
```

**Recommendation:** For loops with many items, pre-filter in the controller to avoid N+1 checks.

---

## 6. Advanced Rules

### TimeBasedRule

#### How Time Windows Work

Time windows are passed as **context** in permission checks:

```php
Arbac::check($user, 'time-restricted.access', [
    'start_time' => '09:00',
    'end_time'   => '17:00',
    'timezone'   => 'America/New_York',
]);
```

**Not config-based or database-based** - you provide the rules at check time.

#### Timezone Handling

```php
public function check(Authenticatable $user, string $permission, array $context = []): bool
{
    $startTime = $context['start_time'] ?? '09:00';
    $endTime = $context['end_time'] ?? '17:00';
    $timezone = $context['timezone'] ?? config('app.timezone', 'UTC');

    $now = Carbon::now($timezone);
    $start = Carbon::parse($startTime, $timezone);
    $end = Carbon::parse($endTime, $timezone);

    return $now->between($start, $end);
}
```

**Timezone Priority:**
1. Context `timezone` parameter
2. App timezone (`config('app.timezone')`)
3. UTC (Carbon default)

#### Permission Naming Convention

Must start with `time-restricted.`:

```php
public function supports(string $permission): bool
{
    return str_starts_with($permission, 'time-restricted.');
}
```

**Examples:**
- ✅ `time-restricted.access`
- ✅ `time-restricted.admin.panel`
- ❌ `access.time-restricted` (won't match)

#### DST Changes

**Handled by Carbon** - Uses the timezone's DST rules automatically.

**Edge Case:** If a time window spans a DST transition, Carbon handles it correctly:
```php
// Spring forward (2 AM becomes 3 AM)
// Window: 01:00 - 04:00
// At 2:30 AM (doesn't exist) -> treated as 3:30 AM
```

#### Practical Usage

**Database-Driven Time Rules:**
```php
// Store rules in database
class TimeRestriction extends Model
{
    protected $fillable = ['permission', 'start_time', 'end_time', 'timezone'];
}

// Check with DB rules
$rule = TimeRestriction::where('permission', 'admin.access')->first();

Arbac::check($user, 'time-restricted.admin.access', [
    'start_time' => $rule->start_time,
    'end_time'   => $rule->end_time,
    'timezone'   => $rule->timezone,
]);
```

---

### IpWhitelistRule

#### Configuring Whitelist

**Context-Based** (not config-based):

```php
Arbac::check($user, 'ip-restricted.admin', [
    'allowed_ips' => ['192.168.1.100', '10.0.0.0/24'],
]);
```

**Database-Driven:**
```php
class IpWhitelist extends Model
{
    protected $casts = ['allowed_ips' => 'array'];
}

$whitelist = IpWhitelist::where('permission', 'admin')->first();

Arbac::check($user, 'ip-restricted.admin', [
    'allowed_ips' => $whitelist->allowed_ips,
]);
```

#### CIDR Notation

**Supported:**
```php
'allowed_ips' => [
    '192.168.1.100',      // Single IP
    '10.0.0.0/24',        // CIDR range (10.0.0.0 - 10.0.0.255)
    '172.16.0.0/16',      // Larger range
]
```

**Implementation:**
```php
protected function ipInRange(string $ip, string $range): bool
{
    list($subnet, $mask) = explode('/', $range);
    
    $ip_long = ip2long($ip);
    $subnet_long = ip2long($subnet);
    $mask_long = -1 << (32 - (int)$mask);
    $subnet_long &= $mask_long;
    
    return ($ip_long & $mask_long) === $subnet_long;
}
```

#### Client IP Detection

**Uses Laravel's `request()->ip()`:**

```php
$userIp = request()->ip();
```

**Proxy Handling:**
- Laravel automatically checks `X-Forwarded-For` if `TrustProxies` middleware is configured
- **Recommendation:** Configure `TrustProxies` middleware for production

```php
// app/Http/Middleware/TrustProxies.php
protected $proxies = '*'; // Or specific proxy IPs
```

**Custom Header:**
```php
// Extend IpWhitelistRule
protected function getClientIp(): string
{
    return request()->header('CF-Connecting-IP') // Cloudflare
        ?? request()->header('X-Real-IP')
        ?? request()->ip();
}
```

#### IPv4 vs IPv6

**Currently:** Only IPv4 is supported.

**IPv6 Support:** Would require updating `ipInRange()` method:
```php
// Future implementation
protected function ipInRange(string $ip, string $range): bool
{
    if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
        // IPv6 logic
    }
    // IPv4 logic (current)
}
```

#### Permission Naming Convention

Must start with `ip-restricted.`:

```php
public function supports(string $permission): bool
{
    return str_starts_with($permission, 'ip-restricted.');
}
```

---

## 7. Role Hierarchy

### Defining Hierarchy

```php
// config/arbac.php
'role_hierarchy' => [
    'super_admin' => ['admin', 'manager', 'member'],
    'admin'       => ['manager', 'member'],
    'manager'     => ['member'],
],
```

**Format:** `'higher_role' => ['subordinate', 'roles']`

### How hasRoleOrHigher() Works

```php
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
```

**Behavior:**
- Returns `true` if user has the exact role OR a higher role
- **Single role comparison** only (not multiple roles)
- **Depth:** Checks one level only (not transitive)

**Example:**
```php
// Config: 'admin' => ['manager', 'member']

$user->assignRole('admin');

$user->hasRoleOrHigher('admin');    // true (exact match)
$user->hasRoleOrHigher('manager');  // true (admin > manager)
$user->hasRoleOrHigher('member');   // true (admin > member)
$user->hasRoleOrHigher('guest');    // false
```

### Interaction with Spatie Methods

**Independent:** `hasRoleOrHigher()` is separate from Spatie's methods:

```php
// Spatie methods (no hierarchy)
$user->hasRole('admin');           // Exact match only
$user->hasAnyRole(['admin', 'manager']); // Any of these roles

// ARBAC method (with hierarchy)
$user->hasRoleOrHigher('manager'); // Checks hierarchy
```

**Recommendation:** Use `hasRoleOrHigher()` when you want hierarchy, Spatie methods when you don't.

### Permission Inheritance

**NOT automatic** - Permissions are not inherited through role hierarchy.

**Why:** Spatie manages permissions separately from ARBAC's hierarchy.

**Workaround:** Manually assign permissions based on hierarchy:
```php
// When assigning a role
if ($user->assignRole('admin')) {
    $hierarchy = config('arbac.role_hierarchy.admin', []);
    
    foreach ($hierarchy as $subordinateRole) {
        $role = Role::findByName($subordinateRole);
        $user->givePermissionTo($role->permissions);
    }
}
```

### Cycle Detection

**Not implemented** - Config is trusted to be acyclic.

**Recommendation:** Validate hierarchy in your application:
```php
// Validation example
function validateHierarchy(array $hierarchy): bool
{
    foreach ($hierarchy as $role => $subordinates) {
        if (in_array($role, $subordinates)) {
            throw new \Exception("Cycle detected: $role cannot be subordinate to itself");
        }
    }
    return true;
}
```

### Error Handling

**Malformed hierarchy:** Silently ignored (returns empty array).

```php
$subordinates = $hierarchy[$userRole->name] ?? []; // Safe default
```

---

## 8. Permission Groups

### Creating and Managing Groups

**Programmatically:**
```php
use Amrshah\Arbac\Models\PermissionGroup;

$group = PermissionGroup::create([
    'name'        => 'admin_permissions',
    'description' => 'All admin permissions',
    'permissions' => ['users.*', 'roles.*', 'permissions.*'],
]);
```

**Seeders:**
```php
// database/seeders/PermissionGroupSeeder.php
public function run()
{
    PermissionGroup::create([
        'name' => 'content_management',
        'permissions' => ['posts.create', 'posts.edit', 'posts.delete'],
    ]);
}
```

**CLI:** No built-in command. Create your own:
```php
php artisan make:command CreatePermissionGroup
```

**Admin UI:** Not included. Planned for v2.1.

### Attaching/Detaching Groups

```php
use Spatie\Permission\Models\Role;

$role = Role::findByName('editor');
$group = PermissionGroup::find(1);

// Assign all permissions in group to role
$group->assignToRole($role);

// Remove all permissions in group from role
$group->removeFromRole($role);
```

**Implementation:**
```php
public function assignToRole($role): void
{
    foreach ($this->permissions as $permission) {
        $role->givePermissionTo($permission);
    }
}
```

### Tenant Context

**Tenant-Aware by Default:**

```php
// Migration includes tenant_id
$table->unsignedBigInteger('tenant_id')->nullable();

// Model has tenant scope
public function scopeTenant($query)
{
    if (function_exists('tenant') && tenant()) {
        return $query->where('tenant_id', tenant('id'));
    }
    
    return $query;
}
```

**Usage:**
```php
// Create tenant-specific group
tenancy()->initialize($tenant);

PermissionGroup::create([
    'tenant_id' => tenant('id'),
    'name' => 'tenant_admin',
    'permissions' => ['tenant.settings'],
]);

// Query tenant groups
PermissionGroup::tenant()->get();
```

### Primary Use Case

**Development Convenience** - Mainly for seeders and initial setup:

```php
// Seed common permission groups
PermissionGroup::create([
    'name' => 'blog_author',
    'permissions' => ['posts.create', 'posts.edit', 'posts.view'],
]);

PermissionGroup::create([
    'name' => 'blog_editor',
    'permissions' => ['posts.*', 'categories.*'],
]);
```

**Runtime Management** - Can be used, but no UI provided (yet).

### ArbacManager Integration

**Not integrated** - Permission groups are a convenience layer on top of Spatie.

```php
// Groups don't affect permission checks directly
Arbac::check($user, 'posts.edit'); // Checks actual permissions, not groups
```

**Workflow:**
1. Create permission group
2. Assign group to role (expands to individual permissions)
3. User inherits permissions from role
4. ARBAC checks individual permissions

---

## 9. Performance & Indexing

### Performance Characteristics

**Without Caching:**
- ~50-100ms per check (depends on DB, rules)
- N+1 queries possible with multiple rules
- Suitable for low-traffic apps

**With Caching (Redis):**
- ~5-10ms per check (cache hit)
- ~50-100ms first check (cache miss)
- **10x faster** for repeated checks
- Recommended for production

**With Audit Logging:**
- +10-20ms per check (synchronous write)
- Consider async logging for high-traffic

### Recommended Indexes

**Beyond Migrations:**

```php
// arbac_audit_logs
$table->index(['tenant_id', 'created_at']); // Tenant reports
$table->index(['user_id', 'created_at']);   // User activity
$table->index(['action', 'created_at']);    // Denied access reports

// permission_groups
$table->index('tenant_id');
$table->index('name');

// Spatie tables (if tenant-aware)
$table->index(['tenant_id', 'name']); // roles, permissions
```

### High-Traffic Systems

**Recommendations:**

1. **Enable Redis Caching**
```env
ARBAC_CACHE_ENABLED=true
ARBAC_CACHE_STORE=redis
ARBAC_CACHE_TTL=3600
```

2. **Disable Granted Logs** (keep denied only)
```env
ARBAC_AUDIT_LOG_GRANTED=false
ARBAC_AUDIT_LOG_DENIED=true
```

3. **Use Queue for Audit Logs** (custom implementation)
```php
// Extend ArbacManager
protected function logPermissionCheck(...$args): void
{
    dispatch(new LogPermissionCheck(...$args));
}
```

4. **Optimize Attribute Rules**
```php
// Cache rule results if expensive
public function check(Authenticatable $user, string $permission, array $context = []): bool
{
    $cacheKey = "rule:{$user->id}:{$permission}";
    
    return Cache::remember($cacheKey, 60, function() use ($user, $permission, $context) {
        // Expensive check
    });
}
```

### Large Multi-Tenant Deployments

**Recommendations:**

1. **Partition Audit Logs by Tenant**
```sql
-- PostgreSQL example
CREATE TABLE arbac_audit_logs_tenant_1 PARTITION OF arbac_audit_logs
FOR VALUES IN (1);
```

2. **Separate Cache per Tenant**
```php
// Already implemented via cache keys
"arbac:{tenant_id}:permission:..."
```

3. **Index Optimization**
```php
// Composite indexes for common queries
$table->index(['tenant_id', 'user_id', 'created_at']);
```

4. **Consider Read Replicas**
```php
// Use read replica for audit log queries
ArbacAuditLog::on('read')->where(...)->get();
```

### Query Optimization

**Eager Loading:**
```php
// ArbacManager doesn't use relationships heavily
// Most checks are direct queries or cached
```

**N+1 Prevention:**
```php
// Pre-load user roles if checking multiple permissions
$user->load('roles.permissions');

foreach ($permissions as $permission) {
    Arbac::check($user, $permission); // Uses loaded data
}
```

**Caching + Logging + Multi-Tenancy:**
```php
// Optimized flow:
1. Check cache (tenant-scoped key)
2. If miss, query DB
3. Cache result
4. Log asynchronously (if high-traffic)
```

---

## 10. Upgrade & Compatibility

### Behavioral Differences from 1.x

**Even with features disabled:**

1. **ArbacManager is now a Singleton**
   - v1.x: New instance each time
   - v2.0: Singleton with auto-loaded rules

2. **Middleware Auto-Registered**
   - v1.x: Manual registration required
   - v2.0: Automatic via service provider

3. **Blade Directives Available**
   - v1.x: Not available
   - v2.0: Always registered

4. **Additional Migrations**
   - v1.x: Only `create_arbac_table`
   - v2.0: + `create_arbac_audit_logs_table`, `create_permission_groups_table`

### Breaking Changes

**None** - v2.0 is backward compatible.

**Config Changes:**
- New keys added (multi_tenancy, audit, role_hierarchy)
- Old keys unchanged
- Defaults are safe (new features disabled)

**API Changes:**
- No removed methods
- No changed signatures
- Only additions

**Middleware:**
- `role` alias may conflict with Spatie (see Middleware section)

### Recommended Upgrade Steps

1. **Backup Database**
```bash
php artisan backup:run
```

2. **Update Composer**
```bash
composer require amrshah/laravel-arbac:^2.0
```

3. **Publish New Migrations**
```bash
php artisan vendor:publish --tag="arbac-migrations"
php artisan migrate
```

4. **Update Configuration** (optional)
```bash
php artisan vendor:publish --tag="arbac-config" --force
```

5. **Review New Config Options**
```php
// config/arbac.php
'cache' => ['enabled' => true],           // Enable caching
'multi_tenancy' => ['enabled' => false],  // Keep disabled unless needed
'audit' => ['enabled' => false],          // Enable if needed
```

6. **Test Specific Areas:**
   - Permission checks still work
   - Middleware routes still protected
   - Custom attribute rules still function
   - Multi-tenant apps: Test tenant isolation

7. **Optional: Enable New Features**
```php
// Gradually enable features
'cache' => ['enabled' => true],  // Week 1
'audit' => ['enabled' => true],  // Week 2
```

### Default Configs

**All new features are OFF by default:**

```php
'cache' => [
    'enabled' => true,  // ⚠️ Exception: Caching is ON by default
],

'multi_tenancy' => [
    'enabled' => false, // ✅ OFF
],

'audit' => [
    'enabled' => false, // ✅ OFF
],
```

**Why caching is ON:** Performance improvement with no breaking changes.

**To disable caching:**
```env
ARBAC_CACHE_ENABLED=false
```

### Testing Checklist

After upgrade, test:

- [ ] Basic permission checks work
- [ ] Role assignments work
- [ ] Middleware protects routes
- [ ] Custom attribute rules function
- [ ] No performance regressions
- [ ] Multi-tenant isolation (if applicable)
- [ ] Cache invalidation works (if enabled)
- [ ] Audit logs write correctly (if enabled)

---

## Appendix: Common Patterns

### Pattern 1: Tenant-Aware Seeding

```php
public function run()
{
    $tenants = Tenant::all();
    
    foreach ($tenants as $tenant) {
        tenancy()->initialize($tenant);
        
        // Seed tenant-specific data
        Permission::create(['name' => 'edit.posts', 'tenant_id' => $tenant->id]);
        
        tenancy()->end();
    }
}
```

### Pattern 2: Conditional Feature Flags

```php
// Check if user can access beta features
Arbac::check($user, 'time-restricted.beta', [
    'start_time' => config('features.beta_start'),
    'end_time' => config('features.beta_end'),
]);
```

### Pattern 3: IP-Based Admin Access

```php
// Restrict admin panel to office IPs
Route::middleware(['auth', 'arbac:ip-restricted.admin'])->group(function () {
    Route::get('/admin', function () {
        // Check passes context automatically
        return view('admin.dashboard');
    });
});

// In controller or middleware
$context = ['allowed_ips' => config('security.admin_ips')];
```

### Pattern 4: Audit Log Analysis

```php
// Security monitoring
$suspiciousActivity = ArbacAuditLog::denied()
    ->where('created_at', '>', now()->subHour())
    ->selectRaw('user_id, COUNT(*) as attempts')
    ->groupBy('user_id')
    ->having('attempts', '>', 10)
    ->get();

// Alert on suspicious activity
foreach ($suspiciousActivity as $activity) {
    event(new SuspiciousActivityDetected($activity));
}
```

---

## Support & Contributing

**Questions?** Open an issue on [GitHub](https://github.com/amrshah/laravel-arbac)

**Found a bug?** Please report with:
- Laravel version
- ARBAC version
- Steps to reproduce
- Expected vs actual behavior

**Want to contribute?** PRs welcome! Please include tests.

---

**Developer Guide v2.0.0** | Last Updated: 2025-11-22
