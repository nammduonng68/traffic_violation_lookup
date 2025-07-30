<?php
// Bao gồm file kết nối
require_once 'db_connect.php';
session_start();

// Kiểm tra phiên đăng nhập
$is_logged_in = isset($_SESSION['user_id']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Khách';

// Khởi tạo biến
$searchResults = [];
$searchPerformed = false;
$searchMessage = '';
$searchCriteria = '';
$searchType = '';

// Xử lý khi form được gửi đi
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $searchPerformed = true;
    
    // Xác định loại tìm kiếm
    if (isset($_POST['search_type'])) {
        $searchType = $_POST['search_type'];
        
        // Tìm kiếm theo biển số xe
        if ($searchType == 'vehicle') {
            $vehicleNumber = mysqli_real_escape_string($conn, $_POST['vehicle_number']);
            $vehicleDate = !empty($_POST['vehicle_date']) ? mysqli_real_escape_string($conn, $_POST['vehicle_date']) : '';
            
            $searchCriteria = "biển số xe $vehicleNumber";
            
            // Tạo câu truy vấn với bảng mới - Sử dụng violation_location và fine_amount từ UserViolation
            $sql = "SELECT v.license_plate AS vehicle_number, 
                           viol.violation_name AS violation_description, 
                           uv.fine_amount AS fine, 
                           uv.processed_at AS violation_date, 
                           uv.violation_location AS location, 
                           uv.payment_status AS status
                    FROM Vehicle v
                    JOIN UserViolation uv ON v.vehicle_id = uv.vehicle_id
                    JOIN Violation viol ON uv.violation_id = viol.violation_id
                    JOIN User u ON uv.user_id = u.user_id
                    WHERE v.license_plate = '$vehicleNumber'";
                    
            if (!empty($vehicleDate)) {
                $sql .= " AND DATE(uv.processed_at) = '$vehicleDate'";
                $searchCriteria .= " ngày $vehicleDate";
            }
            $sql .= " ORDER BY uv.processed_at DESC";
        }
        
        // Tìm kiếm theo GPLX - Sử dụng violation_location và fine_amount từ UserViolation
        else if ($searchType == 'license') {
            $licenseNumber = mysqli_real_escape_string($conn, $_POST['license_number']);
            $licenseDate = !empty($_POST['license_date']) ? mysqli_real_escape_string($conn, $_POST['license_date']) : '';
            
            $searchCriteria = "GPLX $licenseNumber";
            
            // Tạo câu truy vấn sử dụng trường driver_license mới
            $sql = "SELECT v.license_plate AS vehicle_number, 
                           viol.violation_name AS violation_description, 
                           uv.fine_amount AS fine, 
                           uv.processed_at AS violation_date, 
                           uv.violation_location AS location, 
                           uv.payment_status AS status
                    FROM Vehicle v
                    JOIN UserViolation uv ON v.vehicle_id = uv.vehicle_id
                    JOIN Violation viol ON uv.violation_id = viol.violation_id
                    JOIN User u ON uv.user_id = u.user_id
                    WHERE v.driver_license = '$licenseNumber'";
                    
            if (!empty($licenseDate)) {
                $sql .= " AND DATE(uv.processed_at) = '$licenseDate'";
                $searchCriteria .= " ngày $licenseDate";
            }
            $sql .= " ORDER BY uv.processed_at DESC";
        }
        
        // Tìm kiếm theo CMND/CCCD - Sử dụng violation_location và fine_amount từ UserViolation
        else if ($searchType == 'id') {
            $idNumber = mysqli_real_escape_string($conn, $_POST['id_number']);
            $idDate = !empty($_POST['id_date']) ? mysqli_real_escape_string($conn, $_POST['id_date']) : '';
            
            $searchCriteria = "CMND/CCCD $idNumber";
            
            // Tạo câu truy vấn với bảng mới
            $sql = "SELECT v.license_plate AS vehicle_number, 
                           viol.violation_name AS violation_description, 
                           uv.fine_amount AS fine, 
                           uv.processed_at AS violation_date, 
                           uv.violation_location AS location, 
                           uv.payment_status AS status
                    FROM User u
                    JOIN UserViolation uv ON u.user_id = uv.user_id
                    JOIN Vehicle v ON uv.vehicle_id = v.vehicle_id
                    JOIN Violation viol ON uv.violation_id = viol.violation_id
                    WHERE u.identity_number = '$idNumber'";
                    
            if (!empty($idDate)) {
                $sql .= " AND DATE(uv.processed_at) = '$idDate'";
                $searchCriteria .= " ngày $idDate";
            }
            $sql .= " ORDER BY uv.processed_at DESC";
        }
        
        // Thực thi truy vấn
        $result = mysqli_query($conn, $sql);
        
        // Kiểm tra kết quả
        if ($result) {
            $searchResults = mysqli_fetch_all($result, MYSQLI_ASSOC);
            $searchMessage = "Tìm thấy " . count($searchResults) . " kết quả cho " . $searchCriteria;
        } else {
            $searchMessage = "Lỗi truy vấn: " . mysqli_error($conn);
        }
    }
}

// Tạo hàm chuyển đổi trạng thái thanh toán
function formatPaymentStatus($status) {
    if ($status == 'paid') {
        return 'Đã nộp phạt';
    } else {
        return 'Chưa nộp phạt';
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tra Cứu Vi Phạm Luật Giao Thông</title>
    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/index.css">
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
                                <?php if ($user_role == 'Admin'): ?>
                                    <li><a href="admin_dashboard.php"><i class="fas fa-exclamation-triangle"></i> Quản lý vi phạm</a></li>
                                    <li role="separator" class="divider"></li>
                                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
                                <?php else: ?>
                                    <li><a href="profile.php"><i class="fas fa-id-card"></i> Hồ sơ cá nhân</a></li>
                                    <li><a href="vehicles.php"><i class="fas fa-car"></i> Phương tiện</a></li>
                                    <li><a href="dashboard.php"><i class="fas fa-exclamation-triangle"></i> Vi phạm của tôi</a></li>
                                    <li role="separator" class="divider"></li>
                                    <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Đăng xuất</a></li>
                                <?php endif; ?>
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
            <h1>Tra cứu vi phạm luật giao thông</h1>
            <p>Hệ thống tra cứu thông tin xử phạt vi phạm hành chính trong lĩnh vực giao thông đường bộ</p>
        </div>
    </div>

    <div class="container">
        <!-- Main search forms -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-primary">
                    <div class="panel-heading">
                        <h3 class="panel-title">Tra cứu thông tin vi phạm</h3>
                    </div>
                    <div class="panel-body">
                        <ul class="nav nav-tabs">
                            <li class="active"><a data-toggle="tab" href="#vehicle-search">Tra cứu theo biển số xe</a></li>
                            <li><a data-toggle="tab" href="#license-search">Tra cứu theo GPLX</a></li>
                            <li><a data-toggle="tab" href="#id-search">Tra cứu theo CMND/CCCD</a></li>

                            <li class="dropdown">
                                <a class="dropdown-toggle" data-toggle="dropdown" href="#">Tùy chọn tra cứu <span class="caret"></span></a>
                                <ul class="dropdown-menu">
                                    <li><a data-toggle="tab" href="#vehicle-search">Tra cứu theo biển số xe</a></li>
                                    <li><a data-toggle="tab" href="#license-search">Tra cứu theo GPLX</a></li>
                                    <li><a data-toggle="tab" href="#id-search">Tra cứu theo CMND/CCCD</a></li>
                                </ul>
                            </li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Vehicle plate search -->
                            <div id="vehicle-search" class="tab-pane fade in active">
                                <form id="vehicle-form" method="post" action="">
                                    <input type="hidden" name="search_type" value="vehicle">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="vehicle-number">Biển số xe:</label>
                                                <input type="text" class="form-control" id="vehicle-number" name="vehicle_number" placeholder="Nhập biển số xe (VD: 30A-12345)" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="vehicle-date">Ngày vi phạm (không bắt buộc):</label>
                                                <input type="date" class="form-control" id="vehicle-date" name="vehicle_date">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg"><span class="glyphicon glyphicon-search"></span> Tra cứu</button>
                                </form>
                            </div>
                            
                            <!-- Driver license search -->
                            <div id="license-search" class="tab-pane fade">
                                <form id="license-form" method="post" action="">
                                    <input type="hidden" name="search_type" value="license">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="license-number">Số giấy phép lái xe:</label>
                                                <input type="text" class="form-control" id="license-number" name="license_number" placeholder="Nhập số GPLX" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="license-date">Ngày vi phạm (không bắt buộc):</label>
                                                <input type="date" class="form-control" id="license-date" name="license_date">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg"><span class="glyphicon glyphicon-search"></span> Tra cứu</button>
                                </form>
                            </div>
                            
                            <!-- ID card search -->
                            <div id="id-search" class="tab-pane fade">
                                <form id="id-form" method="post" action="">
                                    <input type="hidden" name="search_type" value="id">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id-number">Số CMND/CCCD:</label>
                                                <input type="text" class="form-control" id="id-number" name="id_number" placeholder="Nhập số CMND/CCCD" required>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="form-group">
                                                <label for="id-date">Ngày vi phạm (không bắt buộc):</label>
                                                <input type="date" class="form-control" id="id-date" name="id_date">
                                            </div>
                                        </div>
                                    </div>
                                    <button type="submit" class="btn btn-primary btn-lg"><span class="glyphicon glyphicon-search"></span> Tra cứu</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
                                
        <!-- Search results -->
        <?php if ($searchPerformed): ?>
        <div class="result-box" id="results">
            <div class="panel panel-info">
                <div class="panel-heading">
                    <h3 class="panel-title">Kết quả tra cứu</h3>
                </div>
                <div class="panel-body">
                    <div class="alert alert-info">
                        <strong>Thông báo!</strong> <?php echo $searchMessage; ?>
                    </div>
                    
                    <?php if (count($searchResults) > 0): ?>
                    <table class="table table-striped table-bordered">
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
                            <?php $counter = 1; ?>
                            <?php foreach ($searchResults as $violation): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($violation['vehicle_number']); ?></td>
                                <td><?php echo date('d/m/Y', strtotime($violation['violation_date'])); ?></td>
                                <td><?php echo htmlspecialchars($violation['location']); ?></td>
                                <td><?php echo htmlspecialchars($violation['violation_description']); ?></td>
                                <td><?php echo number_format($violation['fine'], 0, ',', '.') . ' VNĐ'; ?></td>
                                <td>
                                    <?php if ($violation['status'] == 'Đã thanh toán'): ?>
                                        <span class="label label-success">Đã nộp phạt</span>
                                    <?php else: ?>
                                        <span class="label label-danger">Chưa nộp phạt</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    
                    <div class="alert alert-warning">
                        <strong>Lưu ý:</strong> Để biết thêm chi tiết và thực hiện thanh toán trực tuyến, vui lòng <a href="#" class="alert-link">đăng nhập</a> vào hệ thống.
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <strong>Không tìm thấy kết quả!</strong> Không có vi phạm nào được ghi nhận với thông tin bạn cung cấp.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Features section -->
        <div class="row">
            <div class="col-md-12">
                <h2 class="text-center">Dịch vụ của chúng tôi</h2>
                <hr>
            </div>
            <div class="col-md-4">
                <div class="feature-box">
                    <span class="glyphicon glyphicon-search"></span>
                    <h3>Tra cứu nhanh chóng</h3>
                    <p>Tra cứu thông tin vi phạm giao thông nhanh chóng, chính xác từ cơ sở dữ liệu của Cục CSGT</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-box">
                    <span class="glyphicon glyphicon-credit-card"></span>
                    <h3>Thanh toán trực tuyến</h3>
                    <p>Thanh toán tiền phạt an toàn, thuận tiện qua các phương thức thanh toán điện tử</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="feature-box">
                    <span class="glyphicon glyphicon-bell"></span>
                    <h3>Thông báo tự động</h3>
                    <p>Nhận thông báo khi có vi phạm mới thông qua email hoặc tin nhắn SMS</p>
                </div>
            </div>
        </div>
        
        <!-- Statistics -->
        <div class="row">
            <div class="col-md-12">
                <div class="panel panel-default">
                    <div class="panel-heading">
                        <h3 class="panel-title">Thống kê vi phạm giao thông</h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <div class="col-md-3 text-center">
                                <h3>12,000</h3>
                                <p>Vi phạm tháng trước</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h3>68%</h3>
                                <p>Đã thanh toán</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h3>5,000</h3>
                                <p>Vi phạm đèn tín hiệu</p>
                            </div>
                            <div class="col-md-3 text-center">
                                <h3>3,000</h3>
                                <p>Vi phạm tốc độ</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
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

        $(document).ready(function() {
            // Kiểm tra nếu đã tìm kiếm, scroll đến kết quả
            <?php if ($searchPerformed): ?>
            $('html, body').animate({
                scrollTop: $("#results").offset().top - 70
            }, 1000);
            <?php endif; ?>
            
            // Hiển thị đúng tab dựa trên loại tìm kiếm
            <?php if ($searchType == 'license'): ?>
            $('a[href="#license-search"]').tab('show');
            <?php elseif ($searchType == 'id'): ?>
            $('a[href="#id-search"]').tab('show');
            <?php endif; ?>
        });
    </script>
</body>
</html>