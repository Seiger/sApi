# sApi

**sApi** is a lightweight, framework-agnostic API core for **Evolution CMS**, designed to build modern, versioned JSON APIs with Laravel-style routing and JWT authentication.

The package provides only the essential infrastructure:
- routing
- middleware
- authentication
- extensibility for other packages

Business logic, CRUD scaffolding, GraphQL, and API documentation are intentionally **out of scope** of the core and should be implemented via separate modules.

---

## Key Features

- ✅ Single front controller (`/api/index.php`, `/rest/index.php`, etc.)
- ✅ Laravel-style routing (based on `illuminate/routing`)
- ✅ API versioning via URL (`/v1/...`)
- ✅ JWT authentication (Bearer tokens)
- ✅ Middleware with scope-based authorization
- ✅ JSON-only responses (no XML, no GraphQL)
- ✅ Automatic discovery of API routes from other packages
- ✅ Designed for Evolution CMS ecosystem

---

## Requirements

- PHP **8.4+**
- Evolution CMS 3.5+
- Composer

---

## Installation

```bash
composer require seiger/sapi
```

---

## Basic Concept

`sApi` acts as an **API kernel**, not a full framework.

You create a single front controller file (for example `/api/index.php`), bootstrap Evolution CMS, and let `sApi` handle:

- routing
- authentication
- middleware execution
- request dispatching

All API endpoints are defined via route providers.

---

## Front Controller Example

```php
<?php
declare(strict_types=1);

define('MODX_API_MODE', true);
define('IN_MANAGER_MODE', false);

require_once dirname(__DIR__) . '/index.php';

require_once EVO_BASE_PATH . 'vendor/autoload.php';

use Seiger\sApi\Kernel;

$kernel = new Kernel($modx);
$kernel->handle();
```

The base API path is detected automatically from the controller location  
(e.g. `/api`, `/rest`, etc.).

---

## Routing

`sApi` uses **Laravel-style routing** internally.

### Example (core routes)

```php
$router->group(['prefix' => 'v1'], function () use ($router) {
    $router->get('health', function () {
        return new JsonResponse(['ok' => true]);
    });
});
```

---

## JWT Authentication

`sApi` supports **Bearer JWT authentication**.

```
Authorization: Bearer <jwt-token>
```

### Middleware

```php
$router->group(['middleware' => ['jwt:orders:read']], function () use ($router) {
    $router->get('orders', OrdersController::class . '@index');
});
```

JWT payload example:

```json
{
  "sub": 12,
  "scopes": ["orders:read", "orders:write"],
  "exp": 1710000000
}
```

---

## Access Logging (JSON lines)

sApi writes access logs for every API request under `base_path` (except `exclude_paths`) in JSON lines format using the standard Evolution/Laravel logger (Monolog daily).

- Daily file: `core/storage/logs/sapi-YYYY-MM-DD.log`
- Retention: `LOG_DAILY_DAYS` (default `14`)

Configuration is file-only: `core/custom/config/seiger/settings/sApi.php` → `logging`.

Notes:
- Response always includes `X-Request-Id`.
- `Authorization` / cookies are never written to access logs.
- Request body is logged only for `4xx/5xx` (with redaction + size limit).

---

## Audit Logging (Business Events)

Audit logs are explicit **business events** (e.g. `token.issued`, `orders.updated`) written as **JSON lines** into the same daily `sapi` log channel (timeline-friendly).

- Daily file: `core/storage/logs/sapi-YYYY-MM-DD.log`
- Retention: `LOG_DAILY_DAYS` (default `14`)

Example:

```php
app(\Seiger\sApi\Logging\AuditLogger::class)->event('orders.updated', [
    'order_id' => 123,
    'status' => 'paid',
], 'notice');
```

Notes:
- `request_id`, `route`, `sub` can be auto-filled from the current request context (when available).
- Never log JWT tokens or secrets in audit context (redaction is applied by key name).

---

## Route Providers (Extensibility)

Other packages (e.g. `sCommerce`, `sSeo`) can automatically register their API endpoints.

### Interface

```php
use Illuminate\Routing\Router;

interface RouteProviderInterface
{
    public function register(Router $router): void;
}
```

### Example Provider (in another package)

```php
final class CommerceRouteProvider implements RouteProviderInterface
{
    public function register(Router $router): void
    {
        $router->group(['prefix' => 'v1', 'middleware' => ['jwt:orders:read']], function () use ($router) {
            $router->get('orders', OrdersController::class . '@index');
        });
    }
}
```

### Composer Auto-Discovery

```json
{
  "extra": {
    "sapi": {
      "route_providers": [
        "Seiger\\sCommerce\\Api\\Routes\\CommerceRouteProvider"
      ]
    }
  }
}
```

`sApi` will automatically discover and register these providers.

---

## JSON Response Format

All responses are JSON.

### Success
```json
{
  "ok": true,
  "data": {}
}
```

### Error
```json
{
  "ok": false,
  "error": {
    "code": "unauthorized",
    "message": "Invalid token"
  }
}
```

---

## OpenAPI / Swagger

`sApi` is designed to be compatible with **OpenAPI 3.2.0**.

Documentation generation and Swagger UI **are not included in the core**  
and will be provided via a separate module.

---

## License

This package is licensed under the **GNU General Public License v3.0**.

You are free to use it in commercial and non-commercial projects.  
If you distribute modified versions or derivative works, they must also be licensed under GPLv3.

See the `LICENSE` file for details.

---

## Roadmap (High-Level)

- JWT refresh tokens
- Rate limiting middleware
- OpenAPI 3.2 documentation module
- CLI helpers for API debugging
- Request validation helpers

---

## Philosophy

> **Explicit over magic.**  
> **Infrastructure, not business logic.**  
> **Composable, not monolithic.**

`sApi` is intentionally minimal.  
If you need full-stack API automation, use a framework.  
If you need a solid API core for Evolution CMS — this is it.
