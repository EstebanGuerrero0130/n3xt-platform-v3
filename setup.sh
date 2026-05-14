#!/usr/bin/env bash
# ============================================================
# N3XT Platform V3 — Automated Setup Script
# ============================================================
# Usage: chmod +x setup.sh && ./setup.sh
# ============================================================

set -euo pipefail

# ── Colors ─────────────────────────────────────────────────
RED='\033[0;31m'
GREEN='\033[0;32m'
CYAN='\033[0;36m'
YELLOW='\033[1;33m'
BOLD='\033[1m'
NC='\033[0m'

print_header() {
    echo ""
    echo -e "${CYAN}╔══════════════════════════════════════════════════╗${NC}"
    echo -e "${CYAN}║${NC}  ${BOLD}N3XT Platform V3 — Setup Script${NC}               ${CYAN}║${NC}"
    echo -e "${CYAN}║${NC}  Industrial 3D Manufacturing System             ${CYAN}║${NC}"
    echo -e "${CYAN}╚══════════════════════════════════════════════════╝${NC}"
    echo ""
}

print_step() {
    echo -e "\n${GREEN}▸${NC} ${BOLD}$1${NC}"
}

print_warn() {
    echo -e "  ${YELLOW}⚠${NC} $1"
}

print_ok() {
    echo -e "  ${GREEN}✓${NC} $1"
}

print_error() {
    echo -e "  ${RED}✗${NC} $1"
}

# ── Preflight Checks ──────────────────────────────────────
check_dependencies() {
    print_step "Checking dependencies..."

    local missing=0

    if command -v php &> /dev/null; then
        local php_version=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
        print_ok "PHP $php_version found"
    else
        print_error "PHP not found (requires >= 8.3)"
        missing=1
    fi

    if command -v composer &> /dev/null; then
        print_ok "Composer $(composer --version 2>/dev/null | grep -oP '\d+\.\d+\.\d+' | head -1) found"
    else
        print_error "Composer not found"
        missing=1
    fi

    if command -v node &> /dev/null; then
        print_ok "Node $(node --version) found"
    else
        print_error "Node.js not found (requires >= 20)"
        missing=1
    fi

    if command -v npm &> /dev/null; then
        print_ok "npm $(npm --version) found"
    else
        print_error "npm not found"
        missing=1
    fi

    if [ $missing -eq 1 ]; then
        echo ""
        print_error "Missing dependencies. Please install them and try again."
        exit 1
    fi
}

# ── Backend Setup ─────────────────────────────────────────
setup_backend() {
    print_step "Setting up Backend (Laravel 13)..."

    cd backend

    # Install PHP dependencies
    echo -e "  Installing Composer packages..."
    composer install --no-interaction --prefer-dist --optimize-autoloader --no-progress 2>&1 | tail -1
    print_ok "Composer packages installed"

    # Environment file
    if [ ! -f .env ]; then
        cp .env.example .env
        print_ok ".env created from .env.example"
    else
        print_warn ".env already exists, skipping"
    fi

    # Application key
    php artisan key:generate --ansi --no-interaction 2>/dev/null
    print_ok "Application key generated"

    # Storage directories
    php artisan storage:link 2>/dev/null || true
    mkdir -p storage/app/public/stl_files
    mkdir -p storage/app/stl_temp
    print_ok "Storage directories created"

    cd ..
}

# ── Frontend Setup ────────────────────────────────────────
setup_frontend() {
    print_step "Setting up Frontend (Vue 3 + Vite 8)..."

    cd frontend

    # Install Node dependencies
    echo -e "  Installing npm packages..."
    npm install --no-fund --no-audit 2>&1 | tail -3
    print_ok "npm packages installed"

    # Environment file for local dev
    if [ ! -f .env.local ]; then
        echo "VITE_API_URL=http://localhost:8000/api" > .env.local
        print_ok ".env.local created (API → localhost:8000)"
    else
        print_warn ".env.local already exists, skipping"
    fi

    # Verify build
    echo -e "  Verifying production build..."
    npm run build 2>&1 | tail -3
    print_ok "Production build successful"

    cd ..
}

# ── Database Info ─────────────────────────────────────────
show_database_info() {
    print_step "Database Configuration"

    echo ""
    echo -e "  ${BOLD}For Supabase, update backend/.env with:${NC}"
    echo ""
    echo -e "  ${CYAN}DB_CONNECTION${NC}=pgsql"
    echo -e "  ${CYAN}DB_HOST${NC}=aws-0-us-east-1.pooler.supabase.com"
    echo -e "  ${CYAN}DB_PORT${NC}=6543"
    echo -e "  ${CYAN}DB_DATABASE${NC}=postgres"
    echo -e "  ${CYAN}DB_USERNAME${NC}=postgres.YOUR_PROJECT_REF"
    echo -e "  ${CYAN}DB_PASSWORD${NC}=YOUR_SUPABASE_PASSWORD"
    echo ""
    echo -e "  ${BOLD}Then run the schema:${NC}"
    echo -e "  Execute ${CYAN}backend/database/supabase_schema.sql${NC} in Supabase SQL Editor"
    echo -e "  Execute ${CYAN}backend/database/supabase_seed.sql${NC} for initial data"
}

# ── Vercel Info ───────────────────────────────────────────
show_vercel_info() {
    print_step "Vercel Deployment"

    echo ""
    echo -e "  ${BOLD}Frontend is pre-configured for Vercel:${NC}"
    echo -e "  • Root Directory: ${CYAN}frontend${NC}"
    echo -e "  • Build Command:  ${CYAN}npm run build${NC}"
    echo -e "  • Output Dir:     ${CYAN}dist${NC}"
    echo -e "  • Env Variable:   ${CYAN}VITE_API_URL${NC} → your backend URL"
    echo ""
    echo -e "  Config file: ${CYAN}frontend/vercel.json${NC} (SPA rewrites included)"
}

# ── Final Summary ─────────────────────────────────────────
show_summary() {
    echo ""
    echo -e "${GREEN}╔══════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║${NC}  ${BOLD}✅ Setup Complete!${NC}                              ${GREEN}║${NC}"
    echo -e "${GREEN}╚══════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "  ${BOLD}Start developing:${NC}"
    echo ""
    echo -e "  ${CYAN}Backend:${NC}   cd backend && php artisan serve"
    echo -e "  ${CYAN}Frontend:${NC}  cd frontend && npm run dev"
    echo ""
    echo -e "  ${BOLD}Access:${NC}"
    echo -e "  • Frontend:  ${CYAN}http://localhost:5173${NC}"
    echo -e "  • Backend:   ${CYAN}http://localhost:8000${NC}"
    echo -e "  • Admin:     ${CYAN}http://localhost:5173/admin/login${NC}"
    echo ""
    echo -e "  ${BOLD}N3XT 3D${NC} — Advanced Digital Manufacturing 🇨🇴"
    echo ""
}

# ── Main ──────────────────────────────────────────────────
main() {
    print_header
    check_dependencies
    setup_backend
    setup_frontend
    show_database_info
    show_vercel_info
    show_summary
}

main "$@"
