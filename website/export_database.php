<?php
session_start();
require_once("hum_conn_no_login.php");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['export'])) {
    while (ob_get_level()) ob_end_clean();
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="theses_export_' . date('Y-m-d') . '.csv"');
    
    $connectn = hum_conn_no_login();
    
    $conditions = [];
    
    if (!empty($_POST['sdgs']) && is_array($_POST['sdgs'])) {
        $sdgList = implode(',', array_map('intval', $_POST['sdgs']));
        $conditions[] = "t.thesis_id IN (SELECT thesis_id FROM sdg_mappings WHERE sdg_number IN ($sdgList))";
    }
    
    if (!empty($_POST['year']) && $_POST['year'] !== 'all') {
        $year = (int)$_POST['year'];
        $conditions[] = "EXTRACT(YEAR FROM t.publication_date) = $year";
    }
    
    if (!empty($_POST['year_start']) && !empty($_POST['year_end'])) {
        $yearStart = (int)$_POST['year_start'];
        $yearEnd = (int)$_POST['year_end'];
        $conditions[] = "EXTRACT(YEAR FROM t.publication_date) BETWEEN $yearStart AND $yearEnd";
    }
    
    $whereClause = '';
    if (!empty($conditions)) {
        $whereClause = ' WHERE ' . implode(' AND ', $conditions);
    }
    
    $sql = "SELECT t.thesis_id, t.title, t.author, 
            TO_CHAR(t.publication_date, 'YYYY-MM-DD') as publication_date,
            d.department_name, t.url, t.abstract,
            (SELECT LISTAGG(m.sdg_number, ', ') WITHIN GROUP (ORDER BY m.ranking)
             FROM sdg_mappings m WHERE m.thesis_id = t.thesis_id) as sdg_numbers
            FROM theses t
            LEFT JOIN departments d ON t.department_id = d.department_id
            $whereClause
            ORDER BY t.publication_date DESC NULLS LAST";
    
    $stmt = oci_parse($connectn, $sql);
    oci_execute($stmt);
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    fputcsv($output, ['ID', 'Title', 'Author', 'Date', 'Department', 'URL', 'SDGs', 'Abstract']);
    
    while ($row = oci_fetch_assoc($stmt)) {
        $abstract = is_object($row['ABSTRACT']) ? $row['ABSTRACT']->read($row['ABSTRACT']->size()) : ($row['ABSTRACT'] ?? '');
        fputcsv($output, [
            $row['THESIS_ID'], $row['TITLE'], $row['AUTHOR'], $row['PUBLICATION_DATE'],
            $row['DEPARTMENT_NAME'], $row['URL'], $row['SDG_NUMBERS'], $abstract
        ]);
    }
    fclose($output);
    exit;
}

$connectn = hum_conn_no_login();
$stats = ['total' => 0, 'bySDG' => [], 'byYear' => []];

$stmt = oci_parse($connectn, "SELECT COUNT(*) as total FROM theses");
oci_execute($stmt);
$row = oci_fetch_assoc($stmt);
$stats['total'] = $row['TOTAL'];

$stmt = oci_parse($connectn, "SELECT sdg_number, COUNT(DISTINCT thesis_id) as count FROM sdg_mappings WHERE thesis_id IS NOT NULL GROUP BY sdg_number ORDER BY sdg_number");
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
    $stats['bySDG'][(int)$row['SDG_NUMBER']] = (int)$row['COUNT'];
}

$stmt = oci_parse($connectn, "SELECT EXTRACT(YEAR FROM publication_date) as year, COUNT(*) as count FROM theses WHERE publication_date IS NOT NULL GROUP BY EXTRACT(YEAR FROM publication_date) ORDER BY year DESC");
oci_execute($stmt);
while ($row = oci_fetch_assoc($stmt)) {
    if ($row['YEAR']) {
        $stats['byYear'][(int)$row['YEAR']] = (int)$row['COUNT'];
    }
}

oci_close($connectn);

$sdgNames = [
    1 => "No Poverty", 2 => "Zero Hunger", 3 => "Good Health", 4 => "Quality Education",
    5 => "Gender Equality", 6 => "Clean Water", 7 => "Clean Energy", 8 => "Decent Work",
    9 => "Innovation", 10 => "Reduced Inequalities", 11 => "Sustainable Cities",
    12 => "Responsible Consumption", 13 => "Climate Action", 14 => "Life Below Water",
    15 => "Life on Land", 16 => "Peace & Justice"
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Export Database - SDG Thesis Classifier</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="app.css">
    <style>
        .export-container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
        .export-header { text-align: center; margin-bottom: 2rem; }
        .export-header h1 { font-size: 2.5rem; color: var(--primary-green); }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 1.5rem; margin-bottom: 2rem; }
        .stats-card { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); }
        .stats-card h3 { font-size: 1rem; color: #666; margin-bottom: 1rem; text-transform: uppercase; }
        .big-number { font-size: 3rem; font-weight: 700; color: var(--primary-green); }
        .stats-list { max-height: 200px; overflow-y: auto; }
        .stats-list-item { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #eee; }
        .export-section { background: white; border-radius: 12px; padding: 2rem; box-shadow: 0 2px 8px rgba(0,0,0,0.1); margin-bottom: 2rem; }
        .export-section h2 { color: var(--primary-green); margin-bottom: 1rem; }
        .back-link { display: inline-flex; align-items: center; gap: 0.5rem; color: var(--primary-green); text-decoration: none; margin-bottom: 1rem; }
        .chart-section { display: flex; flex-wrap: wrap; gap: 2rem; align-items: flex-start; }
        .chart-container { flex: 1; min-width: 300px; max-width: 500px; }
        .chart-legend { flex: 1; min-width: 250px; }
        .legend-item { display: flex; align-items: center; gap: 0.5rem; padding: 0.35rem 0; font-size: 0.9rem; }
        .legend-color { width: 16px; height: 16px; border-radius: 50%; flex-shrink: 0; }
        .legend-label { flex: 1; }
        .legend-value { font-weight: 600; color: var(--primary-green); }
        .filter-row { display: flex; flex-wrap: wrap; gap: 1.5rem; margin-bottom: 1.5rem; align-items: flex-end; }
        .filter-group { display: flex; flex-direction: column; gap: 0.5rem; }
        .filter-group label { font-weight: 600; color: #333; }
        .filter-group select, .filter-group input { padding: 0.5rem; font-size: 1rem; border: 1px solid #ccc; border-radius: 4px; min-width: 150px; }
        .btn-row { margin-top: 1rem; display: flex; gap: 1rem; flex-wrap: wrap; }
        .sdg-checkboxes { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 0.5rem; padding: 1rem; background: #f8f9fa; border-radius: 8px; margin-top: 0.5rem; }
        .sdg-checkbox { display: flex; align-items: center; gap: 0.5rem; cursor: pointer; }
        .sdg-checkbox input { margin: 0; }
    </style>
</head>
<body>
    <div class="export-container">
        <a href="index.php" class="back-link">← Back to Classifier</a>
        
        <div class="export-header">
            <h1>Database Export</h1>
            <p>Download approved theses data</p>
        </div>
        
        <div class="stats-grid">
            <div class="stats-card">
                <h3>Total Theses</h3>
                <div class="big-number"><?php echo number_format($stats['total']); ?></div>
            </div>
            <div class="stats-card">
                <h3>By Year</h3>
                <div class="stats-list">
                    <?php foreach ($stats['byYear'] as $year => $count): ?>
                    <div class="stats-list-item">
                        <span><?php echo $year; ?></span>
                        <strong><?php echo $count; ?></strong>
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
                        <strong><?php echo $stats['bySDG'][$i] ?? 0; ?></strong>
                    </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <div class="export-section">
            <h2>SDG Distribution</h2>
            <div class="chart-section">
                <div class="chart-container">
                    <canvas id="sdgChart"></canvas>
                </div>
                <div class="chart-legend" id="chartLegend"></div>
            </div>
            <div class="btn-row" style="margin-top: 1.5rem;">
                <button type="button" id="downloadChartBtn" class="btn-secondary">Download Chart as PNG</button>
            </div>
        </div>
        
        <div class="export-section">
            <h2>Export Data</h2>
            <form method="POST">
                <div class="filter-row">
                    <div class="filter-group">
                        <label>Filter by Year:</label>
                        <select name="year">
                            <option value="all">All Years</option>
                            <?php foreach (array_keys($stats['byYear']) as $year): ?>
                            <option value="<?php echo $year; ?>"><?php echo $year; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Or Year Range Start:</label>
                        <input type="number" name="year_start" min="1900" max="2100" placeholder="e.g. 2020">
                    </div>
                    <div class="filter-group">
                        <label>Year Range End:</label>
                        <input type="number" name="year_end" min="1900" max="2100" placeholder="e.g. 2024">
                    </div>
                </div>
                
                <div class="filter-group">
                    <label>Filter by SDG (leave unchecked for all):</label>
                    <div class="sdg-checkboxes">
                        <?php for ($i = 1; $i <= 16; $i++): ?>
                        <label class="sdg-checkbox">
                            <input type="checkbox" name="sdgs[]" value="<?php echo $i; ?>">
                            SDG <?php echo $i; ?>: <?php echo $sdgNames[$i]; ?>
                        </label>
                        <?php endfor; ?>
                    </div>
                </div>
                
                <div class="btn-row">
                    <button type="submit" name="export" value="csv" class="btn-primary btn-large">Download CSV</button>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        const SDG_COLORS = {
            1: "#E5243B", 2: "#DDA63A", 3: "#4C9F38", 4: "#C5192D",
            5: "#FF3A21", 6: "#26BDE2", 7: "#FCC30B", 8: "#A21942",
            9: "#FD6925", 10: "#DD1367", 11: "#FD9D24", 12: "#BF8B2E",
            13: "#3F7E44", 14: "#0A97D9", 15: "#56C02B", 16: "#00689D"
        };
        
        const SDG_NAMES = {
            1: "No Poverty", 2: "Zero Hunger", 3: "Good Health", 4: "Quality Education",
            5: "Gender Equality", 6: "Clean Water", 7: "Clean Energy", 8: "Decent Work",
            9: "Innovation", 10: "Reduced Inequalities", 11: "Sustainable Cities",
            12: "Responsible Consumption", 13: "Climate Action", 14: "Life Below Water",
            15: "Life on Land", 16: "Peace & Justice"
        };
        
        const sdgData = <?php echo json_encode($stats['bySDG']); ?>;
        
        const labels = [];
        const data = [];
        const colors = [];
        
        for (let i = 1; i <= 16; i++) {
            labels.push("SDG " + i);
            data.push(sdgData[i] || 0);
            colors.push(SDG_COLORS[i]);
        }
        
        const total = data.reduce((a, b) => a + b, 0);
        
        const ctx = document.getElementById('sdgChart').getContext('2d');
        new Chart(ctx, {
            type: 'pie',
            data: {
                labels: labels,
                datasets: [{
                    data: data,
                    backgroundColor: colors,
                    borderColor: '#fff',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: function(context) {
                                const idx = context[0].dataIndex + 1;
                                return "SDG " + idx + ": " + SDG_NAMES[idx];
                            },
                            label: function(context) {
                                const value = context.raw;
                                const pct = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                                return value + " theses (" + pct + "%)";
                            }
                        }
                    }
                }
            }
        });
        
        const legendContainer = document.getElementById('chartLegend');
        for (let i = 1; i <= 16; i++) {
            const count = sdgData[i] || 0;
            const pct = total > 0 ? ((count / total) * 100).toFixed(1) : 0;
            const item = document.createElement('div');
            item.className = 'legend-item';
            item.innerHTML = '<div class="legend-color" style="background:' + SDG_COLORS[i] + '"></div>' +
                '<span class="legend-label">SDG ' + i + ': ' + SDG_NAMES[i] + '</span>' +
                '<span class="legend-value">' + count + ' (' + pct + '%)</span>';
            legendContainer.appendChild(item);
        }
        
        document.getElementById('downloadChartBtn').addEventListener('click', function() {
            const link = document.createElement('a');
            link.download = 'sdg_distribution.png';
            link.href = document.getElementById('sdgChart').toDataURL('image/png');
            link.click();
        });
    </script>
</body>
</html>
