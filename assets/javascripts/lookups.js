// Tra cứu theo biển số xe
function searchVehicle() {
    const vehicleNumber = document.getElementById('vehicle-number').value.trim();
    const vehicleError = document.getElementById('vehicle-error');

    // Xóa thông báo lỗi cũ
    vehicleError.textContent = '';
            
    // Kiểm tra biển số xe có được nhập hay không
    if (!vehicleNumber) {
        vehicleError.textContent = 'Vui lòng nhập biển số xe';
        document.getElementById('results').style.display = 'none';
        return;
    }

    if (vehicleNumber == document.getElementById('plate-result').textContent) {
        showResults();
    }
    else {
        vehicleError.textContent = 'Không tìm thấy biển số xe có lỗi vi phạm';
        return;
    }
}

function showResults() {
    document.getElementById('results').style.display = 'block';
    // Scroll xuống kết quả
    $('html, body').animate({
        scrollTop: $("#results").offset().top - 70
    }, 500);
}

// Tra cứu theo biển số xe
function searchLicense() {
    const licenseNumber = document.getElementById('license-number').value.trim();
    const licenseError = document.getElementById('license-error');

    // Xóa thông báo lỗi cũ
    licenseError.textContent = '';
            
    // Kiểm tra biển số xe có được nhập hay không
    if (!licenseNumber) {
        licenseError.textContent = 'Vui lòng nhập số GPLX';
        document.getElementById('driver-license-results').style.display = 'none';
        return;
    }

    if (licenseNumber == document.getElementById('license-result').textContent) {
        showLicenseResults();
    }
    else {
        licenseError.textContent = 'Không tìm thấy số GPLX có lỗi vi phạm';
        return;
    }
}

function showLicenseResults() {
    document.getElementById('driver-license-results').style.display = 'block';
    // Scroll xuống kết quả
    $('html, body').animate({
        scrollTop: $("#driver-license-results").offset().top - 70
    }, 500);
}

// Tra cứu theo số CCCD/CMND
function searchID() {
    const idNumber = document.getElementById('id-number').value.trim();
    const idError = document.getElementById('id-error');

    // Xóa thông báo lỗi cũ
    idError.textContent = '';
            
    // Kiểm tra biển số xe có được nhập hay không
    if (!idNumber) {
        idError.textContent = 'Vui lòng nhập số CMND/CCCD';
        document.getElementById('id-results').style.display = 'none';
        return;
    }

    if (idNumber == document.getElementById('id-result').textContent) {
        showIDResults();
    }
    else {
        idError.textContent = 'Không tìm thấy số CMND/CCCD có lỗi vi phạm';
        return;
    }
}

function showIDResults() {
    document.getElementById('id-results').style.display = 'block';
    // Scroll xuống kết quả
    $('html, body').animate({
        scrollTop: $("#id-results").offset().top - 70
    }, 500);
}