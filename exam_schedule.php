<?php
// Process form submission and run Python script
$execution_result = null;
$execution_error = null;
$resultFilePath = 'process/schedule_result.json'; // Path relative to this PHP script

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_date']) && isset($_POST['end_date'])) {
    $start_date = trim($_POST['start_date']);
    $end_date = trim($_POST['end_date']);
    $holidays = isset($_POST['holidays']) ? trim($_POST['holidays']) : '';
    
    if (!empty($start_date) && !empty($end_date)) {
        // Construct the command to run the Python script
        // Use escapeshellarg to make inputs safe for shell execution
        $command = sprintf('python process/process_schedule.py %s %s %s',
            escapeshellarg($start_date),
            escapeshellarg($end_date),
            escapeshellarg($holidays)
        );
        
        // Execute the command
        $output = [];
        $return_code = 0;
        exec($command, $output, $return_code);
        
        if ($return_code === 0) {
            $execution_result = "Python script executed successfully.";
            // Redirect to refresh the page and show results
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            $execution_error = "Error executing Python script. Return code: $return_code";
            if (!empty($output)) {
                $execution_error .= "<br>Output: " . htmlspecialchars(implode("\n", $output));
            }
        }
    } else {
        $execution_error = "Please select both start and end dates.";
    }
}

// --- Load Schedule Result from File ---
$loaded_result = null;
$load_error = null;

if (file_exists($resultFilePath)) {
    if (is_readable($resultFilePath)) {
        $json_content = file_get_contents($resultFilePath);
        if ($json_content === false) {
            $load_error = "Error: Could not read the result file ({$resultFilePath}).";
        } else {
            $loaded_result = json_decode($json_content, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $load_error = "Error: Could not decode JSON from result file: " . json_last_error_msg();
                $loaded_result = null; // Ensure result is null if decode failed
            }
        }
    } else {
        $load_error = "Error: Result file ({$resultFilePath}) exists but is not readable by the web server.";
    }
} else {
    $load_error = "Info: Result file ({$resultFilePath}) not found. Run the Python script first to generate it.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Scheduling - Classroom</title>
    <link rel="preconnect" href="https://fonts.gstatic.com/" crossorigin="" />
    <link
      rel="stylesheet"
      as="style"
      onload="this.rel='stylesheet'"
      href="https://fonts.googleapis.com/css2?display=swap&amp;family=Lexend%3Awght%40400%3B500%3B700%3B900&amp;family=Noto+Sans%3Awght%40400%3B500%3B700%3B900"
    />
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Include FullCalendar CSS -->
    <link href='reference/php-event-calendar/fullcalendar/packages/core/main.css' rel='stylesheet' />
    <link href='reference/php-event-calendar/fullcalendar/packages/daygrid/main.css' rel='stylesheet' />
    
    <style>
        /* Calendar styling */
        .calendar-day {
            cursor: pointer;
            height: 36px;
            position: relative;
        }
        .calendar-day:hover {
            background-color: #f3f4f6;
        }
        .calendar-day.selected {
            background-color: #1980e6;
            color: white;
        }
        .calendar-day.start-date {
            border-top-left-radius: 50%;
            border-bottom-left-radius: 50%;
        }
        .calendar-day.end-date {
            border-top-right-radius: 50%;
            border-bottom-right-radius: 50%;
        }
        .calendar-day.in-range {
            background-color: #e9f7fd;
        }
        .calendar-day.holiday {
            background-color: #f8d7da;
            color: #721c24;
        }
        .calendar-day.holiday::after {
            content: 'H';
            position: absolute;
            bottom: 2px;
            right: 4px;
            font-size: 0.6em;
        }
        .calendar-day.disabled {
            color: #9ca3af;
            cursor: not-allowed;
        }
        .calendar-day-number {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
        }
        /* Optional: Add custom styles here if needed, or override Tailwind */
        .calendar-day.selected-start,
        .calendar-day.selected-end {
            background-color: #1980e6 !important; /* Blue */
            color: white !important;
            border-radius: 0 !important; /* Allow range background */
        }
        .calendar-day.selected-start {
             border-top-left-radius: 50% !important;
             border-bottom-left-radius: 50% !important;
        }
        .calendar-day.selected-end {
             border-top-right-radius: 50% !important;
             border-bottom-right-radius: 50% !important;
        }
        .calendar-day.selected-range {
            background-color: #e9f7fd !important; /* Light blue */
            border-radius: 0;
            color: #333 !important;
        }
         .calendar-day.selected-holiday {
            background-color: #f8d7da !important; /* Light red */
            color: #721c24 !important;
            position: relative;
             border-radius: 0 !important; /* Keep range selection visible */
        }
         /* Ensure holiday selection doesn't override start/end caps */
         .calendar-day.selected-holiday.selected-start {
            border-top-left-radius: 50% !important;
            border-bottom-left-radius: 50% !important;
         }
         .calendar-day.selected-holiday.selected-end {
            border-top-right-radius: 50% !important;
            border-bottom-right-radius: 50% !important;
         }
        .calendar-day.selected-holiday::after {
            content: 'H'; /* Indicate Holiday */
            position: absolute;
            bottom: 2px;
            right: 4px;
            font-size: 0.6em;
            font-weight: bold;
            color: #721c24;
        }
         .calendar-day:not(.disabled):hover div {
             background-color: #e2e8f0; /* gray-200 */
         }
         .calendar-day.disabled {
             color: #9ca3af; /* gray-400 */
             cursor: not-allowed;
         }
         .calendar-day div {
             border-radius: 50%;
             transition: background-color 0.1s ease-in-out;
         }
    </style>
</head>
<body>
  <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="post">
    <div class="relative flex size-full min-h-screen flex-col bg-white group/design-root overflow-x-hidden" style='font-family: Lexend, "Noto Sans", sans-serif;'>
      <div class="layout-container flex h-full grow flex-col">
        <header class="flex items-center justify-between whitespace-nowrap border-b border-solid border-b-[#f0f2f4] px-10 py-3">
          <div class="flex items-center gap-4 text-[#111418]">
            <div class="size-4">
              <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M4 4H17.3334V17.3334H30.6666V30.6666H44V44H4V4Z" fill="#005a87"></path></svg> <!-- UTAS Blue -->
            </div>
            <h2 class="text-[#111418] text-lg font-bold leading-tight tracking-[-0.015em]">Classroom Portal</h2>
          </div>
          <div class="flex flex-1 justify-end gap-8">
             <!-- Navigation Links -->
             <a href="index.php" class="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-xl h-10 px-4 bg-[#f0f2f4] text-[#111418] text-sm font-bold leading-normal tracking-[0.015em]">
                 <span class="truncate">Back to Portal</span>
             </a>
              <a href="dashboard.php" class="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-xl h-10 px-4 bg-[#f0f2f4] text-[#111418] text-sm font-bold leading-normal tracking-[0.015em]">
                 <span class="truncate">Dashboard</span>
             </a>
            <!-- User Avatar (Placeholder) -->
            <div
              class="bg-center bg-no-repeat aspect-square bg-cover rounded-full size-10"
              style='background-image: url("data:image/svg+xml,%3Csvg width=\'40\' height=\'40\' viewBox=\'0 0 40 40\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg\'%3E%3Crect width=\'40\' height=\'40\' rx=\'20\' fill=\'%23E0E0E0\'/%3E%3Cpath d=\'M20 26C23.3137 26 26 23.3137 26 20C26 16.6863 23.3137 14 20 14C16.6863 14 14 16.6863 14 20C14 23.3137 16.6863 26 20 26Z\' fill=\'%23BDBDBD\'/%3E%3Cpath d=\'M29.5 31C29.5 27.134 25.176 24 20 24C14.824 24 10.5 27.134 10.5 31H29.5Z\' fill=\'%23BDBDBD\'/%3E%3C/svg%3E");'
            ></div>
          </div>
        </header>
        <main class="px-10 sm:px-20 md:px-40 flex flex-1 justify-center py-5">
          <div class="layout-content-container flex flex-col w-full max-w-[960px] py-5 flex-1">
            <div class="flex flex-wrap justify-between gap-3 p-4">
                <h1 class="text-[#111418] tracking-light text-[32px] font-bold leading-tight min-w-72">Schedule Final Exams</h1>
            </div>

            <!-- Display execution messages if any -->
            <?php if ($execution_error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">
                <strong class="font-bold">Error:</strong>
                <span class="block sm:inline"><?php echo $execution_error; ?></span>
            </div>
            <?php endif; ?>
            
            <?php if ($execution_result): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative mb-4" role="alert">
                <span class="block sm:inline"><?php echo $execution_result; ?></span>
            </div>
            <?php endif; ?>

             <!-- Date Range Display Area -->
             <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-4 mb-6 border-b pb-6">
                 <div class="bg-gray-100 p-4 rounded-lg shadow-inner">
                     <label class="block text-sm font-medium text-gray-500 mb-1">Selected Start Date:</label>
                     <p id="display_start_date" class="text-lg font-semibold text-gray-800">--</p>
                 </div>
                 <div class="bg-gray-100 p-4 rounded-lg shadow-inner">
                     <label class="block text-sm font-medium text-gray-500 mb-1">Selected End Date:</label>
                     <p id="display_end_date" class="text-lg font-semibold text-gray-800">--</p>
                 </div>
             </div>

            <!-- Calendar Section -->
            <div class="flex flex-col items-center justify-center gap-6 p-4">
               <h3 class="text-lg font-semibold text-gray-800 w-full text-center">Select Exam Period & Holidays</h3>
               <p class="text-sm text-gray-600 w-full text-center mb-4">Click a date to select the start, click another to select the end. Click dates within the range to mark/unmark as holidays.</p>

              <div class="flex min-w-72 w-full max-w-[400px] flex-1 flex-col gap-0.5 border rounded-lg p-4 shadow bg-white">
                <!-- Calendar Header (JS needed) -->
                <div class="flex items-center p-1 justify-between">
                  <button type="button" id="prev-month" class="focus:outline-none">
                    <div class="text-[#111418] flex size-10 items-center justify-center hover:bg-gray-100 rounded-full" data-icon="CaretLeft">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" fill="currentColor" viewBox="0 0 256 256">
                        <path d="M165.66,202.34a8,8,0,0,1-11.32,11.32l-80-80a8,8,0,0,1,0-11.32l80-80a8,8,0,0,1,11.32,11.32L91.31,128Z"></path>
                      </svg>
                    </div>
                  </button>
                  <p class="text-[#111418] text-base font-bold leading-tight flex-1 text-center" id="calendar-month-year">Month Year</p>
                  <button type="button" id="next-month" class="focus:outline-none">
                    <div class="text-[#111418] flex size-10 items-center justify-center hover:bg-gray-100 rounded-full" data-icon="CaretRight">
                      <svg xmlns="http://www.w3.org/2000/svg" width="18px" height="18px" fill="currentColor" viewBox="0 0 256 256">
                        <path d="M181.66,133.66l-80,80a8,8,0,0,1-11.32-11.32L164.69,128,90.34,53.66a8,8,0,0,1,11.32-11.32l80,80A8,8,0,0,1,181.66,133.66Z"></path>
                      </svg>
                    </div>
                  </button>
                </div>
                <!-- Calendar container -->
                <div id="calendar-container" class="grid grid-cols-7 gap-1">
                    <!-- Calendar will be populated by JavaScript -->
                 </div>
              </div>
              <!-- Hidden inputs to store selected dates for form submission -->
              <input type="hidden" id="start_date_hidden" name="start_date">
              <input type="hidden" id="end_date_hidden" name="end_date">
              <input type="hidden" id="holiday_dates_hidden" name="holidays">
            </div>

            <!-- Results Section (Displays data from file) -->
            <div class="mt-8 p-4 border-t">
                 <h3 class="text-lg font-semibold text-gray-800 mb-3">Schedule</h3>
                 <div class="bg-gray-50 p-4 rounded-lg shadow-inner min-h-[100px] text-sm">
                     <?php
                     if ($load_error) {
                         echo "<p class=\"text-red-600 font-semibold\">Error loading schedule:</p>";
                         echo "<pre class=\"text-xs text-gray-600 bg-red-50 p-2 rounded\">" . htmlspecialchars($load_error) . "</pre>";
                     } elseif ($loaded_result) {
                         if (isset($loaded_result['error'])) {
                             echo "<p class=\"text-orange-600 font-semibold\">Saved Error:</p>";
                             echo "<pre class=\"text-xs text-gray-600 bg-orange-50 p-2 rounded\">" . htmlspecialchars($loaded_result['error']) . "</pre>";
                         } elseif (isset($loaded_result['suggestion'])) {
                             echo "<p class=\"text-green-700 font-semibold mb-3\">Saved Schedule:</p>";
                             // Parse the suggestion
                             $suggestion_lines = explode("\n", trim($loaded_result['suggestion']));
                             $schedule_data = [];
                             $all_course_codes = []; // Collect all course codes to fetch levels
                             $parsing_errors = [];
                             foreach ($suggestion_lines as $line) {
                                 $line = trim($line);
                                 if (empty($line)) continue;
                                 
                                 // Updated regex: Match date, colon, and optional course codes after
                                 if (preg_match('/^\d+\.\s*(\d{4}-\d{2}-\d{2}):\s*(.*)$/i', $line, $matches)) {
                                     $date = trim($matches[1]);
                                     $courses_str = trim($matches[2]);
                                     
                                     if (!empty($courses_str)) {
                                         $courses_on_day = array_map('trim', explode(',', $courses_str));
                                         // Filter out empty strings that might result from trailing commas
                                         $courses_on_day = array_filter($courses_on_day);
                                         
                                         if (!empty($courses_on_day)) {
                                             $schedule_data[$date] = $courses_on_day;
                                             $all_course_codes = array_merge($all_course_codes, $courses_on_day);
                                         } else {
                                             // Date listed but no courses (intended break/study day)
                                             $schedule_data[$date] = []; // Represent as empty array
                                         }
                                     } else {
                                         // Date listed but no courses (intended break/study day)
                                         $schedule_data[$date] = []; // Represent as empty array
                                     }
                                 } else {
                                     // Keep lines that aren't schedule entries but ignore common intro/outro text patterns
                                     if (!preg_match('/^\d{4}-\d{2}-\d{2}/i', $line) && !preg_match('/^(here is|based on|schedule|note:|```)/i', $line)) {
                                         $parsing_errors[] = $line;
                                     }
                                 }
                             }

                             // --- Fetch Course Levels from Database ---
                             $course_levels = [];
                             if (!empty($all_course_codes)) {
                                 $unique_codes = array_unique($all_course_codes);
                                 $placeholders = implode(',', array_fill(0, count($unique_codes), '?'));
                                 $types = str_repeat('s', count($unique_codes));
                                 
                                 try {
                                     require_once 'db_connect.php'; // Ensure connection is available
                                     if (isset($conn) && $conn) {
                                         $stmt = $conn->prepare("SELECT course_code, academic_level FROM Courses WHERE course_code IN ($placeholders)");
                                         $stmt->bind_param($types, ...$unique_codes);
                                         $stmt->execute();
                                         $result = $stmt->get_result();
                                         while ($row = $result->fetch_assoc()) {
                                             $course_levels[$row['course_code']] = $row['academic_level'];
                                         }
                                         $stmt->close();
                                         // $conn->close(); // Keep connection open if needed elsewhere, or close if done
                                     } else {
                                         throw new Exception("Database connection not established in db_connect.php");
                                     }
                                 } catch (Exception $e) {
                                     $load_error .= " | DB Error fetching course levels: " . $e->getMessage();
                                 }
                             }
                             // --- End Fetch Course Levels ---

                             // Display parsing errors if any
                             if (!empty($parsing_errors)) {
                                 echo '<p class="text-yellow-600 font-semibold mt-4">Note: Some lines from the saved suggestion could not be parsed as schedule entries:</p>';
                                 echo '<ul class="list-disc list-inside text-xs text-gray-600 bg-yellow-50 p-2 rounded mb-3">';
                                 foreach ($parsing_errors as $error_line) {
                                     echo '<li>' . htmlspecialchars($error_line) . '</li>';
                                 }
                                 echo '</ul>';
                             }

                             // Prepare for FullCalendar
                             if (!empty($schedule_data)) {
                                 echo '<div id="schedule-calendar" class="mb-4"></div>'; // Container for FullCalendar
                                 
                                 // Create events array for FullCalendar, one event per course
                                 $calendar_events = [];
                                 foreach ($schedule_data as $date => $courses_on_day) {
                                     if (!empty($courses_on_day)) {
                                         foreach ($courses_on_day as $course_code) {
                                             $level = isset($course_levels[$course_code]) ? $course_levels[$course_code] : 'Unknown'; // Get level
                                             $calendar_events[] = [
                                                 'title' => $course_code,
                                                 'start' => $date,
                                                 'level' => $level // Add level to event data
                                             ];
                                         }
                                     } 
                                     // Do not add anything for days with $courses_on_day = [] (empty days)
                                 }
                                 
                                 $calendar_events_json = json_encode($calendar_events);

                                 echo <<<JS
                                 <script>
                                     document.addEventListener('DOMContentLoaded', function() {
                                         var calendarEl = document.getElementById('schedule-calendar');
                                         if (calendarEl) {
                                             var calendarEvents = {$calendar_events_json};
                                             // Define colors for levels
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
                                                     left: 'prev,next',
                                                     center: 'title',
                                                     right: '' 
                                                 },
                                                 events: calendarEvents,
                                                 contentHeight: 'auto', 
                                                 // Use eventDidMount for more control over rendering
                                                 eventDidMount: function(info) {
                                                     var level = info.event.extendedProps.level;
                                                     if (level && levelColors[level]) {
                                                         info.el.style.backgroundColor = levelColors[level];
                                                         info.el.style.borderColor = levelColors[level]; // Match border
                                                         // Ensure text is readable on colored background
                                                         info.el.style.color = '#ffffff'; // White text often works well
                                                         // Add a class for potential further styling
                                                         info.el.classList.add('level-' + level.toLowerCase().replace(' ', '-'));
                                                     }
                                                 },
                                                 eventDisplay: 'block' 
                                             });
                                             calendar.render();
                                         } else {
                                             console.error("Schedule calendar container not found");
                                         }
                                     });
                                 </script>
JS;
                             } elseif (empty($parsing_errors)) {
                                 // If schedule_data is empty AND there were no parsing errors to display
                                 echo '<p class="text-gray-500">(Saved suggestion received but contained no parsable schedule lines or relevant notes)</p>';
                                 echo "<pre class=\"text-xs text-gray-600 bg-gray-100 p-2 rounded\">" . htmlspecialchars($loaded_result['suggestion']) . "</pre>";
                             }

                         } else {
                             echo "<p class=\"text-gray-500\">Loaded result file contained an unexpected structure.</p>";
                             echo "<pre class=\"text-xs text-gray-600 bg-gray-100 p-2 rounded\">" . htmlspecialchars($json_content) . "</pre>";
                         }
                     } else {
                         // This case should ideally be covered by $load_error, but as a fallback:
                         echo "<p class=\"text-gray-500\">Could not load or process the schedule result file.</p>";
                     }
                     ?>
                 </div>
             </div>

            <!-- Action Buttons -->
            <div class="flex justify-end mt-8 px-4 py-3">
              <div class="flex gap-3">
                <button
                  type="button"
                  class="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-xl h-10 px-4 bg-[#f0f2f4] text-[#111418] text-sm font-bold leading-normal tracking-[0.015em] hover:bg-gray-200"
                  onclick="window.location.href='index.php';"
                >
                  <span class="truncate">Cancel</span>
                </button>
                <button
                  type="submit"
                  class="flex min-w-[84px] max-w-[480px] cursor-pointer items-center justify-center overflow-hidden rounded-xl h-10 px-4 bg-[#1980e6] text-white text-sm font-bold leading-normal tracking-[0.015em] hover:bg-blue-700"
                >
                  <span class="truncate">Generate Schedule</span>
                </button>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </form>

  <!-- Include FullCalendar JS -->
  <script src='reference/php-event-calendar/fullcalendar/packages/core/main.js'></script>
  <script src='reference/php-event-calendar/fullcalendar/packages/daygrid/main.js'></script>

  <script>
    document.addEventListener('DOMContentLoaded', function() {
        // DOM elements
        const calendarContainer = document.getElementById('calendar-container');
        const startDateHidden = document.getElementById('start_date_hidden');
        const endDateHidden = document.getElementById('end_date_hidden');
        const holidaysHidden = document.getElementById('holiday_dates_hidden');
        const displayStartDate = document.getElementById('display_start_date');
        const displayEndDate = document.getElementById('display_end_date');
        const monthYearDisplay = document.getElementById('calendar-month-year');
        const prevMonthButton = document.getElementById('prev-month');
        const nextMonthButton = document.getElementById('next-month');

        // State variables
        let currentDate = new Date();
        let currentMonth = currentDate.getMonth();
        let currentYear = currentDate.getFullYear();
        let startDate = null;
        let endDate = null;
        let holidays = new Set();
        
        // Initialize
        displayCalendar();
        updateMonthYearDisplay();
        
        // Set up event listeners for navigation buttons
        prevMonthButton.addEventListener('click', function() {
            currentMonth--;
            if (currentMonth < 0) {
                currentMonth = 11;
                currentYear--;
            }
            displayCalendar();
            updateMonthYearDisplay();
        });
        
        nextMonthButton.addEventListener('click', function() {
            currentMonth++;
            if (currentMonth > 11) {
                currentMonth = 0;
                currentYear++;
            }
            displayCalendar();
            updateMonthYearDisplay();
        });
        
        // Helper functions
        function updateMonthYearDisplay() {
            const monthNames = ["January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"];
            monthYearDisplay.textContent = monthNames[currentMonth] + ' ' + currentYear;
        }
        
        function displayCalendar() {
            // Clear previous calendar
            calendarContainer.innerHTML = '';
            
            // Get first day of month and last day of month
            const firstDay = new Date(currentYear, currentMonth, 1);
            const lastDay = new Date(currentYear, currentMonth + 1, 0);
            
            // Calculate days from previous month to fill first row
            const firstDayIndex = firstDay.getDay(); // 0 = Sunday, 1 = Monday, etc.
            
            // Add days from previous month
            const prevMonthLastDay = new Date(currentYear, currentMonth, 0).getDate();
            for (let i = firstDayIndex - 1; i >= 0; i--) {
                const dayElement = createDayElement(prevMonthLastDay - i, true);
                calendarContainer.appendChild(dayElement);
            }
            
            // Add days of current month
            const daysInMonth = lastDay.getDate();
            for (let i = 1; i <= daysInMonth; i++) {
                const date = new Date(currentYear, currentMonth, i);
                const dayElement = createDayElement(i, false, date);
                calendarContainer.appendChild(dayElement);
                
                // Add click event
                dayElement.addEventListener('click', function() {
                    if (this.classList.contains('disabled')) return;
                    
                    const dateValue = formatDate(date);
                    
                    if (!startDate || (startDate && endDate)) {
                        // Start new selection
                        startDate = dateValue;
                        endDate = null;
                        startDateHidden.value = dateValue;
                        endDateHidden.value = '';
                        displayStartDate.textContent = dateValue;
                        displayEndDate.textContent = 'Select end date...';
                        holidays.clear();
                        holidaysHidden.value = '';
                    } else {
                        // Complete the range
                        if (new Date(dateValue) < new Date(startDate)) {
                            endDate = startDate;
                            startDate = dateValue;
                        } else {
                            endDate = dateValue;
                        }
                        
                        startDateHidden.value = startDate;
                        endDateHidden.value = endDate;
                        displayStartDate.textContent = startDate;
                        displayEndDate.textContent = endDate;
                    }
                    
                    displayCalendar(); // Redraw to show selection
                });
            }
            
            // Add days from next month to fill last row
            const lastDayIndex = lastDay.getDay();
            const nextDays = 7 - lastDayIndex - 1;
            for (let i = 1; i <= nextDays; i++) {
                const dayElement = createDayElement(i, true);
                calendarContainer.appendChild(dayElement);
            }
        }
        
        function createDayElement(day, isDisabled, date) {
            const dayElement = document.createElement('div');
            dayElement.classList.add('calendar-day');
            
            if (isDisabled) {
                dayElement.classList.add('disabled');
            }
            
            // Create day number element
            const dayNumber = document.createElement('div');
            dayNumber.classList.add('calendar-day-number');
            dayNumber.textContent = day;
            dayElement.appendChild(dayNumber);
            
            // If we have a date, add data attribute and check for selected status
            if (date && !isDisabled) {
                const dateString = formatDate(date);
                dayElement.dataset.date = dateString;
                
                // Check if this day is in selected range
                if (startDate && dateString === startDate) {
                    dayElement.classList.add('selected', 'start-date');
                }
                if (endDate && dateString === endDate) {
                    dayElement.classList.add('selected', 'end-date');
                }
                if (startDate && endDate && dateString > startDate && dateString < endDate) {
                    dayElement.classList.add('in-range');
                }
                
                // Check if this is a holiday
                if (holidays.has(dateString)) {
                    dayElement.classList.add('holiday');
                }
                
                // Add click handler for holiday selection if in range
                if (startDate && endDate && dateString >= startDate && dateString <= endDate) {
                    dayElement.addEventListener('click', function(event) {
                        event.stopPropagation();
                        const dateStr = this.dataset.date;

                        if (holidays.has(dateStr)) {
                            holidays.delete(dateStr);
                        } else {
                            holidays.add(dateStr);
                        }
                        
                        holidaysHidden.value = Array.from(holidays).join(',');
                        displayCalendar(); // Redraw calendar
                    });
                }
            }
            
            return dayElement;
        }
        
        function formatDate(date) {
            const year = date.getFullYear();
            const month = (date.getMonth() + 1).toString().padStart(2, '0');
            const day = date.getDate().toString().padStart(2, '0');
            return `${year}-${month}-${day}`;
        }
    });
  </script>
</body>
</html> 