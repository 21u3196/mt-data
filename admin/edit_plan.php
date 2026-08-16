<?php
include_once("../config.php");

if (!is_admin_logged_in()) {
    redirect("login.php");
}

if (!isset($_GET['id'])) {
    redirect("plans.php");
}

$id = (int)$_GET['id'];
$query = mysqli_query($conn, "SELECT * FROM data_plans WHERE id='$id'");

if (mysqli_num_rows($query) == 0) {
    die("Plan Not Found");
}

$plan = mysqli_fetch_assoc($query);
$message = "";

if (isset($_POST['update'])) {
    $plan_name = clean_input($_POST['plan_name']);
    $amount    = (float)$_POST['amount'];

    $stmt = mysqli_prepare($conn, "UPDATE data_plans SET plan_name = ?, amount = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "sdi", $plan_name, $amount, $id);
    if (mysqli_stmt_execute($stmt)) {
        $message = "<div class='mb-6 p-4 rounded-2xl bg-emerald-50 border border-emerald-200 text-emerald-700 text-sm font-semibold flex items-center gap-3'><i class='fa-solid fa-circle-check text-lg'></i> Plan updated successfully!</div>";
        $plan['plan_name'] = $plan_name;
        $plan['amount'] = $amount;
    }
    mysqli_stmt_close($stmt);
}

$page_title = "Edit Data Plan";
include_once("../includes/header.php");
include_once("../includes/navbar.php");
?>

<div class="flex-1 flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
    <div class="w-full max-w-md bg-white rounded-3xl p-8 sm:p-10 shadow-2xl border border-slate-100">
        
        <div class="text-center mb-8">
            <div class="w-12 h-12 rounded-2xl bg-brand-100 text-brand-600 flex items-center justify-center mx-auto text-xl shadow-md shadow-brand-500/10 mb-4">
                <i class="fa-solid fa-pen-to-square"></i>
            </div>
            <h1 class="font-heading text-2xl font-extrabold text-slate-900 tracking-tight">Edit Data Bundle</h1>
            <p class="text-xs sm:text-sm text-slate-500 mt-1">Package ID #<?php echo $plan['id']; ?></p>
        </div>

        <?php echo $message; ?>

        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Bundle / Plan Name</label>
                <input type="text" name="plan_name" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 text-slate-900 focus:ring-2 focus:ring-brand-500 focus:outline-none" value="<?php echo htmlspecialchars($plan['plan_name']); ?>" required>
            </div>

            <div>
                <label class="block text-xs font-bold text-slate-700 uppercase tracking-wider mb-1.5">Price (₦)</label>
                <input type="number" step="0.01" min="10" name="amount" class="w-full px-3.5 py-2.5 rounded-xl text-sm border border-slate-200 text-slate-900 focus:ring-2 focus:ring-brand-500 focus:outline-none" value="<?php echo $plan['amount']; ?>" required>
            </div>

            <button type="submit" name="update" class="w-full mt-2 py-3.5 px-4 rounded-xl bg-gradient-to-r from-brand-600 to-accent-600 hover:from-brand-500 hover:to-accent-500 text-white font-bold text-sm shadow-lg shadow-brand-500/25 transition-all flex items-center justify-center gap-2">
                <i class="fa-solid fa-floppy-disk"></i> Save Plan Changes
            </button>
        </form>

        <div class="mt-8 pt-6 border-t border-slate-100 text-center">
            <a href="plans.php" class="text-xs sm:text-sm font-semibold text-slate-500 hover:text-slate-900 transition-colors inline-flex items-center gap-1.5">
                <i class="fa-solid fa-arrow-left"></i> Return to Plan Catalog
            </a>
        </div>

    </div>
</div>

<?php include_once("../includes/footer.php"); ?>