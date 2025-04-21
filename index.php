<?php
// Include database configuration
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'database' => 'dataUTAS'
];

// Get basic statistics for the dashboard
function getDatabaseStats() {
    $stats = [
        'students' => 0,
        'courses' => 0,
        'diploma_students' => 0,
        'advanced_diploma_students' => 0,
        'bachelor_students' => 0
    ];
    
    try {
        $conn = new mysqli($GLOBALS['db_config']['host'], $GLOBALS['db_config']['user'], 
                          $GLOBALS['db_config']['password'], $GLOBALS['db_config']['database']);
        
        if ($conn->connect_error) {
            return $stats;
        }
        
        // Get total student count
        $result = $conn->query("SELECT COUNT(*) as count FROM Students");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['students'] = $row['count'];
        }
        
        // Get total course count
        $result = $conn->query("SELECT COUNT(*) as count FROM Courses");
        if ($result && $row = $result->fetch_assoc()) {
            $stats['courses'] = $row['count'];
        }
        
        // Get student counts by level
        $result = $conn->query("SELECT academic_level, COUNT(*) as count FROM Students GROUP BY academic_level");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                if ($row['academic_level'] == 'Diploma') {
                    $stats['diploma_students'] = $row['count'];
                } else if ($row['academic_level'] == 'Advanced Diploma') {
                    $stats['advanced_diploma_students'] = $row['count'];
                } else if ($row['academic_level'] == 'Bachelor') {
                    $stats['bachelor_students'] = $row['count'];
                }
            }
        }
        
        $conn->close();
    } catch (Exception $e) {
        // Silently fail
    }
    
    return $stats;
}

// Process the direct search form if submitted
$redirect = false;
if (isset($_POST['direct_search']) && !empty($_POST['student_id'])) {
    $student_id = trim($_POST['student_id']);
    // Redirect to the student schedule page with the ID
    header("Location: student_schedule.php?student_id=" . urlencode($student_id));
    exit;
}

// Get statistics
$stats = getDatabaseStats();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTAS Student Portal</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }
        
        .header {
            background-color: #005a9e;
            color: white;
            padding: 20px 0;
            text-align: center;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .header h1 {
            margin: 0;
            font-size: 2.2em;
        }
        
        .subheader {
            background-color: #0078d4;
            color: white;
            padding: 10px 0;
            text-align: center;
        }
        
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 0 20px;
        }
        
        .stats-container {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 20px;
            margin-bottom: 20px;
            flex: 1;
            min-width: 200px;
            margin: 10px;
            text-align: center;
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            color: #0078d4;
            margin: 10px 0;
        }
        
        .stat-label {
            font-size: 1.1em;
            color: #555;
        }
        
        .main-actions {
            display: flex;
            flex-wrap: wrap;
            margin: 20px 0;
        }
        
        .action-card {
            background-color: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 25px;
            margin: 10px;
            flex: 1;
            min-width: 300px;
            transition: transform 0.3s ease;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
        }
        
        .action-card h2 {
            color: #0078d4;
            margin-top: 0;
        }
        
        .search-form {
            margin-top: 20px;
        }
        
        .search-form input[type="text"] {
            padding: 10px 15px;
            width: 70%;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 1em;
        }
        
        .search-form button {
            padding: 10px 20px;
            background-color: #0078d4;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1em;
            transition: background-color 0.3s ease;
        }
        
        .search-form button:hover {
            background-color: #005a9e;
        }
        
        .btn-primary {
            display: inline-block;
            padding: 12px 24px;
            background-color: #0078d4;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            font-weight: bold;
            margin-top: 15px;
            transition: background-color 0.3s ease;
        }
        
        .btn-primary:hover {
            background-color: #005a9e;
        }
        
        .footer {
            background-color: #333;
            color: white;
            text-align: center;
            padding: 20px 0;
            margin-top: 40px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>UTAS Student Portal</h1>
    </div>
    
    <div class="subheader">
        <p>Manage your academic journey with ease</p>
    </div>
    
    <div class="container">
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['students']; ?></div>
                <div class="stat-label">Total Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['courses']; ?></div>
                <div class="stat-label">Total Courses</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['diploma_students']; ?></div>
                <div class="stat-label">Diploma Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['advanced_diploma_students']; ?></div>
                <div class="stat-label">Advanced Diploma Students</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-number"><?php echo $stats['bachelor_students']; ?></div>
                <div class="stat-label">Bachelor Students</div>
            </div>
        </div>
        
        <div class="main-actions">
            <div class="action-card">
                <h2>Find Your Exam Schedule</h2>
                <p>Enter your student ID to view your personalized exam schedule.</p>
                
                <form class="search-form" method="post" action="">
                    <input type="text" name="student_id" placeholder="Enter your Student ID (e.g., 10d12345)" required>
                    <button type="submit" name="direct_search">Search</button>
                </form>
                
                <p><small>Examples: 10d (Diploma), 11a (Advanced Diploma), 12s (Bachelor)</small></p>
                <a href="student_schedule.php" class="btn-primary">Advanced Search</a>
            </div>
            
            <div class="action-card">
                <h2>Important Information</h2>
                <p>The examination period for this semester runs from <strong>August 15, 2024</strong> to <strong>September 5, 2024</strong>.</p>
                <p>Make sure to check your schedule well in advance to prepare for your exams.</p>
                <p>If you have questions about your exam schedule, please contact the Student Services Office.</p>
                <a href="#" class="btn-primary">Exam Guidelines</a>
            </div>
        </div>
    </div>
    
    <div class="footer">
        <p>&copy; 2024 University of Technical and Applied Sciences (UTAS). All rights reserved.</p>
    </div>
</body>
</html> 