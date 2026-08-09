# ADR-003 — Scalability Strategy

**Status:** Accepted
**Date:** 2026-08-08

---

## Context

BrewOS is a SaaS platform that must eventually support a large number of cafes, users, orders, and transactions. The architecture must be designed to scale, but premature optimization adds unnecessary complexity that slows down early-stage development.

A clear scalability strategy is needed to:

- Establish the Phase 1 architecture baseline
- Define what is acceptable for the current stage
- Identify what optimizations are deferred and under what conditions they should be introduced
- Ensure that no Phase 1 decisions structurally prevent future scaling

---

## Decision

### Phase 1 Architecture

The Phase 1 production architecture is:

```
Internet
    ↓
Load Balancer
    ↓
Multiple Laravel Application Instances (stateless)
    ↓
        MySQL 8+
        +
        Redis (Cache + Queue)
        +
        Queue Workers (Laravel Queue)
        +
        Object Storage (for file uploads)
```

Multiple application instances are supported from the start through the stateless application design. The load balancer distributes requests across instances. State is stored externally in MySQL, Redis, and object storage — never in the application server's local memory.

---

## Stateless Application Design

The application servers must be stateless wherever practical:

| What | How |
|---|---|
| Sessions | Stored in Redis or database (not file-based local storage) |
| Cache | Stored in Redis (not in-process memory cache) |
| Queues | Processed by dedicated queue workers via Redis |
| File uploads | Stored in object storage (not on application server local disk) |
| Locks / Sequences | Redis atomic operations or database advisory locks |

This ensures that any application instance can handle any request without requiring session affinity (sticky sessions).

---

## Performance Requirements

The following practices are mandatory in Phase 1:

### Database

| Practice | Requirement |
|---|---|
| Indexes | All foreign keys and common filter columns must be indexed |
| Pagination | All list endpoints must paginate — no unbounded result sets |
| Eager loading | Relations must be eager-loaded to prevent N+1 queries |
| N+1 prevention | N+1 query detection must be enabled in development and test environments |
| Query optimization | Aggregations and counts must be performed at the database layer, not in PHP |
| Transactions | Multi-step write operations must use database transactions |
| Tenant index | `orders`, `payments`, `invoices` must have composite `(cafe_id, created_at)` index for tenant-scoped chronological queries |

### Caching

| Candidate | Cache Strategy |
|---|---|
| Subscription plan features | Cache per cafe, invalidate on subscription change |
| Cafe settings | Cache per cafe, invalidate on settings update |
| Permission data | Cache per user/role, invalidate on role change |
| Menu data | Cache per cafe, invalidate on menu update |
| Dashboard statistics | Short TTL cache where appropriate |

Redis is used as the cache driver. Cache must not compromise data correctness — mutations must invalidate relevant cache entries.

### Background Processing

Long-running or non-blocking operations must be offloaded to queue workers:

| Operation | Queue |
|---|---|
| Invoice generation | `invoices` queue |
| Email notifications | `notifications` queue |
| Report generation | `reports` queue |
| Data exports | `exports` queue |
| Bulk operations | `bulk` queue |
| Audit log writes | Synchronous (audit logs must be reliable) |

Queue workers run as separate processes and must be monitored.

---

## Phase 1 Performance Targets

The following are engineering targets to validate before production launch:

| Metric | Target |
|---|---|
| Common API response time | < 300 ms (p95) |
| Order creation | < 500 ms |
| Customer-facing menu load | < 1.5 seconds |
| Admin dashboard load | < 2 seconds |
| Error rate | < 0.1% |
| Uptime target | 99.9% |

These targets must be validated through load testing before any major production launch or significant architectural change.

---

## What Is Explicitly Deferred

The following are **not** introduced in Phase 1:

| Optimization | Condition for Introduction |
|---|---|
| MySQL read replicas | When measured read latency under production load justifies the operational complexity |
| Database partitioning | When a specific high-volume table (`orders`, `audit_logs`) shows measurable query degradation |
| Database sharding | When tenant count and data volume exceed what a single MySQL instance can serve reliably |
| Per-tenant databases | When contractual, regulatory, or extreme-scale requirements make shared-schema isolation insufficient |
| Kubernetes / container orchestration | When the number of application instances or services requires automated orchestration |
| Complex event-driven architecture (Kafka, event sourcing) | When service count and data flow complexity justify the operational overhead |
| Microservices | When a specific module's scaling requirements are incompatible with the monolith |

None of the above are prohibited for the future. They are deferred until **measured** workload data justifies the added complexity.

> The architecture must leave room for these optimizations without requiring a full rewrite.

---

## Scalability Design Principles

### Measure Before Optimizing

Performance decisions must be based on measured data, not theoretical assumptions:

```
Measure
  → Identify bottleneck
  → Optimize
  → Load test
  → Monitor
```

Do not add caching, indexes, or partitioning speculatively. Add them when profiling shows they are needed.

### Do Not Block Future Scaling

Phase 1 decisions must not introduce structural debt that prevents future scaling:

- Application servers must remain stateless
- Session and cache storage must be externalized from day one
- File storage must use an abstraction layer (Laravel Storage) so the driver can be swapped
- Database queries must be scoped with tenant indexes so partitioning or sharding can be added later without rewriting queries

### Horizontal Scaling First

Before pursuing complex database scaling strategies, pursue horizontal application scaling:

- Add more application server instances behind the load balancer
- Add more queue workers for background processing
- Increase Redis capacity and use Redis clustering if needed

Database scaling is typically the most complex and expensive operation and should be the last resort.

---

## Consequences

### Positive

- Simple, well-understood infrastructure for Phase 1
- Stateless design enables horizontal scaling without application changes
- Redis-based caching and queueing provide significant performance headroom
- Deferred complexity reduces development cost and operational burden

### Negative / Risks

- Single MySQL instance is a potential bottleneck and single point of failure
- No read replicas means all reads compete with writes on one server
- Must ensure proper connection pooling and index design to avoid MySQL bottlenecks at scale

### Mitigation

- Proper indexing strategy documented in ADR-005 and DatabaseSchema.md
- Redis caching reduces database read pressure for hot data
- Database transactions ensure write integrity
- Load testing before production launch validates targets
- Infrastructure monitoring (slow query log, Redis metrics, application performance monitoring) from day one
