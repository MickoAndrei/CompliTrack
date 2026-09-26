<div align="center">

# 🛡️ CompliTrack

**Regulatory Compliance & Accountability System**
*USTP Claveria — College of Engineering and Technology (CET)*

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](LICENSE)
[![Built with Laravel](https://img.shields.io/badge/Built%20with-Laravel-FF2D20?logo=laravel&logoColor=white)](https://laravel.com)
![Laravel](https://img.shields.io/badge/Laravel-11-FF2D20?logo=laravel&logoColor=white)
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

## Table of Contents

- [Features](#features)
- [Tech Stack](#tech-stack)
- [User Roles & Permissions](#user-roles--permissions)
- [Architecture](#architecture)
- [Installation](#installation)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Backend (Laravel API)](#backend-laravel-api)
  - [Realtime Service (Node.js)](#realtime-service-nodejs)
  - [Mobile App (Flutter)](#mobile-app-flutter)
- [Environment Variables](#environment-variables)
- [Database Collections](#database-collections)
- [API Endpoints](#api-endpoints-summary)
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
- 📱 Cross-platform mobile app (Flutter) for Auditors and Auditees in the field
- 🧾 Full audit logging of every write operation (user, action, IP address)
- 🖨️ PDF compliance reports with official USTP Navy/Gold branding

---

## Tech Stack

> Built on top of the [Laravel](https://laravel.com) framework for the backend API — huge thanks to the Laravel team and community for the tooling this project is built on.

| Layer | Technology |
|---|---|
| Backend API | Laravel 11 (PHP 8.3) — REST API, business logic, role middleware, file encryption |
| Realtime Service | Node.js (Express + Socket.io) — live notifications, status updates, dashboard refresh |
| Mobile App | Flutter 3.24+ (Dart 3.5) — Android/iOS, for Auditee and Auditor field use |
| Database | MongoDB 7.0 (`mongodb/laravel-mongodb`) — flexible schema for compliance docs |
| Auth | Laravel Sanctum (Web) · JWT via `tymon/jwt-auth` (Mobile) |
| Storage | Local encrypted disk + optional S3-compatible (MinIO) |
| Push Notifications | Firebase Cloud Messaging (FCM), dispatched via the Node service |
| Frontend Web | Laravel Blade + Tailwind CSS 3.4 + Alpine.js |
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

Open `.env` and set your MongoDB connection string, `ENCRYPTION_KEY`, and mail
settings — see [Environment Variables](#environment-variables) below for the
full list.

### 3. Install frontend asset dependencies

```bash
npm install
npm run build      # or: npm run dev, for a watched dev build
```

### 4. Run database migrations & seeders

```bash
php artisan migrate
php artisan db:seed        # seeds the 4 fixed roles (0-3)
```

### 5. Install the realtime service dependencies (Node.js)

```bash
cd realtime
npm install
cp .env.example .env       # MongoDB URI, FCM server key, Socket.io port
cd ..
```

### 6. (Optional) Install the mobile app dependencies (Flutter)

```bash
cd mobile
flutter pub get
cd ..
```

### 7. Verify the install

```bash
php artisan serve
```

Visit `http://localhost:8000/login` — if the CompliTrack login page loads,
the backend install is working. See [Getting Started](#getting-started)
below for running each service together during development.

---

## Getting Started

### Prerequisites

- PHP 8.3+ and Composer
- Node.js 20+ and npm/yarn
- MongoDB 7.0 (local or Atlas)
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

Minimal set required by the Laravel backend:

```env
APP_NAME=CompliTrack
APP_URL=http://localhost:8000

DB_CONNECTION=mongodb
MONGO_DB_URI=mongodb://localhost:27017/complitrack

ENCRYPTION_KEY=            # AES-256-GCM key, rotated via Super Admin panel
JWT_SECRET=

MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587

FCM_SERVER_KEY=
NODE_SOCKET_URL=http://localhost:4000
```

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

## API Endpoints Summary

| Method | Endpoint | Description |
|---|---|---|
| `POST` | `/api/v1/auth/login` | Authenticate, returns `{token, user}` |
| `POST` | `/api/v1/auth/forgot-password` | Sends a signed, time-limited reset link |
| `GET` | `/api/v1/users` | List users (role-restricted) |
| `POST` | `/api/v1/archives/upload` | Encrypts and stores evidence |
| `GET` | `/api/v1/archives/{id}/download` | Decrypts and streams a file |
| `POST` | `/api/v1/compliance-checks` | Create a compliance check |
| `PATCH` | `/api/v1/compliance-checks/{id}/status` | Update corrective status, triggers realtime event |
| `GET` | `/api/v1/notifications` | Notifications for the logged-in user |

Full endpoint list is documented in `/docs/api.md`.

---

## Folder Structure

```
app/
  Models/            # User, Department, ArchiveDocument, ComplianceCheck
  Http/Controllers/Api/V1/
  Http/Middleware/RoleMiddleware.php
  Helpers/RoleHelper.php
routes/api.php
config/database.php  # mongodb connection

lib/                  # Flutter mobile app
  core/
  data/
  presentation/
    screens/
    widgets/
```

---

## Security

- Archive files encrypted at rest with **AES-256-GCM**; per-file IV stored in `encryptionIv`
- **SHA-256** checksum generated on upload, verified on download
- Passwords hashed with **bcrypt** (cost 12)
- Login endpoint rate-limited (5 attempts/min per email + IP) → `429` beyond that
- Role checks always via `RoleMiddleware` on the integer `roleId`, never by name
- MongoDB ObjectId format validated before every query
- Every write operation logged to `audit_logs`

Found a security issue? Please see [Security Policy](#) below rather than opening a public issue.

---

## Roadmap

- [x] Phase 1 — Laravel setup, auth, roles seeder, users/department CRUD
- [x] Phase 2 — Archive module with encryption, upload/download
- [ ] Phase 3 — Compliance checks, corrective status, notifications collection
- [ ] Phase 4 — Node.js realtime service + FCM integration
- [ ] Phase 5 — Blade dashboards per role
- [ ] Phase 6 — Flutter mobile app (Auditee/Auditor)
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

</div>