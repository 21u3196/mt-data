<?php
include_once("../config.php");

if (!is_logged_in()) {
    redirect("login.php");
}

$user_id = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect("dashboard.php");
}

$network = clean_input($_POST['network'] ?? '');
$phone   = clean_input($_POST['phone'] ?? '');
$amount  = (float)($_POST['amount'] ?? 0);

if (empty($network) || empty($phone) || $amount <= 0) {
    redirect("dashboard.php");
}

// Database ACID Transaction
mysqli_begin_transaction($conn);

try {
    // Lock row for update
    $user_stmt = mysqli_prepare($conn, "SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
    mysqli_stmt_bind_param($user_stmt, "i", $user_id);
    mysqli_stmt_execute($user_stmt);
    $user_res = mysqli_stmt_get_result($user_stmt);
    $user = mysqli_fetch_assoc($user_res);
    mysqli_stmt_close($user_stmt);

    $current_balance = (float)$user['wallet_balance'];

    if ($current_balance < $amount) {
        mysqli_rollback($conn);
        $error_message = "Insufficient wallet balance. Please fund your wallet to continue.";
        $purchase_success = false;
    } else {
        $new_balance = $current_balance - $amount;

        // Deduct balance
        $update_stmt = mysqli_prepare($conn, "UPDATE users SET wallet_balance = ? WHERE id = ?");
        mysqli_stmt_bind_param($update_stmt, "di", $new_balance, $user_id);
        mysqli_stmt_execute($update_stmt);
        mysqli_stmt_close($update_stmt);

        // Record transaction
        $description = "$network Airtime Recharge";
        $tx_stmt = mysqli_prepare($conn, "INSERT INTO transactions (user_id, service_type, description, phone_number, amount) VALUES (?, 'Airtime', ?, ?, ?)");
        mysqli_stmt_bind_param($tx_stmt, "issd", $user_id, $description, $phone, $amount);
        mysqli_stmt_execute($tx_stmt);
        $tx_id = mysqli_insert_id($conn);
        mysqli_stmt_close($tx_stmt);

        mysqli_commit($conn);
        $purchase_success = true;

        // Automated multi-channel acknowledgement & simulated SMS dispatch
        require_once(__DIR__ . "/../includes/NotificationService.php");
        $ack_info = NotificationService::send_transaction_acknowledgement([
            'user_id'       => $user_id,
            'user_email'    => $user['email'] ?? '',
            'user_fullname' => $user['fullname'] ?? 'Valued Customer',
            'transaction_id'=> $tx_id,
            'service_type'  => 'Airtime',
            'title'         => "$network Airtime Recharge",
            'description'   => "$network ₦" . number_format($amount, 2) . " Airtime",
            'recipient'     => $phone,
            'amount'        => $amount,
            'new_balance'   => $new_balance,
            'date'          => date('Y-m-d H:i:s')
        ]);
    }
} catch (Exception $e) {
    mysqli_rollback($conn);
    $error_message = "Transaction error: " . $e->getMessage();
    $purchase_success = false;
}

$page_title = "Airtime Receipt";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md bg-white rounded-3xl p-8 shadow-2xl border border-slate-100">
        
        <?php if ($purchase_success): ?>
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-3xl bg-emerald-100 text-emerald-600 flex items-center justify-center mx-auto text-3xl shadow-lg shadow-emerald-500/20 mb-4 animate-bounce">
                    <i class="fa-solid fa-circle-check"></i>
                </div>
                <h1 class="font-heading text-2xl font-extrabold text-slate-900">Recharge Successful!</h1>
                <p class="text-xs text-slate-500 mt-1">Receipt Ref: <span class="font-mono font-bold text-slate-700">#TX-<?php echo str_pad($tx_id, 5, '0', STR_PAD_LEFT); ?></span></p>
            </div>

            <div class="p-4 rounded-2xl bg-slate-50 border border-slate-200/80 space-y-3 text-sm mb-5">
                <div class="flex justify-between text-slate-600">
                    <span>Network:</span>
                    <span class="font-bold text-slate-900"><?php echo htmlspecialchars($network); ?></span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Phone Number:</span>
                    <span class="font-bold text-slate-900"><?php echo htmlspecialchars($phone); ?></span>
                </div>
                <div class="flex justify-between text-slate-600">
                    <span>Amount Recharged:</span>
                    <span class="font-bold text-emerald-600">₦<?php echo number_format($amount, 2); ?></span>
                </div>
                <div class="border-t border-slate-200 pt-2 flex justify-between">
                    <span class="text-slate-500 font-medium">New Wallet Balance:</span>
                    <span class="font-extrabold text-emerald-600">₦<?php echo number_format($new_balance, 2); ?></span>
                </div>
            </div>

        

            <a href="dashboard.php" class="w-full py-3.5 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-house"></i> Return to Dashboard
            </a>

        <?php else: ?>
            <div class="text-center mb-6">
                <div class="w-16 h-16 rounded-3xl bg-red-100 text-red-600 flex items-center justify-center mx-auto text-3xl shadow-lg mb-4">
                    <i class="fa-solid fa-triangle-exclamation"></i>
                </div>
                <h1 class="font-heading text-2xl font-extrabold text-slate-900">Transaction Failed</h1>
                <p class="text-sm text-red-600 mt-2 font-medium"><?php echo htmlspecialchars($error_message); ?></p>
            </div>

            <a href="dashboard.php" class="w-full py-3 px-4 rounded-xl bg-slate-900 text-white font-bold text-sm hover:bg-slate-800 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-arrow-left"></i> Return to Dashboard
            </a>
        <?php endif; ?>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>