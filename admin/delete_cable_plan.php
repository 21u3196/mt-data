<?php

include("../config.php");

if(!isset($_SESSION['admin_id']))
{
    header("Location: login.php");
    exit();
}

$id = intval($_GET['id']);

mysqli_query(
$conn,
"DELETE FROM cable_plans
WHERE id='$id'"
);

header(
"Location: cable_plans.php"
);

exit();
?>