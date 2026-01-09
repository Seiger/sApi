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

## Environment Variables

Recommended location: `core/custom/.env` (EvolutionCMS loads it in `core/bootstrap.php`).

| Variable |                                   Default | Description |
|---|------------------------------------------:|---|
| `SAPI_BASE_PATH` |                                     `api` | Base prefix for all sApi routes (e.g. `rest` → `/rest/token`). |
| `SAPI_VERSION` |                                      `v1` | Optional API version prefix (e.g. `v1` → `/rest/v1/token`). Leave empty to use unversioned routes. |
| `SAPI_JWT_SECRET` |                                 _(empty)_ | HS256 secret used to sign/verify JWTs. **Required** to issue tokens. |
| `SAPI_JWT_TTL` |                                    `3600` | Token TTL in seconds. |
| `SAPI_JWT_SCOPES` |                                       `*` | Default token scopes (comma-separated). |
| `SAPI_JWT_ISS` |                                 _(empty)_ | Optional `iss` claim. |
| `SAPI_ALLOWED_USER_ROLES` |                                       `1` | Allowed Evo manager roles for `/token` (comma-separated). |
| `SAPI_LOGGING_ENABLED` |                                       `1` | Enable/disable all sApi logging. |
| `SAPI_LOG_ACCESS_ENABLED` |                                       `1` | Enable/disable access log entries. |
| `SAPI_LOG_EXCLUDE_PATHS` |                                 _(empty)_ | Comma-separated paths to skip from access logging (e.g. `/rest/health`). |
| `SAPI_LOG_BODY_ON_ERROR` |                                       `1` | Log request body for `4xx/5xx`. |
| `SAPI_LOG_MAX_BODY_BYTES` |                                    `4096` | Max request body bytes to log. |
| `SAPI_LOG_AUDIT_ENABLED` |                                       `1` | Enable/disable audit events logging. |
| `SAPI_AUDIT_EXCLUDE_EVENTS` |                                 _(empty)_ | Comma-separated event patterns to skip (supports `*` via `fnmatch`). |
| `SAPI_AUDIT_MAX_CONTEXT_BYTES` |                                    `8192` | Max audit context size. |
| `SAPI_REDACT_BODY_KEYS` | `password,token,refresh_token,jwt,secret` | Keys to redact in logged bodies/contexts. |
| `APP_NAME` |                                     `evo` | Used as Monolog channel `name` for the `sapi` logger. |
| `LOG_LEVEL` |                                   `debug` | Log level for the `sapi` logger. |
| `LOG_DAILY_DAYS` |                                      `14` | Retention days for daily logs. |

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

define('EVO_API_MODE', true);
define('IN_MANAGER_MODE', false);

require_once dirname(__DIR__) . '/index.php';

require_once EVO_BASE_PATH . 'vendor/autoload.php';

use Seiger\sApi\Kernel;

$kernel = new Kernel($modx);
$kernel->handle();
```

The base API path is configured via `SAPI_BASE_PATH` (e.g. `api`, `rest`).

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

### Issuing a Token

To get a JWT, call the built-in token endpoint:

- `POST /{SAPI_BASE_PATH}/{SAPI_VERSION}/token` (if `SAPI_VERSION` is empty → `/{SAPI_BASE_PATH}/token`)
- Body (JSON): `{ "username": "manager_user", "password": "..." }`
- Access is restricted by `SAPI_ALLOWED_USER_ROLES` (Evolution manager role ids).

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

sApi writes access logs for every API request under `SAPI_BASE_PATH` (except `exclude_paths`) in JSON lines format using the standard Evolution/Laravel logger (Monolog daily).

- Daily file: `core/storage/logs/sapi-YYYY-MM-DD.log`
- Retention: `LOG_DAILY_DAYS` (default `14`)

Configuration is via `.env` variables (see `SAPI_LOG_*` and `SAPI_REDACT_BODY_KEYS` above).

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
final class OrdersRouteProvider implements RouteProviderInterface
{
    public function register(Router $router): void
    {
        $router->group(['prefix' => 'orders', 'middleware' => ['jwt:orders:read']], function () use ($router) {
            $router->get('', [OrdersController::class, 'index'])->name('index');
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
        { "class": "Seiger\\sCommerce\\Api\\Routes\\OrdersRouteProvider", "endpoint": "orders" },
        { "class": "Seiger\\sCommerce\\Api\\Routes\\LegacyOrdersRouteProvider", "endpoint": "orders", "version": "v0" }
      ]
    }
  }
}
```

Rules:
- `endpoint` is required.
- `version` is optional; if omitted, `SAPI_VERSION` is used (can be empty for unversioned routes).
- Cache key is `{version}/{endpoint}` or `{endpoint}` when version is empty.
- Priority: `core/custom/composer.json` overrides vendor entries.
- Route name prefix: `sApi.{endpoint}.{version}.…` (version is omitted when empty).
- Providers must register routes **without** `SAPI_BASE_PATH` and **without** version in the URI (sApi applies them).

### Cache

Discovery is cached in `core/storage/cache/sapi_routes_map.php` and is rebuilt only when:
- `core/composer.lock` changes (mtime/size)
- `core/custom/composer.json` changes (mtime/size), if the file exists
- `SAPI_VERSION` changes
- `SAPI_BASE_PATH` changes

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
