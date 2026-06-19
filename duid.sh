#!/bin/bash
# ============================================================
#  duid.sh - Kelola Saldo User
#  Script untuk melihat dan mengedit saldo semua user
#  Penggunaan: bash duid.sh
#  Mendukung: SQLite & MySQL (auto-detect dari .env)
# ============================================================

# Warna
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
MAGENTA='\033[0;35m'
WHITE='\033[1;37m'
BOLD='\033[1m'
DIM='\033[2m'
NC='\033[0m' # No Color

# Path project
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ENV_FILE="$SCRIPT_DIR/.env"

# ============================================================
#  Deteksi Database dari .env
# ============================================================

if [ ! -f "$ENV_FILE" ]; then
    echo -e "${RED}✗ File .env tidak ditemukan di: $ENV_FILE${NC}"
    exit 1
fi

# Baca konfigurasi database dari .env
get_env() {
    local key="$1"
    grep -E "^${key}=" "$ENV_FILE" | head -1 | cut -d'=' -f2- | tr -d '"' | tr -d "'" | tr -d $'\r'
}

DB_CONNECTION=$(get_env "DB_CONNECTION")
DB_HOST=$(get_env "DB_HOST")
DB_PORT=$(get_env "DB_PORT")
DB_DATABASE=$(get_env "DB_DATABASE")
DB_USERNAME=$(get_env "DB_USERNAME")
DB_PASSWORD=$(get_env "DB_PASSWORD")

# Default values
[ -z "$DB_CONNECTION" ] && DB_CONNECTION="sqlite"
[ -z "$DB_HOST" ] && DB_HOST="127.0.0.1"
[ -z "$DB_PORT" ] && DB_PORT="3306"

# ============================================================
#  Fungsi Query Database (abstraksi SQLite / MySQL)
# ============================================================

db_query() {
    local sql="$1"

    if [ "$DB_CONNECTION" = "sqlite" ]; then
        local db_path="$SCRIPT_DIR/database/database.sqlite"
        if [ ! -f "$db_path" ]; then
            echo -e "${RED}✗ Database SQLite tidak ditemukan di: $db_path${NC}" >&2
            return 1
        fi
        sqlite3 -separator '|' "$db_path" "$sql" 2>/dev/null

    elif [ "$DB_CONNECTION" = "mysql" ] || [ "$DB_CONNECTION" = "mariadb" ]; then
        local mysql_cmd="mysql"
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
        echo -e "${RED}✗ DB_CONNECTION '$DB_CONNECTION' tidak didukung. Hanya sqlite dan mysql/mariadb.${NC}" >&2
        return 1
    fi
}

# Tes koneksi
test_connection() {
    local result=$(db_query "SELECT COUNT(*) FROM users;" 2>/dev/null)
    if [ $? -ne 0 ] || [ -z "$result" ]; then
        return 1
    fi
    return 0
}

# ============================================================
#  Fungsi Utilitas
# ============================================================

garis() {
    echo -e "${DIM}─────────────────────────────────────────────────────────────────${NC}"
}

garis_tebal() {
    echo -e "${CYAN}═════════════════════════════════════════════════════════════════${NC}"
}

header() {
    clear
    echo ""
    garis_tebal
    echo -e "${CYAN}  💰  ${BOLD}DUID${NC}${CYAN} - Manajemen Saldo User${NC}"
    echo -e "${DIM}  Database: ${DB_CONNECTION} $([ "$DB_CONNECTION" != "sqlite" ] && echo "@ ${DB_HOST}:${DB_PORT}/${DB_DATABASE}")${NC}"
    garis_tebal
    echo ""
}

format_rupiah() {
    local amount="$1"
    local integer_part=$(echo "$amount" | awk '{printf "%d", $1}')
    echo "Rp $(echo "$integer_part" | sed ':a;s/\B[0-9]\{3\}\>/.&/;ta')"
}

# ============================================================
#  Cek Koneksi Awal
# ============================================================

echo -e "${DIM}Menghubungkan ke database ($DB_CONNECTION)...${NC}"

if ! test_connection; then
    echo -e "${RED}✗ Gagal terhubung ke database.${NC}"
    echo -e "${DIM}  DB_CONNECTION : $DB_CONNECTION${NC}"
    if [ "$DB_CONNECTION" != "sqlite" ]; then
        echo -e "${DIM}  DB_HOST       : $DB_HOST${NC}"
        echo -e "${DIM}  DB_PORT       : $DB_PORT${NC}"
        echo -e "${DIM}  DB_DATABASE   : $DB_DATABASE${NC}"
        echo -e "${DIM}  DB_USERNAME   : $DB_USERNAME${NC}"
    else
        echo -e "${DIM}  DB_PATH       : $SCRIPT_DIR/database/database.sqlite${NC}"
    fi
    exit 1
fi

echo -e "${GREEN}✓ Terhubung!${NC}"
sleep 0.5

# ============================================================
#  Fungsi: Lihat Semua Saldo
# ============================================================

lihat_saldo() {
    header
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

# ============================================================
#  Fungsi: Edit Saldo User
# ============================================================

edit_saldo() {
    header
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
             VALUES ($user_id, 'admin_adjustment', ABS($saldo_baru - $ubalance), $ubalance, $saldo_baru, 'Diubah via duid.sh', $now_func, $now_func);"

        local saldo_terbaru=$(db_query "SELECT balance FROM user_balances WHERE user_id = $user_id;")
        echo -e "  ${WHITE}Saldo terbaru: ${GREEN}$(format_rupiah "$saldo_terbaru")${NC}"
    else
        echo -e "\n  ${RED}✗ Gagal mengubah saldo. Periksa database.${NC}"
    fi

    echo ""
    echo -ne "  ${DIM}Tekan Enter untuk kembali...${NC}"
    read -r
}

# ============================================================
#  Fungsi: Cari User
# ============================================================

cari_user() {
    header
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

# ============================================================
#  Fungsi Pendukung Reset Data
# ============================================================

lihat_daftar_user() {
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

reset_riwayat_user() {
    header
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
        lihat_daftar_user
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

reset_keuangan_seller() {
    header
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
        lihat_daftar_user
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

reset_pesanan_seller() {
    header
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
        lihat_daftar_user
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

reset_menu() {
    while true; do
        header
        echo -e "  ${BOLD}🔄 MENU RESET DATA:${NC}"
        echo ""
        echo -e "    ${WHITE}1)${NC}  🗑️  Reset Riwayat User (Pesanan & Transaksi Saldo)"
        echo -e "    ${WHITE}2)${NC}  💰  Reset Keuangan Dashboard Seller"
        echo -e "    ${WHITE}3)${NC}  📦  Reset Riwayat Pesanan Seller Portal"
        echo -e "    ${WHITE}0)${NC}  🔙  Kembali ke Menu Utama"
        echo ""
        echo -ne "  ${YELLOW}Pilihan: ${NC}"
        read -r reset_pilihan

        case "$reset_pilihan" in
            1) reset_riwayat_user ;;
            2) reset_keuangan_seller ;;
            3) reset_pesanan_seller ;;
            0|"") break ;;
            *) echo -e "\n  ${RED}✗ Pilihan tidak valid.${NC}"; sleep 1 ;;
        esac
    done
}

# ============================================================
#  Menu Utama
# ============================================================

while true; do
    header
    echo -e "  ${BOLD}Menu:${NC}"
    echo ""
    echo -e "  ${WHITE}  1)${NC}  📋  Lihat saldo semua user"
    echo -e "  ${WHITE}  2)${NC}  ✏️   Edit saldo user"
    echo -e "  ${WHITE}  3)${NC}  🔍  Cari user"
    echo -e "  ${WHITE}  4)${NC}  🔄  Reset Data (Riwayat, Keuangan, Pesanan Seller)"
    echo -e "  ${WHITE}  0)${NC}  🚪  Keluar"
    echo ""
    echo -ne "  ${YELLOW}Pilihan: ${NC}"
    read -r pilihan

    case "$pilihan" in
        1) lihat_saldo
           echo -ne "  ${DIM}Tekan Enter untuk kembali ke menu...${NC}"
           read -r
           ;;
        2) edit_saldo ;;
        3) cari_user ;;
        4) reset_menu ;;
        0)
           echo ""
           echo -e "  ${GREEN}👋 Sampai jumpa!${NC}"
           echo ""
           exit 0
           ;;
        *)
           echo -e "\n  ${RED}✗ Pilihan tidak valid.${NC}"
           sleep 1
           ;;
    esac
done
