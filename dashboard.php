<?php
require_once 'analyze_csv.php';

// --- Configuration ---
$csvFilePath = 'datautas.csv';
$marksHeader = '"id","student_id","course_code","test1","midterm","test2","assignment","total","grade","created_at"';

// --- Get Data ---
$analysisResult = analyzeStudentDataFromCSV($csvFilePath, $marksHeader);

// Check for errors returned from the analysis function
$error_message = null;
if (isset($analysisResult['error'])) {
    $error_message = $analysisResult['error'];
    $overallStats = null;
    $courses = null;
} else {
    $overallStats = $analysisResult['overallStats'];
    $courses = $analysisResult['courses'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Performance Dashboard</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            padding: 0;
            margin: 0;
            background-color: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        header {
            background-color: #005a87; /* UTAS Blue */
            color: #fff;
            padding: 15px 25px;
            border-radius: 8px 8px 0 0;
            margin: -20px -20px 20px -20px; /* Extend to container edges */
        }
        header h1 {
            margin: 0;
            font-size: 1.8em;
            font-weight: 500;
        }
        h2 {
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 8px;
            margin-top: 40px;
            margin-bottom: 20px;
            color: #005a87;
            font-weight: 600;
        }
        h3 {
            margin-top: 15px;
            margin-bottom: 10px;
            color: #495057;
            font-weight: 600;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin-top: 15px;
            font-size: 0.95em;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        th, td {
            border: 1px solid #dee2e6;
            padding: 10px 12px;
            text-align: left;
            vertical-align: middle;
        }
        th {
            background-color: #e9ecef;
            font-weight: 600;
            color: #495057;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e9ecef;
        }
        .stats-summary {
            background-color: #e9f7fd;
            padding: 20px;
            border: 1px solid #bce0ee;
            border-radius: 5px;
            margin-bottom: 30px;
        }
        .stats-summary p {
            margin: 5px 0;
            font-size: 1.1em;
        }
        .stats-summary p strong {
            color: #005a87;
        }
        .error {
             background-color: #f8d7da;
             color: #721c24;
             border: 1px solid #f5c6cb;
             padding: 15px;
             border-radius: 5px;
             margin-bottom: 20px;
        }
        .grade-dist th, .grade-dist td {
            text-align: center;
            width: 12%; /* Adjust as needed */
        }
        .grade-dist .percentage {
            font-size: 0.85em;
            color: #6c757d;
        }
         /* Style for per-course grade columns */
        .per-course-stats td:nth-child(n+4) {
             text-align: center;
        }
        footer {
            text-align: center;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 0.9em;
            color: #6c757d;
        }
    </style>
</head>
<body>

<div class="container">
    <header>
        <h1>Student Performance Dashboard</h1>
    </header>

    <?php if ($error_message): ?>
        <div class="error"><?php echo $error_message; ?></div>
    <?php elseif ($overallStats && $courses): ?>
        <div class="stats-summary">
            <h2>Overall Statistics</h2>
            <p>Total Records Analyzed: <strong><?php echo htmlspecialchars($overallStats['count']); ?></strong></p>
            <p>Overall Average Score: <strong><?php echo htmlspecialchars($overallStats['average_score']); ?></strong></p>
            <h3>Overall Grade Distribution:</h3>
            <table class="grade-dist">
                <thead>
                    <tr>
                        <?php foreach (array_keys($overallStats['grades']) as $grade): ?>
                            <th><?php echo htmlspecialchars($grade); ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <?php foreach ($overallStats['grades'] as $count): ?>
                            <td><?php echo htmlspecialchars($count); ?></td>
                        <?php endforeach; ?>
                    </tr>
                     <tr>
                        <?php foreach ($overallStats['grades'] as $grade => $count): ?>
                            <td class="percentage"><?php echo ($overallStats['count'] > 0) ? round(($count / $overallStats['count']) * 100, 1) . '%' : '0%'; ?></td>
                        <?php endforeach; ?>
                    </tr>
                </tbody>
            </table>
        </div>

        <h2>Per Course Statistics</h2>
        <table class="per-course-stats">
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Student Count</th>
                    <th>Average Score</th>
                    <th>A+</th>
                    <th>A</th>
                    <th>B</th>
                    <th>C</th>
                    <th>D</th>
                    <th>F</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($courses as $code => $stats): ?>
                <tr>
                    <td><?php echo htmlspecialchars($code); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($stats['count']); ?></td>
                    <td style="text-align: center;"><?php echo htmlspecialchars($stats['average_score']); ?></td>
                    <?php foreach ($stats['grades'] as $grade => $count): ?>
                        <td>
                            <?php echo htmlspecialchars($count); ?><br>
                            <span class="percentage">(<?php echo ($stats['count'] > 0) ? round(($count / $stats['count']) * 100, 1) . '%' : '0%'; ?>)</span>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

    <?php else: ?>
         <div class="error">An unexpected issue occurred. No analysis data is available.</div>
    <?php endif; ?>

    <footer>
        Data sourced from: <?php echo htmlspecialchars($csvFilePath); ?>
    </footer>

</div>

</body>
</html> 