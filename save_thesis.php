<?php
/**
 * Save Thesis - Updated with admin bypass and 75% threshold
 * Admin users always save to theses table (approved)
 * Non-admin users need 75% threshold to save to theses table
 * Last Modified: 2025-11-30
 */

header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 0);
session_start();

// Updated threshold to 75%
define('MIN_CONFIDENCE_THRESHOLD', 0.75);

try {
    $raw = file_get_contents('php://input');
    if ($raw === false) throw new Exception("No input");

    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    $abstract = trim($data['abstract'] ?? '');
    $preds = $data['predictions'] ?? [];
    $noRelevantTags = $data['noRelevantTags'] ?? false;
    
    // Support both flat format (from new JS) and nested metadata format (from original)
    $meta = $data['metadata'] ?? $data;

    $title = trim($meta['title'] ?? $data['title'] ?? '');
    $author = trim($meta['author'] ?? $data['author'] ?? '');
    $pubDate = trim($meta['publicationDate'] ?? $meta['pubDate'] ?? $data['pubDate'] ?? '');
    $departmentInput = $meta['department'] ?? $data['department'] ?? null;
    $url = trim($meta['url'] ?? $data['url'] ?? '') ?: null;
    $discipline = trim($meta['discipline'] ?? $data['discipline'] ?? '') ?: null;
    $keywordsA = $meta['keywords'] ?? $data['keywords'] ?? [];

    // Department is now optional - only check title, author, date, abstract
    if ($title === '' || $author === '' || $pubDate === '' || $abstract === '') {
        throw new Exception('Missing required fields: title=' . ($title ? 'OK' : 'MISSING') . 
                          ', author=' . ($author ? 'OK' : 'MISSING') . 
                          ', pubDate=' . ($pubDate ? 'OK' : 'MISSING') . 
                          ', abstract=' . ($abstract ? 'OK' : 'MISSING'));
    }

    $keywords = '';
    if (is_array($keywordsA)) {
        $keywords = implode(', ', array_values(array_filter(
            array_map('trim', $keywordsA),
            fn($s) => $s !== ''
        )));
    }

    // Check if user is logged in as admin
    $isAdmin = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
    $adminUserId = $_SESSION['user_id'] ?? null;
    $adminRole = $_SESSION['role'] ?? null;

    // Filter predictions by 75% threshold OR manual edits (score = 1.0)
    $highConfidencePreds = array_filter($preds, function($p) {
        $score = $p['score'] ?? 0;
        $isManual = $p['isManualEdit'] ?? false;
        return $score >= MIN_CONFIDENCE_THRESHOLD || $isManual || $score >= 1.0;
    });
    $highConfidencePreds = array_values($highConfidencePreds);
    usort($highConfidencePreds, fn($a, $b) => ($b['score'] ?? 0) <=> ($a['score'] ?? 0));
    $topPreds = array_slice($highConfidencePreds, 0, 3);

    require_once __DIR__ . '/hum_conn_no_login.php';
    $connectn = hum_conn_no_login();

    if (!$connectn) throw new Exception('Database connection failed');

    // Check for duplicates (same title + author)
    $duplicateCheck = "
        SELECT thesis_id, title, author 
        FROM theses 
        WHERE LOWER(title) = LOWER(:title) 
        AND LOWER(author) = LOWER(:author)
    ";
    $dupStmt = oci_parse($connectn, $duplicateCheck);
    oci_bind_by_name($dupStmt, ':title', $title);
    oci_bind_by_name($dupStmt, ':author', $author);
    oci_execute($dupStmt);
    $existingRecord = oci_fetch_assoc($dupStmt);
    oci_free_statement($dupStmt);
    
    if ($existingRecord) {
        // Return warning but still allow save if forced
        $forceOverwrite = $data['forceOverwrite'] ?? false;
        if (!$forceOverwrite) {
            echo json_encode([
                'ok' => false,
                'duplicate' => true,
                'existingId' => $existingRecord['THESIS_ID'],
                'error' => 'A thesis with this title and author already exists (ID: ' . $existingRecord['THESIS_ID'] . '). Set forceOverwrite=true to save anyway.',
                'existingTitle' => $existingRecord['TITLE'],
                'existingAuthor' => $existingRecord['AUTHOR']
            ]);
            exit;
        }
    }

    // Get department ID (optional)
    $deptId = null;
    if ($departmentInput !== null && $departmentInput !== '' && $departmentInput !== 'null') {
        if (is_numeric($departmentInput)) {
            $deptId = (int)$departmentInput;
        } else {
            $deptQuery = "SELECT department_id FROM departments WHERE LOWER(department_name) = LOWER(:name)";
            $deptStmt = oci_parse($connectn, $deptQuery);
            $deptName = trim($departmentInput);
            oci_bind_by_name($deptStmt, ':name', $deptName);
            if (oci_execute($deptStmt)) {
                $deptRow = oci_fetch_assoc($deptStmt);
                if ($deptRow) $deptId = (int)$deptRow['DEPARTMENT_ID'];
            }
            oci_free_statement($deptStmt);
        }
    }

    // If department not found, try to get "Other" or leave as null
    if ($deptId === null) {
        $defaultDeptQuery = "SELECT department_id FROM departments WHERE department_name = 'Other'";
        $defaultDeptStmt = oci_parse($connectn, $defaultDeptQuery);
        if (oci_execute($defaultDeptStmt)) {
            $defaultRow = oci_fetch_assoc($defaultDeptStmt);
            if ($defaultRow) $deptId = (int)$defaultRow['DEPARTMENT_ID'];
        }
        oci_free_statement($defaultDeptStmt);
    }

    // KEY LOGIC: Determine where to save
    // - If ADMIN is logged in: ALWAYS save to theses table (approved, counts in chart)
    // - If NOT admin: Only save to theses if predictions meet 75% threshold
    // - If NOT admin AND below threshold: save to pending_theses
    
    if ($isAdmin) {
        // Admin users: always save to theses table (approved)
        // But still only save predictions that meet 75% threshold OR are manually edited
        $saveToApproved = true;
        $predsToSave = $topPreds; // Use filtered high-confidence predictions, not all
    } else {
        // Non-admin: only approved if meets threshold
        $saveToApproved = !empty($topPreds);
        $predsToSave = $saveToApproved ? $topPreds : $preds;
    }

    if ($saveToApproved) {
        // Save to theses table (approved - counts in chart/metrics)
        $sql = "
            INSERT INTO theses (
                title, author, publication_date, department_id, abstract,
                url, discipline, keywords, approved_by, approved_at
            ) VALUES (
                :title, :author, TO_DATE(:pubdate, 'YYYY-MM-DD'), :dept, :abstract,
                :url, :discipline, :keywords, :approved_by, SYSTIMESTAMP
            )
            RETURNING thesis_id INTO :out_id
        ";
        
        $stmt = oci_parse($connectn, $sql);
        if (!$stmt) throw new Exception("Parse error: " . oci_error($connectn)['message']);

        $outId = 0;
        oci_bind_by_name($stmt, ':title', $title);
        oci_bind_by_name($stmt, ':author', $author);
        oci_bind_by_name($stmt, ':pubdate', $pubDate);
        oci_bind_by_name($stmt, ':dept', $deptId, -1, SQLT_INT);
        oci_bind_by_name($stmt, ':url', $url);
        oci_bind_by_name($stmt, ':discipline', $discipline);
        oci_bind_by_name($stmt, ':approved_by', $adminUserId, -1, SQLT_INT);
        oci_bind_by_name($stmt, ':out_id', $outId, 40, SQLT_INT);
        
        $idField = 'thesis_id';
        $statusLabel = 'approved';
        
    } else {
        // Save to pending_theses table (does NOT count in chart/metrics)
        $sql = "
            INSERT INTO pending_theses (
                title, author, publication_date, department_id, abstract,
                url, discipline, keywords, submitter_ip, submitter_email, status
            ) VALUES (
                :title, :author, TO_DATE(:pubdate, 'YYYY-MM-DD'), :dept, :abstract,
                :url, :discipline, :keywords, :ip, :email, 'needs_review'
            )
            RETURNING pending_id INTO :out_id
        ";
        
        $stmt = oci_parse($connectn, $sql);
        if (!$stmt) throw new Exception("Parse error: " . oci_error($connectn)['message']);

        $outId = 0;
        oci_bind_by_name($stmt, ':title', $title);
        oci_bind_by_name($stmt, ':author', $author);
        oci_bind_by_name($stmt, ':pubdate', $pubDate);
        oci_bind_by_name($stmt, ':dept', $deptId, -1, SQLT_INT);
        oci_bind_by_name($stmt, ':url', $url);
        oci_bind_by_name($stmt, ':discipline', $discipline);
        
        $submitterEmail = 'web_submission@system';
        $submitterIp = $_SERVER['REMOTE_ADDR'] ?? 'system';
        oci_bind_by_name($stmt, ':ip', $submitterIp);
        oci_bind_by_name($stmt, ':email', $submitterEmail);
        oci_bind_by_name($stmt, ':out_id', $outId, 40, SQLT_INT);
        
        $idField = 'pending_id';
        $statusLabel = 'pending_review';
    }

    // Handle CLOB fields
    $abstractClob = oci_new_descriptor($connectn, OCI_D_LOB);
    $keywordsClob = oci_new_descriptor($connectn, OCI_D_LOB);

    oci_bind_by_name($stmt, ':abstract', $abstractClob, -1, OCI_B_CLOB);
    oci_bind_by_name($stmt, ':keywords', $keywordsClob, -1, OCI_B_CLOB);

    $abstractClob->writeTemporary($abstract, OCI_TEMP_CLOB);
    $keywordsClob->writeTemporary($keywords, OCI_TEMP_CLOB);

    if (!oci_execute($stmt, OCI_NO_AUTO_COMMIT)) {
        $e = oci_error($stmt);
        $abstractClob->free();
        $keywordsClob->free();
        throw new Exception("Insert failed: " . $e['message']);
    }

    oci_free_statement($stmt);

    // SDG mappings (only if we have predictions to save)
    $savedCount = 0;
    $skippedCount = 0;
    $savedSDGs = []; // Track which SDGs we've already saved to avoid duplicates
    
    if (!empty($predsToSave) && !$noRelevantTags) {
        $mapSql = "
            INSERT INTO sdg_mappings ($idField, sdg_number, confidence_score, ranking, classification_method)
            VALUES (:id, :sdg, :score, :rank, :method)
        ";
        $mapStmt = oci_parse($connectn, $mapSql);

        foreach ($predsToSave as $i => $p) {
            // Extract SDG number - try multiple sources
            $sdg = null;
            
            // Try 'id' field first
            if (isset($p['id']) && is_numeric($p['id'])) {
                $sdg = (int)$p['id'];
            }
            
            // Try extracting from label if id is missing or invalid
            if (($sdg === null || $sdg < 1 || $sdg > 16) && isset($p['label'])) {
                preg_match('/(\d+)/', $p['label'], $matches);
                if (!empty($matches[1])) {
                    $sdg = (int)$matches[1];
                }
            }
            
            // Skip if SDG number is still invalid
            if ($sdg === null || $sdg < 1 || $sdg > 16) {
                $skippedCount++;
                continue; // Skip this prediction
            }
            
            // Skip if we've already saved this SDG for this record (avoid duplicate constraint violation)
            if (in_array($sdg, $savedSDGs)) {
                $skippedCount++;
                continue;
            }
            
            $savedSDGs[] = $sdg; // Mark this SDG as saved
            
            $score = (float)($p['score'] ?? 0);
            $rank = count($savedSDGs); // Rank based on order saved (1, 2, 3)
            $isManual = $p['isManualEdit'] ?? false;
            
            // Classification method based on how it was saved
            // Valid values per constraint: 'ai_auto', 'manual_edit', 'admin_override'
            if ($isManual) {
                $method = 'manual_edit';
            } elseif ($isAdmin) {
                $method = 'admin_override';
            } elseif ($saveToApproved) {
                $method = 'ai_auto';
            } else {
                $method = 'ai_auto'; // Low confidence still uses ai_auto
            }

            oci_bind_by_name($mapStmt, ':id', $outId, -1, SQLT_INT);
            oci_bind_by_name($mapStmt, ':sdg', $sdg, -1, SQLT_INT);
            oci_bind_by_name($mapStmt, ':score', $score);
            oci_bind_by_name($mapStmt, ':rank', $rank, -1, SQLT_INT);
            oci_bind_by_name($mapStmt, ':method', $method);

            if (!oci_execute($mapStmt, OCI_NO_AUTO_COMMIT)) {
                throw new Exception("SDG mapping failed: " . oci_error($mapStmt)['message']);
            }
            $savedCount++;
        }
        oci_free_statement($mapStmt);
    }

    if (!oci_commit($connectn)) throw new Exception("Commit failed: " . oci_error($connectn)['message']);

    $abstractClob->free();
    $keywordsClob->free();

    echo json_encode([
        'ok' => true,
        'status' => $statusLabel,
        ($saveToApproved ? 'thesisId' : 'pendingId') => (int)$outId,
        'savedPredictions' => $savedCount,
        'threshold' => MIN_CONFIDENCE_THRESHOLD * 100 . '%',
        'isAdmin' => $isAdmin,
        'savedToApproved' => $saveToApproved
    ]);

} catch (Throwable $e) {
    if (isset($connectn)) @oci_rollback($connectn);
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
