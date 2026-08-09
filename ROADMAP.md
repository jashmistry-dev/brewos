# BrewOS — Development Roadmap

This document tracks the development phases of the BrewOS platform.

Architecture is locked per the ADRs in `docs/07-decisions/`.

---

## Phase 1 — Foundation

### ✅ Phase 0 — Architecture Documentation
*Completed: 2026-08-08*

- [x] Product Vision
- [x] Role & Permission Matrix
- [x] Database Architecture
- [x] Database Schema
- [x] ER Diagram
- [x] ADR-001 Multi-Tenant Architecture
- [x] ADR-002 Technology Stack
- [x] ADR-003 Scalability Strategy
- [x] ADR-004 Security Architecture
- [x] ADR-005 Database Strategy
- [x] Architecture Review and consistency check

---

### ✅ Phase 1A — Project Foundation
*Completed: 2026-08-08*

- [x] Laravel 12 application initialization
- [x] MySQL configuration
- [x] Redis configuration
- [x] Queue infrastructure (Redis + Laravel Queue)
- [x] Cache configuration
- [x] Session configuration (Redis-backed)
- [x] Vite + React + TypeScript configuration
- [x] Inertia.js integration
- [x] Docker environment (app, nginx, mysql, redis, queue, vite)
- [x] `.env.example` complete template
- [x] `.gitignore` updated
- [x] Base frontend component library (Button, Input, Select, Modal, Table, Badge, Alert, Toast, Loading, Empty, Error)
- [x] Application layouts (AppLayout, NavigationShell)
- [x] Error handling configured
- [x] Logging configured
- [x] Pest testing framework configured
- [x] Health check endpoint
- [x] README.md, ROADMAP.md, CHANGELOG.md

---

### 🔲 Phase 1B — Authentication & Tenant Resolution
*Planned*

- [ ] User model and migration
- [ ] Authentication (Laravel session-based, web guard)
- [ ] Login / logout / email verification / password reset
- [ ] Cafe model and migration
- [ ] Tenant resolution middleware (URL slug → Cafe → inject context)
- [ ] Global TenantScope on tenant-owned models
- [ ] Super Admin authentication (separate route prefix `/admin/`)
- [ ] Auth pages (Login, Forgot Password, Reset Password)
- [ ] Auth tests (tenant isolation, cross-tenant rejection)

---

### 🔲 Phase 1C — Core Tables and RBAC
*Planned*

- [ ] Database migrations for all core tables
  - `users`, `roles`, `permissions`, `role_permission`
  - `cafes`, `cafe_users`
  - `branches`
  - `customers`
  - `categories`, `menu_items`
  - `restaurant_tables`
  - `orders`, `order_items`
  - `payments`, `invoices`, `invoice_settings`
  - `plans`, `plan_features`, `subscriptions`
  - `audit_logs`
- [ ] Eloquent models for all tables
- [ ] RBAC middleware (permission checks via policies)
- [ ] Seed data (default plans, default platform role)

---

## Phase 2 — Core Business Features

### 🔲 Phase 2A — Cafe and Branch Management
*Planned*

- [ ] Cafe registration and onboarding flow
- [ ] Cafe settings management
- [ ] Branch creation and management
- [ ] Staff invitation and management
- [ ] Role assignment for staff

### 🔲 Phase 2B — Menu Management
*Planned*

- [ ] Category management
- [ ] Menu item CRUD
- [ ] Menu item images
- [ ] Menu availability toggles

### 🔲 Phase 2C — QR Ordering
*Planned*

- [ ] Table management
- [ ] QR token generation and regeneration
- [ ] Customer-facing QR ordering interface
- [ ] Order creation from QR scan
- [ ] Kitchen display view
- [ ] Order status updates

### 🔲 Phase 2D — Payments and Invoicing
*Planned*

- [ ] Payment recording (cash, UPI, card)
- [ ] Payment gateway integration (Razorpay or equivalent)
- [ ] Invoice generation (background job)
- [ ] Invoice download (PDF)
- [ ] Invoice settings management

---

## Phase 3 — Platform Administration

### 🔲 Phase 3A — Super Admin Platform
*Planned*

- [ ] Super Admin dashboard
- [ ] Cafe listing and management
- [ ] Subscription plan management
- [ ] Audit log viewer

### 🔲 Phase 3B — Subscription Billing
*Planned*

- [ ] SaaS plan management
- [ ] Subscription lifecycle (trial, active, expired)
- [ ] Plan limit enforcement
- [ ] Subscription billing integration

---

## Phase 4 — Reporting and Analytics

### 🔲 Phase 4A — Basic Reports
*Planned*

- [ ] Sales reports by date range
- [ ] Revenue breakdown
- [ ] Staff performance

### 🔲 Phase 4B — Advanced Analytics
*Planned*

- [ ] Customer behavior analytics
- [ ] Menu performance analytics
- [ ] Peak hour analysis

---

## Future Features (Post Phase 4)

- Multi-currency support
- Customer loyalty program
- Inventory management
- Kitchen display system
- Mobile app (native)
- Multi-language support
- AI-powered menu recommendations

---

## Architecture Notes

The architecture for all phases is locked. See `docs/07-decisions/` for the ADRs.

Do not introduce new architectural patterns without approval and a new ADR.
