<?php
include_once("../config.php");

if (!is_admin_logged_in()) {
    redirect("login.php");
}

$message = "";

if (isset($_POST['add_plan'])) {
    $provider_id = (int)$_POST['provider_id'];
    $plan_name   = clean_input($_POST['plan_name']);
    $amount      = (float)$_POST['amount'];

    if ($provider_id > 0 && !empty($plan_name) && $amount > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO cable_plans (provider_id, plan_name, amount) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isd", $provider_id, $plan_name, $amount);
        if (mysqli_stmt_execute($stmt)) {
            $message = "<div class='mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-circle-check text-lg'></i> Cable package '{$plan_name}' added successfully!</div>";
        } else {
            $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Failed to add package.</div>";
        }
        mysqli_stmt_close($stmt);
    }
}

$page_title = "Cable TV Plans";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <div>
            <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Cable TV Subscription Bouquets</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Configure DSTV, GOTV & Startimes decoder packages</p>
        </div>

        <?php echo $message; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <!-- Add Package Form -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <div>
                        <h3 class="font-heading text-lg font-bold text-slate-900">Add Bouquet</h3>
                        <p class="text-xs text-slate-500">Create decoder package</p>
                    </div>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Provider</label>
                        <select name="provider_id" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-amber-500 focus:outline-none" required>
                            <?php
                            $provs = mysqli_query($conn, "SELECT * FROM cable_providers ORDER BY id ASC");
                            while ($pr = mysqli_fetch_assoc($provs)) {
                                echo "<option value='{$pr['id']}'>{$pr['provider_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Bouquet / Package Name</label>
                        <input type="text" name="plan_name" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="e.g. DSTV Compact Plus" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Subscription Cost (₦)</label>
                        <input type="number" step="0.01" min="100" name="amount" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-amber-500 focus:outline-none" placeholder="e.g. 19800.00" required>
                    </div>

                    <button type="submit" name="add_plan" class="w-full mt-2 py-3 px-4 rounded-xl bg-gradient-to-r from-amber-600 to-amber-500 hover:from-amber-500 hover:to-amber-400 text-white font-bold text-sm shadow-md shadow-amber-500/20 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-satellite-dish"></i> Save Bouquet
                    </button>
                </form>
            </div>

            <!-- Active Packages Table -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-tv"></i>
                        </div>
                        <div>
                            <h3 class="font-heading text-base font-bold text-slate-900">Active Cable Bouquets</h3>
                            <p class="text-xs text-slate-500">Live decoder bouquet packages</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">Provider</th>
                                <th class="px-6 py-4">Package Name</th>
                                <th class="px-6 py-4">Price</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $c_plans = mysqli_query($conn, "SELECT cable_plans.*, cable_providers.provider_name FROM cable_plans INNER JOIN cable_providers ON cable_plans.provider_id = cable_providers.id ORDER BY cable_plans.provider_id, cable_plans.amount ASC");
                            while ($cp = mysqli_fetch_assoc($c_plans)):
                            ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-900">#<?php echo $cp['id']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-50 text-amber-700 border border-amber-200">
                                            <?php echo htmlspecialchars($cp['provider_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-slate-800"><?php echo htmlspecialchars($cp['plan_name']); ?></td>
                                    <td class="px-6 py-4 font-extrabold text-amber-600 text-sm">₦<?php echo number_format($cp['amount'], 2); ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <a href="edit_cable_plan.php?id=<?php echo $cp['id']; ?>" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </a>
                                            <a href="delete_cable_plan.php?id=<?php echo $cp['id']; ?>" onclick="return confirm('Delete this package?')" class="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold transition-colors">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>