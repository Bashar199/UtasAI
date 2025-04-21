<?php

/*
 * Database Configuration
 */
define('DB_HOST', 'localhost');       // Replace with your database host (e.g., '127.0.0.1')
define('DB_USER', 'root');      // Your database username
define('DB_PASS', '');          // Your database password (leave empty for default XAMPP)
define('DB_NAME', 'dataUTAS');     // Replace with your database name

/*
 * API Configuration (Example - if needed later)
 */
// define('API_KEY', 'YOUR_API_KEY');

// Path to the schedule result file (relative to the script using it)
define('SCHEDULE_FILE_PATH', 'process/schedule_result.json');

// Optional: Set default timezone
date_default_timezone_set('Asia/Muscat'); // Example timezone

?> 