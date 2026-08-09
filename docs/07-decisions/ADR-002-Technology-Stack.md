# ADR-002 — Technology Stack

**Status:** Accepted
**Date:** 2026-08-08

---

## Context

BrewOS requires a technology stack that:

- Supports a multi-tenant SaaS architecture
- Is well-suited to rapid development with strong conventions
- Supports a modular monolith structure
- Can support future horizontal scaling
- Has strong community support and ecosystem
- Can serve both an admin-facing application and a customer-facing QR ordering interface

---

## Decision

### Backend

| Component | Technology |
|---|---|
| Framework | Laravel 12 |
| Language | PHP 8.2+ |
| Architecture Pattern | Modular Monolith |

Laravel is chosen for its:

- Mature SaaS and multi-tenant ecosystem
- Built-in authentication, authorization, queues, caching, and events
- Eloquent ORM with model scopes suitable for tenant isolation
- Strong conventions that reduce boilerplate
- First-class Redis, MySQL, and file storage integrations

**PHP version minimum:** 8.2

The application must remain compatible with PHP 8.2+ throughout Phase 1.

### Frontend

| Component | Technology |
|---|---|
| UI Framework | React |
| Language | TypeScript |
| Styling | Tailwind CSS |
| Build Tool | Vite |
| Laravel Integration | Inertia.js |

React with TypeScript is chosen for:

- Component-based architecture suitable for a complex multi-interface SaaS product
- Type safety reducing runtime errors
- Strong ecosystem for building interactive, real-time-ready interfaces
- Compatibility with Inertia.js for server-driven routing without a separate API

Tailwind CSS is chosen for:

- Utility-first approach enabling rapid, consistent UI development
- Excellent compatibility with React and Vite

Vite is chosen for:

- Fast development server and build performance
- First-class Laravel integration via `laravel-vite-plugin`

Inertia.js is chosen for:

- Eliminates the need to build and maintain a separate REST API for the admin-facing application
- Preserves server-side routing, authorization, and middleware from Laravel
- Allows React components to act as page views without a separate SPA authentication layer

### Database

| Component | Technology |
|---|---|
| Primary Database | MySQL 8+ |
| ORM | Laravel Eloquent |

MySQL 8+ is chosen for:

- Proven reliability for relational SaaS workloads
- JSON column support (used for `audit_logs`)
- Full support for foreign keys, transactions, and composite indexes
- Excellent Laravel/Eloquent compatibility

### Cache and Queue

| Component | Technology |
|---|---|
| Cache Driver | Redis |
| Queue Driver | Redis (via Laravel Queue) |
| Queue Workers | Laravel Horizon (or plain `artisan queue:work`) |

Redis is chosen for:

- High-performance in-memory storage for caching
- Reliable queue driver for background job processing
- Rate limiting support via Laravel's built-in Redis rate limiter
- Atomic operations suitable for sequential number generation strategies

### Authentication

| Scope | Mechanism |
|---|---|
| Admin/Staff web sessions | Laravel session-based authentication (web guard) |
| Customer QR ordering | Laravel session or stateless token — decided per QR flow design |
| API/External clients (future) | Laravel Sanctum token authentication |

Session-based authentication is used for the primary admin application because:

- Inertia.js operates on the web guard
- Server-side session management integrates naturally with CSRF protection
- No separate token management overhead for internal users

Sanctum will be available for future external API access or mobile application integration.

### Testing

| Component | Technology |
|---|---|
| Primary Test Framework | Pest (built on PHPUnit) |
| Test Types | Unit, Feature, HTTP (API), Browser (optional) |

Pest is chosen for:

- Expressive, readable test syntax
- Full PHPUnit compatibility
- Strong Laravel integration via Pest Laravel plugin

The test suite must include tenant isolation tests verifying that no tenant API endpoint returns data belonging to a different cafe.

### Infrastructure

| Component | Technology |
|---|---|
| Containerization | Docker |
| Local Development | Docker Compose |
| Production Target | Any container-compatible host (VPS, cloud VM, managed container service) |

Docker is chosen for:

- Consistent development environments across team members
- Portable production deployment
- Foundation for future container orchestration if required

---

## Architecture Pattern: Modular Monolith

BrewOS will be built as a **modular monolith**, not as microservices.

The application is organized into logical modules within a single codebase and deployment unit:

```
app/
├── Modules/
│   ├── Auth/
│   ├── Cafe/
│   ├── Branch/
│   ├── Menu/
│   ├── Order/
│   ├── Payment/
│   ├── Invoice/
│   ├── Staff/
│   ├── Customer/
│   ├── Subscription/
│   ├── Report/
│   └── Platform/       ← Super Admin scope
```

Each module owns its own:

- Controllers
- Services
- Requests (Form Requests)
- Resources (API Resources)
- Policies
- Events and Listeners (where applicable)

This pattern:

- Maintains clear separation of concerns without the operational overhead of microservices
- Allows individual modules to be extracted to separate services in the future if needed
- Keeps deployment simple (single application, single deployment pipeline)

---

## What Is Explicitly Not Adopted in Phase 1

The following technologies and patterns are explicitly deferred:

| Technology / Pattern | Reason |
|---|---|
| Microservices | Premature operational complexity for Phase 1 |
| Kubernetes / container orchestration | Not required at current scale |
| GraphQL | REST + Inertia sufficient for Phase 1 needs |
| Separate mobile app backend (BFF) | Deferred to Phase 2+ |
| Read replicas | Deferred until measured workload requires them |
| Database sharding | Deferred until measured workload requires them |
| Complex event-driven architecture (Kafka, etc.) | Laravel events/queues are sufficient for Phase 1 |

These are deferred, not rejected. The architecture must not introduce coupling that prevents adopting them later.

---

## Consequences

### Positive

- Mature, well-documented stack with strong Laravel SaaS conventions
- Single deployment unit reduces operational complexity
- TypeScript and React provide a robust, maintainable frontend
- Redis provides high-performance caching and reliable queue processing
- Inertia.js avoids maintaining a separate API layer for the admin application

### Negative / Risks

- Modular monolith requires discipline to maintain module boundaries
- As the team grows, module ownership and boundaries must be enforced through code review
- PHP/Laravel may require additional infrastructure tuning for very high concurrency (mitigated by horizontal scaling and queue offloading)

---

## Rejected Alternatives

### Node.js / Express Backend

Rejected: Laravel's built-in multi-tenancy, authorization, queue, and ORM ecosystems provide significantly more out-of-the-box value for this use case.

### Django / Python Backend

Rejected: Team expertise and ecosystem alignment favor Laravel/PHP.

### Vue.js Frontend

Not rejected — Vue is a capable alternative. React + TypeScript was chosen for its broader ecosystem and strong typing support. This decision can be revisited if there is a strong team preference before implementation begins.

### Next.js as the Frontend

Rejected for Phase 1: Introduces a separate deployment unit and server-side rendering complexity that Inertia.js avoids. Can be reconsidered for the customer-facing QR ordering interface in a future phase.
