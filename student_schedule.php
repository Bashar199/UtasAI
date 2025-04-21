<?php
require_once 'config.php';

$student_id = null;
$student_courses = [];
$schedule_data = [];
$error_message = null;
$schedule_string = null;

// Function to safely get POST data
function get_post_var($key, $default = null) {
    return isset($_POST[$key]) ? htmlspecialchars(trim($_POST[$key])) : $default;
}

// --- 1. Load the Master Schedule --- 
$schedule_file = SCHEDULE_FILE_PATH;
if (file_exists($schedule_file)) {
    $json_content = file_get_contents($schedule_file);
    $schedule_result = json_decode($json_content, true);
    if (isset($schedule_result['suggestion'])) {
        $schedule_string = $schedule_result['suggestion'];
        // Extract schedule lines (e.g., YYYY-MM-DD: CourseCode or Study Day)
        // Regex: Matches date, colon, space, and course code (3 letters, 3 digits) OR Study Day
        preg_match_all('/(\d{4}-\d{2}-\d{2}):\s*([A-Z]{3}\d{3}|Study Day)/', $schedule_string, $matches, PREG_SET_ORDER);
        
        foreach ($matches as $match) {
            if (isset($match[1]) && isset($match[2])) {
                $schedule_data[$match[1]] = $match[2]; // Key: Date, Value: Course Code or "Study Day"
            }
        }
        // Sort by date just in case the text file isn't perfectly ordered
        ksort($schedule_data);
    } elseif (isset($schedule_result['error'])) {
        $error_message = "Error loading schedule file: " . $schedule_result['error'];
    } else {
         $error_message = "Could not parse schedule data from " . $schedule_file;
    }
} else {
    $error_message = "Schedule file not found at " . $schedule_file;
}

// --- 2. Handle Form Submission --- 
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$error_message) {
    $student_id = get_post_var('student_id');

    if (empty($student_id)) {
        $error_message = "Please enter a Student ID.";
    } else {
        // --- 3. Fetch Student's Courses from Database --- 
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }

            // Prepare statement to prevent SQL injection
            $stmt = $conn->prepare("SELECT course_code FROM CourseEnrollments WHERE student_id = ?");
            $stmt->bind_param("s", $student_id);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $student_courses[] = $row['course_code'];
                }
            } else {
                $error_message = "Student ID '{$student_id}' not found or has no enrollments.";
            }
            
            $stmt->close();
            $conn->close();

        } catch (Exception $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// --- 4. Filter Schedule for the Student --- 
$student_schedule = [];
if ($student_id && !empty($student_courses) && !empty($schedule_data)) {
    foreach ($schedule_data as $date => $item) {
        // Include if it's a course the student is enrolled in OR if it's a Study Day 
        // (assuming students might want to see study days in their schedule)
        if ($item == 'Study Day' || in_array($item, $student_courses)) {
            $student_schedule[$date] = $item;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Exam Schedule</title>
    <link rel="stylesheet" href="css/styles.css"> 
    <style>
        body {
            font-family: sans-serif;
            margin: 20px;
        }
        .container {
            max-width: 600px;
            margin: auto;
            padding: 20px;
            border: 1px solid #ccc;
            border-radius: 8px;
        }
        .error {
            color: red;
            margin-bottom: 15px;
        }
        .schedule-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        .schedule-table th, .schedule-table td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        .schedule-table th {
            background-color: #f2f2f2;
        }
        .form-group {
            margin-bottom: 15px;
        }
        label {
            display: block;
            margin-bottom: 5px;
        }
        input[type="text"] {
            width: calc(100% - 22px); /* Adjust for padding and border */
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 4px;
        }
        .button {
            background-color: #4CAF50;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
        }
        .button:hover {
            background-color: #45a049;
        }
        a.home-link {
             display: inline-block;
             margin-top: 20px;
             color: #007bff;
             text-decoration: none;
        }
        a.home-link:hover {
             text-decoration: underline;
        }
    </style>
</head>
<body>

<div class="container">
    <h1>Student Exam Schedule Search</h1>

    <form method="POST" action="student_schedule.php">
        <div class="form-group">
            <label for="student_id">Enter Student ID:</label>
            <input type="text" id="student_id" name="student_id" value="<?php echo $student_id ?? ''; ?>" required>
        </div>
        <button type="submit" class="button">Search Schedule</button>
    </form>

    <?php if ($error_message): ?>
        <p class="error"><?php echo $error_message; ?></p>
    <?php endif; ?>

    <?php if ($student_id && !$error_message && !empty($student_schedule)): ?>
        <h2>Exam Schedule for <?php echo $student_id; ?></h2>
        <table class="schedule-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Exam / Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($student_schedule as $date => $item): ?>
                <tr>
                    <td><?php echo $date; ?></td>
                    <td><?php echo $item; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php elseif ($student_id && !$error_message && empty($student_courses)): ?>
         <p>No enrollments found for student ID '<?php echo $student_id; ?>'.</p>
    <?php elseif ($student_id && !$error_message && empty($student_schedule)): ?>
        <p>No exams scheduled for the courses enrolled by student ID '<?php echo $student_id; ?>' within the active schedule period.</p>
    <?php endif; ?>

    <a href="index.php" class="home-link">&larr; Back to Home</a>

</div>

</body>
</html> 