<?php

include("../config.php");

if(!isset($_SESSION['admin_id']))
{
    header("Location: login.php");
    exit();
}

if(!isset($_GET['id']))
{
    header("Location: plans.php");
    exit();
}

$id = intval($_GET['id']);

mysqli_query(
$conn,
"DELETE FROM data_plans
WHERE id='$id'"
);

header("Location: plans.php");

exit();