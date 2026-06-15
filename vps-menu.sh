#!/usr/bin/env bash

# vps-menu.sh - Script pengelola VPS & Website Toko VPN/Pulsa

# Ensure script is run as root
if [ "$EUID" -ne 0 ]; then
    echo -e "\e[31m[ERROR] Script ini harus dijalankan sebagai root! Gunakan: sudo ./vps-menu.sh\e[0m"
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

# Get Server Statistics
get_server_info() {
    # CPU Load
    cpu_load=$(uptime | awk -F'load average:' '{ print $2 }' | awk -F',' '{ print $1 }' | xargs)
    [ -z "$cpu_load" ] && cpu_load="0.00"
    
    # RAM
    ram_info=$(free -m | grep Mem)
    ram_used=$(echo "$ram_info" | awk '{print $3}')
    ram_total=$(echo "$ram_info" | awk '{print $2}')
    
    # Disk Usage
    disk_used=$(df -h / | awk 'NR==2 {print $5}')
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

show_dashboard() {
    get_server_info
    echo -e "========================================================="
    echo -e "      🚀 DASHBOARD MANAJEMEN VPS & WEBSITE 🚀"
    echo -e "========================================================="
    echo -e " 💻 Info Server:"
    echo -e "    • Load CPU : ${CYAN}$cpu_load${NC}"
    echo -e "    • RAM      : ${CYAN}${ram_used}MB${NC} / ${CYAN}${ram_total}MB${NC}"
    echo -e "    • Disk /   : ${CYAN}$disk_used${NC}"
    echo -e "---------------------------------------------------------"
    echo -e " [1] 🔄 Update Website dari GitHub (Git Pull & Deploy)"
    echo -e " [2] 👤 Buat Pengguna Website Baru (Laravel/WP)"
    echo -e " [3] ⚡ Cek Status Layanan (Nginx, MySQL, PHP)"
    echo -e " [4] 📊 Pantau Aktivitas Pengunjung (Access Log)"
    echo -e " [5] 💾 Backup Cepat Website (Files & DB)"
    echo -e " [6] 🐞 Lihat Log Error Website (Debugging)"
    echo -e " [7] 🤖 Konfigurasi Bot Telegram & Webhook"
    echo -e " [0] 🚪 Keluar dari Script"
    echo -e "========================================================="
}

# Opsi 1: Update Website
update_website() {
    print_info "Memulai pembaruan website..."
    if [ -f "./update.sh" ]; then
        bash ./update.sh
    else
        print_info "Mengambil perubahan terbaru via Git..."
        git pull origin main
        composer install --no-dev --optimize-autoloader --ignore-platform-reqs
        php artisan migrate --force
        php artisan optimize:clear
        print_success "Pembaruan website selesai!"
    fi
}

# Opsi 2: Buat Pengguna Baru
create_user() {
    echo -e "${BOLD}${BLUE}=== BUAT USER WEBSITE BARU ===${NC}"
    read -p "Masukkan Nama Lengkap: " name
    read -p "Masukkan Email/Username: " email
    read -sp "Masukkan Password: " password
    echo
    read -p "Masukkan Peran/Role (buyer/seller/admin) [buyer]: " role
    role=${role:-buyer}

    if [ -z "$name" ] || [ -z "$email" ] || [ -z "$password" ]; then
        print_error "Nama, email, dan password tidak boleh kosong!"
        return 1
    fi

    print_info "Membuat user baru di database..."
    
    STATS=$(php artisan tinker --execute="
        try {
            \$user = new \App\Models\User();
            \$user->name = '$name';
            \$user->email = '$email';
            \$user->password = \Illuminate\Support\Facades\Hash::make('$password');
            \$user->role = '$role';
            \$user->is_verified = 1;
            \$user->save();
            echo 'USER_CREATED';
        } catch (\Exception \$e) {
            echo 'ERR: ' . \$e->getMessage();
        }
    " 2>/dev/null)

    if [[ "$STATS" == *"USER_CREATED"* ]]; then
        print_success "User baru berhasil dibuat!"
    else
        print_error "Gagal membuat user baru: $STATS"
    fi
}

# Opsi 3: Status Layanan
show_services() {
    echo -e "${BOLD}${BLUE}=== STATUS LAYANAN VPS ===${NC}"
    check_service "nginx"
    check_service "mysql"
    check_service "$php_service"
}

# Opsi 4: Access Log
view_access_log() {
    print_info "Menampilkan 50 aktivitas pengunjung terakhir..."
    if [ -f "/var/log/nginx/access.log" ]; then
        tail -n 50 /var/log/nginx/access.log
    elif [ -f "/var/log/nginx/error.log" ]; then
        tail -n 50 /var/log/nginx/error.log
    else
        print_error "Log akses nginx tidak ditemukan."
    fi
}

# Opsi 5: Backup Files & DB
backup_website() {
    print_info "Memulai pencadangan berkas dan basis data..."
    
    # Dapatkan kredensial DB dari .env jika ada
    local db_name=""
    local db_user=""
    local db_pass=""
    if [ -f ".env" ]; then
        db_name=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2-)
        db_user=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2-)
        db_pass=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2-)
    fi

    mkdir -p backups

    if [ -n "$db_name" ] && [ -n "$db_user" ]; then
        print_info "Mem-backup database [$db_name]..."
        mysqldump -u "$db_user" -p"$db_pass" "$db_name" > backups/db_backup_$(date +%F_%H-%M).sql 2>/dev/null
        print_success "Database berhasil di-backup."
    fi

    print_info "Mem-backup file website..."
    tar -czf backups/files_backup_$(date +%F_%H-%M).tar.gz --exclude='./node_modules' --exclude='./vendor' --exclude='./backups' --exclude='./.git' .
    print_success "Berkas website berhasil di-backup."
}

# Opsi 6: Error Log
view_error_log() {
    print_info "Menampilkan log kesalahan (storage/logs/laravel.log)..."
    if [ -f "storage/logs/laravel.log" ]; then
        tail -n 50 storage/logs/laravel.log
    else
        print_warning "Log kesalahan Laravel kosong atau tidak ditemukan."
    fi
}

# Opsi 7: Konfigurasi Bot Telegram
configure_telegram() {
    echo -e "${BOLD}${BLUE}=== KONFIGURASI INTEGRASI BOT TELEGRAM ===${NC}"
    
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
}

# Main Execution Loop
while true; do
    echo
    show_dashboard
    read -p "Pilih menu [0-7]: " pilihan
    echo
    
    case "$pilihan" in
        1)
            update_website
            ;;
        2)
            create_user
            ;;
        3)
            show_services
            ;;
        4)
            view_access_log
            ;;
        5)
            backup_website
            ;;
        6)
            view_error_log
            ;;
        7)
            configure_telegram
            ;;
        0)
            print_info "Keluar dari panel pengelola."
            exit 0
            ;;
        *)
            print_warning "Pilihan tidak valid. Silakan pilih 0-7."
            ;;
    esac
    
    echo
    read -p "Tekan [Enter] untuk kembali ke Dashboard..." temp
done
