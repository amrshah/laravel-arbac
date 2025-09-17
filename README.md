# ARBAC – Advanced Attribute/Role-Based Access Control for Laravel

[![Tests](https://github.com/amrshah/arbac/actions/workflows/run-tests.yml/badge.svg)](https://github.com/amrshah/arbac/actions)

> **ARBAC** is a Laravel package that combines classic Role-Based Access Control (RBAC) with Attribute-Based Access Control (ABAC) and additional enterprise-ready features — while still being easy enough for small projects.

---

##  Features (MVP)

-  Role-based permissions (built on top of [spatie/laravel-permission])
-  Attribute-based rules (e.g. resource owner, department, status)
-  Dynamic policies & conditions (time-based, context-based)
-  UI components (Blade/Vue) for managing roles, permissions, and attributes
-  Works out of the box with Laravel 10 & 11 (PHP 8.1+)
-  Clean service provider + config publishing
- Pest test suite preconfigured

---

##  Installation

In a Laravel project (10 or 11):


composer require amrshah/arbac

If you’re developing locally:
```
// composer.json
"repositories": [
  {
    "type": "path",
    "url": "packages/amrshah/arbac"
  }
]
```

Then

```
composer require amrshah/arbac:dev-main
```
### Configuration

Publish the config and (if needed) migrations:
```
php artisan vendor:publish --tag="arbac-config"
php artisan vendor:publish --tag="arbac-migrations"
php artisan migrate
```

This gives you a config/arbac.php file where you can tweak defaults (models, cache, UI settings, attribute rules).

### Basic Usage

```
use Amrshah\Arbac\Facades\Arbac;

Arbac::assignRole($user, 'editor');

Arbac::can($user, 'publish-post', ['post' => $post]);
```


ARBAC checks both role-based permissions and any registered attribute rules before allowing access.

### Roadmap

 UI for managing roles/permissions/attributes (Blade + Vue components)

 Granular caching & audit logs

 Multi-tenant aware permissions

 Policy generator for ABAC rules

 Ready-made Livewire/Vue components for dashboards


### Testing
composer test


The laravel-arbac uses Pest by default.

### License

MIT © Amr Shah
