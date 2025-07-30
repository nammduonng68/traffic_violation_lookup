<?php
// Khởi tạo phiên
session_start();

// Xóa tất cả các biến phiên
$_SESSION = array();

// Nếu sử dụng cookie để lưu session ID, hãy xóa cookie đó
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time() - 42000, '/');
}

// Hủy phiên
session_destroy();

// Chuyển hướng về trang chủ
header("Location: index.php");
exit();
?>