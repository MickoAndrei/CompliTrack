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

**CompliTrack** is a centralized, secure, and auditable compliance management system developed specifically for the College of Engineering and Technology (CET) at USTP Claveria. It replaces manual, paper-based tracking with an authoritative digital source of truth for regulatory evidence, audit findings, and corrective actions. The system streamlines institutional accountability and ensures verifiable compliance with key regulatory standards, including the **ARTA 3/7/20-day rule** and **ISO 9001:2015 Clauses 9.2 & 10.2**.

> [!NOTE]
> **Current Status:** The project is currently in the scaffold stage. Foundational directory structures, database models, route definitions, role middleware, and role seeders are implemented. Controllers, services, Blade views, the `realtime/` notification service, and the `mobile/` application are active placeholders undergoing implementation. Features described in this document represent the architectural design target; refer to the [Development Roadmap](#development-roadmap) for current implementation progress.

### Core Functions & Workflow

CompliTrack focuses on three core operations:

| # | Function | Description |
|---|----------|-------------|
| 1 | 🗄️ **Archive Files** | Secure, AES-256-GCM encrypted repository for compliance evidence (PDF, DOCX, images), organized by Department/Office and indexed by reference standard. |
| 2 | ✅ **Check Compliance** | Structured auditing interface enabling auditors to evaluate evidence and issue `Compliant` or `Non-Compliant` compliance check records. |
| 3 | 🔔 **Notify Corrective Action Status** | Automated Corrective Action Request (CAR) tracking (`open → in_progress → closed`) with real-time foreground and in-app notifications for auditees. |

**Audit Lifecycle Workflow:**
1. **Evidence Review:** The Auditor evaluates submitted departmental evidence against target regulatory standards.
2. **Compliance Evaluation:**
   - **Compliant:** The check passes; the Auditee is immediately notified. No CAR is opened.
   - **Non-Compliant:** A Corrective Action Request (CAR) is automatically created with an `open` status, and the Auditee receives an immediate notification.
3. **Resolution & Closure:** The Auditee uploads corrective evidence, the Auditor reviews the changes, and the record transitions through `in_progress` to `closed`, alerting the Auditee at each milestone.

### System Architecture

CompliTrack implements a decoupled architecture connecting web and mobile clients to a Laravel API core, a MongoDB data store, and an asynchronous Node.js notification microservice:

```
Client (Web Blade / Alpine.js / Tailwind CSS + Flutter Mobile App)
        │
        ▼
Laravel API Gateway (PHP 8.2+)  ──────►  MongoDB 7.0 + AES-256 Encrypted Disk
        │                                 (Application Data & Audit Logs)
        ▼
Node.js Microservice (Socket.io) ◄── Polling persisted notifications (2s interval)
        │
        └──► Realtime Event Broadcast (Authenticated Socket.IO Rooms)
```

- **Authentication:** Web sessions utilize **Laravel Sanctum** cookies; mobile clients authenticate via **JWT bearer tokens** (`tymon/jwt-auth`).
- **Dashboard Routing:** Following authentication, clients inspect the user's integer `roleId` and route directly to the designated dashboard (`/super-admin`, `/admin`, `/auditor`, `/auditee`).

---

## Key Features

- **Role-Based Access Control (RBAC):** Strict 4-tier role hierarchy (Super Admin, Admin, Auditor, Auditee) enforced through `RoleMiddleware` solely via an integer `roleId`.
- **Encrypted Evidence Archive:** AES-256-GCM encryption at rest with per-file initialization vectors (`encryptionIv`) and upload/download SHA-256 checksum verification.
- **Structured Compliance Checking:** Fast audit record creation linked directly to archived evidence files and specific regulatory reference standards.
- **Automated Corrective Action Tracking:** Automatic CAR lifecycle management with chronological transition tracking (`open → in_progress → closed`).
- **Real-Time Notification Engine:** Instant in-app alerts and foreground updates powered by a Node.js Socket.IO microservice polling persisted MongoDB events every 2 seconds.
- **Role-Tailored Dashboards:** Specialized user interfaces with key performance indicators (KPIs), compliance gauges, submitted-audit reviews, and trend analytics.
- **Cross-Platform Mobile Application:** Dedicated Flutter application (Android/iOS) optimized for on-site auditing, status tracking, and mobile evidence submission.
- **Immutable Audit Logging:** System-wide capture of all write operations (`POST`, `PATCH`, `DELETE`) recording timestamps, authenticated `userId`, action taken, and client IP.
- **Branded Report Exports:** Formatted PDF compliance summaries featuring official USTP Navy and Gold institutional branding.

---

## Tech Stack

CompliTrack is built on modern open-source web and mobile technologies:

| Layer | Technology | Purpose |
|---|---|---|
| **Backend API** | [Laravel 12](https://laravel.com) (PHP 8.2+) | RESTful API, business logic, role middleware, and file encryption |
| **Realtime Service** | Node.js 20+ (Express + Socket.io) | Live event notifications, status updates, and dashboard refreshes |
| **Mobile App** | [Flutter 3.24+](https://flutter.dev) (Dart 3.5+) | Cross-platform mobile client for Android and iOS |
| **Database** | [MongoDB 7.0](https://www.mongodb.com) (`mongodb/laravel-mongodb`) | CompliTrack application data; SQLite for Laravel framework tables |
| **Authentication** | Laravel Sanctum / JWT (`tymon/jwt-auth`) | Sanctum session cookies (Web) and JWT bearer tokens (Mobile) |
| **Storage** | Local Encrypted Disk / MinIO | AES-256-GCM encrypted local storage; optional S3-compatible cloud storage |
| **Notification Engine** | MongoDB + Socket.IO | Persisted notification records polled by Node.js; HTTP polling web fallback |
| **Frontend Web** | Laravel Blade + Tailwind CSS 4 + Alpine.js | Server-rendered responsive UI compiled via Vite |
| **Iconography** | [Lucide Icons](https://lucide.dev) | Consistent visual icon library across Web and Mobile applications |

### Database Architecture & Specifications

CompliTrack employs a dual-database architecture:
- **MongoDB 7.0:** Stores all application domains, including users, roles, departments, archive documents, compliance checks, notifications, and audit logs. Configured via `MONGODB_URI` and `MONGODB_DATABASE`.
- **SQLite:** Dedicated to Laravel framework internal tables (sessions, cache, jobs, relational migrations) at `database/database.sqlite`. Setting `DB_CONNECTION=sqlite` does **not** store application records in SQLite.

#### Specialized Documentation & Specifications
- [QMS Archives Implementation](docs/QMS_ARCHIVES_IMPLEMENTATION.md) — CET QMS Archives implementation, migrations, authorization rules, and legacy ownership decisions.
- [QMS Development Data](docs/QMS_DEVELOPMENT_DATA.md) — Development mock document importer, Admin/ATL reuse, organizational accounts, and audit/CAR scenarios.
- [Super Admin Dashboard](docs/SUPER_ADMIN_DASHBOARD.md) — Super Admin dashboard metrics, organizational responsibility controls, and verification results.
- [User Management Implementation](docs/USER_MANAGEMENT_IMPLEMENTATION.md) — Account management, organizational validation, preservation rules, and test results.
- [Performance Optimization](docs/PERFORMANCE_OPTIMIZATION.md) & [Performance Phase 2](docs/PERFORMANCE_PHASE_2.md) — Measured Users API, frontend, static asset and OPcache improvements, styling fixes, and regression benchmarks.
- [Audit Team Leader (ATL) Workspace](docs/ATL_WORKSPACE_IMPLEMENTATION.md) — Admin account/ATL workspace, scoped dashboard, submitted-audit review modal, and authorization boundaries.
- [Password Reset Email Configuration](docs/PASSWORD_RESET_EMAIL.md) — Dedicated PHPMailer SMTP transport, Microsoft 365 OAuth setup, and `php artisan mail:check`.
- [Directory File Structure](FILE_STRUCTURE.md) — Complete repository file and folder layout.

---

## Table of Contents

- [Overview](#overview)
- [Key Features](#key-features)
- [Tech Stack](#tech-stack)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Quick Start (Windows PowerShell)](#quick-start-windows-powershell)
  - [Manual Installation](#manual-installation)
- [Usage](#usage)
  - [Running the Services](#running-the-services)
  - [Environment Configuration](#environment-configuration)
  - [User Roles & Permissions](#user-roles--permissions)
  - [API Endpoints](#api-endpoints)
  - [Realtime & Notification System](#realtime--notification-system)
  - [Backend Deployment](#backend-deployment)
- [Project Structure](#project-structure)
  - [Directory Tree](#directory-tree)
  - [Database Collections](#database-collections)
- [Contributing](#contributing)
  - [Git Workflow](#git-workflow)
  - [Coding Standards](#coding-standards)
  - [Security & Vulnerability Reporting](#security--vulnerability-reporting)
  - [Development Roadmap](#development-roadmap)
- [License](#license)
- [Acknowledgments](#acknowledgments)

---

## Getting Started

### Prerequisites

Ensure the following runtimes and tools are installed and accessible on your system `PATH`:

- **PHP:** Version 8.2 or higher with [Composer](https://getcomposer.org/) and the PHP `mongodb` extension enabled.
- **Node.js:** Version 20.0 or higher with `npm` (or `yarn`).
- **Database:** MongoDB 7.0 (local single-node replica set via Docker or remote cluster via MongoDB Atlas).
- **Containerization:** [Docker Desktop](https://www.docker.com/products/docker-desktop/) or Docker Engine with Docker Compose plugin (for local MongoDB replica sets).
- **Mobile SDK (Optional):** [Flutter SDK](https://flutter.dev/docs/get-started/install) 3.24+ (Dart 3.5+). Flutter 3.38.1+ is required for production mobile builds.

---

### Quick Start (Windows PowerShell)

For local development on Windows, an automated startup script manages service orchestration, environment checks, dependency installation, and process execution:

1. **Start all services:**
   ```powershell
   .\start-complitrack.ps1
   ```
   *What this script does:*
   - Validates installed prerequisites (PHP 8.2+, Node.js 20+, Composer, npm).
   - Generates `.env` and `realtime\.env` if missing and generates application keys.
   - Verifies the MongoDB connection configured in `.env`.
   - Runs database migrations and seeders (one-time initialization per database target).
   - Clears stale web sessions, builds frontend assets, and launches Laravel, Vite, and the Node.js realtime microservice concurrently.

2. **Stop running services:**
   ```powershell
   .\stop-complitrack.ps1
   ```
   *Note: This script terminates Laravel, Vite, and the Node.js service, leaving the MongoDB instance running.*

#### MongoDB Connection Troubleshooting
If startup halts with a TLS handshake or server selection error, execute:
```powershell
php artisan config:clear
php artisan mongodb:check --ping
```
- **MongoDB Atlas:** Ensure your current public IP address (or VPN egress IP) is added under **Network Access > IP Access List**.
- **Firewall & Ports:** Verify outbound TCP communication over port `27017` and check for proxy or antivirus TLS inspection.
- **Certificates:** Confirm your system clock is synchronized and CA certificates are up to date. Keep TLS certificate verification enabled. For additional details, see the [Atlas connection troubleshooting guide](https://www.mongodb.com/docs/atlas/troubleshoot-connection/).

---

### Manual Installation

Follow the step-by-step instructions below to manually configure and run the backend, realtime service, and mobile application on any platform.

#### 1. Clone the Repository
```bash
git clone https://github.com/MickoAndrei/CompliTrack.git
cd CompliTrack
```
> [!TIP]
> Active feature development occurs on the `Development` branch. If working on upcoming features, switch branches:
> ```bash
> git checkout Development
> ```

#### 2. Install Backend Dependencies & Configure Environment
```bash
composer install
cp .env.example .env
php artisan key:generate
```

Generate a 32-byte AES-256-GCM encryption key for file storage:
```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```
Open `.env` and assign the generated string to `ENCRYPTION_KEY`. Ensure `MONGODB_URI`, `MONGODB_DATABASE`, and administrative credentials are properly configured (see [Environment Configuration](#environment-configuration)).

#### 3. Start the MongoDB Replica Set
Start the local MongoDB 7.0 container configured as a single-node replica set (`rs0`):
```bash
docker compose up -d mongodb
php artisan mongodb:check
```
*Note: Local replica set data persists in the `mongodb_data` volume. This single-node setup is intended strictly for development and testing.*

#### 4. Install Frontend Dependencies & Compile Assets
```bash
npm install
npm run build
```
*(Use `npm run dev` during active frontend development for hot module reloading).*

#### 5. Run Database Migrations & Seeders
```bash
php artisan migrate --force
php artisan db:seed
```
- Initial Super Admin credentials are read from `.env` (no default hardcoded password exists).
- On local development environments, `php artisan db:seed` automatically creates fictional demo departments, users, and audit records. Passwords for newly created demo accounts are output once in the terminal—record these before clearing the screen.

#### 6. Configure the Realtime Service (Node.js)
```bash
cd realtime
npm install
cp .env.example .env
cd ..
```
*Configure MongoDB URI, JWT secret, and Socket.IO port inside `realtime/.env` as needed.*

#### 7. (Optional) Install Mobile App Dependencies (Flutter)
```bash
cd mobile
flutter pub get
cd ..
```

#### 8. Verify the Installation
Start the Laravel development server:
```bash
php artisan serve
```
Navigate to `http://localhost:8000/login`. The route will be active, and views will be served as implemented.

---

## Usage

### Running the Services

During development, start each service in separate terminal sessions:

- **Laravel Backend API:**
  ```bash
  php artisan serve
  ```
  *Accessible at `http://localhost:8000`.*

- **Realtime Notification Microservice:**
  ```bash
  cd realtime
  npm run dev
  # Or run in production mode:
  npm start
  ```
  *Accessible at `http://localhost:4000`.*

- **Flutter Mobile Application:**
  ```bash
  cd mobile
  flutter run
  ```
  *To target a physical device or network-accessible emulator host, override the API URL at build time:*
  ```bash
  flutter run --dart-define=COMPLITRACK_API_URL=http://<YOUR_LOCAL_IP>:8000
  ```

---

### Environment Configuration

CompliTrack is configured primarily through the root `.env` file. Essential configuration values include:

```env
APP_NAME=CompliTrack
APP_URL=http://localhost:8000

# Laravel framework support tables (SQLite database)
DB_CONNECTION=sqlite

# CompliTrack application data (MongoDB 7.0 replica set)
MONGODB_URI=mongodb://localhost:27017/?replicaSet=rs0&directConnection=true
MONGODB_DATABASE=complitrack

# Initial Super Admin account provisioning
SUPER_ADMIN_EMAIL=admin@example.edu
SUPER_ADMIN_PASSWORD=      # Minimum 12 characters; unique passphrase
ENCRYPTION_KEY=            # Base64-encoded 32-byte AES-256-GCM key

# Mail configuration (PHPMailer SMTP transport)
MAIL_MAILER=smtp
MAIL_HOST=
MAIL_PORT=587

# Realtime service endpoint
NODE_SOCKET_URL=http://localhost:4000
```

- **Encryption Key:** Generate via `php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"`. Never commit this key to source control.
- **Mail Services:** Password reset emails utilize a dedicated PHPMailer SMTP transport instead of logging links. For Microsoft 365 OAuth setup and the `php artisan mail:check` verification command, refer to [PASSWORD_RESET_EMAIL.md](docs/PASSWORD_RESET_EMAIL.md).

---

### User Roles & Permissions

Access control is strictly enforced based on the integer `roleId` field:

| Role ID | Role | Scope | Key Permissions & Responsibilities |
|:---:|---|---|---|
| `0` | **Super Admin** | System-wide | Complete administrative authority: user management, role assignments, department management, system audit logs, and backups. Prohibited from creating compliance checks. |
| `1` | **Admin (CET Admin / ATL)** | CET College-wide | Manages department heads and auditor accounts; serves as Audit Team Leader (ATL) to review submitted audits, track corrective actions, view read-only archives, and export reports. |
| `2` | **Auditor** | Assigned Departments | Performs audits, generates and updates compliance checks, uploads/reads archived evidence, and updates corrective action statuses. |
| `3` | **Auditee (Office Head / Faculty)** | Own Department / Office | Inspects department compliance status, uploads requested evidence and corrective documentation, and receives real-time status notifications. |

> [!IMPORTANT]
> User authorization checks in `RoleMiddleware` evaluate the integer `roleId` directly—permissions are never inferred from usernames, emails, or user codes.

---

### API Endpoints

All application API endpoints are versioned under `/api/v1` (registered in `routes/api.php`). Unless designated as `public`, endpoints require an authenticated session or bearer token via `auth:sanctum`.

| Method | Endpoint | Authorized Roles | Description |
|---|---|---|---|
| `POST` | `/api/v1/auth/login` | Public | Authenticates credentials and returns a session or token and user profile object. |
| `POST` | `/api/v1/auth/logout` | Authenticated | Terminates the current authenticated session or revokes the bearer token. |
| `POST` | `/api/v1/auth/forgot-password` | Public | Generates and emails a signed, time-limited password reset link. |
| `POST` | `/api/v1/auth/reset-password` | Public | Resets the user account password using a validated reset token. |
| `GET` | `/api/v1/version` | Public | Exposes the current shared semantic application version. |
| `GET/POST` | `/api/v1/users` | `0`, `1` | List all users or create a new user account. |
| `GET/PUT/DELETE` | `/api/v1/users/{id}` | `0`, `1` | Retrieve, update, or delete a specific user account. |
| `GET/POST` | `/api/v1/departments` | `0`, `1` | List all CET departments or register a new department. |
| `GET/PUT/DELETE` | `/api/v1/departments/{id}` | `0`, `1` | Retrieve, update, or delete a department record. |
| `POST` | `/api/v1/archives/upload` | All (Scoped) | Encrypts (AES-256-GCM) and stores compliance evidence with SHA-256 checksums. |
| `GET` | `/api/v1/archives` | All (Scoped) | Retrieves a paginated list of archived compliance documents within user scope. |
| `GET` | `/api/v1/archives/{id}/download` | All (Scoped) | Decrypts and streams an archived document after checksum verification. |
| `GET/POST` | `/api/v1/compliance-checks` | All (Scoped) | List compliance evaluations or create a new compliance check. |
| `GET/PUT/DELETE` | `/api/v1/compliance-checks/{id}` | All (Scoped) | Retrieve, update, or remove a compliance check record. |
| `PATCH` | `/api/v1/compliance-checks/{id}/status` | All (Scoped) | Updates corrective action status (`open`, `in_progress`, `closed`) and emits realtime event. |
| `GET` | `/api/v1/notifications` | All | Retrieves persisted notifications for the authenticated user. |
| `PATCH` | `/api/v1/notifications/{id}/read` | All | Marks a designated notification record as read. |
| `GET` | `/api/v1/logs` | `0` | Retrieves system-wide immutable audit logs. |
| `GET` | `/api/v1/reports` | `0`, `1` | Generates structured compliance summary reports. |

#### Sample Authentication Request & Response

**Login Request:**
```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "auditor@example.edu",
    "password": "SamplePassword123!"
  }'
```

**Login Response (`200 OK`):**
```json
{
  "token": "1|laravel_sanctum_token_string...",
  "user": {
    "id": "670da123456789abcdef0123",
    "userCode": "AUD-001",
    "name": "Jane Doe",
    "email": "auditor@example.edu",
    "roleId": 2,
    "departmentId": "670da987654321fedcba3210"
  }
}
```

*Note: For endpoints currently under development, request/response models follow the schemas defined in `routes/api.php`.*

---

### Realtime & Notification System

- **Persistence & Polling:** Laravel writes assignment, audit submission, review, and CAR events directly to the MongoDB `notifications` collection. The Node.js service polls for undelivered notifications every 2 seconds and delivers them to authenticated rooms via Socket.IO.
- **Client Handling:** The web client refreshes notifications via the authenticated HTTP API, while the Flutter app uses its native Socket.IO connection. Offline notifications remain queryable through `GET /api/v1/notifications`.
- **Deduplication:** Stable notification IDs and event dedupe keys prevent duplicate delivery across connection drops and retries.
- **Configuration & Secrets:** The Node.js service loads configuration from root `.env` by default (falling back to `realtime/.env`). Ensure the JWT secret and `HS256` signing algorithm match the Laravel configuration.
- **Testing & Verification:**
  - Realtime unit tests:
    ```bash
    cd realtime && npm test
    ```
  - Full MongoDB integration test (creates and deletes an isolated temporary test database):
    ```bash
    RUN_MONGO_NOTIFICATION_TESTS=1 npm test
    ```
  - Backend integration tests:
    ```bash
    php vendor/phpunit/phpunit/phpunit -c phpunit.audit.xml
    ```
  - Applying notification indexes with backup protection:
    ```bash
    php scripts/apply-notification-indexes.php
    ```

---

### Backend Deployment

Automated deployments are managed via GitHub Actions in `.github/workflows/` (triggers on push to `CompliTrack` or manual workflow dispatch).

#### Required GitHub Secrets
Configure these repository secrets under **Settings → Secrets and variables → Actions**:

| Secret | Value Description |
|---|---|
| `SERVER_IP` | Remote host domain name or public IP address |
| `SERVER_USER` | SSH username with execution privileges |
| `SERVER_SSH_KEY` | Private SSH key authorized on the target server |
| `SERVER_APP_PATH` | Absolute filesystem path to the checked-out CompliTrack repository |

#### Server Prerequisites & Deployment Pipeline
- **Host Tools:** The destination server must have Git, PHP 8.2+, Composer, Node.js 20+, and PM2 installed.
- **Environment Files:** Target server `.env` and `realtime/.env` must be provisioned prior to deployment.
- **Deployment Process:**
  1. Installs production Composer dependencies (`--no-dev --optimize-autoloader`).
  2. Executes database migrations and caches Laravel configuration/routes (`php artisan config:cache`, `route:cache`).
  3. Builds frontend assets via Vite.
  4. Installs Node.js production dependencies and restarts the `complitrack-realtime` PM2 process.
  5. Increments semantic versioning in `package.json`, `realtime/package.json`, and `mobile/pubspec.yaml` (default: patch; `#minor` or `#major` commit flags select larger increments).
  6. Dispatches the automated Flutter APK compilation workflow.

Version numbers are queryable at `GET /api/v1/version` and broadcast via the Socket.IO `server_version` event.

---

## Project Structure

### Directory Tree

```
CompliTrack/
├── .github/workflows/          # GitHub Actions CI/CD (Backend deployment & Flutter APK builds)
├── app/                        # Laravel application logic
│   ├── Http/Controllers/       # API and web controllers
│   ├── Http/Middleware/        # Role authorization and request middleware
│   ├── Models/                 # Eloquent MongoDB and SQLite models
│   └── Services/               # Business logic, encryption, and audit services
├── config/                     # Framework and package configuration files
├── database/                   # Migrations, model factories, seeders, and MongoDB init scripts
│   ├── database.sqlite         # SQLite database file for Laravel framework tables
│   └── mongodb/init.js         # MongoDB replica set and index initialization scripts
├── docs/                       # Architecture specifications, performance benchmarks, and QMS guides
├── mobile/                     # Flutter cross-platform mobile application codebase
│   ├── lib/                    # Dart source code (screens, state management, constants)
│   └── pubspec.yaml            # Flutter dependencies and build versioning
├── realtime/                   # Node.js, Express, and Socket.IO microservice
│   ├── package.json            # Node.js dependencies and test scripts
│   └── server.js               # Socket.IO connection handling and MongoDB polling loop
├── resources/                  # Frontend assets
│   ├── css/                    # Tailwind CSS 4 style definitions
│   ├── js/                     # Client JavaScript and Alpine.js modules
│   └── views/                  # Blade templates (dashboards, layouts, auth views)
├── routes/                     # Application route definitions (api.php, web.php, console.php)
├── scripts/                    # Semantic version bumping, index validation, and maintenance scripts
└── tests/                      # Unit, feature, and integration test suites
```

For complete file listings and architectural responsibilities, refer to [`FILE_STRUCTURE.md`](FILE_STRUCTURE.md).

---

### Database Collections

CompliTrack data is stored within MongoDB collections with schema validation applied via Laravel Form Requests and model rules:

- `roles` — Stores the 4 immutable system roles and their permission matrices.
- `users` — User profiles including `userCode`, `roleId`, `departmentId`, name, email, and authentication credentials.
- `departments` — Academic departments and administrative offices within USTP Claveria CET.
- `archive_documents` — Uploaded compliance evidence records, encrypted file paths, initialization vectors (`encryptionIv`), SHA-256 checksums, and reference standard tags.
- `compliance_checks` — Audit check evaluations, findings, compliance results, `correctiveStatus`, and chronological `statusHistory`.
- `notifications` — Persisted in-app alerts, read states, recipient IDs, and delivery statuses.
- `audit_logs` — Immutable audit records logging user actions (`POST`, `PATCH`, `DELETE`), timestamps, and IP addresses.

---

## Contributing

Contributions, bug reports, and suggestions are welcome! Please follow these guidelines to ensure code quality and consistency across the repository.

### Git Workflow

This project adheres to a dual-branch development model:

| Branch | Purpose & Stability |
|---|---|
| `Development` | Active working branch where new features and bug fixes are pushed and tested. Code here may be unstable. |
| `CompliTrack` | Production-ready main branch. Receives only thoroughly tested and verified code from `Development`. |

> [!CAUTION]
> Never commit or push directly to `CompliTrack`. All changes must be developed on `Development` and merged following verification.

#### Daily Development Workflow
1. Switch to and synchronize the `Development` branch:
   ```bash
   git checkout Development
   git pull origin Development
   ```
2. Make your code changes and stage them:
   ```bash
   git add .
   git commit -m "feat: concise summary of changes"
   ```
3. Push to the remote repository:
   ```bash
   git push origin Development
   ```

#### Merging Changes to `CompliTrack`
- **Option A (Recommended — Pull Request):**
  1. Push commits to `Development`.
  2. Open a Pull Request on GitHub: set base `CompliTrack` ← compare `Development`.
  3. Review the diff and complete the PR merge.
- **Option B (Local Merge):**
  ```bash
  git checkout CompliTrack
  git pull origin CompliTrack
  git merge Development
  git push origin CompliTrack
  git checkout Development
  ```

#### Git Command Quick Reference
| Command | Action |
|---|---|
| `git status` | Displays current branch and staged/unstaged changes |
| `git branch -a` | Lists all local and remote branches |
| `git remote -v` | Displays connected remote repository URLs |
| `git add .` | Stages all modified and new files |
| `git commit -m "message"` | Commits staged changes with a descriptive message |
| `git push origin <branch>` | Pushes commits to the specified remote branch |
| `git pull origin <branch>` | Fetches and integrates remote changes |
| `git checkout <branch>` | Switches to the specified branch |
| `git merge <branch>` | Merges changes from `<branch>` into the current branch |

---

### Coding Standards

Ensure all new contributions follow the established repository conventions:
- **Models:** PascalCase (e.g., `ComplianceCheck.php`, `ArchiveDocument.php`).
- **Database Fields:** camelCase (e.g., `roleId`, `departmentId`, `correctiveStatus`, `encryptionIv`).
- **API Routes:** kebab-case (e.g., `/compliance-checks`, `/forgot-password`, `/reset-password`).
- **Commits:** Clear, descriptive commit messages describing the feature or bugfix.

---

### Security & Vulnerability Reporting

#### Reporting Security Vulnerabilities
If you discover a potential security vulnerability within CompliTrack, **please do not open a public issue.** Instead, submit a private report via GitHub:
1. Navigate to the repository's **Security** tab.
2. Select **Report a vulnerability**.
3. Provide a detailed description along with reproduction steps.

#### Security Controls Matrix
The table below outlines the current status of platform security controls:

| Security Control | Implementation Status | Description |
|---|:---:|---|
| Role verification via `RoleMiddleware` | ✅ Implemented | Authorization verified solely by integer `roleId`. |
| AES-256-GCM Archive Encryption | ⏳ Planned | Per-file initialization vectors stored in `encryptionIv` (`ArchiveEncryptionService` stub). |
| SHA-256 Integrity Verification | ⏳ Planned | Checksums calculated on upload and re-verified before file download. |
| Secure Password Hashing | ⏳ Planned | User passwords hashed using bcrypt (cost factor 12). |
| Login Rate Limiting | ⏳ Planned | Throttled to 5 attempts/minute per IP + email combination (`RateLimitServiceProvider` stub). |
| MongoDB ObjectId Validation | ⏳ Planned | Strict hexadecimal format validation prior to query execution. |
| Audit Trail Logging | ⏳ Planned | System writes recorded to `audit_logs` with user ID, action, and IP (`AuditLogMiddleware` stub). |

---

### Development Roadmap

- [ ] **Phase 1 — Core Framework, Authentication & Entity Management**
  - [x] Laravel 12 + MongoDB integration, models, routes, `RoleMiddleware`, `RoleSeeder`
  - [ ] Complete auth endpoints and User / Department CRUD controllers
- [ ] **Phase 2 — Encrypted Archive Module**
  - [ ] AES-256-GCM encryption/decryption service, file upload/streaming endpoints, SHA-256 checks
- [ ] **Phase 3 — Compliance Checking & CAR Workflow**
  - [ ] Compliance check evaluation creation, status transition engine, notification triggers
- [ ] **Phase 4 — Realtime Microservice**
  - [ ] Socket.IO room broadcasting, MongoDB notification polling worker
- [ ] **Phase 5 — Role Dashboards (Web)**
  - [ ] Tailored Blade views for Super Admin, Admin (ATL), Auditor, and Auditee
- [ ] **Phase 6 — Cross-Platform Mobile Client**
  - [ ] Flutter app implementation for field auditing and mobile evidence submission
- [ ] **Phase 7 — Audit Reporting & Release**
  - [ ] Comprehensive test coverage, immutable audit logging, branded USTP PDF exports

---

## License

This project is open-source software licensed under the **[MIT License](LICENSE)**.

---

## Acknowledgments

- **USTP Claveria:** College of Engineering and Technology (CET) administration, faculty, and quality management teams.
- **[Laravel](https://laravel.com):** The PHP web framework powering the backend API gateway.
- **[Lucide Icons](https://lucide.dev):** Beautiful, consistent icon library utilized across both Web and Mobile interfaces.
- **Open-Source Community:** Special thanks to the maintainers and contributors of MongoDB, Flutter, Node.js, and Socket.IO.
