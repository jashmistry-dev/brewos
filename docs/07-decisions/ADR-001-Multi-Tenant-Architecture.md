# ADR-001 — Multi-Tenant Architecture

**Status:** Accepted
**Date:** 2026-08-08

---

## Context

BrewOS is a SaaS platform serving multiple independent cafe businesses (tenants) from a single application deployment. A fundamental architectural decision must be made about how tenant data is stored and isolated.

The three common multi-tenancy strategies are:

1. **Separate databases per tenant** — strong isolation, high operational complexity and cost
2. **Shared database, separate schemas** — moderate isolation, moderate complexity
3. **Shared database, shared schema** — simpler operations, isolation enforced at application layer

BrewOS must choose a strategy appropriate for its current scale, budget, and operational capacity, while allowing migration toward stricter isolation in the future if required.

---

## Decision

BrewOS will use a **shared database, shared schema** multi-tenant architecture.

```
Shared Laravel Application
         +
  Shared MySQL Database
         +
  Cafe-based tenant isolation (enforced at application layer)
```

---

## Tenant Model

### The Tenant

The `cafes` table is the tenant table. Each row in `cafes` represents one independent business (tenant).

```
cafes
├── id
├── name
├── slug       ← used for tenant identification in URLs and context resolution
├── status
└── ...
```

### User Identity

Users are **global identities**. A single user account can belong to one or more cafes.

User-to-tenant membership is established through the `cafe_users` pivot table:

```
users
  ↓  1:N
cafe_users
  ↓  N:1
cafes
```

### Cafe Membership Structure

The `cafe_users` table determines:

| Column | Purpose |
|---|---|
| `cafe_id` | Which cafe this membership belongs to |
| `user_id` | Which user this membership belongs to |
| `role_id` | The user's role within this specific cafe |
| `branch_id` | Optional operational branch assignment within the cafe |
| `status` | Membership status (active, suspended, etc.) |

This allows the same user to have different roles at different cafes:

```
User A
├── Cafe A → Manager
└── Cafe B → Cafe Owner
```

### Tenant-Owned Resources

All cafe-level resources must carry a `cafe_id` foreign key directly, or derive their tenant ownership through a parent table that does.

Examples:

- `customers.cafe_id` → direct tenant ownership
- `categories.cafe_id` → direct tenant ownership
- `orders.cafe_id` → direct tenant ownership
- `order_items` → tenant ownership derived through `orders.cafe_id`
- `restaurant_tables` → tenant ownership derived through `branches.cafe_id`

---

## Tenant Isolation Rules

### Rule 1 — Server-Side Enforcement

Tenant isolation must be enforced **server-side** at every layer:

```
Authentication
    ↓
Tenant Resolution (Middleware)
    ↓
Authorization (Policies)
    ↓
Query Scopes (Models)
    ↓
Database
```

Frontend visibility controls (hiding buttons, hiding menu items) are **not** a security mechanism and must never be the sole method of preventing tenant data access.

### Rule 2 — Tenant Context Resolution

The active cafe context must be resolved from the authenticated request, not from client-supplied parameters.

The tenant context will be resolved through the URL structure using the cafe slug:

```
/api/cafes/{cafe_slug}/...
```

The tenant resolution middleware will:

1. Extract the `cafe_slug` from the route
2. Look up the `cafes` table for a matching active cafe
3. Verify the authenticated user has an active membership in that cafe
4. Inject the resolved `Cafe` model into the request context
5. Reject any request where the user does not belong to the resolved cafe

A user cannot self-select or override their tenant context through request parameters.

### Rule 3 — Model Scopes

All Eloquent models representing tenant-owned data must enforce tenant scoping at the model layer.

A dedicated `TenantScope` will be applied globally to all tenant-owned models so that no query can accidentally return cross-tenant data.

### Rule 4 — Cross-Table Integrity

Certain relationships cannot be enforced at the database level alone in a shared-schema design. The application layer must validate cross-table tenant consistency:

| Relationship | Risk | Enforcement |
|---|---|---|
| `menu_items.category_id` → `categories.id` | category could belong to different cafe | Application validation |
| `cafe_users.role_id` → `roles.id` | role could belong to different cafe | Application validation |
| `orders.branch_id` → `branches.id` | branch could belong to different cafe | Application validation |
| `cafe_users.branch_id` → `branches.id` | branch could belong to different cafe | Application validation |

These must be enforced in Form Requests and/or Policy checks before any write operation.

### Rule 5 — Super Admin Isolation

The Super Admin operates at the platform level, not within any cafe tenant context.

The Super Admin:

- Accesses the platform administration interface through a separate route prefix (`/admin/...`)
- Does **not** go through the cafe-based tenant resolution middleware
- Can view cafe metadata and subscription information
- Can inspect tenant data only through explicit, audited Super Admin actions — never through the standard tenant API

---

## Role and Permission Scoping

### Platform Roles

Platform roles apply to BrewOS Super Admins:

```
roles
├── cafe_id = NULL
└── scope = platform
```

Example: `super-admin`

### Tenant Roles

Tenant roles apply within a specific cafe:

```
roles
├── cafe_id = {specific cafe id}
└── scope = tenant
```

Examples: `cafe-owner`, `manager`, `cashier`, `waiter`, `kitchen-staff`

### Role Assignment Constraint

A `cafe_users` membership must only be assigned a role belonging to the same cafe, or a platform-level role where applicable.

```
cafe_users.cafe_id = roles.cafe_id (for tenant roles)
or
roles.cafe_id = NULL and roles.scope = 'platform' (for platform roles)
```

---

## Future Migration Path

The shared-schema design is intentional for Phase 1. The architecture does not prevent future migration to:

- **Per-tenant schemas** — if regulatory requirements or enterprise contracts require strict schema-level isolation
- **Per-tenant databases** — if performance at extreme scale requires physical separation
- **Read replicas** — to offload heavy read workloads

These migrations should only be pursued when actual measured workload or contractual requirements justify the operational complexity.

---

## Consequences

### Positive

- Low operational cost and complexity in Phase 1
- Single database to manage, backup, and monitor
- Simple deployment model
- Well-understood pattern for Laravel SaaS applications

### Negative / Risks

- Tenant isolation depends on correct application-layer enforcement
- A bug in query scoping could expose cross-tenant data
- A dedicated test suite for tenant isolation is required
- All developer-contributed queries must be reviewed for tenant context

### Mitigation

- Global `TenantScope` on all tenant-owned models
- Middleware-enforced tenant context resolution
- Laravel Policies for all resource authorization
- Automated test suite verifying tenant isolation for all API endpoints
- Code review requirement for any query that bypasses the global scope

---

## Rejected Alternatives

### Separate Database per Tenant

Rejected because:
- High operational cost at early stage
- Complex provisioning and migration management
- Not appropriate for Phase 1 scale

### Microservices with Per-Service Tenancy

Rejected because:
- Premature complexity for the current team and scale
- Violates the modular monolith principle adopted in ADR-002
- Can be introduced later if specific services warrant isolation
