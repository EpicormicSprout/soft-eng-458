<?php
/**
 * Get SDG Distribution Data for Chart
 * Returns JSON array of publication counts per SDG
 * Last Modified: 11/09/2025
 */

header('Content-Type: application/json');

try {
    require_once("hum_conn_no_login.php");
    $conn = hum_conn_no_login();
    
    // Query to get publication count per SDG from approved theses
    $query = "
        SELECT sdg_number, COUNT(DISTINCT thesis_id) AS publication_count
        FROM sdg_mappings
        WHERE thesis_id IS NOT NULL
        GROUP BY sdg_number
        ORDER BY sdg_number
    ";
    
    $stmt = oci_parse($conn, $query);
    
    if (!oci_execute($stmt)) {
        $e = oci_error($stmt);
        throw new Exception($e['message']);
    }
    
    // Initialize array with 0 for all 16 SDGs
    $sdgCounts = array_fill(0, 16, 0);
    
    // Fill in actual counts from database
    while ($row = oci_fetch_assoc($stmt)) {
        $sdgNum = (int)$row['SDG_NUMBER'];
        $count = (int)$row['PUBLICATION_COUNT'];
        
        if ($sdgNum >= 1 && $sdgNum <= 16) {
            $sdgCounts[$sdgNum - 1] = $count;
        }
    }
    
    oci_free_statement($stmt);
    
    echo json_encode([
        'success' => true,
        'data' => $sdgCounts
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
