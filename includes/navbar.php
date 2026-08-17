<?php
if (!defined('SITE_NAME')) {
    include_once(__DIR__ . "/../config.php");
}

// Compute relative path to root based on script location
$current_dir = dirname($_SERVER['SCRIPT_NAME']);
$is_in_subfolder = (strpos($_SERVER['SCRIPT_NAME'], '/user/') !== false || strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false);
$root = $is_in_subfolder ? "../" : "./";

$user = is_logged_in() ? get_current_user_data() : null;
$admin = is_admin_logged_in() ? get_admin($_SESSION['admin_id']) : null;
$current_page = basename($_SERVER['SCRIPT_NAME']);

// Fetch unread notifications count for logged in user
$unread_notifs = 0;
if (is_logged_in() && $user) {
    require_once(__DIR__ . "/NotificationService.php");
    $unread_notifs = NotificationService::get_unread_count((int)$user['id']);
}
?>

<nav class="sticky top-0 z-50 backdrop-blur-xl bg-white/90 border-b border-slate-200/80 transition-all">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            
            <!-- Brand Logo -->
            <a href="<?php echo $root; ?>index.php" class="flex items-center gap-3 group">
                <div class="w-10 h-10 rounded-xl bg-zinc-900 flex items-center justify-center text-white text-lg shadow-sm group-hover:bg-zinc-800 transition-all">
                    <i class="fa-solid fa-bolt-lightning"></i>
                </div>
                <div class="font-heading font-extrabold text-xl tracking-tight text-zinc-900">
                    MT<span class="text-zinc-500">Data</span>
                </div>
            </a>

            <!-- Desktop Navigation Links -->
            <div class="hidden md:flex items-center gap-1">
                <?php if (is_admin_logged_in()): ?>
                    <a href="<?php echo $root; ?>admin/dashboard.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition-all <?php echo $current_page == 'dashboard.php' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'; ?>">
                        <i class="fa-solid fa-chart-pie mr-1.5 text-brand-500"></i> Admin Hub
                    </a>
                    <a href="<?php echo $root; ?>admin/plans.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition-all <?php echo $current_page == 'plans.php' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'; ?>">
                        <i class="fa-solid fa-database mr-1.5 text-brand-500"></i> Data Plans
                    </a>
                    <a href="<?php echo $root; ?>admin/cable_plans.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition-all <?php echo $current_page == 'cable_plans.php' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'; ?>">
                        <i class="fa-solid fa-tv mr-1.5 text-brand-500"></i> Cable Plans
                    </a>
                    <a href="<?php echo $root; ?>admin/users.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition-all <?php echo $current_page == 'users.php' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'; ?>">
                        <i class="fa-solid fa-users mr-1.5 text-brand-500"></i> Users & Wallets
                    </a>
                    <a href="<?php echo $root; ?>admin/transactions.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition-all <?php echo $current_page == 'transactions.php' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'; ?>">
                        <i class="fa-solid fa-receipt mr-1.5 text-brand-500"></i> Transactions
                    </a>
                <?php elseif (is_logged_in()): ?>
             
                   
                <?php else: ?>
                    <a href="<?php echo $root; ?>index.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold transition-all <?php echo $current_page == 'index.php' ? 'bg-brand-50 text-brand-700 font-bold' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-100/80'; ?>">
                        <i class="fa-solid fa-house mr-1.5 text-brand-500"></i> Home
                    </a>
                    <a href="<?php echo $root; ?>index.php#features" class="px-3.5 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all">
                        <i class="fa-solid fa-layer-group mr-1.5 text-brand-500"></i> Features
                    </a>
                    <a href="<?php echo $root; ?>user/login.php" class="px-3.5 py-2 rounded-lg text-sm font-semibold text-slate-600 hover:text-slate-900 hover:bg-slate-100/80 transition-all">
                        <i class="fa-solid fa-face-viewfinder mr-1.5 text-accent-500"></i> Face ID Login
                    </a>
                <?php endif; ?>
            </div>

            <!-- Right-Side Auth & User Profile -->
            <div class="flex items-center gap-1.5 sm:gap-3">
                <?php if (is_logged_in() && $user): ?>

                    <!-- Automated Acknowledgements Notification Widget -->
                    <div class="relative" id="notificationWidget">
                        <button type="button" onclick="toggleNotificationDropdown(event)" class="relative w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-zinc-100 hover:bg-zinc-200 text-zinc-700 hover:text-zinc-900 flex items-center justify-center transition-all focus:outline-none" title="Notifications & Receipts" aria-label="Notifications">
                            <i class="fa-solid fa-bell text-xs sm:text-sm"></i>
                            <span id="navNotifBadge" class="<?php echo $unread_notifs > 0 ? '' : 'hidden'; ?> absolute -top-1 -right-1 min-w-[17px] h-[17px] px-1 rounded-full bg-red-600 text-white text-[9px] font-extrabold flex items-center justify-center shadow-sm">
                                <?php echo $unread_notifs; ?>
                            </span>
                        </button>

                        <!-- Notification Dropdown / Drawer (Mobile Responsive Position) -->
                        <div id="notificationDropdown" class="hidden fixed inset-x-3 top-16 sm:absolute sm:inset-auto sm:right-0 sm:top-full sm:mt-2 w-auto sm:w-96 rounded-2xl bg-white shadow-xl ring-1 ring-black/10 border border-zinc-200 z-50 overflow-hidden max-h-[85vh] flex flex-col">
                            <!-- Header -->
                            <div class="p-3.5 border-b border-zinc-100 bg-zinc-50 flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <i class="fa-solid fa-bell text-zinc-900 text-sm"></i>
                                    <span class="font-bold text-sm text-zinc-900">Notifications</span>
                                    <span id="drawerUnreadBadge" class="<?php echo $unread_notifs > 0 ? '' : 'hidden'; ?> px-2 py-0.5 rounded-full bg-zinc-200 text-zinc-800 text-[10px] font-extrabold">
                                        <?php echo $unread_notifs; ?> new
                                    </span>
                                </div>
                                <button type="button" onclick="markAllNotificationsRead(event)" class="text-xs font-semibold text-zinc-600 hover:text-zinc-900 transition-colors">
                                    Mark all read
                                </button>
                            </div>

                            <!-- Notifications Content List -->
                            <div id="notifListContainer" class="max-h-80 sm:max-h-96 overflow-y-auto divide-y divide-zinc-100 text-xs">
                                <div class="p-6 text-center text-zinc-400">
                                    <i class="fa-solid fa-spinner fa-spin text-lg mb-2"></i>
                                    <p>Loading notifications...</p>
                                </div>
                            </div>

                            <!-- Footer -->
                            <div class="p-2.5 bg-zinc-50 border-t border-zinc-100 text-center">
                                <a href="<?php echo $root; ?>user/dashboard.php#history" class="text-xs font-bold text-zinc-600 hover:text-zinc-900 transition-colors flex items-center justify-center gap-1.5">
                                    View Transactions <i class="fa-solid fa-arrow-right text-[10px]"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Profile Dropdown Component -->
                    <div class="relative" id="userMenuDropdown">
                        <button type="button" onclick="toggleNavDropdown(event)" class="flex items-center gap-1.5 sm:gap-2.5 p-1 sm:p-1.5 sm:pr-3 rounded-full hover:bg-zinc-100 transition-all border border-zinc-200 bg-white shadow-xs focus:outline-none" aria-label="User Profile">
                            <div class="relative w-7 h-7 sm:w-8 sm:h-8 rounded-full overflow-hidden bg-zinc-800 flex items-center justify-center text-white text-xs font-bold">
                                <?php if (!empty($user['face_photo'])): ?>
                                    <img src="<?php echo htmlspecialchars($user['face_photo']); ?>" alt="Profile" class="w-full h-full object-cover">
                                <?php else: ?>
                                    <span><?php echo strtoupper(substr($user['fullname'] ?? 'U', 0, 1)); ?></span>
                                <?php endif; ?>
                                <span class="absolute bottom-0 right-0 w-2 h-2 rounded-full bg-emerald-500 ring-1 ring-white"></span>
                            </div>
                            <span class="text-xs sm:text-sm font-semibold text-zinc-800 hidden md:inline-block max-w-[120px] truncate">
                                <?php echo htmlspecialchars(explode(' ', $user['fullname'])[0]); ?>
                            </span>
                            <i class="fa-solid fa-chevron-down text-[9px] sm:text-[10px] text-zinc-400"></i>
                        </button>

                        <!-- Menu Dropdown Popup -->
                        <div id="dropdownContent" class="hidden absolute right-0 mt-2 w-60 sm:w-64 rounded-2xl bg-white p-2 shadow-2xl ring-1 ring-black/10 border border-zinc-200 z-50">
                            <div class="px-3.5 py-3 border-b border-zinc-100 mb-1">
                                <p class="text-sm font-bold text-zinc-900 truncate"><?php echo htmlspecialchars($user['fullname']); ?></p>
                                <p class="text-xs text-zinc-500 truncate mb-2"><?php echo htmlspecialchars($user['email']); ?></p>
                                
                                <?php if (!empty($user['face_descriptor'])): ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fa-solid fa-circle-check text-[9px]"></i> Face ID Enrolled
                                    </span>
                                <?php else: ?>
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] sm:text-[11px] font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                        <i class="fa-solid fa-triangle-exclamation text-[9px]"></i> Face ID Not Set
                                    </span>
                                <?php endif; ?>
                            </div>

                            <a href="<?php echo $root; ?>user/dashboard.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold text-zinc-700 hover:bg-zinc-50 hover:text-zinc-900 transition-all">
                                <i class="fa-solid fa-gauge-high w-4 text-zinc-400"></i> Dashboard
                            </a>
                            <a href="<?php echo $root; ?>user/fund_wallet.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold text-emerald-700 bg-emerald-50/60 hover:bg-emerald-100/60 transition-all">
                                <i class="fa-solid fa-plus-circle w-4 text-emerald-600"></i> Fund Digital Wallet
                            </a>
                            <a href="<?php echo $root; ?>user/enroll_face.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold text-zinc-700 hover:bg-zinc-50 hover:text-zinc-900 transition-all">
                                <i class="fa-solid fa-face-viewfinder w-4 text-zinc-600"></i>
                                <?php echo !empty($user['face_descriptor']) ? 'Manage Face Biometrics' : 'Enroll Face ID'; ?>
                            </a>
                            <a href="<?php echo $root; ?>user/dashboard.php#history" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold text-zinc-700 hover:bg-zinc-50 hover:text-zinc-900 transition-all">
                                <i class="fa-solid fa-clock-rotate-left w-4 text-zinc-400"></i> Transaction History
                            </a>

                            <div class="border-t border-zinc-100 my-1"></div>
                            
                            <a href="<?php echo $root; ?>logout.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-xs sm:text-sm font-semibold text-red-600 hover:bg-red-50 transition-all">
                                <i class="fa-solid fa-arrow-right-from-bracket w-4"></i> Sign Out
                            </a>
                        </div>
                    </div>

                <?php elseif (is_admin_logged_in() && $admin): ?>
                    <!-- Admin Profile Menu -->
                    <div class="relative" id="userMenuDropdown">
                        <button type="button" onclick="toggleNavDropdown(event)" class="flex items-center gap-2.5 p-1.5 pr-3 rounded-full hover:bg-zinc-100 transition-all border border-red-200 bg-red-50/50 shadow-sm focus:outline-none">
                            <div class="w-8 h-8 rounded-full bg-red-600 flex items-center justify-center text-white text-xs font-bold">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                            <span class="text-sm font-semibold text-zinc-800 hidden sm:inline-block">
                                <?php echo htmlspecialchars(explode(' ', $admin['fullname'])[0]); ?> (Admin)
                            </span>
                            <i class="fa-solid fa-chevron-down text-[10px] text-zinc-400"></i>
                        </button>

                        <div id="dropdownContent" class="hidden absolute right-0 mt-2 w-56 rounded-2xl bg-white p-2 shadow-2xl ring-1 ring-black/5 border border-zinc-100 z-50">
                            <div class="px-3.5 py-3 border-b border-zinc-100 mb-1">
                                <p class="text-sm font-bold text-zinc-900 truncate"><?php echo htmlspecialchars($admin['fullname']); ?></p>
                                <p class="text-xs text-red-600 font-bold uppercase tracking-wider mt-0.5">Super Administrator</p>
                            </div>
                            <a href="<?php echo $root; ?>admin/dashboard.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold text-zinc-700 hover:bg-zinc-50 hover:text-red-600 transition-all">
                                <i class="fa-solid fa-chart-pie w-4 text-zinc-400"></i> Admin Overview
                            </a>
                            <a href="<?php echo $root; ?>admin/users.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold text-zinc-700 hover:bg-zinc-50 hover:text-red-600 transition-all">
                                <i class="fa-solid fa-users w-4 text-zinc-400"></i> Users & Wallets
                            </a>
                            <div class="border-t border-zinc-100 my-1"></div>
                            <a href="<?php echo $root; ?>logout.php" class="flex items-center gap-2.5 px-3 py-2 rounded-xl text-sm font-semibold text-red-600 hover:bg-red-50 transition-all">
                                <i class="fa-solid fa-arrow-right-from-bracket w-4"></i> Sign Out
                            </a>
                        </div>
                    </div>

                <?php else: ?>
                    <!-- Guest Auth Links Group -->
                    <a href="<?php echo $root; ?>user/login.php" class="px-4 py-2 rounded-xl text-sm font-semibold text-zinc-700 hover:text-zinc-900 hover:bg-zinc-100 transition-all">
                        Sign In
                    </a>
                    <a href="<?php echo $root; ?>user/register.php" class="px-4 py-2 rounded-xl text-sm font-semibold text-white bg-zinc-900 hover:bg-zinc-800 shadow-sm transition-all">
                        <i class="fa-solid fa-user-plus mr-1"></i> Register
                    </a>
                    <a href="<?php echo $root; ?>admin/login.php" class="hidden sm:inline-flex px-3 py-2 rounded-xl text-xs font-bold text-zinc-500 hover:text-zinc-900 hover:bg-zinc-100 transition-all" title="Admin Terminal">
                        <i class="fa-solid fa-lock mr-1"></i> Admin
                    </a>
                <?php endif; ?>
            </div>

        </div>
    </div>
</nav>

<script>
function toggleNavDropdown(e) {
    e.stopPropagation();
    const dropdown = document.getElementById('dropdownContent');
    if (dropdown) {
        dropdown.classList.toggle('hidden');
    }
}

document.addEventListener('click', function(e) {
    const container = document.getElementById('userMenuDropdown');
    const dropdown = document.getElementById('dropdownContent');
    if (dropdown && container && !container.contains(e.target)) {
        dropdown.classList.add('hidden');
    }
});
</script>
<script src="<?php echo $root; ?>assets/js/notifications.js"></script>

