<?php
// Kết nối tới database
require_once 'db_connect.php';
session_start();

// Kiểm tra phiên đăng nhập và quyền admin
$is_logged_in = isset($_SESSION['user_id']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Khách';

// Nếu không phải admin thì chuyển hướng về trang chủ
if (!$is_logged_in || $user_role !== 'Admin') {
    header("Location: index.php");
    exit();
}

// Xử lý thêm vi phạm mới
if (isset($_POST['add_violation'])) {
    $user_name = mysqli_real_escape_string($conn, $_POST['user_name']);
    $license_plate = mysqli_real_escape_string($conn, $_POST['license_plate']);
    $violation_id = mysqli_real_escape_string($conn, $_POST['violation_id']);
    $fine_amount = mysqli_real_escape_string($conn, $_POST['fine_amount']);
    $violation_location = mysqli_real_escape_string($conn, $_POST['violation_location']);
    
    // Kiểm tra giá tiền nằm trong khoảng min_fine và max_fine
    $fine_check_query = "SELECT min_fine, max_fine FROM Violation WHERE violation_id = '$violation_id'";
    $fine_check_result = mysqli_query($conn, $fine_check_query);
    $fine_range = mysqli_fetch_assoc($fine_check_result);
    
    if ($fine_amount < $fine_range['min_fine'] || $fine_amount > $fine_range['max_fine']) {
        $error_message = "Lỗi: Số tiền phạt phải nằm trong khoảng từ " . number_format($fine_range['min_fine']) . " VNĐ đến " . number_format($fine_range['max_fine']) . " VNĐ";
    } else {
        // Kiểm tra hoặc tạo user dựa trên tên
        $user_query = "SELECT user_id FROM User WHERE full_name = '$user_name'";
        $user_result = mysqli_query($conn, $user_query);
        
        if (mysqli_num_rows($user_result) > 0) {
            $user_row = mysqli_fetch_assoc($user_result);
            $user_id_for_violation = $user_row['user_id'];
        } else {
            // Tạo user mới
            $insert_user_query = "INSERT INTO User (username, password, full_name, role) 
                                VALUES ('" . str_replace(' ', '', strtolower($user_name)) . "', 'temp_password', '$user_name', 'User')";
            mysqli_query($conn, $insert_user_query);
            $user_id_for_violation = mysqli_insert_id($conn);
        }
        
        // Kiểm tra hoặc tạo vehicle dựa trên biển số
        $vehicle_query = "SELECT vehicle_id FROM Vehicle WHERE license_plate = '$license_plate'";
        $vehicle_result = mysqli_query($conn, $vehicle_query);
        
        if (mysqli_num_rows($vehicle_result) > 0) {
            $vehicle_row = mysqli_fetch_assoc($vehicle_result);
            $vehicle_id_for_violation = $vehicle_row['vehicle_id'];
        } else {
            // Tạo vehicle mới
            $insert_vehicle_query = "INSERT INTO Vehicle (license_plate, owner_id) 
                                    VALUES ('$license_plate', '$user_id_for_violation')";
            mysqli_query($conn, $insert_vehicle_query);
            $vehicle_id_for_violation = mysqli_insert_id($conn);
        }
        
        // Trạng thái thanh toán luôn luôn là "Chưa nộp phạt" khi thêm mới
        $payment_status = "Chưa nộp phạt";
        
        $current_date = date('Y-m-d H:i:s');
        
        $insert_query = "INSERT INTO UserViolation (user_id, violation_id, vehicle_id, fine_amount, violation_location, status, payment_status, processed_by, processed_at) 
                        VALUES ('$user_id_for_violation', '$violation_id', '$vehicle_id_for_violation', '$fine_amount', '$violation_location', 'Đã xử lý', '$payment_status', '$user_name', '$current_date')";
        
        if (mysqli_query($conn, $insert_query)) {
            $success_message = "Thêm vi phạm thành công!";
        } else {
            $error_message = "Lỗi: " . mysqli_error($conn);
        }
    }
}

// Xử lý xóa vi phạm
if (isset($_GET['delete'])) {
    $violation_id = mysqli_real_escape_string($conn, $_GET['delete']);
    $delete_query = "DELETE FROM UserViolation WHERE user_violation_id = '$violation_id'";
    
    if (mysqli_query($conn, $delete_query)) {
        $success_message = "Xóa vi phạm thành công!";
    } else {
        $error_message = "Lỗi: " . mysqli_error($conn);
    }
}

// Lấy thông tin loại vi phạm cho dropdown
$violations_query = "SELECT violation_id, violation_name, min_fine, max_fine FROM Violation";
$violations_result = mysqli_query($conn, $violations_query);
$violations = [];
while ($row = mysqli_fetch_assoc($violations_result)) {
    $violations[] = $row;
}

// Lấy danh sách vi phạm hiện có
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$records_per_page = 10;
$offset = ($page - 1) * $records_per_page;

$violations_list_query = "SELECT 
                            uv.user_violation_id,
                            u.full_name,
                            v.license_plate,
                            vio.violation_name,
                            uv.processed_at,
                            uv.violation_location,
                            uv.fine_amount,
                            uv.payment_status
                        FROM UserViolation uv 
                        JOIN User u ON uv.user_id = u.user_id
                        JOIN Vehicle v ON uv.vehicle_id = v.vehicle_id 
                        JOIN Violation vio ON uv.violation_id = vio.violation_id 
                        ORDER BY uv.processed_at DESC
                        LIMIT $offset, $records_per_page";

$violations_list_result = mysqli_query($conn, $violations_list_query);
$violations_list = [];
while ($row = mysqli_fetch_assoc($violations_list_result)) {
    $violations_list[] = $row;
}

// Đếm tổng số vi phạm để phân trang
$count_query = "SELECT COUNT(*) as total FROM UserViolation";
$count_result = mysqli_query($conn, $count_query);
$count_row = mysqli_fetch_assoc($count_result);
$total_records = $count_row['total'];
$total_pages = ceil($total_records / $records_per_page);

// Lấy thông tin thống kê tổng quan
$stats_query = "SELECT 
                COUNT(*) as total_violations,
                SUM(CASE WHEN payment_status = 'Đã thanh toán' THEN 1 ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status = 'Chưa nộp phạt' OR payment_status = 'Chưa thanh toán' THEN 1 ELSE 0 END) as total_unpaid,
                SUM(fine_amount) as total_amount
            FROM UserViolation";

$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quản lý Vi Phạm - Tra Cứu Vi Phạm Luật Giao Thông</title>
    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/dashboard-style.css">
    <link rel="stylesheet" href="./assets/css/admin_dashboard-style.css"> 
    <link rel="stylesheet" href="./assets/css/navbar.css">
    <link rel="stylesheet" href="./assets/css/footer.css">
    <link rel="stylesheet" href="./assets/css/responsive.css">
    <link rel="stylesheet" href="./assets/css/widget-style.css">  
    <!-- jQuery và Bootstrap JavaScript -->
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
                                    <li><a href="admin_dashboard.php"><i class="fas fa-cogs"></i> Quản lý vi phạm</a></li>
                                <?php endif; ?>
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
            <h1><i class="fas fa-cogs"></i> Quản lý Vi Phạm Giao Thông</h1>
            <p>Hệ thống quản lý và cập nhật thông tin vi phạm giao thông dành cho quản trị viên</p>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert-container" id="success-alert">
                <div class="alert alert-success alert-dismissible fade in">
                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                    <strong><i class="fas fa-check-circle"></i> Thành công!</strong> <?php echo $success_message; ?>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    $("#success-alert").fadeOut(500);
                }, 1000);
            </script>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert-container" id="error-alert">
                <div class="alert alert-danger alert-dismissible fade in">
                    <a href="#" class="close" data-dismiss="alert"></a>
                    <strong><i class="fas fa-exclamation-circle"></i> Lỗi!</strong> <?php echo $error_message; ?>
                </div>
            </div>
            <script>
                setTimeout(function() {
                    $("#error-alert").fadeOut(500);
                }, 1000);
            </script>
        <?php endif; ?>

        <!-- Admin Dashboard Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="admin-header">
                    <h3><i class="fas fa-tachometer-alt"></i> Trang quản trị - <?php echo $user_name; ?> <span class="label label-primary">Admin</span></h3>
                </div>
            </div>
        </div>

        <!-- Thống kê chung -->
        <div class="row admin-stats">
            <div class="col-md-3">
                <div class="admin-stat-box">
                    <h4><i class="fas fa-chart-bar"></i> Tổng số vi phạm</h4>
                    <div class="stat-number"><?php echo number_format($stats['total_violations']); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-stat-box">
                    <h4><i class="fas fa-check-circle"></i> Đã thanh toán</h4>
                    <div class="stat-number status-paid"><?php echo number_format($stats['total_paid']); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-stat-box">
                    <h4><i class="fas fa-times-circle"></i> Chưa thanh toán</h4>
                    <div class="stat-number status-unpaid"><?php echo number_format($stats['total_unpaid']); ?></div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="admin-stat-box">
                    <h4><i class="fas fa-money-bill-wave"></i> Tổng tiền phạt</h4>
                    <div class="stat-number"><?php echo number_format($stats['total_amount']); ?> VNĐ</div>
                </div>
            </div>
        </div>

        <!-- Thêm vi phạm mới -->
        <div class="panel panel-admin">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-plus-circle"></i> Thêm vi phạm mới</h3>
            </div>
            <div class="panel-body">
                <form action="" method="post" class="form-horizontal" id="violationForm">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Người vi phạm</label>
                                <div class="col-sm-8">
                                    <input type="text" name="user_name" class="form-control" required placeholder="Nhập tên người vi phạm">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Biển số xe</label>
                                <div class="col-sm-8">
                                    <input type="text" name="license_plate" class="form-control" required placeholder="Nhập biển số xe">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Loại vi phạm</label>
                                <div class="col-sm-8">
                                    <select name="violation_id" id="violation_id" class="form-control" required onchange="updateFineRange()">
                                        <option value="">-- Chọn loại vi phạm --</option>
                                        <?php foreach ($violations as $violation): ?>
                                            <option value="<?php echo $violation['violation_id']; ?>" 
                                                    data-min="<?php echo $violation['min_fine']; ?>" 
                                                    data-max="<?php echo $violation['max_fine']; ?>">
                                                <?php echo htmlspecialchars($violation['violation_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Số tiền phạt (VNĐ)</label>
                                <div class="col-sm-8">
                                    <input type="number" name="fine_amount" id="fine_amount" class="form-control" required min="0">
                                    <span class="help-block" id="fine_range"></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Địa điểm vi phạm</label>
                                <div class="col-sm-8">
                                    <input type="text" name="violation_location" class="form-control" required placeholder="Nhập địa chỉ vi phạm">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4">Trạng thái thanh toán</label>
                                <div class="col-sm-8">
                                    <p class="form-control-static" style = "color: black"><strong style = "color: red">CHƯA NỘP PHẠT</strong> (Mặc định với vi phạm mới) </p>
                                    <!-- Input ẩn để gửi giá trị -->
                                    <input type="hidden" name="payment_status" value="Chưa nộp phạt">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" name="add_violation" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Thêm mới
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Danh sách vi phạm -->
        <div class="panel panel-admin">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-list"></i> Danh sách vi phạm</h3>
            </div>
            <div class="panel-body">
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Người vi phạm</th>
                                <th>Biển số xe</th>
                                <th>Loại vi phạm</th>
                                <th>Ngày xử lý</th>
                                <th>Địa điểm</th>
                                <th>Mức phạt</th>
                                <th>Trạng thái</th>
                                <th>Thao tác</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($violations_list as $violation): ?>
                                <tr>
                                    <td><?php echo $violation['user_violation_id']; ?></td>
                                    <td><?php echo htmlspecialchars($violation['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($violation['license_plate']); ?></td>
                                    <td><?php echo htmlspecialchars($violation['violation_name']); ?></td>
                                    <td><?php echo $violation['processed_at'] ? date('d/m/Y H:i', strtotime($violation['processed_at'])) : 'N/A'; ?></td>
                                    <td><?php echo htmlspecialchars($violation['violation_location']); ?></td>
                                    <td><?php echo number_format($violation['fine_amount'], 0, ',', '.') . ' VNĐ'; ?></td>
                                    <td>
                                        <?php if ($violation['payment_status'] == 'Đã thanh toán'): ?>
                                            <span class="label label-success">Đã thanh toán</span>
                                        <?php else: ?>
                                            <span class="label label-danger">Chưa nộp phạt</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="table-actions">
                                        <a href="edit_violation.php?id=<?php echo $violation['user_violation_id']; ?>" class="btn btn-sm btn-warning">
                                            <i class="fas fa-edit"></i> Sửa
                                        </a>
                                        <a href="admin_dashboard.php?delete=<?php echo $violation['user_violation_id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Bạn có chắc chắn muốn xóa vi phạm này?');">
                                            <i class="fas fa-trash"></i> Xóa
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            
                            <?php if (empty($violations_list)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">Không có dữ liệu vi phạm</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <!-- Phân trang -->
                <?php if ($total_pages > 1): ?>
                    <nav aria-label="Page navigation">
                        <ul class="pagination">
                            <?php if ($page > 1): ?>
                                <li>
                                    <a href="?page=<?php echo ($page - 1); ?>" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                            
                            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                <li class="<?php echo ($page == $i ? 'active' : ''); ?>">
                                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                                </li>
                            <?php endfor; ?>
                            
                            <?php if ($page < $total_pages): ?>
                                <li>
                                    <a href="?page=<?php echo ($page + 1); ?>" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
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

    <script>
        // JavaScript để kiểm tra và hiển thị khoảng tiền phạt
        function updateFineRange() {
            const violationSelect = document.getElementById('violation_id');
            const fineAmountInput = document.getElementById('fine_amount');
            const fineRangeSpan = document.getElementById('fine_range');
            
            if(violationSelect.selectedIndex > 0) {
                const selectedOption = violationSelect.options[violationSelect.selectedIndex];
                const minFine = parseFloat(selectedOption.getAttribute('data-min'));
                const maxFine = parseFloat(selectedOption.getAttribute('data-max'));
                
                // Hiển thị khoảng phạt tiền
                fineRangeSpan.innerHTML = 'Mức phạt từ: ' + minFine.toLocaleString('vi-VN') + ' VNĐ đến ' + maxFine.toLocaleString('vi-VN') + ' VNĐ';
                
                // // Đặt min và max cho input số tiền phạt
                // fineAmountInput.min = minFine;
                // fineAmountInput.max = maxFine;
                
                // // Đặt giá trị mặc định là mức phạt tối thiểu
                // if(!fineAmountInput.value || fineAmountInput.value < minFine) {
                //     fineAmountInput.value = minFine;
                // }
            } else {
                fineRangeSpan.innerHTML = '';
                fineAmountInput.value = '';
                fineAmountInput.removeAttribute('min');
                fineAmountInput.removeAttribute('max');
            }
        }

        // Kiểm tra form trước khi submit để đảm bảo số tiền phạt nằm trong khoảng
        document.getElementById('violationForm').addEventListener('submit', function(event) {
            const violationSelect = document.getElementById('violation_id');
            const fineAmountInput = document.getElementById('fine_amount');
            
            if(violationSelect.selectedIndex > 0) {
                const selectedOption = violationSelect.options[violationSelect.selectedIndex];
                const minFine = parseFloat(selectedOption.getAttribute('data-min'));
                const maxFine = parseFloat(selectedOption.getAttribute('data-max'));
                const currentFine = parseFloat(fineAmountInput.value);
                
                if(currentFine < minFine || currentFine > maxFine) {
                    alert('Số tiền phạt phải nằm trong khoảng từ ' + minFine.toLocaleString('vi-VN') + ' VNĐ đến ' + maxFine.toLocaleString('vi-VN') + ' VNĐ');
                    event.preventDefault(); // Ngăn form submit nếu giá trị không hợp lệ
                    return false;
                }
            }
        });

        // Thêm sự kiện onchange cho select box vi phạm
        document.addEventListener('DOMContentLoaded', function() {
            const violationSelect = document.getElementById('violation_id');
            if(violationSelect) {
                violationSelect.addEventListener('change', updateFineRange);
                // Gọi hàm updateFineRange khi trang tải xong để hiển thị giá trị ban đầu
                updateFineRange();
            }
            
            // Định dạng số tiền khi nhập
            const fineAmountInput = document.getElementById('fine_amount');
            if(fineAmountInput) {
                fineAmountInput.addEventListener('input', function(e) {
                    // Đảm bảo giá trị nhập vào là số
                    let value = this.value.replace(/[^0-9]/g, '');
                    
                    // Kiểm tra giới hạn min-max nếu có
                    if(this.hasAttribute('min') && value < parseFloat(this.getAttribute('min'))) {
                        value = this.getAttribute('min');
                    }
                    if(this.hasAttribute('max') && value > parseFloat(this.getAttribute('max'))) {
                        value = this.getAttribute('max');
                    }
                    
                    this.value = value;
                });
            }
            
            // Thêm các chức năng bổ sung cho form xử lý vi phạm nếu có
            const licensePlateSelect = document.getElementById('vehicle_id');
            if(licensePlateSelect) {
                licensePlateSelect.addEventListener('change', function() {
                    // Nếu cần lấy thông tin phương tiện dựa trên biển số xe
                    const selectedOption = this.options[this.selectedIndex];
                    const vehicleType = selectedOption.getAttribute('data-type');
                    const vehicleMake = selectedOption.getAttribute('data-make');
                    
                    // Hiển thị thông tin phương tiện nếu cần
                    const vehicleInfoDiv = document.getElementById('vehicle_info');
                    if(vehicleInfoDiv && vehicleType && vehicleMake) {
                        vehicleInfoDiv.innerHTML = 'Loại xe: ' + vehicleType + ', Hãng xe: ' + vehicleMake;
                    }
                });
            }
            
            // Xử lý hiển thị ngày vi phạm
            const processedAtInput = document.getElementById('processed_at');
            if(processedAtInput) {
                // Đặt giá trị mặc định là ngày hôm nay
                const today = new Date();
                const year = today.getFullYear();
                const month = String(today.getMonth() + 1).padStart(2, '0');
                const day = String(today.getDate()).padStart(2, '0');
                const hours = String(today.getHours()).padStart(2, '0');
                const minutes = String(today.getMinutes()).padStart(2, '0');
                
                // Format: yyyy-MM-ddThh:mm (định dạng chuẩn cho input type="datetime-local")
                const formattedDateTime = `${year}-${month}-${day}T${hours}:${minutes}`;
                processedAtInput.value = formattedDateTime;
            }
        });

        // Hỗ trợ tìm kiếm trong form tìm kiếm vi phạm nếu có
        function searchViolations() {
            const searchInput = document.getElementById('search_term');
            const searchBy = document.getElementById('search_by');
            
            if(searchInput && searchBy) {
                const term = searchInput.value.trim();
                if(term === '') {
                    alert('Vui lòng nhập từ khóa tìm kiếm');
                    return false;
                }
                
                // Kiểm tra định dạng biển số xe nếu tìm theo biển số
                if(searchBy.value === 'license_plate') {
                    // Regex kiểm tra biển số xe Việt Nam
                    const licensePlateRegex = /^[0-9]{2}[A-Za-z]{1,2}-[0-9]{4,5}$/;
                    if(!licensePlateRegex.test(term)) {
                        alert('Biển số xe không đúng định dạng (VD: 51F-12345)');
                        return false;
                    }
                }
                
                return true; // Cho phép form submit nếu mọi thứ hợp lệ
            }
            
            return true;
        }
    </script>