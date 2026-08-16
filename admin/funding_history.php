<?php
include_once("../config.php");

if (!is_admin_logged_in()) {
    redirect("login.php");
}

$page_title = "Wallet Funding Audit";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Wallet Funding Audit Trail</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Audit log of all manual and automated wallet credits issued to users</p>
            </div>
            <a href="users.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-bold shadow-sm transition-all">
                <i class="fa-solid fa-users"></i> Back to User Directory
            </a>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Ref #</th>
                            <th class="px-6 py-4">Recipient Customer</th>
                            <th class="px-6 py-4">Payment Method</th>
                            <th class="px-6 py-4">Credit Amount</th>
                            <th class="px-6 py-4">Authorized By</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Date & Time</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $query = mysqli_query($conn, "SELECT wallet_funding.*, users.fullname AS user_name, users.email, admins.fullname AS admin_name FROM wallet_funding INNER JOIN users ON wallet_funding.user_id = users.id LEFT JOIN admins ON wallet_funding.funded_by = admins.id ORDER BY wallet_funding.id DESC");
                        if (mysqli_num_rows($query) > 0):
                            while ($row = mysqli_fetch_assoc($query)):
                                $ref = !empty($row['reference']) ? $row['reference'] : ('WF-' . str_pad($row['id'], 5, '0', STR_PAD_LEFT));
                                $method = $row['payment_method'] ?? 'Admin Manual Credit';
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4 font-mono font-bold text-slate-900"><?php echo htmlspecialchars($ref); ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900"><?php echo htmlspecialchars($row['user_name']); ?></div>
                                    <div class="text-xs text-slate-400"><?php echo htmlspecialchars($row['email']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo strpos($method, 'Card') !== false ? 'bg-indigo-50 text-indigo-700 border border-indigo-200' : (strpos($method, 'Transfer') !== false ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-slate-100 text-slate-700 border border-slate-200'); ?>">
                                        <i class="fa-solid <?php echo strpos($method, 'Card') !== false ? 'fa-credit-card' : (strpos($method, 'Transfer') !== false ? 'fa-building-columns' : 'fa-hand-holding-dollar'); ?> text-[10px]"></i>
                                        <?php echo htmlspecialchars($method); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-extrabold text-emerald-600 text-base">
                                    +₦<?php echo number_format($row['amount'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-slate-100 text-slate-800 border border-slate-200">
                                        <i class="fa-solid fa-shield-halved text-slate-500"></i> <?php echo htmlspecialchars($row['admin_name'] ?? 'System Gateway'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fa-solid fa-circle-check text-[10px]"></i> <?php echo htmlspecialchars($row['status'] ?? 'Completed'); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500"><?php echo date('M d, Y H:i', strtotime($row['funded_date'])); ?></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">No wallet funding logs recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>