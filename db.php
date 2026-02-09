<?php
$host = 'localhost';        // Localhost for XAMPP
$db   = 'Testing';      // Your local database name (check phpMyAdmin)
$user = 'root';             // Default XAMPP MySQL username
$pass = '';                 // Default XAMPP MySQL password (blank by default)

$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die('Database connection failed: ' . $conn->connect_error);
}
