<?php

require_once 'config.php';

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Optional: Set character set to utf8mb4 for better compatibility
if (!$conn->set_charset("utf8mb4")) {
    // Log error or handle failure if needed, but don't necessarily die
    // printf("Error loading character set utf8mb4: %s\n", $conn->error);
}

// The connection variable $conn is now available for use in other scripts
// You would typically include or require this file where you need database access.

// Example of how to close the connection (usually done at the end of the script that includes this file)
// $conn->close();

?> 