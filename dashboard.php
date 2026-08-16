<?php
include("config.php");

if (is_admin_logged_in()) {
    redirect("admin/dashboard.php");
} elseif (is_logged_in()) {
    redirect("user/dashboard.php");
} else {
    redirect("index.php");
}
?>