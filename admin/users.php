<?php
include_once("../config.php");

if (!is_admin_logged_in()) {
    redirect("login.php");
}

$message = "";

// Handle Manual Wallet Funding
if (isset($_POST['fund_wallet'])) {
    $user_id = (int)$_POST['user_id'];
    $amount  = (float)$_POST['amount'];
    $admin_id = (int)$_SESSION['admin_id'];

    if ($amount > 0 && $user_id > 0) {
        mysqli_begin_transaction($conn);
        try {
            // Update wallet balance
            $u_stmt = mysqli_prepare($conn, "UPDATE users SET wallet_balance = wallet_balance + ? WHERE id = ?");
            mysqli_stmt_bind_param($u_stmt, "di", $amount, $user_id);
            mysqli_stmt_execute($u_stmt);
            mysqli_stmt_close($u_stmt);

            // Record funding audit log
            $f_stmt = mysqli_prepare($conn, "INSERT INTO wallet_funding (user_id, amount, funded_by) VALUES (?, ?, ?)");
            mysqli_stmt_bind_param($f_stmt, "idi", $user_id, $amount, $admin_id);
            mysqli_stmt_execute($f_stmt);
            mysqli_stmt_close($f_stmt);

            mysqli_commit($conn);
            $message = "<div class='mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-circle-check text-lg'></i> Wallet credited with ₦" . number_format($amount, 2) . " successfully!</div>";
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Failed to credit wallet: " . $e->getMessage() . "</div>";
        }
    }
}

$page_title = "User Directory";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
                <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Customer Directory & Wallets</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Manage accounts, audit biometric statuses, and issue wallet credits</p>
            </div>
            <a href="funding_history.php" class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white text-xs sm:text-sm font-bold shadow-sm transition-all">
                <i class="fa-solid fa-clock-rotate-left"></i> Funding Audit Trail
            </a>
        </div>

        <?php echo $message; ?>

        <!-- Users Table Card -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">User</th>
                            <th class="px-6 py-4">Contact Info</th>
                            <th class="px-6 py-4">Biometric Face ID</th>
                            <th class="px-6 py-4">Wallet Balance</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $users = mysqli_query($conn, "SELECT * FROM users ORDER BY id DESC");
                        while ($u = mysqli_fetch_assoc($users)):
                            $has_face = !empty($u['face_descriptor']);
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4">
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-2xl overflow-hidden bg-brand-600 text-white flex items-center justify-center font-bold text-xs shadow-sm flex-shrink-0">
                                            <?php if (!empty($u['face_photo'])): ?>
                                                <img src="<?php echo htmlspecialchars($u['face_photo']); ?>" alt="Face" class="w-full h-full object-cover">
                                            <?php else: ?>
                                                <span><?php echo strtoupper(substr($u['fullname'], 0, 1)); ?></span>
                                            <?php endif; ?>
                                        </div>
                                        <div>
                                            <div class="font-bold text-slate-900"><?php echo htmlspecialchars($u['fullname']); ?></div>
                                            <div class="text-xs text-slate-400">ID: #USR-<?php echo $u['id']; ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4">
                                    <div class="text-slate-800 font-medium"><?php echo htmlspecialchars($u['email']); ?></div>
                                    <div class="text-xs text-slate-500"><?php echo htmlspecialchars($u['phone']); ?></div>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if ($has_face): ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                            <i class="fa-solid fa-circle-check text-[10px]"></i> Enrolled (128-D)
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            <i class="fa-solid fa-triangle-exclamation text-[10px]"></i> Not Enrolled
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="px-6 py-4 font-extrabold text-brand-700 text-base">
                                    ₦<?php echo number_format((float)$u['wallet_balance'], 2); ?>
                                </td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $u['status'] == 'Active' ? 'bg-emerald-50 text-emerald-700' : 'bg-red-50 text-red-700'; ?>">
                                        <?php echo htmlspecialchars($u['status']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <button type="button" onclick="openFundModal(<?php echo $u['id']; ?>, '<?php echo addslashes($u['fullname']); ?>')" class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-brand-50 hover:bg-brand-100 text-brand-700 border border-brand-200 text-xs font-bold transition-all">
                                        <i class="fa-solid fa-plus text-[10px]"></i> Credit
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<!-- Fund Wallet Modal -->
<div id="fundModal" class="hidden fixed inset-0 z-50 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-100">
        <div class="flex items-center justify-between mb-6">
            <h3 class="font-heading text-lg font-bold text-slate-900">Fund User Wallet</h3>
            <button type="button" onclick="closeFundModal()" class="w-8 h-8 rounded-full bg-slate-100 text-slate-500 hover:text-slate-800 flex items-center justify-center text-sm">
                <i class="fa-solid fa-xmark"></i>
            </button>
        </div>

        <form method="POST" class="space-y-4">
            <input type="hidden" name="user_id" id="modalUserId">

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Customer</label>
                <input type="text" id="modalUserName" class="w-full px-3.5 py-2.5 rounded-xl text-sm bg-slate-100 text-slate-700 border border-slate-200 cursor-not-allowed" readonly>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Credit Amount (₦)</label>
                <input type="number" step="100" min="10" name="amount" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 text-slate-900 focus:ring-2 focus:ring-brand-500 focus:outline-none" placeholder="e.g. 5000" required>
            </div>

            <div class="flex gap-3 pt-2">
                <button type="button" onclick="closeFundModal()" class="flex-1 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                    Cancel
                </button>
                <button type="submit" name="fund_wallet" class="flex-1 py-2.5 rounded-xl bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-500 hover:to-accent-500 text-white text-xs font-bold shadow-md shadow-brand-500/20 transition-all">
                    Authorize Credit
                </button>
            </div>
        </form>
    </div>
</div>

<script>
function openFundModal(userId, userName) {
    document.getElementById('modalUserId').value = userId;
    document.getElementById('modalUserName').value = userName;
    document.getElementById('fundModal').classList.remove('hidden');
}

function closeFundModal() {
    document.getElementById('fundModal').classList.add('hidden');
}
</script>

<?php include_once("../includes/footer.php"); ?>