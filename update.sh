#!/bin/bash

# ==============================================================================
# SCRIPT UPDATE OTOMATIS LARAVEL VPN STORE (VPS)
# Mengambil kode terbaru dari GitHub dan mereset cache aplikasi
# ==============================================================================

# Hentikan eksekusi jika ada perintah yang gagal
set -e

# Warna untuk output terminal
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

# 1. Pull Kode Terbaru dari GitHub
echo -e "\n${KUNING}Langkah 1: Mengambil kode terbaru dari repositori Git...${NC}"
# Jalankan git pull. Jika Anda menggunakan branch selain 'main', sesuaikan di bawah (misal 'master')
git pull

# 2. Pasang Dependensi Composer Baru (jika ada)
echo -e "\n${KUNING}Langkah 2: Memasang package PHP baru...${NC}"
composer install --no-dev --optimize-autoloader --ignore-platform-reqs --working-dir="$APP_DIR"

# 3. Jalankan Migrasi Database Baru (jika ada perubahan skema)
echo -e "\n${KUNING}Langkah 3: Menjalankan migrasi database...${NC}"
php artisan migrate --force

# 4. Bersihkan & Optimalkan Cache Laravel
echo -e "\n${KUNING}Langkah 4: Melakukan caching konfigurasi dan rute Laravel...${NC}"
php artisan optimize

# 5. Build Aset Frontend Baru (jika ada perubahan css/js/blade)
echo -e "\n${KUNING}Langkah 5: Memasang paket npm & melakukan build aset Vite...${NC}"
if [ -f "package.json" ]; then
  npm install
  npm run build
fi

# 6. Atur Ulang Permissions Folder
echo -e "\n${KUNING}Langkah 6: Mengatur hak akses folder storage & cache...${NC}"
chown -R www-data:www-data "$APP_DIR/storage"
chown -R www-data:www-data "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

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
