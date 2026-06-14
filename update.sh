#!/bin/bash

# ==============================================================================
# SCRIPT UPDATE OTOMATIS LARAVEL VPN STORE (VPS) - TANGGUH & SELF-HEALING
# Mengambil kode terbaru dari GitHub, mereset cache, mem-build aset & memvalidasi DB
# ==============================================================================

# Hentikan eksekusi jika ada perintah kritis yang gagal di luar block penanganan
set -e

# Warna untuk output terminal (ANSI)
HIJAU='\033[0;32m'
BIRU='\033[0;34m'
KUNING='\033[1;33m'
MERAH='\033[0;31m'
NC='\033[0m' # No Color

echo -e "${BIRU}======================================================================"
echo -e "       Memulai Update Aplikasi Laravel VPN Store dari GitHub          "
echo -e "======================================================================${NC}"

# Dapatkan direktori aktif script dijalankan
APP_DIR=$(pwd)

# 1. Pull Kode & Tangani Konflik Perubahan Lokal
echo -e "\n${KUNING}Langkah 1: Mengambil kode terbaru dari repositori Git...${NC}"
HAS_CHANGES=false
if [ -n "$(git status --porcelain 2>/dev/null)" ]; then
    HAS_CHANGES=true
    echo -e "${KUNING}[PERINGATAN] Terdeteksi perubahan lokal di VPS yang belum di-commit!${NC}"
    echo -e "Mencoba mengamankan sementara dengan 'git stash'..."
    if git stash; then
        echo -e "${HIJAU}[SUKSES] Perubahan lokal disimpan sementara.${NC}"
    else
        echo -e "${MERAH}[ERROR] Gagal melakukan git stash!${NC}"
        read -p "Apakah Anda ingin memaksa reset repositori (git reset --hard)? Perubahan lokal akan hilang! (y/n): " JAWAB_RESET
        if [[ "$JAWAB_RESET" =~ ^[Yy]$ ]]; then
            git reset --hard HEAD
            git clean -fd
        else
            echo -e "${MERAH}[ABORT] Update dibatalkan.${NC}"
            exit 1
        fi
    fi
fi

# Pastikan di branch main
git fetch origin
git checkout main 2>/dev/null || git checkout -b main origin/main

if git pull origin main; then
    echo -e "${HIJAU}[SUKSES] Git pull berhasil.${NC}"
else
    echo -e "${MERAH}[ERROR] Gagal melakukan git pull.${NC}"
    read -p "Apakah Anda ingin memaksa reset repositori ke origin/main? (y/n): " JAWAB_RESET
    if [[ "$JAWAB_RESET" =~ ^[Yy]$ ]]; then
        git reset --hard origin/main
        HAS_CHANGES=false
    else
        echo -e "${MERAH}[ABORT] Update dibatalkan.${NC}"
        exit 1
    fi
fi

# Kembalikan stash jika ada
if [ "$HAS_CHANGES" = true ]; then
    echo -e "${KUNING}Mengembalikan perubahan lokal (git stash pop)...${NC}"
    if git stash pop; then
        echo -e "${HIJAU}[SUKSES] Perubahan lokal berhasil dikembalikan.${NC}"
    else
        echo -e "${KUNING}[PERINGATAN] Terjadi konflik saat mengembalikan perubahan. Silakan periksa secara manual.${NC}"
    fi
fi

# 2. Pasang Dependensi Composer Baru
echo -e "\n${KUNING}Langkah 2: Memasang package PHP baru via Composer...${NC}"
COMPOSER_CMD="composer"
if ! command -v composer &> /dev/null; then
    if [ -f "composer.phar" ]; then
        COMPOSER_CMD="php composer.phar"
    else
        echo -e "${KUNING}Composer tidak terpasang secara global. Mengunduh secara otomatis...${NC}"
        curl -sS https://getcomposer.org/installer | php
        COMPOSER_CMD="php composer.phar"
    fi
fi

if php -d memory_limit=-1 $COMPOSER_CMD install --no-dev --optimize-autoloader --ignore-platform-reqs; then
    echo -e "${HIJAU}[SUKSES] Composer install berhasil.${NC}"
else
    echo -e "${KUNING}[WARNING] Composer install gagal. Mencoba dengan --no-scripts...${NC}"
    php -d memory_limit=-1 $COMPOSER_CMD install --no-dev --optimize-autoloader --ignore-platform-reqs --no-scripts
fi

# 3. Jalankan Migrasi Database Baru & Auto-Repair Admin
echo -e "\n${KUNING}Langkah 3: Mendiagnosis database & menjalankan migrasi...${NC}"
# Jalankan migrasi
if php artisan migrate --force; then
    echo -e "${HIJAU}[SUKSES] Migrasi database berhasil.${NC}"
else
    echo -e "${MERAH}[WARNING] Migrasi gagal. Mencoba ulang setelah membersihkan cache...${NC}"
    php artisan cache:clear
    php artisan config:clear
    php artisan migrate --force
fi

# Jalankan seeder
if php artisan db:seed --force; then
    echo -e "${HIJAU}[SUKSES] Seeding database selesai.${NC}"
else
    echo -e "${KUNING}[WARNING] Seeding database gagal atau dilewati.${NC}"
fi

# Auto-repair admin utama
echo -e "${KUNING}Memverifikasi akun Admin Utama di database...${NC}"
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
    echo -e "${HIJAU}[SUKSES] Akun Admin Utama 'admin@vpn.com' dijamin aktif & valid (Role: admin, Status: Aktif, Pass: password).${NC}"
else
    echo -e "${MERAH}[WARNING] Gagal memvalidasi akun Admin: $ADMIN_STATUS${NC}"
fi

# 4. Bersihkan & Optimalkan Cache Laravel
echo -e "\n${KUNING}Langkah 4: Melakukan caching konfigurasi dan rute Laravel...${NC}"
php artisan optimize:clear
php artisan optimize || true

# 5. Build Aset Frontend Baru dengan Fallback
echo -e "\n${KUNING}Langkah 5: Memasang paket npm & melakukan build aset Vite...${NC}"
if [ -f "package.json" ]; then
    if [ -d "node_modules" ]; then
        chmod -R 777 node_modules &>/dev/null || true
    fi

    NPM_OK=false
    if npm install --no-audit --no-fund; then
        NPM_OK=true
    else
        echo -e "${KUNING}[WARNING] npm install standar gagal. Mengulang dengan --ignore-scripts...${NC}"
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
                    target=$(readlink -f "$file" 2>/dev/null)
                    if [ -f "$target" ]; then
                        chmod +x "$target" 2>/dev/null || true
                    fi
                fi
            done
        fi

        # Eksekusi build
        if npm run build; then
            echo -e "${HIJAU}[SUKSES] npm run build berhasil.${NC}"
        else
            echo -e "${KUNING}[WARNING] npm run build gagal (Permission Denied/noexec). Mengaktifkan fallback 1 (Node direct)...${NC}"
            if [ -f "node_modules/vite/bin/vite.js" ]; then
                if node node_modules/vite/bin/vite.js build; then
                    echo -e "${HIJAU}[SUKSES] Aset frontend berhasil di-build via Node fallback.${NC}"
                else
                    echo -e "${KUNING}[WARNING] Node fallback gagal. Mencoba fallback 2 (npx)...${NC}"
                    if npx vite build; then
                        echo -e "${HIJAU}[SUKSES] Aset frontend berhasil di-build via npx.${NC}"
                    else
                        echo -e "${KUNING}[WARNING] npx gagal. Mencoba fallback 3 (RAM-disk isolation)...${NC}"
                        TMP_DIR="/dev/shm/vite-build-$(date +%s)"
                        mkdir -p "$TMP_DIR"
                        tar --exclude='./node_modules' --exclude='./.git' -cf - . | (cd "$TMP_DIR" && tar -xf -)
                        ln -s "$APP_DIR/node_modules" "$TMP_DIR/node_modules"
                        cd "$TMP_DIR"
                        if node node_modules/vite/bin/vite.js build; then
                            cp -r public/build "$APP_DIR/public/"
                            echo -e "${HIJAU}[SUKSES] Aset frontend berhasil di-build via RAM-disk isolation.${NC}"
                        else
                            echo -e "${MERAH}[ERROR] Semua metode build frontend gagal!${NC}"
                            exit 1
                        fi
                        cd "$APP_DIR"
                        rm -rf "$TMP_DIR"
                    fi
                fi
            else
                echo -e "${MERAH}[ERROR] Berkas vite.js tidak ditemukan!${NC}"
                exit 1
            fi
        fi
    else
        echo -e "${MERAH}[ERROR] Gagal memasang dependensi NPM!${NC}"
        exit 1
    fi
fi

# 6. Atur Ulang Permissions Folder
echo -e "\n${KUNING}Langkah 6: Mengatur hak akses folder storage & cache...${NC}"
chown -R www-data:www-data "$APP_DIR" 2>/dev/null || true
find "$APP_DIR" -type d -exec chmod 755 {} \; 2>/dev/null || true
find "$APP_DIR" -type f -exec chmod 644 {} \; 2>/dev/null || true
if [ -d "$APP_DIR/storage" ] && [ -d "$APP_DIR/bootstrap/cache" ]; then
    chmod -R 775 "$APP_DIR/storage"
    chmod -R 775 "$APP_DIR/bootstrap/cache"
fi

# 7. Restart PM2 Worker agar menggunakan kode terbaru
echo -e "\n${KUNING}Langkah 7: Merestart queue worker di PM2...${NC}"
if command -v pm2 &> /dev/null; then
    pm2 restart vpn-queue-worker || pm2 start "php artisan queue:work --tries=3" --name vpn-queue-worker --cwd "$APP_DIR"
    pm2 save
fi

echo -e "\n${HIJAU}======================================================================"
echo -e "          UPDATE APLIKASI SELESAI DENGAN SUKSES!                      "
echo -e "======================================================================${NC}"
echo -e "Aplikasi Anda di VPS telah diperbarui ke versi terbaru dari GitHub."
echo -e "======================================================================"
