<?php
/**
 * Search API Endpoint
 * Returns theses matching selected SDGs
 * Last Modified: 2025-12-01
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);

try {
    require_once __DIR__ . '/hum_conn_no_login.php';
    
    // Create connection
    $connectn = hum_conn_no_login();
    if (!$connectn) {
        throw new Exception('Database connection failed');
    }

    // Get selected SDGs from query parameter
    $sdgNumbers = [];
    if (isset($_GET['sdgs'])) {
        $sdgsParam = $_GET['sdgs'];
        $sdgNumbers = array_map('intval', explode(',', $sdgsParam));
        $sdgNumbers = array_filter($sdgNumbers, function($sdg) {
            return $sdg >= 1 && $sdg <= 16;
        });
        $sdgNumbers = array_values($sdgNumbers); // Re-index
    }

    if (empty($sdgNumbers)) {
        echo json_encode([]);
        exit;
    }

    $sdgCount = count($sdgNumbers);
    $sdgList = implode(',', $sdgNumbers);

    // Oracle-compatible query using LISTAGG instead of GROUP_CONCAT
    $query = "
        SELECT 
            t.thesis_id,
            t.title,
            t.author,
            TO_CHAR(t.publication_date, 'YYYY-MM-DD') as publication_date,
            t.abstract,
            t.url,
            t.discipline,
            t.keywords,
            d.department_name,
            (SELECT LISTAGG(m.sdg_number, ', ') WITHIN GROUP (ORDER BY m.ranking)
             FROM sdg_mappings m WHERE m.thesis_id = t.thesis_id) as sdg_numbers
        FROM theses t
        LEFT JOIN departments d ON t.department_id = d.department_id
        WHERE t.thesis_id IN (
            SELECT thesis_id
            FROM sdg_mappings
            WHERE sdg_number IN ($sdgList)
            GROUP BY thesis_id
            HAVING COUNT(DISTINCT sdg_number) = :sdgCount
        )
        ORDER BY t.publication_date DESC NULLS LAST, t.title ASC
    ";
    
    $stmt = oci_parse($connectn, $query);
    if (!$stmt) {
        $e = oci_error($connectn);
        throw new Exception('Query parse error: ' . $e['message']);
    }
    
    oci_bind_by_name($stmt, ':sdgCount', $sdgCount);
    
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        throw new Exception('Query execution error: ' . $e['message']);
    }
    
    $results = [];
    while ($row = oci_fetch_assoc($stmt)) {
        // Handle CLOB fields - check for empty/null before reading
        $abstract = '';
        if (isset($row['ABSTRACT']) && $row['ABSTRACT'] !== null) {
            if (is_object($row['ABSTRACT'])) {
                $size = $row['ABSTRACT']->size();
                if ($size > 0) {
                    $abstract = $row['ABSTRACT']->read($size);
                }
            } else {
                $abstract = $row['ABSTRACT'];
            }
        }
        
        $keywords = '';
        if (isset($row['KEYWORDS']) && $row['KEYWORDS'] !== null) {
            if (is_object($row['KEYWORDS'])) {
                $size = $row['KEYWORDS']->size();
                if ($size > 0) {
                    $keywords = $row['KEYWORDS']->read($size);
                }
            } else {
                $keywords = $row['KEYWORDS'];
            }
        }
        
        $results[] = [
            'thesis_id' => $row['THESIS_ID'],
            'title' => $row['TITLE'],
            'author' => $row['AUTHOR'],
            'publication_date' => $row['PUBLICATION_DATE'],
            'department_name' => $row['DEPARTMENT_NAME'],
            'abstract' => $abstract,
            'url' => $row['URL'],
            'discipline' => $row['DISCIPLINE'],
            'keywords' => $keywords,
            'sdg_numbers' => $row['SDG_NUMBERS']
        ];
    }
    
    oci_free_statement($stmt);
    oci_close($connectn);

    echo json_encode($results);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
