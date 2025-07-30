<?php
// Kết nối tới database
require_once 'db_connect.php';
session_start();

// Kiểm tra phiên đăng nhập
$is_logged_in = isset($_SESSION['user_id']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Khách';

// Kiểm tra nếu người dùng là Admin thì chuyển hướng hoặc hiển thị thông báo
$is_admin = ($user_role == 'Admin');

// Lấy thông tin thống kê cho dashboard dựa trên vai trò người dùng
$total_violations = 0;
$total_paid = 0;
$total_unpaid = 0;

if ($is_logged_in && !$is_admin) {
    $stats_query = "SELECT 
        COUNT(*) as total_violations,
        SUM(CASE WHEN payment_status = 'Đã thanh toán' THEN 1 ELSE 0 END) as total_paid,
        SUM(CASE WHEN payment_status = 'Chưa thanh toán' THEN 1 ELSE 0 END) as total_unpaid
    FROM UserViolation WHERE user_id = $user_id";

    $stats_result = mysqli_query($conn, $stats_query);
    if ($stats_result && mysqli_num_rows($stats_result) > 0) {
        $stats = mysqli_fetch_assoc($stats_result);
        $total_violations = $stats['total_violations'];
        $total_paid = $stats['total_paid'];
        $total_unpaid = $stats['total_unpaid'];
    }
}

// Lấy danh sách các loại vi phạm phổ biến cho người dùng thường
$violation_stats = [];

if ($is_logged_in && !$is_admin) {
    $violation_query = "SELECT vio.violation_name, COUNT(*) as count 
                       FROM UserViolation uv 
                       JOIN Violation vio ON uv.violation_id = vio.violation_id 
                       WHERE uv.user_id = $user_id
                       GROUP BY uv.violation_id 
                       ORDER BY count DESC 
                       LIMIT 5";

    $violation_result = mysqli_query($conn, $violation_query);
    if ($violation_result && mysqli_num_rows($violation_result) > 0) {
        while ($row = mysqli_fetch_assoc($violation_result)) {
            $violation_stats[] = $row;
        }
    }
}

// Lấy danh sách vi phạm của người dùng - ban đầu lấy 10 bản ghi
$violations = [];

if ($is_logged_in && !$is_admin) {
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
                        WHERE uv.user_id = $user_id
                        ORDER BY uv.processed_at DESC 
                        LIMIT 10";

    $violations_result = mysqli_query($conn, $violations_query);
    if ($violations_result && mysqli_num_rows($violations_result) > 0) {
        while ($row = mysqli_fetch_assoc($violations_result)) {
            $violations[] = $row;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Tra Cứu Vi Phạm Luật Giao Thông</title>
    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/dashboard-style.css"> 
    <link rel="stylesheet" href="./assets/css/navbar.css">
    <link rel="stylesheet" href="./assets/css/footer.css">
    <link rel="stylesheet" href="./assets/css/responsive.css">
    <link rel="stylesheet" href="./assets/css/widget-style.css">  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="./assets/javascripts/lookups.js"></script>
    <script src="./assets/javascripts/widget.js"></script>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-inverse navbar-fixed-top">
        <div class="container">
            <div class="navbar-header">
                <!-- Tạo 1 thanh toggle để responsive cho thiết bị mobile -->
                <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target="#navbar">
                    <span class="sr-only">Toggle navigation</span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                    <span class="icon-bar"></span>
                </button>
                <a class="navbar-brand" href="index.php"><i class="fas fa-car-crash"></i> Cổng tra cứu VPGT</a>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
                    <li><a href="index.php">Trang chủ</a></li>
                    <li><a href="#">Giới thiệu</a></li>
                    <li><a href="#">Quy định</a></li>
                    <li><a href="#">Liên hệ</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <?php if ($is_logged_in): ?>
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#">
                                <i class="fas fa-user"></i> <?php echo $user_name; ?> <span class="caret"></span>
                            </a>
                            <ul class="dropdown-menu">
                                <li><a ><i class="fas fa-id-card"></i> Hồ sơ cá nhân</a></li>
                                <li><a ><i class="fas fa-car"></i> Phương tiện</a></li>
                                <li><a href="dashboard.php"><i class="fas fa-exclamation-triangle"></i> Vi phạm của tôi</a></li>
                                <li role="separator" class="divider"></li>
                                <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <li class="dropdown">
                            <a class="dropdown-toggle" data-toggle="dropdown" href="#">Tài khoản <span class="caret"></span></a>
                            <ul class="dropdown-menu">
                                <li><a href="login.php"><span class="glyphicon glyphicon-log-in"></span> Đăng nhập</a></li>
                            </ul>
                        </li> 
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="jumbotron">
        <div class="container">
            <h1>Thống kê vi phạm</h1>
            <p>Xem chi tiết các lỗi vi phạm và thực hiện nộp phạt</p>
        </div>
    </div>

    <div class="container">
        <?php if ($is_admin): ?>
            <!-- Thông báo cho Admin -->
            <div class="alert alert-danger admin-message">
                <h4><i class="fas fa-exclamation-triangle"></i> Không thể truy cập</h4>
                <p>Admin không thể truy cập trang này. Vui lòng sử dụng trang quản trị dành riêng cho Admin.</p>
                <a href="admin_dashboard.php" class="btn btn-primary"><i class="fas fa-tachometer-alt"></i> Đi đến trang quản trị</a>
            </div>
        <?php elseif (!$is_logged_in): ?>
            <!-- Thông báo nếu chưa đăng nhập -->
            <div class="not-logged-in-message">
                <h4><i class="fas fa-exclamation-circle"></i> Vui lòng đăng nhập</h4>
                <p>Bạn cần đăng nhập để xem thông tin vi phạm và sử dụng đầy đủ tính năng của hệ thống.</p>
                <a href="login.php" class="btn btn-primary"><i class="fas fa-sign-in-alt"></i> Đăng nhập ngay</a>
            </div>
        <?php else: ?>
            <!-- Welcome Message -->
            <div class="row dashboard-header">
                <div class="col-md-12">
                    <div class="welcome-message">
                        <i class="fas fa-tachometer-alt"></i> Xin chào, <?php echo $user_name; ?>!
                    </div>
                </div>
            </div>

            <!-- Dashboard Widgets -->
            <div class="row">
                <div class="col-md-4">
                    <div class="dashboard-widget">
                        <h4><i class="fas fa-chart-bar"></i> Tổng số vi phạm</h4>
                        <div class="stats-number"><?php echo $total_violations; ?></div>
                        <div class="stats-description">Tổng số vi phạm của bạn</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-widget">
                        <h4><i class="fas fa-check-circle"></i> Đã thanh toán</h4>
                        <div class="stats-number status-paid"><?php echo $total_paid; ?></div>
                        <div class="stats-description">Số vi phạm của bạn đã thanh toán</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="dashboard-widget">
                        <h4><i class="fas fa-times-circle"></i> Chưa thanh toán</h4>
                        <div class="stats-number status-unpaid"><?php echo $total_unpaid; ?></div>
                        <div class="stats-description">Số vi phạm của bạn chưa thanh toán</div>
                    </div>
                </div>
                
                <!-- Loại vi phạm phổ biến -->
                <div class="col-md-12">
                    <div class="dashboard-widget">
                        <h4><i class="fas fa-chart-pie"></i> Vi phạm phổ biến</h4>
                        <?php if (!empty($violation_stats)): ?>
                            <?php foreach ($violation_stats as $stat): ?>
                                <div class="violation-stat">
                                    <div><?php echo htmlspecialchars($stat['violation_name']); ?></div>
                                    <div><span class="violation-badge"><?php echo $stat['count']; ?></span></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p>Không có dữ liệu</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Dashboard Stats -->
            <div class="row">
                <!-- Vi phạm gần đây -->
                <div class="col-md-12">
                    <div class="dashboard-widget">
                        <h4><i class="fas fa-list"></i> Danh sách lỗi vi phạm</h4>
                        <?php if (!empty($violations)): ?>
                            <div class="table-container">
                                <div class="table-responsive">
                                    <table class="table table-striped" id="violations-table">
                                        <thead>
                                            <tr>
                                                <th>STT</th>
                                                <th>Biển số xe</th>
                                                <th>Ngày vi phạm</th>
                                                <th>Địa điểm</th>
                                                <th>Lỗi vi phạm</th>
                                                <th>Mức phạt</th>
                                                <th>Trạng thái</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $stt = 1; ?>
                                            <?php foreach ($violations as $violation): ?>
                                                <tr>
                                                    <td><?php echo $stt++; ?></td>
                                                    <td><?php echo htmlspecialchars($violation['license_plate']); ?></td>
                                                    <td><?php echo $violation['processed_at'] ? date('d/m/Y H:i', strtotime($violation['processed_at'])) : 'N/A'; ?></td>
                                                    <td><?php echo htmlspecialchars($violation['violation_location']); ?></td>
                                                    <td><?php echo htmlspecialchars($violation['violation_name']); ?></td>
                                                    <td><?php echo number_format($violation['fine_amount'], 0, ',', '.') . ' VNĐ'; ?></td>
                                                    <td>
                                                        <?php if ($violation['payment_status'] == 'Đã thanh toán'): ?>
                                                            <span class="label label-success">Đã thanh toán</span>
                                                        <?php else: ?>
                                                            <a href="payment.php?id=<?php echo $violation['user_violation_id']; ?>" class="label label-danger">Nhấn để thanh toán</a>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="loading-indicator" id="loading">
                                    <i class="fas fa-spinner fa-spin"></i> Đang tải...
                                </div>
                                <div class="load-more-container">
                                    <button id="load-more-btn" class="btn btn-primary">
                                        <i class="fas fa-plus"></i> Xem thêm
                                    </button>
                                </div>
                            </div>
                        <?php else: ?>
                            <p>Không có dữ liệu</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Footer -->
    <footer class="footer">
        <div class="container">
            <div class="row">
                <div class="col-md-4">
                    <h4>Về chúng tôi</h4>
                    <p>Cổng thông tin tra cứu vi phạm luật giao thông chính thức của Cục Cảnh sát giao thông - Bộ Công an</p>
                </div>
                <div class="col-md-4">
                    <h4>Liên hệ</h4>
                    <address>
                        <strong>Cục Cảnh sát giao thông</strong><br>
                        112 Đ. Lê Duẩn, Văn Miếu, Hoàn Kiếm, Hà Nội<br>
                        <span class="glyphicon glyphicon-phone"></span> Hotline: 6811.3333.1168<br>
                        <span class="glyphicon glyphicon-envelope"></span> Email: support@csgt.gov.vn
                    </address>
                </div>
                <div class="col-md-4">
                    <h4>Hướng dẫn</h4>
                    <ul class="list-unstyled">
                        <li><a href="#"><span class="glyphicon glyphicon-chevron-right"></span> Hướng dẫn tra cứu</a></li>
                        <li><a href="#"><span class="glyphicon glyphicon-chevron-right"></span> Hướng dẫn thanh toán</a></li>
                        <li><a href="#"><span class="glyphicon glyphicon-chevron-right"></span> Câu hỏi thường gặp</a></li>
                        <li><a href="#"><span class="glyphicon glyphicon-chevron-right"></span> Quy định và chính sách</a></li>
                    </ul>
                </div>
            </div>
            <hr>
            <div class="text-center">
                <img src="./assets/img/logo.png" alt="">
                <p>&copy; 2025 Cục Cảnh sát giao thông - Bộ Công an. All rights reserved.</p>
            </div>
        </div>
    </footer>
    
    <div id="widget" onclick="toggleSubmenu()">
        <div class="float-contact">
            <div class="float-contact-header">
                <p>Bạn muốn liên hệ qua?</p>
            </div>
            <div class="float-contact-content">
                <div class="gg-map">
                    <a href="https://maps.app.goo.gl/1Tn1fmn9vqNbgLhY9"><img src="./assets/widget/gps.png" alt="">Địa chỉ</a>
                </div>
                <div class="call-hotline">
                    <a href="tel:681133331168"><img src="./assets/widget/phone.png" alt="">Hotline</a>
                </div>
                <div class="mail">
                    <a href="support@csgt.gov.vn"><img src="./assets/widget/email.png" alt="">Email</a>
                </div>
            </div>
        </div>
        <div class="widget-toggle">
            <a href=""><img src="./assets/widget/chat.png" alt=""></a>
        </div>
        <div class="widget-toggle-cancel">
            <a href=""><img src="./assets/widget/cancel.png" alt=""></a>
        </div>
    </div> 

    <script>
        document.querySelector(".widget-toggle").addEventListener("click", function(event) {
            event.preventDefault(); // Ngăn chuyển trang
        });
        document.querySelector(".widget-toggle-cancel").addEventListener("click", function(event) {
            event.preventDefault(); // Ngăn chuyển trang
        });

        // Định dạng số tiền VND
        function formatCurrency(number) {
            return number.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ".");
        }
                
        // Chức năng Load More
        $(document).ready(function() {
            var offset = 10; // Số bản ghi đã hiển thị
            var limit = 10; // Số bản ghi tải thêm mỗi lần

            $('#load-more-btn').click(function() {
                var button = $(this);
                $('#loading').show();
                button.prop('disabled', true);

                $.ajax({
                    url: 'get_more_violations.php',
                    type: 'POST',
                    data: {
                        offset: offset,
                        limit: limit
                    },
                    success: function(response) {
                        var result = JSON.parse(response);
                        
                        if (result.violations.length > 0) {
                            // Thêm các vi phạm mới vào bảng
                            var currentStt = $('#violations-table tbody tr').length + 1;
                            
                            result.violations.forEach(function(violation) {
                                var paymentStatus = violation.payment_status === 'Đã thanh toán' 
                                    ? '<span class="label label-success">Đã thanh toán</span>' 
                                    : '<span class="label label-danger">Chưa thanh toán</span>';
                                
                                var date = violation.processed_at 
                                    ? new Date(violation.processed_at).toLocaleDateString('vi-VN', {day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'}).replace(',', '') 
                                    : 'N/A';
                                
                                var fineAmount = parseFloat(violation.fine_amount).toLocaleString('vi-VN') + ' VNĐ';
                                
                                var row = '<tr>' +
                                    '<td>' + currentStt + '</td>' +
                                    '<td>' + violation.license_plate + '</td>' +
                                    '<td>' + date + '</td>' +
                                    '<td>' + violation.violation_location + '</td>' +
                                    '<td>' + violation.violation_name + '</td>' +
                                    '<td>' + fineAmount + '</td>' +
                                    '<td>' + paymentStatus + '</td>' +
                                    '</tr>';
                                
                                $('#violations-table tbody').append(row);
                                currentStt++;
                            });
                            
                            // Cập nhật offset cho lần tải tiếp theo
                            offset += result.violations.length;
                            
                            // Ẩn nút "Xem thêm" nếu không còn dữ liệu
                            if (!result.has_more) {
                                button.hide();
                            }
                        } else {
                            button.hide();
                        }
                    },
                    error: function() {
                        alert('Đã xảy ra lỗi. Vui lòng thử lại sau.');
                    },
                    complete: function() {
                        $('#loading').hide();
                        button.prop('disabled', false);
                    }
                });
            });
        });
    </script>
</body>
</html>