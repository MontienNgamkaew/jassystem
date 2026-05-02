<?php
require_once 'db.php';

// Fetch initial data
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
$packages = $pdo->query("SELECT * FROM packages ORDER BY name ASC")->fetchAll();
$companies = $pdo->query("SELECT id, name_th, is_default FROM companies ORDER BY is_default DESC, name_th ASC")->fetchAll();

// Handle Form Submit (Save Document)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'save') {
    $type = $_POST['type'];
    $date = $_POST['date'];
    $customer_id = $_POST['customer_id'];
    $company_id = $_POST['company_id'] ?? 0;
    
    $items_code = $_POST['item_code'] ?? [];
    $items_raw_name = $_POST['item_raw_name'] ?? [];
    $items_unit = $_POST['item_unit'] ?? [];
    
    $items_name = $_POST['item_name'] ?? [];
    $items_qty = $_POST['item_qty'] ?? [];
    $items_price = $_POST['item_price'] ?? [];
    $items_total = $_POST['item_total'] ?? [];
    $subtotal = floatval($_POST['subtotal'] ?? 0);
    $vat_amount = floatval($_POST['vat_amount'] ?? 0);
    $show_date = isset($_POST['show_date']) ? 1 : 0;
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $include_vat = isset($_POST['include_vat']) ? 1 : 0;
    
    $save_as_package = isset($_POST['save_as_package']) ? true : false;
    $update_master_price = isset($_POST['update_master_price']) ? true : false;
    $new_pkg_code = $_POST['new_pkg_code'] ?? '';
    $new_pkg_name = $_POST['new_pkg_name'] ?? '';
    $new_pkg_desc = $_POST['new_pkg_desc'] ?? '';
    
    // Generate Document Number (Simple format: TYPE-YYYYMM-XXXX)
    $prefix = '';
    if ($type == 'Quote') $prefix = 'QT';
    else if ($type == 'Invoice') $prefix = 'INV';
    else if ($type == 'Receipt') $prefix = 'RE';
    
    $ym = date('Ym', strtotime($date));
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM documents WHERE type = ? AND DATE_FORMAT(date, '%Y%m') = ?");
    $stmt->execute([$type, $ym]);
    $count = $stmt->fetchColumn() + 1;
    $doc_no = sprintf("%s-%s-%04d", $prefix, $ym, $count);
    
    try {
        $pdo->beginTransaction();
        
        // 1. Process Products (Auto-create if not exists)
        $product_ids = []; // Store mapped product IDs for the package creation
        for ($i = 0; $i < count($items_code); $i++) {
            $code = $items_code[$i];
            $rname = $items_raw_name[$i];
            $runit = $items_unit[$i];
            $rprice = $items_price[$i];
            
            $prod_id = null;
            if (!empty($code)) {
                $stmt = $pdo->prepare("SELECT id FROM products WHERE code = ?");
                $stmt->execute([$code]);
                $prod = $stmt->fetch();
                if ($prod) {
                    $prod_id = $prod['id'];
                    // Update master price if requested
                    if ($update_master_price) {
                        $updateStmt = $pdo->prepare("UPDATE products SET unit_price = ? WHERE id = ?");
                        $updateStmt->execute([$rprice, $prod_id]);
                    }
                } else {
                    // Create new product
                    $stmt = $pdo->prepare("INSERT INTO products (code, name, unit, unit_price) VALUES (?, ?, ?, ?)");
                    $stmt->execute([$code, $rname, $runit, $rprice]);
                    $prod_id = $pdo->lastInsertId();
                }
            }
            $product_ids[$i] = $prod_id;
        }
        
        // 2. Process "Save as Package"
        if ($save_as_package && !empty($new_pkg_code) && !empty($new_pkg_name)) {
            // Insert Package
            $stmt = $pdo->prepare("INSERT INTO packages (code, name, description) VALUES (?, ?, ?)");
            $stmt->execute([$new_pkg_code, $new_pkg_name, $new_pkg_desc]);
            $package_id = $pdo->lastInsertId();
            
            // Insert Package Items
            $stmt = $pdo->prepare("INSERT INTO package_items (package_id, product_id, quantity) VALUES (?, ?, ?)");
            for ($i = 0; $i < count($product_ids); $i++) {
                if ($product_ids[$i] !== null && $items_qty[$i] > 0) {
                    // Avoid duplicates in package
                    $checkStmt = $pdo->prepare("SELECT id FROM package_items WHERE package_id = ? AND product_id = ?");
                    $checkStmt->execute([$package_id, $product_ids[$i]]);
                    if (!$checkStmt->fetch()) {
                        $stmt->execute([$package_id, $product_ids[$i], $items_qty[$i]]);
                    }
                }
            }
        }
        
        // 3. Insert Document
        $stmt = $pdo->prepare("INSERT INTO documents (doc_no, type, date, customer_id, company_id, total_amount, include_vat, show_date) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$doc_no, $type, $date, $customer_id, $company_id, $grand_total, $include_vat, $show_date]);
        $document_id = $pdo->lastInsertId();
        
        // 4. Insert Document Items
        $stmt = $pdo->prepare("INSERT INTO document_items (document_id, item_name, quantity, price, total) VALUES (?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($items_name); $i++) {
            if (!empty($items_name[$i]) && $items_qty[$i] > 0) {
                $stmt->execute([
                    $document_id,
                    $items_name[$i],
                    $items_qty[$i],
                    $items_price[$i],
                    $items_total[$i]
                ]);
            }
        }
        
        $pdo->commit();
        header("Location: documents.php?msg=created");
        exit();
    } catch (\PDOException $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
    }
}

include_once 'includes/header.php';
?>

<style>
    .step-section { display: none; }
    .step-section.active { display: block; }
    .review-table th, .review-table td { padding: 12px; }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-file-earmark-plus"></i> ออกเอกสารใหม่</h2>
</div>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<form method="POST" id="documentForm">
    <input type="hidden" name="action" value="save">
    <input type="hidden" name="subtotal" id="inputSubtotal" value="0">
    <input type="hidden" name="vat_amount" id="inputVatAmount" value="0">
    <input type="hidden" name="grand_total" id="inputGrandTotal" value="0">
    
    <!-- STEP 1: Edit Mode -->
    <div id="step-edit" class="step-section active">
        <div class="row">
            <!-- Document Info -->
            <div class="col-md-12 mb-4">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">ข้อมูลเอกสาร</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label small mb-1">ประเภทเอกสาร</label>
                                <select class="form-select form-select-sm" name="type" id="docType" required>
                                    <option value="Quote">ใบเสนอราคา</option>
                                    <option value="Invoice">ใบแจ้งหนี้</option>
                                    <option value="Receipt">ใบเสร็จรับเงิน</option>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small mb-1">ออกในนามบริษัท <span class="text-danger">*</span></label>
                                <select name="company_id" class="form-select form-select-sm" required>
                                    <?php foreach ($companies as $comp): ?>
                                        <option value="<?= $comp['id'] ?>" <?= $comp['is_default'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($comp['name_th']) ?> <?= $comp['is_default'] ? '(เริ่มต้น)' : '' ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small mb-1">วันที่</label>
                                <div class="input-group input-group-sm">
                                    <input type="date" class="form-control" name="date" id="docDate" value="<?= date('Y-m-d') ?>" required>
                                </div>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="noDate" name="show_date" value="1" checked onchange="toggleDateInput()">
                                    <label class="form-check-label text-muted small" for="noDate">แสดงวันที่ในเอกสาร</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small mb-1">เลือกลูกค้า <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" name="customer_id" id="docCustomer" required>
                                    <option value="">-- เลือกลูกค้า --</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>" 
                                            data-name="<?= htmlspecialchars($c['name']) ?>" 
                                            data-phone="<?= htmlspecialchars($c['phone'] ?? '-') ?>"
                                            data-address="<?= htmlspecialchars($c['address']) ?>">
                                            <?= htmlspecialchars($c['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Select Package & Items -->
            <div class="col-md-12 mb-4">
                <div class="card card-custom">
                    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                        <span>รายการสินค้า</span>
                        <div class="d-flex gap-2">
                            <a href="assets/samples/sample_document_items.csv" class="btn btn-sm btn-outline-secondary" download title="ดาวน์โหลดไฟล์ตัวอย่าง CSV">
                                <i class="bi bi-download"></i> โหลดตัวอย่าง CSV
                            </a>
                            <input type="file" id="csvFileInput" accept=".csv" style="display: none;" onchange="uploadCsvToDoc()">
                            <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('csvFileInput').click()">
                                <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
                            </button>
                            
                            <select class="form-select form-select-sm" style="width: 250px;" id="packageSelect" onchange="loadPackageItems()">
                                <option value="">-- ดึงสินค้าจากแพ็กเกจ --</option>
                                <?php foreach ($packages as $p): ?>
                                    <option value="<?= $p['id'] ?>">แพ็กเกจ: <?= htmlspecialchars($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0">
                                <thead>
                                    <tr>
                                        <th class="ps-3" width="40%">รายการ</th>
                                        <th width="15%">จำนวน</th>
                                        <th width="15%">ราคา/หน่วย</th>
                                        <th width="20%" class="text-end pe-3">รวม (บาท)</th>
                                        <th width="10%"></th>
                                    </tr>
                                </thead>
                                <tbody id="itemsTableBody">
                                    <!-- Items will be loaded here via JS -->
                                    <tr id="emptyRow">
                                        <td colspan="5" class="text-center py-4 text-muted">กรุณาเลือกแพ็กเกจด้านบนเพื่อดึงข้อมูลสินค้า</td>
                                    </tr>
                                </tbody>
                                <tfoot>
                                    <tr class="bg-light">
                                        <th colspan="3" class="text-end">รวมเงิน:</th>
                                        <th class="text-end pe-3 text-secondary" id="displaySubtotal">0.00</th>
                                        <th></th>
                                    </tr>
                                    <tr class="bg-light" id="vatRow" style="display:none;">
                                        <th colspan="3" class="text-end">ภาษีมูลค่าเพิ่ม 7%:</th>
                                        <th class="text-end pe-3 text-secondary" id="displayVatAmount">0.00</th>
                                        <th></th>
                                    </tr>
                                    <tr class="bg-light">
                                        <th colspan="3" class="text-end">รวมทั้งสิ้น:</th>
                                        <th class="text-end pe-3 fs-5 text-primary" id="displayGrandTotal">0.00</th>
                                        <th></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="includeVat" name="include_vat" onchange="calcGrandTotal()">
                                    <label class="form-check-label fw-bold text-success" for="includeVat">รวม VAT 7% (ภาษีมูลค่าเพิ่ม)</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="updateMasterPrice" name="update_master_price">
                                    <label class="form-check-label fw-bold text-danger" for="updateMasterPrice">อัปเดต "ราคาสินค้า" ในบิลนี้ กลับไปทับราคาในฐานข้อมูลหลักด้วย</label>
                                </div>
                                <div class="form-check form-switch mb-2">
                                    <input class="form-check-input" type="checkbox" id="saveAsPackage" name="save_as_package" onchange="togglePackageForm()">
                                    <label class="form-check-label fw-bold" for="saveAsPackage">บันทึกรายการเหล่านี้เป็น "แพ็กเกจใหม่" สำหรับใช้ในอนาคต</label>
                                </div>
                                <div id="newPackageForm" style="display: none;" class="p-3 bg-light border rounded">
                                    <div class="row">
                                        <div class="col-md-4 mb-2">
                                            <input type="text" class="form-control form-control-sm" name="new_pkg_code" id="newPkgCode" placeholder="รหัสแพ็กเกจใหม่">
                                        </div>
                                        <div class="col-md-8 mb-2">
                                            <input type="text" class="form-control form-control-sm" name="new_pkg_name" id="newPkgName" placeholder="ชื่อแพ็กเกจใหม่">
                                        </div>
                                        <div class="col-md-12">
                                            <input type="text" class="form-control form-control-sm" name="new_pkg_desc" placeholder="รายละเอียด (ไม่บังคับ)">
                                        </div>
                                    </div>
                                    <small class="text-muted">* หากมีสินค้ารหัสใหม่ในตาราง ระบบจะสร้างสินค้าใหม่ลงคลังให้อัตโนมัติด้วย</small>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <button type="button" class="btn btn-warning mt-2" onclick="previewDocument()">
                                    <i class="bi bi-eye"></i> ตรวจสอบเอกสารก่อนบันทึก
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: Preview Mode -->
    <div id="step-preview" class="step-section">
        <div class="card card-custom mb-4">
            <div class="card-body p-5">
                <div class="text-center mb-5 border-bottom pb-4">
                    <img src="assets/img/logo.png" alt="Logo" style="height: 60px; margin-bottom: 15px;">
                    <h2 class="fw-bold" style="color: var(--primary-color);">ระบบจัดการเอกสาร Jasmine Active System</h2>
                    <h3 class="fw-bold mt-3" id="previewDocTitle">ใบเสนอราคา</h3>
                </div>
                
                <div class="row mb-5">
                    <div class="col-sm-6">
                        <h5 class="text-muted mb-2">ข้อมูลลูกค้า:</h5>
                        <div class="fw-bold fs-5 mb-1" id="previewCustName">-</div>
                        <div class="mb-1" id="previewCustAddress">-</div>
                        <div id="previewCustPhone">-</div>
                    </div>
                    <div class="col-sm-6 text-end">
                        <h5 class="text-muted mb-2">ข้อมูลเอกสาร:</h5>
                        <div><strong>วันที่:</strong> <span id="previewDate">-</span></div>
                    </div>
                </div>
                
                <table class="table table-bordered review-table">
                    <thead class="table-light">
                        <tr>
                            <th width="5%" class="text-center">#</th>
                            <th width="50%">รายการ</th>
                            <th width="15%" class="text-center">จำนวน</th>
                            <th width="15%" class="text-end">ราคา/หน่วย</th>
                            <th width="15%" class="text-end">จำนวนเงิน</th>
                        </tr>
                    </thead>
                    <tbody id="previewTableBody">
                        <!-- Preview rows injected via JS -->
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="4" class="text-end">รวมเงิน</th>
                            <th class="text-end text-secondary" id="previewSubtotal">0.00</th>
                        </tr>
                        <tr id="previewVatRow" style="display:none;">
                            <th colspan="4" class="text-end">ภาษีมูลค่าเพิ่ม 7%</th>
                            <th class="text-end text-secondary" id="previewVatAmount">0.00</th>
                        </tr>
                        <tr>
                            <th colspan="4" class="text-end fs-5">รวมทั้งสิ้น</th>
                            <th class="text-end fs-5 text-primary" id="previewGrandTotal">0.00</th>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <div class="card-footer bg-light p-4 text-center">
                <button type="button" class="btn btn-secondary btn-lg me-3" onclick="backToEdit()">
                    <i class="bi bi-pencil"></i> กลับไปแก้ไข
                </button>
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="bi bi-check-circle"></i> ยืนยันและบันทึกเอกสาร
                </button>
            </div>
        </div>
    </div>
</form>

<script>
// Format Number
function formatNumber(num) {
    return Number(num).toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

// Global state
let itemIndex = 0;

async function loadPackageItems() {
    const packageId = document.getElementById('packageSelect').value;
    if (!packageId) return;
    
    try {
        const response = await fetch(`ajax_get_package_items.php?package_id=${packageId}`);
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('emptyRow')?.remove();
            data.items.forEach(item => {
                appendRowToTable(item.code, item.name, item.unit, item.quantity, item.unit_price);
            });
            calcGrandTotal();
        } else {
            swalError(data.message);
        }
    } catch (err) {
        console.error(err);
        swalError('เกิดข้อผิดพลาดในการดึงข้อมูลแพ็กเกจ');
    }
}

async function uploadCsvToDoc() {
    const fileInput = document.getElementById('csvFileInput');
    if (fileInput.files.length === 0) return;
    
    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);
    
    try {
        const response = await fetch('ajax_import_csv_to_doc.php', {
            method: 'POST',
            body: formData
        });
        const data = await response.json();
        
        if (data.success) {
            document.getElementById('emptyRow')?.remove();
            data.items.forEach(item => {
                appendRowToTable(item.code, item.name, item.unit, item.quantity, item.unit_price);
            });
            calcGrandTotal();
            swalSuccess('นำเข้าข้อมูลสำเร็จ ' + data.items.length + ' รายการ');
        } else {
            swalError(data.message);
        }
    } catch (err) {
        console.error(err);
        swalError('เกิดข้อผิดพลาดในการอัปโหลดไฟล์');
    }
    
    // Reset file input
    fileInput.value = '';
}

function appendRowToTable(code, name, unit, quantity, price) {
    const total = parseFloat(quantity) * parseFloat(price);
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="ps-3">
            <input type="hidden" name="item_code[]" value="${code}">
            <input type="hidden" name="item_raw_name[]" value="${name}">
            <input type="hidden" name="item_unit[]" value="${unit}">
            <input type="text" class="form-control form-control-sm" name="item_name[]" value="[${code}] ${name}" required>
        </td>
        <td>
            <div class="input-group input-group-sm">
                <input type="number" step="0.01" class="form-control item-qty" name="item_qty[]" value="${quantity}" oninput="calcRow(this)" required>
                <span class="input-group-text">${unit}</span>
            </div>
        </td>
        <td>
            <input type="number" step="0.01" class="form-control form-control-sm item-price" name="item_price[]" value="${price}" oninput="calcRow(this)" required>
        </td>
        <td class="text-end pe-3">
            <input type="hidden" class="item-total-val" name="item_total[]" value="${total.toFixed(2)}">
            <span class="item-total-disp fw-bold">${formatNumber(total)}</span>
        </td>
        <td>
            <button type="button" class="btn btn-outline-danger btn-table-action" onclick="removeRow(this)"><i class="bi bi-x"></i></button>
        </td>
    `;
    document.getElementById('itemsTableBody').appendChild(tr);
}

function calcRow(el) {
    const tr = el.closest('tr');
    const qty = parseFloat(tr.querySelector('.item-qty').value) || 0;
    const price = parseFloat(tr.querySelector('.item-price').value) || 0;
    const total = qty * price;
    
    tr.querySelector('.item-total-val').value = total.toFixed(2);
    tr.querySelector('.item-total-disp').innerText = formatNumber(total);
    calcGrandTotal();
}

function removeRow(btn) {
    btn.closest('tr').remove();
    calcGrandTotal();
}

function calcGrandTotal() {
    let subtotal = 0;
    document.querySelectorAll('.item-total-val').forEach(input => {
        subtotal += parseFloat(input.value) || 0;
    });
    
    const includeVat = document.getElementById('includeVat').checked;
    const vatAmount = includeVat ? subtotal * 0.07 : 0;
    const grandTotal = subtotal + vatAmount;

    document.getElementById('inputSubtotal').value = subtotal.toFixed(2);
    document.getElementById('inputVatAmount').value = vatAmount.toFixed(2);
    document.getElementById('inputGrandTotal').value = grandTotal.toFixed(2);

    document.getElementById('displaySubtotal').innerText = formatNumber(subtotal);
    document.getElementById('displayVatAmount').innerText = formatNumber(vatAmount);
    document.getElementById('displayGrandTotal').innerText = formatNumber(grandTotal);
    document.getElementById('vatRow').style.display = includeVat ? '' : 'none';
}

// Workflow Logic
function previewDocument() {
    // Basic validation
    const custId = document.getElementById('docCustomer').value;
    if (!custId) {
        swalWarning('กรุณาเลือกลูกค้าก่อนดำเนินการต่อ');
        return;
    }
    if (document.querySelectorAll('.item-qty').length === 0) {
        swalWarning('กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ หรือเลือกแพ็กเกจด้านบน');
        return;
    }
    if (document.getElementById('saveAsPackage').checked) {
        if (!document.getElementById('newPkgCode').value || !document.getElementById('newPkgName').value) {
            swalWarning('กรุณากรอกรหัสและชื่อแพ็กเกจใหม่ให้ครบถ้วน');
            return;
        }
    }

    // Populate Preview Data
    const typeSelect = document.getElementById('docType');
    document.getElementById('previewDocTitle').innerText = typeSelect.options[typeSelect.selectedIndex].text;
    
    const custSelect = document.getElementById('docCustomer');
    const custOption = custSelect.options[custSelect.selectedIndex];
    document.getElementById('previewCustName').innerText = custOption.getAttribute('data-name');
    document.getElementById('previewCustAddress').innerText = custOption.getAttribute('data-address');
    document.getElementById('previewCustPhone').innerText = "โทร: " + custOption.getAttribute('data-phone');
    
    // Format Date from YYYY-MM-DD to DD/MM/YYYY
    const dateParts = document.getElementById('docDate').value.split('-');
    if(dateParts.length === 3) {
        document.getElementById('previewDate').innerText = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
    }

    // Populate Table
    const tbody = document.getElementById('previewTableBody');
    tbody.innerHTML = '';
    
    const names = document.querySelectorAll('input[name="item_name[]"]');
    const qtys = document.querySelectorAll('input[name="item_qty[]"]');
    const prices = document.querySelectorAll('input[name="item_price[]"]');
    const totals = document.querySelectorAll('input[name="item_total[]"]');
    
    for (let i = 0; i < names.length; i++) {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td class="text-center">${i + 1}</td>
            <td>${names[i].value}</td>
            <td class="text-center">${parseFloat(qtys[i].value)}</td>
            <td class="text-end">${formatNumber(prices[i].value)}</td>
            <td class="text-end fw-bold">${formatNumber(totals[i].value)}</td>
        `;
        tbody.appendChild(tr);
    }
    
    const includeVat = document.getElementById('includeVat').checked;
    document.getElementById('previewSubtotal').innerText = document.getElementById('displaySubtotal').innerText;
    document.getElementById('previewVatAmount').innerText = document.getElementById('displayVatAmount').innerText;
    document.getElementById('previewGrandTotal').innerText = document.getElementById('displayGrandTotal').innerText;
    document.getElementById('previewVatRow').style.display = includeVat ? '' : 'none';

    // Switch View
    document.getElementById('step-edit').classList.remove('active');
    document.getElementById('step-preview').classList.add('active');
    window.scrollTo(0,0);
}

function backToEdit() {
    document.getElementById('step-preview').classList.remove('active');
    document.getElementById('step-edit').classList.add('active');
    window.scrollTo(0,0);
}

function toggleDateInput() {
    const show = document.getElementById('noDate').checked;
    document.getElementById('docDate').disabled = !show;
    document.getElementById('docDate').style.opacity = show ? '1' : '0.4';
}

function togglePackageForm() {
    const isChecked = document.getElementById('saveAsPackage').checked;
    document.getElementById('newPackageForm').style.display = isChecked ? 'block' : 'none';
    document.getElementById('newPkgCode').required = isChecked;
    document.getElementById('newPkgName').required = isChecked;
}
</script>

<?php include_once 'includes/footer.php'; ?>
