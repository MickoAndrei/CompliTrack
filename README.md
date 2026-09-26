 HEAD
# 🛡️ CompliTrack

**Regulatory Compliance & Accountability System** *USTP Claveria — College of Engineering and Technology (CET)*

[![License: MIT]()](http://LICENSE) [![Built with Laravel]()](https://laravel.com) ![Laravel]() ![MongoDB]() ![Node.js]() ![Flutter]() ![Status]()

---

## Overview

**CompliTrack** is a centralized, secure, and auditable compliance management system built exclusively for **USTP Claveria CET**. It replaces manual, paper-based tracking with a single source of truth for regulatory evidence, audit findings, and corrective actions — covering standards such as the **ARTA 3/7/20-day rule** and **ISO 9001:2015 Clause 9.2/10.2**.

The system is intentionally scoped to three core functions:

| \# | Function | Description |
| :---- | :---- | :---- |
| 1 | 🗄️ **Archive Files** | Secure, encrypted repository (AES‑256‑GCM) for compliance evidence (PDF, DOCX, images). Organized by Department/Office, tagged by reference standard. |
| 2 | ✅ **Check Compliance** | Auditors create a `ComplianceCheck` record marking **Compliant** / **Non‑Compliant** based on archived evidence. |
| 3 | 🔔 **Notify Corrective Action Status** | Non‑compliant checks auto‑create a corrective action (`open → in_progress → closed`) and notify the Auditee in‑app \+ push on every change. Compliant checks also notify the Auditee once, so a passed check is never silent. |

**Workflow:** Auditor checks → if Non‑Compliant, open CAR → notify Auditee → Auditee uploads fix → Auditor reviews → `in_progress` / `closed` → notify Auditee. If Compliant, notify Auditee immediately with no CAR opened.

---

## Table of Contents

- [Features](#features)  
- [Tech Stack](#tech-stack)  
- [User Roles & Permissions](#user-roles--permissions)  
- [Architecture](#architecture)  
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
- 🔔 Real-time in-app \+ push notifications (Socket.io \+ Firebase Cloud Messaging)  
- 📊 Role-specific dashboards with KPIs, compliance gauges, and trend charts  
- 📱 Cross-platform mobile app (Flutter) for Auditors and Auditees in the field  
- 🧾 Full audit logging of every write operation (user, action, IP address)  
- 🖨️ PDF compliance reports with official USTP Navy/Gold branding

---

## Tech Stack

> Built on top of the [Laravel](https://laravel.com) framework for the backend API — huge thanks to the Laravel team and community for the tooling this project is built on.

| Layer | Technology |
| :---- | :---- |
| Backend API | Laravel 11 (PHP 8.3) — REST API, business logic, role middleware, file encryption |
| Realtime Service | Node.js (Express \+ Socket.io) — live notifications, status updates, dashboard refresh |
| Mobile App | Flutter 3.24+ (Dart 3.5) — Android/iOS, for Auditee and Auditor field use |
| Database | MongoDB 7.0 (`mongodb/laravel-mongodb`) — flexible schema for compliance docs |
| Auth | Laravel Sanctum (Web) · JWT via `tymon/jwt-auth` (Mobile) |
| Storage | Local encrypted disk \+ optional S3-compatible (MinIO) |
| Push Notifications | Firebase Cloud Messaging (FCM), dispatched via the Node service |
| Frontend Web | Laravel Blade \+ Tailwind CSS 3.4 \+ Alpine.js |
| Icons | [Lucide Icons](https://lucide.dev) — single icon library across Web & Mobile |

---

## User Roles & Permissions

| RoleId | Role | Scope | Key Permissions |
| :---- | :---- | :---- | :---- |
| `0` | **Super Admin** | System-wide | Full access: manage users, roles, departments, system logs, backups; cannot create compliance checks |
| `1` | **Admin** (CET Admin) | CET College only | Manage departments and auditor/auditee accounts, read-only archive access, view reports |
| `2` | **Auditor** | Assigned departments | Create/update compliance checks, read/write archive, change corrective status |
| `3` | **Auditee** (Office Head/Faculty) | Own office only | View own compliance status, upload evidence, view notifications |

> Role is determined **only** by the integer `roleId` field — never inferred from `userId`.

---

## Architecture

Client (Web Blade \+ Flutter App)

        │

        ▼

Laravel API Gateway (PHP)  ──────►  MongoDB \+ Encrypted Storage

        │

        ▼

Node.js microservice (watches MongoDB change streams)

        │

        ├──► Socket.io (live in-app updates)

        └──► Firebase Cloud Messaging (push notifications)

Login issues a **Laravel Sanctum** session (Web) or a **JWT bearer token** (Mobile). The client stores `roleId` from the auth response and routes to the matching dashboard (`/super-admin`, `/admin`, `/auditor`, `/auditee`).

---

## Getting Started

### Prerequisites

- PHP 8.3+ and Composer  
- Node.js 20+ and npm/yarn  
- MongoDB 7.0 (local or Atlas)  
- Flutter 3.24+ / Dart 3.5+ (for the mobile app)  
- Firebase project (for FCM push notifications)

### Backend (Laravel API)

git clone https://github.com/\<your-org\>/complitrack.git

cd complitrack/backend

composer install

cp .env.example .env

php artisan key:generate

\# Configure MongoDB connection, ENCRYPTION\_KEY, and Mail settings in .env

php artisan migrate        \# seeds roles (0-3), indexes

php artisan serve

### Realtime Service (Node.js)

cd complitrack/realtime

npm install

cp .env.example .env       \# MongoDB URI, FCM server key, Socket.io port

npm run dev

### Mobile App (Flutter)

cd complitrack/mobile

flutter pub get

flutter run

---

## Environment Variables

Minimal set required by the Laravel backend:

APP\_NAME=CompliTrack

APP\_URL=http://localhost:8000

DB\_CONNECTION=mongodb

MONGO\_DB\_URI=mongodb://localhost:27017/complitrack

ENCRYPTION\_KEY=            \# AES-256-GCM key, rotated via Super Admin panel

JWT\_SECRET=

MAIL\_MAILER=smtp

MAIL\_HOST=

MAIL\_PORT=587

FCM\_SERVER\_KEY=

NODE\_SOCKET\_URL=http://localhost:4000

---

## Database Collections

MongoDB collections (schema enforced via Laravel validation):

- `roles` — the 4 fixed roles and their permission lists  
- `users` — `userCode`, `roleId`, `departmentId`, `fcmToken`, etc.  
- `departments` — CET departments/offices  
- `archive_documents` — encrypted evidence files, checksum, reference standard  
- `compliance_checks` — result, `correctiveStatus`, full `statusHistory`  
- `notifications` — in-app \+ FCM delivery records  
- `audit_logs` — every POST/PATCH/DELETE, with `userId` and IP

---

## API Endpoints Summary

| Method | Endpoint | Description |
| :---- | :---- | :---- |
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

app/

  Models/            \# User, Department, ArchiveDocument, ComplianceCheck

  Http/Controllers/Api/V1/

  Http/Middleware/RoleMiddleware.php

  Helpers/RoleHelper.php

routes/api.php

config/database.php  \# mongodb connection

lib/                  \# Flutter mobile app

  core/

  data/

  presentation/

    screens/

    widgets/

---

## Security

- Archive files encrypted at rest with **AES-256-GCM**; per-file IV stored in `encryptionIv`  
- **SHA-256** checksum generated on upload, verified on download  
- Passwords hashed with **bcrypt** (cost 12\)  
- Login endpoint rate-limited (5 attempts/min per email \+ IP) → `429` beyond that  
- Role checks always via `RoleMiddleware` on the integer `roleId`, never by name  
- MongoDB ObjectId format validated before every query  
- Every write operation logged to `audit_logs`

Found a security issue? Please see [Security Policy](#) below rather than opening a public issue.

---

## Roadmap

- [x] Phase 1 — Laravel setup, auth, roles seeder, users/department CRUD  
- [x] Phase 2 — Archive module with encryption, upload/download  
- [ ] Phase 3 — Compliance checks, corrective status, notifications collection  
- [ ] Phase 4 — Node.js realtime service \+ FCM integration  
- [ ] Phase 5 — Blade dashboards per role  
- [ ] Phase 6 — Flutter mobile app (Auditee/Auditor)  
- [ ] Phase 7 — Testing, audit logs, PDF reports, deployment

---

## Contributing

Contributions are welcome. Please open an issue to discuss significant changes before submitting a pull request, and follow the naming conventions in the project's development guide (PascalCase models, camelCase fields, kebab-case routes).

---

## License

This project is licensed under the **MIT License** — see [LICENSE](http://LICENSE) for details.

---

## Acknowledgments

- USTP Claveria — College of Engineering and Technology (CET)  
- [Laravel](https://laravel.com) — the backend framework this project is built on  
- [Lucide Icons](https://lucide.dev)  
- Built with Laravel, MongoDB, Node.js, and Flutter

# CompliTrack
Centralized, secure, and auditable regulatory compliance management system built for USTP Claveria's College of Engineering and Technology (CET). Tracks evidence archiving, compliance checks, and corrective action status across departments, with role-based dashboards and real-time notifications.
 34fb73ab3e6f23af4019ee63f5f9032514c7064c
