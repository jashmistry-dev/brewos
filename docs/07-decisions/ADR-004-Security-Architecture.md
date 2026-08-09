# ADR-004 — Security Architecture

**Status:** Accepted
**Date:** 2026-08-08

---

## Context

BrewOS handles sensitive data across multiple tenants including:

- Cafe business data
- Customer personal information
- Staff accounts and roles
- Payment records and transaction references
- Subscription and billing information
- Platform configuration

A formal security architecture is required before implementation begins to ensure that security is built in by design, not retrofitted after the fact.

---

## Decision

### Security Enforcement Order

Every authenticated request must pass through the following layers in order:

```
1. Authentication
        ↓
2. Tenant Resolution
        ↓
3. Authorization (Gate / Policy)
        ↓
4. Form Request Validation
        ↓
5. Controller / Service
        ↓
6. Database
```

A request that fails at any layer must be rejected at that layer and must not proceed to the next.

---

## Authentication

### Mechanism

- **Admin and staff web sessions:** Laravel session-based authentication using the `web` guard.
- **Customer QR ordering:** Laravel session or stateless anonymous token — to be specified in the QR ordering flow design document.
- **Future API/external clients:** Laravel Sanctum personal access tokens.

### Password Handling

- Passwords must be hashed using **bcrypt** (Laravel default).
- Minimum password length: **8 characters**.
- Passwords must never be stored in plaintext or logged anywhere.
- Passwords must never appear in `audit_logs.old_values` or `audit_logs.new_values`.

### Session Security

- Sessions must be stored in Redis or the database — not in local file storage.
- Session cookies must have `HttpOnly`, `Secure` (in production), and `SameSite=Lax` attributes.
- Session IDs must be regenerated after successful login (prevents session fixation).

### Email Verification

- Email verification is required before a new staff or owner account gains full access.
- The verification link must expire after a defined period.

### Password Reset

- Password reset tokens must be single-use and time-limited.
- Reset links must be delivered by email only.
- After a successful reset, all existing sessions for the user must be invalidated.

---

## Tenant Resolution

Tenant context must be resolved from the authenticated request, not from client-supplied parameters.

Resolution process:

1. Extract `cafe_slug` from the route (`/api/cafes/{cafe_slug}/...`)
2. Look up the `cafes` table for a matching active cafe record
3. Verify the authenticated user has an active `cafe_users` membership in that cafe
4. Inject the resolved `Cafe` model into the request context for use throughout the request lifecycle
5. Reject with `403 Forbidden` if the user does not belong to the resolved cafe

**A client must never be able to switch their active cafe by modifying request parameters.**

---

## Authorization

### Role-Based Access Control (RBAC)

- Users are assigned roles through `cafe_users.role_id`.
- Roles are assigned permissions through `role_permission`.
- Authorization checks must always verify both the permission and the tenant context.

### Laravel Gates and Policies

- All resource-level authorization must use **Laravel Policies**.
- Policies must verify both the permission (`user has permission X`) and tenant ownership (`resource belongs to authenticated cafe`).
- `Gate::before()` may be used to grant Super Admin bypass access to platform-level resources only — never to tenant data without an explicit audit trail.

### Hardcoded Role Checks Are Prohibited

The following pattern is explicitly prohibited throughout the application:

```php
// PROHIBITED — do not do this
if ($user->role === 'manager') {
    // ...
}
```

Instead:

```php
// CORRECT
$this->authorize('order.update', $order);
```

### Permission Resolution

Permissions are resolved through the role-permission chain:

```
User
  → Cafe Membership (cafe_users)
    → Role
      → Role Permissions (role_permission)
        → Permission slug
```

Permission data should be cached per user+cafe combination and invalidated when the role or role permissions change.

---

## Tenant Isolation

### Model Scopes

All Eloquent models representing tenant-owned data must apply a `TenantScope` global scope that automatically restricts all queries to the authenticated cafe.

Models that derive tenant ownership through a parent (e.g., `OrderItem` through `Order`) must join through the parent to enforce isolation — they cannot use a direct `cafe_id` scope.

### Cross-Table Validation

All write operations that involve a cross-table foreign key must validate that both records belong to the same cafe. This validation must occur in the Form Request or Service layer, before the database write.

Examples that require explicit validation:

- `menu_items.category_id` must belong to the same `cafe_id` as the menu item
- `cafe_users.role_id` must belong to the same `cafe_id` as the cafe_user record (for tenant roles)
- `orders.branch_id` must belong to the same `cafe_id` as the order
- `cafe_users.branch_id` must belong to the same `cafe_id` as the cafe_user record

### Prohibited Behaviors

- Client-controlled cafe switching (e.g., a POST body parameter `cafe_id`) is prohibited.
- Client-controlled financial totals are prohibited. Monetary values (subtotals, taxes, discounts, totals) must always be calculated server-side from verified data. Client-submitted totals must never be trusted or persisted without server-side recalculation.

---

## Super Admin Isolation

The Super Admin is isolated from the tenant system:

- The Super Admin uses a separate route prefix: `/admin/...`
- The Super Admin does not go through the cafe tenant resolution middleware
- The Super Admin's access scope is the platform, not any individual cafe
- The Super Admin cannot access cafe business data (orders, customers, invoices) through the standard tenant API endpoints
- Any Super Admin action that inspects tenant data must be performed through dedicated, audited Super Admin endpoints that require an explicit Super Admin permission check
- Super Admin actions must be logged in `audit_logs` with `cafe_id` set to the affected cafe and `user_id` set to the Super Admin user

---

## Input Validation and Mass Assignment

### Form Request Validation

Every controller action that accepts user input must use a dedicated **Laravel Form Request** class. Inline validation in controllers is prohibited.

### Mass Assignment Protection

All Eloquent models must explicitly define either `$fillable` or `$guarded`:

- Preferred: `$fillable` with explicit list of allowed columns
- Computed or sensitive columns (`cafe_id`, `status`, financial totals, `role_id`) must not be mass-assignable

---

## CSRF Protection

- CSRF protection is provided by Laravel's default `VerifyCsrfToken` middleware for all `web` guard routes.
- API routes using stateless token authentication (Sanctum) are exempt from CSRF but must use token validation instead.
- CSRF tokens must be included in all state-changing web form submissions and Inertia.js requests (Inertia handles this automatically).

---

## Rate Limiting

The following rate limiting rules apply:

| Endpoint / Action | Limit |
|---|---|
| Login attempt | 5 attempts per minute per IP |
| Password reset request | 3 attempts per 15 minutes per IP |
| API endpoints (authenticated) | 120 requests per minute per user |
| QR ordering endpoints (public) | 60 requests per minute per IP |
| File upload endpoints | 20 requests per minute per user |

Rate limiting must be implemented using Laravel's built-in rate limiter backed by Redis.

Throttle responses must return `HTTP 429 Too Many Requests` with a `Retry-After` header.

---

## File Upload Security

| Requirement | Rule |
|---|---|
| Allowed types | Defined per upload context (e.g., images only for logos and menu items) |
| Maximum file size | Defined per upload context (e.g., 5 MB for images) |
| File name | Must be sanitized and replaced with a server-generated UUID-based name |
| Storage location | Must use Laravel Storage abstraction (not raw `$_FILES`) |
| Public access | Menu item images and cafe logos: public URL. Invoice logos: private, signed URL |
| Validation | MIME type must be validated server-side — client-reported MIME is not trusted |
| Malware | No malware scanning in Phase 1 — file type validation only. Revisit before processing uploaded documents |

---

## Audit Logging

The `audit_logs` table records security-relevant and business-relevant events.

### What Must Be Logged

| Event | Required |
|---|---|
| Login (success and failure) | Yes |
| Password change | Yes |
| Role assignment change | Yes |
| Permission change | Yes |
| Cafe settings change | Yes |
| Staff account created / deactivated | Yes |
| Menu item price change | Yes |
| Order cancelled | Yes |
| Subscription changed | Yes |
| Payment recorded | Yes |
| Super Admin action on tenant | Yes |

### Sensitive Field Exclusions

The following fields must never appear in `audit_logs.old_values` or `audit_logs.new_values`:

- `password`
- `remember_token`
- Any raw payment credential or card number
- Any third-party API secret

### Audit Log Integrity

- Audit logs are append-only. No audit log record should be modified or deleted by the application.
- Audit log writes should be synchronous for security-critical events (not queued) to ensure the log is always current.

---

## Environment and Secrets

- All credentials, API keys, and secrets must be stored in environment variables (`.env` file).
- The `.env` file must be listed in `.gitignore` and must never be committed to the Git repository.
- Production secrets must be injected through the deployment pipeline environment configuration, not committed to version control.
- A `.env.example` file listing all required environment variable names (with no real values) must be maintained and committed.

---

## What the Frontend May and May Not Do

| Action | Permitted |
|---|---|
| Hide unauthorized UI elements based on user permissions | Permitted — improves UX |
| Rely on hidden UI as the sole access control mechanism | Prohibited |
| Submit financial totals for the server to trust and persist | Prohibited |
| Identify the active cafe through URL slug | Permitted |
| Override the active cafe through POST/PATCH body parameters | Prohibited |

Server-side authorization is always mandatory regardless of frontend behavior.

---

## Consequences

### Positive

- Security is enforced at every layer of the request lifecycle
- Tenant isolation is explicit and testable
- Clear rules prevent common vulnerabilities (mass assignment, client-controlled totals, cross-tenant access)
- Audit logging provides accountability and forensic capability

### Negative / Risks

- Strict layering requires discipline and code review
- Policy and scope enforcement adds complexity to model and controller code
- Incorrect cache invalidation of permission data could cause authorization errors

### Mitigation

- Automated integration tests for all authorization rules
- Automated tenant isolation tests for all API endpoints
- Code review checklist covering security requirements
- Development-time tools (Laravel Telescope, query logging) to detect scope bypasses
