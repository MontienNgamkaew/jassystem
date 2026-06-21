<?php
require_once 'db.php';

// Handle Delete Document
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    // Verify CSRF Token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF Token");
    }

    $id = $_POST['delete_id'] ?? '';
    try {
        $pdo->beginTransaction();
        // Delete items first
        $stmt = $pdo->prepare("DELETE FROM document_items WHERE document_id = ?");
        $stmt->execute([$id]);
        // Delete document
        $stmt = $pdo->prepare("DELETE FROM documents WHERE id = ?");
        $stmt->execute([$id]);
        $pdo->commit();
        header("Location: documents.php?msg=deleted");
        exit;
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการลบเอกสาร";
    }
}

// Sorting
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';
$sort_map = [
    'doc_no'        => 'd.doc_no',
    'type'          => 'd.type',
    'date'          => 'd.date',
    'company_name'  => 'comp.name_th',
    'customer_name' => 'c.name',
    'total_amount'  => 'd.total_amount',
    'created_at'    => 'd.created_at'
];
$sort_col = $sort_map[$sort] ?? 'd.created_at';
$order_sql = $order === 'asc' ? 'ASC' : 'DESC';

// Search & filter
$search      = trim($_GET['search'] ?? '');
$type_filter = in_array($_GET['type_filter'] ?? '', ['Quote', 'Invoice', 'Receipt']) ? $_GET['type_filter'] : '';

$where_parts = [];
$params      = [];
if ($search !== '') {
    $where_parts[] = "(d.doc_no LIKE ? OR c.name LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($type_filter !== '') {
    $where_parts[] = "d.type = ?";
    $params[] = $type_filter;
}
$where_sql = $where_parts ? 'WHERE ' . implode(' AND ', $where_parts) : '';

// Pagination
$per_page    = 20;
$page        = max(1, (int)($_GET['page'] ?? 1));

$count_stmt = $pdo->prepare("SELECT COUNT(*) FROM documents d LEFT JOIN customers c ON d.customer_id = c.id LEFT JOIN companies comp ON d.company_id = comp.id $where_sql");
$count_stmt->execute($params);
$total_count = $count_stmt->fetchColumn();
$total_pages = max(1, (int)ceil($total_count / $per_page));
$page        = min($page, $total_pages);
$offset      = ($page - 1) * $per_page;

$sql = "SELECT d.*, c.name as customer_name, comp.name_th as company_name
        FROM documents d
        LEFT JOIN customers c ON d.customer_id = c.id
        LEFT JOIN companies comp ON d.company_id = comp.id
        $where_sql
        ORDER BY {$sort_col} {$order_sql}, d.id DESC
        LIMIT ? OFFSET ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([...$params, $per_page, $offset]);
$documents = $stmt->fetchAll();

$type_labels = [
    'Quote'   => ['label' => 'ใบเสนอราคา',    'class' => 'bg-primary'],
    'Invoice' => ['label' => 'ใบแจ้งหนี้',     'class' => 'bg-warning text-dark'],
    'Receipt' => ['label' => 'ใบเสร็จรับเงิน', 'class' => 'bg-success'],
];

function sortLinkDoc($column, $label, $current_sort, $current_order, $search, $type_filter, $extra_class = '') {
    $icon = '';
    if ($current_sort === $column) {
        $icon = $current_order === 'asc' ? '<i class="bi bi-caret-up-fill ms-1"></i>' : '<i class="bi bi-caret-down-fill ms-1"></i>';
    }
    $next_order = ($current_sort === $column && $current_order === 'asc') ? 'desc' : 'asc';
    $qs = http_build_query(['sort' => $column, 'order' => $next_order, 'search' => $search, 'type_filter' => $type_filter]);
    $class = "text-decoration-none text-dark d-flex align-items-center " . $extra_class;
    return "<a href=\"?{$qs}\" class=\"{$class}\">{$label}{$icon}</a>";
}

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-archive"></i> ประวัติเอกสารทั้งหมด</h2>
    <a href="create_document.php" class="btn btn-primary">
        <i class="bi bi-file-earmark-plus"></i> ออกเอกสารใหม่
    </a>
</div>

<?php if (isset($_GET['msg'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>{
<?php if ($_GET['msg'] === 'created'): ?>
    swalSuccess('บันทึกเอกสารสำเร็จแล้ว! กดปุ่ม PDF เพื่อดาวน์โหลดได้เลยครับ', 3000);
<?php elseif ($_GET['msg'] === 'deleted'): ?>
    swalSuccess('ลบเอกสารสำเร็จ');
<?php endif; ?>
});</script>
<?php endif; ?>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<!-- Search & Filter -->
<form method="GET" class="card card-custom mb-3 p-3">
    <div class="row g-2 align-items-end">
        <div class="col-md-5">
            <label class="form-label small mb-1">ค้นหา</label>
            <input type="text" class="form-control form-control-sm" name="search" value="<?= htmlspecialchars($search) ?>" placeholder="เลขเอกสาร หรือ ชื่อลูกค้า">
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1">ประเภทเอกสาร</label>
            <select class="form-select form-select-sm" name="type_filter">
                <option value="">-- ทั้งหมด --</option>
                <option value="Quote"   <?= $type_filter === 'Quote'   ? 'selected' : '' ?>>ใบเสนอราคา</option>
                <option value="Invoice" <?= $type_filter === 'Invoice' ? 'selected' : '' ?>>ใบแจ้งหนี้</option>
                <option value="Receipt" <?= $type_filter === 'Receipt' ? 'selected' : '' ?>>ใบเสร็จรับเงิน</option>
            </select>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-sm btn-primary w-100"><i class="bi bi-search me-1"></i> ค้นหา</button>
        </div>
        <?php if ($search !== '' || $type_filter !== ''): ?>
        <div class="col-md-2">
            <a href="documents.php" class="btn btn-sm btn-outline-secondary w-100">ล้างตัวกรอง</a>
        </div>
        <?php endif; ?>
        <input type="hidden" name="sort" value="<?= htmlspecialchars($sort) ?>">
        <input type="hidden" name="order" value="<?= htmlspecialchars($order) ?>">
    </div>
</form>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-3"><?= sortLinkDoc('doc_no', 'เลขที่เอกสาร', $sort, $order, $search, $type_filter) ?></th>
                        <th><?= sortLinkDoc('type', 'ประเภท', $sort, $order, $search, $type_filter) ?></th>
                        <th><?= sortLinkDoc('date', 'วันที่', $sort, $order, $search, $type_filter) ?></th>
                        <th><?= sortLinkDoc('company_name', 'ออกในนามบริษัท', $sort, $order, $search, $type_filter) ?></th>
                        <th><?= sortLinkDoc('customer_name', 'ลูกค้า', $sort, $order, $search, $type_filter) ?></th>
                        <th class="text-end"><?= sortLinkDoc('total_amount', 'ยอดรวม (บาท)', $sort, $order, $search, $type_filter, 'justify-content-end') ?></th>
                        <th class="text-center" width="80">VAT</th>
                        <th class="text-center pe-3" width="120">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($documents) > 0): ?>
                        <?php foreach ($documents as $d): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($d['doc_no']) ?></td>
                            <td>
                                <?php $t = $type_labels[$d['type']] ?? ['label'=>$d['type'],'class'=>'bg-secondary']; ?>
                                <span class="badge <?= $t['class'] ?>"><?= $t['label'] ?></span>
                            </td>
                            <td><?= date('d/m/Y', strtotime($d['date'])) ?></td>
                            <td><?= htmlspecialchars($d['company_name'] ?? 'บริษัทหลัก') ?></td>
                            <td><?= htmlspecialchars($d['customer_name'] ?? '-') ?></td>
                            <td class="text-end fw-bold"><?= number_format($d['total_amount'], 2) ?></td>
                            <td class="text-center">
                                <?php if ($d['include_vat']): ?>
                                    <span class="badge bg-success">รวม VAT</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-center pe-3">
                                <a href="view_document.php?id=<?= $d['id'] ?>" class="btn btn-outline-primary btn-table-action" title="ดูเอกสาร">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="edit_document.php?id=<?= $d['id'] ?>" class="btn btn-outline-warning btn-table-action" title="แก้ไขเอกสาร">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <a href="generate_pdf.php?id=<?= $d['id'] ?>" target="_blank" class="btn btn-outline-danger btn-table-action" title="พิมพ์ PDF">
                                    <i class="bi bi-file-earmark-pdf"></i>
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-table-action" title="ลบเอกสาร" onclick="confirmDelete(<?= $d['id'] ?>, '<?= htmlspecialchars($d['doc_no']) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-5 text-muted">
                                <i class="bi bi-inbox" style="font-size:2rem;"></i><br>
                                <?= $search || $type_filter ? 'ไม่พบเอกสารที่ตรงกับการค้นหา' : 'ยังไม่มีเอกสาร' ?>
                                <?php if (!$search && !$type_filter): ?>
                                <a href="create_document.php" class="ms-2 btn btn-sm btn-primary">ออกเอกสารใหม่</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php if ($total_pages > 1): ?>
<div class="d-flex justify-content-between align-items-center px-3 py-2 border-top">
    <small class="text-muted">แสดง <?= number_format(($page-1)*$per_page+1) ?>–<?= number_format(min($page*$per_page,$total_count)) ?> จาก <?= number_format($total_count) ?> รายการ</small>
    <nav>
        <ul class="pagination pagination-sm mb-0">
            <?php
            $base_qs = http_build_query(['sort'=>$sort,'order'=>$order,'search'=>$search,'type_filter'=>$type_filter]);
            for ($p = 1; $p <= $total_pages; $p++):
                $active = $p === $page ? 'active' : '';
            ?>
            <li class="page-item <?= $active ?>">
                <a class="page-link" href="?<?= $base_qs ?>&page=<?= $p ?>"><?= $p ?></a>
            </li>
            <?php endfor; ?>
        </ul>
    </nav>
</div>
<?php endif; ?>

<script>
function confirmDelete(id, doc_no) {
    swalConfirm('ยืนยันการลบ', `คุณต้องการลบเอกสาร "${doc_no}" ใช่หรือไม่?\n(ข้อมูลจะไม่สามารถกู้คืนได้)`, 'ใช่, ลบเลย').then((result) => {
        if (result.isConfirmed) {
            submitPostDelete('documents.php', id);
        }
    });
}
</script>

<?php include_once 'includes/footer.php'; ?>
