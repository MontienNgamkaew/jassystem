<?php
require_once 'db.php';
require_once 'auth.php';

// Admin only
if ($_SESSION['role'] !== 'admin') {
    header('Location: index.php');
    exit;
}

$msg = '';
$error = '';

// --- CREATE USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $username  = trim($_POST['username'] ?? '');
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $password  = trim($_POST['password'] ?? '');

    if (!$username || !$password) {
        $error = 'กรุณากรอกชื่อผู้ใช้งานและรหัสผ่าน';
    } elseif (strlen($password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        $hash = password_hash($password, PASSWORD_BCRYPT);
        try {
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$username, $hash, $full_name, $role]);
            $msg = 'สร้างผู้ใช้งาน "' . htmlspecialchars($username) . '" สำเร็จ';
        } catch (PDOException $e) {
            $error = 'ชื่อผู้ใช้งานนี้ถูกใช้ไปแล้ว';
        }
    }
}

// --- EDIT USER ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id        = intval($_POST['id']);
    $full_name = trim($_POST['full_name'] ?? '');
    $role      = $_POST['role'] === 'admin' ? 'admin' : 'user';
    $is_active = isset($_POST['is_active']) ? 1 : 0;

    // Prevent deactivating yourself
    if ($id === intval($_SESSION['user_id']) && !$is_active) {
        $error = 'ไม่สามารถปิดใช้งานบัญชีของตัวเองได้';
    } else {
        $stmt = $pdo->prepare("UPDATE users SET full_name=?, role=?, is_active=? WHERE id=?");
        $stmt->execute([$full_name, $role, $is_active, $id]);
        $msg = 'อัปเดตข้อมูลผู้ใช้งานสำเร็จ';
    }
}

// --- RESET PASSWORD ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'reset_password') {
    $id          = intval($_POST['id']);
    $new_password = trim($_POST['new_password'] ?? '');
    if (strlen($new_password) < 6) {
        $error = 'รหัสผ่านต้องมีความยาวอย่างน้อย 6 ตัวอักษร';
    } else {
        $hash = password_hash($new_password, PASSWORD_BCRYPT);
        $stmt = $pdo->prepare("UPDATE users SET password=? WHERE id=?");
        $stmt->execute([$hash, $id]);
        $msg = 'รีเซ็ตรหัสผ่านสำเร็จ';
    }
}

// --- DELETE USER ---
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    if ($del_id === intval($_SESSION['user_id'])) {
        $error = 'ไม่สามารถลบบัญชีของตัวเองได้';
    } else {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$del_id]);
        header('Location: users.php?msg=deleted');
        exit;
    }
}

$users = $pdo->query("SELECT * FROM users ORDER BY role ASC, id ASC")->fetchAll();

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-people-fill"></i> จัดการผู้ใช้งาน</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createModal">
        <i class="bi bi-person-plus"></i> เพิ่มผู้ใช้งาน
    </button>
</div>

<?php if ($msg): ?>
<script>document.addEventListener('DOMContentLoaded', () => swalSuccess(<?= json_encode($msg) ?>));</script>
<?php endif; ?>
<?php if (!empty($_GET['msg']) && $_GET['msg'] === 'deleted'): ?>
<script>document.addEventListener('DOMContentLoaded', () => swalSuccess('ลบผู้ใช้งานสำเร็จ'));</script>
<?php endif; ?>
<?php if ($error): ?>
<script>document.addEventListener('DOMContentLoaded', () => swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3">#</th>
                        <th>ชื่อผู้ใช้งาน</th>
                        <th>ชื่อ-นามสกุล</th>
                        <th class="text-center">สิทธิ์</th>
                        <th class="text-center">สถานะ</th>
                        <th>สร้างเมื่อ</th>
                        <th class="text-center pe-3">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                <tr class="<?= !$u['is_active'] ? 'opacity-50' : '' ?>">
                    <td class="ps-3 text-muted"><?= $u['id'] ?></td>
                    <td class="fw-bold">
                        <i class="bi bi-person-circle me-1 text-primary"></i>
                        <?= htmlspecialchars($u['username']) ?>
                        <?php if ($u['id'] == $_SESSION['user_id']): ?>
                            <span class="badge bg-info ms-1" style="font-size:.7rem;">คุณ</span>
                        <?php endif; ?>
                    </td>
                    <td><?= htmlspecialchars($u['full_name'] ?: '-') ?></td>
                    <td class="text-center">
                        <?php if ($u['role'] === 'admin'): ?>
                            <span class="badge bg-danger">Admin</span>
                        <?php else: ?>
                            <span class="badge bg-secondary">User</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($u['is_active']): ?>
                            <span class="badge bg-success">ใช้งานอยู่</span>
                        <?php else: ?>
                            <span class="badge bg-dark">ปิดใช้งาน</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($u['created_at'])) ?></td>
                    <td class="text-center pe-3">
                        <button class="btn btn-outline-primary btn-table-action" title="แก้ไข"
                            onclick="openEditModal(<?= htmlspecialchars(json_encode($u)) ?>)">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-warning btn-table-action" title="รีเซ็ตรหัสผ่าน"
                            onclick="openResetModal(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                            <i class="bi bi-key"></i>
                        </button>
                        <?php if ($u['id'] != $_SESSION['user_id']): ?>
                        <button class="btn btn-outline-danger btn-table-action" title="ลบ"
                            onclick="confirmDeleteUser(<?= $u['id'] ?>, '<?= htmlspecialchars(addslashes($u['username'])) ?>')">
                            <i class="bi bi-trash"></i>
                        </button>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- ======= CREATE MODAL ======= -->
<div class="modal fade" id="createModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="create">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-person-plus me-2"></i>เพิ่มผู้ใช้งานใหม่</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">ชื่อผู้ใช้งาน (Username) <span class="text-danger">*</span></label>
                    <input type="text" class="form-control form-control-sm" name="username" required placeholder="เช่น john, user01">
                </div>
                <div class="mb-3">
                    <label class="form-label small">ชื่อ-นามสกุล</label>
                    <input type="text" class="form-control form-control-sm" name="full_name" placeholder="เช่น สมชาย ใจดี">
                </div>
                <div class="mb-3">
                    <label class="form-label small">สิทธิ์การใช้งาน</label>
                    <select class="form-select form-select-sm" name="role">
                        <option value="user">User (ทั่วไป)</option>
                        <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label small">รหัสผ่าน <span class="text-danger">*</span></label>
                    <input type="password" class="form-control form-control-sm" name="password" required placeholder="อย่างน้อย 6 ตัวอักษร" minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-circle me-1"></i>สร้างผู้ใช้งาน</button>
            </div>
        </form>
    </div>
</div>

<!-- ======= EDIT MODAL ======= -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="id" id="editId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-pencil me-2"></i>แก้ไขผู้ใช้งาน: <span id="editUsernameLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">ชื่อ-นามสกุล</label>
                    <input type="text" class="form-control form-control-sm" name="full_name" id="editFullName">
                </div>
                <div class="mb-3">
                    <label class="form-label small">สิทธิ์การใช้งาน</label>
                    <select class="form-select form-select-sm" name="role" id="editRole">
                        <option value="user">User (ทั่วไป)</option>
                        <option value="admin">Admin (ผู้ดูแลระบบ)</option>
                    </select>
                </div>
                <div class="mb-3 form-check">
                    <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive" value="1">
                    <label class="form-check-label small" for="editIsActive">เปิดใช้งาน</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-save me-1"></i>บันทึก</button>
            </div>
        </form>
    </div>
</div>

<!-- ======= RESET PASSWORD MODAL ======= -->
<div class="modal fade" id="resetModal" tabindex="-1">
    <div class="modal-dialog">
        <form class="modal-content" method="POST">
            <input type="hidden" name="action" value="reset_password">
            <input type="hidden" name="id" id="resetId">
            <div class="modal-header">
                <h5 class="modal-title"><i class="bi bi-key me-2"></i>รีเซ็ตรหัสผ่าน: <span id="resetUsernameLabel"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label small">รหัสผ่านใหม่ <span class="text-danger">*</span></label>
                    <input type="password" class="form-control form-control-sm" name="new_password" id="newPassword" required placeholder="อย่างน้อย 6 ตัวอักษร" minlength="6">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">ยกเลิก</button>
                <button type="submit" class="btn btn-warning btn-sm text-dark"><i class="bi bi-key me-1"></i>รีเซ็ตรหัสผ่าน</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(user) {
    document.getElementById('editId').value = user.id;
    document.getElementById('editUsernameLabel').textContent = user.username;
    document.getElementById('editFullName').value = user.full_name || '';
    document.getElementById('editRole').value = user.role;
    document.getElementById('editIsActive').checked = user.is_active == 1;
    new bootstrap.Modal(document.getElementById('editModal')).show();
}

function openResetModal(id, username) {
    document.getElementById('resetId').value = id;
    document.getElementById('resetUsernameLabel').textContent = username;
    document.getElementById('newPassword').value = '';
    new bootstrap.Modal(document.getElementById('resetModal')).show();
}

function confirmDeleteUser(id, username) {
    swalConfirm('ยืนยันการลบ', `ต้องการลบผู้ใช้งาน "${username}" ใช่หรือไม่?`, 'ใช่, ลบเลย').then(r => {
        if (r.isConfirmed) window.location.href = `users.php?delete=${id}`;
    });
}
</script>

<?php include_once 'includes/footer.php'; ?>
