# BrewOS — Project Architecture Review

**Date:** 2026-08-08
**Reviewed By:** Antigravity (Architecture Review Pass)
**Status:** Awaiting Decision-Maker Approval Before Implementation

---

## Overview

This document is a structured review of all existing BrewOS documentation.
It identifies what has been finalized, what is missing, contradictions between documents, database relationship issues, tenant isolation gaps, scalability concerns, security concerns, and the blockers that must be resolved before implementation begins.

This document does **not** propose fixes. It records the current state and marks unresolved items as **Needs Decision**.

---

## 1. Documents Reviewed

| Document | Status |
|---|---|
| `docs/01-product/ProductVision.md` | ✅ Substantive content present |
| `docs/01-product/RolePermissionMatrix.md` | ✅ Substantive content present |
| `docs/01-product/ProductRequirements.md` | ⚠️ Empty file |
| `docs/01-product/UserRoles.md` | ⚠️ Empty file |
| `docs/01-product/FeatureRoadmap.md` | ⚠️ Empty file |
| `docs/01-product/SaaSPlans.md` | ⚠️ Empty file |
| `docs/02-ui/CafeAdminUI.md` | ⚠️ Empty file |
| `docs/02-ui/CustomerUI.md` | ⚠️ Empty file |
| `docs/02-ui/DesignSystem.md` | ⚠️ Empty file |
| `docs/02-ui/NavigationArchitecture.md` | ⚠️ Empty file |
| `docs/02-ui/StaffUI.md` | ⚠️ Empty file |
| `docs/02-ui/SuperAdminUI.md` | ⚠️ Empty file |
| `docs/03-database/DatabaseArchitecture.md` | ✅ Substantive content present |
| `docs/03-database/DatabaseSchema.md` | ✅ Substantive content present |
| `docs/03-database/ERDiagram.md` | ✅ Substantive content present |
| `docs/04-api/APIArchitecture.md` | ⚠️ Empty file |
| `docs/04-api/APIConventions.md` | ⚠️ Empty file |
| `docs/04-api/AuthenticationAPI.md` | ⚠️ Empty file |
| `docs/05-roadmap/MVP.md` | ⚠️ Empty file |
| `docs/05-roadmap/DevelopmentRoadmap.md` | ⚠️ Empty file |
| `docs/05-roadmap/FutureFeatures.md` | ⚠️ Empty file |
| `docs/07-decisions/ADR-001-Multi-Tenant-Architecture.md` | ⚠️ Empty file |
| `docs/07-decisions/ADR-002-Technology-Stack.md` | ⚠️ Empty file |
| `docs/07-decisions/ADR-003-Scalability-Strategy.md` | ⚠️ Empty file |
| `docs/07-decisions/ADR-004-Security-Architecture.md` | ⚠️ Empty file |
| `docs/07-decisions/ADR-005-Database-Strategy.md` | ⚠️ Empty file |
| `docs/08-research/CompetitorResearch.md` | ⚠️ Empty file |
| `docs/08-research/SaaSResearch.md` | ⚠️ Empty file |
| `docs/08-research/ScalabilityResearch.md` | ⚠️ Empty file |
| `backend/` | ⚠️ Empty directory — no code exists yet |

**Summary:** Of 29 non-root documents reviewed, 3 have substantive content. 26 are empty files or empty directories.

---

## 2. What Architecture Has Already Been Finalized

The following decisions are clearly stated and consistent across the three substantive documents.

### 2.1 Product Identity

- BrewOS is a **multi-tenant SaaS platform** for cafes and food businesses.
- Platform hierarchy: **BrewOS Platform → Cafe (Tenant) → Branch → Operations**.
- Business model: **subscription-based SaaS** with tiered plans (Starter, Professional, Enterprise).

### 2.2 Multi-Tenancy Model

- **Shared-database, shared-schema** multi-tenant architecture (single MySQL database, all tenants share tables).
- Each cafe is a tenant, identified by `cafe_id`.
- Tenant isolation enforced through middleware, authorization policies, model/query scopes, and database constraints.
- Frontend hiding is explicitly excluded as a security mechanism.

### 2.3 Database Decisions

- **MySQL 8+** as the relational database.
- All primary keys: `BIGINT UNSIGNED AUTO_INCREMENT`.
- All monetary values: `DECIMAL(12,2)`. FLOAT and DOUBLE explicitly prohibited for money.
- Laravel Eloquent timestamps (`created_at`, `updated_at`) on all tables.
- Default timezone: `Asia/Kolkata`.
- Default currency: `INR`.
- Both timezone and currency are configurable and must not be hardcoded.

### 2.4 Core Tables Defined

The following tables are fully specified in `DatabaseSchema.md` with column types, nullability, defaults, and key annotations:

`users`, `roles`, `permissions`, `role_permission`, `cafes`, `cafe_users`, `branches`, `customers`, `categories`, `menu_items`, `restaurant_tables`, `orders`, `order_items`, `payments`, `invoices`, `invoice_settings`, `plans`, `plan_features`, `subscriptions`, `audit_logs`

### 2.5 Authorization System

- **Role-Based Access Control (RBAC)**: users receive roles, roles contain permissions.
- No hardcoded role checks (`if ($user->role == 'admin')` explicitly prohibited).
- Roles have a `scope` column: `platform` (Super Admin) or `tenant` (cafe-level roles).
- Platform roles have `cafe_id = NULL`. Tenant roles have `cafe_id = specific_cafe_id`.
- Pre-defined roles: Super Admin, Cafe Owner, Manager, Cashier, Waiter, Kitchen Staff.
- Permission naming convention established: `resource.action` format (e.g., `menu.view`, `order.create`).

### 2.6 Key Relationship Decisions

- Users are **global identities** — a user can belong to multiple cafes via `cafe_users`.
- `cafe_users` links users, cafes, and roles; determines tenant membership.
- `branch_id` on `cafe_users` is optional — used for operational branch assignment.
- Customers are **separate** from administrative users (different table, different auth path).
- `order_items.unit_price` stores **price at time of order** to preserve historical pricing.
- Subscriptions retain **historical records** instead of being overwritten.
- Orders can have **multiple payment records** (split payment support designed in).
- Orders have a **one-to-one relationship** with invoices at MVP.

### 2.7 Delete Strategy (Partially Defined)

- Historical records (orders, payments, invoices, subscriptions, audit logs) must **not** be physically deleted.
- Soft deletes indicated for: cafes, menu items, categories, customers, users.
- Cascade deletion permitted for non-historical pivot data (e.g., `role_permission` when a role is deleted).

### 2.8 Scalability Intent

- Current: single shared database.
- Future path: read replicas → partitioning → sharding → per-tenant databases.
- Redis intended for: caching, queues, rate limiting, temporary data.
- Background jobs intended for: invoice generation, emails, reports, bulk operations.
- Horizontal scaling designed in (stateless application servers behind load balancer).

### 2.9 Performance Principles

- Avoid N+1 queries.
- Use pagination for large datasets.
- Use eager loading.
- Use database-side aggregation.
- Composite index example documented: `INDEX(cafe_id, created_at)` on `orders`.

### 2.10 Security Principles (Stated, Not Specified)

The following are stated as requirements in `ProductVision.md`:
Authentication, authorization, tenant isolation, CSRF protection, input validation, secure password handling, rate limiting, secure session handling, secure file uploads, environment-based secrets, audit logging, proper database constraints.

---

## 3. What Decisions Are Still Missing

### 3.1 Technology Stack — Needs Decision

- All five ADR files are empty.
- `ADR-002-Technology-Stack.md` is completely blank.
- No documented decision exists for:
  - Backend framework (Laravel is implied by references to "Laravel migrations" and "Eloquent timestamps" in the schema docs — but never formally decided in any ADR)
  - Frontend framework (Inertia.js? Blade? Livewire? Vue? React? Not documented)
  - API type (REST? GraphQL? Not documented)
  - Authentication library (Sanctum? Passport? Fortify? Not documented)
  - Queue driver (Redis? Database? Not documented)
  - Cache driver (Redis? Memcached? Not documented)
  - File storage (local? S3-compatible? Not documented)
  - Deployment infrastructure (VPS? Cloud? Docker? Not documented)
  - PHP version requirement (Not documented)

### 3.2 Authentication Architecture — Needs Decision

- `docs/04-api/AuthenticationAPI.md` is empty.
- No decision documented for:
  - Session-based authentication vs. token-based (Sanctum vs. Passport)
  - How customers authenticate for QR ordering (session? anonymous token? not specified)
  - Email verification flow
  - Password reset flow
  - Multi-device session handling
  - Whether Super Admin uses the same authentication table as cafe staff

### 3.3 API Architecture — Needs Decision

- `docs/04-api/APIArchitecture.md` and `docs/04-api/APIConventions.md` are both empty.
- No decision documented for:
  - URL structure for tenant resolution (subdomain routing? path prefix `/api/{cafe_slug}/`? header-based?)
  - API versioning strategy
  - Response envelope format (e.g., `{ data, meta, errors }`)
  - Pagination format
  - Error response format
  - Rate limiting strategy per plan tier

### 3.4 Tenant Context Resolution — Needs Decision

- How the application resolves the current tenant context at request time is not documented.
- The database schema uses `cafe_id` columns for isolation, but the mechanism for injecting the tenant context into the application (middleware, subdomain, URL path, JWT claim, etc.) is not specified.
- This is a critical architectural decision that affects routing, middleware, and model scopes.

### 3.5 QR Code Ordering Flow — Needs Decision

- QR ordering is listed as an MVP feature.
- `restaurant_tables.qr_token` is defined in the schema.
- However, the customer-facing ordering flow is not documented:
  - Does a scanned QR code lead to a public URL? What does the URL look like?
  - How is the table session maintained on the customer device?
  - Can the same table token be scanned by multiple customers simultaneously?
  - Who generates/regenerates the `qr_token`? What is the regeneration policy?
  - Is payment handled within the QR flow or separately?

### 3.6 Subscription Enforcement — Needs Decision

- `plan_features` defines configurable limits (e.g., `staff_limit`, `table_limit`).
- No documented decision on:
  - Where/how plan limits are enforced in the application (middleware? service class? model observer?)
  - What happens when a cafe exceeds a limit mid-subscription (graceful degradation? hard block?)
  - What happens when a subscription expires (immediate lockout? grace period?)
  - How trial periods are handled in the subscription lifecycle

### 3.7 Order Number Generation — Needs Decision

- `orders.order_number` is documented as human-readable (e.g., `ORD-20260808-0001`).
- No documented decision on:
  - Whether the sequence is per-cafe, per-branch, or global
  - How race conditions during concurrent order creation are handled (database sequence? Redis counter? DB advisory lock?)
  - Whether order numbers reset daily, monthly, or never

### 3.8 Invoice Number Generation — Needs Decision

- `invoices.invoice_number` has a `UNIQUE(cafe_id, invoice_number)` constraint.
- Same open questions as order numbers: sequence scope, race condition handling, reset policy.

### 3.9 Soft Delete Implementation — Needs Decision

- Soft deletes are indicated for several tables but not formally decided per table.
- No documentation on:
  - Whether Laravel's built-in `SoftDeletes` trait will be used
  - Whether soft-deleted records should appear in any queries
  - How soft-deleted records affect foreign key relationships (e.g., soft-deleted menu item still referenced in active `order_items`)
  - Retention period before permanent deletion

### 3.10 Cafe Registration and Onboarding Flow — Needs Decision

- Cafe registration is listed as an MVP feature.
- No documented decision on:
  - Is registration self-service or does it require Super Admin approval?
  - `ProductVision.md` says Super Admin can "approve cafes" suggesting manual approval — but the registration flow is not specified
  - Does a default branch get automatically created on registration?
  - Does a default `invoice_settings` record get created on registration?
  - What is the onboarding step sequence?

### 3.11 Payment Gateway Integration — Needs Decision

- Razorpay is mentioned as an example provider.
- No documented decision on:
  - Which payment gateway is used for MVP
  - Whether customer-facing payments (QR ordering) use the same gateway as SaaS subscription billing
  - Webhook handling architecture for payment events
  - Refund flow

### 3.12 MVP Scope — Needs Decision

- `docs/05-roadmap/MVP.md` is empty.
- `ProductVision.md` lists 18 MVP features, but no acceptance criteria, no feature priorities, and no definition of "done" for any feature.

### 3.13 Development Roadmap — Needs Decision

- `docs/05-roadmap/DevelopmentRoadmap.md` is empty.
- No phasing, milestones, or sprint breakdown exists.

### 3.14 UI and Design System — Needs Decision

- All six UI documentation files are empty.
- No documented decision on:
  - Frontend framework
  - Component library
  - Layout system
  - Navigation architecture
  - Responsive breakpoints
  - URL routing structure

---

## 4. Contradictions Between Existing Documents

### 4.1 Roles Table — `cafe_id` Column Presence

**Contradiction between `DatabaseArchitecture.md` and `DatabaseSchema.md`.**

`DatabaseArchitecture.md` (Section 4 — Roles) defines the `roles` table columns as:

```
id | name | slug | scope | created_at | updated_at
```

No `cafe_id` column is present in the architecture document.

`DatabaseSchema.md` (Section 3 — Roles) defines the `roles` table columns as:

```
id | cafe_id | name | slug | scope | created_at | updated_at
```

`cafe_id` is present in the schema document with `FK, INDEX` and nullable.

**Finding:** The architecture doc omits `cafe_id` from the roles table. The schema doc includes it. The ERD and data integrity rules in `DatabaseSchema.md` depend on this column existing. The schema document is internally consistent; the architecture document is incomplete and inconsistent with the schema.

### 4.2 `invoice_settings` — "Normally One" vs. Hard UNIQUE Constraint

**Tension between `ERDiagram.md`, `DatabaseArchitecture.md`, and `DatabaseSchema.md`.**

`ERDiagram.md` states:

> A cafe normally has one active invoice configuration.
> Cafe → Invoice Settings: 1:1

`DatabaseArchitecture.md` states:

> There should **normally** be one active invoice configuration per cafe.

`DatabaseSchema.md` defines `invoice_settings.cafe_id` as `UNIQUE`, which enforces a **strict** one-to-one at the database level.

The word "normally" in both narrative documents implies the constraint is a soft intention. The `UNIQUE` constraint in the schema enforces it as a hard database rule. If the product later requires per-branch invoice settings, this UNIQUE constraint must be dropped and redesigned.

**Finding:** The intent (strict one-to-one vs. soft expectation with future flexibility) is inconsistent across documents. The database enforces hard one-to-one while the narrative implies it could change.

### 4.3 Order → Invoice Relationship Cardinality

**Contradiction within `ERDiagram.md`.**

Section 8 states:

> Initial MVP relationship: orders has one invoices (1:1)

But the same section also states:

> The system may later support invoice revisions or credit notes without changing the original order.

The `invoices` table in `DatabaseSchema.md` defines `order_id` as `FK, INDEX` — not `UNIQUE`. This means the database technically permits multiple invoices per order.

**Finding:** The ER diagram documents a 1:1 relationship, but the schema allows 1:N at the database level. These are contradictory. No decision has been made about how invoice revisions or credit notes will be represented without breaking the MVP 1:1 assumption.

### 4.4 Permission Naming Convention — Inconsistency in Own Examples

`RolePermissionMatrix.md` (Section 10) establishes the convention as `resource.action`.

But the same document (Section 15) uses inconsistent examples:

- `staff.manage` — aggregate action not in the standard CRUD vocabulary
- `menu.manage` — same issue
- `order.manage` — same issue
- `kitchen.view` — `kitchen` is a new resource not listed in the permissions table in the same document
- `kitchen.order_status_update` — compound action name inconsistent with `resource.action` format

`DatabaseArchitecture.md` (Section 5) uses clean examples: `menu.view`, `menu.create`, `order.cancel`, etc.

**Finding:** The permission naming convention is stated but not consistently applied within its own document. Aggregate permissions (`*.manage`), the `kitchen` resource, and compound action names are undefined relative to the stated format. No canonical permission list exists anywhere in the project.

---

## 5. Database Relationship Inconsistencies

### 5.1 Cross-Tenant Risk: `menu_items.category_id → categories.id`

`menu_items.category_id` references `categories.id`. Both tables have independent `cafe_id` columns. No database-level composite foreign key constraint ensures that a menu item's `category_id` belongs to the same cafe.

`DatabaseSchema.md` (Section 26) acknowledges this:

> A menu item category must belong to the same cafe as the menu item.
> This must be enforced at the application authorization/validation layer.

**Finding:** Cross-tenant category assignment is possible at the database level if application-layer validation fails. The specific enforcement mechanism (Laravel policy? service class? form request?) has not been specified.

### 5.2 Cross-Tenant Risk: `cafe_users.role_id → roles.id`

`cafe_users.role_id` references `roles.id` without a database-level constraint ensuring the role belongs to the same cafe as the membership.

`DatabaseSchema.md` (Section 26) acknowledges this:

> A cafe membership role must belong to the same cafe.

**Finding:** Same risk pattern. A misconfigured role assignment could grant a user from Cafe A a role belonging to Cafe B. The enforcement mechanism is not specified.

### 5.3 Cross-Tenant Risk: `orders.branch_id → branches.id`

`orders.branch_id` references `branches.id`, but a branch belongs to a specific cafe via `branches.cafe_id`. A branch from Cafe B could theoretically be assigned to an order in Cafe A at the database level.

`DatabaseSchema.md` (Section 26) acknowledges this:

> An order branch must belong to the order's cafe.

**Finding:** Same enforcement gap. Application-layer validation is required but the mechanism is not specified.

### 5.4 Undocumented Risk: `cafe_users.branch_id` Cross-Tenant

`cafe_users.branch_id` links a user's membership to a branch. No database-level constraint ensures the branch belongs to the same cafe as the membership. This risk is not called out anywhere in the existing documentation.

**Finding:** Undocumented cross-tenant risk. Must be enforced at the application layer.

### 5.5 `restaurant_tables` Has No Direct `cafe_id`

`restaurant_tables` has only `branch_id`. It has no direct `cafe_id` column.

To query a tenant's tables, the application must always join through `branches`.

**Finding:** This is a deliberate design choice (per the architecture principle that not every table needs `cafe_id`), but it means the standard `cafe_id`-based global scope cannot be applied to `restaurant_tables`. Every query against this table must join through `branches` to enforce tenant isolation. This constraint is not documented for implementers.

### 5.6 `order_items` Has No Direct `cafe_id`

`order_items` has only `order_id`. It has no `cafe_id` or `branch_id`.

To scope order items to a tenant, the application must always join through `orders`.

**Finding:** Same pattern as `restaurant_tables`. Deliberate but undocumented constraint for query scope implementation.

### 5.7 `audit_logs` Polymorphic Pattern Not Specified

`audit_logs.entity_type` (`VARCHAR(100)`) and `audit_logs.entity_id` (`BIGINT UNSIGNED`) form a polymorphic reference. No decision has been made about:

- Whether this uses Laravel's polymorphic conventions (e.g., `App\Models\Order`)
- How `entity_type` values will be formatted (full class path vs. short slug vs. table name)
- Whether audit logging will be automatic (model observer) or manual (explicit calls)

**Finding: Needs Decision.**

---

## 6. Tenant Isolation Concerns

### 6.1 No Documented Global Scope Strategy

The documentation repeatedly states tenant isolation will be enforced through "model/query scopes" but does not specify:

- Whether a `GlobalScope` will be applied to all tenant-owned models automatically
- Whether scope enforcement is automatic or manual per query
- What happens if a developer forgets to apply the tenant scope on a specific query
- Whether there is a test strategy to catch tenant isolation failures

**Needs Decision:** The enforcement mechanism must be specified before implementation begins.

### 6.2 Super Admin Access to Tenant Data

The Super Admin has platform-level access and can manage cafes. However, it is not specified:

- Can the Super Admin view a specific cafe's orders, customers, or invoices?
- If yes, how does the Super Admin bypass tenant isolation safely?
- If no, how does the Super Admin investigate support tickets requiring inspection of tenant data?

**Needs Decision:** Super Admin access model for tenant-owned data is not defined.

### 6.3 Customer Session and Data Isolation

Customers interact with the QR ordering interface. The documentation does not define:

- Whether a customer can see their own order history across sessions
- How a customer's orders are associated to them (session token? registered account? phone number?)
- Whether two customers at the same table can see each other's active orders

**Needs Decision:** Customer session and data isolation model is not specified.

### 6.4 Shared `roles` and `permissions` Table Scope Filtering

Platform roles and tenant roles coexist in the same `roles` table. Queries against `roles` must always filter by `scope` or `cafe_id` to prevent platform roles from appearing in tenant contexts and vice versa. The enforcement of this filter is not documented.

**Finding:** This is an implicit requirement that could easily be missed during implementation. No global scope or documented query convention prevents a tenant from receiving platform role data.

---

## 7. Scalability Concerns

### 7.1 Sequential Number Generation — Race Condition Risk

Human-readable sequential numbers (`ORD-20260808-0001`, invoice numbers) are defined in the schema but no implementation strategy exists.

In a multi-instance deployment, naive `MAX(order_number) + 1` logic will produce duplicate numbers under concurrent load.

**Needs Decision:** A safe sequential ID generation strategy must be decided before implementation. Options include: database sequence, Redis atomic counter, table-level advisory lock, or a UUID-based approach that abandons the sequential format.

### 7.2 Subscription Limit Enforcement — No Caching Strategy

Enforcing `plan_features` limits requires reading the cafe's active subscription and features on every relevant request. No caching strategy for this data is documented.

**Needs Decision:** Subscription feature data caching strategy not specified.

### 7.3 Audit Logs — No Archival or Retention Strategy

`audit_logs` is append-only. In a multi-tenant SaaS with active usage, this table will grow very large. No archival, partitioning, or purge strategy is documented.

**Needs Decision:** Audit log retention and archival policy not specified.

### 7.4 QR Token Generation Strategy

`restaurant_tables.qr_token` has a global `UNIQUE` constraint (correct for security). The token generation strategy is not documented: UUID? Signed token? Short alphanumeric code? Token regeneration policy?

**Needs Decision.**

### 7.5 Large Tenant Data — No Partitioning Plan

No database partitioning strategy is documented for high-volume tables (`orders`, `order_items`, `audit_logs`). The documentation acknowledges partitioning as a future option but provides no trigger criteria or migration plan.

**Acceptable gap for MVP** — but must be acknowledged as a known future risk.

---

## 8. Security Concerns

### 8.1 ADR-004-Security-Architecture.md Is Empty

The dedicated security architecture decision record is completely blank. Security requirements are stated as a bulleted list of intentions in `ProductVision.md`. No formal security design exists.

**Needs Decision:** A security architecture document must be written before implementation begins.

### 8.2 Authentication Mechanism Not Specified

Not documented whether BrewOS will use Laravel Sanctum, Passport, Fortify, or a combination. This affects how staff tokens, customer tokens, and Super Admin sessions are managed and separated.

**Needs Decision.**

### 8.3 Rate Limiting Strategy Not Defined

`ProductVision.md` lists rate limiting as a security requirement. No strategy is documented for:

- Login endpoint rate limiting
- API rate limiting per authenticated user
- QR ordering endpoint rate limiting (public-facing endpoint)
- Per-plan API rate limits

**Needs Decision.**

### 8.4 File Upload Security Not Specified

File uploads are implied (cafe logos, menu item images, invoice logos). No documentation exists for:

- Allowed file types and size limits
- Storage location (local disk vs. object storage)
- File name sanitization strategy
- How uploaded files are served (public URL vs. signed URL vs. proxied)

**Needs Decision.**

### 8.5 Password Policy Not Defined

No password policy is documented: minimum length, complexity requirements, bcrypt cost factor, etc.

**Needs Decision.**

### 8.6 Sensitive Data in Audit Logs

`audit_logs` stores `old_values` and `new_values` as JSON. No exclusion list is documented for sensitive fields.

**Concern:** If a field containing sensitive data (e.g., a payment reference, a password hash) is changed, the audit log could inadvertently capture that data in plaintext JSON.

**Needs Decision:** Which fields must be excluded from audit log capture.

### 8.7 `provider_subscription_id` Storage

`subscriptions.provider_subscription_id` stores the external payment provider's subscription ID as plain `VARCHAR(255)`. This is likely acceptable (provider subscription IDs are reference identifiers, not secrets), but this assumption has not been formally confirmed.

**Needs Decision: Confirm this is not a sensitive credential requiring encryption at rest.**

### 8.8 CSRF Protection Scope

CSRF protection is listed as a requirement. With stateless token authentication (Sanctum), CSRF tokens are not required for API routes. With session-based authentication, they are. The CSRF strategy depends entirely on the authentication architecture decision.

**Needs Decision: Follows from B-02 (authentication architecture).**

---

## 9. What Must Be Finalized Before Implementation Begins

The following items are blockers. Implementation must not begin on any feature until these are resolved.

| # | Blocker | Priority |
|---|---|---|
| B-01 | Technology stack decision — backend framework, frontend framework, API type, auth library | Critical |
| B-02 | Authentication architecture — session vs. token, customer auth flow, Super Admin auth separation | Critical |
| B-03 | API architecture — URL structure, tenant resolution mechanism, response format, versioning | Critical |
| B-04 | Tenant context resolution — how the application resolves the active cafe from a request | Critical |
| B-05 | QR ordering flow — full customer journey, table session model, concurrent customer handling | Critical |
| B-06 | Order number and invoice number generation — race-condition-safe strategy | High |
| B-07 | Subscription enforcement — limit enforcement mechanism, expiry behavior, grace periods | High |
| B-08 | Tenant scope enforcement mechanism — global scope vs. manual scope, test strategy | High |
| B-09 | Super Admin access to tenant data — access model and bypass mechanism | High |
| B-10 | Security architecture document — rate limiting, file uploads, password policy, session handling | High |
| B-11 | Soft delete finalization — per-table decision, effect on active foreign key references | Medium |
| B-12 | Audit log implementation pattern — polymorphic model conventions, sensitive field exclusions | Medium |
| B-13 | MVP scope document — feature-level acceptance criteria and definition of done | Medium |
| B-14 | Complete canonical permission list — all permissions used in the system | Medium |
| B-15 | Invoice settings scope — one-per-cafe (strict UNIQUE) vs. future one-per-branch flexibility | Medium |

---

## 10. Additional Observations

### 10.1 All ADR Files Are Placeholder Shells

Five ADR files exist with correct filenames but are completely empty. The ADR process was started (file structure created) but no ADRs have been written. The ADRs should be populated after the blocking decisions above are resolved.

### 10.2 Stray Conversational Note in DatabaseArchitecture.md

`DatabaseArchitecture.md` ends at line 892 with the heading:

> One correction I want you to remember

This reads as a note from a chat session rather than formal documentation. The content (branch-level timezone discussion) is valid and should be preserved, but it should be moved into the appropriate formal section of the document and the conversational framing removed before the document is used as a reference.

### 10.3 Broken Markdown Formatting in DatabaseArchitecture.md

`DatabaseArchitecture.md` contains an unclosed code fence in Section 7 (Cafes — Default Configuration). The opening triple-backtick is present but the closing triple-backtick is missing. This causes all content from Section 8 onward to render as a code block in standard Markdown renderers, making the document difficult to read in any Markdown viewer. The actual content is present but the formatting is broken.

### 10.4 No Backend Code Exists

The `backend/` directory is empty. Implementation has not started. This review is purely architectural and documentary.

### 10.5 `branches.slug` — Application-Layer URL Validation Required

`DatabaseSchema.md` defines `UNIQUE(cafe_id, slug)` for branches (correctly scoped). If the API uses URLs like `/cafes/{cafe_slug}/branches/{branch_slug}`, the application must validate that the `branch_slug` belongs to the resolved cafe. This is not documented as an explicit implementation requirement.

---

## 11. Summary Assessment

| Area | Status |
|---|---|
| Product vision and goals | ✅ Finalized |
| Multi-tenancy model (shared-database) | ✅ Finalized |
| Database engine (MySQL 8+) | ✅ Finalized |
| Core table schema | ✅ Finalized |
| RBAC authorization model | ✅ Finalized |
| Primary key and money column types | ✅ Finalized |
| Historical data preservation | ✅ Finalized |
| Scalability intent and future direction | ✅ Stated, not formally decided |
| Security principles | ✅ Stated, not formally designed |
| Technology stack | ❌ Not decided |
| Authentication mechanism | ❌ Not decided |
| API architecture and conventions | ❌ Not decided |
| Tenant context resolution | ❌ Not decided |
| QR ordering customer flow | ❌ Not decided |
| Order/invoice number generation | ❌ Not decided |
| Subscription enforcement logic | ❌ Not decided |
| Tenant isolation enforcement mechanism | ❌ Not decided |
| File upload architecture | ❌ Not decided |
| UI/frontend architecture | ❌ Not decided |
| MVP scope and acceptance criteria | ❌ Not decided |
| Full canonical permission list | ❌ Not decided |
| Development roadmap and phasing | ❌ Not decided |
| Soft delete per-table decisions | ❌ Not decided |
| Audit log implementation pattern | ❌ Not decided |

---

*End of Architecture Review Report.*
*This document must be reviewed and the identified decisions must be recorded in the appropriate ADR files before implementation begins.*
