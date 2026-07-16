#!/usr/bin/env bash

# =====================================================================
#             🚀 TOKO VPN & PULSA VPS MANAGEMENT CONTROL 🚀
# =====================================================================
# Script pengelola VPS & Website Toko VPN/Pulsa secara instan.
# Dibuat untuk kenyamanan penuh administrator dalam satu perintah "set".
# Menggabungkan: tokoctl.sh, update.sh, akun.sh, duid.sh
# =====================================================================

# 1. Eskalasi Hak Akses Root Otomatis (Auto-Sudo)
if [ "$EUID" -ne 0 ]; then
    echo -e "\e[33m[INFO] Script ini memerlukan akses root. Mengalihkan menggunakan sudo...\e[0m"
    exec sudo bash "$0" "$@"
fi

# Pindah ke direktori script dijalankan
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"

# Deteksi direktori project Laravel secara otomatis
# Prioritas: 1) /var/www/toko, 2) Direktori script (jika ada artisan), 3) /var/www/html, 4) Direktori script
PROJECT_DIR=""
if [ -f "/var/www/toko/artisan" ]; then
    PROJECT_DIR="/var/www/toko"
elif [ -f "$SCRIPT_DIR/artisan" ]; then
    PROJECT_DIR="$SCRIPT_DIR"
elif [ -f "/var/www/html/artisan" ]; then
    PROJECT_DIR="/var/www/html"
else
    PROJECT_DIR="$SCRIPT_DIR"
fi

cd "$PROJECT_DIR" || { echo -e "\e[31m[ERROR] Gagal masuk ke direktori project: $PROJECT_DIR\e[0m"; exit 1; }

# Tambahkan project dir ke safe.directory git untuk mencegah error ownership saat dijalankan sebagai root
if command -v git &>/dev/null; then
    git config --global --add safe.directory "$PROJECT_DIR" 2>/dev/null || true
fi

# =====================================================================
#  2. Definisi Warna ANSI untuk Tampilan Premium (Aesthetics)
# =====================================================================
RED='\e[31m'
GREEN='\e[32m'
YELLOW='\e[33m'
BLUE='\e[34m'
MAGENTA='\e[35m'
CYAN='\e[36m'
WHITE='\e[37m'
BOLD='\e[1m'
DIM='\e[2m'
NC='\e[0m' # No Color

# =====================================================================
#  3. Fungsi Helper Output
# =====================================================================
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

print_border() {
    echo -e "${CYAN}=====================================================================${NC}"
}

print_header() {
    print_border
    echo -e "      ${BOLD}${GREEN}🚀   DASHBOARD MANAJEMEN VPS & WEBSITE   🚀${NC}"
    print_border
}

garis() {
    echo -e "${DIM}──────────────────────────────────────────────────────────────────────────────────────────${NC}"
}

garis_tebal() {
    echo -e "${CYAN}══════════════════════════════════════════════════════════════════════════════════════════${NC}"
}

# Fungsi Helper: Menjalankan PHP Tinker
execute_tinker() {
    local php_code="$1"
    php artisan tinker --execute="$php_code" 2>/dev/null
}

# =====================================================================
#  4. Fungsi Database (Abstraksi SQLite / MySQL) - dari duid.sh
# =====================================================================
# Variabel DB global (diinisialisasi saat menu saldo diakses)
DUID_DB_INITIALIZED=false

duid_get_env() {
    local key="$1"
    grep -E "^${key}=" "$PROJECT_DIR/.env" | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'" | tr -d $'\r'
}

duid_init_db() {
    if [ "$DUID_DB_INITIALIZED" = true ]; then
        return 0
    fi

    if [ ! -f "$PROJECT_DIR/.env" ]; then
        print_error "File .env tidak ditemukan di: $PROJECT_DIR/.env"
        return 1
    fi

    DB_CONNECTION=$(duid_get_env "DB_CONNECTION")
    DB_HOST=$(duid_get_env "DB_HOST")
    DB_PORT=$(duid_get_env "DB_PORT")
    DB_DATABASE=$(duid_get_env "DB_DATABASE")
    DB_USERNAME=$(duid_get_env "DB_USERNAME")
    DB_PASSWORD=$(duid_get_env "DB_PASSWORD")

    [ -z "$DB_CONNECTION" ] && DB_CONNECTION="sqlite"
    [ -z "$DB_HOST" ] && DB_HOST="127.0.0.1"
    [ -z "$DB_PORT" ] && DB_PORT="3306"

    # Test koneksi
    local result
    result=$(db_query "SELECT COUNT(*) FROM users;" 2>/dev/null)
    if [ $? -ne 0 ] || [ -z "$result" ]; then
        print_error "Gagal terhubung ke database ($DB_CONNECTION)."
        return 1
    fi

    DUID_DB_INITIALIZED=true
    return 0
}

db_query() {
    local sql="$1"

    if [ "$DB_CONNECTION" = "sqlite" ]; then
        local db_path="$PROJECT_DIR/database/database.sqlite"
        if [ ! -f "$db_path" ]; then
            echo -e "${RED}✗ Database SQLite tidak ditemukan di: $db_path${NC}" >&2
            return 1
        fi
        sqlite3 -separator '|' "$db_path" "$sql" 2>/dev/null

    elif [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "mariadb" ]; then
        if ! command -v mysql &> /dev/null; then
            echo -e "${RED}✗ mysql client tidak ditemukan.${NC}" >&2
            return 1
        fi

        local mysql_opts="-h $DB_HOST -P $DB_PORT -u $DB_USERNAME --batch --skip-column-names -N"
        if [ -n "$DB_PASSWORD" ]; then
            mysql_opts="$mysql_opts -p$DB_PASSWORD"
        fi

        mysql $mysql_opts "$DB_DATABASE" -e "$sql" 2>/dev/null | tr '\t' '|'
    else
        echo -e "${RED}✗ DB_CONNECTION '$DB_CONNECTION' tidak didukung.${NC}" >&2
        return 1
    fi
}

format_rupiah() {
    local amount="$1"
    local integer_part=$(echo "$amount" | awk '{printf "%d", $1}')
    echo "Rp $(echo "$integer_part" | sed ':a;s/\B[0-9]\{3\}\>/.\&/;ta')"
}

# =====================================================================
#  5. Pengumpulan Informasi Statistik Server
# =====================================================================
get_server_info() {
    # CPU Load
    if [ -f /proc/loadavg ]; then
        cpu_load=$(awk '{print $1}' /proc/loadavg)
    else
        cpu_load=$(uptime 2>/dev/null | awk -F'load average:' '{ print $2 }' | awk -F',' '{ print $1 }' | xargs)
        [ -z "$cpu_load" ] && cpu_load="0.00"
    fi
    
    # RAM Usage
    if command -v free >/dev/null 2>&1; then
        local ram_info=$(free -m | grep Mem)
        ram_used=$(echo "$ram_info" | awk '{print $3}')
        ram_total=$(echo "$ram_info" | awk '{print $2}')
    else
        ram_used="0"
        ram_total="0"
    fi
    
    # Disk Usage
    if command -v df >/dev/null 2>&1; then
        disk_used=$(df -h / | awk 'NR==2 {print $5}')
    else
        disk_used="0%"
    fi
}

show_dashboard() {
    get_server_info
    print_border
    echo -e "      ${BOLD}${GREEN}🚀   DASHBOARD MANAJEMEN VPS & WEBSITE   🚀${NC}"
    print_border
    echo -e "  ${BOLD}${WHITE}💻 Info Server:${NC}"
    echo -e "    • Project  : ${CYAN}$PROJECT_DIR${NC}"
    echo -e "    • CPU Load : ${CYAN}$cpu_load${NC}"
    echo -e "    • RAM      : ${CYAN}${ram_used}MB${NC} / ${CYAN}${ram_total}MB${NC}"
    echo -e "    • Disk /   : ${CYAN}$disk_used${NC}"
    print_border
    echo -e "  ${BOLD}${WHITE}Pilih Opsi Manajemen:${NC}"
    echo -e "    [1] 🔄  Update Website (Git Pull & Deploy)"
    echo -e "    [2] 👤  Manajemen Akun & Kredensial"
    echo -e "    [3] ⚡  Cek Status Layanan & Sistem"
    echo -e "    [4] 📊  Pantau Aktivitas Pengunjung (Access Log)"
    echo -e "    [5] 📂  Backup & Restore Website (Files & DB)"
    echo -e "    [6] 🤖  Menu Bot (Telegram & WA)"
    echo -e "    [7] 🐞  Lihat Log Kesalahan (Error Log)"
    echo -e "    [8] 💰  Manajemen Saldo User (DUID)"
    echo -e "    [9] 📊  Kelola Riwayat Transaksi"
    echo -e "    [10] ❌  Uninstall Website (Hapus Total Website)"
    echo -e "    [11] 🌐  Ganti Domain"
    echo -e "    [0] 🚪  Keluar dari Panel"
    print_border
    echo -n "Pilih menu [0-11]: "
}

# =====================================================================
#  6. Instalasi & Konfigurasi Alias "set" Global
# =====================================================================
install_alias() {
    local target_script="/var/www/toko/tokoctl.sh"
    local current_script
    current_script=$(readlink -f "$0" 2>/dev/null || realpath "$0" 2>/dev/null)
    [ -z "$current_script" ] && current_script="$PWD/tokoctl.sh"

    print_info "Mengonfigurasi alias 'set' secara global..."
    
    local script_to_alias="$current_script"
    if [ "$current_script" != "$target_script" ] && [ -d "/var/www/toko" ]; then
        if [ -f "$target_script" ]; then
            script_to_alias="$target_script"
        else
            print_info "Menyalin script ke $target_script agar terpusat di folder website..."
            cp "$current_script" "$target_script"
            chmod +x "$target_script"
            script_to_alias="$target_script"
        fi
    fi

    local alias_cmd="alias set='sudo bash $script_to_alias'"
    local alias_added=false
    local files_to_edit=("/etc/bash.bashrc" "/etc/profile" "$HOME/.bashrc" "$HOME/.zshrc")
    
    for file in "${files_to_edit[@]}"; do
        if [ -f "$file" ]; then
            if ! grep -q "alias set=" "$file"; then
                echo -e "\n# Alias untuk panel VPS toko\n$alias_cmd" >> "$file"
                alias_added=true
            fi
        fi
    done

    # Tambahkan pembungkus bin kustom sebagai fallback cadangan
    if [ -d "/usr/local/bin" ] && [ ! -f "/usr/local/bin/set" ]; then
        echo -e "#!/bin/bash\nsudo bash $script_to_alias \"\$@\"" > /usr/local/bin/set
        chmod +x /usr/local/bin/set
        alias_added=true
    fi

    if [ "$alias_added" = true ]; then
        print_success "Alias 'set' berhasil dipasang!"
        echo -e "${GREEN}Silakan ketik: ${BOLD}source ~/.bashrc${NC}${GREEN} atau muat ulang terminal Anda untuk mencobanya.${NC}"
    else
        print_success "Alias 'set' sudah dikonfigurasi sebelumnya."
    fi
}

check_and_propose_alias() {
    local alias_found=false
    local files_to_check=("/etc/bash.bashrc" "$HOME/.bashrc" "$HOME/.zshrc" "/usr/local/bin/set")
    
    for file in "${files_to_check[@]}"; do
        if [ -f "$file" ]; then
            if grep -q "alias set=" "$file" || [ "$file" = "/usr/local/bin/set" ]; then
                alias_found=true
                break
            fi
        fi
    done

    if [ "$alias_found" = false ]; then
        echo
        print_warning "Alias cepat 'set' belum aktif di server Anda."
        read -p "Apakah Anda ingin memasang alias 'set' sekarang? (y/n): " jawab_alias
        if [[ "$jawab_alias" =~ ^[Yy]$ ]]; then
            install_alias
        fi
    fi
}

# =====================================================================
#  MENU 1: Update Website (Git Pull & Deploy) - dari update.sh
# =====================================================================
update_website() {
    print_border
    echo -e "      ${BOLD}${BLUE}🔄   UPDATE WEBSITE DARI GITHUB & DEPLOY   🔄${NC}"
    print_border
    print_info "Memulai pembaruan otomatis dari repositori..."

    local APP_DIR="$PROJECT_DIR"
    local FORCE_UPDATE=true

    # Simpan hash commit sebelum melakukan git pull
    local BEFORE_HASH=$(git rev-parse HEAD 2>/dev/null || echo "")

    # 1. Pull Kode & Tangani Konflik Perubahan Lokal
    echo -e "\n${YELLOW}Langkah 1: Mengambil kode terbaru dari repositori Git...${NC}"
    local HAS_CHANGES=false
    if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
        HAS_CHANGES=true
        print_warning "Terdeteksi perubahan lokal di VPS yang belum di-commit!"
        echo -e "Mencoba mengamankan sementara dengan 'git stash'..."
        if git stash; then
            print_success "Perubahan lokal disimpan sementara."
        else
            print_error "Gagal melakukan git stash!"
            read -p "Apakah Anda ingin memaksa reset repositori (git reset --hard)? Perubahan lokal akan hilang! (y/n): " JAWAB_RESET
            if [[ "$JAWAB_RESET" =~ ^[Yy]$ ]]; then
                git reset --hard HEAD
                git clean -fd
            else
                print_error "Update dibatalkan."
                return 1
            fi
        fi
    fi

    # Deteksi branch saat ini (default ke main)
    local CURRENT_BRANCH=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
    CURRENT_BRANCH=${CURRENT_BRANCH:-main}
    print_info "Branch aktif saat ini: $CURRENT_BRANCH"

    # Pastikan git dikonfigurasi untuk pull non-rebase secara default
    git config pull.rebase false 2>/dev/null || true

    git fetch origin
    git checkout "$CURRENT_BRANCH" 2>/dev/null || git checkout -b "$CURRENT_BRANCH" "origin/$CURRENT_BRANCH"

    if git pull origin "$CURRENT_BRANCH"; then
        print_success "Git pull berhasil."
    else
        print_error "Gagal melakukan git pull."
        read -p "Apakah Anda ingin memaksa reset repositori ke origin/$CURRENT_BRANCH? (y/n): " JAWAB_RESET
        if [[ "$JAWAB_RESET" =~ ^[Yy]$ ]]; then
            git reset --hard "origin/$CURRENT_BRANCH"
            HAS_CHANGES=false
        else
            print_error "Update dibatalkan."
            return 1
        fi
    fi

    # Kembalikan stash jika ada
    if [ "$HAS_CHANGES" = true ]; then
        print_info "Mengembalikan perubahan lokal (git stash pop)..."
        if git stash pop; then
            print_success "Perubahan lokal berhasil dikembalikan."
        else
            print_warning "Terjadi konflik saat mengembalikan perubahan. Silakan periksa secara manual."
        fi
    fi

    # Bandingkan commit hash sebelum dan sesudah git pull
    local AFTER_HASH=$(git rev-parse HEAD 2>/dev/null || echo "")
    if [ "$BEFORE_HASH" = "$AFTER_HASH" ] && [ "$FORCE_UPDATE" = false ]; then
        echo -e "\n${GREEN}======================================================================"
        echo -e "Aplikasi sudah berada di versi terbaru ($AFTER_HASH)."
        echo -e "Tidak ada perubahan/update baru yang perlu diterapkan."
        echo -e "======================================================================${NC}"
        return 0
    fi

    # 2. Pasang Dependensi Composer Baru
    echo -e "\n${YELLOW}Langkah 2: Memasang package PHP baru via Composer...${NC}"
    local COMPOSER_EXEC=""
    if command -v composer &> /dev/null; then
        COMPOSER_EXEC="composer"
    elif [ -f "composer.phar" ]; then
        COMPOSER_EXEC="php -d memory_limit=-1 composer.phar"
    else
        print_info "Composer tidak terpasang secara global. Mengunduh secara otomatis..."
        if curl -sS https://getcomposer.org/installer | php &>/dev/null; then
            print_success "composer.phar berhasil diunduh."
            COMPOSER_EXEC="php -d memory_limit=-1 composer.phar"
        else
            print_error "Gagal mengunduh composer.phar. Silakan pasang Composer secara manual."
            return 1
        fi
    fi

    if [ "$COMPOSER_EXEC" = "composer" ]; then
        if COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --ignore-platform-reqs; then
            print_success "Composer install berhasil."
        else
            print_warning "Composer install gagal. Mencoba dengan --no-scripts..."
            if COMPOSER_MEMORY_LIMIT=-1 composer install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts; then
                print_success "Composer install berhasil dengan --no-scripts."
            else
                print_error "Semua upaya composer install gagal!"
                return 1
            fi
        fi
    else
        if $COMPOSER_EXEC install --no-dev --optimize-autoloader --ignore-platform-reqs; then
            print_success "Composer install berhasil."
        else
            print_warning "Composer install gagal. Mencoba dengan --no-scripts..."
            if $COMPOSER_EXEC install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts; then
                print_success "Composer install berhasil dengan --no-scripts."
            else
                print_error "Semua upaya composer install gagal!"
                return 1
            fi
        fi
    fi

    # 3. Jalankan Migrasi Database Baru & Auto-Repair Admin
    echo -e "\n${YELLOW}Langkah 3: Mendiagnosis database & menjalankan migrasi...${NC}"
    if php artisan migrate --force; then
        print_success "Migrasi database berhasil."
    else
        print_warning "Migrasi gagal. Mencoba ulang setelah membersihkan cache..."
        php artisan cache:clear
        php artisan config:clear
        php artisan migrate --force
    fi

    # Jalankan seeder
    if php artisan db:seed --force; then
        print_success "Seeding database selesai."
    else
        print_warning "Seeding database gagal atau dilewati."
    fi

    # Auto-repair admin utama
    print_info "Memverifikasi akun Admin Utama di database..."
    local ADMIN_STATUS
    ADMIN_STATUS=$(php -r "
        try {
            require 'vendor/autoload.php';
            \$app = require_once 'bootstrap/app.php';
            \$app->make('Illuminate\\\Contracts\\\Console\\\Kernel')->bootstrap();
            use App\\\Models\\\User;
            use Illuminate\\\Support\\\Facades\\\Hash;
            \$admin = User::where('email', 'admin@vpn.com')->first();
            if (!\$admin) {
                \$admin = new User();
                \$admin->name = 'Admin Utama';
                \$admin->email = 'admin@vpn.com';
            }
            \$admin->password = Hash::make('password');
            \$admin->role = 'admin';
            \$admin->is_verified = 1;
            \$admin->save();
            echo 'ADMIN_SECURED';
        } catch (Exception \$e) {
            echo 'ERR: ' . \$e->getMessage();
        }
    " 2>/dev/null)

    if [ "$ADMIN_STATUS" = "ADMIN_SECURED" ]; then
        print_success "Akun Admin Utama 'admin@vpn.com' dijamin aktif & valid (Role: admin, Status: Aktif, Pass: password)."
    else
        print_warning "Gagal memvalidasi akun Admin: $ADMIN_STATUS"
    fi

    # 4. Bersihkan & Optimalkan Cache Laravel
    echo -e "\n${YELLOW}Langkah 4: Melakukan caching konfigurasi, rute, dan view Laravel...${NC}"
    php artisan optimize:clear
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

    # 5. Build Aset Frontend Baru dengan Fallback
    echo -e "\n${YELLOW}Langkah 5: Memasang paket npm & melakukan build aset Vite...${NC}"
    if [ -f "package.json" ]; then
        if [ -d "node_modules" ]; then
            chmod -R 777 node_modules &>/dev/null || true
        fi

        local NPM_OK=false
        if npm install --no-audit --no-fund; then
            NPM_OK=true
        else
            print_warning "npm install standar gagal. Mengulang dengan --ignore-scripts..."
            if npm install --ignore-scripts --no-audit --no-fund; then
                NPM_OK=true
            fi
        fi

        if [ "$NPM_OK" = true ]; then
            # Konfigurasi bin permissions
            if [ -d "node_modules/.bin" ]; then
                chmod -R +x node_modules/.bin 2>/dev/null || true
                for file in node_modules/.bin/*; do
                    if [ -L "$file" ]; then
                        local target=$(readlink -f "$file" 2>/dev/null)
                        if [ -f "$target" ]; then
                            chmod +x "$target" 2>/dev/null || true
                        fi
                    fi
                done
            fi

            # Eksekusi build (pastikan vite executable)
            chmod +x node_modules/.bin/vite 2>/dev/null || true
            if npm run build; then
                print_success "npm run build berhasil."
            else
                print_warning "npm run build gagal. Mengaktifkan fallback 1 (Node direct)..."
                if [ -f "node_modules/vite/bin/vite.js" ]; then
                    if node node_modules/vite/bin/vite.js build; then
                        print_success "Aset frontend berhasil di-build via Node fallback."
                    else
                        print_warning "Node fallback gagal. Mencoba fallback 2 (npx)..."
                        if npx vite build; then
                            print_success "Aset frontend berhasil di-build via npx."
                        else
                            print_warning "npx gagal. Mencoba fallback 3 (RAM-disk isolation)..."
                            local TMP_DIR="/dev/shm/vite-build-$(date +%s)"
                            mkdir -p "$TMP_DIR"
                            tar --exclude='./node_modules' --exclude='./.git' -cf - . | (cd "$TMP_DIR" && tar -xf -)
                            ln -s "$APP_DIR/node_modules" "$TMP_DIR/node_modules"
                            cd "$TMP_DIR"
                            if node node_modules/vite/bin/vite.js build; then
                                cp -r public/build "$APP_DIR/public/"
                                print_success "Aset frontend berhasil di-build via RAM-disk isolation."
                            else
                                print_error "Semua metode build frontend gagal!"
                            fi
                            cd "$APP_DIR"
                            rm -rf "$TMP_DIR"
                        fi
                    fi
                else
                    print_error "Berkas vite.js tidak ditemukan!"
                fi
            fi
        else
            print_error "Gagal memasang dependensi NPM!"
        fi
    fi

    # 6. Atur Ulang Permissions Folder secara aman
    echo -e "\n${YELLOW}Langkah 6: Mengatur hak akses folder & file secara aman...${NC}"
    chown -R www-data:www-data "$APP_DIR" 2>/dev/null || true
    find "$APP_DIR" -path "$APP_DIR/node_modules" -prune -o -path "$APP_DIR/vendor" -prune -o -path "$APP_DIR/.git" -prune -o -type d -exec chmod 755 {} \; 2>/dev/null || true
    find "$APP_DIR" -path "$APP_DIR/node_modules" -prune -o -path "$APP_DIR/vendor" -prune -o -path "$APP_DIR/.git" -prune -o -type f -exec chmod 644 {} \; 2>/dev/null || true
    chmod +x "$APP_DIR"/artisan 2>/dev/null || true
    chmod +x "$APP_DIR"/*.sh 2>/dev/null || true
    if [ -d "$APP_DIR/node_modules/.bin" ]; then
        chmod -R +x "$APP_DIR/node_modules/.bin" 2>/dev/null || true
    fi
    if [ -d "$APP_DIR/storage" ] && [ -d "$APP_DIR/bootstrap/cache" ]; then
        chmod -R 775 "$APP_DIR/storage"
        chmod -R 775 "$APP_DIR/bootstrap/cache"
    fi

    # 7. Restart Queue Worker
    echo -e "\n${YELLOW}Langkah 7: Merestart queue worker...${NC}"
    php artisan queue:restart
    if command -v pm2 &> /dev/null; then
        print_info "Merestart queue worker di PM2..."
        pm2 restart vpn-queue-worker || pm2 start "php artisan queue:work --tries=3" --name vpn-queue-worker --cwd "$APP_DIR"
        pm2 save
    fi

    # Tampilkan commit terbaru secara estetis
    echo
    echo -e "${BOLD}${CYAN}=== INFORMASI COMMIT TERAKHIR (VERSI AKTIF) ===${NC}"
    if command -v git > /dev/null 2>&1; then
        git log -1 --pretty=format:"${GREEN}Hash Commit :${NC} %h%n${GREEN}Author      :${NC} %an (%ae)%n${GREEN}Tanggal     :${NC} %ad%n${GREEN}Pesan       :${NC} %s"
        echo -e "\n"
    else
        print_warning "Aplikasi git tidak tersedia untuk menampilkan log commit."
    fi

    echo -e "\n${GREEN}======================================================================"
    echo -e "          UPDATE APLIKASI SELESAI DENGAN SUKSES!                      "
    echo -e "======================================================================${NC}"
}

# =====================================================================
#  MENU 2: Manajemen Akun & Kredensial - dari akun.sh
# =====================================================================
akun_header() {
    clear
    echo ""
    garis_tebal
    echo -e "${CYAN}  👤  ${BOLD}MANAJEMEN AKUN${NC}${CYAN} - Kredensial & Pengguna Website${NC}"
    echo -e "${DIM}  Lokasi Project: $PROJECT_DIR${NC}"
    garis_tebal
    echo ""
}

akun_list_users() {
    akun_header
    echo -e "${BOLD}${WHITE}Daftar Akun Pengguna:${NC}"
    garis

    local user_data
    user_data=$(execute_tinker "
        try {
            foreach(\App\Models\User::all() as \$u) {
                echo \$u->id . '|' . \$u->getWebsiteId() . '|' . \$u->name . '|' . \$u->email . '|' . (\$u->phone ?? '-') . '|' . \$u->role . '|' . (\$u->is_verified ? 'Verified' : 'Unverified') . PHP_EOL;
            }
        } catch (\Exception \$e) {
            echo 'ERROR: ' . \$e->getMessage();
        }
    ")

    if [[ "$user_data" == *"ERROR"* ]] || [ -z "$user_data" ]; then
        print_error "Gagal mengambil data pengguna dari database."
        echo -e "${RED}Detail: $user_data${NC}"
        return 1
    fi

    printf "${BOLD}${CYAN} %-3s | %-8s | %-18s | %-28s | %-12s | %-8s | %-10s${NC}\n" "ID" "ID Web" "Nama" "Email" "Telepon" "Role" "Status"
    garis

    echo "$user_data" | while IFS='|' read -r id web_id name email phone role status; do
        if [ -n "$id" ]; then
            local role_color="$WHITE"
            if [ "$role" = "admin" ]; then
                role_color="$RED"
            elif [ "$role" = "seller" ]; then
                role_color="$GREEN"
            fi

            local status_color="$YELLOW"
            if [ "$status" = "Verified" ]; then
                status_color="$GREEN"
            fi

            printf " %-3s | %-8s | %-18s | %-28s | %-12s | ${role_color}%-8s${NC} | ${status_color}%-10s${NC}\n" \
                "$id" "$web_id" "${name:0:18}" "${email:0:28}" "${phone:0:12}" "$role" "$status"
        fi
    done
    garis
    echo ""
}

akun_tambah_user() {
    akun_header
    echo -e "${BOLD}${GREEN}➕ Tambah Pengguna Baru${NC}"
    garis
    
    read -p "Masukkan Nama: " nama
    if [ -z "$nama" ]; then
        print_error "Nama tidak boleh kosong! Batal."
        return 1
    fi

    read -p "Masukkan Email: " email
    if [ -z "$email" ]; then
        print_error "Email tidak boleh kosong! Batal."
        return 1
    fi

    # Validasi email duplikat
    local email_exists
    email_exists=$(execute_tinker "echo \App\Models\User::where('email', '$email')->exists() ? 'true' : 'false';")
    if [ "$email_exists" = "true" ]; then
        print_error "Email '$email' sudah terdaftar di database!"
        return 1
    fi

    read -p "Masukkan Password Baru: " password
    if [ -z "$password" ]; then
        print_error "Password tidak boleh kosong! Batal."
        return 1
    fi

    echo -e "Pilih Role:"
    echo -e "  [1] buyer (Pembeli / User Biasa)"
    echo -e "  [2] seller (Penjual / Reseller)"
    echo -e "  [3] admin (Administrator Utama)"
    read -p "Pilih Role [1-3, default 1]: " role_opt
    local role="buyer"
    case "$role_opt" in
        2) role="seller" ;;
        3) role="admin" ;;
    esac

    read -p "Masukkan No Telepon/WhatsApp (opsional, Enter untuk melewati): " telepon
    telepon=${telepon:-""}

    read -p "Verifikasi akun langsung? (y/n, default y): " verified_opt
    local verified=1
    if [[ "$verified_opt" =~ ^[Nn]$ ]]; then
        verified=0
    fi

    echo -e "\n${YELLOW}Membuat akun di database...${NC}"

    local result
    result=$(execute_tinker "
        try {
            \$user = new \App\Models\User();
            \$user->name = '$nama';
            \$user->email = '$email';
            \$user->password = \Illuminate\Support\Facades\Hash::make('$password');
            \$user->role = '$role';
            \$user->phone = '$telepon';
            \$user->is_verified = $verified;
            \$user->save();
            echo 'SUCCESS';
        } catch (\Exception \$e) {
            echo 'ERR: ' . \$e->getMessage();
        }
    ")

    if [ "$result" = "SUCCESS" ]; then
        print_success "Pengguna baru '$nama' ($role) berhasil ditambahkan!"
    else
        print_error "Gagal membuat pengguna baru: $result"
    fi
}

akun_edit_user() {
    akun_list_users
    read -p "Masukkan ID User yang ingin diedit (Enter untuk batal): " user_id
    if [ -z "$user_id" ]; then
        echo -e "${YELLOW}Dibatalkan.${NC}"
        return 0
    fi

    local current_user
    current_user=$(execute_tinker "
        \$u = \App\Models\User::find($user_id);
        if (\$u) {
            echo \$u->name . '|' . \$u->email . '|' . (\$u->phone ?? '') . '|' . \$u->role . '|' . \$u->is_verified;
        } else {
            echo 'NOT_FOUND';
        }
    ")

    if [ "$current_user" = "NOT_FOUND" ] || [ -z "$current_user" ]; then
        print_error "User dengan ID $user_id tidak ditemukan!"
        return 1
    fi

    IFS='|' read -r curr_name curr_email curr_phone curr_role curr_verified <<< "$current_user"

    akun_header
    echo -e "${BOLD}${YELLOW}✏️ Edit Pengguna (ID: $user_id)${NC}"
    garis
    echo -e "Tekan [Enter] langsung untuk mempertahankan nilai lama."
    garis

    read -p "Nama baru [$curr_name]: " new_name
    new_name=${new_name:-$curr_name}

    read -p "Email baru [$curr_email]: " new_email
    new_email=${new_email:-$curr_email}

    if [ "$new_email" != "$curr_email" ]; then
        local email_exists
        email_exists=$(execute_tinker "echo \App\Models\User::where('email', '$new_email')->exists() ? 'true' : 'false';")
        if [ "$email_exists" = "true" ]; then
            print_error "Email '$new_email' sudah terdaftar untuk user lain! Batal."
            return 1
        fi
    fi

    read -p "Password baru (biarkan kosong jika tidak ingin diubah): " new_password

    [ "$curr_verified" = "1" ] && status_str="Verified" || status_str="Unverified"
    echo -e "Role saat ini: ${BOLD}$curr_role${NC}"
    echo -e "Pilih Role Baru:"
    echo -e "  [1] buyer"
    echo -e "  [2] seller"
    echo -e "  [3] admin"
    read -p "Pilihan [1-3, default pertahankan yang lama]: " new_role_opt
    local new_role="$curr_role"
    case "$new_role_opt" in
        1) new_role="buyer" ;;
        2) new_role="seller" ;;
        3) new_role="admin" ;;
    esac

    read -p "No Telepon [$curr_phone]: " new_phone
    new_phone=${new_phone:-$curr_phone}

    echo -e "Status verifikasi saat ini: ${BOLD}$status_str${NC}"
    read -p "Ubah status verifikasi? (y=Aktif/Verified, n=Unverified, Enter=Lewati): " new_verified_opt
    local new_verified="$curr_verified"
    if [[ "$new_verified_opt" =~ ^[Yy]$ ]]; then
        new_verified=1
    elif [[ "$new_verified_opt" =~ ^[Nn]$ ]]; then
        new_verified=0
    fi

    echo -e "\n${YELLOW}Menyimpan perubahan di database...${NC}"

    local php_update_code
    if [ -n "$new_password" ]; then
        php_update_code="
            try {
                \$u = \App\Models\User::findOrFail($user_id);
                \$u->name = '$new_name';
                \$u->email = '$new_email';
                \$u->password = \Illuminate\Support\Facades\Hash::make('$new_password');
                \$u->role = '$new_role';
                \$u->phone = '$new_phone';
                \$u->is_verified = $new_verified;
                \$u->save();
                echo 'SUCCESS';
            } catch (\Exception \$e) {
                echo 'ERR: ' . \$e->getMessage();
            }
        "
    else
        php_update_code="
            try {
                \$u = \App\Models\User::findOrFail($user_id);
                \$u->name = '$new_name';
                \$u->email = '$new_email';
                \$u->role = '$new_role';
                \$u->phone = '$new_phone';
                \$u->is_verified = $new_verified;
                \$u->save();
                echo 'SUCCESS';
            } catch (\Exception \$e) {
                echo 'ERR: ' . \$e->getMessage();
            }
        "
    fi

    local result
    result=$(execute_tinker "$php_update_code")

    if [ "$result" = "SUCCESS" ]; then
        print_success "Akun user ID $user_id ($new_name) berhasil diperbarui!"
    else
        print_error "Gagal memperbarui akun: $result"
    fi
}

akun_hapus_user() {
    akun_list_users
    read -p "Masukkan ID User yang akan DIHAPUS (Enter untuk batal): " user_id
    if [ -z "$user_id" ]; then
        echo -e "${YELLOW}Dibatalkan.${NC}"
        return 0
    fi

    local current_user
    current_user=$(execute_tinker "
        \$u = \App\Models\User::find($user_id);
        if (\$u) {
            echo \$u->name . '|' . \$u->email . '|' . \$u->role;
        } else {
            echo 'NOT_FOUND';
        }
    ")

    if [ "$current_user" = "NOT_FOUND" ] || [ -z "$current_user" ]; then
        print_error "User dengan ID $user_id tidak ditemukan!"
        return 1
    fi

    IFS='|' read -r name email role <<< "$current_user"

    if [ "$role" = "admin" ]; then
        local admin_count
        admin_count=$(execute_tinker "echo \App\Models\User::where('role', 'admin')->count();")
        if [ "$admin_count" -le 1 ]; then
            print_error "User ini adalah satu-satunya administrator di website. Anda tidak boleh menghapusnya!"
            return 1
        fi
    fi

    echo -e "${RED}${BOLD}PERINGATAN: Anda akan menghapus akun berikut secara permanen!${NC}"
    echo -e "  Nama  : $name"
    echo -e "  Email : $email"
    echo -e "  Role  : $role"
    garis
    read -p "Apakah Anda yakin ingin menghapus akun ini? (ketik 'HAPUS' untuk mengonfirmasi): " konfirmasi

    if [ "$konfirmasi" != "HAPUS" ]; then
        echo -e "${YELLOW}Penghapusan dibatalkan.${NC}"
        return 0
    fi

    echo -e "${YELLOW}Menghapus user dari database...${NC}"
    local result
    result=$(execute_tinker "
        try {
            \$u = \App\Models\User::findOrFail($user_id);
            \$u->delete();
            echo 'SUCCESS';
        } catch (\Exception \$e) {
            echo 'ERR: ' . \$e->getMessage();
        }
    ")

    if [ "$result" = "SUCCESS" ]; then
        print_success "Akun user ID $user_id ($name) berhasil dihapus secara permanen!"
    else
        print_error "Gagal menghapus akun: $result"
    fi
}

manage_accounts() {
    while true; do
        akun_header
        echo -e "  ${BOLD}${WHITE}Pilih Opsi Manajemen Akun:${NC}"
        echo -e "    [1] 📋 Lihat Semua Akun Pengguna"
        echo -e "    [2] ➕ Tambah Akun Pengguna Baru"
        echo -e "    [3] ✏️  Edit Kredensial & Detail Akun"
        echo -e "    [4] ❌ Hapus Akun Pengguna"
        echo -e "    [0] 🔙 Kembali ke Menu Utama"
        garis_tebal
        read -p "Pilih menu [0-4]: " pilihan_akun
        echo ""

        case "$pilihan_akun" in
            1)
                akun_list_users
                read -p "Tekan [Enter] untuk kembali..." temp
                ;;
            2)
                akun_tambah_user
                read -p "Tekan [Enter] untuk kembali..." temp
                ;;
            3)
                akun_edit_user
                read -p "Tekan [Enter] untuk kembali..." temp
                ;;
            4)
                akun_hapus_user
                read -p "Tekan [Enter] untuk kembali..." temp
                ;;
            0)
                break
                ;;
            *)
                print_warning "Pilihan tidak valid. Silakan pilih menu [0-4]."
                sleep 1
                ;;
        esac
    done
}

# =====================================================================
#  MENU 3: Cek Status Layanan & Sistem
# =====================================================================
detect_php_service() {
    php_service="php8.3-fpm"
    if command -v systemctl >/dev/null 2>&1; then
        if systemctl list-units --type=service | grep -q "php.*-fpm"; then
            php_service=$(systemctl list-units --type=service | grep -o "php.*-fpm" | head -n 1)
        fi
    elif [ -d "/etc/init.d" ]; then
        local found
        found=$(ls /etc/init.d/ | grep php | grep fpm | head -n 1)
        if [ -n "$found" ]; then
            php_service="$found"
        fi
    fi
}

check_service_status() {
    local service=$1
    local name=$2
    if command -v systemctl >/dev/null 2>&1; then
        if systemctl is-active "$service" >/dev/null 2>&1; then
            echo -e "  ${GREEN}●${NC} $name: ${BOLD}${GREEN}Active (Running)${NC}"
        else
            echo -e "  ${RED}●${NC} $name: ${BOLD}${RED}Inactive (Stopped)${NC}"
        fi
    else
        if ps aux | grep -v grep | grep -qi "$service"; then
            echo -e "  ${GREEN}●${NC} $name: ${BOLD}${GREEN}Active (Running)${NC}"
        else
            echo -e "  ${RED}●${NC} $name: ${BOLD}${RED}Inactive (Stopped)${NC}"
        fi
    fi
}

show_services_status() {
    print_border
    echo -e "      ${BOLD}${BLUE}⚡   CEK STATUS LAYANAN & SISTEM VPS   ⚡${NC}"
    print_border
    
    detect_php_service
    
    echo -e "${BOLD}${WHITE}Layanan Sistem Core:${NC}"
    check_service_status "nginx" "Web Server (Nginx)"
    check_service_status "mysql" "Database Server (MySQL)"
    check_service_status "$php_service" "FastCGI Process Manager ($php_service)"
    
    echo
    echo -e "${BOLD}${WHITE}Layanan Background (PM2 Queue Worker):${NC}"
    if command -v pm2 >/dev/null 2>&1; then
        local pm2_status
        pm2_status=$(pm2 status 2>/dev/null)
        if [ $? -eq 0 ]; then
            echo -e "  ${GREEN}●${NC} PM2 Status: ${BOLD}${GREEN}Active (Running)${NC}"
            pm2 list | grep -E "online|errored|stopped|name" | grep -v "PM2"
        else
            echo -e "  ${RED}●${NC} PM2 Status: ${BOLD}${RED}Inactive (Stopped)${NC}"
        fi
    else
        echo -e "  ${YELLOW}●${NC} PM2 Status: ${YELLOW}Tidak terpasang di VPS (Queue worker tidak berjalan)${NC}"
    fi
    echo
}

# =====================================================================
#  MENU 4: Pantau Aktivitas Pengunjung (Access Log)
# =====================================================================
view_access_log() {
    print_border
    echo -e "      ${BOLD}${BLUE}📊   PANTAU AKTIVITAS PENGUNJUNG (ACCESS LOG)   📊${NC}"
    print_border
    
    local log_paths=("/var/log/nginx/access.log" "/var/log/nginx/toko_access.log" "/var/log/nginx/toko.access.log")
    local found_log=""
    
    for path in "${log_paths[@]}"; do
        if [ -f "$path" ]; then
            found_log="$path"
            break
        fi
    done

    if [ -z "$found_log" ]; then
        print_warning "File nginx access.log default tidak ditemukan."
        if [ -d "/var/log/nginx" ]; then
            echo -e "File log alternatif yang tersedia di /var/log/nginx:"
            ls -l /var/log/nginx
        fi
        read -p "Masukkan jalur kustom file access log Anda (atau Enter untuk batal): " custom_path
        if [ -f "$custom_path" ]; then
            found_log="$custom_path"
        else
            print_error "Jalur log tidak valid. Batal."
            return 1
        fi
    fi

    echo -e "${GREEN}Membuka log aktivitas real-time dari: $found_log${NC}"
    echo -e "${YELLOW}Tekan [Ctrl+C] untuk keluar dari monitor log dan kembali ke menu.${NC}"
    echo -e "---------------------------------------------------------------------"
    
    tail -n 30 -f "$found_log"
}

# =====================================================================
#  MENU 5: Backup & Restore Website (Files & DB)
# =====================================================================
backup_website() {
    echo -e "${BOLD}${BLUE}=== BUAT CADANGAN (BACKUP) BARU ===${NC}"
    
    # Pre-checks: pastikan tar terinstal
    if ! command -v tar &>/dev/null; then
        print_error "Perintah 'tar' tidak ditemukan di sistem! Silakan pasang 'tar' terlebih dahulu."
        return 1
    fi
    
    # Ambil konfigurasi database dari .env
    local db_conn="sqlite"
    local db_name="database/database.sqlite"
    local db_user=""
    local db_pass=""
    local db_host="127.0.0.1"
    local db_port="3306"

    if [ -f ".env" ]; then
        db_conn=$(grep "^DB_CONNECTION=" .env | cut -d'=' -f2-)
        db_conn=${db_conn:-sqlite}
        
        db_name=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2-)
        db_user=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2-)
        db_pass=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2-)
        db_host=$(grep "^DB_HOST=" .env | cut -d'=' -f2-)
        db_port=$(grep "^DB_PORT=" .env | cut -d'=' -f2-)
    fi

    local backup_dir="backups"
    mkdir -p "$backup_dir"
    
    local timestamp=$(date +%Y%m%d_%H%M%S)
    local db_temp_file="db_temp_backup.sql"
    local backup_filename="toko_backup_${timestamp}.tar.gz"

    # Export DB sesuai tipe koneksi
    if [ "$db_conn" = "mysql" ]; then
        print_info "Mengekspor basis data MySQL..."
        
        if ! command -v mysqldump &>/dev/null; then
            print_warning "Perintah 'mysqldump' tidak ditemukan!"
            print_info "Mencoba memasang client database secara otomatis..."
            apt-get update && apt-get install -y mysql-client mariadb-client-core || true
        fi
        
        local dump_status=1
        
        if command -v mysqldump &>/dev/null; then
            if mysqldump "${db_name:-toko}" > "$db_temp_file" 2>/dev/null; then
                dump_status=0
            else
                print_warning "Ekspor root lokal gagal. Mencoba menggunakan kredensial .env..."
                if [ -n "$db_pass" ]; then
                    export MYSQL_PWD="$db_pass"
                fi
                mysqldump -h "${db_host:-127.0.0.1}" -P "${db_port:-3306}" -u "${db_user:-root}" "${db_name:-toko}" > "$db_temp_file" 2>/dev/null
                dump_status=$?
                unset MYSQL_PWD
            fi
        fi

        if [ $dump_status -ne 0 ]; then
            print_error "Gagal mengekspor database MySQL! Pastikan kredensial di .env valid dan mysql-client terpasang."
            rm -f "$db_temp_file"
            return 1
        fi
    else
        print_info "Mengekspor basis data SQLite..."
        local sqlite_path="${db_name:-database/database.sqlite}"
        if [ ! -f "$sqlite_path" ]; then
            sqlite_path="database/database.sqlite"
        fi
        if [ -f "$sqlite_path" ]; then
            if command -v sqlite3 &>/dev/null; then
                sqlite3 "$sqlite_path" ".backup '$db_temp_file'"
            else
                cp "$sqlite_path" "$db_temp_file"
            fi
        else
            print_error "File SQLite tidak ditemukan di: $sqlite_path"
            return 1
        fi
    fi

    print_info "Mengompresi semua berkas website dan database..."
    
    tar -czf "${backup_dir}/${backup_filename}" \
        --exclude="./node_modules" \
        --exclude="./vendor" \
        --exclude="./.git" \
        --exclude="./backups" \
        --exclude="./${backup_dir}" \
        --exclude="backups" \
        --exclude="./storage/framework/cache/data/*" \
        --exclude="./storage/framework/sessions/*" \
        --exclude="./storage/framework/views/*.php" \
        --exclude="./bootstrap/cache/*.php" \
        . 2>/dev/null

    local tar_status=$?
    rm -f "$db_temp_file"

    if [ $tar_status -eq 0 ] && [ -f "${backup_dir}/${backup_filename}" ]; then
        local size=$(du -sh "${backup_dir}/${backup_filename}" | awk '{print $1}')
        print_success "Cadangan website berhasil dibuat!"
        echo -e "  ${CYAN}File Hasil:${NC} ${backup_dir}/${backup_filename}"
        echo -e "  ${CYAN}Ukuran   :${NC} $size"

        echo
        read -p "Apakah Anda ingin mengunggah file backup ini ke Google Drive? (y/n, default n): " upload_gdrive
        if [[ "$upload_gdrive" =~ ^[Yy]$ ]]; then
            if ! command -v rclone &>/dev/null; then
                print_warning "Rclone tidak terdeteksi di server."
                read -p "Apakah Anda ingin menginstal rclone secara otomatis sekarang? (y/n): " install_rclone_ans
                if [[ "$install_rclone_ans" =~ ^[Yy]$ ]]; then
                    print_info "Menginstal rclone..."
                    apt-get update --allow-releaseinfo-change && apt-get install -y rclone
                fi
            fi

            if command -v rclone &>/dev/null; then
                if ! rclone listremotes | grep -q "^gdrive:"; then
                    print_warning "Remote 'gdrive' belum dikonfigurasi di Rclone."
                    echo -e "Untuk menggunakan Google Drive, Anda harus mengonfigurasi remote bernama 'gdrive'."
                    echo -e "Silakan jalankan perintah ${BOLD}rclone config${NC} di terminal terpisah."
                    echo -e "Ikuti petunjuk konfigurasi untuk membuat remote Google Drive dengan nama 'gdrive'."
                    read -p "Tekan [Enter] setelah Anda selesai mengonfigurasi rclone..."
                fi

                if rclone listremotes | grep -q "^gdrive:"; then
                    print_info "Mengunggah file ke Google Drive (folder: toko_backups)..."
                    if rclone copy "${backup_dir}/${backup_filename}" gdrive:toko_backups/; then
                        print_success "File berhasil diunggah ke Google Drive!"
                        
                        print_info "Membuat link sharing publik..."
                        local share_link
                        share_link=$(rclone link "gdrive:toko_backups/${backup_filename}" 2>&1)
                        if [ $? -eq 0 ] && [ -n "$share_link" ]; then
                            echo -e "  ${CYAN}Link Sharing:${NC} ${GREEN}${share_link}${NC}"
                        else
                            print_warning "Gagal membuat link sharing secara otomatis: $share_link"
                            echo -e "  ${YELLOW}Silakan buka Google Drive Anda, cari file '${backup_filename}', lalu klik kanan -> Bagikan (Share) -> Salin Link.${NC}"
                        fi
                    else
                        print_error "Gagal mengunggah file ke Google Drive."
                    fi
                else
                    print_error "Konfigurasi remote 'gdrive' tidak ditemukan. Upload dibatalkan."
                fi
            else
                print_error "Rclone tidak terpasang. Upload dibatalkan."
            fi
        fi
    else
        print_error "Gagal mengompresi backup."
        return 1
    fi
}

restore_website() {
    echo -e "${BOLD}${RED}=== PULIHKAN (RESTORE) DATA WEBSITE ===${NC}"
    
    local backup_dir="backups"
    mkdir -p "$backup_dir"

    echo -e "Pilih sumber file backup:"
    echo -e "  [1] File Backup Lokal (di folder backups/)"
    echo -e "  [2] Download & Pulihkan dari Google Drive via Link Sharing"
    read -p "Pilih sumber [1-2, default 1]: " sumber_backup
    sumber_backup=${sumber_backup:-1}

    local selected_backup=""

    if [ "$sumber_backup" = "2" ]; then
        read -p "Masukkan Link Sharing Google Drive: " gdrive_url
        if [ -z "$gdrive_url" ]; then
            print_error "Link tidak boleh kosong!"
            return 1
        fi

        local file_id=""
        if [[ "$gdrive_url" =~ d/([^/]+) ]]; then
            file_id="${BASH_REMATCH[1]}"
        elif [[ "$gdrive_url" =~ id=([^&]+) ]]; then
            file_id="${BASH_REMATCH[1]}"
        else
            file_id="$gdrive_url"
        fi

        local timestamp=$(date +%Y%m%d_%H%M%S)
        local downloaded_file="${backup_dir}/toko_backup_gdrive_${timestamp}.tar.gz"
        print_info "Mengunduh file backup dari Google Drive (ID: $file_id)..."
        
        local confirm_url="https://docs.google.com/uc?export=download&id=${file_id}"
        local confirm_cookie="/tmp/gdrive_confirm_cookie.txt"
        
        local confirm_code=$(curl -sL -c "$confirm_cookie" "$confirm_url" | grep -o -E 'confirm=[^&]*' | head -n 1)
        if [ -n "$confirm_code" ]; then
            curl -L -b "$confirm_cookie" "https://docs.google.com/uc?export=download&${confirm_code}&id=${file_id}" -o "$downloaded_file"
        else
            curl -L "$confirm_url" -o "$downloaded_file"
        fi
        rm -f "$confirm_cookie"

        if [ -f "$downloaded_file" ] && [ -s "$downloaded_file" ]; then
            print_success "File backup berhasil diunduh dari Google Drive."
            selected_backup="$downloaded_file"
        else
            print_error "Gagal mengunduh file backup dari Google Drive."
            print_warning "Pastikan link valid dan diatur ke 'Siapa saja yang memiliki link dapat melihat/mengunduh'."
            rm -f "$downloaded_file"
            return 1
        fi
    else
        if [ ! -d "$backup_dir" ] || [ -z "$(ls -A "$backup_dir" 2>/dev/null)" ]; then
            print_error "Tidak ada file backup yang terdeteksi di folder '$backup_dir'."
            return 1
        fi

        echo -e "Pilih file backup untuk dipulihkan:"
        local i=1
        local backups_list=()
        for file in "$backup_dir"/toko_backup_*.tar.gz; do
            if [ -f "$file" ]; then
                backups_list+=("$file")
                local size=$(du -sh "$file" | awk '{print $1}')
                local date_str=$(basename "$file" | sed -E 's/toko_backup_(.*)\\.tar\\.gz/\\1/')
                local formatted_date=$(echo "$date_str" | sed -E 's/([0-9]{4})([0-9]{2})([0-9]{2})_([0-9]{2})([0-9]{2})([0-9]{2})/\\1-\\2-\\3 \\4:\\5:\\6/')
                echo -e "  [$i] $(basename "$file") ($size) - [$formatted_date]"
                i=$((i+1))
            fi
        done

        if [ ${#backups_list[@]} -eq 0 ]; then
            print_error "Tidak ada arsip backup valid."
            return 1
        fi

        read -p "Masukkan nomor backup [1-$((i-1))] (atau Enter untuk batal): " pilihan
        if [ -z "$pilihan" ] || ! [[ "$pilihan" =~ ^[0-9]+$ ]] || [ "$pilihan" -lt 1 ] || [ "$pilihan" -ge "$i" ]; then
            print_warning "Batal memulihkan."
            return 1
        fi

        selected_backup="${backups_list[$((pilihan-1))]}"
    fi
    
    echo -e "\n${BOLD}${RED}[PERINGATAN] Mengembalikan backup akan menghapus data saat ini!${NC}"
    read -p "Apakah Anda yakin ingin melanjutkan? (y/n): " konfirmasi
    if [[ ! "$konfirmasi" =~ ^[Yy]$ ]]; then
        print_warning "Pemulihan dibatalkan."
        if [ "$sumber_backup" = "2" ] && [ -f "$selected_backup" ]; then
            rm -f "$selected_backup"
        fi
        return 1
    fi

    # Pre-flight check: pastikan php terinstal
    if ! command -v php &>/dev/null; then
        print_error "Perintah 'php' tidak terdeteksi! Silakan pasang PHP terlebih dahulu."
        return 1
    fi

    # 0. Hentikan service sementara
    print_info "Menghentikan service sementara untuk menghindari lock database..."
    detect_php_service
    systemctl stop nginx "$php_service" 2>/dev/null || true
    if command -v pm2 &>/dev/null; then
        pm2 stop vpn-queue-worker 2>/dev/null || true
    fi

    # 1. Cadangkan file .env lokal
    local temp_env="/tmp/toko_current_env_backup"
    if [ -f ".env" ]; then
        cp ".env" "$temp_env"
        print_info "Mencadangkan file .env lokal saat ini..."
    fi

    print_info "Mengekstrak file backup..."
    if ! tar -xzf "$selected_backup"; then
        print_error "Ekstraksi arsip gagal."
        rm -f "$temp_env"
        return 1
    fi

    # 2. Sinkronisasikan kredensial database dan domain
    if [ -f "$temp_env" ] && [ -f ".env" ]; then
        print_info "Menyelaraskan konfigurasi database & domain (.env)..."
        
        update_env_value() {
            local key="$1"
            local value="$2"
            if grep -q "^${key}=" .env; then
                sed -i "s|^${key}=.*|${key}=${value}|g" .env
            else
                echo "${key}=${value}" >> .env
            fi
        }

        local new_db_conn=$(grep "^DB_CONNECTION=" "$temp_env" | cut -d'=' -f2-)
        local new_db_host=$(grep "^DB_HOST=" "$temp_env" | cut -d'=' -f2-)
        local new_db_port=$(grep "^DB_PORT=" "$temp_env" | cut -d'=' -f2-)
        local new_db_name=$(grep "^DB_DATABASE=" "$temp_env" | cut -d'=' -f2-)
        local new_db_user=$(grep "^DB_USERNAME=" "$temp_env" | cut -d'=' -f2-)
        local new_db_pass=$(grep "^DB_PASSWORD=" "$temp_env" | cut -d'=' -f2-)
        local new_app_url=$(grep "^APP_URL=" "$temp_env" | cut -d'=' -f2-)

        [ -n "$new_db_conn" ] && update_env_value "DB_CONNECTION" "$new_db_conn"
        [ -n "$new_db_host" ] && update_env_value "DB_HOST" "$new_db_host"
        [ -n "$new_db_port" ] && update_env_value "DB_PORT" "$new_db_port"
        [ -n "$new_db_name" ] && update_env_value "DB_DATABASE" "$new_db_name"
        [ -n "$new_db_user" ] && update_env_value "DB_USERNAME" "$new_db_user"
        [ -n "$new_db_pass" ] && update_env_value "DB_PASSWORD" "$new_db_pass"
        [ -n "$new_app_url" ] && update_env_value "APP_URL" "$new_app_url"
        
        rm -f "$temp_env"
    fi

    # Restore DB jika temp dump ada
    local db_temp_file="db_temp_backup.sql"
    if [ -f "$db_temp_file" ]; then
        local db_conn="sqlite"
        local db_name="database/database.sqlite"
        local db_user=""
        local db_pass=""
        local db_host="127.0.0.1"
        local db_port="3306"

        if [ -f ".env" ]; then
            db_conn=$(grep "^DB_CONNECTION=" .env | cut -d'=' -f2-)
            db_conn=${db_conn:-sqlite}
            db_name=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2-)
            db_user=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2-)
            db_pass=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2-)
            db_host=$(grep "^DB_HOST=" .env | cut -d'=' -f2-)
            db_port=$(grep "^DB_PORT=" .env | cut -d'=' -f2-)
        fi

        if [ "$db_conn" = "mysql" ]; then
            print_info "Memulihkan database MySQL..."
            
            # Pasang mysql-client jika hilang
            if ! command -v mysql &>/dev/null; then
                print_warning "Perintah 'mysql' tidak ditemukan di sistem!"
                print_info "Mencoba memasang client MySQL secara otomatis..."
                apt-get update && apt-get install -y mysql-client mariadb-client-core || true
            fi
            
            local mysql_status=1
            if command -v mysql &>/dev/null; then
                if mysql -e "CREATE DATABASE IF NOT EXISTS \`${db_name:-toko}\`;" &>/dev/null; then
                    mysql "${db_name:-toko}" < "$db_temp_file" &>/dev/null
                    mysql_status=$?
                else
                    print_warning "Koneksi root lokal dibatasi atau gagal. Mencoba menggunakan kredensial dari file .env..."
                    if [ -n "$db_pass" ]; then
                        export MYSQL_PWD="$db_pass"
                    fi
                    mysql -h "${db_host:-127.0.0.1}" -P "${db_port:-3306}" -u "${db_user:-root}" -e "CREATE DATABASE IF NOT EXISTS \`${db_name:-toko}\`;" 2>/dev/null
                    mysql -h "${db_host:-127.0.0.1}" -P "${db_port:-3306}" -u "${db_user:-root}" "${db_name:-toko}" < "$db_temp_file" 2>/dev/null
                    mysql_status=$?
                    unset MYSQL_PWD
                fi
            fi
            
            if [ $mysql_status -eq 0 ]; then
                print_success "Database MySQL berhasil dipulihkan."
            else
                print_error "Gagal memulihkan database MySQL!"
            fi
        else
            print_info "Memulihkan database SQLite..."
            local sqlite_path="${db_name:-database/database.sqlite}"
            mkdir -p "$(dirname "$sqlite_path")"
            cp "$db_temp_file" "$sqlite_path"
            chmod 664 "$sqlite_path"
            print_success "Database SQLite berhasil dipulihkan ke: $sqlite_path"
        fi
        
        rm -f "$db_temp_file"
    fi

    # Pembuatan ulang cache & pemasangan dependensi
    print_info "Menginstal ulang dependensi & membangun aset frontend..."
    
    local COMPOSER_EXEC=""
    if command -v composer &> /dev/null; then
        COMPOSER_EXEC="composer"
    elif [ -f "composer.phar" ]; then
        COMPOSER_EXEC="php -d memory_limit=-1 composer.phar"
    else
        print_info "Composer tidak terpasang secara global. Mengunduh secara otomatis..."
        if curl -sS https://getcomposer.org/installer | php &>/dev/null; then
            print_success "composer.phar berhasil diunduh."
            COMPOSER_EXEC="php -d memory_limit=-1 composer.phar"
        else
            print_warning "Gagal mengunduh composer.phar. Silakan pasang Composer secara manual."
        fi
    fi

    if [ -n "$COMPOSER_EXEC" ]; then
        if $COMPOSER_EXEC install --no-dev --optimize-autoloader --ignore-platform-reqs; then
            print_success "Composer dependensi berhasil dipasang."
        else
            print_warning "Composer install gagal. Mencoba dengan --no-scripts..."
            $COMPOSER_EXEC install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts || true
        fi
    fi

    if [ -f "package.json" ]; then
        if command -v npm &>/dev/null; then
            npm install --no-audit --no-fund || true
            npm run build || npx vite build || true
        else
            print_warning "npm tidak ditemukan. Aset frontend tidak dapat di-build."
        fi
    fi
    
    # Bangun ulang symbolic link storage agar tidak error (pointing ke path lama)
    print_info "Membangun ulang symbolic link storage Laravel..."
    rm -rf public/storage
    php artisan storage:link || true

    # Bersihkan Cache Laravel secara menyeluruh
    print_info "Mengosongkan & memproses ulang cache Laravel..."
    php artisan optimize:clear || true
    php artisan cache:clear || true
    php artisan config:clear || true
    php artisan route:clear || true
    php artisan view:clear || true

    # Amankan Perizinan folder & file
    print_info "Mengatur perizinan folder & file..."
    chown -R www-data:www-data . 2>/dev/null || true
    find . -path "./node_modules" -prune -o -path "./vendor" -prune -o -path "./.git" -prune -o -type d -exec chmod 755 {} \; 2>/dev/null || true
    find . -path "./node_modules" -prune -o -path "./vendor" -prune -o -path "./.git" -prune -o -type f -exec chmod 644 {} \; 2>/dev/null || true
    chmod +x artisan *.sh 2>/dev/null || true
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true

    # Berikan izin execute (+x) pada seluruh path direktori induk agar Nginx/PHP-FPM bisa menjangkaunya
    print_info "Mengatur hak akses traversal direktori induk..."
    local dir_path="$PWD"
    while [ "$dir_path" != "/" ] && [ -n "$dir_path" ]; do
        chmod +x "$dir_path" 2>/dev/null || true
        dir_path=$(dirname "$dir_path")
    done

    # Perbaikan jika folder berada di /root
    if [[ "$PWD" == "/root"* ]]; then
        print_warning "Proyek berada di dalam direktori /root. Membuka akses masuk direktori untuk Nginx..."
        chmod +x /root
    fi

    # Auto-adjust Nginx root path
    local current_root="$PWD/public"
    local app_url=$(grep "^APP_URL=" .env | cut -d'=' -f2- | sed 's/https\?:\/\///' | sed 's/\/$//' | tr -d '\r')
    local nginx_found=false
    if [ -n "$app_url" ]; then
        for conf in /etc/nginx/sites-available/*; do
            if [ -f "$conf" ] && (grep -q "server_name.*$app_url" "$conf" || grep -q "server_name.*${app_url//\./\\.}" "$conf"); then
                print_info "Menyesuaikan root directory Nginx di $conf menjadi $current_root..."
                sed -i "s|root .*/public;|root $current_root;|g" "$conf"
                nginx -t &>/dev/null && systemctl reload nginx
                nginx_found=true
            fi
        done
    fi
    if [ "$nginx_found" = false ] && [ -n "$app_url" ]; then
        print_warning "Konfigurasi Nginx untuk domain '$app_url' tidak ditemukan di /etc/nginx/sites-available/*."
        print_info "Jika ini VPS baru, pastikan Anda membuat virtual host Nginx untuk '$app_url' dengan root menuju: $current_root"
    fi

    # Membuka port yang dibutuhkan (80 & 443) jika belum terbuka
    print_info "Memastikan port 80 & 443 terbuka di Firewall..."
    if command -v iptables &>/dev/null; then
        iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT 2>/dev/null || true
        iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT 2>/dev/null || true
        if command -v netfilter-persistent &>/dev/null; then
            netfilter-persistent save 2>/dev/null || true
        fi
    fi

    if command -v ufw &>/dev/null; then
        ufw allow 80/tcp 2>/dev/null || true
        ufw allow 443/tcp 2>/dev/null || true
        ufw reload 2>/dev/null || true
    fi

    # Restart FastCGI & Nginx
    detect_php_service
    print_info "Memuat ulang layanan PHP ($php_service) & Web Server (Nginx)..."
    systemctl restart "$php_service" || service "$php_service" restart || true
    systemctl restart nginx || service nginx restart || true

    # Restart PM2 Queue Worker
    if command -v pm2 &>/dev/null; then
        print_info "Merestart PM2 Queue Worker..."
        pm2 restart vpn-queue-worker || pm2 start "php artisan queue:work --tries=3" --name vpn-queue-worker --cwd "$PWD"
        pm2 save
    fi

    # Hapus file unduhan Google Drive
    if [ "$sumber_backup" = "2" ] && [ -f "$selected_backup" ]; then
        rm -f "$selected_backup"
    fi

    print_success "Data website berhasil dipulihkan dengan sukses!"
}

manage_backup_restore() {
    while true; do
        echo
        echo -e "${BOLD}${CYAN}=====================================================================${NC}"
        echo -e "                 📂  MENU CADANGAN & PEMULIHAN  📂"
        echo -e "${BOLD}${CYAN}=====================================================================${NC}"
        echo -e "  [1] 💾  Buat Cadangan Baru (Backup Website)"
        echo -e "  [2] 🔄  Pulihkan Website dari Cadangan (Restore Website)"
        echo -e "  [3] 🚪  Kembali ke Menu Utama"
        echo -e "${BOLD}${CYAN}=====================================================================${NC}"
        read -p "Pilih opsi [1-3]: " backup_pilihan
        echo
        
        case "$backup_pilihan" in
            1)
                backup_website
                ;;
            2)
                restore_website
                ;;
            3)
                break
                ;;
            *)
                print_warning "Pilihan tidak valid. Silakan pilih 1-3."
                ;;
        esac
        
        echo
        read -p "Tekan [Enter] untuk kembali ke Menu Backup..." temp
    done
}

# =====================================================================
#  MENU 6: Menu Bot Telegram (Sub-menu)
# =====================================================================
setting_bot_token_admin() {
    print_border
    echo -e "      ${BOLD}${BLUE}🤖   SETTING BOT TOKEN & ID TELEGRAM ADMIN   🤖${NC}"
    print_border
    
    local old_token=""
    local old_admin=""
    local old_domain=""
    local old_api_base="https://api.telegram.org"
    
    if [ -f ".env" ]; then
        old_token=$(grep "^TELEGRAM_BOT_TOKEN=" .env | cut -d'=' -f2-)
        old_admin=$(grep "^TELEGRAM_ADMIN_ID=" .env | cut -d'=' -f2-)
        old_domain=$(grep "^APP_URL=" .env | cut -d'=' -f2- | sed -E 's|https?://||')
        local env_api_base=$(grep "^TELEGRAM_API_BASE=" .env | cut -d'=' -f2-)
        [ -n "$env_api_base" ] && old_api_base="$env_api_base"
    fi

    # Token
    if [ -n "$old_token" ]; then
        read -p "Masukkan Telegram Bot Token [$old_token]: " bot_token
        bot_token=${bot_token:-$old_token}
    else
        read -p "Masukkan Telegram Bot Token: " bot_token
    fi

    # Admin ID
    if [ -n "$old_admin" ]; then
        read -p "Masukkan Telegram Admin ID [$old_admin]: " admin_id
        admin_id=${admin_id:-$old_admin}
    else
        read -p "Masukkan Telegram Admin ID: " admin_id
    fi

    # Domain
    if [ -n "$old_domain" ]; then
        read -p "Masukkan Domain Website (tanpa https://, misal: toko.arizan.my.id) [$old_domain]: " web_domain
        web_domain=${web_domain:-$old_domain}
    else
        read -p "Masukkan Domain Website (tanpa https://, misal: toko.arizan.my.id): " web_domain
    fi

    # Telegram API Proxy
    read -p "Masukkan Telegram API Base URL [$old_api_base]: " api_base
    api_base=${api_base:-$old_api_base}

    if [ -z "$bot_token" ] || [ -z "$admin_id" ] || [ -z "$web_domain" ]; then
        print_error "Token, Admin ID, dan Domain tidak boleh kosong!"
        return 1
    fi

    print_info "Menulis konfigurasi ke berkas .env..."
    touch .env
    for key in TELEGRAM_BOT_TOKEN TELEGRAM_ADMIN_ID TELEGRAM_API_BASE; do
        if grep -q "^$key=" .env; then
            sed -i "/^$key=/d" .env
        fi
    done
    echo "TELEGRAM_BOT_TOKEN=$bot_token" >> .env
    echo "TELEGRAM_ADMIN_ID=$admin_id" >> .env
    echo "TELEGRAM_API_BASE=$api_base" >> .env

    # Update APP_URL
    if grep -q "^APP_URL=" .env; then
        sed -i "s|^APP_URL=.*|APP_URL=https://$web_domain|g" .env
    else
        echo "APP_URL=https://$web_domain" >> .env
    fi

    print_success "Konfigurasi bot disimpan di .env!"

    print_info "Clear cache konfigurasi Laravel..."
    php artisan optimize:clear >/dev/null 2>&1

    # Daftarkan webhook ke Telegram API
    print_info "Mendaftarkan Webhook ke Telegram API..."
    local webhook_url="https://$web_domain/webhook/telegram"
    local register_response
    register_response=$(curl -s -k -w "\nHTTP_STATUS:%{http_code}" "${api_base}/bot${bot_token}/setWebhook?url=${webhook_url}")
    
    local response_body=$(echo "$register_response" | sed '/HTTP_STATUS:/d')
    local http_status=$(echo "$register_response" | grep "HTTP_STATUS:" | cut -d':' -f2)

    if [ "$http_status" -eq 200 ] && echo "$response_body" | grep -q '"ok":true'; then
        print_success "Webhook Telegram berhasil didaftarkan ke: $webhook_url"
    else
        print_error "Gagal mendaftarkan webhook! HTTP Status: $http_status. Respons: $response_body"
        print_warning "Pastikan token bot valid dan domain VPS Anda sudah terpasang HTTPS (SSL)."
    fi
}

configure_bot() {
    while true; do
        clear
        echo
        echo -e "${BOLD}${CYAN}=====================================================================${NC}"
        echo -e "                            🤖  MENU BOT (TELEGRAM & WHATSAPP)  🤖"
        echo -e "${BOLD}${CYAN}=====================================================================${NC}"
        echo -e "  ${BOLD}${WHITE}[TELEGRAM BOT]${NC}"
        echo -e "    [1] ⚙️   Setting Bot Token & ID Telegram Admin"
        echo -e "    [2] 🧹  Clear Cache Konfigurasi Telegram"
        echo -e "    [3] ⚡  Tes Koneksi Bot Telegram"
        echo -e "  ${BOLD}${WHITE}[WHATSAPP BOT - BAILEYS]${NC}"
        echo -e "    [4] 🟢  Start/Restart WhatsApp Bot (PM2)"
        echo -e "    [5] 🔴  Stop WhatsApp Bot (PM2)"
        echo -e "    [6] 📊  Lihat Log WhatsApp Bot (PM2 Logs)"
        echo -e "  [0] 🚪  Kembali ke Menu Utama"
        echo -e "${BOLD}${CYAN}=====================================================================${NC}"
        read -p "Pilih opsi [0-6]: " bot_pilihan
        echo

        case "$bot_pilihan" in
            1)
                setting_bot_token_admin
                ;;
            2)
                print_info "Membersihkan cache konfigurasi Laravel..."
                php artisan config:clear
                php artisan optimize:clear
                print_success "Cache konfigurasi berhasil dibersihkan!"
                ;;
            3)
                print_info "Menguji koneksi bot Telegram..."
                php artisan telegram:test
                ;;
            4)
                if ! command -v pm2 &>/dev/null; then
                    print_error "PM2 tidak terpasang di VPS! Tidak dapat menjalankan bot di background."
                else
                    print_info "Menjalankan WhatsApp Bot di PM2..."
                    pm2 delete wa-gateway &>/dev/null || true
                    pm2 start index.js --name "wa-gateway" --cwd "$PROJECT_DIR/whatsapp-gateway"
                    pm2 save
                    print_success "WhatsApp Bot berhasil dijalankan di background!"
                fi
                ;;
            5)
                if ! command -v pm2 &>/dev/null; then
                    print_error "PM2 tidak terpasang di VPS!"
                else
                    print_info "Menghentikan WhatsApp Bot di PM2..."
                    pm2 stop wa-gateway &>/dev/null || true
                    pm2 delete wa-gateway &>/dev/null || true
                    pm2 save --force &>/dev/null || true
                    print_success "WhatsApp Bot berhasil dihentikan!"
                fi
                ;;
            6)
                if ! command -v pm2 &>/dev/null; then
                    print_error "PM2 tidak terpasang!"
                else
                    print_info "Menampilkan 20 baris log terakhir (Tekan Ctrl+C untuk keluar):"
                    pm2 logs wa-gateway --lines 20 --no-daemon
                fi
                ;;
            0|"")
                break
                ;;
            *)
                print_warning "Pilihan tidak valid. Silakan pilih 0-6."
                ;;
        esac
        echo
        read -p "Tekan [Enter] untuk melanjutkan..." temp
    done
}

# =====================================================================
#  MENU 7: Lihat Log Kesalahan (Error Log)
# =====================================================================
view_error_log() {
    print_border
    echo -e "      ${BOLD}${BLUE}🐞   LIHAT LOG ERROR WEBSITE (LARAVEL LOG)   🐞${NC}"
    print_border
    
    local log_file="storage/logs/laravel.log"
    if [ -f "$log_file" ]; then
        echo -e "${GREEN}Membuka log kesalahan terbaru di: $log_file${NC}"
        echo -e "${YELLOW}Tekan [Ctrl+C] untuk keluar dari monitor log dan kembali ke menu.${NC}"
        echo -e "---------------------------------------------------------------------"
        tail -n 50 -f "$log_file"
    else
        print_warning "Log kesalahan Laravel tidak ditemukan atau kosong di: $log_file"
    fi
}

# =====================================================================
#  MENU 8: Manajemen Saldo User (DUID) - dari duid.sh
# =====================================================================
duid_header() {
    clear
    echo ""
    garis_tebal
    echo -e "${CYAN}  💰  ${BOLD}MANAJEMEN SALDO${NC}${CYAN} - Kelola Saldo User${NC}"
    echo -e "${DIM}  Database: ${DB_CONNECTION} $([ "$DB_CONNECTION" != "sqlite" ] && echo "@ ${DB_HOST}:${DB_PORT}/${DB_DATABASE}")${NC}"
    garis_tebal
    echo ""
}

duid_lihat_saldo() {
    duid_header
    echo -e "${BOLD}${WHITE}  📋 DAFTAR SALDO SEMUA USER${NC}"
    echo ""
    garis

    local data=$(db_query \
        "SELECT u.id, u.name, u.email, u.role, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         ORDER BY u.id ASC;")

    if [ -z "$data" ]; then
        echo -e "  ${YELLOW}⚠ Tidak ada data user ditemukan.${NC}"
        garis
        return
    fi

    printf "  ${BOLD}${WHITE}%-4s │ %-20s │ %-28s │ %-8s │ %18s${NC}\n" "ID" "Nama" "Email" "Role" "Saldo"
    garis

    local total_saldo=0
    local total_users=0

    while IFS='|' read -r id name email role balance; do
        total_users=$((total_users + 1))
        total_saldo=$(echo "$total_saldo + $balance" | bc 2>/dev/null || echo "$total_saldo")

        local role_color="${WHITE}"
        if [ "$role" = "admin" ]; then
            role_color="${RED}"
        elif [ "$role" = "seller" ]; then
            role_color="${MAGENTA}"
        else
            role_color="${GREEN}"
        fi

        local saldo_color="${GREEN}"
        local saldo_int=$(echo "$balance" | awk '{printf "%d", $1}')
        if [ "$saldo_int" -eq 0 ]; then
            saldo_color="${DIM}"
        elif [ "$saldo_int" -lt 0 ]; then
            saldo_color="${RED}"
        fi

        local saldo_formatted=$(format_rupiah "$balance")
        local name_short=$(echo "$name" | cut -c1-20)
        local email_short=$(echo "$email" | cut -c1-28)

        printf "  %-4s │ %-20s │ %-28s │ ${role_color}%-8s${NC} │ ${saldo_color}%18s${NC}\n" \
            "$id" "$name_short" "$email_short" "$role" "$saldo_formatted"
    done <<< "$data"

    garis
    local total_formatted=$(format_rupiah "$total_saldo")
    echo -e "  ${BOLD}Total User: ${CYAN}$total_users${NC}  ${BOLD}│  Total Saldo: ${GREEN}$total_formatted${NC}"
    garis
    echo ""
}

duid_edit_saldo() {
    duid_header
    echo -e "${BOLD}${WHITE}  ✏️  EDIT SALDO USER${NC}"
    echo ""

    local data=$(db_query \
        "SELECT u.id, u.name, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         ORDER BY u.id ASC;")

    garis
    printf "  ${BOLD}%-4s │ %-25s │ %18s${NC}\n" "ID" "Nama" "Saldo"
    garis

    while IFS='|' read -r id name balance; do
        local saldo_formatted=$(format_rupiah "$balance")
        printf "  %-4s │ %-25s │ %18s\n" "$id" "$(echo "$name" | cut -c1-25)" "$saldo_formatted"
    done <<< "$data"

    garis
    echo ""

    echo -ne "  ${YELLOW}Masukkan ID user yang ingin diedit (0 = kembali): ${NC}"
    read -r user_id

    if [ "$user_id" = "0" ] || [ -z "$user_id" ]; then
        return
    fi

    local user_info=$(db_query \
        "SELECT u.id, u.name, u.email, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         WHERE u.id = $user_id;")

    if [ -z "$user_info" ]; then
        echo -e "\n  ${RED}✗ User dengan ID $user_id tidak ditemukan.${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r
        return
    fi

    IFS='|' read -r uid uname uemail ubalance <<< "$user_info"

    echo ""
    garis
    echo -e "  ${BOLD}User terpilih:${NC}"
    echo -e "  ${WHITE}ID    : ${CYAN}$uid${NC}"
    echo -e "  ${WHITE}Nama  : ${CYAN}$uname${NC}"
    echo -e "  ${WHITE}Email : ${CYAN}$uemail${NC}"
    echo -e "  ${WHITE}Saldo : ${GREEN}$(format_rupiah "$ubalance")${NC}"
    garis
    echo ""

    echo -e "  ${BOLD}Pilih aksi:${NC}"
    echo -e "  ${WHITE}1)${NC} Set saldo ke nilai tertentu"
    echo -e "  ${WHITE}2)${NC} Tambah saldo"
    echo -e "  ${WHITE}3)${NC} Kurangi saldo"
    echo -e "  ${WHITE}0)${NC} Kembali"
    echo ""
    echo -ne "  ${YELLOW}Pilihan: ${NC}"
    read -r aksi

    local saldo_baru=""
    case "$aksi" in
        1)
            echo -ne "\n  ${YELLOW}Masukkan saldo baru (angka, contoh: 50000): ${NC}"
            read -r saldo_baru
            ;;
        2)
            echo -ne "\n  ${YELLOW}Masukkan jumlah yang ditambah (contoh: 10000): ${NC}"
            read -r jumlah
            saldo_baru=$(echo "$ubalance + $jumlah" | bc 2>/dev/null)
            if [ -z "$saldo_baru" ]; then
                echo -e "  ${RED}✗ Input tidak valid.${NC}"
                echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
                read -r
                return
            fi
            ;;
        3)
            echo -ne "\n  ${YELLOW}Masukkan jumlah yang dikurangi (contoh: 10000): ${NC}"
            read -r jumlah
            saldo_baru=$(echo "$ubalance - $jumlah" | bc 2>/dev/null)
            if [ -z "$saldo_baru" ]; then
                echo -e "  ${RED}✗ Input tidak valid.${NC}"
                echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
                read -r
                return
            fi
            ;;
        0|"")
            return
            ;;
        *)
            echo -e "\n  ${RED}✗ Pilihan tidak valid.${NC}"
            echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
            read -r
            return
            ;;
    esac

    # Validasi input numerik
    if ! echo "$saldo_baru" | grep -qE '^-?[0-9]+\.?[0-9]*$'; then
        echo -e "\n  ${RED}✗ Input saldo tidak valid. Masukkan angka saja.${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r
        return
    fi

    # Konfirmasi
    echo ""
    garis
    echo -e "  ${BOLD}${YELLOW}⚠ KONFIRMASI PERUBAHAN:${NC}"
    echo -e "  ${WHITE}User   : ${CYAN}$uname${NC} (ID: $uid)"
    echo -e "  ${WHITE}Sebelum: ${RED}$(format_rupiah "$ubalance")${NC}"
    echo -e "  ${WHITE}Sesudah: ${GREEN}$(format_rupiah "$saldo_baru")${NC}"
    garis
    echo ""
    echo -ne "  ${YELLOW}Yakin ingin mengubah? (y/n): ${NC}"
    read -r konfirmasi

    if [ "$konfirmasi" != "y" ] && [ "$konfirmasi" != "Y" ]; then
        echo -e "\n  ${DIM}Dibatalkan.${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r
        return
    fi

    # Cek apakah record user_balances sudah ada
    local existing=$(db_query "SELECT COUNT(*) FROM user_balances WHERE user_id = $user_id;")

    # Timestamp sesuai DB
    local now_func="datetime('now')"
    if [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "mariadb" ]; then
        now_func="NOW()"
    fi

    if [ "$existing" -gt 0 ]; then
        db_query "UPDATE user_balances SET balance = $saldo_baru, updated_at = $now_func WHERE user_id = $user_id;"
    else
        db_query "INSERT INTO user_balances (user_id, balance, created_at, updated_at) VALUES ($user_id, $saldo_baru, $now_func, $now_func);"
    fi

    if [ $? -eq 0 ]; then
        echo -e "\n  ${GREEN}✓ Saldo berhasil diubah!${NC}"

        # Catat ke balance_transactions
        db_query \
            "INSERT INTO balance_transactions (user_id, type, amount, balance_before, balance_after, description, created_at, updated_at)
             VALUES ($user_id, 'admin_adjustment', ABS($saldo_baru - $ubalance), $ubalance, $saldo_baru, 'Diubah via tokoctl.sh', $now_func, $now_func);"

        local saldo_terbaru=$(db_query "SELECT balance FROM user_balances WHERE user_id = $user_id;")
        echo -e "  ${WHITE}Saldo terbaru: ${GREEN}$(format_rupiah "$saldo_terbaru")${NC}"
    else
        echo -e "\n  ${RED}✗ Gagal mengubah saldo. Periksa database.${NC}"
    fi

    echo ""
    echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
    read -r
}

duid_cari_user() {
    duid_header
    echo -e "${BOLD}${WHITE}  🔍 CARI USER${NC}"
    echo ""
    echo -ne "  ${YELLOW}Masukkan nama atau email (sebagian juga bisa): ${NC}"
    read -r keyword

    if [ -z "$keyword" ]; then
        return
    fi

    echo ""
    garis

    local data=$(db_query \
        "SELECT u.id, u.name, u.email, u.role, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         WHERE u.name LIKE '%$keyword%' OR u.email LIKE '%$keyword%'
         ORDER BY u.id ASC;")

    if [ -z "$data" ]; then
        echo -e "  ${YELLOW}⚠ Tidak ada user yang cocok dengan '$keyword'.${NC}"
        garis
        echo ""
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r
        return
    fi

    printf "  ${BOLD}%-4s │ %-20s │ %-28s │ %-8s │ %18s${NC}\n" "ID" "Nama" "Email" "Role" "Saldo"
    garis

    while IFS='|' read -r id name email role balance; do
        local saldo_formatted=$(format_rupiah "$balance")
        local role_color="${GREEN}"
        [ "$role" = "admin" ] && role_color="${RED}"
        [ "$role" = "seller" ] && role_color="${MAGENTA}"

        printf "  %-4s │ %-20s │ %-28s │ ${role_color}%-8s${NC} │ %18s\n" \
            "$id" "$(echo "$name" | cut -c1-20)" "$(echo "$email" | cut -c1-28)" "$role" "$saldo_formatted"
    done <<< "$data"

    garis
    echo ""
    echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
    read -r
}

duid_lihat_daftar_user() {
    local data=$(db_query \
        "SELECT u.id, u.name, u.email, u.role, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         ORDER BY u.id ASC;")

    garis
    printf "  ${BOLD}%-4s │ %-20s │ %-28s │ %-8s │ %18s${NC}\n" "ID" "Nama" "Email" "Role" "Saldo"
    garis

    while IFS='|' read -r id name email role balance; do
        local saldo_formatted=$(format_rupiah "$balance")
        local role_color="${WHITE}"
        if [ "$role" = "admin" ]; then
            role_color="${RED}"
        elif [ "$role" = "seller" ]; then
            role_color="${MAGENTA}"
        else
            role_color="${GREEN}"
        fi
        printf "  %-4s │ %-20s │ %-28s │ ${role_color}%-8s${NC} │ %18s\n" \
            "$id" "$(echo "$name" | cut -c1-20)" "$(echo "$email" | cut -c1-28)" "$role" "$saldo_formatted"
    done <<< "$data"
    garis
    echo ""
}

duid_reset_riwayat_user() {
    duid_header
    echo -e "${BOLD}${WHITE}  🗑️  RESET RIWAYAT USER (PESANAN & TRANSAKSI SALDO)${NC}"
    echo ""
    echo -e "  Pilih cakupan reset:"
    echo -e "    ${WHITE}1)${NC} Semua User"
    echo -e "    ${WHITE}2)${NC} User/Buyer Tertentu"
    echo -e "    ${WHITE}0)${NC} Batal"
    echo ""
    echo -ne "  ${YELLOW}Pilihan: ${NC}"
    read -r cakupan

    if [ "$cakupan" = "0" ] || [ -z "$cakupan" ]; then
        return
    fi

    if [ "$cakupan" = "1" ]; then
        echo ""
        garis
        echo -e "  ${RED}${BOLD}⚠ PERINGATAN KELAS BERAT: Anda akan menghapus SELURUH riwayat pesanan,${NC}"
        echo -e "  ${RED}${BOLD}komplain, log pembayaran, dan transaksi saldo dari SEMUA user!${NC}"
        garis
        echo -ne "  ${YELLOW}Ketik 'RESET-ALL' untuk mengonfirmasi: ${NC}"
        read -r konfirmasi
        if [ "$konfirmasi" != "RESET-ALL" ]; then
            echo -e "\n  ${DIM}Dibatalkan.${NC}"
            sleep 1.5
            return
        fi

        echo -e "\n  ${DIM}Sedang mereset riwayat semua user...${NC}"
        db_query "DELETE FROM complaints;"
        db_query "DELETE FROM payment_logs;"
        db_query "DELETE FROM orders;"
        db_query "DELETE FROM balance_transactions;"
        
        echo -e "  ${GREEN}✓ Berhasil mereset riwayat semua user!${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r

    elif [ "$cakupan" = "2" ]; then
        duid_lihat_daftar_user
        echo -ne "  ${YELLOW}Masukkan ID user yang ingin direset riwayatnya (0 = kembali): ${NC}"
        read -r user_id
        if [ "$user_id" = "0" ] || [ -z "$user_id" ]; then
            return
        fi

        local user_info=$(db_query "SELECT id, name, email, phone FROM users WHERE id = $user_id;")
        if [ -z "$user_info" ]; then
            echo -e "\n  ${RED}✗ User dengan ID $user_id tidak ditemukan.${NC}"
            echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
            read -r
            return
        fi

        IFS='|' read -r uid uname uemail uphone <<< "$user_info"

        echo ""
        garis
        echo -e "  ${YELLOW}${BOLD}⚠ KONFIRMASI RESET RIWAYAT USER:${NC}"
        echo -e "  ${WHITE}ID    : ${CYAN}$uid${NC}"
        echo -e "  ${WHITE}Nama  : ${CYAN}$uname${NC}"
        echo -e "  ${WHITE}Email : ${CYAN}$uemail${NC}"
        echo -e "  ${WHITE}Phone : ${CYAN}$uphone${NC}"
        garis
        echo -ne "  ${YELLOW}Yakin ingin mereset riwayat user ini? (y/n): ${NC}"
        read -r konfirmasi

        if [ "$konfirmasi" != "y" ] && [ "$konfirmasi" != "Y" ]; then
            echo -e "\n  ${DIM}Dibatalkan.${NC}"
            echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
            read -r
            return
        fi

        echo -e "\n  ${DIM}Sedang mereset riwayat user $uname...${NC}"
        
        local query_order_ids="SELECT id FROM orders WHERE user_id = $uid"
        if [ -n "$uemail" ]; then
            query_order_ids="$query_order_ids OR email_or_whatsapp = '$uemail'"
        fi
        if [ -n "$uphone" ]; then
            query_order_ids="$query_order_ids OR email_or_whatsapp = '$uphone'"
        fi
        
        db_query "DELETE FROM complaints WHERE user_id = $uid OR order_id IN ($query_order_ids);"
        db_query "DELETE FROM payment_logs WHERE matched_order_id IN ($query_order_ids);"
        
        local delete_orders_sql="DELETE FROM orders WHERE user_id = $uid"
        if [ -n "$uemail" ]; then
            delete_orders_sql="$delete_orders_sql OR email_or_whatsapp = '$uemail'"
        fi
        if [ -n "$uphone" ]; then
            delete_orders_sql="$delete_orders_sql OR email_or_whatsapp = '$uphone'"
        fi
        db_query "$delete_orders_sql;"
        db_query "DELETE FROM balance_transactions WHERE user_id = $uid;"

        echo -e "  ${GREEN}✓ Berhasil mereset riwayat user $uname!${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r
    fi
}

duid_reset_keuangan_seller() {
    duid_header
    echo -e "${BOLD}${WHITE}  💰  RESET KEUANGAN DASHBOARD SELLER${NC}"
    echo ""
    echo -e "  Pilih cakupan reset:"
    echo -e "    ${WHITE}1)${NC} Semua Seller"
    echo -e "    ${WHITE}2)${NC} Seller/Admin Tertentu"
    echo -e "    ${WHITE}0)${NC} Batal"
    echo ""
    echo -ne "  ${YELLOW}Pilihan: ${NC}"
    read -r cakupan

    if [ "$cakupan" = "0" ] || [ -z "$cakupan" ]; then
        return
    fi

    if [ "$cakupan" = "1" ]; then
        echo ""
        garis
        echo -e "  ${RED}${BOLD}⚠ PERINGATAN: Anda akan menghapus data penjualan seluruh seller,${NC}"
        echo -e "  ${RED}${BOLD}termasuk komisi yang diperoleh, transfer wallet, dan saldo tertahan!${NC}"
        echo -e "  ${YELLOW}${BOLD}Info: Saldo realtime Orderkuota tidak akan terganggu.${NC}"
        garis
        echo -ne "  ${YELLOW}Ketik 'RESET-KEUANGAN-ALL' untuk mengonfirmasi: ${NC}"
        read -r konfirmasi
        if [ "$konfirmasi" != "RESET-KEUANGAN-ALL" ]; then
            echo -e "\n  ${DIM}Dibatalkan.${NC}"
            sleep 1.5
            return
        fi

        echo -e "\n  ${DIM}Sedang mereset keuangan semua seller...${NC}"
        db_query "DELETE FROM complaints WHERE order_id IN (SELECT o.id FROM orders o JOIN products p ON o.product_id = p.id);"
        db_query "DELETE FROM payment_logs WHERE matched_order_id IN (SELECT o.id FROM orders o JOIN products p ON o.product_id = p.id);"
        db_query "DELETE FROM orders WHERE product_id IN (SELECT id FROM products);"
        db_query "UPDATE orders SET commission_earned = 0;"
        db_query "DELETE FROM balance_transactions WHERE type = 'transfer_in' AND description LIKE '%Transfer dari Dompet Seller%';"
        db_query "UPDATE user_balances SET held_balance = 0;"

        echo -e "  ${GREEN}✓ Berhasil mereset keuangan semua seller! (Saldo Orderkuota aman)${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r

    elif [ "$cakupan" = "2" ]; then
        duid_lihat_daftar_user
        echo -ne "  ${YELLOW}Masukkan ID user/seller/admin yang ingin direset keuangannya (0 = kembali): ${NC}"
        read -r user_id
        if [ "$user_id" = "0" ] || [ -z "$user_id" ]; then
            return
        fi

        local user_info=$(db_query "SELECT id, name, email, role FROM users WHERE id = $user_id;")
        if [ -z "$user_info" ]; then
            echo -e "\n  ${RED}✗ User dengan ID $user_id tidak ditemukan.${NC}"
            echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
            read -r
            return
        fi

        IFS='|' read -r uid uname uemail urole <<< "$user_info"

        echo ""
        garis
        echo -e "  ${YELLOW}${BOLD}⚠ KONFIRMASI RESET KEUANGAN SELLER/ADMIN:${NC}"
        echo -e "  ${WHITE}ID    : ${CYAN}$uid${NC}"
        echo -e "  ${WHITE}Nama  : ${CYAN}$uname${NC}"
        echo -e "  ${WHITE}Role  : ${CYAN}$urole${NC}"
        echo -e "  ${WHITE}Email : ${CYAN}$uemail${NC}"
        echo -e "  ${YELLOW}${BOLD}Info  : Saldo realtime Orderkuota tidak akan terganggu.${NC}"
        garis
        echo -ne "  ${YELLOW}Yakin ingin mereset keuangan dashboard seller untuk user ini? (y/n): ${NC}"
        read -r konfirmasi

        if [ "$konfirmasi" != "y" ] && [ "$konfirmasi" != "Y" ]; then
            echo -e "\n  ${DIM}Dibatalkan.${NC}"
            echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
            read -r
            return
        fi

        echo -e "\n  ${DIM}Sedang mereset keuangan dashboard seller untuk $uname...${NC}"
        
        db_query "DELETE FROM complaints WHERE order_id IN (SELECT id FROM orders WHERE product_id IN (SELECT id FROM products WHERE user_id = $uid));"
        db_query "DELETE FROM payment_logs WHERE matched_order_id IN (SELECT id FROM orders WHERE product_id IN (SELECT id FROM products WHERE user_id = $uid));"
        db_query "DELETE FROM orders WHERE product_id IN (SELECT id FROM products WHERE user_id = $uid);"
        db_query "UPDATE orders SET commission_earned = 0 WHERE user_id = $uid;"
        db_query "DELETE FROM balance_transactions WHERE user_id = $uid AND type = 'transfer_in' AND description LIKE '%Transfer dari Dompet Seller%';"
        db_query "UPDATE user_balances SET held_balance = 0 WHERE user_id = $uid;"

        echo -e "  ${GREEN}✓ Berhasil mereset keuangan dashboard seller untuk $uname! (Saldo Orderkuota aman)${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r
    fi
}

duid_reset_pesanan_seller() {
    duid_header
    echo -e "${BOLD}${WHITE}  📦  RESET RIWAYAT PESANAN SELLER PORTAL${NC}"
    echo ""
    echo -e "  Pilih cakupan reset:"
    echo -e "    ${WHITE}1)${NC} Semua Seller"
    echo -e "    ${WHITE}2)${NC} Seller/Admin Tertentu"
    echo -e "    ${WHITE}0)${NC} Batal"
    echo ""
    echo -ne "  ${YELLOW}Pilihan: ${NC}"
    read -r cakupan

    if [ "$cakupan" = "0" ] || [ -z "$cakupan" ]; then
        return
    fi

    if [ "$cakupan" = "1" ]; then
        echo ""
        garis
        echo -e "  ${RED}${BOLD}⚠ PERINGATAN: Anda akan menghapus SELURUH riwayat pesanan (penjualan produk)${NC}"
        echo -e "  ${RED}${BOLD}yang ada di portal transaksi semua seller/admin!${NC}"
        garis
        echo -ne "  ${YELLOW}Ketik 'RESET-PESANAN-ALL' untuk mengonfirmasi: ${NC}"
        read -r konfirmasi
        if [ "$konfirmasi" != "RESET-PESANAN-ALL" ]; then
            echo -e "\n  ${DIM}Dibatalkan.${NC}"
            sleep 1.5
            return
        fi

        echo -e "\n  ${DIM}Sedang mereset riwayat pesanan di seller portal...${NC}"
        db_query "DELETE FROM complaints WHERE order_id IN (SELECT o.id FROM orders o JOIN products p ON o.product_id = p.id);"
        db_query "DELETE FROM payment_logs WHERE matched_order_id IN (SELECT o.id FROM orders o JOIN products p ON o.product_id = p.id);"
        db_query "DELETE FROM orders WHERE product_id IN (SELECT id FROM products);"

        echo -e "  ${GREEN}✓ Berhasil mereset riwayat pesanan di semua seller portal!${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r

    elif [ "$cakupan" = "2" ]; then
        duid_lihat_daftar_user
        echo -ne "  ${YELLOW}Masukkan ID user/seller/admin yang ingin direset riwayat pesanan portalnya (0 = kembali): ${NC}"
        read -r user_id
        if [ "$user_id" = "0" ] || [ -z "$user_id" ]; then
            return
        fi

        local user_info=$(db_query "SELECT id, name, email, role FROM users WHERE id = $user_id;")
        if [ -z "$user_info" ]; then
            echo -e "\n  ${RED}✗ User dengan ID $user_id tidak ditemukan.${NC}"
            echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
            read -r
            return
        fi

        IFS='|' read -r uid uname uemail urole <<< "$user_info"

        echo ""
        garis
        echo -e "  ${YELLOW}${BOLD}⚠ KONFIRMASI RESET PESANAN SELLER PORTAL:${NC}"
        echo -e "  ${WHITE}ID    : ${CYAN}$uid${NC}"
        echo -e "  ${WHITE}Nama  : ${CYAN}$uname${NC}"
        echo -e "  ${WHITE}Role  : ${CYAN}$urole${NC}"
        echo -e "  ${WHITE}Email : ${CYAN}$uemail${NC}"
        garis
        echo -ne "  ${YELLOW}Yakin ingin mereset riwayat pesanan seller portal untuk user ini? (y/n): ${NC}"
        read -r konfirmasi

        if [ "$konfirmasi" != "y" ] && [ "$konfirmasi" != "Y" ]; then
            echo -e "\n  ${DIM}Dibatalkan.${NC}"
            echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
            read -r
            return
        fi

        echo -e "\n  ${DIM}Sedang mereset riwayat pesanan seller portal untuk $uname...${NC}"
        db_query "DELETE FROM complaints WHERE order_id IN (SELECT id FROM orders WHERE product_id IN (SELECT id FROM products WHERE user_id = $uid));"
        db_query "DELETE FROM payment_logs WHERE matched_order_id IN (SELECT id FROM orders WHERE product_id IN (SELECT id FROM products WHERE user_id = $uid));"
        db_query "DELETE FROM orders WHERE product_id IN (SELECT id FROM products WHERE user_id = $uid);"

        echo -e "  ${GREEN}✓ Berhasil mereset riwayat pesanan seller portal untuk $uname!${NC}"
        echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
        read -r
    fi
}

duid_reset_menu() {
    while true; do
        duid_header
        echo -e "  ${BOLD}🔄 MENU RESET DATA:${NC}"
        echo ""
        echo -e "    ${WHITE}1)${NC}  🗑️  Reset Riwayat User (Pesanan & Transaksi Saldo)"
        echo -e "    ${WHITE}2)${NC}  💰  Reset Keuangan Dashboard Seller"
        echo -e "    ${WHITE}3)${NC}  📦  Reset Riwayat Pesanan Seller Portal"
        echo -e "    ${WHITE}0)${NC}  🔙  Kembali ke Menu Saldo"
        echo ""
        echo -ne "  ${YELLOW}Pilihan: ${NC}"
        read -r reset_pilihan

        case "$reset_pilihan" in
            1) duid_reset_riwayat_user ;;
            2) duid_reset_keuangan_seller ;;
            3) duid_reset_pesanan_seller ;;
            0|"") break ;;
            *) echo -e "\n  ${RED}✗ Pilihan tidak valid.${NC}"; sleep 1 ;;
        esac
    done
}
 
manage_transaction_history() {
    # Inisialisasi koneksi database saat pertama kali masuk
    if ! duid_init_db; then
        print_error "Tidak dapat masuk karena koneksi database gagal."
        return 1
    fi

    while true; do
        duid_header
        echo -e "  ${BOLD}📊  KELOLA RIWAYAT TRANSAKSI:${NC}"
        echo ""
        echo -e "    ${WHITE}1)${NC}  Hapus riwayat transaksi user (pilih user)"
        echo -e "    ${WHITE}2)${NC}  Hapus semua riwayat transaksi user"
        echo -e "    ${WHITE}0)${NC}  Kembali ke Menu Utama"
        echo ""
        echo -ne "  ${YELLOW}Pilihan: ${NC}"
        read -r tx_pilihan

        case "$tx_pilihan" in
            1)
                duid_lihat_daftar_user
                echo -ne "  ${YELLOW}Masukkan ID user yang ingin dihapus riwayat transaksinya (0 = kembali): ${NC}"
                read -r user_id
                if [ "$user_id" = "0" ] || [ -z "$user_id" ]; then
                    continue
                fi

                local user_info=$(db_query "SELECT id, name, email, phone FROM users WHERE id = $user_id;")
                if [ -z "$user_info" ]; then
                    echo -e "\n  ${RED}✗ User dengan ID $user_id tidak ditemukan.${NC}"
                    echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
                    read -r
                    continue
                fi

                IFS='|' read -r uid uname uemail uphone <<< "$user_info"

                echo ""
                garis
                echo -e "  ${YELLOW}${BOLD}⚠ KONFIRMASI HAPUS RIWAYAT TRANSAKSI USER:${NC}"
                echo -e "  ${WHITE}ID    : ${CYAN}$uid${NC}"
                echo -e "  ${WHITE}Nama  : ${CYAN}$uname${NC}"
                echo -e "  ${WHITE}Email : ${CYAN}$uemail${NC}"
                echo -e "  ${WHITE}Phone : ${CYAN}$uphone${NC}"
                garis
                echo -ne "  ${YELLOW}Yakin ingin menghapus riwayat transaksi user ini? (y/n): ${NC}"
                read -r konfirmasi

                if [ "$konfirmasi" != "y" ] && [ "$konfirmasi" != "Y" ]; then
                    echo -e "\n  ${DIM}Dibatalkan.${NC}"
                    echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
                    read -r
                    continue
                fi

                echo -e "\n  ${DIM}Sedang menghapus riwayat transaksi user $uname...${NC}"
                
                local query_order_ids="SELECT id FROM orders WHERE user_id = $uid"
                if [ -n "$uemail" ]; then
                    query_order_ids="$query_order_ids OR email_or_whatsapp = '$uemail'"
                fi
                if [ -n "$uphone" ]; then
                    query_order_ids="$query_order_ids OR email_or_whatsapp = '$uphone'"
                fi
                
                db_query "DELETE FROM complaints WHERE user_id = $uid OR order_id IN ($query_order_ids);"
                db_query "DELETE FROM payment_logs WHERE matched_order_id IN ($query_order_ids);"
                
                local delete_orders_sql="DELETE FROM orders WHERE user_id = $uid"
                if [ -n "$uemail" ]; then
                    delete_orders_sql="$delete_orders_sql OR email_or_whatsapp = '$uemail'"
                fi
                if [ -n "$uphone" ]; then
                    delete_orders_sql="$delete_orders_sql OR email_or_whatsapp = '$uphone'"
                fi
                db_query "$delete_orders_sql;"
                db_query "DELETE FROM balance_transactions WHERE user_id = $uid;"

                echo -e "  ${GREEN}✓ Berhasil menghapus riwayat transaksi user $uname!${NC}"
                echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
                read -r
                ;;
            2)
                echo ""
                garis
                echo -e "  ${RED}${BOLD}⚠ PERINGATAN KELAS BERAT: Anda akan menghapus SELURUH riwayat pesanan,${NC}"
                echo -e "  ${RED}${BOLD}komplain, log pembayaran, dan transaksi saldo dari SEMUA user!${NC}"
                garis
                echo -ne "  ${YELLOW}Ketik 'RESET-ALL' untuk mengonfirmasi: ${NC}"
                read -r konfirmasi
                if [ "$konfirmasi" != "RESET-ALL" ]; then
                    echo -e "\n  ${DIM}Dibatalkan.${NC}"
                    sleep 1.5
                    continue
                fi

                echo -e "\n  ${DIM}Sedang menghapus riwayat transaksi semua user...${NC}"
                db_query "DELETE FROM complaints;"
                db_query "DELETE FROM payment_logs;"
                db_query "DELETE FROM orders;"
                db_query "DELETE FROM balance_transactions;"
                
                echo -e "  ${GREEN}✓ Berhasil menghapus riwayat transaksi semua user!${NC}"
                echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
                read -r
                ;;
            0|"")
                break
                ;;
            *)
                echo -e "\n  ${RED}✗ Pilihan tidak valid.${NC}"
                sleep 1
                ;;
        esac
    done
}

uninstall_website() {
    clear
    print_border
    echo -e "      ${BOLD}${RED}🚨   UNINSTALL & HAPUS TOTAL WEBSITE LARAVEL   🚨${NC}"
    print_border
    echo -e "  ${YELLOW}${BOLD}PERINGATAN KERAS! Tindakan ini bersifat DESTRUKTIF & TIDAK BISA DIBATALKAN!${NC}"
    echo -e "  Skrip ini akan menghapus seluruh data berikut secara permanen:"
    echo -e "    1. Database MySQL / SQLite beserta User database terkait."
    echo -e "    2. Konfigurasi Nginx Server Block & Symlink domain."
    echo -e "    3. Sertifikat SSL Certbot (Let's Encrypt)."
    echo -e "    4. Antrean Queue Worker PM2."
    echo -e "    5. Alias perintah cepat 'set' global."
    echo -e "    6. ${RED}${BOLD}SELURUH FOLDER PROJECT LARAVEL INI ($PROJECT_DIR)${NC}"
    print_border
    echo ""
    echo -ne "  Ketik ${RED}${BOLD}UNINSTALL-TOTAL${NC} untuk mengonfirmasi: "
    read -r konfirmasi

    if [ "$konfirmasi" != "UNINSTALL-TOTAL" ]; then
        print_warning "Uninstall dibatalkan oleh pengguna."
        return 1
    fi

    echo -ne "  ${YELLOW}Apakah Anda benar-benar yakin? Ini kesempatan terakhir untuk membatalkan! (y/n): ${NC}"
    read -r konfirmasi_final
    if [[ ! "$konfirmasi_final" =~ ^[Yy]$ ]]; then
        print_warning "Uninstall dibatalkan oleh pengguna."
        return 1
    fi

    print_info "Memulai proses penghapusan total..."

    # Ambil konfigurasi sebelum menghapus
    local db_conn=""
    local db_name=""
    local db_user=""
    local app_url=""
    
    if [ -f ".env" ]; then
        db_conn=$(grep "^DB_CONNECTION=" .env | cut -d'=' -f2- | tr -d '\r')
        db_name=$(grep "^DB_DATABASE=" .env | cut -d'=' -f2- | tr -d '\r')
        db_user=$(grep "^DB_USERNAME=" .env | cut -d'=' -f2- | tr -d '\r')
        app_url=$(grep "^APP_URL=" .env | cut -d'=' -f2- | sed -E 's|https?://||' | sed 's|/$||' | tr -d '\r')
    fi

    # 1. Hapus Database & User (jika MySQL)
    if [ "$db_conn" = "mysql" ] && [ -n "$db_name" ]; then
        print_info "Menghapus Database MySQL: $db_name dan User: $db_user..."
        local db_pass=$(grep "^DB_PASSWORD=" .env | cut -d'=' -f2- | tr -d '\r')
        local db_host=$(grep "^DB_HOST=" .env | cut -d'=' -f2- | tr -d '\r')
        local db_port=$(grep "^DB_PORT=" .env | cut -d'=' -f2- | tr -d '\r')
        [ -z "$db_host" ] && db_host="127.0.0.1"
        [ -z "$db_port" ] && db_port="3306"

        local mysql_opts="-h $db_host -P $db_port"
        if mysql $mysql_opts -u root -e "DROP DATABASE IF EXISTS \`$db_name\`;" 2>/dev/null; then
            mysql $mysql_opts -u root -e "DROP USER IF EXISTS '$db_user'@'localhost';" 2>/dev/null
            mysql $mysql_opts -u root -e "DROP USER IF EXISTS '$db_user'@'127.0.0.1';" 2>/dev/null
            mysql $mysql_opts -u root -e "FLUSH PRIVILEGES;" 2>/dev/null
            print_success "Database & User MySQL berhasil dihapus."
        else
            # Fallback menggunakan kredensial .env
            if [ -n "$db_pass" ]; then
                export MYSQL_PWD="$db_pass"
            fi
            if mysql $mysql_opts -u "$db_user" -e "DROP DATABASE IF EXISTS \`$db_name\`;" 2>/dev/null; then
                print_success "Database MySQL berhasil dihapus via kredensial lokal."
            else
                print_warning "Gagal menghapus database secara otomatis. Silakan hapus database '$db_name' secara manual jika diperlukan."
            fi
            unset MYSQL_PWD
        fi
    fi

    # 2. Hapus PM2 Queue Worker
    if command -v pm2 &>/dev/null; then
        print_info "Menghentikan & menghapus PM2 Queue Worker..."
        pm2 delete vpn-queue-worker 2>/dev/null || true
        pm2 save --force 2>/dev/null || true
    fi

    # 3. Hapus Sertifikat SSL Certbot
    if [ -n "$app_url" ] && command -v certbot &>/dev/null; then
        print_info "Menghapus sertifikat SSL Certbot untuk domain: $app_url..."
        certbot delete --cert-name "$app_url" --non-interactive 2>/dev/null || true
        rm -rf "/etc/letsencrypt/live/$app_url" "/etc/letsencrypt/archive/$app_url" "/etc/letsencrypt/renewal/$app_url.conf" 2>/dev/null || true
    fi

    # 4. Hapus Konfigurasi Nginx Server Block
    if [ -n "$app_url" ]; then
        print_info "Menghapus konfigurasi Nginx untuk domain: $app_url..."
        rm -f "/etc/nginx/sites-enabled/$app_url"
        rm -f "/etc/nginx/sites-available/$app_url"
        nginx -t &>/dev/null && systemctl restart nginx 2>/dev/null || true
    fi

    # 5. Hapus Alias Global 'set'
    print_info "Menghapus alias 'set' global..."
    rm -f /usr/local/bin/set
    local files_to_clean=("/etc/bash.bashrc" "/etc/profile" "$HOME/.bashrc" "$HOME/.zshrc")
    for file in "${files_to_clean[@]}"; do
        if [ -f "$file" ]; then
            sed -i '/# Alias untuk panel VPS toko/d' "$file" 2>/dev/null || true
            sed -i '/alias set=/d' "$file" 2>/dev/null || true
        fi
    done

    # 6. Hapus Seluruh Folder Project
    print_info "Menghapus folder project website ($PROJECT_DIR)..."
    if [ -n "$PROJECT_DIR" ] && [ "$PROJECT_DIR" != "/" ] && [ "$PROJECT_DIR" != "/home" ] && [ "$PROJECT_DIR" != "/var" ]; then
        cd /
        rm -rf "$PROJECT_DIR"
    fi
    
    print_success "Uninstall selesai! VPS Anda sekarang bersih dari data website ini."
    echo -e "${YELLOW}Koneksi SSH mungkin perlu dimuat ulang (atau restart terminal Anda) agar perubahan alias aktif.${NC}"
    exit 0
}

manage_saldo() {
    # Inisialisasi koneksi database saat pertama kali masuk
    if ! duid_init_db; then
        print_error "Tidak dapat masuk ke menu saldo karena koneksi database gagal."
        return 1
    fi

    while true; do
        duid_header
        echo -e "  ${BOLD}Menu:${NC}"
        echo ""
        echo -e "  ${WHITE}  1)${NC}  📋  Lihat saldo semua user"
        echo -e "  ${WHITE}  2)${NC}  ✏️   Edit saldo user"
        echo -e "  ${WHITE}  3)${NC}  🔍  Cari user"
        echo -e "  ${WHITE}  4)${NC}  🔄  Reset Data (Riwayat, Keuangan, Pesanan Seller)"
        echo -e "  ${WHITE}  0)${NC}  🔙  Kembali ke Menu Utama"
        echo ""
        echo -ne "  ${YELLOW}Pilihan: ${NC}"
        read -r pilihan_saldo

        case "$pilihan_saldo" in
            1) duid_lihat_saldo
               echo -ne "  ${DIM}Tekan Enter untuk kembali ke menu...${NC}"
               read -r
               ;;
            2) duid_edit_saldo ;;
            3) duid_cari_user ;;
            4) duid_reset_menu ;;
            0)
               break
               ;;
            *)
               echo -e "\n  ${RED}✗ Pilihan tidak valid.${NC}"
               sleep 1
               ;;
        esac
    done
}

change_domain() {
    clear
    print_border
    echo -e "      ${BOLD}${GREEN}🌐   GANTI DOMAIN WEBSITE   🌐${NC}"
    print_border

    # 1. Dapatkan domain saat ini dari .env
    local old_domain=""
    if [ -f ".env" ]; then
        old_domain=$(grep "^APP_URL=" .env | cut -d'=' -f2- | sed -E 's|https?://||' | sed 's|/$||' | tr -d '\r')
    fi

    if [ -n "$old_domain" ]; then
        echo -e "  Domain saat ini: ${CYAN}$old_domain${NC}"
    else
        echo -e "  ${YELLOW}Domain saat ini tidak terdeteksi di .env.${NC}"
    fi

    # 2. Input domain baru
    echo ""
    echo -n "  Masukkan domain baru (contoh: domainbaru.com): "
    read -r new_domain
    new_domain=$(echo "$new_domain" | xargs) # trim spaces

    # Hapus http:// atau https:// jika pengguna menyertakannya
    new_domain=$(echo "$new_domain" | sed -E 's|https?://||' | sed 's|/$||' | tr -d '\r')

    if [ -z "$new_domain" ]; then
        print_error "Domain baru tidak boleh kosong!"
        return 1
    fi

    if [ "$new_domain" = "$old_domain" ]; then
        print_warning "Domain baru sama dengan domain saat ini."
        return 1
    fi

    echo ""
    echo -e "  Website akan dipindahkan ke domain baru:"
    echo -e "  Dari : ${YELLOW}$old_domain${NC}"
    echo -e "  Ke   : ${GREEN}$new_domain${NC}"
    echo ""
    echo -ne "  ${YELLOW}Apakah Anda yakin ingin melanjutkan? (y/n): ${NC}"
    read -r konfirmasi
    if [[ ! "$konfirmasi" =~ ^[Yy]$ ]]; then
        print_info "Proses ganti domain dibatalkan."
        return 1
    fi

    # 3. Perbarui APP_URL di file .env
    print_info "Mengubah APP_URL di .env menjadi https://$new_domain..."
    if grep -q "^APP_URL=" .env; then
        sed -i "s|^APP_URL=.*|APP_URL=https://$new_domain|g" .env
    else
        echo "APP_URL=https://$new_domain" >> .env
    fi

    # 4. Bersihkan cache Laravel agar konfigurasi baru dimuat
    print_info "Membersihkan cache Laravel..."
    php artisan config:clear 2>/dev/null || true
    php artisan route:clear 2>/dev/null || true
    php artisan view:clear 2>/dev/null || true
    php artisan cache:clear 2>/dev/null || true

    # 5. Konfigurasi Nginx & SSL (jika Nginx terpasang di sistem)
    if command -v nginx &>/dev/null; then
        # Hapus Nginx server block lama jika old_domain valid dan bukan localhost
        if [ -n "$old_domain" ] && [ "$old_domain" != "localhost" ] && [ "$old_domain" != "127.0.0.1" ]; then
            print_info "Menghapus konfigurasi Nginx lama untuk domain: $old_domain..."
            rm -f "/etc/nginx/sites-enabled/$old_domain"
            rm -f "/etc/nginx/sites-available/$old_domain"
            
            # Hapus SSL Certbot lama jika terpasang
            if command -v certbot &>/dev/null; then
                print_info "Menghapus sertifikat SSL Certbot lama untuk domain: $old_domain..."
                certbot delete --cert-name "$old_domain" --non-interactive 2>/dev/null || true
                rm -rf "/etc/letsencrypt/live/$old_domain" "/etc/letsencrypt/archive/$old_domain" "/etc/letsencrypt/renewal/$old_domain.conf" 2>/dev/null || true
            fi
        fi

        # Deteksi PHP service & socket path
        detect_php_service
        local php_ver=$(echo "$php_service" | grep -oE '[0-9]+\.[0-9]+')
        local php_socket="/var/run/php/php${php_ver}-fpm.sock"
        if [ ! -S "$php_socket" ]; then
            # Cari file socket php fpm apa saja di /var/run/php/
            local found_socket=$(find /var/run/php/ -name "php*-fpm.sock" 2>/dev/null | head -n 1)
            if [ -n "$found_socket" ]; then
                php_socket="$found_socket"
            else
                php_socket="/var/run/php/php8.3-fpm.sock"
            fi
        fi

        # Buat virtual host Nginx baru (port 80)
        print_info "Membuat konfigurasi Nginx baru di /etc/nginx/sites-available/$new_domain..."
        local NGINX_CONF="/etc/nginx/sites-available/$new_domain"
        cat <<EOT > "$NGINX_CONF"
server {
    listen 80;
    server_name $new_domain;
    root $PROJECT_DIR/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:$php_socket;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOT

        # Aktifkan server block baru
        ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/"

        # Test dan Reload Nginx agar port 80 aktif untuk validasi Certbot
        print_info "Memeriksa dan memuat ulang Nginx..."
        if nginx -t &>/dev/null; then
            systemctl restart nginx 2>/dev/null || service nginx restart || true
        else
            print_error "Konfigurasi Nginx baru tidak valid! Berikut detail kesalahannya:"
            nginx -t
        fi

        # 6. Pembuatan Sertifikat SSL Certbot
        if command -v certbot &>/dev/null; then
            print_info "Memasang sertifikat SSL Certbot untuk domain: $new_domain..."
            if certbot --nginx -d "$new_domain" --non-interactive --agree-tos -m "admin@$new_domain" --redirect; then
                print_success "Sertifikat SSL Certbot berhasil dipasang untuk $new_domain."
            else
                print_warning "Certbot gagal membuat sertifikat SSL otomatis."
                print_info "Pastikan domain '$new_domain' sudah mengarah ke IP VPS ini (A Record)."
                print_info "Anda bisa mencoba membuat SSL secara manual nanti dengan perintah:"
                print_info "  certbot --nginx -d $new_domain"
            fi
        else
            print_warning "Certbot tidak terpasang. Lewati pembuatan SSL otomatis."
        fi

        # Atur perizinan traversal folder
        print_info "Mengatur perizinan folder..."
        local dir_path="$PROJECT_DIR"
        while [ "$dir_path" != "/" ] && [ -n "$dir_path" ]; do
            chmod +x "$dir_path" 2>/dev/null || true
            dir_path=$(dirname "$dir_path")
        done

        # Muat ulang php-fpm & Nginx
        print_info "Memuat ulang layanan PHP ($php_service) & Web Server (Nginx)..."
        systemctl restart "$php_service" 2>/dev/null || service "$php_service" restart || true
        systemctl restart nginx 2>/dev/null || service nginx restart || true
    else
        print_warning "Layanan Nginx tidak ditemukan pada sistem ini. Setup web server dilewati."
    fi

    # 7. Restart PM2 Queue Worker
    if command -v pm2 &>/dev/null; then
        print_info "Merestart PM2 Queue Worker..."
        pm2 restart vpn-queue-worker || pm2 start "php artisan queue:work --tries=3" --name vpn-queue-worker --cwd "$PROJECT_DIR"
        pm2 save
    fi

    print_border
    print_success "Domain berhasil diganti dengan sukses!"
    echo -e "Website sekarang dapat diakses secara langsung di alamat domain baru:"
    echo -e "🔗  ${BOLD}${GREEN}https://$new_domain${NC}"
    print_border
}


# =====================================================================
#  INISIALISASI & LOOP MENU UTAMA
# =====================================================================

# Periksa & tawarkan instalasi alias saat pertama kali script dijalankan
check_and_propose_alias

# Loop Menu Interaktif
while true; do
    echo
    show_dashboard
    read pilihan
    echo
    
    case "$pilihan" in
        1)
            update_website
            ;;
        2)
            manage_accounts
            ;;
        3)
            show_services_status
            ;;
        4)
            view_access_log
            ;;
        5)
            manage_backup_restore
            ;;
        6)
            configure_bot
            ;;
        7)
            view_error_log
            ;;
        8)
            manage_saldo
            ;;
        9)
            manage_transaction_history
            ;;
        10)
            uninstall_website
            ;;
        11)
            change_domain
            ;;
        0)
            print_info "Keluar dari panel pengelola VPS. Sampai jumpa!"
            exit 0
            ;;
        *)
            print_warning "Pilihan tidak valid. Silakan pilih menu [0-11]."
            ;;
    esac
    
    echo
    read -p "Tekan [Enter] untuk kembali ke Dashboard..." temp
done
