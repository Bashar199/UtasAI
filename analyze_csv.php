<?php

/**
 * Analyzes student marks data from a specific section of a CSV file.
 *
 * @param string $csvFilePath Path to the CSV file.
 * @param string $marksHeader The exact header line marking the start of the marks data.
 * @param int $limitRows Safety limit for the number of rows to process.
 * @return array An array containing 'overallStats' and 'courses' analysis results, or an empty array on error.
 */
function analyzeStudentDataFromCSV(string $csvFilePath, string $marksHeader, int $limitRows = 10000): array
{
    // --- Data Structures ---
    $studentMarks = [];
    $courses = []; // To store course-specific data
    $overallStats = [
        'total_score_sum' => 0,
        'count' => 0,
        'grades' => [
            'A+' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0
        ]
    ];

    // --- Read and Parse CSV ---
    $rowCount = 0;
    $headerFound = false;
    $actualHeaders = [];
    $error = null;

    if (!file_exists($csvFilePath) || !is_readable($csvFilePath)) {
        return ['error' => "Error: Could not open or read the CSV file: " . htmlspecialchars($csvFilePath)];
    }

    if (($handle = fopen($csvFilePath, "r")) !== FALSE) {
        while (($data = fgetcsv($handle)) !== FALSE && $rowCount < $limitRows) {
            $rowCount++;
            // Reconstruct the line as it appears in the file for header comparison
            $line = implode(',', array_map(function($field) { return '"' . str_replace('"', '""', $field) . '"'; }, $data));

            if (!$headerFound) {
                // Check if the current line is the marks header
                if (trim($line) === trim($marksHeader)) {
                    $headerFound = true;
                    // Use the fields from the header line (strip quotes)
                    $actualHeaders = array_map(function($h) { return trim($h, ' "'); }, $data);
                    // Basic check for essential columns
                    if (!in_array('id', $actualHeaders) || !in_array('student_id', $actualHeaders) || !in_array('course_code', $actualHeaders) || !in_array('total', $actualHeaders) || !in_array('grade', $actualHeaders)) {
                         fclose($handle);
                         return ['error' => "Error: Marks header found, but essential columns (id, student_id, course_code, total, grade) are missing."];
                    }
                }
                continue; // Skip lines until the correct header is found
            }

            // Stop if we hit the next section's header or EOF (simple checks)
            if (count($data) < count($actualHeaders)) {
                 break;
            }
            if (count($data) == 3 && ctype_upper(trim($data[0], ' "'))) { // Example check for Courses header
                 break;
            }
            if (count($data) == 2 && preg_match('/^12s\d{3}25$/', trim($data[0], ' "'))) { // Example check for Students header
                 break;
            }
            if (count($data) !== count($actualHeaders)) { // Skip malformed lines within the section
                continue;
            }

            // Combine header with data row, handle potential errors
            try {
                 $row = array_combine($actualHeaders, $data);
            } catch (ValueError $e) {
                 // This can happen if count($data) !== count($actualHeaders) slipped through
                 continue; // Skip this malformed row
            }

            // Clean up data
            $row = array_map(function($value) { return trim($value, ' "'); }, $row);

            // Basic validation
            if (empty($row['student_id']) || empty($row['course_code']) || !is_numeric($row['total']) || empty($row['grade'])) {
                continue; // Skip rows with missing/invalid essential data
            }

            $studentMarks[] = $row; // Keep parsed data if needed elsewhere, though not strictly required for aggregation

            // --- Aggregate Stats ---
            $total = floatval($row['total']);
            $grade = trim(strtoupper($row['grade']));
            $courseCode = trim($row['course_code']);

            // Overall
            $overallStats['total_score_sum'] += $total;
            $overallStats['count']++;
            if (array_key_exists($grade, $overallStats['grades'])) {
                $overallStats['grades'][$grade]++;
            }

            // Per Course
            if (!isset($courses[$courseCode])) {
                $courses[$courseCode] = [
                    'total_score_sum' => 0,
                    'count' => 0,
                    'grades' => ['A+' => 0, 'A' => 0, 'B' => 0, 'C' => 0, 'D' => 0, 'F' => 0]
                ];
            }
            $courses[$courseCode]['total_score_sum'] += $total;
            $courses[$courseCode]['count']++;
            if (array_key_exists($grade, $courses[$courseCode]['grades'])) {
                $courses[$courseCode]['grades'][$grade]++;
            }
        }
        fclose($handle);

        if (!$headerFound) {
             return ['error' => "Error: Specified marks header was not found in the file."];
        }
         if ($overallStats['count'] === 0) {
             return ['error' => "Error: Marks header found, but no valid data rows followed."];
        }


    } else {
        return ['error' => "Error: Could not open the CSV file: " . htmlspecialchars($csvFilePath)];
    }

    // --- Calculate Averages ---
    $overallStats['average_score'] = ($overallStats['count'] > 0) ? round($overallStats['total_score_sum'] / $overallStats['count'], 2) : 0;

    foreach ($courses as $code => $stats) {
        $courses[$code]['average_score'] = ($stats['count'] > 0) ? round($stats['total_score_sum'] / $stats['count'], 2) : 0;
    }

    // Sort courses by code for consistent display
    ksort($courses);

    return [
        'overallStats' => $overallStats,
        'courses' => $courses
    ];
}

// --- Example Usage (Optional - for testing this script directly) ---
/*
$csvFilePath = 'datautas.csv';
$marksHeader = '"id","student_id","course_code","test1","midterm","test2","assignment","total","grade","created_at"';
$analysisResult = analyzeStudentDataFromCSV($csvFilePath, $marksHeader);

if (isset($analysisResult['error'])) {
    echo $analysisResult['error'];
} else {
    echo "<pre>";
    echo "Overall Stats:\n";
    print_r($analysisResult['overallStats']);
    echo "\nCourse Stats:\n";
    print_r($analysisResult['courses']);
    echo "</pre>";
}
*/

?> 