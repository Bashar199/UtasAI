<?php
// Include database configuration
$db_config = [
    'host' => 'localhost',
    'user' => 'root',
    'password' => '',
    'database' => 'dataUTAS'
];

// Function to get student information
function getStudentInfo($conn, $student_id) {
    $stmt = $conn->prepare("SELECT student_id, academic_level FROM Students WHERE student_id = ?");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        return $result->fetch_assoc();
    }
    return null;
}

// Function to get student's enrolled courses
function getStudentCourses($conn, $student_id) {
    $stmt = $conn->prepare("
        SELECT ce.course_code, c.course_name, c.academic_level
        FROM CourseEnrollments ce
        JOIN Courses c ON ce.course_code = c.course_code
        WHERE ce.student_id = ?
        ORDER BY ce.course_code
    ");
    $stmt->bind_param("s", $student_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $courses = [];
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
    return $courses;
}

// Function to get exam schedule from JSON file
function getExamSchedule() {
    $schedule_file = 'process/schedule_result.json';
    if (!file_exists($schedule_file)) {
        return ['error' => 'Exam schedule file not found. Please generate a schedule first.'];
    }
    
    $schedule_data = json_decode(file_get_contents($schedule_file), true);
    if (!isset($schedule_data['suggestion'])) {
        return ['error' => 'No valid exam schedule found. Please generate a schedule first.'];
    }
    
    $suggestion = $schedule_data['suggestion'];
    $exam_schedule = [];
    
    // Parse the schedule text
    $lines = explode("\n", $suggestion);
    foreach ($lines as $line) {
        if (strpos($line, ':') !== false) {
            $parts = explode(':', $line, 2);
            if (count($parts) == 2) {
                $date_part = trim($parts[0]);
                // Remove numbering if present
                if (strpos($date_part, '.') !== false) {
                    $date_part = trim(explode('.', $date_part, 2)[1]);
                }
                
                $course_part = trim($parts[1]);
                // Only add actual exam dates (not study days)
                if ($course_part != "Study Day") {
                    $exam_schedule[$course_part] = $date_part;
                }
            }
        }
    }
    
    return $exam_schedule;
}

// Process search from either POST or GET
$search_error = '';
$student = null;
$student_exams = [];
$student_id = '';

// Check for GET parameter (from index.php redirect)
if (isset($_GET['student_id']) && !empty($_GET['student_id'])) {
    $student_id = trim($_GET['student_id']);
}
// Check for POST parameter (from direct form submission)
else if (isset($_POST['search']) && !empty($_POST['student_id'])) {
    $student_id = trim($_POST['student_id']);
}

// Process the student ID if we have one
if (!empty($student_id)) {
    // Connect to database
    $conn = new mysqli($db_config['host'], $db_config['user'], $db_config['password'], $db_config['database']);
    if ($conn->connect_error) {
        $search_error = "Database connection failed: " . $conn->connect_error;
    } else {
        // Get student information
        $student = getStudentInfo($conn, $student_id);
        if (!$student) {
            $search_error = "Student with ID '$student_id' not found.";
        } else {
            // Get courses the student is enrolled in
            $courses = getStudentCourses($conn, $student_id);
            
            // Get exam schedule
            $exam_schedule = getExamSchedule();
            if (isset($exam_schedule['error'])) {
                $search_error = $exam_schedule['error'];
            } else {
                // Match enrolled courses with exam dates
                foreach ($courses as $course) {
                    $course_code = $course['course_code'];
                    if (isset($exam_schedule[$course_code])) {
                        $student_exams[] = [
                            'date' => $exam_schedule[$course_code],
                            'course_code' => $course_code,
                            'course_name' => $course['course_name'],
                            'level' => $course['academic_level']
                        ];
                    }
                }
                
                // Sort by date
                usort($student_exams, function($a, $b) {
                    return $a['date'] <=> $b['date'];
                });
            }
        }
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Exam Schedule</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background-color: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            margin-top: 0;
            text-align: center;
        }
        .search-form {
            text-align: center;
            margin-bottom: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 8px;
        }
        .search-form input[type="text"] {
            padding: 8px 12px;
            width: 200px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .search-form button {
            padding: 8px 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .error {
            color: #D8000C;
            background-color: #FFD2D2;
            padding: 10px;
            margin-bottom: 20px;
            border-radius: 4px;
        }
        .student-info {
            margin-bottom: 20px;
            padding: 10px;
            background-color: #e7f3fe;
            border-left: 6px solid #2196F3;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .no-exams {
            text-align: center;
            padding: 20px;
            background-color: #ffffd6;
            border-radius: 4px;
        }
        .back-link {
            display: inline-block;
            margin-bottom: 20px;
            color: #2196F3;
            text-decoration: none;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="index.php" class="back-link">← Back to Home</a>
        <h1>Student Exam Schedule</h1>
        
        <div class="search-form">
            <form method="post" action="">
                <input type="text" name="student_id" placeholder="Enter Student ID" required value="<?php echo htmlspecialchars($student_id); ?>">
                <button type="submit" name="search">Search</button>
            </form>
            <p><small>Example: 10d12345, 11a12345, or 12s12345</small></p>
        </div>
        
        <?php if (!empty($search_error)): ?>
            <div class="error"><?php echo $search_error; ?></div>
        <?php endif; ?>
        
        <?php if ($student): ?>
            <div class="student-info">
                <h2>Student: <?php echo htmlspecialchars($student['student_id']); ?></h2>
                <p><strong>Academic Level:</strong> <?php echo htmlspecialchars($student['academic_level']); ?></p>
            </div>
            
            <?php if (empty($student_exams)): ?>
                <div class="no-exams">
                    <p>No exams scheduled for your enrolled courses.</p>
                </div>
            <?php else: ?>
                <h3>Your Exam Schedule</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Course Code</th>
                            <th>Course Name</th>
                            <th>Level</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($student_exams as $exam): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($exam['date']); ?></td>
                                <td><?php echo htmlspecialchars($exam['course_code']); ?></td>
                                <td><?php echo htmlspecialchars($exam['course_name']); ?></td>
                                <td><?php echo htmlspecialchars($exam['level']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html> 