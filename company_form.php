<?php
require_once 'db.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$company = [
    'name_th' => '',
    'name_en' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'tax_id' => '',
    'warranty_terms'         => '',
    'warranty_terms_invoice'  => '',
    'warranty_terms_receipt'  => '',
    'payment_terms' => '',
    'logo_path' => '',
    'stamp_enabled' => 0,
    'stamp_path' => '',
    'show_date_in_signature' => 1,
    'is_default' => 0
];

$error = '';

if ($id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
    $stmt->execute([$id]);
    $company = $stmt->fetch();
    if (!$company) {
        die("Company not found");
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $company['name_th']  = $_POST['name_th'] ?? '';
    $company['name_en']  = $_POST['name_en'] ?? '';
    $company['address']  = $_POST['address'] ?? '';
    $company['phone']    = $_POST['phone'] ?? '';
    $company['email']    = $_POST['email'] ?? '';
    $company['tax_id']   = $_POST['tax_id'] ?? '';
    $company['warranty_terms']          = $_POST['warranty_terms'] ?? '';
    $company['warranty_terms_invoice']   = $_POST['warranty_terms_invoice'] ?? '';
    $company['warranty_terms_receipt']   = $_POST['warranty_terms_receipt'] ?? '';
    $company['payment_terms']    = $_POST['payment_terms'] ?? '';
    $company['stamp_enabled']    = isset($_POST['stamp_enabled']) ? 1 : 0;
    $company['show_date_in_signature'] = isset($_POST['show_date_in_signature']) ? 1 : 0;

    // Handle Logo upload
    if (!empty($_FILES['logo_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['logo_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            $filename = 'logo_' . time() . '.' . $ext;
            $dest = __DIR__ . '/assets/img/' . $filename;
            if (move_uploaded_file($_FILES['logo_file']['tmp_name'], $dest)) {
                $company['logo_path'] = 'assets/img/' . $filename;
            }
        }
    }

    // Handle Stamp upload
    if (!empty($_FILES['stamp_file']['name'])) {
        $ext = strtolower(pathinfo($_FILES['stamp_file']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif'])) {
            $filename = 'stamp_' . time() . '.' . $ext;
            $dest = __DIR__ . '/assets/img/' . $filename;
            if (move_uploaded_file($_FILES['stamp_file']['tmp_name'], $dest)) {
                $company['stamp_path'] = 'assets/img/' . $filename;
            }
        }
    }

    if (empty($company['name_th'])) {
        $error = "กรุณากรอกชื่อบริษัท (ภาษาไทย)";
    } else {
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE companies SET 
                name_th=?, name_en=?, address=?, phone=?, email=?, tax_id=?, 
                warranty_terms=?, warranty_terms_invoice=?, warranty_terms_receipt=?,
                payment_terms=?, logo_path=?, stamp_enabled=?, stamp_path=?, show_date_in_signature=? 
                WHERE id=?");
            $stmt->execute([
                $company['name_th'], $company['name_en'], $company['address'], $company['phone'], $company['email'], $company['tax_id'],
                $company['warranty_terms'], $company['warranty_terms_invoice'], $company['warranty_terms_receipt'],
                $company['payment_terms'], $company['logo_path'], $company['stamp_enabled'], $company['stamp_path'], $company['show_date_in_signature'],
                $id
            ]);
        } else {
            // Check if it's the first company, if so make it default
            $count = $pdo->query("SELECT COUNT(*) FROM companies")->fetchColumn();
            $is_default = ($count == 0) ? 1 : 0;

            $stmt = $pdo->prepare("INSERT INTO companies (
                name_th, name_en, address, phone, email, tax_id, 
                warranty_terms, warranty_terms_invoice, warranty_terms_receipt,
                payment_terms, logo_path, stamp_enabled, stamp_path, show_date_in_signature, is_default
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $company['name_th'], $company['name_en'], $company['address'], $company['phone'], $company['email'], $company['tax_id'],
                $company['warranty_terms'], $company['warranty_terms_invoice'], $company['warranty_terms_receipt'],
                $company['payment_terms'], $company['logo_path'], $company['stamp_enabled'], $company['stamp_path'], $company['show_date_in_signature'], $is_default
            ]);
        }
        header("Location: companies.php?msg=saved");
        exit;
    }
}

include_once 'includes/header.php';
?>

<div class="page-header">
    <a href="companies.php" class="btn btn-outline-secondary">
        <i class="bi bi-arrow-left"></i> กลับ
    </a>
</div>

<?php if ($error): ?>
<script>document.addEventListener('DOMContentLoaded',()=>swalError(<?= json_encode($error) ?>));</script>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data">
<div class="row g-4">

    <!-- Company Info Card -->
    <div class="col-lg-8">
        <div class="card card-custom">
            <div class="card-header card-header-custom">
                <i class="bi bi-building me-2"></i> ข้อมูลบริษัท
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ชื่อบริษัท (ภาษาไทย) <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="name_th" value="<?= htmlspecialchars($company['name_th']) ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">ชื่อบริษัท (ภาษาอังกฤษ)</label>
                        <input type="text" class="form-control" name="name_en" value="<?= htmlspecialchars($company['name_en']) ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold">ที่อยู่</label>
                        <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($company['address']) ?></textarea>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">เบอร์โทรศัพท์</label>
                        <input type="text" class="form-control" name="phone" value="<?= htmlspecialchars($company['phone']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">อีเมล</label>
                        <input type="email" class="form-control" name="email" value="<?= htmlspecialchars($company['email']) ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-bold">เลขประจำตัวผู้เสียภาษี</label>
                        <input type="text" class="form-control" name="tax_id" value="<?= htmlspecialchars($company['tax_id']) ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-bold">เงื่อนไขการชำระเงิน</label>
                        <input type="text" class="form-control" name="payment_terms" value="<?= htmlspecialchars($company['payment_terms']) ?>" placeholder="เช่น ภายใน 30 วัน">
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold"><i class="bi bi-sticky me-1 text-success"></i> หมายเหตุ – ใบเสนอราคา (Quotation)</label>
                        <textarea class="form-control" name="warranty_terms" rows="3" placeholder="เงื่อนไขและหมายเหตุที่ต้องการแสดงในใบเสนอราคา"><?= htmlspecialchars($company['warranty_terms']) ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold"><i class="bi bi-sticky me-1 text-warning"></i> หมายเหตุ – ใบส่งของ/ใบแจ้งหนี้ (Invoice)</label>
                        <textarea class="form-control" name="warranty_terms_invoice" rows="3" placeholder="เงื่อนไขและหมายเหตุที่ต้องการแสดงในใบส่งของ/ใบแจ้งหนี้"><?= htmlspecialchars($company['warranty_terms_invoice'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label class="form-label fw-bold"><i class="bi bi-sticky me-1 text-info"></i> หมายเหตุ – ใบเสร็จรับเงิน (Receipt)</label>
                        <textarea class="form-control" name="warranty_terms_receipt" rows="3" placeholder="เงื่อนไขและหมายเหตุที่ต้องการแสดงในใบเสร็จรับเงิน"><?= htmlspecialchars($company['warranty_terms_receipt'] ?? '') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Logo & Stamp Card -->
    <div class="col-lg-4">
        <!-- Logo -->
        <div class="card card-custom mb-4">
            <div class="card-header card-header-custom">
                <i class="bi bi-image me-2"></i> โลโก้บริษัท
            </div>
            <div class="card-body">
                <?php if (!empty($company['logo_path']) && file_exists(__DIR__ . '/' . $company['logo_path'])): ?>
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($company['logo_path']) ?>?t=<?= time() ?>" alt="Logo" style="max-height:100px; max-width:100%; border:1px solid #eee; border-radius:8px; padding:4px;">
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted mb-3 py-3 border rounded">
                        <i class="bi bi-image" style="font-size:2rem;"></i><br>ยังไม่มีโลโก้
                    </div>
                <?php endif; ?>
                <label class="form-label fw-bold">อัปโหลดโลโก้ใหม่</label>
                <input type="file" class="form-control" name="logo_file" accept=".png,.jpg,.jpeg,.gif">
                <small class="text-muted">PNG, JPG ขนาดสูงสุด 2MB (แนะนำพื้นหลังใส .png)</small>
            </div>
        </div>

        <!-- Stamp -->
        <div class="card card-custom mb-4">
            <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="bi bi-patch-check me-2"></i> ตราประทับ / ลายเซ็น</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="stampEnabled" name="stamp_enabled" 
                           <?= $company['stamp_enabled'] ? 'checked' : '' ?> onchange="toggleStamp()">
                </div>
            </div>
            <div class="card-body" id="stampSection" style="<?= $company['stamp_enabled'] ? '' : 'display:none;' ?>">
                <?php if (!empty($company['stamp_path']) && file_exists(__DIR__ . '/' . $company['stamp_path'])): ?>
                    <div class="text-center mb-3">
                        <img src="<?= htmlspecialchars($company['stamp_path']) ?>?t=<?= time() ?>" alt="Stamp" style="max-height:120px; max-width:100%; border:1px solid #eee; border-radius:8px; padding:4px;">
                    </div>
                <?php else: ?>
                    <div class="text-center text-muted mb-3 py-3 border rounded">
                        <i class="bi bi-patch-check" style="font-size:2rem;"></i><br>ยังไม่มีตราประทับ
                    </div>
                <?php endif; ?>
                <label class="form-label fw-bold">อัปโหลดตราประทับ</label>
                <input type="file" class="form-control" name="stamp_file" accept=".png,.jpg,.jpeg,.gif">
                <small class="text-muted">แนะนำใช้ไฟล์ PNG พื้นหลังโปร่งใส</small>
            </div>
            <div class="card-body" id="stampDisabledMsg" style="<?= $company['stamp_enabled'] ? 'display:none;' : '' ?>">
                <p class="text-muted mb-0 text-center py-2"><i class="bi bi-dash-circle me-1"></i> ไม่แสดงตราประทับในเอกสาร</p>
            </div>
        </div>
        
        <!-- Signature Date Toggle -->
        <div class="card card-custom">
            <div class="card-header card-header-custom d-flex justify-content-between align-items-center">
                <span><i class="bi bi-calendar-date me-2"></i> วันที่ในส่วนลายเซ็น</span>
                <div class="form-check form-switch mb-0">
                    <input class="form-check-input" type="checkbox" id="showDateSignature" name="show_date_in_signature"
                           <?= $company['show_date_in_signature'] ? 'checked' : '' ?>>
                </div>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-0">เปิดเพื่อให้แสดงบรรทัดวันที่ใต้ช่องเซ็นชื่อ</p>
            </div>
        </div>
    </div>

</div>

<div class="mt-4 text-end">
    <button type="submit" class="btn btn-primary btn-lg">
        <i class="bi bi-save me-2"></i> บันทึกข้อมูลบริษัท
    </button>
</div>
</form>

<script>
function toggleStamp() {
    const enabled = document.getElementById('stampEnabled').checked;
    document.getElementById('stampSection').style.display = enabled ? '' : 'none';
    document.getElementById('stampDisabledMsg').style.display = enabled ? 'none' : '';
}
</script>

<?php include_once 'includes/footer.php'; ?>
