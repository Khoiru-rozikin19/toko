#!/usr/bin/env bash

# tokoctl.sh - Script pengelola website Toko VPN & Pulsa di VPS

# Ensure script is run as root
if [ "$EUID" -ne 0 ]; then
    echo -e "\e[31m[ERROR] Script ini harus dijalankan sebagai root! Gunakan: sudo ./tokoctl.sh\e[0m"
    exit 1
fi

# Change directory to the script folder
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

# Formatting colors
RED='\e[31m'
GREEN='\e[32m'
YELLOW='\e[33m'
BLUE='\e[34m'
CYAN='\e[36m'
WHITE='\e[37m'
BOLD='\e[1m'
NC='\e[0m' # No Color

print_info() {
    echo -e "${BLUE}[INFO]${NC} $1"
}

print_success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Auto-detect PHP-FPM service
php_service="php8.3-fpm"
if command -v systemctl >/dev/null 2>&1; then
    if systemctl list-units --type=service | grep -q "php.*-fpm"; then
        php_service=$(systemctl list-units --type=service | grep -o "php.*-fpm" | head -n 1)
    fi
fi

check_service() {
    local service=$1
    if command -v systemctl >/dev/null 2>&1; then
        if systemctl is-active "$service" >/dev/null 2>&1; then
            echo -e "  ${GREEN}●${NC} $service: ${GREEN}Active (Running)${NC}"
        else
            echo -e "  ${RED}●${NC} $service: ${RED}Inactive (Stopped)${NC}"
        fi
    else
        echo -e "  ${YELLOW}●${NC} $service: ${YELLOW}Systemctl tidak tersedia (Local/Non-systemd)${NC}"
    fi
}

show_status() {
    echo -e "${BOLD}${BLUE}==================================================${NC}"
    echo -e "${BOLD}${BLUE}          STATUS WEBSITE & LAYANAN (VPS)          ${NC}"
    echo -e "${BOLD}${BLUE}==================================================${NC}"
    
    echo -e "${BOLD}${WHITE}[1] Layanan Sistem:${NC}"
    check_service "nginx"
    check_service "$php_service"
    check_service "mysql"
    
    echo
    echo -e "${BOLD}${WHITE}[2] Status Laravel:${NC}"
    if [ -f ".env" ]; then
        APP_ENV=$(grep "^APP_ENV=" .env | cut -d'=' -f2-)
        APP_DEBUG=$(grep "^APP_DEBUG=" .env | cut -d'=' -f2-)
        TELEGRAM_BOT=$(grep "^TELEGRAM_BOT_TOKEN=" .env | cut -d'=' -f2-)
        
        echo -e "  Environment:   ${CYAN}$APP_ENV${NC}"
        echo -e "  Debug Mode:    ${CYAN}$APP_DEBUG${NC}"
        if [ -n "$TELEGRAM_BOT" ]; then
            echo -e "  Bot Telegram:  ${GREEN}Terkonfigurasi${NC}"
        else
            echo -e "  Bot Telegram:  ${RED}Belum Terkonfigurasi!${NC}"
        fi
    else
        echo -e "  .env file:     ${RED}Tidak Ditemukan!${NC}"
    fi
    
    if [ -f "bootstrap/cache/config.php" ]; then
        echo -e "  Config Cache:  ${GREEN}Cached${NC}"
    else
        echo -e "  Config Cache:  ${YELLOW}Not Cached${NC}"
    fi
    
    if [ -f "bootstrap/cache/routes-v7.php" ] || [ -d "bootstrap/cache/routes" ]; then
        echo -e "  Route Cache:   ${GREEN}Cached${NC}"
    else
        echo -e "  Route Cache:   ${YELLOW}Not Cached${NC}"
    fi
    
    echo
    echo -e "${BOLD}${WHITE}[3] Statistik Transaksi (Orders):${NC}"
    STATS=$(php artisan tinker --execute='
        try {
            echo App\Models\Order::count() . "|" . 
                 App\Models\Order::where("status", "pending_manual")->count() . "|" . 
                 App\Models\Order::where("status", "paid")->count() . "|" . 
                 App\Models\Order::where("status", "success")->count() . "|" . 
                 App\Models\Order::where("status", "rejected")->count() . "|" . 
                 App\Models\Order::where("status", "expired")->count();
        } catch (\Exception $e) {
            echo "ERROR";
        }
    ' 2>/dev/null)
    
    if [ "$STATS" = "ERROR" ] || [ -z "$STATS" ]; then
        echo -e "  ${RED}Gagal mengambil statistik database (mungkin tabel belum dimigrasi atau cache belum bersih).${NC}"
    else
        IFS='|' read -r total pending_manual paid success rejected expired <<< "$STATS"
        echo -e "  Total Pesanan  : ${BOLD}${WHITE}$total${NC}"
        echo -e "  Pending Manual : ${YELLOW}$pending_manual${NC} (Menunggu Verifikasi)"
        echo -e "  Paid           : ${CYAN}$paid${NC} (Sudah Dibayar & Diproses H2H)"
        echo -e "  Sukses (Selesai): ${GREEN}$success${NC}"
        echo -e "  Ditolak        : ${RED}$rejected${NC}"
        echo -e "  Kedaluwarsa    : ${WHITE}$expired${NC}"
    fi
    
    echo -e "${BOLD}${BLUE}==================================================${NC}"
}

update_website() {
    print_info "Menjalankan script update website..."
    if [ -f "./update.sh" ]; then
        bash ./update.sh
    else
        print_error "Berkas update.sh tidak ditemukan!"
    fi
}

configure_telegram() {
    echo -e "${BOLD}${BLUE}==================================================${NC}"
    echo -e "${BOLD}${BLUE}       KONFIGURASI INTEGRASI BOT TELEGRAM         ${NC}"
    echo -e "${BOLD}${BLUE}==================================================${NC}"
    
    # Ambil konfigurasi lama dari .env jika ada
    local old_token=""
    local old_admin=""
    local old_domain=""
    if [ -f ".env" ]; then
        old_token=$(grep "^TELEGRAM_BOT_TOKEN=" .env | cut -d'=' -f2-)
        old_admin=$(grep "^TELEGRAM_ADMIN_ID=" .env | cut -d'=' -f2-)
        old_domain=$(grep "^APP_URL=" .env | cut -d'=' -f2- | sed -E 's|https?://||')
    fi

    # Input Token Bot
    if [ -n "$old_token" ]; then
        read -p "Masukkan Telegram Bot Token [$old_token]: " bot_token
        bot_token=${bot_token:-$old_token}
    else
        read -p "Masukkan Telegram Bot Token: " bot_token
    fi

    # Input Admin ID
    if [ -n "$old_admin" ]; then
        read -p "Masukkan Telegram Admin ID [$old_admin]: " admin_id
        admin_id=${admin_id:-$old_admin}
    else
        read -p "Masukkan Telegram Admin ID: " admin_id
    fi

    # Input Domain
    if [ -n "$old_domain" ]; then
        read -p "Masukkan Domain Website (tanpa https://, contoh: vpn.domainanda.com) [$old_domain]: " web_domain
        web_domain=${web_domain:-$old_domain}
    else
        read -p "Masukkan Domain Website (tanpa https://, contoh: vpn.domainanda.com): " web_domain
    fi

    if [ -z "$bot_token" ] || [ -z "$admin_id" ] || [ -z "$web_domain" ]; then
        print_error "Token, Admin ID, dan Domain tidak boleh kosong!"
        return 1
    fi

    print_info "Menyimpan konfigurasi ke file .env..."
    
    # Update atau Tambahkan ke .env
    for key in TELEGRAM_BOT_TOKEN TELEGRAM_ADMIN_ID; do
        if grep -q "^$key=" .env; then
            sed -i "/^$key=/d" .env
        fi
    done
    echo "TELEGRAM_BOT_TOKEN=$bot_token" >> .env
    echo "TELEGRAM_ADMIN_ID=$admin_id" >> .env

    # Pastikan APP_URL terupdate dengan domain baru
    if grep -q "^APP_URL=" .env; then
        sed -i "s|^APP_URL=.*|APP_URL=https://$web_domain|g" .env
    fi

    print_success "Konfigurasi bot disimpan di .env!"

    # Hapus cache konfigurasi Laravel
    print_info "Membersihkan cache konfigurasi Laravel..."
    php artisan optimize:clear >/dev/null 2>&1

    # Daftarkan webhook ke Telegram
    print_info "Mendaftarkan Webhook ke Telegram API..."
    local webhook_url="https://$web_domain/webhook/telegram"
    local register_response
    register_response=$(curl -s -w "\nHTTP_STATUS:%{http_code}" "https://api.telegram.org/bot${bot_token}/setWebhook?url=${webhook_url}")
    
    local response_body
    response_body=$(echo "$register_response" | sed '/HTTP_STATUS:/d')
    local http_status
    http_status=$(echo "$register_response" | grep "HTTP_STATUS:" | cut -d':' -f2)

    if [ "$http_status" -eq 200 ] && echo "$response_body" | grep -q '"ok":true'; then
        print_success "Webhook Telegram berhasil didaftarkan ke: $webhook_url"
    else
        print_error "Gagal mendaftarkan Webhook! Respons dari Telegram: $response_body"
        print_warning "Pastikan token bot Anda valid dan server Anda sudah online menggunakan HTTPS."
    fi
    echo -e "${BOLD}${BLUE}==================================================${NC}"
}

show_help() {
    echo "Penggunaan: sudo ./tokoctl.sh [opsi]"
    echo
    echo "Opsi:"
    echo "  update            Update website dari Git repo, install dependencies, migrasi & cache"
    echo "  telegram-config   Konfigurasi Token Bot Telegram, Chat ID, dan Set Webhook"
    echo "  status            Tampilkan status layanan VPS (Nginx, PHP, MySQL) dan statistik toko"
    echo "  help              Tampilkan bantuan ini"
    echo
    echo "Jika dijalankan tanpa opsi, akan menampilkan menu interaktif."
}

# Parse command line arguments
case "$1" in
    update)
        update_website
        exit $?
        ;;
    telegram-config)
        configure_telegram
        exit $?
        ;;
    status)
        show_status
        exit $?
        ;;
    help|--help|-h)
        show_help
        exit 0
        ;;
    "")
        # Interactive mode
        while true; do
            echo
            echo -e "${BOLD}${BLUE}--------------------------------------------------${NC}"
            echo -e "${BOLD}${BLUE}          PANEL PENGELOLA TOKO VPN & PULSA        ${NC}"
            echo -e "${BOLD}${BLUE}--------------------------------------------------${NC}"
            echo -e " 1. Update Website dari GitHub"
            echo -e " 2. Konfigurasi Bot Telegram & Set Webhook"
            echo -e " 3. Cek Status Website & Transaksi"
            echo -e " 4. Keluar"
            echo -e "${BOLD}${BLUE}--------------------------------------------------${NC}"
            read -p "Pilih opsi [1-4]: " pilihan
            echo
            
            case "$pilihan" in
                1)
                    update_website
                    ;;
                2)
                    configure_telegram
                    ;;
                3)
                    show_status
                    ;;
                4)
                    print_info "Keluar dari panel pengelola."
                    exit 0
                    ;;
                *)
                    print_warning "Pilihan tidak valid. Silakan pilih 1-4."
                    ;;
            esac
        done
        ;;
    *)
        print_error "Opsi tidak dikenal: $1"
        show_help
        exit 1
        ;;
esac
