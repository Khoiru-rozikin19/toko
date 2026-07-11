@extends('layouts.app', ['title' => 'Komisi Seller'])

@section('content')
<div class="space-y-6 sm:space-y-8">
    
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between border-b border-slate-200 dark:border-slate-800 pb-5">
        <div>
            <p class="text-sm text-slate-500 dark:text-slate-400">Atur komisi/cashback per-produk untuk seller tertentu. Komisi otomatis masuk ke saldo dompet seller saat transaksi berhasil.</p>
        </div>
        <button onclick="toggleCreateModal(true)" class="mt-4 sm:mt-0 flex items-center space-x-2 px-5 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 active:scale-95 transition-all duration-200">
            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"></path></svg>
            <span>Tambah Komisi</span>
        </button>
    </div>

    <!-- Stats -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <span class="text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Total Aturan</span>
            <span class="text-2xl font-black text-slate-800 dark:text-slate-100 mt-1 block">{{ $commissions->count() }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <span class="text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Aturan Aktif</span>
            <span class="text-2xl font-black text-emerald-600 dark:text-emerald-400 mt-1 block">{{ $commissions->where('is_active', true)->count() }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <span class="text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Seller Terlibat</span>
            <span class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1 block">{{ $commissions->pluck('seller_id')->unique()->count() }}</span>
        </div>
        <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 p-5 rounded-2xl shadow-sm">
            <span class="text-xs text-slate-400 dark:text-slate-500 font-bold uppercase block">Produk Berkomisi</span>
            <span class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1 block">{{ $commissions->pluck('product_id')->unique()->count() }}</span>
        </div>
    </div>

    <!-- Commissions Table -->
    <div class="bg-white dark:bg-slate-900 border border-slate-100 dark:border-slate-800 rounded-3xl shadow-sm overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 dark:bg-slate-900/60 border-b border-slate-100 dark:border-slate-800 text-xs font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">
                        <th class="py-4.5 px-6">Seller</th>
                        <th class="py-4.5 px-6">Produk</th>
                        <th class="py-4.5 px-6">Harga Produk</th>
                        <th class="py-4.5 px-6">Komisi</th>
                        <th class="py-4.5 px-6">Status</th>
                        <th class="py-4.5 px-6 text-center">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800 text-sm font-medium text-slate-700 dark:text-slate-350">
                    @forelse($commissions as $commission)
                        <tr class="hover:bg-slate-50/50 dark:hover:bg-slate-800/20 transition-all duration-150 {{ !$commission->is_active ? 'opacity-50' : '' }}">
                            <td class="py-4.5 px-6">
                                <div class="font-bold text-slate-800 dark:text-slate-200">{{ $commission->seller->name ?? 'Deleted User' }}</div>
                                <div class="text-xs text-slate-400 dark:text-slate-500">{{ $commission->seller->email ?? '-' }}</div>
                            </td>
                            <td class="py-4.5 px-6">
                                <span class="font-bold text-slate-800 dark:text-slate-200">{{ $commission->product->name ?? 'Deleted Product' }}</span>
                            </td>
                            <td class="py-4.5 px-6 text-slate-600 dark:text-slate-400">
                                Rp {{ number_format($commission->product->price ?? 0, 0, ',', '.') }}
                            </td>
                            <td class="py-4.5 px-6">
                                <span class="text-emerald-600 dark:text-emerald-400 font-bold">
                                    Rp {{ number_format($commission->commission_amount, 0, ',', '.') }}
                                </span>
                            </td>
                            <td class="py-4.5 px-6">
                                @if($commission->is_active)
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 dark:bg-emerald-950/30 text-emerald-700 dark:text-emerald-400">
                                        Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-100 dark:bg-slate-800 text-slate-500 dark:text-slate-400">
                                        Nonaktif
                                    </span>
                                @endif
                            </td>
                            <td class="py-4.5 px-6 text-center">
                                <div class="flex items-center justify-center space-x-2">
                                    <!-- Edit Button -->
                                    <button onclick="openEditModal({{ json_encode($commission) }})" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-blue-50 dark:hover:bg-blue-950/20 text-slate-600 hover:text-blue-600 dark:text-slate-400 dark:hover:text-blue-400 rounded-xl transition-all duration-200" title="Edit Komisi">
                                        <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path></svg>
                                    </button>

                                    <!-- Toggle Form -->
                                    <form action="{{ route('admin.commissions.toggle', $commission->id) }}" method="POST" class="inline">
                                        @csrf
                                        @if($commission->is_active)
                                            <button type="submit" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-amber-50 dark:hover:bg-amber-950/20 text-slate-600 hover:text-amber-600 dark:text-slate-400 dark:hover:text-amber-400 rounded-xl transition-all duration-200" title="Nonaktifkan">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"></path></svg>
                                            </button>
                                        @else
                                            <button type="submit" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-emerald-50 dark:hover:bg-emerald-950/20 text-slate-600 hover:text-emerald-600 dark:text-slate-400 dark:hover:text-emerald-400 rounded-xl transition-all duration-200" title="Aktifkan">
                                                <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                            </button>
                                        @endif
                                    </form>

                                    <!-- Delete Form -->
                                    <form action="{{ route('admin.commissions.delete', $commission->id) }}" method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus aturan komisi ini?')" class="inline">
                                        @csrf
                                        <button type="submit" class="p-2 bg-slate-100 dark:bg-slate-800 hover:bg-rose-50 dark:hover:bg-rose-950/20 text-slate-600 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 rounded-xl transition-all duration-200" title="Hapus">
                                            <svg class="w-4.5 h-4.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="py-10 text-center text-slate-400">
                                Belum ada aturan komisi. Tekan tombol "Tambah Komisi" untuk memulai.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- CREATE COMMISSION MODAL -->
<div id="createCommissionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative">
        <button onclick="toggleCreateModal(false)" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight mb-6">Tambah Aturan Komisi</h3>

        <form action="{{ route('admin.commissions.store') }}" method="POST" class="space-y-4">
            @csrf
            <div>
                <label for="create_seller_id" class="block text-xs font-bold text-slate-500 uppercase mb-2">Pilih Seller</label>
                <select id="create_seller_id" name="seller_id" required class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <option value="">-- Pilih Seller --</option>
                    @foreach($sellers as $seller)
                        <option value="{{ $seller->id }}">{{ $seller->name }} ({{ ucfirst($seller->role) }}) — {{ $seller->email }}</option>
                    @endforeach
                </select>
            </div>

            <div>
                <label for="create_product_id" class="block text-xs font-bold text-slate-500 uppercase mb-2">Pilih Produk</label>
                <select id="create_product_id" name="product_id" required onchange="updateProductPrice('create')" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                    <option value="" data-price="0">-- Pilih Produk --</option>
                    @foreach($products as $product)
                        <option value="{{ $product->id }}" data-price="{{ $product->price }}">{{ $product->name }} — Rp {{ number_format($product->price, 0, ',', '.') }}</option>
                    @endforeach
                </select>
                <p id="create_product_price_hint" class="text-xs text-slate-400 dark:text-slate-500 mt-1.5 hidden">
                    Harga produk: <span class="font-bold text-blue-600 dark:text-blue-400" id="create_product_price_display"></span>
                </p>
            </div>

            <div>
                <label for="create_commission_amount" class="block text-xs font-bold text-slate-500 uppercase mb-2">Jumlah Komisi (Rp)</label>
                <input type="number" id="create_commission_amount" name="commission_amount" required min="100" placeholder="Contoh: 3000" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
                <p class="text-xs text-slate-400 dark:text-slate-500 mt-1.5">Komisi ini akan masuk ke saldo dompet seller setiap kali produk terjual.</p>
            </div>

            <div class="flex justify-end space-x-2 pt-4">
                <button type="button" onclick="toggleCreateModal(false)" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-2xl text-sm font-semibold transition-all duration-200">
                    Batal
                </button>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all duration-200">
                    Simpan Komisi
                </button>
            </div>
        </form>
    </div>
</div>

<!-- EDIT COMMISSION MODAL -->
<div id="editCommissionModal" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm hidden transition-all duration-300">
    <div class="bg-white dark:bg-slate-950 rounded-3xl w-full max-w-lg border border-slate-100 dark:border-slate-800 p-5 sm:p-8 shadow-2xl relative">
        <button onclick="toggleEditModal(false)" class="absolute top-5 right-5 p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-xl hover:bg-slate-100 dark:hover:bg-slate-900 transition-all duration-200">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
        </button>

        <h3 class="text-2xl font-extrabold text-slate-800 dark:text-slate-100 tracking-tight mb-6">Ubah Jumlah Komisi</h3>

        <form id="editCommissionForm" action="" method="POST" class="space-y-4">
            @csrf

            <div class="bg-slate-50 dark:bg-slate-900/60 p-4 border border-slate-200 dark:border-slate-800 rounded-2xl space-y-2">
                <div class="flex justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase">Seller</span>
                    <span id="edit_seller_name" class="text-sm font-bold text-slate-800 dark:text-slate-200"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase">Produk</span>
                    <span id="edit_product_name" class="text-sm font-bold text-slate-800 dark:text-slate-200"></span>
                </div>
                <div class="flex justify-between">
                    <span class="text-xs font-bold text-slate-500 uppercase">Harga Produk</span>
                    <span id="edit_product_price" class="text-sm font-bold text-blue-600 dark:text-blue-400"></span>
                </div>
            </div>

            <div>
                <label for="edit_commission_amount" class="block text-xs font-bold text-slate-500 uppercase mb-2">Jumlah Komisi Baru (Rp)</label>
                <input type="number" id="edit_commission_amount" name="commission_amount" required min="100" class="w-full px-4 py-3 bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 focus:border-blue-500 focus:bg-white focus:outline-none rounded-2xl text-sm font-medium text-slate-800 dark:text-slate-100 transition-all duration-200">
            </div>

            <div class="flex justify-end space-x-2 pt-4">
                <button type="button" onclick="toggleEditModal(false)" class="px-5 py-3 bg-slate-100 hover:bg-slate-200 dark:bg-slate-850 dark:hover:bg-slate-800 text-slate-700 dark:text-slate-300 rounded-2xl text-sm font-semibold transition-all duration-200">
                    Batal
                </button>
                <button type="submit" class="px-6 py-3 bg-blue-600 hover:bg-blue-700 text-white rounded-2xl text-sm font-bold shadow-lg shadow-blue-500/20 transition-all duration-200">
                    Perbarui Komisi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleCreateModal(show) {
        const modal = document.getElementById('createCommissionModal');
        if (show) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }

    function toggleEditModal(show) {
        const modal = document.getElementById('editCommissionModal');
        if (show) {
            modal.classList.remove('hidden');
        } else {
            modal.classList.add('hidden');
        }
    }

    function openEditModal(commission) {
        document.getElementById('edit_seller_name').innerText = commission.seller ? commission.seller.name : 'N/A';
        document.getElementById('edit_product_name').innerText = commission.product ? commission.product.name : 'N/A';
        document.getElementById('edit_product_price').innerText = commission.product ? 'Rp ' + Number(commission.product.price).toLocaleString('id-ID') : '-';
        document.getElementById('edit_commission_amount').value = Math.round(commission.commission_amount);
        document.getElementById('editCommissionForm').action = `/admin/commissions/${commission.id}/update`;
        toggleEditModal(true);
    }

    function updateProductPrice(prefix) {
        const select = document.getElementById(`${prefix}_product_id`);
        const hint = document.getElementById(`${prefix}_product_price_hint`);
        const display = document.getElementById(`${prefix}_product_price_display`);

        if (select && select.value) {
            const option = select.options[select.selectedIndex];
            const price = option.dataset.price;
            if (price && Number(price) > 0) {
                display.innerText = 'Rp ' + Number(price).toLocaleString('id-ID');
                hint.classList.remove('hidden');
            } else {
                hint.classList.add('hidden');
            }
        } else {
            hint.classList.add('hidden');
        }
    }
</script>
@endsection
