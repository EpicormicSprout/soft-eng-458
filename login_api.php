<?php
/**
 * Login API Endpoint
 * Handles user authentication
 * Last Modified: 2025-11-30
 */

header('Content-Type: application/json');
session_start();

try {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);

    $username = trim($data['username'] ?? '');
    $password = $data['password'] ?? '';

    if (empty($username) || empty($password)) {
        echo json_encode(['success' => false, 'error' => 'Username and password are required']);
        exit;
    }

    require_once __DIR__ . '/hum_conn_no_login.php';
    $connectn = hum_conn_no_login();

    if (!$connectn) {
        throw new Exception('Database connection failed');
    }

    $query = "
        SELECT user_id, username, email, password_hash, full_name, role, is_active
        FROM authorized_users
        WHERE username = :username AND is_active = 1
    ";

    $stmt = oci_parse($connectn, $query);
    oci_bind_by_name($stmt, ':username', $username);
    
    if (!oci_execute($stmt)) {
        throw new Exception('Query failed');
    }

    $user = oci_fetch_assoc($stmt);
    oci_free_statement($stmt);

    if ($user && password_verify($password, $user['PASSWORD_HASH'])) {
        // Update last login
        $updateQuery = "UPDATE authorized_users SET last_login = SYSDATE WHERE user_id = :userId";
        $updateStmt = oci_parse($connectn, $updateQuery);
        oci_bind_by_name($updateStmt, ':userId', $user['USER_ID']);
        oci_execute($updateStmt, OCI_COMMIT_ON_SUCCESS);
        oci_free_statement($updateStmt);

        // Set session
        $_SESSION['user_id'] = $user['USER_ID'];
        $_SESSION['username'] = $user['USERNAME'];
        $_SESSION['full_name'] = $user['FULL_NAME'];
        $_SESSION['role'] = $user['ROLE'];
        $_SESSION['logged_in'] = true;

        echo json_encode([
            'success' => true,
            'user' => [
                'username' => $user['USERNAME'],
                'fullName' => $user['FULL_NAME'],
                'role' => $user['ROLE']
            ]
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Invalid username or password']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
