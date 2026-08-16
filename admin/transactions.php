<?php
include_once("../config.php");

if (!is_admin_logged_in()) {
    redirect("login.php");
}

$page_title = "Transaction Ledger";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <div>
            <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Master Transaction Ledger</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Audit log of all data, airtime, and cable vending activity</p>
        </div>

        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Ref #</th>
                            <th class="px-6 py-4">Customer</th>
                            <th class="px-6 py-4">Service</th>
                            <th class="px-6 py-4">Details</th>
                            <th class="px-6 py-4">Recipient</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $query = mysqli_query($conn, "SELECT transactions.*, users.fullname, users.email FROM transactions INNER JOIN users ON transactions.user_id = users.id ORDER BY transactions.id DESC");
                        if (mysqli_num_rows($query) > 0):
                            while ($row = mysqli_fetch_assoc($query)):
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-900">#TX-<?php echo str_pad($row['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td class="px-6 py-4">
                                    <div class="font-bold text-slate-900"><?php echo htmlspecialchars($row['fullname']); ?></div>
                                    <div class="text-xs text-slate-400"><?php echo htmlspecialchars($row['email']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $row['service_type'] == 'Data' ? 'bg-brand-50 text-brand-700 border border-brand-200' : ($row['service_type'] == 'Airtime' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'); ?>">
                                        <?php echo htmlspecialchars($row['service_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-slate-700 font-medium"><?php echo htmlspecialchars($row['description']); ?></td>
                                <td class="px-6 py-4 text-slate-600"><?php echo htmlspecialchars($row['phone_number']); ?></td>
                                <td class="px-6 py-4 font-extrabold text-slate-900">₦<?php echo number_format($row['amount'], 2); ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fa-solid fa-check text-[10px]"></i> Success
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500"><?php echo date('M d, Y H:i', strtotime($row['transaction_date'])); ?></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="8" class="px-6 py-12 text-center text-slate-400">No transactions recorded yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>