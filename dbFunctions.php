<?php
/**
 * Database Functions for SDG Thesis Classifier
 * Cal Poly Humboldt
 * Last Modified: 11/09/2025
 */

/**
 * Search theses that match ALL selected SDGs (conditional AND)
 * 
 * @param array $sdgNumbers Array of SDG numbers (1-16)
 * @return array Array of thesis records with SDG information
 */
function searchThesesBySDGs($sdgNumbers) {
    global $connectn;
    
    if (empty($sdgNumbers)) {
        return [];
    }
    
    $sdgCount = count($sdgNumbers);
    
    // Query to find theses that have ALL selected SDGs
    $query = "
        SELECT DISTINCT
            t.thesis_id,
            t.title,
            t.author,
            t.publication_date,
            t.abstract,
            t.url,
            t.discipline,
            t.keywords,
            d.department_name,
            GROUP_CONCAT(DISTINCT sm.sdg_number ORDER BY sm.ranking) AS sdg_numbers,
            GROUP_CONCAT(
                DISTINCT CONCAT('SDG ', sm.sdg_number)
                ORDER BY sm.ranking
                SEPARATOR ', '
            ) AS sdg_labels
        FROM theses t
        INNER JOIN departments d ON t.department_id = d.department_id
        INNER JOIN sdg_mappings sm ON t.thesis_id = sm.thesis_id
        WHERE t.thesis_id IN (
            SELECT thesis_id
            FROM sdg_mappings
            WHERE sdg_number IN (" . implode(',', array_map('intval', $sdgNumbers)) . ")
            GROUP BY thesis_id
            HAVING COUNT(DISTINCT sdg_number) = :sdgCount
        )
        GROUP BY t.thesis_id
        ORDER BY t.publication_date DESC, t.title ASC
    ";
    
    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':sdgCount', $sdgCount);
    oci_execute($stmt);
    
    $results = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $results[] = $row;
    }
    
    oci_free_statement($stmt);
    return $results;
}

/**
 * Get SDG distribution statistics for the pie chart
 */
function getSDGStatistics() {
    global $connectn;
    
    $query = "
        SELECT sdg_number, COUNT(DISTINCT thesis_id) AS publication_count
        FROM sdg_mappings
        WHERE thesis_id IS NOT NULL
        GROUP BY sdg_number
        ORDER BY sdg_number
    ";
    
    $stmt = oci_parse($connectn, $query);
    oci_execute($stmt);
    
    $stats = array_fill(1, 16, 0);
    while ($row = oci_fetch_assoc($stmt)) {
        $stats[$row['SDG_NUMBER']] = (int)$row['PUBLICATION_COUNT'];
    }
    
    oci_free_statement($stmt);
    return array_values($stats);
}

/**
 * Save a new pending thesis submission
 */
function savePendingThesis($data, $sdgPredictions) {
    global $connectn;
    
    $query = "
        INSERT INTO pending_theses (
            title, author, publication_date, department_id, abstract,
            url, discipline, keywords, submitter_ip, submitter_email
        ) VALUES (
            :title, :author, TO_DATE(:pubDate, 'YYYY-MM-DD'), :deptId, :abstract,
            :url, :discipline, :keywords, :ip, :email
        )
        RETURNING pending_id INTO :pendingId
    ";
    
    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':title', $data['title']);
    oci_bind_by_name($stmt, ':author', $data['author']);
    oci_bind_by_name($stmt, ':pubDate', $data['publicationDate']);
    oci_bind_by_name($stmt, ':deptId', $data['departmentId']);
    oci_bind_by_name($stmt, ':abstract', $data['abstract']);
    oci_bind_by_name($stmt, ':url', $data['url']);
    oci_bind_by_name($stmt, ':discipline', $data['discipline']);
    oci_bind_by_name($stmt, ':keywords', $data['keywords']);
    oci_bind_by_name($stmt, ':ip', $_SERVER['REMOTE_ADDR']);
    oci_bind_by_name($stmt, ':email', $data['submitterEmail']);
    oci_bind_by_name($stmt, ':pendingId', $pendingId, -1, SQLT_INT);
    
    $result = oci_execute($stmt, OCI_NO_AUTO_COMMIT);
    
    if (!$result) {
        oci_rollback($connectn);
        oci_free_statement($stmt);
        return false;
    }
    
    oci_free_statement($stmt);
    
    // Insert SDG mappings
    $mappingQuery = "
        INSERT INTO sdg_mappings (
            pending_id, sdg_number, confidence_score, ranking, classification_method
        ) VALUES (
            :pendingId, :sdgNum, :score, :rank, 'ai_auto'
        )
    ";
    
    $mappingStmt = oci_parse($connectn, $mappingQuery);
    
    foreach ($sdgPredictions as $rank => $prediction) {
        $sdgNum = $prediction['id'];
        $score = $prediction['score'];
        $ranking = $rank + 1;
        
        oci_bind_by_name($mappingStmt, ':pendingId', $pendingId);
        oci_bind_by_name($mappingStmt, ':sdgNum', $sdgNum);
        oci_bind_by_name($mappingStmt, ':score', $score);
        oci_bind_by_name($mappingStmt, ':rank', $ranking);
        
        $result = oci_execute($mappingStmt, OCI_NO_AUTO_COMMIT);
        
        if (!$result) {
            oci_rollback($connectn);
            oci_free_statement($mappingStmt);
            return false;
        }
    }
    
    oci_free_statement($mappingStmt);
    oci_commit($connectn);
    
    return $pendingId;
}

/**
 * Get all pending theses awaiting approval
 */
function getPendingTheses($status = 'pending') {
    global $connectn;
    
    $query = "
        SELECT 
            pt.pending_id, pt.title, pt.author, pt.publication_date,
            pt.abstract, pt.url, pt.discipline, pt.keywords, pt.status,
            pt.submitted_at, pt.submitter_email, d.department_name,
            GROUP_CONCAT(
                CONCAT('SDG ', sm.sdg_number, ' (', ROUND(sm.confidence_score * 100, 1), '%)')
                ORDER BY sm.ranking SEPARATOR ', '
            ) AS sdg_classifications
        FROM pending_theses pt
        LEFT JOIN departments d ON pt.department_id = d.department_id
        LEFT JOIN sdg_mappings sm ON pt.pending_id = sm.pending_id
        WHERE pt.status = :status
        GROUP BY pt.pending_id
        ORDER BY pt.submitted_at DESC
    ";
    
    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':status', $status);
    oci_execute($stmt);
    
    $results = [];
    while ($row = oci_fetch_assoc($stmt)) {
        $results[] = $row;
    }
    
    oci_free_statement($stmt);
    return $results;
}

/**
 * Approve a pending thesis and move to main database
 */
function approvePendingThesis($pendingId, $userId) {
    global $connectn;
    
    $query = "BEGIN sp_approve_pending_thesis(:pendingId, :userId); END;";
    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':pendingId', $pendingId);
    oci_bind_by_name($stmt, ':userId', $userId);
    
    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    oci_free_statement($stmt);
    return $result;
}

/**
 * Reject a pending thesis
 */
function rejectPendingThesis($pendingId, $userId, $notes = '') {
    global $connectn;
    
    $query = "
        UPDATE pending_theses
        SET status = 'rejected', reviewed_by = :userId,
            reviewed_at = SYSDATE, review_notes = :notes
        WHERE pending_id = :pendingId
    ";
    
    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':pendingId', $pendingId);
    oci_bind_by_name($stmt, ':userId', $userId);
    oci_bind_by_name($stmt, ':notes', $notes);
    
    $result = oci_execute($stmt, OCI_COMMIT_ON_SUCCESS);
    oci_free_statement($stmt);
    return $result;
}

/**
 * Export search results to CSV
 */
function exportResultsToCSV($results, $sdgNumbers = []) {
    $csv = [];
    $csv[] = ['Thesis ID', 'Title', 'Author', 'Publication Date', 'Department', 
              'Discipline', 'SDG Classifications', 'Abstract', 'Keywords', 'URL'];
    
    foreach ($results as $thesis) {
        $csv[] = [
            $thesis['thesis_id'], $thesis['title'], $thesis['author'],
            $thesis['publication_date'], $thesis['department_name'],
            $thesis['discipline'] ?? '', $thesis['sdg_labels'] ?? '',
            $thesis['abstract'], $thesis['keywords'] ?? '', $thesis['url'] ?? ''
        ];
    }
    
    $output = fopen('php://temp', 'r+');
    foreach ($csv as $row) {
        fputcsv($output, $row);
    }
    rewind($output);
    $csvString = stream_get_contents($output);
    fclose($output);
    
    return $csvString;
}

/**
 * Export search results to BibTeX format
 */
function exportResultsToBibTeX($results) {
    $bibtex = "";
    
    foreach ($results as $thesis) {
        $citeKey = strtolower(str_replace(' ', '', $thesis['author'])) . 
                    date('Y', strtotime($thesis['publication_date']));
        
        $bibtex .= "@mastersthesis{" . $citeKey . ",\n";
        $bibtex .= "  title = {" . addslashes($thesis['title']) . "},\n";
        $bibtex .= "  author = {" . addslashes($thesis['author']) . "},\n";
        $bibtex .= "  year = {" . date('Y', strtotime($thesis['publication_date'])) . "},\n";
        $bibtex .= "  school = {Cal Poly Humboldt},\n";
        $bibtex .= "  type = {Master's Thesis},\n";
        
        if (!empty($thesis['department_name'])) {
            $bibtex .= "  department = {" . addslashes($thesis['department_name']) . "},\n";
        }
        if (!empty($thesis['url'])) {
            $bibtex .= "  url = {" . $thesis['url'] . "},\n";
        }
        if (!empty($thesis['keywords'])) {
            $bibtex .= "  keywords = {" . addslashes($thesis['keywords']) . "},\n";
        }
        
        $bibtex .= "  abstract = {" . addslashes($thesis['abstract']) . "}\n";
        $bibtex .= "}\n\n";
    }
    
    return $bibtex;
}

/**
 * Get department ID by name
 */
function getDepartmentId($departmentName) {
    global $connectn;
    
    $query = "SELECT department_id FROM departments WHERE department_name = :deptName";
    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':deptName', $departmentName);
    oci_execute($stmt);
    
    $row = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    
    return $row ? $row['DEPARTMENT_ID'] : false;
}

/**
 * Authenticate authorized user
 */
function authenticateUser($username, $password) {
    global $connectn;
    
    $query = "
        SELECT user_id, username, email, password_hash, full_name, role, is_active
        FROM authorized_users
        WHERE username = :username AND is_active = 1
    ";
    
    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':username', $username);
    oci_execute($stmt);
    
    $user = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);
    
    if ($user && password_verify($password, $user['PASSWORD_HASH'])) {
        $updateQuery = "UPDATE authorized_users SET last_login = SYSDATE WHERE user_id = :userId";
        $updateStmt = oci_parse($connectn, $updateQuery);
        oci_bind_by_name($updateStmt, ':userId', $user['USER_ID']);
        oci_execute($updateStmt, OCI_COMMIT_ON_SUCCESS);
        oci_free_statement($updateStmt);
        
        return $user;
    }
    
    return false;
}

?>
