<?php
require_once 'db.php';

// Handle setting default company
if (isset($_GET['set_default']) && is_numeric($_GET['set_default'])) {
    $id = $_GET['set_default'];
    $pdo->exec("UPDATE companies SET is_default = 0");
    $stmt = $pdo->prepare("UPDATE companies SET is_default = 1 WHERE id = ?");
    if ($stmt->execute([$id])) {
        header("Location: companies.php?msg=default_set");
        exit;
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    // Check if it's the last company
    $count = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
    if ($count <= 1) {
        $error = "ไม่สามารถลบบริษัทนี้ได้ ต้องมีบริษัทอย่างน้อย 1 บริษัทในระบบ";
    } else {
        // Check if documents use it
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE company_id = ?");
        $stmt->execute([$id]);
        if ($stmt->fetchColumn() > 0) {
            $error = "ไม่สามารถลบบริษัทได้ เนื่องจากมีเอกสารที่อ้างอิงถึงบริษัทนี้";
        } else {
            // Check if it's default
            $stmt = $pdo->prepare("SELECT is_default FROM companies WHERE id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() == 1) {
                $error = "ไม่สามารถลบบริษัทที่เป็นค่าเริ่มต้นได้ กรุณาตั้งบริษัทอื่นเป็นค่าเริ่มต้นก่อน";
            } else {
                $stmt = $pdo->prepare("DELETE FROM companies WHERE id = ?");
                if ($stmt->execute([$id])) {
                    header("Location: companies.php?msg=deleted");
                    exit;
                } else {
                    $error = "เกิดข้อผิดพลาดในการลบข้อมูล";
                }
            }
        }
    }
}

// Handle sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'is_default';
$order = isset($_GET['order']) && $_GET['order'] === 'desc' ? 'desc' : 'asc';
$allowed_sort = ['name_th', 'phone', 'tax_id', 'is_default'];
if (!in_array($sort, $allowed_sort)) $sort = 'is_default';

$order_sql = $order === 'desc' ? 'DESC' : 'ASC';
// If sorting by is_default, we want defaults first, which is DESC. But the user clicked 'asc' or 'desc'. 
// So we just use the selected order.

// Fetch companies
$companies = $pdo->query("SELECT * FROM companies ORDER BY {$sort} {$order_sql}, name_th ASC")->fetchAll();

function sortLinkComp($column, $label, $current_sort, $current_order, $extra_class = '') {
    $icon = '';
    if ($current_sort === $column) {
        $icon = $current_order === 'asc' ? '<i class="bi bi-caret-up-fill ms-1"></i>' : '<i class="bi bi-caret-down-fill ms-1"></i>';
    }
    $next_order = ($current_sort === $column && $current_order === 'asc') ? 'desc' : 'asc';
    $class = "text-decoration-none text-dark d-flex align-items-center " . $extra_class;
    return "<a href=\"?sort={$column}&order={$next_order}\" class=\"{$class}\">{$label}{$icon}</a>";
}

include_once 'includes/header.php';
?>

<div class="page-header">
    <a href="company_form.php" class="btn btn-primary">
        <i class="bi bi-plus-circle"></i> เพิ่มบริษัทใหม่
    </a>
</div>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
<?php if ($_GET['msg'] == 'default_set'): ?>
    swalSuccess('เปลี่ยนบริษัทเริ่มต้นสำเร็จ');
<?php elseif ($_GET['msg'] == 'deleted'): ?>
    swalSuccess('ลบข้อมูลบริษัทสำเร็จ');
<?php else: ?>
    swalSuccess('ทำรายการสำเร็จ');
<?php endif; ?>
});</script>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3" width="80">โลโก้</th>
                        <th><?= sortLinkComp('name_th', 'ชื่อบริษัท', $sort, $order) ?></th>
                        <th><?= sortLinkComp('phone', 'เบอร์โทร', $sort, $order) ?></th>
                        <th><?= sortLinkComp('tax_id', 'เลขผู้เสียภาษี', $sort, $order) ?></th>
                        <th class="text-center" width="150"><?= sortLinkComp('is_default', 'สถานะ', $sort, $order, 'justify-content-center') ?></th>
                        <th class="text-center pe-3" width="120">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($companies)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">ยังไม่มีข้อมูลบริษัท</td>
                    </tr>
                    <?php else: ?>
                        <?php foreach ($companies as $company): ?>
                        <tr>
                            <td class="ps-3">
                                <?php if (!empty($company['logo_path']) && file_exists(__DIR__ . '/' . $company['logo_path'])): ?>
                                    <img src="<?= htmlspecialchars($company['logo_path']) ?>?t=<?= time() ?>" alt="Logo" style="height:32px; border-radius:4px;">
                                <?php else: ?>
                                    <div class="bg-light text-muted d-flex align-items-center justify-content-center" style="width:32px; height:32px; border-radius:4px;"><i class="bi bi-image"></i></div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($company['name_th']) ?></strong>
                                <?php if($company['name_en']): ?>
                                    <br><small class="text-muted"><?= htmlspecialchars($company['name_en']) ?></small>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($company['phone'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($company['tax_id'] ?: '-') ?></td>
                            <td class="text-center">
                                <?php if ($company['is_default']): ?>
                                    <span class="badge bg-success">เริ่มต้น</span>
                                <?php else: ?>
                                    <a href="companies.php?set_default=<?= $company['id'] ?>" class="btn btn-outline-secondary btn-table-action" title="ตั้งเป็นค่าเริ่มต้น"><i class="bi bi-check2-circle"></i></a>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-3">
                                <a href="company_form.php?id=<?= $company['id'] ?>" class="btn btn-outline-primary btn-table-action" title="แก้ไข">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger btn-table-action" title="ลบ" onclick="confirmDelete(<?= $company['id'] ?>, '<?= htmlspecialchars(addslashes($company['name_th'])) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function confirmDelete(id, name) {
    swalConfirm('ยืนยันการลบ', `คุณต้องการลบบริษัท "${name}" ใช่หรือไม่?`, 'ใช่, ลบเลย').then((result) => {
        if (result.isConfirmed) {
            window.location.href = `companies.php?delete=${id}`;
        }
    });
}
</script>

<?php include_once 'includes/footer.php'; ?>
