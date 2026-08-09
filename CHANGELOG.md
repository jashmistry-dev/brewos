# BrewOS — Changelog

All notable changes to this project will be documented in this file.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [Unreleased]

### Phase 1A — Project Foundation (2026-08-08)

#### Added

**Infrastructure**
- `docker-compose.yml` — Multi-service Docker Compose configuration
- `docker/app/Dockerfile` — PHP 8.2-FPM Alpine container with all required extensions
- `docker/app/php.ini` — PHP configuration overrides (security, OPcache, limits)
- `docker/nginx/default.conf` — Nginx reverse proxy configuration with security headers
- `docker/vite/Dockerfile` — Node.js 20 container for Vite dev server (dev profile)
- `docker/mysql/my.cnf` — MySQL 8 configuration (utf8mb4, InnoDB, slow query log)

**Backend (Laravel 12)**
- Initialized Laravel 12 application in `backend/`
- Configured MySQL 8+ database connection
- Configured Redis for cache, sessions, and queues
- Configured Laravel Queue with Redis driver
- Configured Laravel logging with daily rotation
- Configured Inertia.js server-side adapter
- Configured Vite with React and TypeScript support
- Configured Pest testing framework

**Frontend (React + TypeScript + Tailwind)**
- `backend/resources/js/app.tsx` — Inertia.js application entry point
- `backend/resources/js/types/index.d.ts` — Global TypeScript declarations
- `backend/resources/js/types/inertia.d.ts` — Inertia page props types
- `backend/resources/js/layouts/AppLayout.tsx` — Base application layout
- `backend/resources/js/layouts/NavigationShell.tsx` — Navigation shell component
- `backend/resources/js/components/ui/Button.tsx` — Button component
- `backend/resources/js/components/ui/Input.tsx` — Input component
- `backend/resources/js/components/ui/Select.tsx` — Select component
- `backend/resources/js/components/ui/Modal.tsx` — Modal/dialog component
- `backend/resources/js/components/ui/Table.tsx` — Table component
- `backend/resources/js/components/ui/Badge.tsx` — Badge component
- `backend/resources/js/components/ui/Alert.tsx` — Alert component
- `backend/resources/js/components/ui/Toast.tsx` — Toast/notification component
- `backend/resources/js/components/ui/PageContainer.tsx` — Page container component
- `backend/resources/js/components/ui/LoadingState.tsx` — Loading state component
- `backend/resources/js/components/ui/EmptyState.tsx` — Empty state component
- `backend/resources/js/components/ui/ErrorState.tsx` — Error state component
- `backend/resources/js/pages/Health.tsx` — Health check page
- `backend/vite.config.ts` — Vite configuration (React + TypeScript + Inertia)
- `backend/tsconfig.json` — TypeScript configuration
- `backend/tailwind.config.js` — Tailwind CSS configuration

**Backend Routes and Health**
- `GET /health` — Application health check endpoint
- `GET /` — Root page (Inertia)

**Testing**
- Pest configured with PHPUnit backend
- Feature test: `HealthCheckTest.php`

**Documentation**
- `README.md` — Updated with setup and development instructions
- `ROADMAP.md` — Updated with Phase 1A completion status
- `CHANGELOG.md` — This file

#### Changed

- `backend/.env.example` — Complete environment template for all Phase 1A services
- `.gitignore` — Updated to exclude all secrets, build artifacts, and vendor directories

#### Architecture

- Architecture locked per `docs/07-decisions/` ADRs
- No business-logic tables created (Phase 1B onwards)
- No authentication implemented (Phase 1B)
- No tenant resolution implemented (Phase 1B)

---

## [0.0.0] — Architecture Review Complete (2026-08-08)

### Added

- Complete documentation architecture (`docs/`)
- Five Architecture Decision Records (ADR-001 through ADR-005)
- Project Architecture Review report
- Database schema documentation
- Product vision and RBAC documentation
