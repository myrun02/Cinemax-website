<?php
if ($_SERVER['HTTP_HOST'] === 'localhost' || $_SERVER['HTTP_HOST'] === '127.0.0.1') {
    // Local XAMPP Settings
    $host = "localhost";
    $user = "root";
    $pass = "";
    $db   = "cinemax_db";
} else {
    // Live InfinityFree Settings
    $host = "cinemax.infinityfreeapp.com"; 
    $user = "if0_43543695";             
    $pass = "amirun02";    
    $db   = "if0_43543695_cinemax";  
}

$conn = mysqli_connect($host, $user, $pass, $db);
if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}
?>