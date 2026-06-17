#!/bin/bash
# ============================================================
#  duid.sh - Kelola Saldo User
#  Script untuk melihat dan mengedit saldo semua user
#  Penggunaan: bash duid.sh
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

# Path database SQLite (sesuaikan jika perlu)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
DB_PATH="$SCRIPT_DIR/database/database.sqlite"

# Cek apakah database ada
if [ ! -f "$DB_PATH" ]; then
    echo -e "${RED}✗ Database tidak ditemukan di: $DB_PATH${NC}"
    echo -e "${DIM}  Pastikan script ini berada di root folder project Laravel.${NC}"
    exit 1
fi

# Cek apakah sqlite3 terinstall
if ! command -v sqlite3 &> /dev/null; then
    echo -e "${RED}✗ sqlite3 tidak ditemukan. Install dengan: sudo apt install sqlite3${NC}"
    exit 1
fi

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
    echo -e "${DIM}  Kelola saldo semua user dengan mudah${NC}"
    garis_tebal
    echo ""
}

format_rupiah() {
    local amount="$1"
    # Bulatkan ke integer, lalu format dengan pemisah ribuan
    local integer_part=$(echo "$amount" | awk '{printf "%d", $1}')
    echo "Rp $(echo "$integer_part" | sed ':a;s/\B[0-9]\{3\}\>/.&/;ta')"
}

# ============================================================
#  Fungsi: Lihat Semua Saldo
# ============================================================

lihat_saldo() {
    header
    echo -e "${BOLD}${WHITE}  📋 DAFTAR SALDO SEMUA USER${NC}"
    echo ""
    garis

    # Query untuk mendapatkan semua user beserta saldonya
    local data=$(sqlite3 "$DB_PATH" -separator '|' \
        "SELECT u.id, u.name, u.email, u.role, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         ORDER BY u.id ASC;" 2>/dev/null)

    if [ -z "$data" ]; then
        echo -e "  ${YELLOW}⚠ Tidak ada data user ditemukan.${NC}"
        garis
        return
    fi

    # Header tabel
    printf "  ${BOLD}${WHITE}%-4s │ %-20s │ %-28s │ %-8s │ %18s${NC}\n" "ID" "Nama" "Email" "Role" "Saldo"
    garis

    local total_saldo=0
    local total_users=0

    while IFS='|' read -r id name email role balance; do
        total_users=$((total_users + 1))
        total_saldo=$(echo "$total_saldo + $balance" | bc 2>/dev/null || echo "$total_saldo")

        # Warna berdasarkan role
        local role_color="${WHITE}"
        if [ "$role" = "admin" ]; then
            role_color="${RED}"
        elif [ "$role" = "seller" ]; then
            role_color="${MAGENTA}"
        else
            role_color="${GREEN}"
        fi

        # Warna saldo
        local saldo_color="${GREEN}"
        local saldo_int=$(echo "$balance" | awk '{printf "%d", $1}')
        if [ "$saldo_int" -eq 0 ]; then
            saldo_color="${DIM}"
        elif [ "$saldo_int" -lt 0 ]; then
            saldo_color="${RED}"
        fi

        local saldo_formatted=$(format_rupiah "$balance")

        # Potong nama dan email jika terlalu panjang
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

    # Tampilkan daftar singkat
    local data=$(sqlite3 "$DB_PATH" -separator '|' \
        "SELECT u.id, u.name, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         ORDER BY u.id ASC;" 2>/dev/null)

    garis
    printf "  ${BOLD}%-4s │ %-25s │ %18s${NC}\n" "ID" "Nama" "Saldo"
    garis

    while IFS='|' read -r id name balance; do
        local saldo_formatted=$(format_rupiah "$balance")
        printf "  %-4s │ %-25s │ %18s\n" "$id" "$(echo "$name" | cut -c1-25)" "$saldo_formatted"
    done <<< "$data"

    garis
    echo ""

    # Input ID user
    echo -ne "  ${YELLOW}Masukkan ID user yang ingin diedit (0 = kembali): ${NC}"
    read -r user_id

    if [ "$user_id" = "0" ] || [ -z "$user_id" ]; then
        return
    fi

    # Validasi ID user
    local user_info=$(sqlite3 "$DB_PATH" -separator '|' \
        "SELECT u.id, u.name, u.email, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         WHERE u.id = $user_id;" 2>/dev/null)

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

    # Pilih aksi
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
    local existing=$(sqlite3 "$DB_PATH" \
        "SELECT COUNT(*) FROM user_balances WHERE user_id = $user_id;" 2>/dev/null)

    if [ "$existing" -gt 0 ]; then
        # Update existing record
        sqlite3 "$DB_PATH" \
            "UPDATE user_balances SET balance = $saldo_baru, updated_at = datetime('now') WHERE user_id = $user_id;" 2>/dev/null
    else
        # Insert new record
        sqlite3 "$DB_PATH" \
            "INSERT INTO user_balances (user_id, balance, created_at, updated_at) VALUES ($user_id, $saldo_baru, datetime('now'), datetime('now'));" 2>/dev/null
    fi

    if [ $? -eq 0 ]; then
        echo -e "\n  ${GREEN}✓ Saldo berhasil diubah!${NC}"

        # Catat ke balance_transactions
        sqlite3 "$DB_PATH" \
            "INSERT INTO balance_transactions (user_id, type, amount, balance_before, balance_after, description, created_at, updated_at)
             VALUES ($user_id, 'admin_adjustment', ABS($saldo_baru - $ubalance), $ubalance, $saldo_baru, 'Diubah via duid.sh', datetime('now'), datetime('now'));" 2>/dev/null

        # Tampilkan saldo terbaru
        local saldo_terbaru=$(sqlite3 "$DB_PATH" \
            "SELECT balance FROM user_balances WHERE user_id = $user_id;" 2>/dev/null)
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

    local data=$(sqlite3 "$DB_PATH" -separator '|' \
        "SELECT u.id, u.name, u.email, u.role, COALESCE(ub.balance, 0)
         FROM users u
         LEFT JOIN user_balances ub ON u.id = ub.user_id
         WHERE u.name LIKE '%$keyword%' OR u.email LIKE '%$keyword%'
         ORDER BY u.id ASC;" 2>/dev/null)

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
#  Menu Utama
# ============================================================

while true; do
    header
    echo -e "  ${BOLD}Menu:${NC}"
    echo ""
    echo -e "  ${WHITE}  1)${NC}  📋  Lihat saldo semua user"
    echo -e "  ${WHITE}  2)${NC}  ✏️   Edit saldo user"
    echo -e "  ${WHITE}  3)${NC}  🔍  Cari user"
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
