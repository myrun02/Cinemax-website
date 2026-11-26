<?php
$host = "sqlxxx.infinityfree.com"; // from InfinityFree panel
$user = "if0_xxxxxxx";             // from InfinityFree panel
$pass = "your_vpanel_password";    // from InfinityFree panel
$db   = "if0_xxxxxxx_cinemax";     // from InfinityFree panel

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>