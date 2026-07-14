#!/bin/bash

# ==============================================================================
# SCRIPT DEPLOYMENT OTOMATIS LARAVEL VPN STORE DENGAN QRIS DINAMIS
# OS Sasaran: Ubuntu 24.04 LTS (DigitalOcean VPS)
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
echo -e "         Selamat Datang di Script Auto-Deploy Laravel VPN Store       "
echo -e "======================================================================${NC}"

# 1. Validasi Akses Root
if [ "$EUID" -ne 0 ]; then
  echo -e "${MERAH}ERROR: Mohon jalankan script ini sebagai root (Gunakan: sudo ./deploy.sh)${NC}"
  exit 1
fi

# 2. Input Subdomain & Konfigurasi
echo -e "${KUNING}Langkah 1: Konfigurasi Domain & Database${NC}"
read -p "Masukkan Subdomain yang akan digunakan (contoh: vpn.domainanda.com): " SUBDOMAIN
if [ -z "$SUBDOMAIN" ]; then
  echo -e "${MERAH}ERROR: Subdomain tidak boleh kosong!${NC}"
  exit 1
fi

# Cek konflik port (jika port 80/443 sudah digunakan oleh HAProxy/Xray)
echo -e "\n${KUNING}Konfigurasi Port Nginx & SSL:${NC}"
read -p "Apakah port 80/443 digunakan service lain (seperti HAProxy/Xray)? (y/n, default n): " PORT_CONFLICT
NGINX_PORT=80
if [[ "$PORT_CONFLICT" =~ ^[Yy]$ ]]; then
  read -p "Masukkan port kustom untuk Nginx (contoh: 8080 atau 81): " NGINX_PORT
  if [ -z "$NGINX_PORT" ]; then
    NGINX_PORT=8080
  fi
fi

read -p "Apakah Anda ingin memasang SSL otomatis menggunakan Certbot? (y/n, default y): " INSTALL_SSL
INSTALL_SSL=${INSTALL_SSL:-y}

# Generate Password Acak untuk MySQL
DB_PASS=$(openssl rand -base64 12 | tr -d '/+=')
DB_NAME="toko_vpn"
DB_USER="vpn_user"

echo -e "\n${HIJAU}Konfigurasi deploy yang akan digunakan:${NC}"
echo -e "• Subdomain   : ${SUBDOMAIN}"
echo -e "• Port Nginx  : ${NGINX_PORT}"
echo -e "• Pasang SSL  : ${INSTALL_SSL}"
echo -e "• Database    : ${DB_NAME}"
echo -e "• DB User     : ${DB_USER}"
echo -e "• DB Password : ${DB_PASS}"
echo -e "--------------------------------------------------------"
read -p "Apakah data di atas sudah benar? (y/n): " KONFIRMASI
if [ "$KONFIRMASI" != "y" ] && [ "$KONFIRMASI" != "Y" ]; then
  echo -e "${MERAH}Deployment dibatalkan oleh pengguna.${NC}"
  exit 1
fi

# Dapatkan direktori aktif script dijalankan
APP_DIR=$(pwd)
echo -e "\n${BIRU}Direktori aplikasi terdeteksi di: ${APP_DIR}${NC}"

# 3. Update System & Pasang Repository Ondrej PHP
echo -e "\n${KUNING}Langkah 2: Melakukan update system & registrasi PPA PHP...${NC}"

# Optimalisasi koneksi (Paksa IPv4 untuk menghindari timeout IPv6 pada VPS baru)
echo -e "${BIRU}Mengoptimalkan konfigurasi jaringan (Prioritas IPv4)...${NC}"
sysctl -w net.ipv6.conf.all.disable_ipv6=1 &>/dev/null || true
sysctl -w net.ipv6.conf.default.disable_ipv6=1 &>/dev/null || true
sysctl -w net.ipv6.conf.lo.disable_ipv6=1 &>/dev/null || true
echo 'Acquire::ForceIPv4 "true";' > /etc/apt/apt.conf.d/99force-ipv4

# Membuka port yang dibutuhkan (80 & 443) jika belum terbuka
echo -e "${BIRU}Membuka port 80 & 443 di Firewall...${NC}"
if command -v iptables &>/dev/null; then
  iptables -I INPUT 1 -p tcp --dport 80 -j ACCEPT
  iptables -I INPUT 1 -p tcp --dport 443 -j ACCEPT
  if command -v netfilter-persistent &>/dev/null; then
    netfilter-persistent save
  fi
fi

if command -v ufw &>/dev/null; then
  ufw allow 80/tcp
  ufw allow 443/tcp
  ufw reload
fi

apt update --allow-releaseinfo-change && apt upgrade -y
apt install -y software-properties-common curl git unzip nano gnupg2 ca-certificates lsb-release apt-transport-https

# Coba daftarkan PPA Ondrej PHP
if ! add-apt-repository ppa:ondrej/php -y; then
  echo -e "${KUNING}[WARNING] add-apt-repository gagal (koneksi timeout). Mencoba menambahkan PPA secara manual...${NC}"
  
  mkdir -p /etc/apt/keyrings
  
  # Ambil key dari keyserver hkp port 80 (biasanya lolos firewall)
  if ! gpg --no-default-keyring --keyring /etc/apt/keyrings/ondrej-php.gpg --keyserver hkp://keyserver.ubuntu.com:80 --recv-keys 14AA40EC0831756756D7F66C4F4EA0AAE5267A6C; then
    echo -e "${KUNING}[WARNING] Gagal mengambil key dari keyserver. Mengunduh via HTTPS...${NC}"
    curl -fsSL "https://keyserver.ubuntu.com/pks/lookup?op=get&options=mr&search=0x14AA40EC0831756756D7F66C4F4EA0AAE5267A6C" | gpg --dearmor -o /etc/apt/keyrings/ondrej-php.gpg
  fi
  
  # Dapatkan codename OS secara dinamis (misal: noble, jammy, dll.)
  CODENAME=$(lsb_release -sc)
  echo "deb [signed-by=/etc/apt/keyrings/ondrej-php.gpg] https://ppa.launchpadcontent.net/ondrej/php/ubuntu ${CODENAME} main" | tee /etc/apt/sources.list.d/ondrej-php.list
fi

apt update --allow-releaseinfo-change

# 4. Pasang Web Server Nginx & PHP 8.3
echo -e "\n${KUNING}Langkah 3: Memasang Nginx & PHP 8.3 FPM beserta modul...${NC}"
apt install -y nginx php8.3 php8.3-fpm php8.3-cli php8.3-mysql php8.3-sqlite3 php8.3-mbstring php8.3-xml php8.3-curl php8.3-bcmath php8.3-zip

# 5. Pasang & Konfigurasi MySQL Server
echo -e "\n${KUNING}Langkah 4: Memasang & mengamankan MySQL Server...${NC}"
# Cek apakah MySQL atau MariaDB sudah aktif
MYSQL_INSTALLED=false
if systemctl is-active mysql &>/dev/null || systemctl is-active mariadb &>/dev/null; then
  MYSQL_INSTALLED=true
  echo -e "${HIJAU}Database server (MySQL/MariaDB) terdeteksi sudah berjalan. Menggunakan database yang ada.${NC}"
fi

if [ "$MYSQL_INSTALLED" = false ]; then
  apt install -y mysql-server
  systemctl start mysql
  systemctl enable mysql
fi

# Buat Database dan User
echo -e "${BIRU}Membuat database ${DB_NAME} dan user ${DB_USER}...${NC}"
mysql -e "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'localhost';"
mysql -e "CREATE USER IF NOT EXISTS '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
mysql -e "ALTER USER '${DB_USER}'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';"
mysql -e "GRANT ALL PRIVILEGES ON \`${DB_NAME}\`.* TO '${DB_USER}'@'127.0.0.1';"
mysql -e "FLUSH PRIVILEGES;"

# 6. Pasang Composer & Node.js
echo -e "\n${KUNING}Langkah 5: Memasang Composer & Node.js LTS...${NC}"
if ! [ -x "$(command -v composer)" ]; then
  curl -sS https://getcomposer.org/installer | php
  mv composer.phar /usr/local/bin/composer
  chmod +x /usr/local/bin/composer
fi

if ! [ -x "$(command -v node)" ]; then
  curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
  apt install -y nodejs
fi

# 7. Setup Environment Laravel (.env)
echo -e "\n${KUNING}Langkah 6: Menyiapkan file konfigurasi .env Laravel...${NC}"
if [ -f "$APP_DIR/.env" ]; then
  echo -e "${BIRU}Mencadangkan file .env lama menjadi .env.backup...${NC}"
  cp "$APP_DIR/.env" "$APP_DIR/.env.backup"
fi

cp "$APP_DIR/.env.example" "$APP_DIR/.env"

# Inject variabel ke dalam file .env
sed -i "s|APP_URL=http://localhost|APP_URL=https://${SUBDOMAIN}|g" "$APP_DIR/.env"
sed -i "s/APP_ENV=local/APP_ENV=production/g" "$APP_DIR/.env"
sed -i "s/APP_DEBUG=true/APP_DEBUG=false/g" "$APP_DIR/.env"
sed -i "s/DB_CONNECTION=mysql/DB_CONNECTION=mysql/g" "$APP_DIR/.env"
sed -i "s/DB_HOST=127.0.0.1/DB_HOST=127.0.0.1/g" "$APP_DIR/.env"
sed -i "s/DB_PORT=3306/DB_PORT=3306/g" "$APP_DIR/.env"
sed -i "s/DB_DATABASE=toko/DB_DATABASE=${DB_NAME}/g" "$APP_DIR/.env"
sed -i "s/DB_USERNAME=root/DB_USERNAME=${DB_USER}/g" "$APP_DIR/.env"
sed -i "s/DB_PASSWORD=/DB_PASSWORD=${DB_PASS}/g" "$APP_DIR/.env"

# 8. Pasang Dependensi Composer (Vendor)
echo -e "\n${KUNING}Langkah 7: Memasang package PHP via Composer...${NC}"
composer install --no-dev --optimize-autoloader --ignore-platform-reqs --working-dir="$APP_DIR"

# 9. Generate Key & Jalankan Migrasi
echo -e "\n${KUNING}Langkah 8: Generate APP_KEY & Migrasi Database...${NC}"
php artisan key:generate --force
php artisan migrate --force
php artisan db:seed --force

# 10. Konfigurasi Izin File (Permissions)
echo -e "\n${KUNING}Langkah 9: Mengatur hak akses folder storage & cache...${NC}"
chown -R www-data:www-data "$APP_DIR/storage"
chown -R www-data:www-data "$APP_DIR/bootstrap/cache"
chmod -R 775 "$APP_DIR/storage"
chmod -R 775 "$APP_DIR/bootstrap/cache"

# Berikan izin execute (+x) pada seluruh path direktori induk agar Nginx/PHP-FPM bisa menjangkaunya
echo -e "${BIRU}Mengatur hak akses traversal direktori induk...${NC}"
DIR_PATH="$APP_DIR"
while [ "$DIR_PATH" != "/" ] && [ -n "$DIR_PATH" ]; do
  chmod +x "$DIR_PATH"
  DIR_PATH=$(dirname "$DIR_PATH")
done

# 11. Build Aset Frontend (npm & vite dengan fallback tangguh)
echo -e "\n${KUNING}Langkah 10: Memasang paket NPM dan melakukan Build Aset Vite...${NC}"
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
  # Selesaikan masalah perizinan bin
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

  # Build aset
  if npm run build; then
    echo -e "${HIJAU}[SUKSES] npm run build berhasil.${NC}"
  else
    echo -e "${KUNING}[WARNING] npm run build gagal. Mengaktifkan fallback 1 (Node direct)...${NC}"
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

# 12. Konfigurasi Nginx Server Block
echo -e "\n${KUNING}Langkah 11: Membuat konfigurasi Nginx Server Block...${NC}"
NGINX_CONF="/etc/nginx/sites-available/${SUBDOMAIN}"

cat <<EOT > "$NGINX_CONF"
server {
    listen ${NGINX_PORT};
    server_name ${SUBDOMAIN};
    root ${APP_DIR}/public;

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
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME \$realpath_root\$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
EOT

# Buat link aktif di sites-enabled
ln -sf "$NGINX_CONF" "/etc/nginx/sites-enabled/"

# Test Nginx & Restart
nginx -t
systemctl restart nginx

# 13. SSL dengan Certbot (Let's Encrypt)
if [[ "$INSTALL_SSL" =~ ^[Yy]$ ]]; then
  echo -e "\n${KUNING}Langkah 12: Memasang SSL Certbot untuk ${SUBDOMAIN}...${NC}"
  apt install -y certbot python3-certbot-nginx
  certbot --nginx -d "$SUBDOMAIN" --non-interactive --agree-tos -m "admin@${SUBDOMAIN}" --redirect
else
  echo -e "\n${KUNING}Langkah 12: Melewati pemasangan SSL otomatis Certbot.${NC}"
  echo -e "${BIRU}Silakan atur SSL secara manual melalui HAProxy/Xray atau web proxy Anda.${NC}"
fi

# 14. Pasang PM2 untuk Background Queue Worker
echo -e "\n${KUNING}Langkah 13: Memasang PM2 & menjalankan Laravel Queue Worker...${NC}"
npm install -g pm2

# Jalankan queue worker di latar belakang dengan PM2
pm2 delete vpn-queue-worker 2>/dev/null || true
pm2 start "php artisan queue:work --tries=3" --name vpn-queue-worker --cwd "$APP_DIR"
pm2 save

# Mengaktifkan PM2 auto-startup
env PATH=$PATH:/usr/bin pm2 startup systemd -u root --hp /root || true

# Restart Nginx dan PHP-FPM di akhir untuk memastikan izin akses dan konfigurasi baru dimuat sepenuhnya
echo -e "\n${KUNING}Langkah Akhir: Merestart Nginx & PHP-FPM...${NC}"
systemctl restart nginx
systemctl restart php8.3-fpm

# Selesai
echo -e "\n${HIJAU}======================================================================"
echo -e "        DEPLOYMENT SELESAI DENGAN SUKSES!                             "
echo -e "======================================================================${NC}"
echo -e "Website Anda sekarang aktif di: ${HIJAU}https://${SUBDOMAIN}${NC}"
echo -e "Portal Admin: ${HIJAU}https://${SUBDOMAIN}/admin/login${NC}"
echo -e "• Email Admin: ${KUNING}admin@vpn.com${NC}"
echo -e "• Password   : ${KUNING}password${NC}"
echo -e "• Token API  : ${KUNING}rahasiahappy123${NC} (Bisa diubah di menu Pengaturan)"
echo -e "======================================================================"
