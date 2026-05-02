<?php
require_once 'db.php';

// Handle Delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM customers WHERE id = ?");
        $stmt->execute([$id]);
        header("Location: customers.php?msg=deleted");
        exit();
    } catch (\PDOException $e) {
        $error = "ไม่สามารถลบข้อมูลได้ อาจมีการออกเอกสารในชื่อลูกค้านี้อยู่";
    }
}

// Handle Form Submit (Add / Edit)
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id = $_POST['id'] ?? '';
    $name = $_POST['name'];
    $tax_id = $_POST['tax_id'];
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'];

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE customers SET name=?, tax_id=?, address=?, phone=? WHERE id=?");
        $stmt->execute([$name, $tax_id, $address, $phone, $id]);
        header("Location: customers.php?msg=updated");
        exit();
    } else {
        // Insert
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, tax_id, address, phone) VALUES (?, ?, ?, ?)");
            $stmt->execute([$name, $tax_id, $address, $phone]);
            header("Location: customers.php?msg=added");
            exit();
        } catch (\PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Fetch Customers
$allowed_sort_cols = ['name', 'phone', 'tax_id'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowed_sort_cols) ? $_GET['sort'] : 'id';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'ASC' : 'DESC';

$customers = $pdo->query("SELECT * FROM customers ORDER BY $sort $order")->fetchAll();

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-people"></i> จัดการลูกค้า</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> เพิ่มลูกค้าใหม่
    </button>
</div>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalSuccess('ทำรายการสำเร็จ'));</script>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <?php 
                    function sortLinkCust($colName, $label, $currentSort, $currentOrder, $alignClass = '') {
                        $newOrder = ($currentSort === $colName && $currentOrder === 'ASC') ? 'desc' : 'asc';
                        $icon = '<i class="bi bi-arrow-down-up text-muted opacity-25 ms-1"></i>';
                        if ($currentSort === $colName) {
                            $icon = $currentOrder === 'ASC' ? '<i class="bi bi-caret-up-fill ms-1 text-primary"></i>' : '<i class="bi bi-caret-down-fill ms-1 text-primary"></i>';
                        }
                        return "<a href='customers.php?sort=$colName&order=$newOrder' class='text-decoration-none text-dark d-flex align-items-center $alignClass'>$label $icon</a>";
                    }
                    ?>
                    <tr>
                        <th class="ps-3" width="30%"><?= sortLinkCust('name', 'ชื่อลูกค้า/บริษัท', $sort, $order) ?></th>
                        <th width="15%">เลขผู้เสียภาษี</th>
                        <th width="35%">ที่อยู่</th>
                        <th width="10%">เบอร์โทร</th>
                        <th class="text-center pe-3" width="10%">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $c): ?>
                        <tr>
                            <td class="ps-3 fw-bold text-primary"><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['tax_id'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($c['address'] ?: '-') ?></td>
                            <td><?= htmlspecialchars($c['phone'] ?: '-') ?></td>
                            <td class="text-center pe-3">
                                <button class="btn btn-outline-primary btn-table-action" title="แก้ไข"
                                        onclick='editCustomer(<?= json_encode($c) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button type="button" class="btn btn-outline-danger btn-table-action" title="ลบ"
                                   onclick="confirmDelete(<?= $c['id'] ?>, '<?= htmlspecialchars(addslashes($c['name'])) ?>')">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-muted">ยังไม่มีข้อมูลลูกค้า</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Modal -->
<div class="modal fade" id="customerModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" method="POST" action="customers.php">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">เพิ่มลูกค้าใหม่</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" name="id" id="customerId">
        <div class="mb-3">
            <label class="form-label">ชื่อลูกค้า / บริษัท <span class="text-danger">*</span></label>
            <input type="text" class="form-control" name="name" id="customerName" required>
        </div>
        <div class="mb-3">
            <label class="form-label">เลขประจำตัวผู้เสียภาษี (Tax ID)</label>
            <input type="text" class="form-control" name="tax_id" id="customerTaxId">
        </div>
        <div class="mb-3">
            <label class="form-label">เบอร์โทรศัพท์</label>
            <input type="text" class="form-control" name="phone" id="customerPhone">
        </div>
        <div class="mb-3">
            <label class="form-label">ที่อยู่</label>
            <textarea class="form-control" name="address" id="customerAddress" rows="3"></textarea>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">บันทึกข้อมูล</button>
      </div>
    </form>
  </div>
</div>

<script>
function confirmDelete(id, name) {
    swalConfirm('ยืนยันการลบ', `คุณต้องการลบลูกค้า "${name}" ใช่หรือไม่?`, 'ใช่, ลบเลย').then((result) => {
        if (result.isConfirmed) {
            window.location.href = `customers.php?delete=${id}`;
        }
    });
}

function clearForm() {
    document.getElementById('modalTitle').innerText = 'เพิ่มลูกค้าใหม่';
    document.getElementById('customerId').value = '';
    document.getElementById('customerName').value = '';
    document.getElementById('customerTaxId').value = '';
    document.getElementById('customerPhone').value = '';
    document.getElementById('customerAddress').value = '';
}

function editCustomer(customer) {
    document.getElementById('modalTitle').innerText = 'แก้ไขลูกค้า';
    document.getElementById('customerId').value = customer.id;
    document.getElementById('customerName').value = customer.name;
    document.getElementById('customerTaxId').value = customer.tax_id;
    document.getElementById('customerPhone').value = customer.phone || '';
    document.getElementById('customerAddress').value = customer.address;
    
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}
</script>

<?php include_once 'includes/footer.php'; ?>
