<?php
/**
 * Database Export Page
 * Allows downloading theses from the approved database with filters
 * Filters: Year, Year Range, SDG Tags
 * Last Modified: 2025-11-30
 */

session_start();
require_once("hum_conn_no_login.php");

// Check if user is logged in (optional - remove if public access needed)
$isLoggedIn = isset($_SESSION['user_id']) && isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

// Handle export request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="theses_export_' . date('Y-m-d') . '.csv"');
    
    $connectn = hum_conn_no_login();
    
    // Build query with filters
    $conditions = [];
    $params = [];
    
    // Year filter
    if (!empty($_POST['year'])) {
        $conditions[] = "EXTRACT(YEAR FROM t.publication_date) = :year";
        $params[':year'] = (int)$_POST['year'];
    }
    
    // Year range filter
    if (!empty($_POST['year_start']) && !empty($_POST['year_end'])) {
        $conditions[] = "EXTRACT(YEAR FROM t.publication_date) BETWEEN :year_start AND :year_end";
        $params[':year_start'] = (int)$_POST['year_start'];
        $params[':year_end'] = (int)$_POST['year_end'];
    }
    
    // SDG filter
    $sdgFilter = [];
    if (!empty($_POST['sdgs']) && is_array($_POST['sdgs'])) {
        $sdgFilter = array_map('intval', $_POST['sdgs']);
    }
    
    // Base query
    $sql = "
        SELECT DISTINCT
            t.thesis_id,
            t.title,
            t.author,
            TO_CHAR(t.publication_date, 'YYYY') as year,
            TO_CHAR(t.publication_date, 'YYYY-MM-DD') as publication_date,
            d.department_name,
            t.url,
            t.discipline,
            t.keywords,
            t.abstract,
            (SELECT LISTAGG(m.sdg_number, ', ') WITHIN GROUP (ORDER BY m.ranking)
             FROM sdg_mappings m WHERE m.thesis_id = t.thesis_id) as sdg_numbers,
            (SELECT LISTAGG(m.classification_method, ', ') WITHIN GROUP (ORDER BY m.ranking)
             FROM sdg_mappings m WHERE m.thesis_id = t.thesis_id) as classification_methods
        FROM theses t
        LEFT JOIN departments d ON t.department_id = d.department_id
    ";
    
    // Add SDG filter with JOIN
    if (!empty($sdgFilter)) {
        $sql .= " INNER JOIN sdg_mappings sm ON t.thesis_id = sm.thesis_id AND sm.sdg_number IN (" . implode(',', $sdgFilter) . ")";
    }
    
    if (!empty($conditions)) {
        $sql .= " WHERE " . implode(' AND ', $conditions);
    }
    
    $sql .= " ORDER BY t.publication_date DESC, t.title";
    
    $stmt = oci_parse($connectn, $sql);
    
    foreach ($params as $key => $value) {
        oci_bind_by_name($stmt, $key, $params[$key]);
    }
    
    oci_execute($stmt);
    
    // Output CSV
    $output = fopen('php://output', 'w');
    
    // Header row
    fputcsv($output, [
        'Thesis ID', 'Title', 'Author', 'Year', 'Publication Date', 
        'Department', 'URL', 'Discipline', 'Keywords', 'SDG Numbers', 
        'Classification Methods', 'Abstract'
    ]);
    
    // Data rows
    while ($row = oci_fetch_assoc($stmt)) {
        // Handle CLOB fields
        $abstract = '';
        if (isset($row['ABSTRACT'])) {
            if (is_object($row['ABSTRACT'])) {
                $abstract = $row['ABSTRACT']->read($row['ABSTRACT']->size());
            } else {
                $abstract = $row['ABSTRACT'];
            }
        }
        
        $keywords = '';
        if (isset($row['KEYWORDS'])) {
            if (is_object($row['KEYWORDS'])) {
                $keywords = $row['KEYWORDS']->read($row['KEYWORDS']->size());
            } else {
                $keywords = $row['KEYWORDS'];
            }
        }
        
        fputcsv($output, [
            $row['THESIS_ID'],
            $row['TITLE'],
            $row['AUTHOR'],
            $row['YEAR'],
            $row['PUBLICATION_DATE'],
            $row['DEPARTMENT_NAME'],
            $row['URL'],
            $row['DISCIPLINE'],
            $keywords,
            $row['SDG_NUMBERS'],
            $row['CLASSIFICATION_METHODS'],
            $abstract
        ]);
    }
    
    fclose($output);
    oci_free_statement($stmt);
    oci_close($connectn);
    exit;
}

// Get stats for display
$connectn = hum_conn_no_login();
$stats = [];

// Total count
$countSql = "SELECT COUNT(*) as total FROM theses";
$countStmt = oci_parse($connectn, $countSql);
oci_execute($countStmt);
$countRow = oci_fetch_assoc($countStmt);
$stats['total'] = $countRow['TOTAL'];
oci_free_statement($countStmt);

// Count by year
$yearSql = "
    SELECT EXTRACT(YEAR FROM publication_date) as year, COUNT(*) as count
    FROM theses
    GROUP BY EXTRACT(YEAR FROM publication_date)
    ORDER BY year DESC
";
$yearStmt = oci_parse($connectn, $yearSql);
oci_execute($yearStmt);
$stats['byYear'] = [];
while ($row = oci_fetch_assoc($yearStmt)) {
    $stats['byYear'][$row['YEAR']] = $row['COUNT'];
}
oci_free_statement($yearStmt);

// Count by SDG
$sdgSql = "
    SELECT sdg_number, COUNT(DISTINCT thesis_id) as count
    FROM sdg_mappings
    WHERE thesis_id IS NOT NULL
    GROUP BY sdg_number
    ORDER BY sdg_number
";
$sdgStmt = oci_parse($connectn, $sdgSql);
oci_execute($sdgStmt);
$stats['bySDG'] = [];
while ($row = oci_fetch_assoc($sdgStmt)) {
    $stats['bySDG'][$row['SDG_NUMBER']] = $row['COUNT'];
}
oci_free_statement($sdgStmt);

oci_close($connectn);

$sdgNames = [
    1 => "No Poverty",
    2 => "Zero Hunger", 
    3 => "Good Health and Well-being",
    4 => "Quality Education",
    5 => "Gender Equality",
    6 => "Clean Water and Sanitation",
    7 => "Affordable and Clean Energy",
    8 => "Decent Work and Economic Growth",
    9 => "Industry, Innovation and Infrastructure",
    10 => "Reduced Inequalities",
    11 => "Sustainable Cities and Communities",
    12 => "Responsible Consumption and Production",
    13 => "Climate Action",
    14 => "Life Below Water",
    15 => "Life on Land",
    16 => "Peace, Justice and Strong Institutions"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Export Database - SDG Thesis Classifier</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;850&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    <style>
        .export-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        .export-header {
            text-align: center;
            margin-bottom: 3rem;
        }
        .export-header h1 {
            font-size: 2.5rem;
            color: var(--primary-green);
            margin-bottom: 0.5rem;
        }
        .export-header p {
            color: var(--gray);
            font-size: 1.1rem;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 3rem;
        }
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--shadow-sm);
        }
        .stats-card h3 {
            font-size: 1rem;
            color: var(--gray);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        .stats-card .big-number {
            font-size: 3rem;
            font-weight: 700;
            color: var(--primary-green);
        }
        .stats-list {
            max-height: 200px;
            overflow-y: auto;
        }
        .stats-list-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #eee;
        }
        .stats-list-item:last-child {
            border-bottom: none;
        }
        .export-form {
            background: white;
            border-radius: 12px;
            padding: 2rem;
            box-shadow: var(--shadow-sm);
            margin-bottom: 2rem;
        }
        .export-form h2 {
            margin-bottom: 1.5rem;
            color: var(--primary-green);
        }
        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
        }
        .filter-group input, .filter-group select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
        }
        .sdg-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 0.5rem;
            max-height: 300px;
            overflow-y: auto;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        .sdg-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .sdg-checkbox input {
            width: auto;
        }
        .export-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--primary-green);
            text-decoration: none;
            margin-bottom: 1rem;
        }
        .back-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="export-container">
        <a href="index.php" class="back-link">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <path d="M19 12H5"></path>
                <path d="m12 19-7-7 7-7"></path>
            </svg>
            Back to Classifier
        </a>
        
        <div class="export-header">
            <h1>Database Export</h1>
            <p>Download approved theses data filtered by year, date range, or SDG tags</p>
        </div>
        
        <!-- Stats Overview -->
        <div class="stats-grid">
            <div class="stats-card">
                <h3>Total Approved Theses</h3>
                <div class="big-number"><?php echo number_format($stats['total']); ?></div>
            </div>
            
            <div class="stats-card">
                <h3>By Year</h3>
                <div class="stats-list">
                    <?php foreach ($stats['byYear'] as $year => $count): ?>
                    <div class="stats-list-item">
                        <span><?php echo $year ?: 'Unknown'; ?></span>
                        <strong><?php echo number_format($count); ?></strong>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="stats-card">
                <h3>By SDG</h3>
                <div class="stats-list">
                    <?php for ($i = 1; $i <= 16; $i++): ?>
                    <div class="stats-list-item">
                        <span>SDG <?php echo $i; ?></span>
                        <strong><?php echo number_format($stats['bySDG'][$i] ?? 0); ?></strong>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <!-- SDG Distribution Chart -->
        <div class="export-form" style="margin-bottom: 2rem;">
            <h2>SDG Distribution</h2>
            <p style="color: var(--gray); margin-bottom: 1rem;">Visual breakdown of theses by SDG. Updates based on your filter selections.</p>
            <div style="display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start;">
                <div style="flex: 1; min-width: 300px; max-width: 600px;">
                    <canvas id="exportChart"></canvas>
                </div>
                <div style="flex: 0 0 auto;">
                    <button type="button" id="downloadChartBtn" class="btn-secondary">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect>
                            <circle cx="8.5" cy="8.5" r="1.5"></circle>
                            <polyline points="21 15 16 10 5 21"></polyline>
                        </svg>
                        Download Chart as PNG
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Export Form -->
        <form class="export-form" method="POST" id="exportForm">
            <h2>Export Options</h2>
            
            <div class="filter-grid">
                <div class="filter-group">
                    <label for="year">Specific Year</label>
                    <select name="year" id="year">
                        <option value="">All Years</option>
                        <?php foreach (array_keys($stats['byYear']) as $year): ?>
                        <option value="<?php echo $year; ?>"><?php echo $year ?: 'Unknown'; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="year_start">Year Range Start</label>
                    <input type="number" name="year_start" id="year_start" min="1900" max="2100" placeholder="e.g., 2020">
                </div>
                
                <div class="filter-group">
                    <label for="year_end">Year Range End</label>
                    <input type="number" name="year_end" id="year_end" min="1900" max="2100" placeholder="e.g., 2024">
                </div>
            </div>
            
            <div class="filter-group" style="margin-bottom: 1.5rem;">
                <label>Filter by SDG Tags (optional)</label>
                <div class="sdg-checkboxes">
                    <?php for ($i = 1; $i <= 16; $i++): ?>
                    <label class="sdg-checkbox">
                        <input type="checkbox" name="sdgs[]" value="<?php echo $i; ?>" class="sdg-filter-checkbox">
                        SDG <?php echo $i; ?>: <?php echo $sdgNames[$i]; ?>
                    </label>
                    <?php endfor; ?>
                </div>
            </div>
            
            <div class="export-actions">
                <button type="submit" name="export" value="csv" class="btn-primary btn-large">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"></path>
                        <polyline points="7 10 12 15 17 10"></polyline>
                        <line x1="12" y1="15" x2="12" y2="3"></line>
                    </svg>
                    Download CSV
                </button>
                <button type="reset" class="btn-secondary btn-large">Clear Filters</button>
            </div>
        </form>
        
        <p style="text-align: center; color: var(--gray);">
            <small>Export includes: Title, Author, Year, Department, URL, SDG Tags, Classification Methods, and Abstract</small>
        </p>
    </div>
    
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // SDG Data
        const SDG_DATA = {
            1:  { name: "No Poverty", color: "#E5243B" },
            2:  { name: "Zero Hunger", color: "#DDA63A" },
            3:  { name: "Good Health", color: "#4C9F38" },
            4:  { name: "Quality Education", color: "#C5192D" },
            5:  { name: "Gender Equality", color: "#FF3A21" },
            6:  { name: "Clean Water", color: "#26BDE2" },
            7:  { name: "Clean Energy", color: "#FCC30B" },
            8:  { name: "Decent Work", color: "#A21942" },
            9:  { name: "Innovation", color: "#FD6925" },
            10: { name: "Reduced Inequalities", color: "#DD1367" },
            11: { name: "Sustainable Cities", color: "#FD9D24" },
            12: { name: "Responsible Consumption", color: "#BF8B2E" },
            13: { name: "Climate Action", color: "#3F7E44" },
            14: { name: "Life Below Water", color: "#0A97D9" },
            15: { name: "Life on Land", color: "#56C02B" },
            16: { name: "Peace & Justice", color: "#00689D" }
        };
        
        // Full SDG data from PHP
        const fullSDGData = <?php echo json_encode($stats['bySDG']); ?>;
        
        // Initialize chart data (all SDGs)
        let chartData = [];
        for (let i = 1; i <= 16; i++) {
            chartData.push(fullSDGData[i] || 0);
        }
        
        // Create chart
        const canvas = document.getElementById('exportChart');
        const ctx = canvas.getContext('2d');
        
        const labels = Object.entries(SDG_DATA).map(([n, s]) => `${n}. ${s.name}`);
        const colors = Object.values(SDG_DATA).map(s => s.color);
        
        let exportChart = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: chartData,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                aspectRatio: 1.5,
                plugins: {
                    legend: {
                        position: 'right',
                        labels: {
                            padding: 10,
                            usePointStyle: true,
                            font: { size: 11 }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const sdgNum = context[0].dataIndex + 1;
                                return `SDG ${sdgNum}: ${SDG_DATA[sdgNum].name}`;
                            },
                            label: function(context) {
                                const value = context.raw;
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return `${value} theses (${pct}%)`;
                            }
                        }
                    }
                }
            }
        });
        
        // Update chart when SDG checkboxes change
        document.querySelectorAll('.sdg-filter-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', updateChart);
        });
        
        function updateChart() {
            const checkedSDGs = Array.from(document.querySelectorAll('.sdg-filter-checkbox:checked'))
                .map(cb => parseInt(cb.value));
            
            // Update colors - gray out unselected SDGs
            const newColors = Object.values(SDG_DATA).map((s, i) => {
                const sdgNum = i + 1;
                if (checkedSDGs.length === 0) return s.color;
                return checkedSDGs.includes(sdgNum) ? s.color : '#e0e0e0';
            });
            
            exportChart.data.datasets[0].backgroundColor = newColors;
            exportChart.update();
        }
        
        // Download chart as PNG
        document.getElementById('downloadChartBtn').addEventListener('click', function() {
            const link = document.createElement('a');
            link.download = 'sdg_distribution_chart.png';
            link.href = canvas.toDataURL('image/png');
            link.click();
        });
        
        // Reset chart when form is reset
        document.getElementById('exportForm').addEventListener('reset', function() {
            setTimeout(() => {
                const colors = Object.values(SDG_DATA).map(s => s.color);
                exportChart.data.datasets[0].backgroundColor = colors;
                exportChart.update();
            }, 10);
        });
    </script>
</body>
</html>
