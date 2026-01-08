<?php

if (function_exists('opcache_reset')) opcache_reset();
ini_set('display_errors', 0);
ini_set('log_errors', 1);
error_reporting(0);

// Include storage
require_once __DIR__ . '/approval_storage.php';

// Set headers for fast response
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('X-Accel-Buffering: no'); // Disable nginx buffering
ob_implicit_flush(true);

// Start timing
$startTime = microtime(true);

// Get input FAST
$tournament_id = isset($_POST['tournament_id']) ? (int)$_POST['tournament_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

// Quick validation
if ($tournament_id <= 0 || !in_array($action, ['approve', 'reject'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit();
}

// Determine status
$status = $action === 'approve' ? 'approved' : 'rejected';

try {
    // Save to storage (this is async/background save)
    $result = saveApproval($tournament_id, $status);
    
    if ($result) {
        // Calculate execution time
        $execTime = round((microtime(true) - $startTime) * 1000, 2);
        
        // Success response with minimal data
        echo json_encode([
            'success' => true,
            'message' => "Tournament {$action}d successfully",
            'data' => [
                'id' => $tournament_id,
                'status' => $status,
                'timestamp' => time(),
                'exec_time_ms' => $execTime
            ]
        ], JSON_UNESCAPED_UNICODE);
        
    } else {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Save failed']);
    }
    
} catch (Exception $e) {
    error_log("Approval error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Server error']);
}

// Force immediate output
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

exit();
?>



