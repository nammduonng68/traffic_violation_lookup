<?php
// db_connect.php - File kết nối đến cơ sở dữ liệu

// Thông tin kết nối
$db_host = '127.0.0.1:3307';
$db_user = 'root';  // Thay bằng username MySQL của bạn
$db_pass = '';  // Thay bằng password MySQL của bạn
$db_name = 'traffic_violations';

// Tạo kết nối
$conn = mysqli_connect($db_host, $db_user, $db_pass, $db_name);

// Kiểm tra kết nối
if (!$conn) {
    die("Kết nối thất bại: " . mysqli_connect_error());
}

// Đặt charset để hiển thị tiếng Việt
mysqli_set_charset($conn, "utf8mb4");
?>