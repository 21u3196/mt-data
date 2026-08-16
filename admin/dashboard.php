<?php
include_once("../config.php");

if (!is_admin_logged_in()) {
    redirect("login.php");
}

$admin = get_admin($_SESSION['admin_id']);

// Real-time metric queries
$total_users = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users"))['c'];
$total_wallets = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(wallet_balance) as s FROM users"))['s'] ?? 0;
$total_txs = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions"))['c'];
$total_volume = mysqli_fetch_assoc(mysqli_query($conn, "SELECT SUM(amount) as s FROM transactions"))['s'] ?? 0;
$face_enrolled_count = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as c FROM users WHERE face_descriptor IS NOT NULL"))['c'];

$page_title = "Admin Hub";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <!-- Admin Header Banner -->
        <div class="rounded-3xl bg-slate-900 p-6 sm:p-8 text-white shadow-xl flex flex-col md:flex-row md:items-center md:justify-between gap-6">
            <div>
                <div class="flex items-center gap-2 text-red-400 text-xs font-bold uppercase tracking-wider mb-2">
                    <i class="fa-solid fa-shield-halved"></i> Super Administrator Console
                </div>
                <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
                    Welcome back, <?php echo htmlspecialchars($admin['fullname']); ?>
                </h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-1">
                    System Node: <span class="text-slate-200 font-semibold">Online</span> &bull; 
                    Bcrypt Security: <span class="text-emerald-400 font-semibold">Enforced</span> &bull; 
                    AI Biometrics: <span class="text-brand-400 font-semibold">Active</span>
                </p>
            </div>

            <div class="flex items-center gap-3">
                <a href="users.php" class="inline-flex items-center gap-2 px-5 py-2.5 rounded-2xl bg-brand-600 hover:bg-brand-500 text-white text-xs sm:text-sm font-bold shadow-lg shadow-brand-500/25 transition-all">
                    <i class="fa-solid fa-hand-holding-dollar"></i> Fund User Wallet
                </a>
            </div>
        </div>

        <!-- Metric Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6">
            
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Registered Users</p>
                    <h3 class="font-heading text-2xl font-extrabold text-slate-900 mt-1"><?php echo number_format($total_users); ?></h3>
                    <p class="text-[11px] text-brand-600 font-bold mt-1"><?php echo $face_enrolled_count; ?> Face ID Enrolled</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-users"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">User Wallet Liquidity</p>
                    <h3 class="font-heading text-2xl font-extrabold text-brand-600 mt-1">₦<?php echo number_format($total_wallets, 2); ?></h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-1">Active customer balances</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-indigo-50 text-brand-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Sales Volume</p>
                    <h3 class="font-heading text-2xl font-extrabold text-emerald-600 mt-1">₦<?php echo number_format($total_volume, 2); ?></h3>
                    <p class="text-[11px] text-slate-400 font-medium mt-1">Gross vending throughput</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Orders</p>
                    <h3 class="font-heading text-2xl font-extrabold text-slate-900 mt-1"><?php echo number_format($total_txs); ?></h3>
                    <p class="text-[11px] text-emerald-600 font-bold mt-1">100% Success Rate</p>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>

        </div>

        <!-- Recent System Vending Activity -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-bolt"></i>
                    </div>
                    <div>
                        <h3 class="font-heading text-base font-bold text-slate-900">Live Customer Activity</h3>
                        <p class="text-xs text-slate-500">Real-time vending stream across all networks</p>
                    </div>
                </div>
                <a href="transactions.php" class="text-xs font-bold text-brand-600 hover:text-brand-700">View Master Ledger &rarr;</a>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Ref #</th>
                            <th class="px-6 py-4">Customer</th>
                            <th class="px-6 py-4">Service</th>
                            <th class="px-6 py-4">Recipient</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $recent = mysqli_query($conn, "SELECT transactions.*, users.fullname, users.email FROM transactions INNER JOIN users ON transactions.user_id = users.id ORDER BY transactions.id DESC LIMIT 10");
                        if (mysqli_num_rows($recent) > 0):
                            while ($tx = mysqli_fetch_assoc($recent)):
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-900">#TX-<?php echo str_pad($tx['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-semibold text-slate-900"><?php echo htmlspecialchars($tx['fullname']); ?></div>
                                    <div class="text-xs text-slate-400"><?php echo htmlspecialchars($tx['email']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $tx['service_type'] == 'Data' ? 'bg-brand-50 text-brand-700 border border-brand-200' : ($tx['service_type'] == 'Airtime' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'); ?>">
                                        <?php echo htmlspecialchars($tx['service_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($tx['phone_number']); ?></td>
                                <td class="px-6 py-4 font-extrabold text-slate-900">₦<?php echo number_format($tx['amount'], 2); ?></td>
                                <td class="px-6 py-4 text-xs text-slate-500"><?php echo date('M d, Y H:i', strtotime($tx['transaction_date'])); ?></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="6" class="px-6 py-12 text-center text-slate-400">No transactions recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>