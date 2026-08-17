<?php
include_once("../config.php");

if (!is_logged_in()) {
    redirect("login.php");
}

$user = get_current_user_data();
$user_id = (int)$user['id'];
$wallet_balance = (float)$user['wallet_balance'];

// Process Funding Submission
$error_message = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['process_funding'])) {
    $amount = (float)($_POST['amount'] ?? 0);
    $payment_method = clean_input($_POST['payment_method'] ?? 'Card Payment');
    $custom_ref = clean_input($_POST['payment_reference'] ?? ('WF-' . strtoupper(bin2hex(random_bytes(4)))));

    if ($amount < 100) {
        $error_message = "Minimum funding amount is ₦100.00.";
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

            $old_bal = (float)($u_data['wallet_balance'] ?? 0);
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

            // Dispatch notification safely without blocking or hanging on slow network
            $ack_info = [];
            try {
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
            } catch (Throwable $notifErr) {
                // Non-fatal notification error
            }

            // Store receipt in session and redirect to receipt
            $_SESSION['last_funding'] = [
                'id'             => $funding_id,
                'reference'      => $custom_ref,
                'amount'         => $amount,
                'payment_method' => $payment_method,
                'old_balance'    => $old_bal,
                'new_balance'    => $new_bal,
                'date'           => date('Y-m-d H:i:s'),
                'sms_message'    => $ack_info['sms_message'] ?? '',
                'email_sent'     => $ack_info['email_sent'] ?? false
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

<div class="flex-1 py-4 sm:py-6 px-3 sm:px-6 relative">
    <div class="max-w-xl mx-auto space-y-4">
        
        <!-- Header & Balance Banner -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-sm flex items-center justify-between gap-3">
            <div>
                <h1 class="font-heading text-lg sm:text-xl font-bold text-slate-900">Fund Wallet</h1>
                <p class="text-xs text-slate-500">Add funds via Card, Transfer, or USSD</p>
            </div>
            
            <div class="text-right">
                <span class="text-[11px] font-medium text-slate-400 block">Balance</span>
                <span class="font-heading font-extrabold text-slate-900 text-base sm:text-lg">₦<?php echo number_format($wallet_balance, 2); ?></span>
            </div>
        </div>

        <?php if (!empty($error_message)): ?>
            <div class="p-3.5 rounded-xl bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                <i class="fa-solid fa-circle-exclamation text-sm flex-shrink-0"></i>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Funding Form Card -->
        <div class="bg-white rounded-2xl p-4 sm:p-5 border border-slate-200 shadow-sm space-y-5">
            
            <!-- Amount Section -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">1. Select or Enter Amount</label>
                
                <!-- Quick Amount Pills -->
                <div class="grid grid-cols-3 gap-2 mb-3">
                    <?php $presets = [1000, 2000, 5000, 10000, 20000, 50000]; foreach ($presets as $p): ?>
                        <button type="button" onclick="setFundingAmount(<?php echo $p; ?>)" class="preset-btn py-2 px-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:border-zinc-800 hover:bg-slate-50 transition-all text-center" data-val="<?php echo $p; ?>">
                            ₦<?php echo number_format($p); ?>
                        </button>
                    <?php endforeach; ?>
                </div>

                <!-- Custom Amount Input -->
                <div class="relative rounded-xl border border-slate-300 bg-white focus-within:border-zinc-900 focus-within:ring-1 focus-within:ring-zinc-900 transition-all">
                    <span class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-500 font-extrabold text-sm">
                        ₦
                    </span>
                    <input type="number" id="fundingAmountInput" step="50" min="100" max="1000000" value="2000" oninput="updatePayableSummary()" class="w-full pl-8 pr-3.5 py-2.5 rounded-xl text-base font-bold text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="Enter amount (min ₦100)">
                </div>
            </div>

            <!-- Payment Channel Selector -->
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-2">2. Choose Payment Channel</label>
                
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" id="tabCard" onclick="selectChannel('card')" class="channel-tab py-2.5 px-2 rounded-xl text-xs font-bold border-2 border-zinc-900 bg-zinc-900 text-white transition-all text-center flex flex-col items-center gap-1">
                        <i class="fa-regular fa-credit-card text-sm"></i>
                        <span>Card</span>
                    </button>

                    <button type="button" id="tabTransfer" onclick="selectChannel('transfer')" class="channel-tab py-2.5 px-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:bg-slate-50 transition-all text-center flex flex-col items-center gap-1">
                        <i class="fa-solid fa-building-columns text-sm"></i>
                        <span>Transfer</span>
                    </button>

                    <button type="button" id="tabUssd" onclick="selectChannel('ussd')" class="channel-tab py-2.5 px-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:bg-slate-50 transition-all text-center flex flex-col items-center gap-1">
                        <i class="fa-solid fa-hashtag text-sm"></i>
                        <span>USSD</span>
                    </button>
                </div>
            </div>

            <!-- CHANNEL 1: CARD FORM -->
            <div id="channelCard" class="space-y-3 pt-2">
                <!-- Card Validation Alert -->
                <div id="cardValidationError" class="hidden p-2.5 rounded-lg bg-red-50 border border-red-200 text-red-700 text-xs font-semibold flex items-center gap-2">
                    <i class="fa-solid fa-circle-exclamation text-xs flex-shrink-0"></i>
                    <span id="cardValidationMsg">Please complete all card details.</span>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Card Number</label>
                    <div class="relative rounded-xl border border-slate-300 bg-white focus-within:border-zinc-900 transition-all">
                        <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                            <i class="fa-solid fa-credit-card"></i>
                        </span>
                        <input type="text" id="cardInputNumber" oninput="handleCardNumberInput(this)" maxlength="19" class="w-full pl-8 pr-3 py-2.5 rounded-xl text-xs font-mono text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none" placeholder="Card Number (e.g. 5399 4100 1234 5678)" value="5399 4100 1234 5678">
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">Expiry Date</label>
                        <input type="text" id="cardInputExpiry" oninput="handleExpiryInput(this)" maxlength="5" class="w-full px-3 py-2.5 rounded-xl text-xs font-mono border border-slate-300 text-slate-900 placeholder-slate-400 bg-white focus:border-zinc-900 focus:outline-none" placeholder="MM/YY" value="12/28">
                    </div>
                    <div>
                        <label class="block text-[11px] font-semibold text-slate-600 mb-1">CVV</label>
                        <input type="password" id="cardInputCvv" oninput="clearCardError()" maxlength="4" class="w-full px-3 py-2.5 rounded-xl text-xs font-mono border border-slate-300 text-slate-900 placeholder-slate-400 bg-white focus:border-zinc-900 focus:outline-none" placeholder="CVV" value="882">
                    </div>
                </div>

                <div>
                    <label class="block text-[11px] font-semibold text-slate-600 mb-1">Card PIN</label>
                    <input type="password" id="cardInputPin" oninput="clearCardError()" maxlength="4" class="w-full px-3 py-2.5 rounded-xl text-xs font-mono border border-slate-300 text-slate-900 placeholder-slate-400 bg-white focus:border-zinc-900 focus:outline-none" placeholder="4-Digit PIN" value="1234">
                </div>

                <!-- Pay Button -->
                <button type="button" id="payNowBtn" onclick="submitCardPaymentDirectly()" class="w-full py-3 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2 mt-2">
                    <i class="fa-solid fa-lock text-xs"></i>
                    <span>Pay Now ₦<span class="pay-amount-label">2,000.00</span></span>
                </button>
            </div>

            <!-- CHANNEL 2: BANK TRANSFER -->
            <div id="channelTransfer" style="display: none;" class="space-y-3 pt-2">
                <div class="p-4 rounded-xl bg-slate-900 text-white space-y-3">
                    <div class="flex items-center justify-between text-xs pb-2 border-b border-slate-800">
                        <span class="text-emerald-400 font-bold"><i class="fa-solid fa-bolt mr-1"></i> Dedicated Virtual Account</span>
                        <span class="text-slate-400 font-mono text-[11px]" id="transferTimer">29:58</span>
                    </div>

                    <div class="space-y-2 text-xs">
                        <div class="flex justify-between">
                            <span class="text-slate-400">Bank:</span>
                            <span class="font-bold text-white">Moniepoint MFB</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-slate-400">Account No:</span>
                            <div class="flex items-center gap-1.5">
                                <span class="font-mono text-sm font-extrabold text-emerald-400"><?php echo $virtual_account_num; ?></span>
                                <button type="button" onclick="copyText('<?php echo $virtual_account_num; ?>', this)" class="p-1 rounded bg-slate-800 hover:bg-slate-700 text-slate-300 transition-colors" title="Copy">
                                    <i class="fa-regular fa-copy text-xs"></i>
                                </button>
                            </div>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-400">Account Name:</span>
                            <span class="font-semibold text-white truncate max-w-[180px]">MT Data / <?php echo htmlspecialchars($user['fullname']); ?></span>
                        </div>
                    </div>
                </div>

                <button type="button" onclick="submitTransferPaymentDirectly()" class="w-full py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                    <i class="fa-solid fa-circle-check text-xs"></i>
                    <span>I Have Sent ₦<span class="pay-amount-label">2,000.00</span></span>
                </button>
            </div>

            <!-- CHANNEL 3: USSD -->
            <div id="channelUssd" style="display: none;" class="space-y-2 pt-2">
                <p class="text-xs text-slate-500 mb-2">Tap any bank below to simulate instantaneous USSD authorization:</p>
                
                <?php
                $ussd_banks = [
                    ['GTBank', '*737*2*'],
                    ['Zenith Bank', '*966*'],
                    ['Access Bank', '*901*'],
                    ['UBA', '*919*'],
                    ['First Bank', '*894*'],
                    ['Kuda Bank', '*894*']
                ];
                foreach ($ussd_banks as $ub):
                ?>
                    <div class="p-2.5 rounded-xl bg-slate-50 border border-slate-200 flex items-center justify-between">
                        <div>
                            <div class="font-bold text-xs text-slate-900"><?php echo $ub[0]; ?></div>
                            <div class="font-mono text-[11px] text-slate-500">
                                <?php echo $ub[1]; ?><span class="pay-amount-label-clean">2000</span>*<?php echo $virtual_account_num; ?>#
                            </div>
                        </div>
                        <button type="button" onclick="submitUssdPaymentDirectly('<?php echo $ub[0]; ?>')" class="px-2.5 py-1.5 rounded-lg bg-zinc-900 hover:bg-zinc-800 text-[11px] font-bold text-white transition-colors">
                            Pay Now
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>
</div>

<!-- Hidden Form for Backend Execution -->
<form id="paymentSubmitForm" method="POST" style="display: none;">
    <input type="hidden" name="process_funding" value="1">
    <input type="hidden" name="amount" id="formAmountInput" value="2000">
    <input type="hidden" name="payment_method" id="formMethodInput" value="Card Payment">
    <input type="hidden" name="payment_reference" id="formRefInput" value="">
</form>

<!-- Full-Screen Smooth Loading Overlay during Payment -->
<div id="paymentLoadingOverlay" class="hidden fixed inset-0 z-50 bg-slate-950/70 backdrop-blur-sm flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl p-6 max-w-xs w-full text-center space-y-3 shadow-2xl border border-slate-100">
        <div class="w-12 h-12 rounded-full border-4 border-slate-200 border-t-zinc-900 animate-spin mx-auto"></div>
        <h3 class="font-heading text-base font-bold text-slate-900">Processing Payment...</h3>
        <p class="text-xs text-slate-500">Crediting ₦<span class="pay-amount-label">2,000.00</span> to your MT Data wallet.</p>
    </div>
</div>

<script>
let currentChannel = 'card';

// Initialize amount
document.addEventListener('DOMContentLoaded', () => {
    updatePayableSummary();
    setFundingAmount(2000);
});

function setFundingAmount(amt) {
    document.getElementById('fundingAmountInput').value = amt;
    updatePayableSummary();
    
    // Highlight active preset
    document.querySelectorAll('.preset-btn').forEach(btn => {
        if (parseInt(btn.getAttribute('data-val')) === amt) {
            btn.className = "preset-btn py-2 px-2 rounded-xl text-xs font-bold border-2 border-zinc-900 bg-zinc-900 text-white transition-all text-center";
        } else {
            btn.className = "preset-btn py-2 px-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:border-zinc-800 hover:bg-slate-50 transition-all text-center";
        }
    });
}

function updatePayableSummary() {
    const val = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    const formatted = val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    
    document.querySelectorAll('.pay-amount-label').forEach(el => {
        el.innerText = formatted;
    });

    document.querySelectorAll('.pay-amount-label-clean').forEach(el => {
        el.innerText = Math.round(val).toString();
    });

    document.getElementById('formAmountInput').value = val;
}

function selectChannel(channel) {
    currentChannel = channel;
    
    const tabCard = document.getElementById('tabCard');
    const tabTransfer = document.getElementById('tabTransfer');
    const tabUssd = document.getElementById('tabUssd');

    const contentCard = document.getElementById('channelCard');
    const contentTransfer = document.getElementById('channelTransfer');
    const contentUssd = document.getElementById('channelUssd');

    // Reset styles
    [tabCard, tabTransfer, tabUssd].forEach(tab => {
        tab.className = "channel-tab py-2.5 px-2 rounded-xl text-xs font-bold border border-slate-200 text-slate-700 hover:bg-slate-50 transition-all text-center flex flex-col items-center gap-1";
    });

    // Hide all
    contentCard.style.display = 'none';
    contentTransfer.style.display = 'none';
    contentUssd.style.display = 'none';

    if (channel === 'card') {
        tabCard.className = "channel-tab py-2.5 px-2 rounded-xl text-xs font-bold border-2 border-zinc-900 bg-zinc-900 text-white transition-all text-center flex flex-col items-center gap-1";
        contentCard.style.display = 'block';
    } else if (channel === 'transfer') {
        tabTransfer.className = "channel-tab py-2.5 px-2 rounded-xl text-xs font-bold border-2 border-zinc-900 bg-zinc-900 text-white transition-all text-center flex flex-col items-center gap-1";
        contentTransfer.style.display = 'block';
    } else if (channel === 'ussd') {
        tabUssd.className = "channel-tab py-2.5 px-2 rounded-xl text-xs font-bold border-2 border-zinc-900 bg-zinc-900 text-white transition-all text-center flex flex-col items-center gap-1";
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

function submitCardPaymentDirectly() {
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

    if (!cardNum || cardNum.length < 15) {
        showCardError("Please enter a valid card number.", "cardInputNumber");
        return;
    }
    if (!expiry || expiry.length < 4) {
        showCardError("Please enter valid card expiry (MM/YY).", "cardInputExpiry");
        return;
    }
    if (!cvv || cvv.length < 3) {
        showCardError("Please enter your card CVV.", "cardInputCvv");
        return;
    }
    if (!pin || pin.length < 4) {
        showCardError("Please enter your 4-digit card PIN.", "cardInputPin");
        return;
    }

    // Show smooth overlay
    document.getElementById('paymentLoadingOverlay').classList.remove('hidden');

    const ref = 'WF-' + Math.random().toString(36).substring(2, 9).toUpperCase();
    document.getElementById('formRefInput').value = ref;
    document.getElementById('formMethodInput').value = 'Card Payment';
    document.getElementById('formAmountInput').value = amt;

    setTimeout(() => {
        document.getElementById('paymentSubmitForm').submit();
    }, 800);
}

function submitTransferPaymentDirectly() {
    const amt = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    if (amt < 100) {
        alert("Please enter a funding amount of at least ₦100.");
        document.getElementById('fundingAmountInput').focus();
        return;
    }

    document.getElementById('paymentLoadingOverlay').classList.remove('hidden');
    
    const ref = 'TRF-' + Math.random().toString(36).substring(2, 9).toUpperCase();
    document.getElementById('formRefInput').value = ref;
    document.getElementById('formMethodInput').value = 'Bank Transfer (Moniepoint MFB)';
    document.getElementById('formAmountInput').value = amt;

    setTimeout(() => {
        document.getElementById('paymentSubmitForm').submit();
    }, 800);
}

function submitUssdPaymentDirectly(bankName) {
    const amt = parseFloat(document.getElementById('fundingAmountInput').value) || 0;
    if (amt < 100) {
        alert("Please enter a funding amount of at least ₦100.");
        document.getElementById('fundingAmountInput').focus();
        return;
    }

    document.getElementById('paymentLoadingOverlay').classList.remove('hidden');

    const ref = 'USSD-' + Math.random().toString(36).substring(2, 9).toUpperCase();
    document.getElementById('formRefInput').value = ref;
    document.getElementById('formMethodInput').value = `USSD (${bankName})`;
    document.getElementById('formAmountInput').value = amt;

    setTimeout(() => {
        document.getElementById('paymentSubmitForm').submit();
    }, 800);
}

function copyText(text, btn) {
    navigator.clipboard.writeText(text);
    const original = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-check text-xs text-emerald-400"></i>';
    setTimeout(() => { btn.innerHTML = original; }, 1500);
}

// Countdown timer
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
