#!/usr/bin/env bash
# ============================================================
#  akun.sh - Kelola Akun & Kredensial User
#  Script untuk melihat, menambah, mengedit, dan menghapus user/admin.
#  Penggunaan: bash akun.sh
#  Mendukung: SQLite & MySQL (melalui artisan tinker)
# ============================================================

# 1. Eskalasi Hak Akses Root Otomatis (Auto-Sudo)
if [ "$EUID" -ne 0 ]; then
    echo -e "\e[33m[INFO] Script ini memerlukan akses root. Mengalihkan menggunakan sudo...\e[0m"
    exec sudo bash "$0" "$@"
fi

# Pindah ke direktori script dijalankan
SCRIPT_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" && pwd )"
cd "$SCRIPT_DIR" || exit 1

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

# Validasi folder project Laravel
if [ ! -f "artisan" ] || [ ! -d "vendor" ]; then
    echo -e "${RED}✗ Error: Script ini harus diletakkan dan dijalankan dari folder root project Laravel Anda.${NC}"
    exit 1
fi

garis() {
    echo -e "${DIM}──────────────────────────────────────────────────────────────────────────────────────────${NC}"
}

garis_tebal() {
    echo -e "${CYAN}══════════════════════════════════════════════════════════════════════════════════════════${NC}"
}

header() {
    clear
    echo ""
    garis_tebal
    echo -e "${CYAN}  👤  ${BOLD}AKUN.SH${NC}${CYAN} - Manajemen Kredensial & Pengguna Website${NC}"
    echo -e "${DIM}  Lokasi Project: $SCRIPT_DIR${NC}"
    garis_tebal
    echo ""
}

# Fungsi Helper: Menjalankan PHP Tinker
execute_tinker() {
    local php_code="$1"
    php artisan tinker --execute="$php_code" 2>/dev/null
}

# Menu 1: Tampilkan Semua User
list_users() {
    header
    echo -e "${BOLD}${WHITE}Daftar Akun Pengguna:${NC}"
    garis

    # Ambil data dari database melalui tinker
    local user_data
    user_data=$(execute_tinker "
        try {
            foreach(\App\Models\User::all() as \$u) {
                echo \$u->id . '|' . \$u->name . '|' . \$u->email . '|' . (\$u->phone ?? '-') . '|' . \$u->role . '|' . (\$u->is_verified ? 'Verified' : 'Unverified') . PHP_EOL;
            }
        } catch (\Exception \$e) {
            echo 'ERROR: ' . \$e->getMessage();
        }
    ")

    if [[ "$user_data" == *"ERROR"* ]] || [ -z "$user_data" ]; then
        echo -e "${RED}✗ Gagal mengambil data pengguna dari database.${NC}"
        echo -e "${RED}Detail: $user_data${NC}"
        return 1
    fi

    # Render Table Header
    printf "${BOLD}${CYAN} %-3s | %-18s | %-28s | %-12s | %-8s | %-10s${NC}\n" "ID" "Nama" "Email" "Telepon" "Role" "Status"
    garis

    echo "$user_data" | while IFS='|' read -r id name email phone role status; do
        if [ -n "$id" ]; then
            # Format warna per role
            local role_color="$WHITE"
            if [ "$role" = "admin" ]; then
                role_color="$RED"
            elif [ "$role" = "seller" ]; then
                role_color="$GREEN"
            fi

            # Format warna status
            local status_color="$YELLOW"
            if [ "$status" = "Verified" ]; then
                status_color="$GREEN"
            fi

            printf " %-3s | %-18s | %-28s | %-12s | ${role_color}%-8s${NC} | ${status_color}%-10s${NC}\n" \
                "$id" "${name:0:18}" "${email:0:28}" "${phone:0:12}" "$role" "$status"
        fi
    done
    garis
    echo ""
}

# Menu 2: Tambah Akun Baru
tambah_user() {
    header
    echo -e "${BOLD}${GREEN}➕ Tambah Pengguna Baru${NC}"
    garis
    
    read -p "Masukkan Nama: " nama
    if [ -z "$nama" ]; then
        echo -e "${RED}✗ Nama tidak boleh kosong! Batal.${NC}"
        return 1
    fi

    read -p "Masukkan Email: " email
    if [ -z "$email" ]; then
        echo -e "${RED}✗ Email tidak boleh kosong! Batal.${NC}"
        return 1
    fi

    # Validasi email duplikat
    local email_exists
    email_exists=$(execute_tinker "echo \App\Models\User::where('email', '$email')->exists() ? 'true' : 'false';")
    if [ "$email_exists" = "true" ]; then
        echo -e "${RED}✗ Error: Email '$email' sudah terdaftar di database!${NC}"
        return 1
    fi

    read -p "Masukkan Password Baru: " password
    if [ -z "$password" ]; then
        echo -e "${RED}✗ Password tidak boleh kosong! Batal.${NC}"
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
        echo -e "${GREEN}✓ Pengguna baru '$nama' ($role) berhasil ditambahkan!${NC}"
    else
        echo -e "${RED}✗ Gagal membuat pengguna baru: $result${NC}"
    fi
}

# Menu 3: Edit Akun
edit_user() {
    list_users
    read -p "Masukkan ID User yang ingin diedit (Enter untuk batal): " user_id
    if [ -z "$user_id" ]; then
        echo -e "${YELLOW}Dibatalkan.${NC}"
        return 0
    fi

    # Cek apakah user ada
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
        echo -e "${RED}✗ User dengan ID $user_id tidak ditemukan!${NC}"
        return 1
    fi

    # Parsing data user saat ini
    IFS='|' read -r curr_name curr_email curr_phone curr_role curr_verified <<< "$current_user"

    header
    echo -e "${BOLD}${YELLOW}✏️ Edit Pengguna (ID: $user_id)${NC}"
    garis
    echo -e "Tekan [Enter] langsung untuk mempertahankan nilai lama."
    garis

    # 1. Nama
    read -p "Nama baru [$curr_name]: " new_name
    new_name=${new_name:-$curr_name}

    # 2. Email
    read -p "Email baru [$curr_email]: " new_email
    new_email=${new_email:-$curr_email}

    # Validasi email unik jika email diubah
    if [ "$new_email" != "$curr_email" ]; then
        local email_exists
        email_exists=$(execute_tinker "echo \App\Models\User::where('email', '$new_email')->exists() ? 'true' : 'false';")
        if [ "$email_exists" = "true" ]; then
            echo -e "${RED}✗ Error: Email '$new_email' sudah terdaftar untuk user lain! Batal.${NC}"
            return 1
        fi
    fi

    # 3. Password
    read -p "Password baru (biarkan kosong jika tidak ingin diubah): " new_password

    # 4. Role
    local role_name="buyer"
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

    # 5. Telepon
    read -p "No Telepon [$curr_phone]: " new_phone
    new_phone=${new_phone:-$curr_phone}

    # 6. Verifikasi
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
        echo -e "${GREEN}✓ Akun user ID $user_id ($new_name) berhasil diperbarui!${NC}"
    else
        echo -e "${RED}✗ Gagal memperbarui akun: $result${NC}"
    fi
}

# Menu 4: Hapus Akun
hapus_user() {
    list_users
    read -p "Masukkan ID User yang akan DIHAPUS (Enter untuk batal): " user_id
    if [ -z "$user_id" ]; then
        echo -e "${YELLOW}Dibatalkan.${NC}"
        return 0
    fi

    # Cek apakah user ada
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
        echo -e "${RED}✗ User dengan ID $user_id tidak ditemukan!${NC}"
        return 1
    fi

    IFS='|' read -r name email role <<< "$current_user"

    # Cegah penghapusan admin satu-satunya demi keselamatan
    if [ "$role" = "admin" ]; then
        local admin_count
        admin_count=$(execute_tinker "echo \App\Models\User::where('role', 'admin')->count();")
        if [ "$admin_count" -le 1 ]; then
            echo -e "${RED}✗ Gagal: User ini adalah satu-satunya administrator di website. Anda tidak boleh menghapusnya!${NC}"
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
        echo -e "${GREEN}✓ Akun user ID $user_id ($name) berhasil dihapus secara permanen!${NC}"
    else
        echo -e "${RED}✗ Gagal menghapus akun: $result${NC}"
    fi
}

# Loop Utama Interactive Dashboard
while true; do
    header
    echo -e "  ${BOLD}${WHITE}Pilih Opsi Manajemen Akun:${NC}"
    echo -e "    [1] 📋 Lihat Semua Akun Pengguna"
    echo -e "    [2] ➕ Tambah Akun Pengguna Baru"
    echo -e "    [3] ✏️  Edit Kredensial & Detail Akun"
    echo -e "    [4] ❌ Hapus Akun Pengguna"
    echo -e "    [0] 🚪 Keluar dari Script"
    garis_tebal
    read -p "Pilih menu [0-4]: " pilihan
    echo ""

    case "$pilihan" in
        1)
            list_users
            read -p "Tekan [Enter] untuk kembali..." temp
            ;;
        2)
            tambah_user
            read -p "Tekan [Enter] untuk kembali..." temp
            ;;
        3)
            edit_user
            read -p "Tekan [Enter] untuk kembali..." temp
            ;;
        4)
            hapus_user
            read -p "Tekan [Enter] untuk kembali..." temp
            ;;
        0)
            echo -e "${BLUE}[INFO] Keluar dari Script. Sampai jumpa!${NC}"
            exit 0
            ;;
        *)
            echo -e "${YELLOW}Pilihan tidak valid. Silakan pilih menu [0-4].${NC}"
            sleep 1
            ;;
    esac
done
