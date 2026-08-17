<?php
include_once("../config.php");

if (!is_logged_in()) {
    redirect("login.php");
}

$user = get_current_user_data();
$user_id = $user['id'];
$wallet_balance = (float)$user['wallet_balance'];

// Process Simulated Payment submission
$error_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_funding'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = clean_input($_POST['payment_method'] ?? 'Card Payment');
    $custom_ref = clean_input($_POST['payment_reference'] ?? ('WF-' . strtoupper(bin2hex(random_bytes(4)))));

    if ($amount < 100) {
        $error_message = "Minimum wallet funding amount is ₦100.00.";
    } else {
        // Execute atomic database transaction
        mysqli_begin_transaction($conn);
        try {
            // Lock user row
            $u_stmt = mysqli_prepare($conn, "SELECT wallet_balance FROM users WHERE id = ? FOR UPDATE");
            mysqli_stmt_bind_param($u_stmt, "i", $user_id);
            mysqli_stmt_execute($u_stmt);
            $res = mysqli_stmt_get_result($u_stmt);
            $u_data = mysqli_fetch_assoc($res);
            mysqli_stmt_close($u_stmt);

            $old_bal = (float)$u_data['wallet_balance'];
            $new_bal = $old_bal + $amount;

            // Update user wallet balance
            $up_stmt = mysqli_prepare($conn, "UPDATE users SET wallet_balance = ? WHERE id = ?");
            mysqli_stmt_bind_param($up_stmt, "di", $new_bal, $user_id);
            mysqli_stmt_execute($up_stmt);
            mysqli_stmt_close($up_stmt);

            // Record into wallet_funding
            $admin_system_id = 1;
            $f_stmt = mysqli_prepare($conn, "INSERT INTO wallet_funding (user_id, amount, funded_by, payment_method, reference, status) VALUES (?, ?, ?, ?, ?, 'Completed')");
            mysqli_stmt_bind_param($f_stmt, "idiss", $user_id, $amount, $admin_system_id, $payment_method, $custom_ref);
            mysqli_stmt_execute($f_stmt);
            $funding_id = mysqli_insert_id($conn);
            mysqli_stmt_close($f_stmt);

            mysqli_commit($conn);

            // Automated multi-channel funding acknowledgement
            require_once(__DIR__ . "/../includes/NotificationService.php");
            $ack_info = NotificationService::send_funding_acknowledgement([
                'user_id'       => $user_id,
                'user_email'    => $user['email'] ?? '',
                'user_fullname' => $user['fullname'] ?? 'Valued Customer',
                'funding_id'    => $funding_id,
                'amount'        => $amount,
                'payment_method'=> $payment_method,
                'old_balance'   => $old_bal,
                'new_balance'   => $new_bal,
                'reference'     => $custom_ref,
                'date'          => date('Y-m-d H:i:s')
            ]);

            // Store receipt in session and redirect to receipt
            $_SESSION['last_funding'] = [
                'id' => $funding_id,
                'reference' => $custom_ref,
                'amount' => $amount,
                'payment_method' => $payment_method,
                'old_balance' => $old_bal,
                'new_balance' => $new_bal,
                'date' => date('Y-m-d H:i:s'),
                'sms_message' => $ack_info['sms_message'] ?? '',
                'email_sent' => $ack_info['email_sent'] ?? false
            ];

            redirect("funding_receipt.php");
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = "Payment processing error: " . $e->getMessage();
        }
    }
}

// Generate virtual account number for transfer simulation based on phone or unique hash
$virtual_account_num = "9" . substr(preg_replace('/[^0-9]/', '', $user['phone'] ?? '0801234567'), -9);
if (strlen($virtual_account_num) < 10) {
    $virtual_account_num = "90" . str_pad($user_id, 8, '0', STR_PAD_LEFT);
}

$page_title = "Fund Wallet";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8 relative">
    <div class="max-w-4xl mx-auto space-y-8">
        
        <!-- Header Banner -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
            <div>
           
                <h1 class="font-heading text-2xl sm:text-3xl font-extrabold text-slate-900 tracking-tight">Fund Digital Wallet</h1>
                <p class="text-xs sm:text-sm text-slate-500 mt-1">Instant real-time wallet funding via Card, Bank Transfer, or USSD</p>
            </div>
            
            <div class="flex items-center gap-3 p-3.5 bg-white rounded-2xl border border-slate-200 shadow-sm">
                <div class="w-10 h-10 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center text-lg">
                    <i class="fa-solid fa-wallet"></i>
                </div>
                <div>
                    <span class="text-xs font-medium text-slate-400 block">Current Balance</span>
                    <span class="font-heading font-extrabold text-slate-900 text-lg">₦<?php echo number_format($wallet_balance, 2); ?></span>
                </div>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3">
                <i class="fa-solid fa-circle-exclamation text-lg"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Step 1: Select Amount -->
        <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
            <div class="flex items-center gap-3 mb-6">
                <span class="w-7 h-7 rounded-full bg-brand-600 text-white font-bold text-xs flex items-center justify-center">1</span>
                <h3 class="font-heading text-lg font-bold text-slate-900">Choose Funding Amount</h3>
            </div>

            <!-- Quick Pill Buttons -->
            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">Quick Amount Presets</label>
            <div class="grid grid-cols-3 sm:grid-cols-6 gap-2.5 mb-6">
                <?php $presets = [1000, 2000, 5000, 10000, 20000, 50000]; foreach ($presets as $p): ?>
                    <button type="button" onclick="setFundingAmount(<?php echo $p; ?>)" class="preset-btn py-3 px-3 rounded-2xl text-xs sm:text-sm font-bold border border-slate-200 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 transition-all text-slate-700 text-center" data-val="<?php echo $p; ?>">
                        ₦<?php echo number_format($p); ?>
                    </button>
                <?php endforeach; ?>
            </div>

            <!-- Custom Amount Input -->
            <div class="max-w-lg space-y-4">
                <div>
                    <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Or Enter Custom Amount (₦)</label>
                    <div class="relative rounded-2xl border-2 border-slate-200 bg-white focus-within:border-brand-500 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none text-slate-400 font-extrabold text-base">
                            ₦
                        </span>
                        <input type="number" id="fundingAmountInput" step="50" min="100" max="1000000" value="" oninput="updatePayableSummary()" onkeydown="if(event.key==='Enter'){event.preventDefault();proceedToPaymentSection();}" class="w-full pl-9 pr-4 py-3.5 rounded-2xl text-lg font-extrabold text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="Enter amount (e.g. 2000)">
                    </div>
                    <p class="text-[11px] text-slate-400 mt-1">Min: ₦100 &bull; Max: ₦1,000,000 per transaction</p>
                </div>

                <!-- Pay Now Button to Unlock Step 2 Form -->
                <button type="button" onclick="proceedToPaymentSection()" class="w-full sm:w-auto px-8 py-4 rounded-2xl bg-zinc-900 hover:bg-zinc-800 text-white font-extrabold text-base shadow-sm transition-all flex items-center justify-center gap-3">
                    <span>Pay Now ₦<span class="pay-amount-label">0.00</span></span>
                    <i class="fa-solid fa-arrow-down text-sm"></i>
                </button>
            </div>
        </div>

        <!-- Step 2: Payment Method Channels (Hidden until Pay Now is clicked) -->
        <div id="paymentMethodsSection" style="display: none;" class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm space-y-6">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-4 border-b border-slate-100">
                <div class="flex items-center gap-3">
                    <span class="w-7 h-7 rounded-full bg-brand-600 text-white font-bold text-xs flex items-center justify-center">2</span>
                    <h3 class="font-heading text-lg font-bold text-slate-900">Select Payment Channel</h3>
                </div>
                <div class="inline-flex items-center gap-2 px-4 py-2 rounded-2xl bg-brand-50 border border-brand-200 text-brand-900 text-xs font-bold">
                    <span>Amount:</span>
                    <span class="text-brand-600 text-sm font-extrabold">₦<span class="pay-amount-label">0.00</span></span>
                </div>
            </div>

            <!-- Channel Tabs -->
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-8">
                
                <!-- Channel 1: Card -->
                <button type="button" id="channelTabCard" onclick="selectChannel('card')" class="channel-btn p-4 rounded-2xl border-2 border-brand-500 bg-brand-50/50 text-left transition-all relative flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-brand-600 text-white flex items-center justify-center text-lg shadow-sm flex-shrink-0">
                        <i class="fa-regular fa-credit-card"></i>
                    </div>
                    <div>
                        <div class="font-bold text-sm text-slate-900">Debit / Credit Card</div>
                        <div class="text-[11px] text-slate-500">Mastercard, Visa, Verve</div>
                        <span class="inline-block mt-1 text-[10px] font-bold text-emerald-600 bg-emerald-50 px-2 py-0.5 rounded-md">Instant Credit</span>
                    </div>
                </button>

                <!-- Channel 2: Bank Transfer -->
                <button type="button" id="channelTabTransfer" onclick="selectChannel('transfer')" class="channel-btn p-4 rounded-2xl border-2 border-slate-200 hover:border-slate-300 text-left transition-all relative flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg shadow-sm flex-shrink-0">
                        <i class="fa-solid fa-building-columns"></i>
                    </div>
                    <div>
                        <div class="font-bold text-sm text-slate-900">Bank Transfer</div>
                        <div class="text-[11px] text-slate-500">Dedicated Virtual Account</div>
                        <span class="inline-block mt-1 text-[10px] font-bold text-brand-600 bg-brand-50 px-2 py-0.5 rounded-md">Automated Match</span>
                    </div>
                </button>

                <!-- Channel 3: USSD -->
                <button type="button" id="channelTabUssd" onclick="selectChannel('ussd')" class="channel-btn p-4 rounded-2xl border-2 border-slate-200 hover:border-slate-300 text-left transition-all relative flex items-start gap-3">
                    <div class="w-10 h-10 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-lg shadow-sm flex-shrink-0">
                        <i class="fa-solid fa-hashtag"></i>
                    </div>
                    <div>
                        <div class="font-bold text-sm text-slate-900">USSD Banking</div>
                        <div class="text-[11px] text-slate-500">GTB, Zenith, Access, UBA</div>
                        <span class="inline-block mt-1 text-[10px] font-bold text-slate-600 bg-slate-100 px-2 py-0.5 rounded-md">Direct String</span>
                    </div>
                </button>

            </div>

            <!-- CHANNEL CONTENT 1: CARD CHECKOUT -->
            <div id="channelContentCard" class="space-y-6">
                <div class="max-w-lg mx-auto bg-slate-50/70 p-6 sm:p-8 rounded-3xl border border-slate-200/80 space-y-5">
                    
                    <!-- Card Validation Error Notice -->
                    <div id="cardValidationError" class="hidden p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                        <i class="fa-solid fa-circle-exclamation text-sm flex-shrink-0"></i>
                        <span id="cardValidationMsg">Please complete all card details.</span>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Card Number</label>
                        <div id="cardNumWrap" class="relative rounded-2xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-zinc-900 transition-all">
                            <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400 text-sm">
                                <i class="fa-solid fa-credit-card"></i>
                            </span>
                            <input type="text" id="cardInputNumber" oninput="handleCardNumberInput(this)" maxlength="19" class="w-full pl-10 pr-4 py-3.5 rounded-2xl text-sm font-mono text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="Card Number (e.g. 5399 4100 1234 5678)" value="" required>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Expiry Date</label>
                            <input type="text" id="cardInputExpiry" oninput="handleExpiryInput(this)" maxlength="5" class="w-full px-4 py-3.5 rounded-2xl text-sm font-mono border border-slate-200 text-slate-900 placeholder-slate-400 bg-white focus:ring-2 focus:ring-zinc-900 focus:outline-none" placeholder="MM/YY" value="" required>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">CVV</label>
                            <input type="password" id="cardInputCvv" oninput="clearCardError()" maxlength="4" class="w-full px-4 py-3.5 rounded-2xl text-sm font-mono border border-slate-200 text-slate-900 placeholder-slate-400 bg-white focus:ring-2 focus:ring-zinc-900 focus:outline-none" placeholder="CVV" value="" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Card 4-Digit PIN</label>
                        <input type="password" id="cardInputPin" oninput="clearCardError()" maxlength="4" class="w-full px-4 py-3.5 rounded-2xl text-sm font-mono border border-slate-200 text-slate-900 placeholder-slate-400 bg-white focus:ring-2 focus:ring-zinc-900 focus:outline-none" placeholder="4-Digit PIN" value="" required>
                    </div>

                    <!-- Card Action Button -->
                    <button type="button" onclick="startCardPaymentFlow()" class="w-full py-4 px-6 rounded-2xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-base shadow-sm transition-all flex items-center justify-center gap-2 mt-2">
                        <i class="fa-solid fa-lock"></i>
                        <span>Pay ₦<span class="pay-amount-label">0.00</span> Now</span>
                    </button>

                </div>
            </div>

            <!-- CHANNEL CONTENT 2: BANK TRANSFER -->
            <div id="channelContentTransfer" style="display: none;" class="space-y-6">
                <div class="p-6 rounded-3xl bg-slate-900 text-white shadow-xl relative overflow-hidden">
                    <div class="absolute -right-8 -bottom-8 w-40 h-40 bg-emerald-500/20 rounded-full blur-3xl"></div>
                    
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 pb-6 border-b border-slate-800">
                        <div>
                            <span class="text-xs font-bold text-emerald-400 uppercase tracking-wider block mb-1">
                                <i class="fa-solid fa-bolt mr-1"></i> Dedicated Virtual Account
                            </span>
                            <h4 class="font-heading text-lg font-bold text-white">Transfer Exact Amount Below</h4>
                        </div>
                        <div class="flex items-center gap-2 px-3 py-1.5 rounded-full bg-slate-800 border border-slate-700 text-xs text-slate-300">
                            <i class="fa-regular fa-clock text-amber-400"></i>
                            <span>Expires in <span id="transferTimer" class="font-mono font-bold text-amber-400">29:58</span></span>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 py-6 border-b border-slate-800">
                        <div>
                            <span class="text-xs text-slate-400 block mb-1">Bank Name</span>
                            <span class="font-heading text-base font-bold text-white">Moniepoint Microfinance</span>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400 block mb-1">Account Number</span>
                            <div class="flex items-center gap-2">
                                <span class="font-mono text-xl font-extrabold text-emerald-400 tracking-wider"><?php echo $virtual_account_num; ?></span>
                                <button type="button" onclick="copyText('<?php echo $virtual_account_num; ?>', this)" class="p-1.5 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors" title="Copy Account Number">
                                    <i class="fa-regular fa-copy text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <div>
                            <span class="text-xs text-slate-400 block mb-1">Beneficiary Name</span>
                            <span class="font-semibold text-white truncate block">MT Data / <?php echo htmlspecialchars($user['fullname']); ?></span>
                        </div>
                    </div>

                    <div class="pt-6 flex flex-col sm:flex-row items-center justify-between gap-4">
                        <div>
                            <span class="text-xs text-slate-400 block">Total Amount to Transfer</span>
                            <span class="font-heading text-2xl font-extrabold text-white">₦<span class="pay-amount-label">0.00</span></span>
                        </div>
                        <button type="button" onclick="simulateBankTransferVerification()" class="w-full sm:w-auto py-3.5 px-8 rounded-2xl bg-emerald-500 hover:bg-emerald-400 text-slate-950 font-extrabold text-sm shadow-lg shadow-emerald-500/25 transition-all flex items-center justify-center gap-2">
                            <i class="fa-solid fa-circle-check"></i> I Have Transferred ₦<span class="pay-amount-label">0.00</span>
                        </button>
                    </div>
                </div>
            </div>

            <!-- CHANNEL CONTENT 3: USSD -->
            <div id="channelContentUssd" style="display: none;" class="space-y-6">
                <div class="p-6 rounded-3xl bg-slate-50 border border-slate-200">
                    <h4 class="font-heading text-base font-bold text-slate-900 mb-2">Select Your Bank USSD Dial String</h4>
                    <p class="text-xs text-slate-500 mb-6">Dial the direct code from your bank-registered SIM to complete payment</p>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 mb-6">
                        <?php
                        $ussd_banks = [
                            ['GTBank', '*737*2*'],
                            ['Zenith Bank', '*966*'],
                            ['Access Bank', '*901*'],
                            ['UBA', '*919*'],
                            ['First Bank', '*894*'],
                            ['Kuda MFB', '*894*'],
                        ];
                        foreach ($ussd_banks as $ub):
                        ?>
                            <div class="p-4 rounded-2xl bg-white border border-slate-200/80 flex items-center justify-between">
                                <div>
                                    <div class="font-bold text-sm text-slate-900"><?php echo $ub[0]; ?></div>
                                    <div class="font-mono text-xs text-brand-600 font-semibold mt-0.5">
                                        <?php echo $ub[1]; ?><span class="pay-amount-label-clean">0</span>*<?php echo $virtual_account_num; ?>#
                                    </div>
                                </div>
                                <button type="button" onclick="simulateUssdPayment('<?php echo $ub[0]; ?>')" class="px-3 py-1.5 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-bold text-slate-700 transition-colors">
                                    Simulate Dial
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<!-- Hidden Form for Processing Backend Verification -->
<form id="paymentSubmitForm" method="POST" style="display: none;">
    <input type="hidden" name="process_funding" value="1">
    <input type="hidden" name="amount" id="formAmountInput" value="0">
    <input type="hidden" name="payment_method" id="formMethodInput" value="Card Payment">
    <input type="hidden" name="payment_reference" id="formRefInput" value="">
</form>

<!-- Modal: Simulated 3D Secure / Bank OTP Challenge -->
<div id="otpModal" class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-6 sm:p-8 max-w-md w-full shadow-2xl border border-slate-100 text-center relative overflow-hidden">
        
        <!-- Processing State (Initially Hidden) -->
        <div id="otpProcessingState" class="hidden py-8 space-y-4">
            <div class="w-16 h-16 rounded-full border-4 border-brand-200 border-t-brand-600 animate-spin mx-auto"></div>
            <h3 class="font-heading text-lg font-bold text-slate-900">Authorizing with Issuer Bank...</h3>
            <p class="text-xs text-slate-500">Confirming 3D-Secure authentication and token settlement.</p>
        </div>

        <!-- Input State -->
        <div id="otpInputState" class="space-y-6">
            <div class="w-14 h-14 rounded-2xl bg-brand-100 text-brand-600 flex items-center justify-center mx-auto text-2xl shadow-md shadow-brand-500/10">
                <i class="fa-solid fa-shield-halved"></i>
            </div>

            <div>
                <h3 class="font-heading text-xl font-extrabold text-slate-900">3D-Secure Authentication</h3>
                <p class="text-xs text-slate-500 mt-1">
                    Enter any 6-digit one-time passcode to simulate bank authorization:
                </p>
            </div>

            <div>
                <input type="text" id="otpCodeInput" maxlength="6" class="w-full text-center tracking-[0.5em] font-mono text-2xl font-extrabold py-3.5 rounded-2xl border-2 border-slate-200 focus:border-brand-500 focus:outline-none" placeholder="e.g. 123456" value="">
            </div>

            <div class="flex gap-3">
                <button type="button" onclick="closeOtpModal()" class="flex-1 py-3 rounded-xl bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                    Cancel
                </button>
                <button type="button" onclick="submitFinalPayment('Card Payment')" class="flex-1 py-3 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white text-xs font-bold shadow-sm transition-all flex items-center justify-center gap-1.5">
                    <i class="fa-solid fa-check"></i> Authorize Payment
                </button>
            </div>
        </div>

    </div>
</div>

<!-- Modal: Transfer Verifying Simulation -->
<div id="transferModal" class="hidden fixed inset-0 z-50 bg-slate-950/60 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-3xl p-8 max-w-sm w-full shadow-2xl border border-slate-100 text-center space-y-4">
        <div class="w-16 h-16 rounded-full border-4 border-emerald-200 border-t-emerald-600 animate-spin mx-auto"></div>
        <h3 class="font-heading text-lg font-bold text-slate-900">Verifying Bank Transfer...</h3>
        <p class="text-xs text-slate-500">Querying NIBSS / Moniepoint webhook for inbound credit...</p>
    </div>
</div>

<script>
let currentChannel = 'card';

function setFundingAmount(amt) {
    document.getElementById('fundingAmountInput').value = amt;
    updatePayableSummary();
    
    // Highlight active preset
    document.querySelectorAll('.preset-btn').forEach(btn => {
        if (parseInt(btn.getAttribute('data-val')) === amt) {
            btn.className = "preset-btn py-3 px-3 rounded-2xl text-xs sm:text-sm font-bold border-2 border-brand-500 bg-brand-50 text-brand-700 transition-all text-center";
        } else {
            btn.className = "preset-btn py-3 px-3 rounded-2xl text-xs sm:text-sm font-bold border border-slate-200 hover:border-brand-500 hover:bg-brand-50 hover:text-brand-700 transition-all text-slate-700 text-center";
        }
    });
}

function proceedToPaymentSection() {
    const val = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    if (val < 100) {
        alert("Please select or enter an amount of at least ₦100.");
        document.getElementById('fundingAmountInput').focus();
        return;
    }
    updatePayableSummary();
    const section = document.getElementById('paymentMethodsSection');
    section.style.display = 'block';
    section.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

function updatePayableSummary() {
    const val = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    const formatted = val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    document.querySelectorAll('.pay-amount-label').forEach(el => {
        el.innerText = formatted;
    });

    document.querySelectorAll('.pay-amount-label-clean').forEach(el => {
        el.innerText = val.toString();
    });

    document.getElementById('formAmountInput').value = val;
}

function selectChannel(channel) {
    currentChannel = channel;
    
    const tabCard = document.getElementById('channelTabCard');
    const tabTransfer = document.getElementById('channelTabTransfer');
    const tabUssd = document.getElementById('channelTabUssd');

    const contentCard = document.getElementById('channelContentCard');
    const contentTransfer = document.getElementById('channelContentTransfer');
    const contentUssd = document.getElementById('channelContentUssd');

    // Reset styles
    [tabCard, tabTransfer, tabUssd].forEach(tab => {
        tab.className = "channel-btn p-4 rounded-2xl border-2 border-slate-200 hover:border-slate-300 text-left transition-all relative flex items-start gap-3";
    });

    // Hide all
    contentCard.style.display = 'none';
    contentTransfer.style.display = 'none';
    contentUssd.style.display = 'none';

    if (channel === 'card') {
        tabCard.className = "channel-btn p-4 rounded-2xl border-2 border-brand-500 bg-brand-50/50 text-left transition-all relative flex items-start gap-3";
        contentCard.style.display = 'block';
    } else if (channel === 'transfer') {
        tabTransfer.className = "channel-btn p-4 rounded-2xl border-2 border-brand-500 bg-brand-50/50 text-left transition-all relative flex items-start gap-3";
        contentTransfer.style.display = 'block';
    } else if (channel === 'ussd') {
        tabUssd.className = "channel-btn p-4 rounded-2xl border-2 border-brand-500 bg-brand-50/50 text-left transition-all relative flex items-start gap-3";
        contentUssd.style.display = 'block';
    }
}

function clearCardError() {
    const errorEl = document.getElementById('cardValidationError');
    if (errorEl) errorEl.classList.add('hidden');
}

function showCardError(msg, focusId) {
    const errorEl = document.getElementById('cardValidationError');
    const msgEl = document.getElementById('cardValidationMsg');
    if (errorEl && msgEl) {
        msgEl.innerText = msg;
        errorEl.classList.remove('hidden');
    }
    if (focusId) {
        const input = document.getElementById(focusId);
        if (input) {
            input.focus();
            input.classList.add('border-red-500');
            setTimeout(() => input.classList.remove('border-red-500'), 2500);
        }
    }
}

function handleCardNumberInput(input) {
    clearCardError();
    let val = input.value.replace(/\D/g, '').substring(0, 19);
    let formatted = val.match(/.{1,4}/g)?.join(' ') || val;
    input.value = formatted;
}

function handleExpiryInput(input) {
    clearCardError();
    let val = input.value.replace(/\D/g, '').substring(0, 4);
    if (val.length >= 2) {
        val = val.substring(0, 2) + '/' + val.substring(2);
    }
    input.value = val;
}

function startCardPaymentFlow() {
    clearCardError();
    const amt = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    if (amt < 100) {
        alert("Please enter a funding amount of at least ₦100.");
        document.getElementById('fundingAmountInput').focus();
        return;
    }

    const cardNum = document.getElementById('cardInputNumber').value.replace(/\s+/g, '').trim();
    const expiry = document.getElementById('cardInputExpiry').value.trim();
    const cvv = document.getElementById('cardInputCvv').value.trim();
    const pin = document.getElementById('cardInputPin').value.trim();

    // Validate Card Number
    if (!cardNum) {
        showCardError("Please enter your card number before proceeding.", "cardInputNumber");
        return;
    }
    if (cardNum.length < 15 || cardNum.length > 19) {
        showCardError("Please enter a valid 16-digit debit/credit card number.", "cardInputNumber");
        return;
    }

    // Validate Expiry Date
    if (!expiry) {
        showCardError("Please enter your card expiry date (MM/YY).", "cardInputExpiry");
        return;
    }
    const expiryParts = expiry.split('/');
    if (expiryParts.length !== 2 || expiryParts[0].length !== 2 || expiryParts[1].length !== 2) {
        showCardError("Please enter a valid expiry format: MM/YY.", "cardInputExpiry");
        return;
    }
    const month = parseInt(expiryParts[0], 10);
    if (month < 1 || month > 12) {
        showCardError("Invalid expiry month. Enter MM between 01 and 12.", "cardInputExpiry");
        return;
    }

    // Validate CVV
    if (!cvv) {
        showCardError("Please enter your 3 or 4-digit card CVV code.", "cardInputCvv");
        return;
    }
    if (cvv.length < 3 || cvv.length > 4 || isNaN(cvv)) {
        showCardError("CVV must be 3 or 4 digits.", "cardInputCvv");
        return;
    }

    // Validate PIN
    if (!pin) {
        showCardError("Please enter your 4-digit card PIN.", "cardInputPin");
        return;
    }
    if (pin.length !== 4 || isNaN(pin)) {
        showCardError("Card PIN must be exactly 4 numeric digits.", "cardInputPin");
        return;
    }

    // All card details are present and valid -> Open 3D-Secure / OTP Authorization modal
    document.getElementById('otpModal').classList.remove('hidden');
}

function closeOtpModal() {
    document.getElementById('otpModal').classList.add('hidden');
}

function submitFinalPayment(methodName) {
    document.getElementById('otpInputState').classList.add('hidden');
    document.getElementById('otpProcessingState').classList.remove('hidden');

    const ref = 'WF-' + Math.random().toString(36).substring(2, 9).toUpperCase();
    document.getElementById('formRefInput').value = ref;
    document.getElementById('formMethodInput').value = methodName;

    setTimeout(() => {
        document.getElementById('paymentSubmitForm').submit();
    }, 1500);
}

function simulateBankTransferVerification() {
    const amt = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    if (amt < 100) {
        alert("Please select or enter an amount to transfer (min ₦100).");
        document.getElementById('fundingAmountInput').focus();
        return;
    }

    document.getElementById('transferModal').classList.remove('hidden');
    
    const ref = 'TRF-' + Math.random().toString(36).substring(2, 9).toUpperCase();
    document.getElementById('formRefInput').value = ref;
    document.getElementById('formMethodInput').value = 'Bank Transfer (Moniepoint MFB)';

    setTimeout(() => {
        document.getElementById('paymentSubmitForm').submit();
    }, 2000);
}

function simulateUssdPayment(bankName) {
    const amt = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    if (amt < 100) {
        alert("Please select or enter an amount for USSD payment (min ₦100).");
        document.getElementById('fundingAmountInput').focus();
        return;
    }

    const ref = 'USSD-' + Math.random().toString(36).substring(2, 9).toUpperCase();
    document.getElementById('formRefInput').value = ref;
    document.getElementById('formMethodInput').value = `USSD (${bankName})`;
    
    alert(`Dial string sent to ${bankName}. Processing instantaneous USSD push approval...`);
    document.getElementById('paymentSubmitForm').submit();
}

function copyText(text, btn) {
    navigator.clipboard.writeText(text);
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check text-xs text-emerald-400"></i>';
    setTimeout(() => { btn.innerHTML = original; }, 1500);
}

// Countdown timer for virtual account transfer
let timeLeft = 29 * 60 + 58;
setInterval(() => {
    if (timeLeft <= 0) return;
    timeLeft--;
    const mins = Math.floor(timeLeft / 60);
    const secs = timeLeft % 60;
    const timerEl = document.getElementById('transferTimer');
    if (timerEl) {
        timerEl.innerText = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
    }
}, 1000);
</script>

<?php include_once("../includes/footer.php"); ?>

