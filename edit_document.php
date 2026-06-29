<?php
require_once 'db.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { header('Location: documents.php'); exit; }

// Load document
$stmt = $pdo->prepare("SELECT * FROM documents WHERE id = ?");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { header('Location: documents.php'); exit; }

// Load items
$stmt = $pdo->prepare("SELECT * FROM document_items WHERE document_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$doc_items = $stmt->fetchAll();

// Load supporting data
$customers = $pdo->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
$packages  = $pdo->query("SELECT * FROM packages ORDER BY name ASC")->fetchAll();
$companies = $pdo->query("SELECT id, name_th, is_default FROM companies ORDER BY is_default DESC, name_th ASC")->fetchAll();

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        die("Invalid CSRF Token");
    }

    $date        = $_POST['date']        ?? $doc['date'];
    $customer_id = intval($_POST['customer_id'] ?? $doc['customer_id']);
    $company_id  = intval($_POST['company_id']  ?? $doc['company_id']);
    $items_name  = $_POST['item_name']   ?? [];
    $items_unit  = $_POST['item_unit']   ?? [];
    $items_qty   = $_POST['item_qty']    ?? [];
    $items_price = $_POST['item_price']  ?? [];
    $items_total = $_POST['item_total']  ?? [];
    $grand_total = floatval($_POST['grand_total'] ?? 0);
    $include_vat = isset($_POST['include_vat']) ? 1 : 0;
    $show_date   = isset($_POST['show_date'])   ? 1 : 0;
    $notes       = trim($_POST['notes'] ?? '');

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("UPDATE documents SET date=?, customer_id=?, company_id=?, total_amount=?, include_vat=?, show_date=?, notes=? WHERE id=?");
        $stmt->execute([$date, $customer_id, $company_id, $grand_total, $include_vat, $show_date, $notes ?: null, $id]);

        $pdo->prepare("DELETE FROM document_items WHERE document_id = ?")->execute([$id]);

        $stmt = $pdo->prepare("INSERT INTO document_items (document_id, item_name, quantity, unit, price, total) VALUES (?, ?, ?, ?, ?, ?)");
        for ($i = 0; $i < count($items_name); $i++) {
            if (!empty($items_name[$i]) && floatval($items_qty[$i]) > 0) {
                $stmt->execute([$id, $items_name[$i], $items_qty[$i], $items_unit[$i] ?? '', $items_price[$i], $items_total[$i]]);
            }
        }

        $pdo->commit();
        header("Location: view_document.php?id={$id}&msg=updated");
        exit;
    } catch (\PDOException $e) {
        $pdo->rollBack();
        $error = "เกิดข้อผิดพลาดในการบันทึก: " . $e->getMessage();
    }
}

$type_labels = ['Quote' => 'ใบเสนอราคา', 'Invoice' => 'ใบแจ้งหนี้', 'Receipt' => 'ใบเสร็จรับเงิน'];
$prefill_items_json = json_encode(array_map(fn($it) => [
    'name'  => $it['item_name'],
    'unit'  => $it['unit'] ?? '',
    'qty'   => $it['quantity'],
    'price' => $it['price'],
], $doc_items));

include_once 'includes/header.php';
?>

<style>
    .step-section { display: none; }
    .step-section.active { display: block; }
    .review-table th, .review-table td { padding: 12px; }
</style>

<div class="page-header">
    <h2 class="page-title"><i class="bi bi-pencil-square"></i> แก้ไขเอกสาร</h2>
</div>

<?php if (isset($error)): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<div class="alert alert-warning mb-3">
    <i class="bi bi-exclamation-triangle me-2"></i>
    กำลังแก้ไขเอกสาร <strong><?= htmlspecialchars($doc['doc_no']) ?></strong>
    (<?= htmlspecialchars($type_labels[$doc['type']] ?? $doc['type']) ?>)
    — เลขที่เอกสารจะคงเดิม
</div>

<form method="POST" id="editForm" novalidate>
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">
    <input type="hidden" name="subtotal" id="inputSubtotal" value="0">
    <input type="hidden" name="vat_amount" id="inputVatAmount" value="0">
    <input type="hidden" name="grand_total" id="inputGrandTotal" value="0">

    <!-- STEP 1: Edit -->
    <div id="step-edit" class="step-section active">
        <div class="row">
            <div class="col-md-12 mb-4">
                <div class="card card-custom">
                    <div class="card-header card-header-custom">ข้อมูลเอกสาร</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label class="form-label small mb-1">ประเภทเอกสาร</label>
                                <input type="text" class="form-control form-control-sm bg-light" value="<?= htmlspecialchars($type_labels[$doc['type']] ?? $doc['type']) ?>" readonly>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small mb-1">ออกในนามบริษัท <span class="text-danger">*</span></label>
                                <select name="company_id" class="form-select form-select-sm" required>
                                    <?php foreach ($companies as $comp): ?>
                                        <option value="<?= $comp['id'] ?>" <?= $comp['id'] == $doc['company_id'] ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($comp['name_th']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label small mb-1">วันที่</label>
                                <div class="input-group input-group-sm">
                                    <input type="date" class="form-control" name="date" id="docDate" value="<?= htmlspecialchars($doc['date']) ?>" required>
                                </div>
                                <div class="form-check mt-1">
                                    <input class="form-check-input" type="checkbox" id="noDate" name="show_date" value="1" <?= $doc['show_date'] ? 'checked' : '' ?>>
                                    <label class="form-check-label text-muted small" for="noDate">แสดงวันที่ในเอกสาร</label>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label small mb-1">เลือกลูกค้า <span class="text-danger">*</span></label>
                                <select class="form-select form-select-sm" name="customer_id" id="docCustomer" required>
                                    <option value="">-- เลือกลูกค้า --</option>
                                    <?php foreach ($customers as $c): ?>
                                        <option value="<?= $c['id'] ?>"
                                            <?= $c['id'] == $doc['customer_id'] ? 'selected' : '' ?>
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

            <div class="col-md-12 mb-4">
                <div class="card card-custom">
                    <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                        <span>รายการสินค้า</span>
                        <div class="d-flex gap-2">
                            <a href="assets/samples/sample_document_items.csv" class="btn btn-sm btn-outline-secondary" download>
                                <i class="bi bi-download"></i> โหลดตัวอย่าง CSV
                            </a>
                            <input type="file" id="csvFileInput" accept=".csv" style="display: none;" onchange="uploadCsvToDoc()">
                            <button type="button" class="btn btn-sm btn-success" onclick="document.getElementById('csvFileInput').click()">
                                <i class="bi bi-file-earmark-arrow-up"></i> Import CSV
                            </button>
                            <select class="form-select form-select-sm" style="width: 250px;" id="packageSelect" onchange="loadPackageItems()">
                                <option value="">-- เพิ่มสินค้าจากแพ็กเกจ --</option>
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
                                    <tr id="emptyRow" style="display:none;">
                                        <td colspan="5" class="text-center py-4 text-muted">กรุณาเพิ่มรายการสินค้า</td>
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
                                    <input class="form-check-input" type="checkbox" id="includeVat" name="include_vat" <?= $doc['include_vat'] ? 'checked' : '' ?> onchange="calcGrandTotal()">
                                    <label class="form-check-label fw-bold text-success" for="includeVat">รวม VAT 7% (ภาษีมูลค่าเพิ่ม)</label>
                                </div>
                                <div class="mt-2">
                                    <label class="form-label small mb-1 fw-bold">หมายเหตุ (Notes)</label>
                                    <textarea class="form-control form-control-sm" name="notes" id="docNotes" rows="2" placeholder="หมายเหตุเพิ่มเติม (ไม่บังคับ)"><?= htmlspecialchars($doc['notes'] ?? '') ?></textarea>
                                </div>
                            </div>
                            <div class="col-md-4 text-end">
                                <a href="view_document.php?id=<?= $id ?>" class="btn btn-secondary mt-2 me-2">
                                    <i class="bi bi-x-circle"></i> ยกเลิก
                                </a>
                                <button type="button" class="btn btn-warning mt-2" onclick="previewDocument()">
                                    <i class="bi bi-eye"></i> ตรวจสอบก่อนบันทึก
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- STEP 2: Preview -->
    <div id="step-preview" class="step-section">
        <div class="card card-custom mb-4">
            <div class="card-body p-5">
                <div class="text-center mb-4">
                    <h3 class="fw-bold" id="previewDocTitle">-</h3>
                    <p class="text-muted">ตรวจสอบข้อมูลก่อนบันทึก</p>
                </div>
                <div class="row mb-4">
                    <div class="col-sm-6">
                        <h5 class="text-muted mb-2">ข้อมูลลูกค้า:</h5>
                        <div class="fw-bold fs-5 mb-1" id="previewCustName">-</div>
                        <div class="mb-1" id="previewCustAddress">-</div>
                        <div id="previewCustPhone">-</div>
                    </div>
                    <div class="col-sm-6 text-end">
                        <h5 class="text-muted mb-2">ข้อมูลเอกสาร:</h5>
                        <div><strong>เลขที่:</strong> <?= htmlspecialchars($doc['doc_no']) ?></div>
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
                    <tbody id="previewTableBody"></tbody>
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
                    <i class="bi bi-check-circle"></i> ยืนยันและบันทึกการแก้ไข
                </button>
            </div>
        </div>
    </div>
</form>

<script>
function formatNumber(num) {
    return Number(num).toLocaleString('th-TH', {minimumFractionDigits: 2, maximumFractionDigits: 2});
}

async function loadPackageItems() {
    const packageId = document.getElementById('packageSelect').value;
    if (!packageId) return;
    try {
        const response = await fetch(`ajax_get_package_items.php?package_id=${packageId}`);
        const data = await response.json();
        if (data.success) {
            data.items.forEach(item => appendRowToTable(item.code, item.name, item.unit, item.quantity, item.unit_price));
            calcGrandTotal();
        } else {
            swalError(data.message);
        }
    } catch (err) {
        swalError('เกิดข้อผิดพลาดในการดึงข้อมูลแพ็กเกจ');
    }
}

async function uploadCsvToDoc() {
    const fileInput = document.getElementById('csvFileInput');
    if (fileInput.files.length === 0) return;
    const formData = new FormData();
    formData.append('csv_file', fileInput.files[0]);
    try {
        const response = await fetch('ajax_import_csv_to_doc.php', { method: 'POST', body: formData });
        const data = await response.json();
        if (data.success) {
            data.items.forEach(item => appendRowToTable(item.code, item.name, item.unit, item.quantity, item.unit_price));
            calcGrandTotal();
            swalSuccess('นำเข้าข้อมูลสำเร็จ ' + data.items.length + ' รายการ');
        } else {
            swalError(data.message);
        }
    } catch (err) {
        swalError('เกิดข้อผิดพลาดในการอัปโหลดไฟล์');
    }
    fileInput.value = '';
}

function appendRowToTable(code, name, unit, quantity, price) {
    const total = parseFloat(quantity) * parseFloat(price);
    const displayName = code ? `[${code}] ${name}` : name;
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td class="ps-3">
            <input type="hidden" name="item_code[]" value="${code}">
            <input type="hidden" name="item_raw_name[]" value="${name}">
            <input type="hidden" name="item_unit[]" value="${unit}">
            <input type="text" class="form-control form-control-sm" name="item_name[]" value="${displayName}" required>
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

function previewDocument() {
    const custId = document.getElementById('docCustomer').value;
    if (!custId) { swalWarning('กรุณาเลือกลูกค้าก่อนดำเนินการต่อ'); return; }
    if (document.querySelectorAll('.item-qty').length === 0) { swalWarning('กรุณาเพิ่มสินค้าอย่างน้อย 1 รายการ'); return; }

    document.getElementById('previewDocTitle').innerText = '<?= htmlspecialchars($type_labels[$doc['type']] ?? $doc['type']) ?>';

    const custSelect = document.getElementById('docCustomer');
    const custOption = custSelect.options[custSelect.selectedIndex];
    document.getElementById('previewCustName').innerText = custOption.getAttribute('data-name');
    document.getElementById('previewCustAddress').innerText = custOption.getAttribute('data-address');
    document.getElementById('previewCustPhone').innerText = "โทร: " + custOption.getAttribute('data-phone');

    const dateParts = document.getElementById('docDate').value.split('-');
    if (dateParts.length === 3) {
        document.getElementById('previewDate').innerText = `${dateParts[2]}/${dateParts[1]}/${dateParts[0]}`;
    }

    const tbody = document.getElementById('previewTableBody');
    tbody.innerHTML = '';
    const names   = document.querySelectorAll('input[name="item_name[]"]');
    const qtys    = document.querySelectorAll('input[name="item_qty[]"]');
    const prices  = document.querySelectorAll('input[name="item_price[]"]');
    const totals  = document.querySelectorAll('input[name="item_total[]"]');
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
    document.getElementById('previewSubtotal').innerText   = document.getElementById('displaySubtotal').innerText;
    document.getElementById('previewVatAmount').innerText  = document.getElementById('displayVatAmount').innerText;
    document.getElementById('previewGrandTotal').innerText = document.getElementById('displayGrandTotal').innerText;
    document.getElementById('previewVatRow').style.display = includeVat ? '' : 'none';

    document.getElementById('step-edit').classList.remove('active');
    document.getElementById('step-preview').classList.add('active');
    window.scrollTo(0, 0);
}

function backToEdit() {
    document.getElementById('step-preview').classList.remove('active');
    document.getElementById('step-edit').classList.add('active');
    window.scrollTo(0, 0);
}

// Pre-populate existing items
document.addEventListener('DOMContentLoaded', function() {
    const items = <?= $prefill_items_json ?>;
    items.forEach(item => appendRowToTable('', item.name, item.unit, item.qty, item.price));
    calcGrandTotal();
});
</script>

<?php include_once 'includes/footer.php'; ?>
