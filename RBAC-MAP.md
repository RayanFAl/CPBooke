# RBAC Hardening Audit

This audit maps every permission that existed in the RBAC registry to its real usage in the codebase.

Global gate registration

- All registry permissions are exposed as gates in `App\Providers\AppServiceProvider::boot` through the dynamic `Gate::define($permission, ...)` loop.
- A permission is only considered `USED` when it has a real enforcement point or a concrete UI permission check outside the registry itself.

## Active Permissions

### `users.view`

- Status: `USED`
- Usage:
  - Admin route middleware in `routes/admin/users.php` on `index` and `show`
  - Admin navigation permission in `resources/js/modules/admin/config/navigation.js`
- Usage type:
  - Admin
- Enforcement:
  - Route middleware

### `users.create`

- Status: `USED`
- Usage:
  - Admin route middleware in `routes/admin/users.php` on `create` and `store`
  - Form request authorization in `app/Modules/Admin/Users/Http/Requests/StoreUserRequest.php::authorize`
  - UI permission check in `resources/js/modules/admin/users/pages/Index.vue`
- Usage type:
  - Admin
- Enforcement:
  - Route middleware
  - Form request authorization
  - UI check for visibility only

### `users.update`

- Status: `USED`
- Usage:
  - Admin route middleware in `routes/admin/users.php` on `edit`, `update`, and `toggleStatus`
  - Form request authorization in `app/Modules/Admin/Users/Http/Requests/UpdateUserRequest.php::authorize`
  - UI permission checks in `resources/js/modules/admin/users/pages/Index.vue` and `resources/js/modules/admin/users/pages/Show.vue`
- Usage type:
  - Admin
- Enforcement:
  - Route middleware
  - Form request authorization
  - UI checks for visibility only

### `orders.view`

- Status: `USED`
- Usage:
  - Admin route middleware in `routes/admin/orders.php` on `index` and `show`
  - Controller gate in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::index`
  - Controller gate in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::show`
  - Admin navigation permission in `resources/js/modules/admin/config/navigation.js`
- Usage type:
  - Admin
- Enforcement:
  - Route middleware
  - Controller gate authorization

### `orders.change-status`

- Status: `USED`
- Usage:
  - Admin route middleware in `routes/admin/orders.php` on `updateStatus`
  - Controller gate in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::updateStatus`
  - Controller conditional payload exposure in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::show`
  - UI permission check in `resources/js/modules/admin/orders/pages/Show.vue`
- Usage type:
  - Admin
- Enforcement:
  - Route middleware
  - Controller gate authorization
  - UI check for visibility only

### `orders.financials.view`

- Status: `USED`
- Usage:
  - Controller conditional payload exposure in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::index`
  - Controller conditional payload exposure in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::show`
  - UI permission checks in `resources/js/modules/admin/orders/pages/Index.vue` and `resources/js/modules/admin/orders/pages/Show.vue`
- Usage type:
  - Admin
- Enforcement:
  - Backend payload shaping hides financial data when unauthorized
  - UI checks mirror backend visibility

### `support.view`

- Status: `USED`
- Usage:
  - Admin route middleware in `routes/admin/support.php`
  - Admin navigation permission in `resources/js/modules/admin/config/navigation.js`
- Usage type:
  - Admin
- Enforcement:
  - Route middleware

### `finance.view`

- Status: `USED`
- Usage:
  - Admin route middleware in `routes/admin/finance.php`
  - Controller conditional payload exposure in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::index`
  - Controller conditional payload exposure in `app/Modules/Admin/Orders/Http/Controllers/OrdersController.php::show`
  - Admin navigation permission in `resources/js/modules/admin/config/navigation.js`
  - UI permission checks in `resources/js/modules/admin/orders/pages/Index.vue` and `resources/js/modules/admin/orders/pages/Show.vue`
- Usage type:
  - Admin
- Enforcement:
  - Route middleware
  - Backend payload shaping for order financial fields
  - UI checks mirror backend visibility

## Removed Dead Permissions

### `users.delete`

- Status: `UNUSED`
- Usage:
  - No route, controller, request, policy, middleware, gate call site, or UI check outside the registry
- Cleanup:
  - Removed from `app/Support/Rbac/RbacRegistry.php`

### `support.reply`

- Status: `UNUSED`
- Usage:
  - No route, controller, request, policy, middleware, gate call site, or UI check outside the registry
- Cleanup:
  - Removed from `app/Support/Rbac/RbacRegistry.php`

### `support.close`

- Status: `UNUSED`
- Usage:
  - No route, controller, request, policy, middleware, gate call site, or UI check outside the registry
- Cleanup:
  - Removed from `app/Support/Rbac/RbacRegistry.php`

### `settings.manage`

- Status: `UNUSED`
- Usage:
  - The settings module exists, but access is controlled by `role:super_admin` in `routes/admin/settings.php`
  - The settings navigation item in `resources/js/modules/admin/config/navigation.js` also uses `role`, not this permission
  - The permission string itself has no real enforcement or UI check
- Cleanup:
  - Removed from `app/Support/Rbac/RbacRegistry.php`

## Partial Usage Findings

- No remaining permission is classified as `PARTIALLY_USED` after cleanup.

## Final Registry State

- The RBAC registry now contains only permissions with live usage in the current codebase.
- `Database\Seeders\RolesAndPermissionsSeeder` required no direct change because it already derives and cleans permissions from `App\Support\Rbac\RbacRegistry` dynamically.