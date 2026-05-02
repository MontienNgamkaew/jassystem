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
    $address = $_POST['address'];

    if ($id) {
        // Update
        $stmt = $pdo->prepare("UPDATE customers SET name=?, tax_id=?, address=? WHERE id=?");
        $stmt->execute([$name, $tax_id, $address, $id]);
        header("Location: customers.php?msg=updated");
        exit();
    } else {
        // Insert
        try {
            $stmt = $pdo->prepare("INSERT INTO customers (name, tax_id, address) VALUES (?, ?, ?)");
            $stmt->execute([$name, $tax_id, $address]);
            header("Location: customers.php?msg=added");
            exit();
        } catch (\PDOException $e) {
            $error = "เกิดข้อผิดพลาด: " . $e->getMessage();
        }
    }
}

// Fetch Customers
$customers = $pdo->query("SELECT * FROM customers ORDER BY id DESC")->fetchAll();

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-people"></i> จัดการลูกค้า</h2>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#customerModal" onclick="clearForm()">
        <i class="bi bi-plus-circle"></i> เพิ่มลูกค้าใหม่
    </button>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<?php if (isset($_GET['msg'])): ?>
    <div class="alert alert-success">ทำรายการสำเร็จ</div>
<?php endif; ?>

<div class="card card-custom">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-custom table-hover mb-0">
                <thead>
                    <tr>
                        <th class="ps-4">ชื่อลูกค้า / บริษัท</th>
                        <th>เลขประจำตัวผู้เสียภาษี</th>
                        <th>ที่อยู่</th>
                        <th class="text-center pe-4">จัดการ</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($customers) > 0): ?>
                        <?php foreach ($customers as $c): ?>
                        <tr>
                            <td class="ps-4 fw-bold"><?= htmlspecialchars($c['name']) ?></td>
                            <td><?= htmlspecialchars($c['tax_id']) ?></td>
                            <td><?= htmlspecialchars($c['address']) ?></td>
                            <td class="text-center pe-4">
                                <button class="btn btn-sm btn-outline-primary" 
                                        onclick='editCustomer(<?= json_encode($c) ?>)'>
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <a href="customers.php?delete=<?= $c['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                   onclick="return confirm('คุณต้องการลบลูกค้านี้ใช่หรือไม่?');">
                                    <i class="bi bi-trash"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" class="text-center py-4 text-muted">ยังไม่มีข้อมูลลูกค้า</td>
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
function clearForm() {
    document.getElementById('modalTitle').innerText = 'เพิ่มลูกค้าใหม่';
    document.getElementById('customerId').value = '';
    document.getElementById('customerName').value = '';
    document.getElementById('customerTaxId').value = '';
    document.getElementById('customerAddress').value = '';
}

function editCustomer(customer) {
    document.getElementById('modalTitle').innerText = 'แก้ไขลูกค้า';
    document.getElementById('customerId').value = customer.id;
    document.getElementById('customerName').value = customer.name;
    document.getElementById('customerTaxId').value = customer.tax_id;
    document.getElementById('customerAddress').value = customer.address;
    
    var myModal = new bootstrap.Modal(document.getElementById('customerModal'));
    myModal.show();
}
</script>

<?php include_once 'includes/footer.php'; ?>
