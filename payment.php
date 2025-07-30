<?php
// Kết nối tới database
require_once 'db_connect.php';
session_start();

// Kiểm tra phiên đăng nhập
$is_logged_in = isset($_SESSION['user_id']);
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0;
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'User';
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Khách';


$user_id = $_SESSION['user_id'];
$user_name = isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Khách';

// Kiểm tra ID vi phạm từ tham số URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    // Nếu không có ID vi phạm, chuyển về dashboard
    header("Location: dashboard.php");
    exit();
}

$violation_id = mysqli_real_escape_string($conn, $_GET['id']);

// Lấy thông tin chi tiết vi phạm từ database - Chỉ lấy vi phạm của user hiện tại
$query = "SELECT 
            uv.user_violation_id,
            uv.violation_id,
            uv.vehicle_id,
            uv.user_id,
            uv.processed_at,
            uv.violation_location,
            uv.fine_amount,
            uv.payment_status,
            v.license_plate,
            vio.violation_name
          FROM UserViolation uv
          JOIN Vehicle v ON uv.vehicle_id = v.vehicle_id
          JOIN Violation vio ON uv.violation_id = vio.violation_id
          WHERE uv.user_violation_id = '$violation_id' AND uv.user_id = '$user_id'";

$result = mysqli_query($conn, $query);

// Kiểm tra nếu không tìm thấy vi phạm hoặc không có quyền xem
if (!$result || mysqli_num_rows($result) == 0) {
    header("Location: dashboard.php");
    exit();
}

$violation = mysqli_fetch_assoc($result);

// Kiểm tra nếu vi phạm đã được thanh toán
if ($violation['payment_status'] == 'Đã thanh toán') {
    // Chuyển về dashboard với thông báo
    header("Location: dashboard.php?msg=already_paid");
    exit();
}

// Tổng số tiền thanh toán (không còn phí xử lý)
$total_amount = $violation['fine_amount'];

// Xử lý khi nhận yêu cầu thanh toán từ form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_confirm'])) {
    // Lưu thông tin thanh toán
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);
    $payment_date = date('Y-m-d H:i:s');

    // Cập nhật trạng thái thanh toán trong database
    $update_query = "UPDATE UserViolation SET 
                        payment_status = 'Đã thanh toán'
                    WHERE user_violation_id = '$violation_id'";
    
    if (mysqli_query($conn, $update_query)) {
        // Chuyển hướng về dashboard với thông báo thành công
        header("Location: dashboard.php?msg=payment_success");
        exit();
    } else {
        $error_message = "Lỗi khi cập nhật thanh toán: " . mysqli_error($conn);
    }
}

// Xử lý AJAX cho việc xác nhận thanh toán
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_payment_confirm'])) {
    $payment_method = mysqli_real_escape_string($conn, $_POST['payment_method']);

    // Cập nhật trạng thái thanh toán trong database - Không sử dụng các trường không tồn tại
    $update_query = "UPDATE UserViolation SET 
                        payment_status = 'Đã thanh toán'
                    WHERE user_violation_id = '$violation_id'";
    
    if (mysqli_query($conn, $update_query)) {
        echo json_encode(['success' => true, 'message' => 'Thanh toán thành công']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Lỗi khi cập nhật thanh toán: ' . mysqli_error($conn)]);
    }
    exit();
}

// Format số tiền
function formatCurrency($amount) {
    return number_format($amount, 0, ',', '.');
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thanh toán vi phạm - Tra Cứu Vi Phạm Luật Giao Thông</title>
    <!-- Bootstrap 3 CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/css/bootstrap.min.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/dashboard-style.css">
    <link rel="stylesheet" href="./assets/css/payment-style.css">
    <link rel="stylesheet" href="./assets/css/navbar.css">
    <link rel="stylesheet" href="./assets/css/footer.css">
    <link rel="stylesheet" href="./assets/css/responsive.css">
    <link rel="stylesheet" href="./assets/css/widget-style.css">  
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/twitter-bootstrap/3.4.1/js/bootstrap.min.js"></script>
    <script src="./assets/javascripts/widget.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
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
            <h1>Thanh toán vi phạm</h1>
            <p>Thực hiện thanh toán phạt vi phạm luật giao thông</p>
        </div>
    </div>

    <div class="container">
        <?php if (isset($error_message)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-circle"></i> <?php echo $error_message; ?>
        </div>
        <?php endif; ?>
        
        <div class="row">
            <div class="col-md-8">
                <div class="payment-container">
                    <div class="payment-header">
                        <h3><i class="fas fa-file-invoice"></i> Chi tiết lỗi vi phạm</h3>
                    </div>
                    
                    <div class="violation-details">
                        <div class="row violation-row">
                            <div class="col-md-4"><strong>Biển số xe:</strong></div>
                            <div class="col-md-8"><?php echo htmlspecialchars($violation['license_plate']); ?></div>
                        </div>
                        <div class="row violation-row">
                            <div class="col-md-4"><strong>Lỗi vi phạm:</strong></div>
                            <div class="col-md-8"><?php echo htmlspecialchars($violation['violation_name']); ?></div>
                        </div>
                        <div class="row violation-row">
                            <div class="col-md-4"><strong>Ngày vi phạm:</strong></div>
                            <div class="col-md-8"><?php echo date('d/m/Y H:i', strtotime($violation['processed_at'])); ?></div>
                        </div>
                        <div class="row violation-row">
                            <div class="col-md-4"><strong>Địa điểm:</strong></div>
                            <div class="col-md-8"><?php echo htmlspecialchars($violation['violation_location']); ?></div>
                        </div>
                        <div class="row violation-row">
                            <div class="col-md-4"><strong>Mức phạt:</strong></div>
                            <div class="col-md-8"><span class="text-danger"><?php echo formatCurrency($violation['fine_amount']); ?> VNĐ</span></div>
                        </div>
                    </div>
                    
                    <div class="payment-methods">
                        <h4><i class="fas fa-credit-card"></i> Chọn phương thức thanh toán</h4>
                        
                        <div class="payment-option selected" data-method="momo">
                            <div class="row">
                                <div class="col-md-2 text-center">
                                    <img src="./assets/img/momo.png" alt="MoMo">
                                </div>
                                <div class="col-md-8">
                                    <h4>Ví điện tử MoMo</h4>
                                    <p class="text-muted">Thanh toán nhanh chóng qua ví điện tử MoMo</p>
                                </div>
                                <div class="col-md-2 text-right">
                                    <i class="fas fa-check-circle text-primary" style="font-size: 24px;"></i>
                                </div>
                            </div>
                        </div>
                        
                        <div class="payment-option" data-method="vnpay">
                            <div class="row">
                                <div class="col-md-2 text-center">
                                    <img src="./assets/img/VNPAY.png" alt="VNPay">
                                </div>
                                <div class="col-md-8">
                                    <h4>VNPAY</h4>
                                    <p class="text-muted">Thanh toán qua cổng VNPAY bằng quét mã QR hoặc thẻ ngân hàng nội địa</p>
                                </div>
                                <div class="col-md-2 text-right">
                                    <i class="far fa-circle text-muted" style="font-size: 24px;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-md-4">
                <div class="payment-container">
                    <div class="payment-header">
                        <h3><i class="fas fa-file-invoice-dollar"></i> Tổng thanh toán</h3>
                    </div>
                    
                    <div class="payment-summary">
                        <div class="summary-row">
                            <div>Tiền phạt:</div>
                            <div><?php echo formatCurrency($violation['fine_amount']); ?> VNĐ</div>
                        </div>
                        <div class="summary-row total-row">
                            <div>Tổng cộng:</div>
                            <div class="text-danger"><?php echo formatCurrency($total_amount); ?> VNĐ</div>
                        </div>
                    </div>
                    
                    <div class="payment-actions">
                        <button type="button" class="btn btn-primary btn-block btn-payment" id="btn-proceed-payment">
                            <i class="fas fa-lock"></i> Tiến hành thanh toán
                        </button>
                        <a href="dashboard.php" class="btn btn-default btn-block">
                            <i class="fas fa-arrow-left"></i> Quay lại
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal xác nhận thanh toán -->
    <div class="modal fade" id="confirmPaymentModal" tabindex="-1" role="dialog">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <div class="modal-header">
                    <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                    <h4 class="modal-title">Xác nhận thanh toán</h4>
                </div>
                <div class="modal-body">
                    <div class="text-center" id="payment-method-info">
                        <img src="./assets/img/momo.png" id="payment-logo" alt="MoMo">
                        <h4>Bạn đang thanh toán qua <span id="payment-method-name">MoMo</span></h4>
                    </div>
                    <div class="alert alert-info">
                        <p>Thông tin thanh toán:</p>
                        <ul>
                            <li>ID Vi phạm: <?php echo htmlspecialchars($violation['user_violation_id']); ?></li>
                            <li>Biển số xe: <?php echo htmlspecialchars($violation['license_plate']); ?></li>
                            <li>Tổng số tiền: <strong><?php echo formatCurrency($total_amount); ?> VNĐ</strong></li>
                        </ul>
                    </div>
                    
                    <!-- Thông báo lỗi nhập liệu -->
                    <div class="alert alert-danger" id="validation-error" style="display: none;">
                        <i class="fas fa-exclamation-circle"></i> Vui lòng nhập đầy đủ thông tin
                    </div>
                    
                    <!-- Tab cho phương thức thanh toán -->
                    <div class="payment-method-tab">
                        <ul class="nav nav-tabs" role="tablist">
                            <li role="presentation" class="active"><a href="#account-tab" aria-controls="account-tab" role="tab" data-toggle="tab">Tài khoản liên kết</a></li>
                            <li role="presentation"><a href="#qr-tab" aria-controls="qr-tab" role="tab" data-toggle="tab">Quét mã QR</a></li>
                        </ul>
                        
                        <div class="tab-content">
                            <!-- Tab thông tin tài khoản -->
                            <div role="tabpanel" class="tab-pane active" id="account-tab">
                                <div class="payment-form">
                                    <form id="payment-account-form">
                                        <div class="form-group">
                                            <label for="account-number">Số tài khoản / Số điện thoại:</label>
                                            <input type="text" class="form-control" id="account-number" placeholder="Nhập số tài khoản hoặc số điện thoại đăng ký">
                                        </div>
                                        <div class="form-group">
                                            <label for="account-name">Họ và tên chủ tài khoản:</label>
                                            <input type="text" class="form-control" id="account-name" placeholder="Nhập họ và tên chủ tài khoản">
                                        </div>
                                        <div class="form-group" id="bank-selection-wrapper">
                                            <label for="bank-selection">Ngân hàng:</label>
                                            <select class="form-control" id="bank-selection">
                                                <option value="">-- Chọn ngân hàng --</option>
                                                <option value="vietcombank">Vietcombank</option>
                                                <option value="vietinbank">Vietinbank</option>
                                                <option value="bidv">BIDV</option>
                                                <option value="agribank">Agribank</option>
                                                <option value="tpbank">TPBank</option>
                                                <option value="mbbank">MB Bank</option>
                                                <option value="techcombank">Techcombank</option>
                                            </select>
                                        </div>
                                    </form>
                                </div>
                            </div>
                            
                            <!-- Tab quét mã QR -->
                            <div role="tabpanel" class="tab-pane" id="qr-tab">
                                <div class="qrcode-container">
                                    <div class="qrcode-wrapper">
                                        <div id="qrcode"></div>
                                    </div>
                                    <p class="qr-instruction">Mở ứng dụng <span id="qr-app-name">MoMo</span> và quét mã QR để thanh toán</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-default" data-dismiss="modal">Hủy bỏ</button>
                    <button type="button" class="btn btn-primary" id="confirm-payment-btn">Xác nhận thanh toán</button>
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

        // Xử lý sự kiện khi click vào phương thức thanh toán
        document.querySelectorAll('.payment-option').forEach(function(option) {
            option.addEventListener('click', function() {
                // Bỏ chọn tất cả các phương thức
                document.querySelectorAll('.payment-option').forEach(function(opt) {
                    opt.classList.remove('selected');
                    opt.querySelector('i').className = 'far fa-circle text-muted';
                });
                
                // Chọn phương thức hiện tại
                this.classList.add('selected');
                this.querySelector('i').className = 'fas fa-check-circle text-primary';
            });
        });
        
        // Xử lý sự kiện khi click vào nút thanh toán
        document.getElementById('btn-proceed-payment').addEventListener('click', function() {
            // Lấy phương thức thanh toán đã chọn
            const selectedMethod = document.querySelector('.payment-option.selected').dataset.method;
            
            // Cập nhật thông tin modal
            if (selectedMethod === 'momo') {
                document.getElementById('payment-logo').src = './assets/img/momo.png';
                document.getElementById('payment-method-name').textContent = 'MoMo';
                document.getElementById('qr-app-name').textContent = 'MoMo';
            } else {
                document.getElementById('payment-logo').src = './assets/img/VNPAY.png';
                document.getElementById('payment-method-name').textContent = 'VNPAY';
                document.getElementById('qr-app-name').textContent = 'ngân hàng';
            }
            
            // Tạo QR code
            createQRCode(selectedMethod);
            
            // Hiển thị modal xác nhận
            $('#confirmPaymentModal').modal('show');
            
            // Reset thông báo lỗi
            $('#validation-error').hide();
            
            // Hiển thị tab tài khoản liên kết mặc định
            $('a[href="#account-tab"]').tab('show');
            
            // Kiểm tra và hiển thị nút xác nhận thanh toán theo tab
            toggleConfirmButton();
        });
        
        // Hàm tạo QR code
        function createQRCode(method) {
            // Xóa QR code cũ nếu có
            document.getElementById('qrcode').innerHTML = '';
            
            // Tạo dữ liệu cho QR code
            const qrData = {
                id: '<?php echo $violation['user_violation_id']; ?>',
                licensePlate: '<?php echo $violation['license_plate']; ?>',
                amount: '<?php echo $total_amount; ?>',
                method: method,
                timestamp: new Date().getTime()
            };
            
            // Tạo QR code mới
            new QRCode(document.getElementById('qrcode'), {
                text: JSON.stringify(qrData),
                width: 180,
                height: 180,
                colorDark: '#000000',
                colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.H
            });
        }
        
        // Xử lý khi chuyển đổi tab
        $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
            // Ẩn thông báo lỗi khi chuyển tab
            $('#validation-error').hide();
            
            // Kiểm tra và hiển thị nút xác nhận thanh toán theo tab
            toggleConfirmButton();
        });
        
        // Hàm kiểm tra và hiển thị nút xác nhận thanh toán theo tab
        function toggleConfirmButton() {
            // Kiểm tra tab hiện tại
            if ($('#qr-tab').hasClass('active')) {
                // Nếu đang ở tab QR, ẩn nút xác nhận thanh toán
                $('#confirm-payment-btn').hide();
            } else {
                // Nếu đang ở tab tài khoản liên kết, hiển thị nút xác nhận thanh toán
                $('#confirm-payment-btn').show();
            }
        }
        
        // Xử lý sự kiện khi click vào nút xác nhận thanh toán
        document.getElementById('confirm-payment-btn').addEventListener('click', function() {
            // Nếu đang ở tab tài khoản liên kết, kiểm tra nhập liệu
            if ($('#account-tab').hasClass('active')) {
                const accountNumber = $('#account-number').val().trim();
                const accountName = $('#account-name').val().trim();
                const bankSelection = $('#bank-selection').val();
                
                // Kiểm tra nhập liệu
                if (accountNumber === '' || accountName === '' || bankSelection === '') {
                    // Hiển thị thông báo lỗi nếu không nhập đủ thông tin
                    $('#validation-error').show();
                    return;
                }
            }
            
            const selectedMethod = document.querySelector('.payment-option.selected').dataset.method;
            
            // Hiển thị loading
            $('#confirm-payment-btn').html('<i class="fas fa-spinner fa-spin"></i> Đang xử lý...');
            $('#confirm-payment-btn').prop('disabled', true);
            
            // Gửi yêu cầu cập nhật trạng thái thanh toán
            $.ajax({
                url: window.location.href,
                type: 'POST',
                data: {
                    ajax_payment_confirm: true,
                    payment_method: selectedMethod
                },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        // Đóng modal
                        $('#confirmPaymentModal').modal('hide');
                        
                        // Hiển thị thông báo thành công
                        alert('Thanh toán thành công! Cảm ơn bạn đã thanh toán phạt vi phạm giao thông.');
                        
                        // Chuyển về trang dashboard
                        window.location.href = 'dashboard.php?msg=payment_success';
                    } else {
                        alert('Có lỗi xảy ra: ' + response.message);
                        $('#confirm-payment-btn').html('Xác nhận thanh toán');
                        $('#confirm-payment-btn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Có lỗi khi kết nối đến máy chủ: ' + error);
                    $('#confirm-payment-btn').html('Xác nhận thanh toán');
                    $('#confirm-payment-btn').prop('disabled', false);
                }
            });
        });
    </script>
</body>
</html>