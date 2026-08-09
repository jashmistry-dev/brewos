# ADR-005 — Database Strategy

**Status:** Accepted
**Date:** 2026-08-08

---

## Context

BrewOS uses a shared-database, shared-schema multi-tenant architecture (per ADR-001). The database strategy must define the rules and conventions that govern the database schema design across the entire platform.

This document resolves four specific contradictions identified in the Architecture Review (`PROJECT-ARCHITECTURE-REVIEW.md`):

1. The `roles` table column list discrepancy (`cafe_id` presence)
2. The `invoice_settings` 1:1 relationship enforcement
3. The `invoices.order_id` uniqueness and 1:1 relationship
4. The permission naming convention inconsistency

---

## Decision

### Database Engine

| Setting | Value |
|---|---|
| Engine | MySQL 8+ |
| Schema approach | Shared database, shared schema |
| ORM | Laravel Eloquent |

### Primary Keys

All tables use:

```sql
BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
```

No UUID primary keys in Phase 1. UUIDs may be used for public-facing tokens (e.g., `qr_token`) but not as primary keys.

### Financial Values

All monetary columns use:

```sql
DECIMAL(12, 2)
```

`FLOAT` and `DOUBLE` are explicitly prohibited for any financial value.

### Timestamps

All tables use Laravel's standard Eloquent timestamps:

```sql
created_at TIMESTAMP NULL
updated_at TIMESTAMP NULL
```

### Default Timezone

```
Asia/Kolkata
```

This is a default. Cafes and branches may override this at the application level. The value must not be hardcoded throughout the application.

### Default Currency

```
INR
```

This is a default. Cafes may override this at the application level. The value must not be hardcoded throughout the application.

---

## Resolved Contradiction 1 — `roles` Table: `cafe_id` Column

### Previous State (Contradiction)

`DatabaseArchitecture.md` (Section 4) listed the `roles` table columns without `cafe_id`.
`DatabaseSchema.md` (Section 3) included `cafe_id` in the `roles` table.

### Decision

The **`DatabaseSchema.md` definition is authoritative**.

The `roles` table **must include `cafe_id`**.

**Authoritative `roles` table definition:**

| Column | Type | Nullable | Default | Key |
|---|---|---|---|---|
| id | BIGINT UNSIGNED | No | Auto Increment | PK |
| cafe_id | BIGINT UNSIGNED | Yes | NULL | FK, INDEX |
| name | VARCHAR(100) | No | — | — |
| slug | VARCHAR(100) | No | — | INDEX |
| scope | VARCHAR(20) | No | tenant | INDEX |
| created_at | TIMESTAMP | Yes | NULL | — |
| updated_at | TIMESTAMP | Yes | NULL | — |

**Business rules:**

- **Platform role:** `cafe_id = NULL`, `scope = 'platform'`
  - Example: `super-admin`
- **Tenant role:** `cafe_id = {specific cafe id}`, `scope = 'tenant'`
  - Examples: `cafe-owner`, `manager`, `cashier`, `waiter`, `kitchen-staff`

A tenant role must belong to the same cafe as the `cafe_users` membership it is assigned to.

`DatabaseArchitecture.md` must be updated to include `cafe_id` in its roles table column list.

---

## Resolved Contradiction 2 — `invoice_settings`: Strict 1:1 with Cafes

### Previous State (Contradiction)

`DatabaseArchitecture.md` and `ERDiagram.md` used the word "normally" (e.g., "There should normally be one active invoice configuration per cafe"), implying the 1:1 relationship was a soft intention.

`DatabaseSchema.md` enforced it as a hard `UNIQUE` constraint on `invoice_settings.cafe_id`.

### Decision

The 1:1 relationship between `cafes` and `invoice_settings` is **strict and enforced at the database level** in Phase 1.

```sql
UNIQUE (cafe_id)
```

on `invoice_settings`.

**Rationale:**

- One invoice configuration per cafe is the correct Phase 1 business rule.
- If per-branch invoice settings are required in a future phase, this constraint will be explicitly revisited in a new ADR at that time.
- The word "normally" in the narrative documents is misleading and must be replaced with definitive language.

**All documents must be updated** to replace "normally" with the definitive rule: **one invoice configuration per cafe, enforced by a database UNIQUE constraint**.

---

## Resolved Contradiction 3 — `invoices`: Strict 1:1 with Orders

### Previous State (Contradiction)

`ERDiagram.md` (Section 8) stated the Order → Invoice relationship is 1:1 for Phase 1.

However, `DatabaseSchema.md` defined `invoices.order_id` as `FK, INDEX` (not `UNIQUE`), allowing multiple invoices per order at the database level.

### Decision

The Order → Invoice relationship is **strict 1:1 in Phase 1**, enforced at the database level.

```sql
UNIQUE (order_id)
```

must be added to the `invoices` table.

**Authoritative `invoices` uniqueness constraint:**

```
UNIQUE(cafe_id, invoice_number)   ← already present
UNIQUE(order_id)                  ← added by this decision
```

**Rationale:**

- Phase 1 requires one invoice per order.
- If invoice revisions or credit notes are introduced in a future phase, the `UNIQUE(order_id)` constraint will be explicitly dropped in a new migration, documented in a new ADR, at that time.
- Keeping the constraint now prevents accidental duplicate invoice creation and makes the system correct by design.

`DatabaseSchema.md` must be updated to add `UNIQUE` to `invoices.order_id`.

---

## Resolved Contradiction 4 — Permission Naming Convention

### Previous State (Contradiction)

`RolePermissionMatrix.md` (Section 10) established the convention as `resource.action`.

`RolePermissionMatrix.md` (Section 15) then violated this convention with:

- `staff.manage`, `menu.manage`, `order.manage` (aggregate actions — not standard CRUD verbs)
- `kitchen.view`, `kitchen.order_status_update` (compound action name, `kitchen` resource undefined in the permissions table)

### Decision

The **canonical permission naming convention is `resource.action`** where:

- `resource` is a lowercase singular noun representing the managed entity
- `action` is a lowercase verb representing the operation

**Standard actions:**

| Action | Meaning |
|---|---|
| `view` | Read one or many records |
| `create` | Create a new record |
| `update` | Modify an existing record |
| `delete` | Remove a record |
| `cancel` | Cancel a record (order-specific) |
| `download` | Download a file (invoice-specific) |

**Aggregate permissions (`*.manage`) are prohibited.** Roles must be defined as combinations of specific granular permissions.

**Authoritative permission list for Phase 1:**

```
cafe.view
cafe.update
cafe.settings.update

branch.view
branch.create
branch.update

staff.view
staff.create
staff.update
staff.delete

role.view
role.create
role.update
role.delete

menu.view
menu.create
menu.update
menu.delete

category.view
category.create
category.update
category.delete

table.view
table.create
table.update
table.delete

order.view
order.create
order.update
order.cancel

order.kitchen.view
order.kitchen.update

customer.view
customer.create
customer.update

payment.view
payment.create

invoice.view
invoice.create
invoice.download
invoice.settings.update

report.view

subscription.view
subscription.update

platform.cafe.view
platform.cafe.create
platform.cafe.update
platform.cafe.suspend
platform.plan.view
platform.plan.create
platform.plan.update
platform.audit.view
```

**Notes:**

- `order.kitchen.view` and `order.kitchen.update` replace the previously undefined `kitchen.*` permissions.
- These represent the Phase 1 canonical list. New permissions must follow the `resource.action` convention and be documented before being added to the codebase.
- `RolePermissionMatrix.md` must be updated to replace all `*.manage` examples and the `kitchen.*` examples with specific permissions from this list.

---

## `cafe_id` Placement Strategy

Not every table requires a direct `cafe_id` column. The strategy is:

### Tables with Direct `cafe_id` (High-Volume / Query Boundary)

These tables are queried directly by tenant context and must carry `cafe_id`:

```
cafes             ← is the tenant
cafe_users        ← cafe_id
branches          ← cafe_id
customers         ← cafe_id
categories        ← cafe_id
menu_items        ← cafe_id
orders            ← cafe_id  (high-volume query boundary)
payments          ← cafe_id  (high-volume query boundary)
invoices          ← cafe_id  (query boundary)
invoice_settings  ← cafe_id
subscriptions     ← cafe_id
audit_logs        ← cafe_id
roles             ← cafe_id (NULL for platform roles)
```

### Tables Without Direct `cafe_id` (Derived Through Parent)

These tables derive tenant ownership through their parent table. They must always be queried through a join to their parent:

```
restaurant_tables → tenant context through branches.cafe_id
order_items       → tenant context through orders.cafe_id
role_permission   → platform-level, no cafe_id needed
plan_features     → platform-level, no cafe_id needed
```

This is a deliberate design choice. Implementers must be aware that standard `cafe_id`-based global scopes cannot be applied directly to these tables.

---

## Foreign Keys and Indexes

All foreign key relationships must be enforced at the database level with `FOREIGN KEY` constraints.

Composite indexes to be created in migrations:

```sql
INDEX (cafe_id, created_at)   -- on orders, payments, invoices
INDEX (cafe_id, status)       -- on orders, subscriptions
INDEX (cafe_id, user_id)      -- on cafe_users
```

Refer to `DatabaseSchema.md` for the full index specification per table.

---

## Delete Strategy

### Append-Only (No Delete)

| Table | Rule |
|---|---|
| `orders` | No delete — historical retention required |
| `order_items` | No delete — historical retention required |
| `payments` | No delete — historical retention required |
| `invoices` | No delete — historical retention required |
| `subscriptions` | No delete — historical retention required |
| `audit_logs` | No delete — append-only, integrity requirement |

### Soft Delete (`deleted_at` column)

| Table | Rule |
|---|---|
| `cafes` | Soft delete |
| `users` | Soft delete |
| `menu_items` | Soft delete |
| `categories` | Soft delete |
| `customers` | Soft delete |
| `branches` | Soft delete |

Soft-deleted records must not be returned by standard queries. All models with soft delete must use Laravel's `SoftDeletes` trait.

Foreign key references to soft-deleted records must be reviewed. For example, a soft-deleted `menu_item` may still be referenced by historical `order_items` — this is intentional and correct.

### Cascade Delete

| Relationship | Rule |
|---|---|
| `role` → `role_permission` | Cascade — deleting a role removes its permission assignments |
| `plan` → `plan_features` | Cascade — deleting a plan removes its feature definitions |

Business transaction data must never be cascade-deleted.

---

## Schema as the Source of Truth

`DatabaseSchema.md` is the authoritative database implementation blueprint.

Laravel migrations must follow `DatabaseSchema.md`.

If the schema changes:

1. Update `DatabaseSchema.md`
2. Record the architectural decision in a new ADR (or amend this one)
3. Create or modify the Laravel migration
4. Test the migration
5. Update `ERDiagram.md` if relationships change

Documentation and the actual database schema must remain synchronized at all times.

---

## Consequences

### Positive

- Clear, resolved conventions remove ambiguity before implementation
- Strict 1:1 constraints on `invoice_settings` and `invoices` prevent accidental data integrity errors
- Granular permission list enables precise RBAC without aggregate permissions
- `cafe_id` placement strategy is documented and intentional

### Negative / Risks

- `UNIQUE(invoices.order_id)` will require a migration change if invoice revisions are introduced
- `UNIQUE(invoice_settings.cafe_id)` will require a migration change if per-branch invoice settings are introduced
- Developers must manually enforce cross-table tenant validation since it cannot be done at the database level alone

### Mitigation

- Future schema changes go through ADR process before implementation
- Application-layer cross-table validation is tested through automated integration tests
