<?php
// Kết nối database
require_once 'db_connect.php';
session_start();

// Kiểm tra phiên đăng nhập
$is_logged_in = isset($_SESSION['user_id']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';

// Kiểm tra xem request có phải là POST và có tham số offset và limit hay không
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['offset']) && isset($_POST['limit'])) {
    $offset = intval($_POST['offset']);
    $limit = intval($_POST['limit']);
    
    // Lấy thêm vi phạm từ database dựa trên vai trò người dùng
    if ($is_logged_in) {
        if ($user_role == 'Admin') {
            $violations_query = "SELECT 
                            uv.user_violation_id,
                            v.license_plate, 
                            vio.violation_name, 
                            uv.processed_at, 
                            uv.violation_location,
                            uv.fine_amount,
                            uv.payment_status
                            FROM UserViolation uv 
                            JOIN Vehicle v ON uv.vehicle_id = v.vehicle_id 
                            JOIN Violation vio ON uv.violation_id = vio.violation_id 
                            ORDER BY uv.processed_at DESC 
                            LIMIT ?, ?";
        } else {
            $violations_query = "SELECT 
                            uv.user_violation_id,
                            v.license_plate, 
                            vio.violation_name, 
                            uv.processed_at, 
                            uv.violation_location,
                            uv.fine_amount,
                            uv.payment_status
                            FROM UserViolation uv 
                            JOIN Vehicle v ON uv.vehicle_id = v.vehicle_id 
                            JOIN Violation vio ON uv.violation_id = vio.violation_id 
                            WHERE uv.user_id = ?
                            ORDER BY uv.processed_at DESC 
                            LIMIT ?, ?";
        }
        
        $stmt = mysqli_prepare($conn, $violations_query);
        
        if ($user_role == 'Admin') {
            mysqli_stmt_bind_param($stmt, "ii", $offset, $limit);
        } else {
            mysqli_stmt_bind_param($stmt, "iii", $user_id, $offset, $limit);
        }
        
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        $violations = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $violations[] = $row;
        }
        
        // Kiểm tra xem còn dữ liệu để tải không
        $has_more = count($violations) == $limit;
        
        // Trả về kết quả dưới dạng JSON
        echo json_encode([
            'violations' => $violations,
            'has_more' => $has_more
        ]);
    } else {
        // Trả về lỗi nếu người dùng chưa đăng nhập
        echo json_encode([
            'error' => 'User not logged in',
            'violations' => [],
            'has_more' => false
        ]);
    }
} else {
    // Trả về lỗi nếu request không hợp lệ
    echo json_encode([
        'error' => 'Invalid request',
        'violations' => [],
        'has_more' => false
    ]);
}