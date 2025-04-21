<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Basic PHP Page</title>
    <style>
        body { 
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 20px; 
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 800px;
            margin: 20px auto;
            padding: 25px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        h1 {
            color: #005a87; /* UTAS Blue */
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 10px;
            margin-top: 0;
        }
        .status {
             margin-top: 20px; 
             padding: 12px 15px;
             border-radius: 5px; 
             border: 1px solid;
             font-size: 0.95em;
        }
        .success {
             background-color: #d4edda; 
             border-color: #c3e6cb; 
             color: #155724; 
        }
        .error {
             background-color: #f8d7da; 
             border-color: #f5c6cb; 
             color: #721c24; 
        }
        .button-link {
            display: inline-block;
            margin-top: 25px;
            padding: 12px 25px;
            background-color: #005a87; /* UTAS Blue */
            color: #fff;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        .button-link:hover {
            background-color: #004165; /* Darker UTAS Blue */
            color: #fff;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Welcome to the Application Portal</h1>

    <p>This page confirms the basic setup and database connectivity.</p>

    <?php
    try {
        // Attempt to include the database connection file
        require_once 'db_connect.php';

        // If included successfully and connection is okay (no die() called in db_connect.php)
        echo '<div class="status success">Database connection configured successfully.</div>';

        // Close the connection as it's not needed further on this page
        if (isset($conn)) {
            $conn->close();
        }

    } catch (Throwable $e) {
        // Catch potential errors if db_connect.php file is missing or connection fails
        echo '<div class="status error">Database Connection Error: ' . htmlspecialchars($e->getMessage()) . ' (Check config.php and ensure MySQL is running)</div>';
        // Log the detailed error for debugging: error_log($e->getMessage());
    }
    ?>

    <a href="dashboard.php" class="button-link">View Performance Dashboard</a>
    <a href="exam_schedule.php" class="button-link" style="background-color: #ffc107; margin-left: 10px;">Set Exam Schedule</a>

</div> 

</body>
</html> 