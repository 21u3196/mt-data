<?php
include_once("../config.php");

if (!is_logged_in()) {
    redirect("login.php");
}

$user = get_current_user_data();
$funding = $_SESSION['last_funding'] ?? null;

if (!$funding) {
    redirect("dashboard.php");
}

$page_title = "Funding Receipt";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 flex items-center justify-center py-6 px-3 sm:px-6">
    <div class="w-full max-w-sm bg-white rounded-2xl p-5 sm:p-6 shadow-md border border-slate-200 relative">
        
        <!-- Header -->
        <div class="text-center mb-5">
            <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center mx-auto text-2xl border border-emerald-200 mb-3">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h1 class="font-heading text-xl font-bold text-slate-900">Wallet Funded!</h1>
            <p class="text-[11px] text-slate-500 mt-0.5">Ref: <span class="font-mono font-semibold text-slate-700"><?php echo htmlspecialchars($funding['reference']); ?></span></p>
        </div>

        <!-- Receipt Summary Box -->
        <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2.5 text-xs mb-4">
            <div class="flex justify-between items-center text-slate-600">
                <span>Method:</span>
                <span class="font-bold text-slate-900">
                    <?php echo htmlspecialchars($funding['payment_method']); ?>
                </span>
            </div>

            <div class="flex justify-between items-center text-slate-600">
                <span>Amount Credited:</span>
                <span class="font-extrabold text-emerald-600 text-sm">
                    +₦<?php echo number_format($funding['amount'], 2); ?>
                </span>
            </div>

            <div class="flex justify-between items-center text-slate-600">
                <span>Previous Balance:</span>
                <span class="text-slate-700 font-mono">₦<?php echo number_format($funding['old_balance'], 2); ?></span>
            </div>

            <div class="border-t border-slate-200 pt-2 flex justify-between items-center">
                <span class="text-slate-700 font-bold">New Balance:</span>
                <span class="font-extrabold text-slate-900 text-base">₦<?php echo number_format($funding['new_balance'], 2); ?></span>
            </div>

            <div class="flex justify-between items-center text-[10px] text-slate-400 pt-0.5">
                <span>Date:</span>
                <span><?php echo date('M d, Y H:i', strtotime($funding['date'])); ?></span>
            </div>
        </div>

        <!-- Status Pill -->
        <div class="rounded-xl bg-emerald-50 border border-emerald-100 p-2.5 mb-5 flex items-center justify-between text-xs font-semibold text-emerald-900">
            <span class="flex items-center gap-1.5"><i class="fa-solid fa-bell text-emerald-600"></i> Notification</span>
            <span class="text-[10px] font-bold text-emerald-700 bg-emerald-100 px-2 py-0.5 rounded">Delivered</span>
        </div>

        <!-- Action Buttons -->
        <div class="space-y-2">
            <a href="dashboard.php#buydata" class="w-full py-3 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-xs shadow-sm transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-bolt text-amber-400"></i> Buy Data / Airtime
            </a>

            <div class="grid grid-cols-2 gap-2">
                <a href="fund_wallet.php" class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors text-center">
                    <i class="fa-solid fa-plus mr-1"></i> Fund Again
                </a>
                <a href="dashboard.php" class="py-2.5 px-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors text-center">
                    <i class="fa-solid fa-house mr-1"></i> Dashboard
                </a>
            </div>
        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>
