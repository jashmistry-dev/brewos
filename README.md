# BrewOS

> **Multi-tenant SaaS platform for cafes and food businesses.**

BrewOS provides cafe operators with a complete business management platform: staff management, QR-based ordering, table management, payments, invoices, and subscription billing — all from a single application.

**Architecture:** Shared-database multi-tenant SaaS · Laravel 12 · React + TypeScript · MySQL 8 · Redis · Docker

---

## Table of Contents

- [Requirements](#requirements)
- [Quick Start (Docker)](#quick-start-docker)
- [Environment Configuration](#environment-configuration)
- [Development Workflow](#development-workflow)
- [Available Commands](#available-commands)
- [Project Structure](#project-structure)
- [Documentation](#documentation)

---

## Requirements

| Dependency | Version |
|---|---|
| Docker | 24+ |
| Docker Compose | v2+ |
| PHP (local, optional) | 8.2+ |
| Composer (local, optional) | 2.9+ |
| Node.js (local, optional) | 20 LTS+ |

> All backend and frontend commands can be run inside Docker — local PHP, Composer, and Node.js are optional for development.

---

## Quick Start (Docker)

```bash
# 1. Clone the repository
git clone <repository-url>
cd brewos

# 2. Copy the environment file
cp backend/.env.example backend/.env

# 3. Edit backend/.env — fill in secrets (see Environment Configuration below)

# 4. Build and start all services
docker compose up -d

# 5. Generate the application key
docker compose exec app php artisan key:generate

# 6. Run database migrations
docker compose exec app php artisan migrate

# 7. Start the Vite dev server (separate terminal — dev profile)
docker compose --profile dev up vite

# 8. Visit the application
open http://localhost:8000

# 9. Visit the health check
open http://localhost:8000/health
```

---

## Environment Configuration

Copy `backend/.env.example` to `backend/.env` and configure the following:

### Application

| Variable | Description | Example |
|---|---|---|
| `APP_NAME` | Application name | `BrewOS` |
| `APP_ENV` | Environment | `local` / `production` |
| `APP_KEY` | Encryption key (auto-generated) | `base64:...` |
| `APP_DEBUG` | Debug mode | `true` (local only) |
| `APP_URL` | Application URL | `http://localhost:8000` |

### Database

| Variable | Description | Example |
|---|---|---|
| `DB_HOST` | MySQL host | `mysql` (Docker) |
| `DB_PORT` | MySQL port | `3306` |
| `DB_DATABASE` | Database name | `brewos` |
| `DB_USERNAME` | Database user | `brewos` |
| `DB_PASSWORD` | Database password | (set a strong password) |

### Redis

| Variable | Description | Example |
|---|---|---|
| `REDIS_HOST` | Redis host | `redis` (Docker) |
| `REDIS_PORT` | Redis port | `6379` |
| `REDIS_PASSWORD` | Redis password | (set a strong password) |

### Cache / Queue / Session

| Variable | Description | Recommended |
|---|---|---|
| `CACHE_STORE` | Cache driver | `redis` |
| `QUEUE_CONNECTION` | Queue driver | `redis` |
| `SESSION_DRIVER` | Session driver | `redis` |

---

## Development Workflow

### Start the development environment

```bash
# Start backend services (app, nginx, mysql, redis, queue)
docker compose up -d

# Start Vite dev server (HMR — required for frontend development)
docker compose --profile dev up vite
```

### Stop all services

```bash
docker compose --profile dev down
```

### Run artisan commands

```bash
docker compose exec app php artisan <command>
```

### Run npm commands

```bash
docker compose exec vite npm <command>
```

### Shell access

```bash
# PHP app container
docker compose exec app sh

# MySQL
docker compose exec mysql mysql -u brewos -p brewos
```

---

## Available Commands

### Backend

```bash
# Run tests
docker compose exec app php artisan test

# Run tests (Pest with coverage)
docker compose exec app ./vendor/bin/pest --coverage

# Clear all caches
docker compose exec app php artisan optimize:clear

# Queue worker (already runs as a service, but you can run manually)
docker compose exec app php artisan queue:work

# Check application health
curl http://localhost:8000/health
```

### Frontend

```bash
# Install dependencies (inside Docker)
docker compose exec vite npm install

# Development (Vite HMR)
docker compose --profile dev up vite

# Production build (inside Docker)
docker compose exec vite npm run build

# Type check
docker compose exec vite npx tsc --noEmit
```

### Docker

```bash
# Build all images
docker compose build

# View logs
docker compose logs -f

# View logs for a specific service
docker compose logs -f app
docker compose logs -f queue

# Restart a service
docker compose restart app

# Stop and remove volumes (WARNING: destroys database data)
docker compose down -v
```

### Database

```bash
# Run migrations
docker compose exec app php artisan migrate

# Run migrations with seed
docker compose exec app php artisan migrate --seed

# Rollback last batch
docker compose exec app php artisan migrate:rollback

# Reset and re-run all migrations
docker compose exec app php artisan migrate:fresh
```

---

## Project Structure

```
brewos/
├── backend/                    # Laravel 12 application
│   ├── app/
│   │   ├── Exceptions/         # Exception handling
│   │   ├── Http/
│   │   │   ├── Controllers/    # Route controllers
│   │   │   ├── Middleware/     # HTTP middleware
│   │   │   └── Requests/       # Form request validation
│   │   ├── Models/             # Eloquent models
│   │   ├── Modules/            # Modular monolith modules (Phase 1B+)
│   │   └── Providers/          # Service providers
│   ├── config/                 # Laravel configuration files
│   ├── database/
│   │   ├── factories/          # Model factories
│   │   ├── migrations/         # Database migrations
│   │   └── seeders/            # Database seeders
│   ├── resources/
│   │   ├── js/                 # React + TypeScript frontend
│   │   │   ├── components/     # Reusable UI components
│   │   │   │   └── ui/         # Base UI component library
│   │   │   ├── layouts/        # Layout components
│   │   │   ├── pages/          # Inertia page components
│   │   │   └── types/          # TypeScript type definitions
│   │   └── views/              # Blade templates (Inertia root)
│   ├── routes/
│   │   ├── web.php             # Web routes
│   │   └── api.php             # API routes (Phase 1B+)
│   ├── storage/                # Logs, cache, uploads
│   ├── tests/
│   │   ├── Feature/            # Feature/integration tests
│   │   └── Unit/               # Unit tests
│   ├── .env.example            # Environment template
│   ├── vite.config.ts          # Vite + React configuration
│   └── tsconfig.json           # TypeScript configuration
├── docker/
│   ├── app/                    # PHP-FPM container
│   │   ├── Dockerfile
│   │   └── php.ini
│   ├── nginx/                  # Nginx configuration
│   │   └── default.conf
│   ├── vite/                   # Vite dev server container
│   │   └── Dockerfile
│   └── mysql/                  # MySQL configuration
│       └── my.cnf
├── docs/                       # Architecture documentation
│   ├── 01-product/             # Product vision and RBAC
│   ├── 03-database/            # Database schema
│   ├── 07-decisions/           # Architecture Decision Records
│   └── ...
├── docker-compose.yml          # Docker Compose services
├── CHANGELOG.md
├── README.md
└── ROADMAP.md
```

---

## Documentation

| Document | Description |
|---|---|
| [ProductVision.md](docs/01-product/ProductVision.md) | Product goals and MVP scope |
| [DatabaseSchema.md](docs/03-database/DatabaseSchema.md) | Database table definitions |
| [ADR-001](docs/07-decisions/ADR-001-Multi-Tenant-Architecture.md) | Multi-tenant architecture decision |
| [ADR-002](docs/07-decisions/ADR-002-Technology-Stack.md) | Technology stack decision |
| [ADR-003](docs/07-decisions/ADR-003-Scalability-Strategy.md) | Scalability strategy decision |
| [ADR-004](docs/07-decisions/ADR-004-Security-Architecture.md) | Security architecture decision |
| [ADR-005](docs/07-decisions/ADR-005-Database-Strategy.md) | Database strategy and schema decisions |
| [ARCHITECTURE-REVIEW](docs/07-decisions/PROJECT-ARCHITECTURE-REVIEW.md) | Pre-implementation architecture review |
| [CHANGELOG.md](CHANGELOG.md) | Release history |
| [ROADMAP.md](ROADMAP.md) | Development phases and roadmap |

---

## License

See [LICENSE](LICENSE).
