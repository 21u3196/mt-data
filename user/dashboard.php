<?php
include_once("../config.php");

if (!is_logged_in()) {
    redirect("login.php");
}

$user = get_current_user_data();
if (!$user) {
    redirect("login.php");
}

$user_id = $user['id'];
$wallet_balance = (float)$user['wallet_balance'];
$has_face = !empty($user['face_descriptor']);

// Fetch user transactions count
$trans_count_res = mysqli_query($conn, "SELECT COUNT(*) as c FROM transactions WHERE user_id='$user_id'");
$trans_count = mysqli_fetch_assoc($trans_count_res)['c'];

$page_title = "User Dashboard";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
        

        <!-- Metric Cards Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-6">
            
            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Wallet Balance</p>
                    <h3 class="font-heading text-2xl sm:text-3xl font-extrabold text-brand-600 mt-1">
                        ₦<?php echo number_format($wallet_balance, 2); ?>
                    </h3>
                    <a href="fund_wallet.php" class="inline-flex items-center gap-1 text-xs font-bold text-emerald-600 hover:text-emerald-700 mt-2">
                        <i class="fa-solid fa-plus-circle"></i> + Top Up Digital Wallet
                    </a>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-brand-50 text-brand-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-wallet"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Total Transactions</p>
                    <h3 class="font-heading text-2xl sm:text-3xl font-extrabold text-slate-900 mt-1">
                        <?php echo number_format($trans_count); ?>
                    </h3>
                </div>
                <div class="w-12 h-12 rounded-2xl bg-emerald-50 text-emerald-600 flex items-center justify-center text-xl">
                    <i class="fa-solid fa-receipt"></i>
                </div>
            </div>

            <div class="bg-white p-6 rounded-3xl border border-slate-200/80 shadow-sm flex items-center justify-between">
                <div>
                    <p class="text-xs font-bold text-slate-500 uppercase tracking-wider">Authentication Security</p>
                    <div class="flex items-center gap-2 mt-1">
                        <span class="font-heading text-base font-bold <?php echo $has_face ? 'text-emerald-600' : 'text-amber-600'; ?>">
                            <?php echo $has_face ? '2FA Biometrics Active' : 'Basic Password Only'; ?>
                        </span>
                    </div>
                </div>
                <div class="w-12 h-12 rounded-2xl <?php echo $has_face ? 'bg-emerald-50 text-emerald-600' : 'bg-amber-50 text-amber-600'; ?> flex items-center justify-center text-xl">
                    <i class="fa-solid <?php echo $has_face ? 'fa-face-smile' : 'fa-lock'; ?>"></i>
                </div>
            </div>

        </div>

        <!-- Service Cards Grid: Buy Data, Buy Airtime, Cable TV -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            
            <!-- Buy Data Bundle -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm" id="buydata">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-wifi"></i>
                    </div>
                    <div>
                        <h3 class="font-heading text-lg font-bold text-slate-900">Buy Data Bundle</h3>
                        <p class="text-xs text-slate-500">Automated instant SME & Direct Data</p>
                    </div>
                </div>

                <form action="purchase_data.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Network</label>
                        <select name="network" id="dataNetwork" onchange="filterDataPlans()" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-brand-500 focus:outline-none" required>
                            <option value="">-- Choose Network --</option>
                            <option value="MTN">MTN Nigeria</option>
                            <option value="Airtel">Airtel Nigeria</option>
                            <option value="Glo">Glo Nigeria</option>
                            <option value="9mobile">9mobile</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Recipient Phone</label>
                        <div class="relative rounded-xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-brand-500 transition-all">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                            <input type="tel" name="phone" class="w-full pl-9 pr-3 py-2.5 rounded-xl text-sm text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="08012345678" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Data Plan Bundle</label>
                        <select name="plan" id="dataPlan" onchange="updateDataPrice()" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-brand-500 focus:outline-none" required>
                            <option value="" data-price="0">-- Select Bundle --</option>
                            <?php
                            $plans = mysqli_query($conn, "SELECT data_plans.*, networks.network_name FROM data_plans INNER JOIN networks ON data_plans.network_id = networks.id ORDER BY data_plans.network_id, data_plans.amount ASC");
                            while ($p = mysqli_fetch_assoc($plans)) {
                                echo "<option value='{$p['id']}' data-network='{$p['network_name']}' data-price='{$p['amount']}'>[{$p['network_name']}] {$p['plan_name']} - ₦" . number_format($p['amount'], 2) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <!-- Selected Data Price Badge -->
                    <div id="dataPriceCard" class="p-3 rounded-2xl bg-brand-50/80 border border-brand-200 flex items-center justify-between">
                        <span class="text-xs font-bold text-brand-800">Plan Price:</span>
                        <span id="dataPriceDisplay" class="font-heading text-sm font-extrabold text-brand-700">₦0.00</span>
                    </div>

                    <button type="submit" id="dataSubmitBtn" class="w-full mt-2 py-3 px-4 rounded-xl bg-zinc-900 hover:bg-zinc-800 text-white font-bold text-sm shadow-sm transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-bolt"></i> <span>Purchase Data</span>
                    </button>
                </form>
            </div>

            <!-- Buy Airtime -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm" id="buyairtime">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-emerald-100 text-emerald-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-mobile-screen"></i>
                    </div>
                    <div>
                        <h3 class="font-heading text-lg font-bold text-slate-900">Buy Airtime</h3>
                        <p class="text-xs text-slate-500">Instant top-up to any network</p>
                    </div>
                </div>

                <form action="purchase_airtime.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Network</label>
                        <select name="network" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-emerald-500 focus:outline-none" required>
                            <option value="MTN">MTN</option>
                            <option value="Airtel">Airtel</option>
                            <option value="Glo">Glo</option>
                            <option value="9mobile">9mobile</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Phone Number</label>
                        <div class="relative rounded-xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-emerald-500 transition-all">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-phone"></i>
                            </span>
                            <input type="tel" name="phone" class="w-full pl-9 pr-3 py-2.5 rounded-xl text-sm text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="08012345678" required>
                        </div>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Recharge Amount (₦)</label>
                        <div class="relative rounded-xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-emerald-500 transition-all">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-naira-sign"></i>
                            </span>
                            <input type="number" step="50" min="50" name="amount" id="airtimeAmountInput" oninput="updateAirtimeBtn()" class="w-full pl-9 pr-3 py-2.5 rounded-xl text-sm text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="e.g. 500" required>
                        </div>
                    </div>

                    <button type="submit" id="airtimeSubmitBtn" class="w-full mt-2 py-3 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-sm shadow-md shadow-emerald-500/20 hover:shadow-glow-emerald transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-phone-volume"></i> <span>Recharge Airtime</span>
                    </button>
                </form>
            </div>

            <!-- Cable TV -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm" id="cable">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-tv"></i>
                    </div>
                    <div>
                        <h3 class="font-heading text-lg font-bold text-slate-900">Cable Subscription</h3>
                        <p class="text-xs text-slate-500">DSTV, GOTV & Startimes decoder</p>
                    </div>
                </div>

                <form action="subscribe_cable.php" method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Provider</label>
                        <select name="provider" id="cableProvider" onchange="filterCablePlans()" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                            <option value="">-- Select Provider --</option>
                            <option value="DSTV">DSTV</option>
                            <option value="GOTV">GOTV</option>
                            <option value="Startimes">Startimes</option>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Bouquet / Package</label>
                        <select name="plan_id" id="cablePlan" onchange="updateCablePrice()" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                            <option value="" data-price="0">-- Select Package --</option>
                            <?php
                            $cable_plans = mysqli_query($conn, "SELECT cable_plans.*, cable_providers.provider_name FROM cable_plans INNER JOIN cable_providers ON cable_plans.provider_id = cable_providers.id ORDER BY cable_plans.provider_id, cable_plans.amount ASC");
                            while ($cp = mysqli_fetch_assoc($cable_plans)) {
                                echo "<option value='{$cp['id']}' data-provider='{$cp['provider_name']}' data-price='{$cp['amount']}'>[{$cp['provider_name']}] {$cp['plan_name']} - ₦" . number_format($cp['amount'], 2) . "</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Smartcard / IUC Number</label>
                        <div class="relative rounded-xl border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-amber-500 transition-all">
                            <span class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-slate-400 text-xs">
                                <i class="fa-solid fa-credit-card"></i>
                            </span>
                            <input type="text" name="smartcard" class="w-full pl-9 pr-3 py-2.5 rounded-xl text-sm text-slate-900 placeholder-slate-400 bg-transparent border-0 focus:outline-none focus:ring-0" placeholder="Enter smartcard or IUC" required>
                        </div>
                    </div>

                    <!-- Selected Service Price Badge -->
                    <div id="cablePriceCard" class="p-3 rounded-2xl bg-amber-50/80 border border-amber-200 flex items-center justify-between">
                        <span class="text-xs font-bold text-amber-800">Package Price:</span>
                        <span id="cablePriceDisplay" class="font-heading text-sm font-extrabold text-amber-700">₦0.00</span>
                    </div>

                    <button type="submit" id="cableSubmitBtn" class="w-full mt-2 py-3 px-4 rounded-xl bg-amber-600 hover:bg-amber-500 text-white font-bold text-sm shadow-md shadow-amber-500/20 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-satellite-dish"></i> <span>Renew Subscription</span>
                    </button>
                </form>
            </div>

        </div>

        <!-- Recent Transaction History Table -->
        <!-- Transactions Section (Responsive Table on Desktop & Native Cards on Mobile) -->
        <div class="bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden" id="history">
            <div class="p-6 border-b border-slate-100 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3">
                    <div class="w-9 h-9 rounded-xl bg-brand-50 text-brand-600 flex items-center justify-center text-sm">
                        <i class="fa-solid fa-receipt"></i>
                    </div>
                    <div>
                        <h3 class="font-heading text-base font-bold text-slate-900">Transactions</h3>
                        <p class="text-xs text-slate-500">Recent purchases and digital wallet top-up transactions</p>
                    </div>
                </div>
            </div>

            <!-- Mobile View: Modern Transaction Cards (Visible on screens < 768px) -->
            <div class="block md:hidden p-4 space-y-3 bg-slate-50/50">
                <?php
                $hist_query_mobile = mysqli_query($conn, "SELECT * FROM transactions WHERE user_id='$user_id' ORDER BY id DESC LIMIT 15");
                if (mysqli_num_rows($hist_query_mobile) > 0):
                    while ($tx = mysqli_fetch_assoc($hist_query_mobile)):
                        $badge_class = $tx['service_type'] == 'Data' 
                            ? 'bg-brand-50 text-brand-700 border-brand-200' 
                            : ($tx['service_type'] == 'Airtime' 
                                ? 'bg-emerald-50 text-emerald-700 border-emerald-200' 
                                : 'bg-amber-50 text-amber-700 border-amber-200');
                ?>
                    <div class="p-4 rounded-2xl bg-white border border-slate-200/90 shadow-xs space-y-2.5">
                        <div class="flex items-center justify-between">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold border <?php echo $badge_class; ?>">
                                <?php echo htmlspecialchars($tx['service_type']); ?>
                            </span>
                            <span class="font-heading font-extrabold text-sm text-slate-900">
                                ₦<?php echo number_format($tx['amount'], 2); ?>
                            </span>
                        </div>

                        <div>
                            <p class="text-xs font-bold text-slate-900"><?php echo htmlspecialchars($tx['description']); ?></p>
                            <?php if (!empty($tx['phone_number'])): ?>
                                <p class="text-[11px] text-slate-500 flex items-center gap-1.5 mt-0.5">
                                    <i class="fa-solid fa-phone text-[10px] text-slate-400"></i>
                                    <span><?php echo htmlspecialchars($tx['phone_number']); ?></span>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between text-[11px] text-slate-500">
                            <span class="font-mono text-slate-400">#TX-<?php echo str_pad($tx['id'], 5, '0', STR_PAD_LEFT); ?></span>
                            <span class="inline-flex items-center gap-1 text-emerald-600 font-bold">
                                <i class="fa-solid fa-circle-check text-[10px]"></i> Success
                            </span>
                            <span><?php echo date('M d, H:i', strtotime($tx['transaction_date'])); ?></span>
                        </div>
                    </div>
                <?php
                    endwhile;
                else:
                ?>
                    <div class="p-8 text-center text-slate-400 bg-white rounded-2xl border border-slate-200">
                        <i class="fa-regular fa-folder-open text-2xl mb-2 block text-slate-300"></i>
                        <p class="text-xs font-semibold">No transactions recorded yet.</p>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Desktop View: Clean High-Contrast Table (Visible on screens >= 768px) -->
            <div class="hidden md:block overflow-x-auto">
                <table class="w-full text-left text-sm">
                    <thead class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                        <tr>
                            <th class="px-6 py-4">Ref #</th>
                            <th class="px-6 py-4">Service</th>
                            <th class="px-6 py-4">Description</th>
                            <th class="px-6 py-4">Recipient</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">Timestamp</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        <?php
                        $hist_query = mysqli_query($conn, "SELECT * FROM transactions WHERE user_id='$user_id' ORDER BY id DESC LIMIT 15");
                        if (mysqli_num_rows($hist_query) > 0):
                            while ($tx = mysqli_fetch_assoc($hist_query)):
                        ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="px-6 py-4 font-bold text-slate-900 font-mono">#TX-<?php echo str_pad($tx['id'], 5, '0', STR_PAD_LEFT); ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold <?php echo $tx['service_type'] == 'Data' ? 'bg-brand-50 text-brand-700 border border-brand-200' : ($tx['service_type'] == 'Airtime' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200'); ?>">
                                        <?php echo htmlspecialchars($tx['service_type']); ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4 font-semibold text-slate-800"><?php echo htmlspecialchars($tx['description']); ?></td>
                                <td class="px-6 py-4 text-slate-600 font-mono text-xs"><?php echo htmlspecialchars($tx['phone_number']); ?></td>
                                <td class="px-6 py-4 font-bold text-slate-900">₦<?php echo number_format($tx['amount'], 2); ?></td>
                                <td class="px-6 py-4">
                                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <i class="fa-solid fa-check text-[10px]"></i> Success
                                    </span>
                                </td>
                                <td class="px-6 py-4 text-xs text-slate-500"><?php echo date('M d, Y H:i', strtotime($tx['transaction_date'])); ?></td>
                            </tr>
                        <?php
                            endwhile;
                        else:
                        ?>
                            <tr>
                                <td colspan="7" class="px-6 py-12 text-center text-slate-400">
                                    <i class="fa-regular fa-folder-open text-3xl mb-2 block text-slate-300"></i>
                                    No transactions recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

    </div>
</div>

<script>
// Cache all original options
const allCableOptions = Array.from(document.querySelectorAll('#cablePlan option'));
const allDataOptions = Array.from(document.querySelectorAll('#dataPlan option'));

function filterCablePlans() {
    const provider = document.getElementById('cableProvider').value;
    const planSelect = document.getElementById('cablePlan');
    
    planSelect.innerHTML = '';
    
    // Add default option
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.setAttribute('data-price', '0');
    defaultOpt.textContent = provider ? `-- Select ${provider} Package --` : '-- Select Package --';
    planSelect.appendChild(defaultOpt);
    
    allCableOptions.forEach(opt => {
        if (!opt.value) return;
        const optProvider = opt.getAttribute('data-provider');
        if (!provider || optProvider === provider) {
            planSelect.appendChild(opt.cloneNode(true));
        }
    });

    updateCablePrice();
}

function updateCablePrice() {
    const planSelect = document.getElementById('cablePlan');
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    const price = parseFloat(selectedOption ? selectedOption.getAttribute('data-price') : 0) || 0;
    
    const formatted = '₦' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('cablePriceDisplay').innerText = formatted;
    
    const btn = document.getElementById('cableSubmitBtn');
    if (btn) {
        btn.querySelector('span').innerText = price > 0 ? `Renew Subscription (${formatted})` : 'Renew Subscription';
    }
}

function filterDataPlans() {
    const network = document.getElementById('dataNetwork').value;
    const planSelect = document.getElementById('dataPlan');
    
    planSelect.innerHTML = '';
    
    const defaultOpt = document.createElement('option');
    defaultOpt.value = '';
    defaultOpt.setAttribute('data-price', '0');
    defaultOpt.textContent = network ? `-- Select ${network} Bundle --` : '-- Select Bundle --';
    planSelect.appendChild(defaultOpt);
    
    allDataOptions.forEach(opt => {
        if (!opt.value) return;
        const optNetwork = opt.getAttribute('data-network');
        if (!network || optNetwork === network) {
            planSelect.appendChild(opt.cloneNode(true));
        }
    });

    updateDataPrice();
}

function updateDataPrice() {
    const planSelect = document.getElementById('dataPlan');
    const selectedOption = planSelect.options[planSelect.selectedIndex];
    const price = parseFloat(selectedOption ? selectedOption.getAttribute('data-price') : 0) || 0;
    
    const formatted = '₦' + price.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById('dataPriceDisplay').innerText = formatted;
    
    const btn = document.getElementById('dataSubmitBtn');
    if (btn) {
        btn.querySelector('span').innerText = price > 0 ? `Purchase Data (${formatted})` : 'Purchase Data';
    }
}

function updateAirtimeBtn() {
    const val = parseFloat(document.getElementById('airtimeAmountInput').value) || 0;
    const btn = document.getElementById('airtimeSubmitBtn');
    if (btn) {
        const formatted = '₦' + val.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        btn.querySelector('span').innerText = val > 0 ? `Recharge Airtime (${formatted})` : 'Recharge Airtime';
    }
}
</script>

<?php include_once("../includes/footer.php"); ?>