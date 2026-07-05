#!/usr/bin/env bash

# =====================================================================
#             🚀 TOKO VPN & PULSA VPS MANAGEMENT CONTROL 🚀
# =====================================================================
# Script pengelola VPS & Website Toko VPN/Pulsa secara instan.
# Dibuat untuk kenyamanan penuh administrator dalam satu perintah "set".
# =====================================================================

# 1. Eskalasi Hak Akses Root Otomatis (Auto-Sudo)
if [ "$EUID" -ne 0 ]; then
    echo -e "\e[33m[INFO] Script ini memerlukan akses root. Mengalihkan menggunakan sudo...\e[0m"
    exec sudo bash "$0" "$@"
fi

# Pindah ke direktori script dijalankan agar path relatif berfungsi dengan aman
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

# 2. Definisi Warna ANSI untuk Tampilan Premium (Aesthetics)
RED='\e[31m'
GREEN='\e[32m'
YELLOW='\e[33m'
BLUE='\e[34m'
CYAN='\e[36m'
WHITE='\e[37m'
BOLD='\e[1m'
NC='\e[0m' # No Color

# Helper output info
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

# 3. Pengumpulan Informasi Statistik Server
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
    echo -e "    • CPU Load : ${CYAN}$cpu_load${NC}"
    echo -e "    • RAM      : ${CYAN}${ram_used}MB${NC} / ${CYAN}${ram_total}MB${NC}"
    echo -e "    • Disk /   : ${CYAN}$disk_used${NC}"
    print_border
    echo -e "  ${BOLD}${WHITE}Pilih Opsi Manajemen:${NC}"
    echo -e "    [1] 🔄  Update Website (Git Pull & Deploy)"
    echo -e "    [2] 👤  Lihat Pengguna Website & Peran"
    echo -e "    [3] ⚡  Cek Status Layanan & Sistem"
    echo -e "    [4] 📊  Pantau Aktivitas Pengunjung (Access Log)"
    echo -e "    [5] 📂  Backup & Restore Website (Files & DB)"
    echo -e "    [6] 🤖  Konfigurasi Bot Telegram & Webhook"
    echo -e "    [7] 🐞  Lihat Log Kesalahan (Error Log)"
    echo -e "    [0] 🚪  Keluar dari Panel"
    print_border
    echo -n "Pilih menu [0-7]: "
}

# 4. Instalasi & Konfigurasi Alias "set" Global
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

# 5. Opsi 1: Update Website (Git Pull & Deploy)
update_website() {
    print_border
    echo -e "      ${BOLD}${BLUE}🔄   UPDATE WEBSITE DARI GITHUB & DEPLOY   🔄${NC}"
    print_border
    print_info "Memulai pembaruan otomatis dari repositori..."

    if [ -f "./update.sh" ]; then
        bash ./update.sh
    else
        # Fallback manual jika update.sh tidak ditemukan
        print_warning "Script update.sh tidak ditemukan. Menjalankan fallback update manual..."
        
        # Deteksi branch saat ini (default ke main)
        local branch=$(git rev-parse --abbrev-ref HEAD 2>/dev/null)
        branch=${branch:-main}
        
        git fetch origin
        git reset --hard "origin/$branch"
        
        composer install --no-dev --optimize-autoloader --ignore-platform-reqs
        php artisan migrate --force
        
        # Build Aset Frontend
        if [ -f "package.json" ]; then
            npm install --no-audit --no-fund || npm install --ignore-scripts --no-audit --no-fund
            chmod +x node_modules/.bin/vite 2>/dev/null || true
            npm run build || npx vite build || true
        fi
        
        # Optimize Cache
        php artisan optimize:clear
        php artisan config:cache
        php artisan route:cache
        php artisan view:cache
        
        # Restart Queue Workers
        php artisan queue:restart
        if command -v pm2 &> /dev/null; then
            pm2 restart vpn-queue-worker || true
        fi
        
        # Safe permissions
        chown -R www-data:www-data . 2>/dev/null || true
        find . -path "./node_modules" -prune -o -path "./vendor" -prune -o -path "./.git" -prune -o -type d -exec chmod 755 {} \; 2>/dev/null || true
        find . -path "./node_modules" -prune -o -path "./vendor" -prune -o -path "./.git" -prune -o -type f -exec chmod 644 {} \; 2>/dev/null || true
        chmod +x artisan *.sh 2>/dev/null || true
    fi

    # Tampilkan commit terbaru secara estetis
    echo
    echo -e "${BOLD}${CYAN}=== INFORMASI COMMIT TERAKHIR (VERSI AKTIF) ===${NC}"
    if command -v git >/dev/null 2>&1; then
        git log -1 --pretty=format:"${GREEN}Hash Commit :${NC} %h%n${GREEN}Author      :${NC} %an (%ae)%n${GREEN}Tanggal     :${NC} %ad%n${GREEN}Pesan       :${NC} %s"
        echo -e "\n"
    else
        print_warning "Aplikasi git tidak tersedia untuk menampilkan log commit."
    fi
    print_success "Deploy selesai! Website Anda sudah berjalan pada versi commit terbaru."
}

# 6. Opsi 2: Lihat Pengguna Website & Peran (View Users)
view_users() {
    print_border
    echo -e "      ${BOLD}${BLUE}👤   DAFTAR PENGGUNA WEBSITE & PERAN   👤${NC}"
    print_border
    print_info "Menghubungi database website..."

    local user_data
    user_data=$(php artisan tinker --execute="
        try {
            foreach(\App\Models\User::all() as \$u) {
                echo \$u->id . '|' . \$u->name . '|' . \$u->email . '|' . (\$u->phone ?? '-') . '|' . \$u->role . '|' . (\$u->is_verified ? 'Aktif' : 'Belum Verifikasi') . PHP_EOL;
            }
        } catch (\Exception \$e) {
            echo 'ERROR: ' . \$e->getMessage();
        }
    " 2>/dev/null)

    if [[ "$user_data" == *"ERROR"* ]] || [ -z "$user_data" ]; then
        print_error "Gagal mengambil data pengguna: $user_data"
        return 1
    fi

    # Print Table
    echo -e "\n${BOLD}${CYAN}ID  | Nama                 | Email                         | Telepon        | Peran    | Status${NC}"
    echo -e "--------------------------------------------------------------------------------------------------"
    
    echo "$user_data" | while IFS='|' read -r id name email phone role status; do
        if [ -n "$id" ]; then
            printf " %-3s | %-20s | %-29s | %-14s | %-8s | %s\n" "$id" "${name:0:20}" "${email:0:29}" "$phone" "$role" "$status"
        fi
    done
    echo
}

# 7. Opsi 3: Cek Status Layanan & Sistem
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
            # Render brief list of worker
            pm2 list | grep -E "online|errored|stopped|name" | grep -v "PM2"
        else
            echo -e "  ${RED}●${NC} PM2 Status: ${BOLD}${RED}Inactive (Stopped)${NC}"
        fi
    else
        echo -e "  ${YELLOW}●${NC} PM2 Status: ${YELLOW}Tidak terpasang di VPS (Queue worker tidak berjalan)${NC}"
    fi
    echo
}

# 8. Opsi 4: Pantau Aktivitas Pengunjung (Access Log)
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

# 9. Opsi 5: Backup & Restore Website (Files & DB)
backup_website() {
    echo -e "${BOLD}${BLUE}=== BUAT CADANGAN (BACKUP) BARU ===${NC}"
    
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
        if [ -n "$db_pass" ]; then
            export MYSQL_PWD="$db_pass"
        fi
        mysqldump -h "${db_host:-127.0.0.1}" -P "${db_port:-3306}" -u "${db_user:-root}" "${db_name:-toko}" > "$db_temp_file" 2>/dev/null
        local dump_status=$?
        unset MYSQL_PWD
        if [ $dump_status -ne 0 ]; then
            print_error "Gagal mengekspor database MySQL! Pastikan kredensial di .env valid."
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
            cp "$sqlite_path" "$db_temp_file"
        else
            print_error "File SQLite tidak ditemukan di: $sqlite_path"
            return 1
        fi
    fi

    print_info "Mengompresi semua berkas website dan database..."
    
    # Tar source code penting, env, DB, dan uploads
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
                        local share_link=$(rclone link "gdrive:toko_backups/${backup_filename}" 2>/dev/null)
                        if [ -n "$share_link" ]; then
                            echo -e "  ${CYAN}Link Sharing:${NC} ${GREEN}${share_link}${NC}"
                        else
                            echo -e "  ${YELLOW}[INFO] Silakan aktifkan link sharing pada file '${backup_filename}' di Google Drive Anda jika ingin menggunakannya untuk pemulihan (restore) di VPS lain.${NC}"
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
                local date_str=$(basename "$file" | sed -E 's/toko_backup_(.*)\.tar\.gz/\1/')
                local formatted_date=$(echo "$date_str" | sed -E 's/([0-9]{4})([0-9]{2})([0-9]{2})_([0-9]{2})([0-9]{2})([0-9]{2})/\1-\2-\3 \4:\5:\6/')
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
        # Hapus file unduhan sementara jika dilingkari
        if [ "$sumber_backup" = "2" ] && [ -f "$selected_backup" ]; then
            rm -f "$selected_backup"
        fi
        return 1
    fi

    print_info "Mengekstrak file backup..."
    if ! tar -xzf "$selected_backup"; then
        print_error "Ekstraksi arsip gagal."
        return 1
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
            if [ -n "$db_pass" ]; then
                export MYSQL_PWD="$db_pass"
            fi
            mysql -h "${db_host:-127.0.0.1}" -P "${db_port:-3306}" -u "${db_user:-root}" -e "CREATE DATABASE IF NOT EXISTS ${db_name:-toko};" 2>/dev/null
            mysql -h "${db_host:-127.0.0.1}" -P "${db_port:-3306}" -u "${db_user:-root}" "${db_name:-toko}" < "$db_temp_file" 2>/dev/null
            local mysql_status=$?
            unset MYSQL_PWD
            
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
    composer install --no-dev --optimize-autoloader --ignore-platform-reqs || true
    npm install --no-audit --no-fund || true
    npm run build || npx vite build || true
    
    php artisan optimize:clear || true
    php artisan optimize || true

    print_info "Mengonfigurasi hak akses folder..."
    chown -R www-data:www-data . 2>/dev/null || true
    chmod -R 775 storage bootstrap/cache 2>/dev/null || true

    # Restart PM2
    if command -v pm2 &>/dev/null; then
        pm2 restart vpn-queue-worker || pm2 start "php artisan queue:work --tries=3" --name vpn-queue-worker
        pm2 save
    fi

    # Hapus file unduhan Google Drive agar tidak memenuhi ruang disk
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

# 10. Opsi 6: Konfigurasi Bot Telegram, Proxy, & Webhook
configure_telegram() {
    print_border
    echo -e "      ${BOLD}${BLUE}🤖   KONFIGURASI INTEGRASI BOT TELEGRAM   🤖${NC}"
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

# 11. Opsi 7: Lihat Log Kesalahan (Error Log)
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
            view_users
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
            configure_telegram
            ;;
        7)
            view_error_log
            ;;
        0)
            print_info "Keluar dari panel pengelola VPS. Sampai jumpa!"
            exit 0
            ;;
        *)
            print_warning "Pilihan tidak valid. Silakan pilih menu [0-7]."
            ;;
    esac
    
    echo
    read -p "Tekan [Enter] untuk kembali ke Dashboard..." temp
done
