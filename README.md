<p align="center">
  <img src="frontend/public/favicon.svg" width="80" alt="N3XT 3D Logo" />
</p>

<h1 align="center">N3XT Platform V3</h1>

<p align="center">
  <strong>Industrial-Grade 3D Manufacturing Management System</strong><br/>
  <sub>Automated quoting engine · Real-time STL visualization · Full production pipeline</sub>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?style=flat-square&logo=laravel&logoColor=white" alt="Laravel 13" />
  <img src="https://img.shields.io/badge/Vue.js-3.5-4FC08D?style=flat-square&logo=vuedotjs&logoColor=white" alt="Vue 3.5" />
  <img src="https://img.shields.io/badge/Vite-8-646CFF?style=flat-square&logo=vite&logoColor=white" alt="Vite 8" />
  <img src="https://img.shields.io/badge/TailwindCSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white" alt="Tailwind 4" />
  <img src="https://img.shields.io/badge/Three.js-r184-000000?style=flat-square&logo=threedotjs&logoColor=white" alt="Three.js" />
  <img src="https://img.shields.io/badge/PostgreSQL-Supabase-3FCF8E?style=flat-square&logo=supabase&logoColor=white" alt="Supabase" />
  <img src="https://img.shields.io/badge/Deploy-Vercel-000000?style=flat-square&logo=vercel&logoColor=white" alt="Vercel" />
</p>

<p align="center">
  <img src="https://img.shields.io/github/license/migel/n3xt-platform-v3?style=flat-square" alt="License" />
  <img src="https://img.shields.io/badge/PHP-8.3+-777BB4?style=flat-square&logo=php&logoColor=white" alt="PHP 8.3+" />
  <img src="https://img.shields.io/badge/Node-20+-339933?style=flat-square&logo=nodedotjs&logoColor=white" alt="Node 20+" />
</p>

---

## 🏭 Overview

**N3XT Platform V3** is a full-stack manufacturing management system designed for professional 3D printing workshops. It combines an intelligent quoting engine with real-time production tracking, inventory management, and a premium customer-facing storefront.

### ⚡ Key Features

| Module | Description |
|--------|-------------|
| **OrcaEngine Quoting** | Automated cost calculation with configurable parameters for infrastructure, labor, margins, and materials |
| **STL Visualizer** | Browser-based 3D model viewer powered by Three.js with real-time volume/area analysis |
| **Kanban Production** | Drag-and-drop order pipeline: `Pending → Printing → Completed → Shipped` |
| **Machine Monitor** | Real-time printer status tracking with maintenance scheduling and hour logging |
| **Inventory System** | Material stock management with automatic deduction on order completion |
| **Accounting Dashboard** | Revenue analytics, purchase logging, and profit/loss visualization with Chart.js |
| **Customer Portal** | Self-service order tracking, registration, and project history |
| **Catalog CMS** | Dynamic product catalog with Cloudinary image optimization |

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                        VERCEL (CDN)                             │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │              Vue 3 SPA + Vite 8 + Tailwind 4             │  │
│  │  ┌──────────┐ ┌──────────┐ ┌──────────┐ ┌─────────────┐ │  │
│  │  │HomeView  │ │QuoteView │ │CatalogView│ │AdminDashboard│ │  │
│  │  │          │ │+STL View │ │           │ │  +Kanban     │ │  │
│  │  └──────────┘ └──────────┘ └──────────┘ └─────────────┘ │  │
│  │              vue-router (History Mode)                    │  │
│  └────────────────────────┬──────────────────────────────────┘  │
└───────────────────────────┼─────────────────────────────────────┘
                            │ HTTPS (Axios)
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                    BACKEND SERVER (PHP)                          │
│  ┌───────────────────────────────────────────────────────────┐  │
│  │                 Laravel 13 + Sanctum                      │  │
│  │  ┌──────────────┐ ┌────────────────┐ ┌────────────────┐  │  │
│  │  │ AuthController│ │OrderController │ │SettingController│  │  │
│  │  │ +Unified Auth│ │ +OrcaEngine   │ │ +JSON Config   │  │  │
│  │  └──────────────┘ └────────────────┘ └────────────────┘  │  │
│  │  ┌──────────────┐ ┌────────────────┐ ┌────────────────┐  │  │
│  │  │MaterialCtrl  │ │PrinterCtrl    │ │PurchaseCtrl   │  │  │
│  │  └──────────────┘ └────────────────┘ └────────────────┘  │  │
│  └────────────────────────┬──────────────────────────────────┘  │
└───────────────────────────┼─────────────────────────────────────┘
                            │ PostgreSQL (Port 6543)
                            ▼
┌─────────────────────────────────────────────────────────────────┐
│                     SUPABASE (Database)                          │
│  ┌─────────────────────────────────────────────────────────┐    │
│  │  PostgreSQL 15 — 19 Tables — Pooler Connection          │    │
│  │  users · orders · materials · inventories · printers    │    │
│  │  purchases · settings · recurrent_customers · ...       │    │
│  └─────────────────────────────────────────────────────────┘    │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📂 Project Structure

```
n3xt-platform-v3/
├── .github/
│   ├── workflows/
│   │   └── ci.yml                    # CI/CD Pipeline
│   ├── ISSUE_TEMPLATE/
│   │   └── bug_report.md             # Bug report template
│   └── PULL_REQUEST_TEMPLATE.md      # PR template
│
├── backend/                          # Laravel 13 API
│   ├── app/
│   │   ├── Http/Controllers/         # 9 REST controllers
│   │   ├── Models/                   # 9 Eloquent models
│   │   ├── Providers/                # Service providers
│   │   └── Traits/                   # Shared traits
│   ├── config/                       # App configuration
│   ├── database/
│   │   ├── migrations/               # 34 migration files
│   │   ├── seeders/                  # Database seeders
│   │   ├── supabase_schema.sql       # Consolidated PG schema
│   │   └── supabase_seed.sql         # Initial data
│   ├── routes/
│   │   ├── api.php                   # API routes (Sanctum)
│   │   └── web.php                   # Web routes
│   ├── tests/                        # PHPUnit tests
│   ├── .env.example                  # Environment template
│   ├── composer.json                 # PHP dependencies
│   └── phpunit.xml                   # Test configuration
│
├── frontend/                         # Vue 3 SPA
│   ├── public/
│   │   ├── favicon.svg               # App icon
│   │   ├── manifest.json             # PWA manifest
│   │   ├── robots.txt                # SEO crawlers
│   │   └── sitemap.xml               # SEO sitemap
│   ├── src/
│   │   ├── components/
│   │   │   ├── admin/                # 9 admin modules
│   │   │   │   ├── AccountingDashboard.vue
│   │   │   │   ├── ContactManager.vue
│   │   │   │   ├── DiscountManager.vue
│   │   │   │   ├── InventoryManager.vue
│   │   │   │   ├── KanbanBoard.vue
│   │   │   │   ├── MachineMonitor.vue
│   │   │   │   ├── OrderCard.vue
│   │   │   │   ├── OrderHistory.vue
│   │   │   │   └── PurchaseLog.vue
│   │   │   ├── AppNavbar.vue         # Global navigation
│   │   │   ├── AppFooter.vue         # Global footer
│   │   │   ├── StlViewer.vue         # Three.js 3D viewer
│   │   │   └── WhatsAppWidget.vue    # Support widget
│   │   ├── views/
│   │   │   ├── HomeView.vue          # Landing page
│   │   │   ├── QuoteView.vue         # OrcaEngine quoter
│   │   │   ├── CatalogView.vue       # Product catalog
│   │   │   ├── AdminDashboard.vue    # Admin control panel
│   │   │   ├── CustomerDashboard.vue # Customer portal
│   │   │   ├── LoginView.vue         # Unified auth
│   │   │   ├── TrackView.vue         # Order tracking
│   │   │   ├── ProductDetailView.vue # Product detail
│   │   │   └── ProjectInitView.vue   # Contact / intake
│   │   ├── router/index.js           # Vue Router (History)
│   │   ├── services/api.js           # Axios API layer
│   │   ├── style.css                 # Design system
│   │   ├── App.vue                   # Root component
│   │   └── main.js                   # Entry point
│   ├── index.html                    # SPA shell + CSP
│   ├── vercel.json                   # Vercel deployment config
│   ├── vite.config.js                # Build configuration
│   └── package.json                  # Node dependencies
│
├── .gitignore                        # Git exclusions
├── setup.sh                          # Automated setup script
├── LICENSE                           # Project license
└── README.md                         # This file
```

---

## 🚀 Quick Start

### Prerequisites

| Tool | Version | Purpose |
|------|---------|---------|
| PHP | ≥ 8.3 | Backend runtime |
| Composer | ≥ 2.7 | PHP dependency manager |
| Node.js | ≥ 20 | Frontend toolchain |
| npm | ≥ 10 | Node package manager |

### Automated Setup

```bash
# Clone the repository
git clone https://github.com/migel/n3xt-platform-v3.git
cd n3xt-platform-v3

# Run the setup script (installs everything)
chmod +x setup.sh
./setup.sh
```

### Manual Setup

#### Backend (Laravel)

```bash
cd backend

# Install dependencies
composer install

# Configure environment
cp .env.example .env
php artisan key:generate

# Configure your Supabase credentials in .env:
# DB_CONNECTION=pgsql
# DB_HOST=aws-0-us-east-1.pooler.supabase.com
# DB_PORT=6543
# DB_DATABASE=postgres
# DB_USERNAME=postgres.YOUR_PROJECT_REF
# DB_PASSWORD=YOUR_SUPABASE_PASSWORD

# Run migrations
php artisan migrate

# Start development server
php artisan serve
```

#### Frontend (Vue)

```bash
cd frontend

# Install dependencies
npm install

# Configure API URL
# Create .env.local with:
# VITE_API_URL=http://localhost:8000/api

# Start development server
npm run dev
```

### Vercel Deployment

The frontend is pre-configured for Vercel. Simply:

1. Import the repository in [vercel.com](https://vercel.com)
2. Set **Root Directory** to `frontend`
3. Set **Build Command** to `npm run build`
4. Set **Output Directory** to `dist`
5. Add environment variable: `VITE_API_URL` → your backend URL

---

## 🔐 Security

- **Content Security Policy** (CSP) with strict directives
- **X-Frame-Options: DENY** — clickjacking protection
- **Honeypot fields** — bot detection on all forms
- **Rate limiting** — brute-force protection on auth endpoints
- **Math CAPTCHA** — human verification on registration & orders
- **Laravel Sanctum** — token-based API authentication
- **Security Nonce** — request integrity validation

---

## 🗺️ Roadmap

- [x] **V1** — Core quoting engine + order management
- [x] **V2** — Admin dashboard + inventory + accounting
- [x] **V3** — Vercel deployment + Supabase migration + Customer portal
- [ ] **V3.1** — Email notifications (Resend/Mailgun integration)
- [ ] **V3.2** — PDF invoice generation with company branding
- [ ] **V3.3** — Multi-language support (EN/ES)
- [ ] **V4.0** — Supabase Auth integration (replace Sanctum)
- [ ] **V4.1** — Real-time order updates via Supabase Realtime
- [ ] **V4.2** — Mobile PWA with offline support

---

## 🛠️ Tech Stack

| Layer | Technology | Version |
|-------|-----------|---------|
| **Frontend** | Vue.js | 3.5 |
| **Build** | Vite | 8.0 |
| **Styling** | Tailwind CSS | 4.3 |
| **3D Engine** | Three.js | r184 |
| **Charts** | Chart.js + vue-chartjs | 4.5 |
| **Drag & Drop** | vuedraggable | 4.1 |
| **Backend** | Laravel | 13.0 |
| **Auth** | Laravel Sanctum | 4.0 |
| **Database** | PostgreSQL (Supabase) | 15 |
| **Hosting** | Vercel (Frontend) | — |
| **PHP Runtime** | PHP | 8.3 |

---

## 📄 License

This project is proprietary software. All rights reserved by **N3XT 3D**.

---

<p align="center">
  <sub>Built with precision by <strong>N3XT 3D</strong> — Advanced Digital Manufacturing 🇨🇴</sub>
</p>
