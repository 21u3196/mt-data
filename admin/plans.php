<?php
include_once("../config.php");

if (!is_admin_logged_in()) {
    redirect("login.php");
}

$message = "";

if (isset($_POST['add_plan'])) {
    $network_id = (int)$_POST['network_id'];
    $plan_name  = clean_input($_POST['plan_name']);
    $amount     = (float)$_POST['amount'];

    if ($network_id > 0 && !empty($plan_name) && $amount > 0) {
        $stmt = mysqli_prepare($conn, "INSERT INTO data_plans (network_id, plan_name, amount) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "isd", $network_id, $plan_name, $amount);
        if (mysqli_stmt_execute($stmt)) {
            $message = "<div class='mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-circle-check text-lg'></i> Plan '{$plan_name}' added successfully!</div>";
        } else {
            $message = "<div class='mb-6 p-4 rounded-2xl bg-red-50 border border-red-200 text-red-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-triangle-exclamation text-lg'></i> Failed to add plan.</div>";
        }
        mysqli_stmt_close($stmt);
    }
}

$page_title = "Data Plans Catalog";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 py-8 px-4 sm:px-6 lg:px-8">
    <div class="max-w-7xl mx-auto space-y-8">
        
        <div>
            <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Data Bundle Catalog</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Configure automated mobile data packages and consumer pricing</p>
        </div>

        <?php echo $message; ?>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8 items-start">
            
            <!-- Add Plan Form -->
            <div class="bg-white rounded-3xl p-6 sm:p-8 border border-slate-200/80 shadow-sm">
                <div class="flex items-center gap-3 mb-6">
                    <div class="w-10 h-10 rounded-xl bg-brand-100 text-brand-600 flex items-center justify-center text-lg">
                        <i class="fa-solid fa-plus"></i>
                    </div>
                    <div>
                        <h3 class="font-heading text-lg font-bold text-slate-900">Add New Plan</h3>
                        <p class="text-xs text-slate-500">Create bundle package</p>
                    </div>
                </div>

                <form method="POST" class="space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Network</label>
                        <select name="network_id" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 bg-white text-slate-800 focus:ring-2 focus:ring-brand-500 focus:outline-none" required>
                            <?php
                            $networks = mysqli_query($conn, "SELECT * FROM networks ORDER BY id ASC");
                            while ($n = mysqli_fetch_assoc($networks)) {
                                echo "<option value='{$n['id']}'>{$n['network_name']}</option>";
                            }
                            ?>
                        </select>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Bundle / Plan Name</label>
                        <input type="text" name="plan_name" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-brand-500 focus:outline-none" placeholder="e.g. 2.5GB SME (30 Days)" required>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Price (₦)</label>
                        <input type="number" step="0.01" min="10" name="amount" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 text-slate-900 placeholder-slate-400 focus:ring-2 focus:ring-brand-500 focus:outline-none" placeholder="e.g. 650.00" required>
                    </div>

                    <button type="submit" name="add_plan" class="w-full mt-2 py-3 px-4 rounded-xl bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-500 hover:to-accent-500 text-white font-bold text-sm shadow-md shadow-brand-500/20 transition-all flex items-center justify-center gap-2">
                        <i class="fa-solid fa-cloud-arrow-up"></i> Save Bundle
                    </button>
                </form>
            </div>

            <!-- Active Plans Table -->
            <div class="lg:col-span-2 bg-white rounded-3xl border border-slate-200/80 shadow-sm overflow-hidden">
                <div class="p-6 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-slate-100 text-slate-700 flex items-center justify-center text-sm">
                            <i class="fa-solid fa-database"></i>
                        </div>
                        <div>
                            <h3 class="font-heading text-base font-bold text-slate-900">Active Data Bundles</h3>
                            <p class="text-xs text-slate-500">Live bundle catalog across networks</p>
                        </div>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-slate-50 border-b border-slate-200/80 text-xs font-bold text-slate-600 uppercase tracking-wider">
                            <tr>
                                <th class="px-6 py-4">ID</th>
                                <th class="px-6 py-4">Network</th>
                                <th class="px-6 py-4">Bundle Name</th>
                                <th class="px-6 py-4">Price</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <?php
                            $plans_list = mysqli_query($conn, "SELECT data_plans.*, networks.network_name FROM data_plans INNER JOIN networks ON data_plans.network_id = networks.id ORDER BY data_plans.network_id, data_plans.amount ASC");
                            while ($pl = mysqli_fetch_assoc($plans_list)):
                            ?>
                                <tr class="hover:bg-slate-50/80 transition-colors">
                                    <td class="px-6 py-4 font-bold text-slate-900">#<?php echo $pl['id']; ?></td>
                                    <td class="px-6 py-4">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-bold bg-brand-50 text-brand-700 border border-brand-200">
                                            <?php echo htmlspecialchars($pl['network_name']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 font-semibold text-slate-800"><?php echo htmlspecialchars($pl['plan_name']); ?></td>
                                    <td class="px-6 py-4 font-extrabold text-brand-600 text-sm">₦<?php echo number_format($pl['amount'], 2); ?></td>
                                    <td class="px-6 py-4">
                                        <div class="flex items-center gap-2">
                                            <a href="edit_plan.php?id=<?php echo $pl['id']; ?>" class="px-2.5 py-1 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold transition-colors">
                                                <i class="fa-solid fa-pen-to-square"></i> Edit
                                            </a>
                                            <a href="delete_plan.php?id=<?php echo $pl['id']; ?>" onclick="return confirm('Delete this plan?')" class="px-2.5 py-1 rounded-lg bg-red-50 hover:bg-red-100 text-red-600 text-xs font-bold transition-colors">
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