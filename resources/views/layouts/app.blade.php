<!DOCTYPE html>
<html lang="id" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'RZK - Toko VPN' }}</title>
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Google Fonts / Tailwind CSS -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Font definition if not bundled by bunny -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: 'Plus Jakarta Sans', 'Instrument Sans', sans-serif;
        }
        .dark body {
            background-color: #0b0f19;
            color: #f3f4f6;
        }
        body {
            background-color: #f4f6fc;
            color: #1f2937;
        }
    </style>
    <script>
        // Inline script to prevent theme flash
        if (localStorage.getItem('color-theme') === 'dark' || (!('color-theme' in localStorage) && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    </script>
</head>
<body class="min-h-screen transition-colors duration-200">
    <div class="flex flex-col md:flex-row min-h-screen">
        
        <!-- Backdrop Overlay for Mobile Sidebar -->
        <div id="sidebarBackdrop" class="fixed inset-0 bg-slate-900/50 dark:bg-slate-950/60 z-40 transition-opacity duration-300 opacity-0 pointer-events-none md:hidden"></div>

        <!-- SIDEBAR (Left) -->
        <aside id="sidebar" class="fixed inset-y-0 left-0 w-80 max-w-[calc(100vw-3rem)] md:w-80 bg-white dark:bg-slate-900 border-r border-slate-200 dark:border-slate-800 flex flex-col transform -translate-x-full md:translate-x-0 transition-transform duration-300 ease-in-out md:fixed md:top-0 md:bottom-0 z-50 md:z-30">
            <!-- Sidebar Header & Logo -->
            <div class="p-6 flex items-center justify-between border-b border-slate-100 dark:border-slate-800">
                <a href="{{ url('/') }}" class="flex items-center space-x-3">
                    <div class="bg-blue-600 p-2 rounded-xl text-white">
                        <!-- Briefcase/Bag Icon -->
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"></path>
                        </svg>
                    </div>
                    <span class="text-xl font-bold bg-linear-to-r from-blue-600 to-indigo-500 bg-clip-text text-transparent">RZK</span>
                    <span class="text-sm font-medium text-slate-400 dark:text-slate-500">| {{ Auth::check() ? 'Store' : 'Katalog' }}</span>
                </a>
                
                <!-- Mobile Menu Button -->
                <button id="mobileMenuBtn" class="md:hidden p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg" aria-label="Tutup Menu">
                    <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path></svg>
                </button>
            </div>

            <!-- Profile Widget -->
            @if(Auth::check())
                <a href="{{ route('profile.edit') }}" class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center space-x-4 hover:bg-slate-50 dark:hover:bg-slate-800/40 transition duration-205">
                    <div class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center text-white font-bold text-lg shadow-md shadow-blue-500/20 flex-shrink-0">
                        {{ strtoupper(substr(Auth::user()->name, 0, 1)) }}
                    </div>
                    <div class="overflow-hidden">
                        <h4 class="font-bold text-slate-800 dark:text-slate-100 leading-tight truncate">
                            {{ Auth::user()->name }}
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Role: {{ ucfirst(Auth::user()->role) }}
                        </p>
                    </div>
                </a>
            @else
                <div class="p-6 border-b border-slate-100 dark:border-slate-800 flex items-center space-x-4">
                    <div class="w-12 h-12 rounded-full bg-slate-200 dark:bg-slate-800 flex items-center justify-center text-slate-500 font-bold text-lg flex-shrink-0">
                        G
                    </div>
                    <div>
                        <h4 class="font-bold text-slate-800 dark:text-slate-100 leading-tight">
                            Tamu Pengunjung
                        </h4>
                        <p class="text-xs text-slate-500 dark:text-slate-400">
                            Role: Guest
                        </p>
                    </div>
                </div>
            @endif

            <!-- Sidebar Nav Items -->
            <div id="sidebarMenu" class="flex-1 overflow-y-auto px-4 py-6 space-y-6">
                <!-- BELANJA -->
                <div>
                    <span class="px-3 text-xs font-semibold text-slate-400 dark:text-slate-600 uppercase tracking-wider">Belanja</span>
                    <div class="mt-2 space-y-1">
                        <a href="{{ url('/catalog') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->is('catalog') || request()->is('/') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path></svg>
                            <span>Katalog Produk</span>
                        </a>
                        <a href="{{ Auth::check() ? route('orders.history') : route('login') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('orders.history') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01"></path></svg>
                            <span>Riwayat Pesanan</span>
                        </a>
                    </div>
                </div>

                <!-- AKUN SECTION REMOVED -->

                <!-- SELLER PORTAL & PORTAL ADMIN -->
                @if(Auth::check())
                    @if(Auth::user()->role === 'admin' || Auth::user()->role === 'seller')
                        <div>
                            <span class="px-3 text-xs font-semibold text-slate-400 dark:text-slate-600 uppercase tracking-wider">Seller Portal</span>
                            <div class="mt-2 space-y-1">
                                <a href="{{ route('admin.dashboard') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2H6a2 2 0 01-2-2v-4zM14 16a2 2 0 012-2h2a2 2 0 012 2v4a2 2 0 01-2 2h-2a2 2 0 01-2-2v-4z"></path></svg>
                                    <span>Dashboard Seller</span>
                                </a>
                                <a href="{{ route('admin.products') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.products') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 7l-8-4-8 4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"></path></svg>
                                    <span>Produk Saya</span>
                                </a>
                                <a href="{{ route('admin.account_stocks') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.account_stocks') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7v10c0 2.21 3.582 4 8 4s8-1.79 8-4V7M4 7c0 2.21 3.582 4 8 4s8-1.79 8-4M4 7c0-2.21 3.582-4 8-4s8 1.79 8 4m0 5c0 2.21-3.582 4-8 4s-8-1.79-8-4"></path></svg>
                                    <span>Stok Akun</span>
                                </a>
                                <a href="{{ route('admin.transactions') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.transactions') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                    <span>Kelola Pesanan</span>
                                </a>
                                <a href="{{ route('admin.complaints') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium {{ request()->routeIs('admin.complaints') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                                    <span>Kelola Komplain</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    @if(Auth::user()->role === 'admin')
                        <div class="mt-6">
                            <span class="px-3 text-xs font-semibold text-slate-400 dark:text-slate-600 uppercase tracking-wider">Portal Admin</span>
                            <div class="mt-2 space-y-1">
                                <a href="{{ route('admin.users') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.users') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    <span>Manajemen Pengguna</span>
                                </a>
                                <a href="{{ route('admin.settings') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.settings') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path></svg>
                                    <span>Konfigurasi QRIS & API</span>
                                </a>
                                <a href="{{ route('admin.supplier_settings') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.supplier_settings') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 3v1.5M4.5 8.25H3m18 0h-1.5M8.25 19.5V21M12 3v1.5m0 15V21m3.75-18v1.5m0 15V21m-9-1.5h10.5a2.25 2.25 0 002.25-2.25V6.75a2.25 2.25 0 00-2.25-2.25H6.75A2.25 2.25 0 004.5 6.75v10.5a2.25 2.25 0 002.25 2.25zm.75-12h9v9h-9v-9z"></path></svg>
                                    <span>Pengaturan API Supplier</span>
                                </a>
                                <a href="{{ route('admin.vpn_panel') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.vpn_panel') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 01-3-3m3 3a3 3 0 100 6h13.5a3 3 0 100-6m-13.5-3a3 3 0 013-3h13.5a3 3 0 013 3m-16.5 0a3 3 0 013-3h13.5a3 3 0 013 3m-19.5 0a3 3 0 013-3h13.5a3 3 0 013 3M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75"></path></svg>
                                    <span>VPN Panel</span>
                                </a>
                                 <a href="{{ route('admin.commissions') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.commissions') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z"></path></svg>
                                    <span>Komisi Seller</span>
                                </a>
                            </div>
                        </div>

                        <div class="mt-6">
                            <span class="px-3 text-xs font-semibold text-slate-400 dark:text-slate-600 uppercase tracking-wider font-bold">Tools</span>
                            <div class="mt-2 space-y-1">
                                <a href="{{ route('admin.tools.xl.index') }}" class="flex items-center space-x-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-all duration-200 {{ request()->routeIs('admin.tools.xl.*') ? 'bg-blue-50 dark:bg-blue-950/30 text-blue-600 dark:text-blue-400 border-l-4 border-blue-600' : 'text-slate-600 dark:text-slate-400 hover:bg-slate-50 dark:hover:bg-slate-800' }}">
                                    <!-- Tool/Wrench/Hammer Icon -->
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 006 3.75v16.5a2.25 2.25 0 002.25 2.25h7.5A2.25 2.25 0 0018 20.25V3.75a2.25 2.25 0 00-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3"></path></svg>
                                    <span>Cek & Reset Kuota XL</span>
                                </a>
                            </div>
                        </div>
                    @endif

                    <!-- UPGRADE AKUN SECTION REMOVED -->
                @endif
            </div>
        </aside>

        <!-- MAIN CONTENT CONTAINER -->
        <div class="flex-1 md:ml-80 flex flex-col min-h-screen">
            
            <!-- TOP NAVBAR -->
            <header class="bg-white dark:bg-slate-900 border-b border-slate-200 dark:border-slate-800 h-20 px-6 md:px-10 flex items-center justify-between sticky top-0 z-20 transition-all duration-200">
                <div class="flex items-center space-x-4">
                    <!-- Sidebar Toggle for Mobile -->
                    <button id="mobileMenuOpen" class="md:hidden p-2 text-slate-500 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-lg">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path></svg>
                    </button>
                    <h1 class="text-xl font-bold text-slate-800 dark:text-slate-100">{{ $title ?? 'Dashboard' }}</h1>
                </div>

                <div class="flex items-center space-x-4">
                    <!-- Light / Dark Mode Toggle -->
                    <button id="themeToggle" class="p-2.5 text-slate-500 hover:text-blue-600 hover:bg-slate-100 dark:hover:bg-slate-800 rounded-xl transition-all duration-200">
                        <!-- Dark Mode Icon (Moon) -->
                        <svg id="themeToggleDarkIcon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z"></path></svg>
                        <!-- Light Mode Icon (Sun) -->
                        <svg id="themeToggleLightIcon" class="hidden w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.46 5.05l-.707-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" fill-rule="evenodd" clip-rule="evenodd"></path></svg>
                    </button>

                    <!-- User Actions -->
                    @if(Auth::check())
                        <form action="{{ route('logout') }}" method="POST" class="inline">
                            @csrf
                            <button type="submit" class="flex items-center space-x-2 px-4 py-2 border border-red-200 dark:border-red-900/50 hover:bg-red-50 dark:hover:bg-red-950/20 text-red-600 dark:text-red-400 rounded-xl text-sm font-semibold transition-all duration-200">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                                <span>Logout</span>
                            </button>
                        </form>
                    @else
                        <a href="{{ route('login') }}" class="flex items-center space-x-2 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold transition-all duration-200 shadow-md shadow-emerald-500/10">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M11 16l-4-4m0 0l4-4m-4 4h14m-5 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h7a3 3 0 013 3v1"></path></svg>
                            <span>Login</span>
                        </a>
                    @endif
                </div>
            </header>

            <!-- PAGE CONTENT -->
            <main class="flex-1 p-4 sm:p-6 md:p-10">
                @if(session('success'))
                    <div class="mb-6 p-4 bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/50 text-emerald-800 dark:text-emerald-400 rounded-2xl flex items-center space-x-3">
                        <svg class="w-5 h-5 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <span class="text-sm font-medium">{{ session('success') }}</span>
                    </div>
                @endif

                @yield('content')
            </main>

            <!-- FOOTER -->
            <footer class="bg-white dark:bg-slate-900 border-t border-slate-200 dark:border-slate-800 py-6 px-10 text-center text-sm text-slate-400 dark:text-slate-500 transition-all duration-200">
                &copy; {{ date('Y') }} RZK Platform. Built for premium performance & security.
            </footer>
        </div>
    </div>

    <!-- Floating Telegram Support Button -->
    <a href="https://t.me/yourusername" target="_blank" class="fixed bottom-6 right-6 z-40 bg-blue-600 hover:bg-blue-700 text-white px-5 py-3.5 rounded-full flex items-center space-x-2.5 shadow-xl shadow-blue-500/30 hover:scale-105 active:scale-95 transition-all duration-200 text-sm font-bold">
        <!-- Telegram SVG -->
        <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm4.64 6.8c-.15 1.58-.8 5.42-1.13 7.19-.14.75-.42 1-.68 1.03-.58.05-1.02-.38-1.58-.75-.88-.58-1.38-.94-2.23-1.5-.99-.65-.35-1.01.22-1.59.15-.15 2.71-2.48 2.76-2.69.01-.03.01-.14-.07-.2-.08-.06-.19-.04-.27-.02-.12.02-2.03 1.28-5.74 3.79-.54.37-1.03.55-1.47.54-.48-.01-1.4-.27-2.08-.5-.83-.27-1.49-.42-1.43-.88.03-.24.37-.49 1.02-.75 3.99-1.74 6.66-2.88 7.99-3.43 3.8-1.57.79-.17 1.77.16.03.22.04.45.03.68z"/>
        </svg>
        <span>Bantuan</span>
    </a>

    <!-- Global Theme Switcher & Mobile Menu JS -->
    <script>
        // Theme Toggle Logic
        const themeToggleBtn = document.getElementById('themeToggle');
        const themeToggleDarkIcon = document.getElementById('themeToggleDarkIcon');
        const themeToggleLightIcon = document.getElementById('themeToggleLightIcon');

        // Set initial icon visibility
        if (document.documentElement.classList.contains('dark')) {
            themeToggleLightIcon.classList.remove('hidden');
        } else {
            themeToggleDarkIcon.classList.remove('hidden');
        }

        themeToggleBtn.addEventListener('click', function() {
            // toggle icons
            themeToggleDarkIcon.classList.toggle('hidden');
            themeToggleLightIcon.classList.toggle('hidden');

            // if set via local storage previously
            if (localStorage.getItem('color-theme')) {
                if (localStorage.getItem('color-theme') === 'light') {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('color-theme', 'dark');
                } else {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('color-theme', 'light');
                }
            } else {
                if (document.documentElement.classList.contains('dark')) {
                    document.documentElement.classList.remove('dark');
                    localStorage.setItem('color-theme', 'light');
                } else {
                    document.documentElement.classList.add('dark');
                    localStorage.setItem('color-theme', 'dark');
                }
            }
        });

        // Mobile Menu Toggles
        const mobileMenuOpen = document.getElementById('mobileMenuOpen');
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.getElementById('sidebar');
        const sidebarBackdrop = document.getElementById('sidebarBackdrop');

        function openSidebar() {
            sidebar.classList.remove('-translate-x-full');
            sidebarBackdrop.classList.remove('opacity-0', 'pointer-events-none');
            sidebarBackdrop.classList.add('opacity-100', 'pointer-events-auto');
        }

        function closeSidebar() {
            sidebar.classList.add('-translate-x-full');
            sidebarBackdrop.classList.remove('opacity-100', 'pointer-events-auto');
            sidebarBackdrop.classList.add('opacity-0', 'pointer-events-none');
        }

        if (mobileMenuOpen) mobileMenuOpen.addEventListener('click', openSidebar);
        if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', closeSidebar);
        if (sidebarBackdrop) sidebarBackdrop.addEventListener('click', closeSidebar);

        // Handle window resize to clean up state
        window.addEventListener('resize', function() {
            if (window.innerWidth >= 768) {
                sidebar.classList.remove('-translate-x-full');
                sidebarBackdrop.classList.remove('opacity-100', 'pointer-events-auto');
                sidebarBackdrop.classList.add('opacity-0', 'pointer-events-none');
            } else {
                sidebar.classList.add('-translate-x-full');
            }
        });
    </script>
</body>
</html>
