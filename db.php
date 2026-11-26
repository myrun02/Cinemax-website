<?php
$host = "cinemax.infinityfreeapp.com"; 
$user = "if0_43543695";             
$pass = "amirun02";    
$db   = "if0_43543695_cinemax";     

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>