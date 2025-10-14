<?php
$hostname = "localhost";
$username = "root";
$password = "";
$dbname = "smartcard"; 

$conn = mysqli_connect($hostname, $username, $password, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
}

// Set charset to utf8
mysqli_set_charset($conn, "utf8");
?>