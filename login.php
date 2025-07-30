<?php
// Bao gồm file kết nối
require_once 'db_connect.php';
session_start();

$error_message = '';
$success_message = '';

// Xử lý form đăng nhập khi submit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    
    // Kiểm tra xem username là email hay số điện thoại
    $query = "SELECT * FROM User WHERE username = ? OR email = ? OR phone = ?";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sss", $username, $username, $username);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // Trong thực tế, cần sử dụng password_verify() để kiểm tra mật khẩu đã băm
        // Nhưng vì trong cơ sở dữ liệu mẫu không có mật khẩu băm thực sự, nên tạm thời kiểm tra trực tiếp
        if ($password == $user['password'] || password_verify($password, $user['password'])) {
            // Đăng nhập thành công
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            
            // Redirect dựa trên vai trò của người dùng
            if ($user['role'] == 'Admin') {
                header("Location: admin_dashboard.php");
            } else {
                header("Location: dashboard.php");
            }
            exit();
        } else {
            $error_message = "Mật khẩu không đúng!";
        }
    } else {
        $error_message = "Tài khoản không tồn tại!";
    }
}

// Xử lý form đăng ký khi submit
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    // Lấy thông tin từ form
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = mysqli_real_escape_string($conn, $_POST['full_name']);
    $email = mysqli_real_escape_string($conn, $_POST['email']);
    $phone = mysqli_real_escape_string($conn, $_POST['phone']);
    $address = mysqli_real_escape_string($conn, $_POST['address']);
    $identity_number = mysqli_real_escape_string($conn, $_POST['identity_number']);
    
    // Kiểm tra mật khẩu có khớp nhau không
    if ($password != $confirm_password) {
        $error_message = "Mật khẩu không khớp!";
    } else {
        // Kiểm tra username đã tồn tại hay chưa
        $check_query = "SELECT * FROM User WHERE username = ? OR email = ? OR identity_number = ?";
        $stmt = mysqli_prepare($conn, $check_query);
        mysqli_stmt_bind_param($stmt, "sss", $username, $email, $identity_number);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        
        if (mysqli_num_rows($result) > 0) {
            $user = mysqli_fetch_assoc($result);
            if ($user['username'] == $username) {
                $error_message = "Tên đăng nhập đã tồn tại!";
            } elseif ($user['email'] == $email) {
                $error_message = "Email đã được sử dụng!";
            } elseif ($user['identity_number'] == $identity_number) {
                $error_message = "Số CMND/CCCD đã được sử dụng!";
            }
        } else {
            // Băm mật khẩu để lưu vào database
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Thêm người dùng mới vào database
            $insert_query = "INSERT INTO User (username, password, full_name, email, phone, address, identity_number, role) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, 'User')";
            $stmt = mysqli_prepare($conn, $insert_query);
            mysqli_stmt_bind_param($stmt, "sssssss", $username, $hashed_password, $full_name, $email, $phone, $address, $identity_number);
            
            if (mysqli_stmt_execute($stmt)) {
                $success_message = "Đăng ký thành công! Vui lòng đăng nhập để tiếp tục.";
                // Chuyển hướng đến trang đăng nhập sau 3 giây
                header("refresh:3;url=login.php");
            } else {
                $error_message = "Đăng ký thất bại: " . mysqli_error($conn);
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Đăng Nhập - Tra Cứu Vi Phạm Luật Giao Thông</title>
    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/login-style.css">
    <link rel="stylesheet" href="./assets/css/navbar.css">
    <link rel="stylesheet" href="./assets/css/footer.css">
    <link rel="stylesheet" href="./assets/css/responsive.css">
    <link rel="stylesheet" href="./assets/css/widget-style.css">
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
                <a class="navbar-brand" href="index.php"><i class="fas fa-car-crash"></i>  Cổng tra cứu VPGT</a>
            </div>
            <div id="navbar" class="collapse navbar-collapse">
                <ul class="nav navbar-nav">
                    <li><a href="index.php">Trang chủ</a></li>
                    <li><a href="#">Giới thiệu</a></li>
                    <li><a href="#">Quy định</a></li>
                    <li><a href="#">Liên hệ</a></li>
                </ul>
                <ul class="nav navbar-nav navbar-right">
                    <li class="dropdown">
                        <a class="dropdown-toggle" data-toggle="dropdown" href="#">Tài khoản <span class="caret"></span></a>
                        <ul class="dropdown-menu">
                            <li><a href="login.php"><span class="glyphicon glyphicon-log-in"></span> Đăng nhập</a></li>
                        </ul>
                    </li>    
                </ul>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="jumbotron">
        <div class="container">
            <h1>Đăng nhập hệ thống</h1>
            <p>Truy cập tài khoản để quản lý và thanh toán các khoản phạt vi phạm giao thông</p>
        </div>
    </div>

    <div class="container">
        <div class="row">
            <div class="col-md-8 col-md-offset-2">
                <div class="panel panel-primary login-panel">
                    <div class="panel-heading">
                        <h3 class="panel-title">Đăng nhập tài khoản</h3>
                    </div>
                    <div class="panel-body">
                        <div class="row">
                            <!-- <div class="col-md-6">
                                <div class="login-image"></div>
                            </div> -->
                            <div class="col-md-12">
                                <?php if (!empty($error_message)): ?>
                                    <div class="alert alert-danger">
                                        <?php echo $error_message; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($success_message)): ?>
                                    <div class="alert alert-success">
                                        <?php echo $success_message; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <form id="login-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                                    <div class="form-group">
                                        <label for="username">Email / Số điện thoại:</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="glyphicon glyphicon-user"></i></span>
                                            <input type="text" class="form-control" id="username" name="username" placeholder="Nhập tên đăng nhập hoặc số điện thoại" required>
                                        </div>
                                    </div>
                                    <div class="form-group">
                                        <label for="password">Mật khẩu:</label>
                                        <div class="input-group">
                                            <span class="input-group-addon"><i class="glyphicon glyphicon-lock"></i></span>
                                            <input type="password" class="form-control" id="password" name="password" placeholder="Nhập mật khẩu" required>
                                        </div>
                                    </div>
                                    <div class="checkbox">
                                        <label>
                                            <input type="checkbox"> Ghi nhớ đăng nhập
                                        </label>
                                    </div>
                                    <button type="submit" name="login" class="btn btn-primary btn-lg btn-block">Đăng nhập</button>
                                    <div class="text-center" style="margin-top: 15px;">
                                        <a href="#" data-toggle="modal" data-target="#forgotPasswordModal">Quên mật khẩu?</a> | 
                                        <a href="#" data-toggle="modal" data-target="#registerModal">Đăng ký tài khoản mới</a>
                                    </div>
                                    
                                    <div class="login-options">
                                        <h4>Hoặc đăng nhập với:</h4>
                                        <div class="social-buttons">
                                            <a href="#" class="btn btn-primary"><i class="glyphicon glyphicon-user"></i> VNeID</a>
                                            <a href="#" class="btn btn-info"><i class="glyphicon glyphicon-phone"></i> SMS OTP</a>
                                        </div>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Forgot Password Modal -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Khôi phục mật khẩu</h4>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="form-group">
                            <label for="recovery-email">Email đăng ký:</label>
                            <input type="email" class="form-control" id="recovery-email" placeholder="Nhập email đã đăng ký">
                        </div>
                        <div class="text-center">
                            <button type="button" class="btn btn-primary">Gửi yêu cầu khôi phục</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Register Modal -->
    <div class="modal fade" id="registerModal" tabindex="-1" role="dialog">
        <div class="modal-dialog modal-lg" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Đăng ký tài khoản mới</h4>
                </div>
                <div class="modal-body">
                    <?php if (!empty($error_message)): ?>
                        <div class="alert alert-danger">
                            <?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($success_message)): ?>
                        <div class="alert alert-success">
                            <?php echo $success_message; ?>
                        </div>
                    <?php endif; ?>
                    <form id="register-form" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="username">Tên đăng nhập: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="full_name">Họ và tên: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="full_name" name="full_name" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="email">Email: <span class="text-danger">*</span></label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="phone">Số điện thoại: <span class="text-danger">*</span></label>
                                    <input type="tel" class="form-control" id="phone" name="phone" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="address">Địa chỉ: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="address" name="address">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="identity_number">Số CMND/CCCD: <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="identity_number" name="identity_number" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="password">Mật khẩu: <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="confirm_password">Xác nhận mật khẩu: <span class="text-danger">*</span></label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="checkbox">
                            <label>
                                <input type="checkbox" name="agree" required> Tôi đồng ý với <a href="#">điều khoản sử dụng</a> và <a href="#">chính sách bảo mật</a>
                            </label>
                        </div>
                        
                        <button type="submit" name="register" class="btn btn-success btn-lg btn-block">Đăng ký</button>
                        
                        <div class="text-center" style="margin-top: 15px;">
                            Đã có tài khoản? <a href="login.php">Đăng nhập ngay</a>
                        </div>
                    </form>
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

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>

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
    </script>
</body>
</html>