document.addEventListener("DOMContentLoaded", function() {
    document.querySelector(".float-contact").style.display = "none";
});
document.addEventListener("DOMContentLoaded", function() {
    document.querySelector(".widget-toggle-cancel").style.display = "none";
});

function toggleSubmenu() {
    var submenu = document.querySelector(".float-contact");
    submenu.style.display = (submenu.style.display === "block") ? "none" : "block";
    var submenu2 = document.querySelector(".widget-toggle-cancel");
    submenu2.style.display = (submenu2.style.display === "block") ? "none" : "block";
    var submenu3 = document.querySelector(".widget-toggle");
    submenu3.style.display = (submenu3.style.display === "none") ? "block" : "none";
}
