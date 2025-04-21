<?php
// No PHP logic needed for this page yet.

// --- Load Schedule Result from File ---
$loaded_result = null;
$load_error = null;
$resultFilePath = 'process/schedule_result.json'; // Path relative to this PHP script

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

/*
// --- Process Exam Schedule Request (Now handled by running Python script manually) ---
// (Previous code block executing shell_exec remains commented out or removed)
*/

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
    <!-- Include flatpickr CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <script>
        // Optional: Configure Tailwind if needed
        // tailwind.config = {
        //   theme: {
        //     extend: {
        //       colors: {
        //         clifford: '#da373d',
        //       }
        //     }
        //   }
        // }
    </script>
    <style>
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
  <form action="#" method="post"> <!-- Form wraps the relevant content -->
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
              style='background-image: url("data:image/svg+xml,%3Csvg width=\'40\' height=\'40\' viewBox=\'0 0 40 40\' fill=\'none\' xmlns=\'http://www.w3.org/2000/svg%27%3E%3Crect width=\'40\' height=\'40\' rx=\'20\' fill=\'%23E0E0E0\'/><path d=\'M20 26C23.3137 26 26 23.3137 26 20C26 16.6863 23.3137 14 20 14C16.6863 14 14 16.6863 14 20C14 23.3137 16.6863 26 20 26Z\' fill=\'%23BDBDBD\'/><path d=\'M29.5 31C29.5 27.134 25.176 24 20 24C14.824 24 10.5 27.134 10.5 31H29.5Z\' fill=\'%23BDBDBD\'/><%3C/svg%3E%0A");'
            ></div>
          </div>
        </header>
        <main class="px-10 sm:px-20 md:px-40 flex flex-1 justify-center py-5">
          <div class="layout-content-container flex flex-col w-full max-w-[960px] py-5 flex-1">
            <div class="flex flex-wrap justify-between gap-3 p-4">
                <h1 class="text-[#111418] tracking-light text-[32px] font-bold leading-tight min-w-72">Schedule Final Exams</h1>
            </div>

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
               <p class="text-sm text-gray-600 w-full text-center mb-4">Click a date to select the start, click another to select the end. Click dates within the range to mark/unmark as holidays. (Requires JavaScript).</p>

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
                <!-- Calendar Grid Headers -->
                <div class="grid grid-cols-7 text-center">
                  <p class="text-gray-600 text-[13px] font-bold leading-normal tracking-[0.015em] flex h-10 w-full items-center justify-center pb-0.5">S</p>
                  <p class="text-gray-600 text-[13px] font-bold leading-normal tracking-[0.015em] flex h-10 w-full items-center justify-center pb-0.5">M</p>
                  <p class="text-gray-600 text-[13px] font-bold leading-normal tracking-[0.015em] flex h-10 w-full items-center justify-center pb-0.5">T</p>
                  <p class="text-gray-600 text-[13px] font-bold leading-normal tracking-[0.015em] flex h-10 w-full items-center justify-center pb-0.5">W</p>
                  <p class="text-gray-600 text-[13px] font-bold leading-normal tracking-[0.015em] flex h-10 w-full items-center justify-center pb-0.5">T</p>
                  <p class="text-gray-600 text-[13px] font-bold leading-normal tracking-[0.015em] flex h-10 w-full items-center justify-center pb-0.5">F</p>
                  <p class="text-gray-600 text-[13px] font-bold leading-normal tracking-[0.015em] flex h-10 w-full items-center justify-center pb-0.5">S</p>
                </div>
                <!-- Calendar Days Grid (JS populates this) -->
                <div class="grid grid-cols-7 text-center" id="calendar-days-grid">
                    <!-- JS will fill this. Example Structure: -->
                    <!-- <button type="button" class="calendar-day h-10 w-full text-gray-700 text-sm font-medium focus:outline-none disabled" data-date="YYYY-MM-DD" disabled><div>29</div></button> -->
                    <!-- <button type="button" class="calendar-day h-10 w-full text-gray-700 text-sm font-medium focus:outline-none" data-date="YYYY-MM-DD"><div>1</div></button> -->
                    <div class="col-span-7 h-40 flex items-center justify-center text-gray-400">Calendar loading...</div>
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
                         echo htmlspecialchars($load_error);
                     } elseif ($loaded_result) {
                         if (isset($loaded_result['error'])) {
                             echo "<p class=\"text-orange-600 font-semibold\">Saved Error:</p>";
                             echo htmlspecialchars($loaded_result['error']);
                         } elseif (isset($loaded_result['suggestion'])) {
                             echo "<p class=\"text-green-700 font-semibold mb-3\">Saved Suggestion:</p>";
                             // Parse the suggestion into a table
                             $suggestion_lines = explode("\n", trim($loaded_result['suggestion']));
                             $schedule_data = [];
                             $parsing_errors = [];
                             foreach ($suggestion_lines as $line) {
                                 $line = trim($line);
                                 if (empty($line)) continue;
                                 // Updated regex: Expect format like "#. YYYY-MM-DD: COURSE_CODE" or "#. YYYY-MM-DD: Study Day"
                                 // It captures the date in group 1 and the course/study day in group 2
                                 if (preg_match('/^\d+\.\s*(\d{4}-\d{2}-\d{2})\s*:\s*(\S+.*)/i', $line, $matches)) {
                                     $schedule_data[] = ['date' => trim($matches[1]), 'course' => trim($matches[2])];
                                 } else {
                                     // Handle lines that don't match the numbered list format
                                     // Ignore introductory/concluding text from the AI
                                     if (!preg_match('/^\d{4}-\d{2}-\d{2}/i', $line)) { // Basic check if it starts like a date line
                                          $parsing_errors[] = $line; // Keep lines that aren't schedule entries
                                     }
                                 }
                             }

                             if (!empty($schedule_data)) {
                                 echo '<table class="min-w-full divide-y divide-gray-200 border">';
                                 echo '<thead class="bg-gray-100"><tr>';
                                 echo '<th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>';
                                 echo '<th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Course</th>';
                                 echo '</tr></thead>';
                                 echo '<tbody class="bg-white divide-y divide-gray-200">';
                                 foreach ($schedule_data as $item) {
                                     echo '<tr>';
                                     echo '<td class="px-4 py-2 whitespace-nowrap">' . htmlspecialchars($item['date']) . '</td>';
                                     echo '<td class="px-4 py-2 whitespace-nowrap">' . htmlspecialchars($item['course']) . '</td>';
                                     echo '</tr>';
                                 }
                                 echo '</tbody></table>';
                             }

                             if (!empty($parsing_errors)) {
                                 echo '<p class="text-yellow-600 font-semibold mt-4">Note: Some lines from the saved suggestion could not be formatted into the table:</p>';
                                 echo '<ul class="list-disc list-inside text-xs text-gray-600">';
                                 foreach ($parsing_errors as $error_line) {
                                     echo '<li>' . htmlspecialchars($error_line) . '</li>';
                                 }
                                 echo '</ul>';
                             }
                              if (empty($schedule_data) && empty($parsing_errors)) {
                                 echo '<p class="text-gray-500">(Saved suggestion received but contained no parsable schedule lines)</p>';
                                 echo "<pre class=\"text-xs text-gray-600\">" . htmlspecialchars($loaded_result['suggestion']) . "</pre>";
                              }

                         } else {
                             echo "<p class=\"text-gray-500\">Loaded result file contained an unexpected structure.</p>";
                             echo "<pre class=\"text-xs text-gray-600\">" . htmlspecialchars($json_content) . "</pre>";
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
                  <span class="truncate">Save Schedule (Not Active)</span>
                </button>
              </div>
            </div>
          </div>
        </main>
      </div>
    </div>
  </form>

  <!-- Minimal JS Placeholder - Full implementation needed -->
  <script>
      // Placeholder: Indicate calendar needs JS
      document.addEventListener('DOMContentLoaded', () => {
          const calendarGrid = document.getElementById('calendar-days-grid');
          if (calendarGrid) {
              calendarGrid.innerHTML = '<div class="col-span-7 h-40 flex items-center justify-center text-gray-500 italic">Interactive calendar requires JavaScript.</div>';
          }
           // Placeholder values for display (replace with actual JS logic)
           document.getElementById('calendar-month-year').textContent = new Date().toLocaleDateString('en-US', { month: 'long', year: 'numeric' });
           document.getElementById('display_start_date').textContent = 'Not selected';
           document.getElementById('display_end_date').textContent = 'Not selected';
      });
  </script>

  <!-- Include flatpickr JS -->
  <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

  <script>
    document.addEventListener('DOMContentLoaded', () => {
        const calendarContainer = document.getElementById('calendar-days-grid').parentNode; // Target the container div
        const startDateHidden = document.getElementById('start_date_hidden');
        const endDateHidden = document.getElementById('end_date_hidden');
        const holidaysHidden = document.getElementById('holiday_dates_hidden');
        const displayStartDate = document.getElementById('display_start_date');
        const displayEndDate = document.getElementById('display_end_date');
        const monthYearDisplay = document.getElementById('calendar-month-year');
        const prevMonthButton = document.getElementById('prev-month');
        const nextMonthButton = document.getElementById('next-month');

        let holidayDates = new Set(); // Use a Set for efficient add/delete
        let selectedRange = { start: null, end: null };

        const fp = flatpickr(calendarContainer, {
            mode: "range",
            dateFormat: "Y-m-d",
            inline: true, // Display inline
            static: true, // Attach to the container
            monthSelectorType: 'static', // Use our custom month display
            // defaultDate: ["2024-01-01", "2024-01-10"], // Optional: Set default range
            onChange: function(selectedDates, dateStr, instance) {
                // Update hidden inputs and display for the range
                if (selectedDates.length === 1) {
                    selectedRange.start = selectedDates[0];
                    selectedRange.end = null; // Reset end date if only one is selected
                    startDateHidden.value = instance.formatDate(selectedDates[0], "Y-m-d");
                    endDateHidden.value = "";
                    displayStartDate.textContent = startDateHidden.value;
                    displayEndDate.textContent = 'Select end date...';
                } else if (selectedDates.length === 2) {
                    // Ensure start is before end
                    selectedRange.start = selectedDates[0] < selectedDates[1] ? selectedDates[0] : selectedDates[1];
                    selectedRange.end = selectedDates[0] > selectedDates[1] ? selectedDates[0] : selectedDates[1];

                    startDateHidden.value = instance.formatDate(selectedRange.start, "Y-m-d");
                    endDateHidden.value = instance.formatDate(selectedRange.end, "Y-m-d");
                    displayStartDate.textContent = startDateHidden.value;
                    displayEndDate.textContent = endDateHidden.value;

                    // Clear holidays outside the new range
                    const startStr = startDateHidden.value;
                    const endStr = endDateHidden.value;
                    const updatedHolidays = new Set();
                    holidayDates.forEach(holiday => {
                        if (holiday >= startStr && holiday <= endStr) {
                            updatedHolidays.add(holiday);
                        }
                    });
                    holidayDates = updatedHolidays;
                    holidaysHidden.value = Array.from(holidayDates).join(',');

                } else {
                     // Range cleared
                     selectedRange = { start: null, end: null };
                     startDateHidden.value = "";
                     endDateHidden.value = "";
                     displayStartDate.textContent = 'Not selected';
                     displayEndDate.textContent = 'Not selected';
                     holidayDates.clear();
                     holidaysHidden.value = '';
                }
                // Need to manually refresh day elements to apply range/holiday styles correctly after range change
                instance.redraw();
            },
            onDayCreate: function(dObj, dStr, fp, dayElem) {
                // Add custom click listener for holiday toggling
                const date = fp.formatDate(dObj, "Y-m-d");

                 // Apply existing holiday styles
                if (holidayDates.has(date)) {
                    dayElem.classList.add('selected-holiday');
                }

                dayElem.addEventListener('click', (event) => {
                    // Only toggle holiday if a valid range is selected and the click is on a date within that range
                    if (selectedRange.start && selectedRange.end && date >= fp.formatDate(selectedRange.start, "Y-m-d") && date <= fp.formatDate(selectedRange.end, "Y-m-d")) {
                        // Prevent flatpickr's default range selection behavior when clicking inside the range for holidays
                        event.stopPropagation();

                        if (holidayDates.has(date)) {
                            holidayDates.delete(date);
                            dayElem.classList.remove('selected-holiday');
                        } else {
                            holidayDates.add(date);
                            dayElem.classList.add('selected-holiday');
                        }
                        holidaysHidden.value = Array.from(holidayDates).sort().join(','); // Keep it sorted

                        // Re-apply range classes which might get removed by click
                        fp.selectedDates.forEach(selDate => {
                            const day = fp.days.querySelector(`.flatpickr-day[aria-label="${fp.formatDate(selDate, fp.config.ariaDateFormat)}"]`);
                            if(day) fp.selectDate(selDate, false); // Reassert selection without triggering onChange too much
                        });
                    }
                    // If not within a selected range, let flatpickr handle the click for range selection
                }, true); // Use capture phase to potentially stop flatpickr handler
            },
            onMonthChange: function(selectedDates, dateStr, instance) {
                 monthYearDisplay.textContent = instance.formatDate(instance.currentYear + "-" + (instance.currentMonth + 1) + "-01", "F Y");
            },
            onYearChange: function(selectedDates, dateStr, instance) {
                 monthYearDisplay.textContent = instance.formatDate(instance.currentYear + "-" + (instance.currentMonth + 1) + "-01", "F Y");
            },
             onReady: function(selectedDates, dateStr, instance) {
                 monthYearDisplay.textContent = instance.formatDate(instance.currentYear + "-" + (instance.currentMonth + 1) + "-01", "F Y");
             }

        });

        // Connect external prev/next month buttons
        prevMonthButton.addEventListener('click', () => fp.changeMonth(-1));
        nextMonthButton.addEventListener('click', () => fp.changeMonth(1));

        // Initial setup
         if (!startDateHidden.value || !endDateHidden.value) {
              displayStartDate.textContent = 'Not selected';
              displayEndDate.textContent = 'Not selected';
         } else {
             displayStartDate.textContent = startDateHidden.value;
             displayEndDate.textContent = endDateHidden.value;
             // Optional: Initialize calendar display if hidden values exist
             // fp.setDate([startDateHidden.value, endDateHidden.value], false);
             // Load initial holidays
             const initialHolidays = holidaysHidden.value.split(',').filter(d => d.trim() !== '');
             holidayDates = new Set(initialHolidays);
             fp.redraw(); // Draw with initial holidays
         }

         // Override flatpickr container styles to match design if needed
         const flatpickrCalendar = calendarContainer.querySelector('.flatpickr-calendar');
         if(flatpickrCalendar) {
            flatpickrCalendar.classList.add('shadow-none', 'border-none', 'w-full'); // Remove default flatpickr shadow/border
            flatpickrCalendar.style.width = '100%';
         }
          const flatpickrDays = calendarContainer.querySelector('.dayContainer');
         if(flatpickrDays) {
             flatpickrDays.classList.add('w-full');
             flatpickrDays.style.width = '100%';
             flatpickrDays.style.maxWidth = '100%';
         }

    });
  </script>
</body>
</html> 