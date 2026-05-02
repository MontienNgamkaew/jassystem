<?php
require_once 'db.php';

$id = $_GET['id'] ?? null;
$package = ['code' => '', 'name' => '', 'description' => ''];
$package_items = [];

// Fetch existing data if editing
if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM packages WHERE id = ?");
    $stmt->execute([$id]);
    $package = $stmt->fetch();
    
    $stmt = $pdo->prepare("
        SELECT pi.*, p.name as product_name, p.unit, p.code as product_code 
        FROM package_items pi 
        JOIN products p ON pi.product_id = p.id 
        WHERE pi.package_id = ?
    ");
    $stmt->execute([$id]);
    $package_items = $stmt->fetchAll();
}

// Fetch all products for the dropdown
$products = $pdo->query("SELECT * FROM products ORDER BY name ASC")->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $code = $_POST['code'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $product_ids = $_POST['product_id'] ?? [];
    $quantities = $_POST['quantity'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        if ($id) {
            // Update Package
            $stmt = $pdo->prepare("UPDATE packages SET code=?, name=?, description=? WHERE id=?");
            $stmt->execute([$code, $name, $description, $id]);
            
            // Delete old items
            $stmt = $pdo->prepare("DELETE FROM package_items WHERE package_id=?");
            $stmt->execute([$id]);
            $package_id = $id;
        } else {
            // Insert Package
            $stmt = $pdo->prepare("INSERT INTO packages (code, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$code, $name, $description]);
            $package_id = $pdo->lastInsertId();
        }
        
        // Insert items
        if (!empty($product_ids)) {
            $stmt = $pdo->prepare("INSERT INTO package_items (package_id, product_id, quantity) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($product_ids); $i++) {
                if (!empty($product_ids[$i]) && !empty($quantities[$i])) {
                    $stmt->execute([$package_id, $product_ids[$i], $quantities[$i]]);
                }
            }
        }
        
        $pdo->commit();
        header("Location: packages.php?msg=saved");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาด: รหัสแพ็กเกจอาจซ้ำ หรือข้อมูลไม่ถูกต้อง";
    }
}

include_once 'includes/header.php';
?>

<div class="page-header">
    <h2 class="page-title">
        <i class="bi <?= $id ? 'bi-pencil' : 'bi-plus-circle' ?>"></i> 
        <?= $id ? 'แก้ไขแพ็กเกจ' : 'สร้างแพ็กเกจใหม่' ?>
    </h2>
    <a href="packages.php" class="btn btn-secondary">
        <i class="bi bi-arrow-left"></i> กลับ
    </a>
</div>

<?php if (isset($error)): ?>
    <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
<?php endif; ?>

<form method="POST" id="packageForm">
    <div class="row">
        <!-- Master Data -->
        <div class="col-md-4 mb-4">
            <div class="card card-custom h-100">
                <div class="card-header card-header-custom">ข้อมูลแพ็กเกจ</div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">รหัสแพ็กเกจ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="code" value="<?= htmlspecialchars($package['code']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">ชื่อแพ็กเกจ <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name" value="<?= htmlspecialchars($package['name']) ?>" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">รายละเอียด</label>
                        <textarea class="form-control" name="description" rows="4"><?= htmlspecialchars($package['description']) ?></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100 mt-3">
                        <i class="bi bi-save"></i> บันทึกแพ็กเกจ
                    </button>
                </div>
            </div>
        </div>
        
        <!-- Detail Data (Items) -->
        <div class="col-md-8 mb-4">
            <div class="card card-custom h-100">
                <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                    <span>รายการสินค้าในแพ็กเกจ</span>
                    <button type="button" class="btn btn-sm btn-success" onclick="addItemRow()">
                        <i class="bi bi-plus"></i> เพิ่มสินค้า
                    </button>
                </div>
                <div class="card-body p-0">
                    <table class="table table-custom mb-0" id="itemsTable">
                        <thead>
                            <tr>
                                <th class="ps-4" width="60%">เลือกสินค้า</th>
                                <th width="25%">จำนวน</th>
                                <th class="text-center pe-4" width="15%">ลบ</th>
                            </tr>
                        </thead>
                        <tbody id="itemsBody">
                            <?php foreach ($package_items as $item): ?>
                            <tr>
                                <td class="ps-4">
                                    <div class="input-group">
                                        <select class="form-select product-select" name="product_id[]" required>
                                            <option value="">-- เลือกสินค้า --</option>
                                            <?php foreach ($products as $p): ?>
                                                <option value="<?= $p['id'] ?>" <?= $p['id'] == $item['product_id'] ? 'selected' : '' ?>>
                                                    [<?= htmlspecialchars($p['code']) ?>] <?= htmlspecialchars($p['name']) ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <button class="btn btn-outline-success" type="button" onclick="openQuickAddProduct(this)" title="เพิ่มสินค้าใหม่">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <input type="number" step="0.01" class="form-control" name="quantity[]" value="<?= $item['quantity'] ?>" required>
                                </td>
                                <td class="text-center pe-4">
                                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItemRow(this)">
                                        <i class="bi bi-x"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php if (empty($package_items)): ?>
                        <div id="emptyMessage" class="text-center p-4 text-muted">คลิก "เพิ่มสินค้า" เพื่อจัดแพ็กเกจ</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</form>

<!-- Quick Add Product Modal -->
<div class="modal fade" id="quickAddProductModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog">
    <form class="modal-content" onsubmit="submitQuickAddProduct(event)">
      <div class="modal-header">
        <h5 class="modal-title">เพิ่มสินค้าใหม่แบบด่วน</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
            <label class="form-label">รหัสสินค้า <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="qaCode" required>
        </div>
        <div class="mb-3">
            <label class="form-label">ชื่อสินค้า <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="qaName" required>
        </div>
        <div class="mb-3">
            <label class="form-label">หน่วยนับ <span class="text-danger">*</span></label>
            <input type="text" class="form-control" id="qaUnit" required>
        </div>
        <div class="mb-3">
            <label class="form-label">ราคาต่อหน่วย <span class="text-danger">*</span></label>
            <input type="number" step="0.01" class="form-control" id="qaPrice" required>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">ยกเลิก</button>
        <button type="submit" class="btn btn-primary">บันทึกสินค้า</button>
      </div>
    </form>
  </div>
</div>

<script>
// PHP to JS mapping for dynamic rows
const products = <?= json_encode($products) ?>;

function addItemRow() {
    document.getElementById('emptyMessage')?.remove();
    
    let options = '<option value="">-- เลือกสินค้า --</option>';
    products.forEach(p => {
        options += `<option value="${p.id}">[${p.code}] ${p.name}</option>`;
    });

    const tbody = document.getElementById('itemsBody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="ps-4">
            <div class="input-group">
                <select class="form-select product-select" name="product_id[]" required>
                    ${options}
                </select>
                <button class="btn btn-outline-success" type="button" onclick="openQuickAddProduct(this)" title="เพิ่มสินค้าใหม่">
                    <i class="bi bi-plus-lg"></i>
                </button>
            </div>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control" name="quantity[]" value="1" required>
        </td>
        <td class="text-center pe-4">
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="removeItemRow(this)">
                <i class="bi bi-x"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
}

function removeItemRow(btn) {
    btn.closest('tr').remove();
}

let targetSelectBox = null;
let quickAddModal = null;

function openQuickAddProduct(btn) {
    // Store reference to the select box next to the button
    targetSelectBox = btn.previousElementSibling;
    
    // Clear form
    document.getElementById('qaCode').value = '';
    document.getElementById('qaName').value = '';
    document.getElementById('qaUnit').value = '';
    document.getElementById('qaPrice').value = '';
    
    // Show Modal
    if (!quickAddModal) {
        quickAddModal = new bootstrap.Modal(document.getElementById('quickAddProductModal'));
    }
    quickAddModal.show();
}

async function submitQuickAddProduct(e) {
    e.preventDefault();
    
    const code = document.getElementById('qaCode').value;
    const name = document.getElementById('qaName').value;
    const unit = document.getElementById('qaUnit').value;
    const price = document.getElementById('qaPrice').value;
    
    const formData = new FormData();
    formData.append('code', code);
    formData.append('name', name);
    formData.append('unit', unit);
    formData.append('unit_price', price);
    
    try {
        const response = await fetch('ajax_add_product.php', {
            method: 'POST',
            body: formData
        });
        
        const data = await response.json();
        
        if (data.success) {
            // Add new product to global array
            products.push(data.product);
            
            // Create new Option
            const optionText = `[${data.product.code}] ${data.product.name}`;
            const optionValue = data.product.id;
            
            // Update all select boxes in the table
            const allSelects = document.querySelectorAll('.product-select');
            allSelects.forEach(select => {
                const opt = document.createElement('option');
                opt.value = optionValue;
                opt.text = optionText;
                select.appendChild(opt);
            });
            
            // Auto-select in the target select box
            if (targetSelectBox) {
                targetSelectBox.value = optionValue;
            }
            
            quickAddModal.hide();
        } else {
            alert(data.message);
        }
    } catch (err) {
        alert("เกิดข้อผิดพลาดในการเชื่อมต่อเซิร์ฟเวอร์");
    }
}
</script>

<?php include_once 'includes/footer.php'; ?>
