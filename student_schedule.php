<?php
require_once 'config.php';

$student_id = null;
$student_courses = [];
$schedule_data = []; // Master schedule [date => [course1, course2]]
$course_levels = []; // [course_code => level]
$student_schedule_events = []; // Final array for JS calendar [[title=>..., start=>..., level=>...]]
$error_message = null;
$schedule_string = null;

// Function to safely get POST data
function get_post_var($key, $default = null) {
    return isset($_POST[$key]) ? htmlspecialchars(trim($_POST[$key])) : $default;
}

// --- 1. Load the Master Schedule from JSON --- 
$schedule_file = SCHEDULE_FILE_PATH;
if (file_exists($schedule_file)) {
    $json_content = file_get_contents($schedule_file);
    $schedule_result = json_decode($json_content, true);
    if (isset($schedule_result['suggestion'])) {
        $schedule_string = $schedule_result['suggestion'];
        // Extract schedule lines from the master schedule suggestion
        preg_match_all('/^\d+\.\s*(\d{4}-\d{2}-\d{2}):\s*(.*)$/im', $schedule_string, $matches, PREG_SET_ORDER);
        
        $all_scheduled_course_codes = []; // Collect codes for level fetching
        foreach ($matches as $match) {
            $date = trim($match[1]);
            $courses_str = trim($match[2]);
            if (!empty($courses_str)) {
                $courses_on_day = array_map('trim', explode(',', $courses_str));
                $courses_on_day = array_filter($courses_on_day); // Remove empty strings
                if (!empty($courses_on_day)) {
                    $schedule_data[$date] = $courses_on_day;
                    $all_scheduled_course_codes = array_merge($all_scheduled_course_codes, $courses_on_day);
                }
            }
            // Ignore dates with no courses assigned for this specific student view
        }
        // Fetch levels for all courses mentioned in the schedule
        if (!empty($all_scheduled_course_codes)) {
            $unique_codes = array_unique($all_scheduled_course_codes);
            $placeholders = implode(',', array_fill(0, count($unique_codes), '?'));
            $types = str_repeat('s', count($unique_codes));
            
            try {
                // Use a new connection or ensure db_connect.php provides one
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    throw new Exception("Connection failed: " . $conn->connect_error);
                }
                $stmt = $conn->prepare("SELECT course_code, academic_level FROM Courses WHERE course_code IN ($placeholders)");
                $stmt->bind_param($types, ...$unique_codes);
                $stmt->execute();
                $result = $stmt->get_result();
                while ($row = $result->fetch_assoc()) {
                    $course_levels[$row['course_code']] = $row['academic_level'];
                }
                $stmt->close();
                $conn->close(); 
            } catch (Exception $e) {
                 $error_message .= " | DB Error fetching course levels: " . $e->getMessage();
            }
        }

    } elseif (isset($schedule_result['error'])) {
        $error_message = "Error loading schedule file: " . $schedule_result['error'];
    } else {
         $error_message = "Could not parse schedule data from " . $schedule_file;
    }
} else {
    $error_message = "Master schedule file not found at " . $schedule_file . ". Please generate it first.";
}

// --- 2. Handle Form Submission --- 
if ($_SERVER["REQUEST_METHOD"] == "POST" && !$error_message) {
    $student_id = get_post_var('student_id');

    if (empty($student_id)) {
        $error_message = "Please enter a Student ID.";
    } else {
        // --- 3. Fetch Student's Enrolled Courses --- 
        try {
            $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            if ($conn->connect_error) {
                throw new Exception("Connection failed: " . $conn->connect_error);
            }
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
            $error_message = "Database error fetching student courses: " . $e->getMessage();
        }
    }
}

// --- 4. Prepare Student-Specific Calendar Events --- 
if ($student_id && !empty($student_courses) && !empty($schedule_data) && !$error_message) {
    foreach ($schedule_data as $date => $courses_on_day) {
        foreach ($courses_on_day as $course_code) {
            // Check if the student is enrolled in this specific course
            if (in_array($course_code, $student_courses)) {
                $level = isset($course_levels[$course_code]) ? $course_levels[$course_code] : 'Unknown';
                $student_schedule_events[] = [
                    'title' => $course_code,
                    'start' => $date,
                    'level' => $level
                ];
            }
        }
    }
    // Check if any events were actually found for the student
    if (empty($student_schedule_events)) {
         // Keep $error_message null, but the calendar won't render if $student_schedule_events is empty
         // Add a message to display in the HTML later
         $no_events_message = "No exams found in the current schedule for the courses enrolled by student ID '{$student_id}'.";
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
    <!-- Include FullCalendar CSS -->
    <link href='reference/php-event-calendar/fullcalendar/packages/core/main.css' rel='stylesheet' />
    <link href='reference/php-event-calendar/fullcalendar/packages/daygrid/main.css' rel='stylesheet' />
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

    <?php // Display calendar only if a successful search was performed ?>
    <?php if ($student_id && !$error_message && !empty($student_courses)): ?>
        <h2>Exam Schedule for <?php echo $student_id; ?></h2>
        <div id="student-schedule-calendar" class="mb-4"></div>
    <?php elseif ($student_id && !$error_message && empty($student_courses)): ?>
         <p>No enrollments found for student ID '<?php echo $student_id; ?>'.</p>
    <?php elseif (isset($no_events_message)): // Display message if set in PHP ?>
         <p><?php echo $no_events_message; ?></p>
    <?php endif; ?>

    <a href="index.php" class="home-link">&larr; Back to Home</a>

</div>

<!-- Include FullCalendar JS -->
<script src='reference/php-event-calendar/fullcalendar/packages/core/main.js'></script>
<script src='reference/php-event-calendar/fullcalendar/packages/daygrid/main.js'></script>

<?php // JavaScript for FullCalendar initialization ?>
<?php if ($student_id && !$error_message && !empty($student_schedule_events)): // Check the correct variable ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        var calendarEl = document.getElementById('student-schedule-calendar');
        if (calendarEl) {
            var calendarEvents = <?php echo json_encode($student_schedule_events); /* Use the new events array */ ?>;
            // Define colors for levels (using the updated scheme)
            var levelColors = {
                'Diploma': '#e63946',          // Bright red
                'Advanced Diploma': '#fcbf49', // Gold/amber
                'Bachelor': '#2a9d8f',        // Teal
                'Unknown': '#6c757d'           // Gray
            };
            
            var calendar = new FullCalendar.Calendar(calendarEl, {
                plugins: [ 'dayGrid' ],
                initialView: 'dayGridMonth',
                headerToolbar: { 
                    left: 'prev,next today', 
                    center: 'title', 
                    right: '' 
                },
                events: calendarEvents,
                contentHeight: 'auto', 
                eventDidMount: function(info) {
                    var level = info.event.extendedProps.level; // Assumes level is passed in event data
                    if (level && levelColors[level]) {
                        info.el.style.backgroundColor = levelColors[level];
                        info.el.style.borderColor = levelColors[level];
                        info.el.style.color = '#ffffff'; 
                    }
                },
                eventDisplay: 'block' 
            });
            calendar.render();
        } else {
            console.error("Student schedule calendar container not found");
        }
    });
</script>
<?php endif; ?>

</body>
</html> 