<div align="center">

# 🛡️ CompliTrack

**Regulatory Compliance & Accountability System**
*USTP Claveria — College of Engineering and Technology (CET)*

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?logo=laravel&logoColor=white)
![PHP](https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white)
![MongoDB](https://img.shields.io/badge/MongoDB-7.0-47A248?logo=mongodb&logoColor=white)
![Node.js](https://img.shields.io/badge/Node.js-Express%20%2B%20Socket.io-339933?logo=node.js&logoColor=white)
![Flutter](https://img.shields.io/badge/Flutter-3.24%2B-02569B?logo=flutter&logoColor=white)
![Status](https://img.shields.io/badge/status-in%20development-orange)

</div>

---

## Overview

**CompliTrack** is a centralized, secure, and auditable compliance management
system built exclusively for **USTP Claveria CET**. It replaces manual,
paper-based tracking with a single source of truth for regulatory evidence,
audit findings, and corrective actions — covering standards such as the
**ARTA 3/7/20-day rule** and **ISO 9001:2015 Clause 9.2/10.2**.

The system is intentionally scoped to three core functions:

| # | Function | Description |
|---|----------|--------------|
| 1 | 🗄️ **Archive Files** | Secure, encrypted repository (AES‑256‑GCM) for compliance evidence (PDF, DOCX, images). Organized by Department/Office, tagged by reference standard. |
| 2 | ✅ **Check Compliance** | Auditors create a `ComplianceCheck` record marking **Compliant** / **Non‑Compliant** based on archived evidence. |
| 3 | 🔔 **Notify Corrective Action Status** | Non‑compliant checks auto‑create a corrective action (`open → in_progress → closed`) and notify the Auditee in‑app + push on every change. Compliant checks also notify the Auditee once, so a passed check is never silent. |

**Workflow:** Auditor checks → if Non‑Compliant, open CAR → notify Auditee → Auditee uploads fix → Auditor reviews → `in_progress` / `closed` → notify Auditee. If Compliant, notify Auditee immediately with no CAR opened.

---

## Current Status

> **The project is at the scaffold stage.** The folder structure, models, routes,
> role middleware and role seeder are in place. Most controllers, services,
> Blade views, the `realtime/` service and the `mobile/` app are **empty
> placeholders** waiting to be implemented. Features described below are the
> design target, not what is running today — see the [Roadmap](#roadmap) for
> what is actually done.

---

## Table of Contents

- [Current Status](#current-status)
- [Features](#features)
- [Tech Stack](#tech-stack)
- [User Roles & Permissions](#user-roles--permissions)
- [Architecture](#architecture)
- [Installation](#installation)
- [Git Workflow](#git-workflow)
- [Getting Started](#getting-started)
- [Environment Variables](#environment-variables)
- [Database Collections](#database-collections)
- [API Endpoints](#api-endpoints)
- [Folder Structure](#folder-structure)
- [Security](#security)
- [Roadmap](#roadmap)
- [Contributing](#contributing)
- [License](#license)
- [Acknowledgments](#acknowledgments)

---

## Features

- 🔐 Role-based access control across 4 fixed roles (Super Admin, Admin, Auditor, Auditee)
- 📁 Encrypted evidence archive with checksum verification (SHA‑256) and per‑file IV
- 📋 Compliance check creation linked to archived evidence and reference standards
- 🔄 Corrective Action Tracker with a full status timeline (`open → in_progress → closed`)
- 🔔 Real-time in-app + push notifications (Socket.io + Firebase Cloud Messaging)
- 📊 Role-specific dashboards with KPIs, compliance gauges, and trend charts
- 📱 Cross-platform mobile app (Flutter) for all four roles, built around Auditor and Auditee field use
- 🧾 Full audit logging of every write operation (user, action, IP address)
- 🖨️ PDF compliance reports with official USTP Navy/Gold branding

---

## Tech Stack

> Built on top of the [Laravel](https://laravel.com) framework for the backend API — huge thanks to the Laravel team and community for the tooling this project is built on.

| Layer | Technology |
|---|---|
| Backend API | Laravel 12 (PHP 8.2+) — REST API, business logic, role middleware, file encryption |
| Realtime Service | Node.js (Express + Socket.io) — live notifications, status updates, dashboard refresh |
| Mobile App | Flutter 3.24+ (Dart 3.5) — Android/iOS |
| Database | MongoDB 7.0 (`mongodb/laravel-mongodb`) — flexible schema for compliance docs |
| Auth | Laravel Sanctum (Web) · JWT via `tymon/jwt-auth` (Mobile) |
| Storage | Local encrypted disk + optional S3-compatible (MinIO) |
| Push Notifications | Firebase Cloud Messaging (FCM, HTTP v1), dispatched via the Node service |
| Frontend Web | Laravel Blade + Tailwind CSS 4 (via Vite) + Alpine.js |
| Icons | [Lucide Icons](https://lucide.dev) — single icon library across Web & Mobile |

---

## User Roles & Permissions

| RoleId | Role | Scope | Key Permissions |
|---|---|---|---|
| `0` | **Super Admin** | System-wide | Full access: manage users, roles, departments, system logs, backups; cannot create compliance checks |
| `1` | **Admin** (CET Admin) | CET College only | Manage departments and auditor/auditee accounts, read-only archive access, view reports |
| `2` | **Auditor** | Assigned departments | Create/update compliance checks, read/write archive, change corrective status |
| `3` | **Auditee** (Office Head/Faculty) | Own office only | View own compliance status, upload evidence, view notifications |

> Role is determined **only** by the integer `roleId` field — never inferred from `userId`.

---

## Architecture

```
Client (Web Blade + Flutter App)
        │
        ▼
Laravel API Gateway (PHP)  ──────►  MongoDB + Encrypted Storage
        │
        ▼
Node.js microservice (watches MongoDB change streams)
        │
        ├──► Socket.io (live in-app updates)
        └──► Firebase Cloud Messaging (push notifications)
```

Login issues a **Laravel Sanctum** session (Web) or a **JWT bearer token** (Mobile). The client stores `roleId` from the auth response and routes to the matching dashboard (`/super-admin`, `/admin`, `/auditor`, `/auditee`).

---

## Installation

Follow these steps to get the full repository — backend, realtime service, and
mobile app — installed and running on your machine.

### 1. Clone the repository

```bash
git clone https://github.com/MickoAndrei/CompliTrack.git
cd CompliTrack
```

> The active development work happens on the `Development` branch. If you
> need it specifically:
> ```bash
> git checkout Development
> ```

### 2. Install backend dependencies (Laravel)

```bash
composer install
```

Copy the environment file and generate an app key:

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set `MONGODB_URI`, `MONGODB_DATABASE`, `ENCRYPTION_KEY`, and
the initial administrator credentials — see [Environment Variables](#environment-variables)
below.

For a local development database, `php artisan db:seed` also creates clearly
marked fictional demo departments, users, compliance checks, and notifications.
New demo account passwords are generated and printed once in the terminal;
save them before closing the output. Existing demo account passwords are never
changed by rerunning the seeder. Do not seed this demo dataset into production.

### 3. Start the MongoDB replica set

Docker Desktop (or Docker Engine with the Compose plugin) is required:

```bash
docker compose up -d mongodb
php artisan mongodb:check
```

The Compose service runs MongoDB 7.0 as a single-node `rs0` replica set for
development. Its data persists in the `mongodb_data` volume. Do not use this
single-node setup as a production topology.

### 4. Install frontend asset dependencies

```bash
npm install
npm run build      # or: npm run dev, for a watched dev build
```

### 5. Run database migrations & seeders

```bash
php artisan migrate --force
php artisan db:seed
```

`DB_CONNECTION` remains `sqlite` for Laravel framework tables. Application
collections and their unique, query, TTL, token, and password-reset indexes
are managed in MongoDB. The initial super-admin seeder requires the credentials
in `.env`; it does not contain a default account password.

### 6. Install the realtime service dependencies (Node.js)

```bash
cd realtime
npm install
cp .env.example .env       # MongoDB URI, Firebase service account, Socket.io port
cd ..
```

### 7. (Optional) Install the mobile app dependencies (Flutter)

```bash
cd mobile
flutter pub get
cd ..
```

### 7. Verify the install

```bash
php artisan serve
```

Visit `http://localhost:8000/login`. The route exists, but the login view
(`resources/views/auth/login.blade.php`) is still an empty placeholder, so the
page will be blank until it is built. See [Getting Started](#getting-started)
for running each service during development.

---

## Git Workflow

This repo uses two long-lived branches:

| Branch | Purpose |
|---|---|
| `Development` | Where new/changed modules are pushed and tested. Things are allowed to be temporarily broken here. |
| `CompliTrack` | The stable branch — only receives code that has been verified working on `Development`. This is treated as the "main" branch. |

**Never commit finished-but-untested work straight to `CompliTrack`.**

### Daily workflow — working on Development

```bash
git checkout Development
git pull origin Development      # get the latest changes first

# ...make your changes...

git add .
git commit -m "Describe what you changed"
git push origin Development
```

### Once a change is verified working — merge into CompliTrack

**Option A — Pull Request (recommended):**
1. Push your commits to `Development` (as above).
2. On GitHub, open a Pull Request: base `CompliTrack` ← compare `Development`.
3. Review the diff, then click **Merge pull request**.

**Option B — merge locally:**

```bash
git checkout CompliTrack
git pull origin CompliTrack
git merge Development
git push origin CompliTrack
```

### After merging

Switch back to `Development` for the next round of work:

```bash
git checkout Development
```

### Quick reference — common commands

| Command | What it does |
|---|---|
| `git status` | Shows current branch + staged/unstaged changes |
| `git branch -a` | Lists local and remote branches |
| `git remote -v` | Shows which GitHub repo you're connected to |
| `git add .` | Stages all changed files |
| `git commit -m "message"` | Commits staged changes |
| `git push origin <branch>` | Pushes commits to that branch on GitHub |
| `git pull origin <branch>` | Fetches and merges the latest from that branch |
| `git checkout <branch>` | Switches to a different local branch |
| `git merge <branch>` | Merges another branch into the one you're on |

---

## Getting Started

### Prerequisites

- PHP 8.2+ and Composer
- Node.js 20+ and npm/yarn
- MongoDB 7.0 (local or Atlas) and the PHP `mongodb` extension
- Flutter 3.24+ / Dart 3.5+ (for the mobile app)
- Firebase project (for FCM push notifications)

> Already installed everything? See the [Installation](#installation) section
> above if not. The commands below are for day-to-day development once
> dependencies are in place.

### Backend (Laravel API)

```bash
php artisan serve
```

### Realtime Service (Node.js)

```bash
cd realtime
npm run dev
```

### Mobile App (Flutter)

```bash
cd mobile
flutter run
```

---

## Environment Variables

Set in the root `.env` (copied from `.env.example`). CompliTrack-specific values:

```env
APP_NAME=CompliTrack
APP_URL=http://localhost:8000

# Laravel framework tables (sessions, cache, queue) — leave as-is
DB_CONNECTION=sqlite

# CompliTrack: MongoDB (app data)
MONGODB_URI=mongodb://localhost:27017/?replicaSet=rs0&directConnection=true
MONGODB_DATABASE=complitrack

SUPER_ADMIN_EMAIL=admin@example.edu
SUPER_ADMIN_PASSWORD=      # Set a unique password (at least 12 characters)
ENCRYPTION_KEY=            # Base64-encoded 32-byte AES-256-GCM key

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587

FCM_SERVER_KEY=            # legacy placeholder — see note below
NODE_SOCKET_URL=http://localhost:4000
```

Generate an encryption key locally with
`php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`; do not commit it.
For a physical mobile device or a non-Android-simulator target, override the
default Android-emulator API host at build time:
`flutter run --dart-define=COMPLITRACK_API_URL=http://<your-host>:8000`.

> **FCM note:** Google's legacy FCM server keys no longer work. Push
> notifications use the FCM HTTP v1 API, which needs a Firebase
> **service-account JSON** configured in the `realtime/` service
> (`realtime/src/config/firebase.js`). `FCM_SERVER_KEY` should be replaced
> with a service-account path once that is wired up.

---

## Database Collections

MongoDB collections (schema enforced via Laravel validation):

- `roles` — the 4 fixed roles and their permission lists
- `users` — `userCode`, `roleId`, `departmentId`, `fcmToken`, etc.
- `departments` — CET departments/offices
- `archive_documents` — encrypted evidence files, checksum, reference standard
- `compliance_checks` — result, `correctiveStatus`, full `statusHistory`
- `notifications` — in-app + FCM delivery records
- `audit_logs` — every POST/PATCH/DELETE, with `userId` and IP

---

## API Endpoints

All routes are under `/api/v1` (defined in `routes/api.php`). Everything except
the auth routes requires `auth:sanctum`.

| Method | Endpoint | Roles | Description |
|---|---|---|---|
| `POST` | `/auth/login` | public | Authenticate, returns `{token, user}` |
| `POST` | `/auth/logout` | public | Log out |
| `POST` | `/auth/forgot-password` | public | Sends a signed, time-limited reset link |
| `POST` | `/auth/reset-password` | public | Reset password with the signed token |
| `GET/POST/PUT/DELETE` | `/users`, `/users/{id}` | 0, 1 | User management (`apiResource`) |
| `GET/POST/PUT/DELETE` | `/departments`, `/departments/{id}` | 0, 1 | Department management (`apiResource`) |
| `POST` | `/archives/upload` | all (per scope) | Encrypts and stores evidence |
| `GET` | `/archives` | all (per scope) | List archive documents |
| `GET` | `/archives/{id}/download` | all (per scope) | Decrypts and streams a file |
| `GET/POST/PUT/DELETE` | `/compliance-checks`, `/compliance-checks/{id}` | all (per scope) | Compliance checks (`apiResource`) |
| `PATCH` | `/compliance-checks/{id}/status` | all (per scope) | Update corrective status, triggers realtime event |
| `GET` | `/notifications` | all | Notifications for the logged-in user |
| `PATCH` | `/notifications/{id}/read` | all | Mark a notification as read |
| `GET` | `/logs` | 0 | System audit logs |
| `GET` | `/reports` | 0, 1 | Generate compliance reports |

> The route definitions exist, but the controllers behind them are still empty.

---

## Folder Structure

See [`FILE_STRUCTURE.md`](FILE_STRUCTURE.md) for the complete file-by-file tree.
Top level:

```
app/
  Helpers/RoleHelper.php
  Http/Controllers/Api/V1/     # Auth, User, Department, Archive, ComplianceCheck,
                               # Notification, AuditLog, Report controllers
  Http/Controllers/Web/        # Dashboard, Profile
  Http/Middleware/             # RoleMiddleware, AuditLogMiddleware, EnsureUserIsActive
  Models/                      # User, Role, Department, ArchiveDocument,
                               # ComplianceCheck, Notification, AuditLog
  Services/                    # ArchiveEncryption, ComplianceCheck,
                               # NotificationDispatch, ReportGenerator, AuditLog
config/                        # database.php (mongodb), sanctum, jwt, cors
database/                      # migrations, seeders (Role, Department, SuperAdmin)
resources/views/               # Blade: super-admin/, admin/, auditor/, auditee/,
                               # auth/, components/, layouts/, partials/, errors/
routes/                        # api.php, web.php
tests/

realtime/                      # Node.js + Socket.io + FCM service
  src/{config,sockets,watchers,services,models,utils}/

mobile/                        # Flutter app (all 4 roles)
  lib/
    core/{constants,theme,utils,network,services}/
    data/{models,repositories}/
    presentation/{navigation,screens,widgets}/
```

---

## Security

The design targets below are **planned**; check each against the code before
relying on it. Status reflects the current repository.

| Control | Status |
|---|---|
| Role checks via `RoleMiddleware` on the integer `roleId`, never by name | ✅ Implemented |
| Archive files encrypted at rest with **AES-256-GCM**; per-file IV in `encryptionIv` | ⏳ Planned (`ArchiveEncryptionService` is a stub) |
| **SHA-256** checksum on upload, verified on download | ⏳ Planned |
| Passwords hashed with **bcrypt** (cost 12) | ⏳ Planned |
| Login rate-limited (5 attempts/min per email + IP) → `429` | ⏳ Planned (`RateLimitServiceProvider` is a stub) |
| MongoDB ObjectId format validated before every query | ⏳ Planned |
| Every write operation logged to `audit_logs` | ⏳ Planned (`AuditLogMiddleware` is a stub) |

Found a security issue? Please don't open a public issue. Use GitHub's
**Security → Report a vulnerability** on this repository to report it privately.

---

## Roadmap

- [ ] Phase 1 — Laravel setup, auth, roles seeder, users/department CRUD
  - [x] Laravel + MongoDB setup, models, routes, `RoleMiddleware`, `RoleSeeder`
  - [ ] Auth endpoints, users/department CRUD
- [ ] Phase 2 — Archive module with encryption, upload/download
- [ ] Phase 3 — Compliance checks, corrective status, notifications collection
- [ ] Phase 4 — Node.js realtime service + FCM integration
- [ ] Phase 5 — Blade dashboards per role
- [ ] Phase 6 — Flutter mobile app (all four roles)
- [ ] Phase 7 — Testing, audit logs, PDF reports, deployment

---

## Contributing

Contributions are welcome. Please open an issue to discuss significant
changes before submitting a pull request, and follow the naming conventions
in the project's development guide (PascalCase models, camelCase fields,
kebab-case routes).

---

## License

This project is licensed under the **MIT License** — see [LICENSE](LICENSE) for details.

---

## Acknowledgments

- USTP Claveria — College of Engineering and Technology (CET)
- [Laravel](https://laravel.com) — the backend framework this project is built on
- [Lucide Icons](https://lucide.dev)
- Built with Laravel, MongoDB, Node.js, and Flutter