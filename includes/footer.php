    </div><!-- End .page-content -->
</div><!-- End .main-wrapper -->

<!-- Global Delete Form (CSRF Protected) -->
<form id="globalDeleteForm" method="POST" style="display:none;">
    <input type="hidden" name="action" value="delete">
    <input type="hidden" name="delete_id" id="globalDeleteId">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
</form>
<script>
function submitPostDelete(actionUrl, id) {
    const form = document.getElementById('globalDeleteForm');
    form.action = actionUrl;
    document.getElementById('globalDeleteId').value = id;
    form.submit();
}
</script>

<!-- Bootstrap 5 Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom JS -->
<script src="assets/js/main.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('sidebar').classList.toggle('open');
    document.getElementById('sidebarOverlay').classList.toggle('show');
}
function closeSidebar() {
    document.getElementById('sidebar').classList.remove('open');
    document.getElementById('sidebarOverlay').classList.remove('show');
}
</script>
</body>
</html>
