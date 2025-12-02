<?php
/**
 * Export Results Handler
 * Handles CSV and BibTeX exports of search results
 * Last Modified: 11/09/2025
 */

require_once("hum_conn_no_login.php");
require_once("dbFunctions.php");

// Get parameters
$sdgNumbers = [];
if (isset($_GET['sdgs'])) {
    $sdgsParam = $_GET['sdgs'];
    $sdgNumbers = array_map('intval', explode(',', $sdgsParam));
    $sdgNumbers = array_filter($sdgNumbers, function($sdg) {
        return $sdg >= 1 && $sdg <= 16;
    });
}

$format = isset($_GET['format']) ? $_GET['format'] : 'csv';

// Validate format
if (!in_array($format, ['csv', 'bibtex'])) {
    die('Invalid export format');
}

// Get search results
if (empty($sdgNumbers)) {
    die('No SDGs selected');
}

$results = searchThesesBySDGs($sdgNumbers);

if (empty($results)) {
    die('No results to export');
}

// Generate filename
$sdgString = implode('-', $sdgNumbers);
$dateString = date('Y-m-d');
$filename = "sdg_search_{$sdgString}_{$dateString}";

// Export based on format
if ($format === 'csv') {
    // CSV Export
    $csvContent = exportResultsToCSV($results, $sdgNumbers);
    
    header('Content-Type: text/csv; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.csv\"");
    header('Content-Length: ' . strlen($csvContent));
    
    echo $csvContent;
    
} elseif ($format === 'bibtex') {
    // BibTeX Export
    $bibtexContent = exportResultsToBibTeX($results);
    
    header('Content-Type: text/plain; charset=utf-8');
    header("Content-Disposition: attachment; filename=\"{$filename}.bib\"");
    header('Content-Length: ' . strlen($bibtexContent));
    
    echo $bibtexContent;
}

exit;
?>
