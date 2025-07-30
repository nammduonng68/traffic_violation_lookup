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

// Kiểm tra ID vi phạm
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: admin_dashboard.php");
    exit();
}

$violation_id = mysqli_real_escape_string($conn, $_GET['id']);

// Lấy thông tin vi phạm hiện tại
$get_violation_query = "SELECT 
                            uv.*,
                            u.full_name,
                            v.license_plate,
                            vio.violation_name,
                            vio.min_fine,
                            vio.max_fine
                        FROM UserViolation uv 
                        JOIN User u ON uv.user_id = u.user_id
                        JOIN Vehicle v ON uv.vehicle_id = v.vehicle_id 
                        JOIN Violation vio ON uv.violation_id = vio.violation_id 
                        WHERE uv.user_violation_id = '$violation_id'";

$result = mysqli_query($conn, $get_violation_query);

if (mysqli_num_rows($result) == 0) {
    header("Location: admin_dashboard.php");
    exit();
}

$violation_data = mysqli_fetch_assoc($result);

// Xử lý cập nhật vi phạm
if (isset($_POST['update_violation'])) {
    $fine_amount = mysqli_real_escape_string($conn, $_POST['fine_amount']);
    $violation_location = mysqli_real_escape_string($conn, $_POST['violation_location']);
    $payment_status = mysqli_real_escape_string($conn, $_POST['payment_status']);
    $current_date = date('Y-m-d H:i:s');
    
    // Kiểm tra số tiền phạt có nằm trong khoảng quy định không
    if ($fine_amount < $violation_data['min_fine'] || $fine_amount > $violation_data['max_fine']) {
        $error_message = "Số tiền phạt phải nằm trong khoảng từ " . number_format($violation_data['min_fine']) . " VNĐ đến " . number_format($violation_data['max_fine']) . " VNĐ";
    } else {
        // Cập nhật thông tin vi phạm
        $update_query = "UPDATE UserViolation SET 
                            fine_amount = '$fine_amount', 
                            violation_location = '$violation_location', 
                            payment_status = '$payment_status',
                            processed_by = '$user_name',
                            processed_at = '$current_date',
                            status = 'Đã xử lý'
                        WHERE user_violation_id = '$violation_id'";
        
        if (mysqli_query($conn, $update_query)) {
            $success_message = "Cập nhật vi phạm thành công!";
            
            // Cập nhật lại dữ liệu sau khi sửa
            $result = mysqli_query($conn, $get_violation_query);
            $violation_data = mysqli_fetch_assoc($result);
        } else {
            $error_message = "Lỗi: " . mysqli_error($conn);
        }
    }
}

// Lấy thông tin người dùng cho dropdown
$users_query = "SELECT user_id, full_name FROM User";
$users_result = mysqli_query($conn, $users_query);
$users = [];
while ($row = mysqli_fetch_assoc($users_result)) {
    $users[] = $row;
}

// Lấy thông tin phương tiện cho dropdown
$vehicles_query = "SELECT vehicle_id, license_plate, owner_id FROM Vehicle";
$vehicles_result = mysqli_query($conn, $vehicles_query);
$vehicles = [];
while ($row = mysqli_fetch_assoc($vehicles_result)) {
    $vehicles[] = $row;
}

// Lấy thông tin loại vi phạm cho dropdown
$violations_query = "SELECT violation_id, violation_name, min_fine, max_fine FROM Violation";
$violations_result = mysqli_query($conn, $violations_query);
$violations = [];
while ($row = mysqli_fetch_assoc($violations_result)) {
    $violations[] = $row;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sửa Vi Phạm - Tra Cứu Vi Phạm Luật Giao Thông</title>
    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/edit_violation-style.css">
    <link rel="stylesheet" href="./assets/css/dashboard-style.css"> 
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
            <h1><i class="fas fa-edit"></i> Sửa Thông Tin Vi Phạm</h1>
            <p>Chỉnh sửa và cập nhật thông tin vi phạm giao thông</p>
        </div>
    </div>

    <div class="container">
        <!-- Alert Messages -->
        <?php if (isset($success_message)): ?>
            <div class="alert-container">
                <div class="alert alert-success alert-dismissible fade in">
                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                    <strong><i class="fas fa-check-circle"></i> Thành công!</strong> <?php echo $success_message; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error_message)): ?>
            <div class="alert-container">
                <div class="alert alert-danger alert-dismissible fade in">
                    <a href="#" class="close" data-dismiss="alert" aria-label="close"></a>
                    <strong><i class="fas fa-exclamation-circle"></i> Lỗi!</strong> <?php echo $error_message; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- Admin Dashboard Header -->
        <div class="row">
            <div class="col-md-12">
                <div class="admin-header">
                    <h3><i class="fas fa-edit"></i> Sửa Vi Phạm </h3>
                </div>
            </div>
        </div>

        <!-- Thông tin vi phạm hiện tại -->
        <div class="row">
            <div class="col-md-12">
                <div class="violation-info">
                    <h4>Thông tin vi phạm hiện tại</h4>
                    <div class="row">
                        <div class="col-md-6">
                            <p><strong>Người vi phạm:</strong> <?php echo htmlspecialchars($violation_data['full_name']); ?></p>
                            <p><strong>Biển số xe:</strong> <?php echo htmlspecialchars($violation_data['license_plate']); ?></p>
                            <p><strong>Loại vi phạm:</strong> <?php echo htmlspecialchars($violation_data['violation_name']); ?></p>
                            <p><strong>Mức phạt hiện tại:</strong> <?php echo number_format($violation_data['fine_amount'], 0, ',', '.') . ' VNĐ'; ?></p>
                        </div>
                        <div class="col-md-6">
                            <p><strong>Khoảng phạt cho phép:</strong> <?php echo number_format($violation_data['min_fine'], 0, ',', '.') . ' VNĐ - ' . number_format($violation_data['max_fine'], 0, ',', '.') . ' VNĐ'; ?></p>
                            <p><strong>Địa điểm vi phạm:</strong> <?php echo htmlspecialchars($violation_data['violation_location']); ?></p>
                            <p><strong>Trạng thái thanh toán:</strong> <?php echo $violation_data['payment_status']; ?></p>
                            <p><strong>Xử lý bởi:</strong> <?php echo $violation_data['processed_by'] ? htmlspecialchars($violation_data['processed_by']) : 'Chưa xử lý'; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Form cập nhật vi phạm -->
        <div class="panel panel-admin">
            <div class="panel-heading">
                <h3 class="panel-title"><i class="fas fa-edit"></i> Cập nhật thông tin vi phạm</h3>
            </div>
            <div class="panel-body">
                <form action="" method="post" class="form-horizontal">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label col-sm-4">Người vi phạm</label>
                                <div class="col-sm-8">
                                    <p class="form-control-static"><?php echo htmlspecialchars($violation_data['full_name']); ?></p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4">Phương tiện</label>
                                <div class="col-sm-8">
                                    <p class="form-control-static"><?php echo htmlspecialchars($violation_data['license_plate']); ?></p>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4">Loại vi phạm</label>
                                <div class="col-sm-8">
                                    <p class="form-control-static"><?php echo htmlspecialchars($violation_data['violation_name']); ?></p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Số tiền phạt (VNĐ)</label>
                                <div class="col-sm-8">
                                    <input type="number" name="fine_amount" id="fine_amount" class="form-control" required min="<?php echo $violation_data['min_fine']; ?>" max="<?php echo $violation_data['max_fine']; ?>" value="<?php echo $violation_data['fine_amount']; ?>">
                                    <span class="help-block">Khoảng phạt: <?php echo number_format($violation_data['min_fine'], 0, ',', '.') . ' VNĐ - ' . number_format($violation_data['max_fine'], 0, ',', '.') . ' VNĐ'; ?></span>
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Địa điểm vi phạm</label>
                                <div class="col-sm-8">
                                    <input type="text" name="violation_location" class="form-control" required value="<?php echo htmlspecialchars($violation_data['violation_location']); ?>">
                                </div>
                            </div>
                            <div class="form-group">
                                <label class="control-label col-sm-4 required-field">Trạng thái thanh toán</label>
                                <div class="col-sm-8">
                                    <select name="payment_status" class="form-control" required>
                                        <option value="Chưa thanh toán" <?php echo ($violation_data['payment_status'] == 'Chưa thanh toán') ? 'selected' : ''; ?>>Chưa thanh toán</option>
                                        <option value="Đã thanh toán" <?php echo ($violation_data['payment_status'] == 'Đã thanh toán') ? 'selected' : ''; ?>>Đã thanh toán</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="form-group">
                        <div class="col-sm-offset-2 col-sm-10">
                            <button type="submit" name="update_violation" class="btn btn-primary">
                                <i class="fas fa-save"></i> Cập nhật
                            </button>
                            <a href="admin_dashboard.php" class="btn btn-default">
                                <i class="fas fa-arrow-left"></i> Quay lại
                            </a>
                        </div>
                    </div>
                </form>
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
        // Tự động đóng thông báo sau 5 giây
        $(document).ready(function(){
            setTimeout(function(){
                $(".alert").alert('close');
            }, 1000);
        });
    </script>
</body>
</html>