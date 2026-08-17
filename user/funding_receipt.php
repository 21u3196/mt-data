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

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md bg-white rounded-3xl p-8 sm:p-10 shadow-2xl border border-slate-100 relative">
        
        <!-- Header -->
        <div class="text-center mb-8">
            <div class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-3xl shadow-lg shadow-emerald-500/20 mb-4 animate-bounce">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Wallet Funded!</h1>
            <p class="text-xs text-slate-500 mt-1">Ref: <span class="font-mono font-bold text-slate-700"><?php echo htmlspecialchars($funding['reference']); ?></span></p>
        </div>

        <!-- Receipt Box -->
        <div class="p-5 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3.5 text-sm mb-5">
            <div class="flex justify-between items-center text-slate-600">
                <span>Payment Channel:</span>
                <span class="font-bold text-slate-900 flex items-center gap-1.5">
                    <i class="fa-solid fa-shield-check text-emerald-600"></i>
                    <?php echo htmlspecialchars($funding['payment_method']); ?>
                </span>
            </div>

            <div class="flex justify-between items-center text-slate-600">
                <span>Amount Credited:</span>
                <span class="font-extrabold text-emerald-600 text-base">
                    +₦<?php echo number_format($funding['amount'], 2); ?>
                </span>
            </div>

            <div class="flex justify-between items-center text-slate-600">
                <span>Previous Balance:</span>
                <span class="text-slate-700 font-mono font-semibold">₦<?php echo number_format($funding['old_balance'], 2); ?></span>
            </div>

            <div class="border-t border-slate-200 pt-3 flex justify-between items-center">
                <span class="text-slate-700 font-bold">New Wallet Balance:</span>
                <span class="font-extrabold text-brand-600 text-lg">₦<?php echo number_format($funding['new_balance'], 2); ?></span>
            </div>

            <div class="flex justify-between items-center text-xs text-slate-400 pt-1">
                <span>Processed At:</span>
                <span><?php echo date('M d, Y H:i:s', strtotime($funding['date'])); ?></span>
            </div>
        </div>

        <!-- Automated Confirmation Acknowledgements -->
        <div class="rounded-2xl bg-emerald-50/70 border border-emerald-100 p-4 mb-6 space-y-2.5">
            <div class="flex items-center justify-between text-xs font-bold text-emerald-900">
                <span class="flex items-center gap-1.5"><i class="fa-solid fa-bell-ring text-emerald-600"></i> Automated Acknowledgement</span>
                <span class="px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 text-[10px]">Dispatched</span>
            </div>
            
            <div class="space-y-1.5 text-xs text-slate-600 pt-1">
                <div class="flex items-center gap-2">
                    <i class="fa-solid fa-comment-sms text-emerald-600 text-xs w-4"></i>
                    <span>SMS to <strong class="text-slate-800"><?php echo htmlspecialchars($user['phone'] ?? 'Registered Phone'); ?></strong>:</span>
                    <span class="ml-auto text-[10px] font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded border border-emerald-200">Sent (Simulated)</span>
                </div>
                <?php if (!empty($funding['sms_message'])): ?>
                    <div class="bg-white/80 p-2.5 rounded-xl border border-emerald-100/80 font-mono text-[11px] text-slate-700 leading-relaxed shadow-xs">
                        <span class="text-emerald-600 font-bold">MT-DATA:</span> <?php echo htmlspecialchars($funding['sms_message']); ?>
                    </div>
                <?php endif; ?>

                <div class="flex items-center gap-2 pt-1 text-slate-600">
                    <i class="fa-solid fa-envelope text-brand-600 text-xs w-4"></i>
                    <span>Email to <strong class="text-slate-800"><?php echo htmlspecialchars($user['email'] ?? 'Account'); ?></strong>:</span>
                    <span class="ml-auto text-[10px] font-bold text-brand-600 bg-brand-50 px-1.5 py-0.5 rounded border border-brand-200">Resend Dispatched</span>
                </div>
            </div>
        </div>

        <!-- Actions -->
        <div class="space-y-3">
            <a href="dashboard.php#buydata" class="w-full py-3.5 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-bolt"></i> Buy Data / Airtime Now
            </a>

            <div class="grid grid-cols-2 gap-3">
                <a href="fund_wallet.php" class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors text-center">
                    <i class="fa-solid fa-plus mr-1"></i> Fund Again
                </a>
                <a href="dashboard.php" class="py-2.5 px-4 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors text-center">
                    <i class="fa-solid fa-house mr-1"></i> Dashboard
                </a>
            </div>
        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>
