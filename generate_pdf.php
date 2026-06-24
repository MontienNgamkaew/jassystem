<?php
session_start();
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
require_once 'db.php';
require_once 'vendor/autoload.php';

$id = intval($_GET['id'] ?? 0);
if (!$id) { die('ไม่พบเอกสาร'); }

// Load document
$stmt = $pdo->prepare("
    SELECT d.*, c.name as cust_name, c.address as cust_address,
           c.tax_id as cust_tax_id, c.phone as cust_phone
    FROM documents d
    LEFT JOIN customers c ON d.customer_id = c.id
    WHERE d.id = ?
");
$stmt->execute([$id]);
$doc = $stmt->fetch();
if (!$doc) { die('ไม่พบเอกสาร'); }

// Load items
$stmt = $pdo->prepare("SELECT * FROM document_items WHERE document_id = ? ORDER BY id ASC");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Load settings (Company Info)
$stmt = $pdo->prepare("SELECT * FROM companies WHERE id = ?");
$stmt->execute([$doc['company_id']]);
$company = $stmt->fetch();
if (!$company) {
    // Fallback to default company if not found
    $stmt = $pdo->query("SELECT * FROM companies WHERE is_default = 1 LIMIT 1");
    $company = $stmt->fetch();
}

$settings = [];
if ($company) {
    $settings = [
        'company_name_th' => $company['name_th'],
        'company_name_en' => $company['name_en'],
        'company_address' => $company['address'],
        'company_phone'   => $company['phone'],
        'company_email'   => $company['email'],
        'company_tax_id'  => $company['tax_id'],
        'warranty_terms'         => $company['warranty_terms'],
        'warranty_terms_invoice' => $company['warranty_terms_invoice'] ?? '',
        'warranty_terms_receipt' => $company['warranty_terms_receipt'] ?? '',
        'payment_terms'   => $company['payment_terms'],
        'logo_path'       => $company['logo_path'],
        'stamp_enabled'   => $company['stamp_enabled'],
        'stamp_path'      => $company['stamp_path'],
        'show_date_in_signature' => $company['show_date_in_signature'],
    ];
}

// ---- Thai Baht Text Converter ----
function bahtText($amount) {
    $amount = round($amount, 2);
    $parts  = explode('.', number_format($amount, 2, '.', ''));
    $int    = (int)$parts[0];
    $dec    = (int)$parts[1];
    $ones   = ['','หนึ่ง','สอง','สาม','สี่','ห้า','หก','เจ็ด','แปด','เก้า'];
    $tens   = ['','สิบ','ยี่สิบ','สามสิบ','สี่สิบ','ห้าสิบ','หกสิบ','เจ็ดสิบ','แปดสิบ','เก้าสิบ'];
    $pos    = ['','สิบ','ร้อย','พัน','หมื่น','แสน'];

    function n2t($n, $ones, $tens, $pos) {
        if ($n == 0) return '';
        $r = '';
        $d = str_split(strrev((string)$n));
        foreach ($d as $i => $c) {
            $c = (int)$c;
            if ($c == 0) continue;
            if ($i == 0 && $c == 1 && strlen((string)$n) > 1) { $r = 'เอ็ด'.$r; continue; }
            if ($i == 1 && $c == 1) { $r = 'สิบ'.$r; continue; }
            if ($i == 1)            { $r = $tens[$c].$r; continue; }
            $r = $ones[$c].($pos[$i] ?? '').$r;
        }
        return $r;
    }

    $text = '';
    if ($int >= 1000000) { $text .= n2t((int)($int/1000000),$ones,$tens,$pos).'ล้าน'; $int %= 1000000; }
    $text .= n2t($int,$ones,$tens,$pos);
    if (empty($text)) $text = 'ศูนย์';
    $text .= 'บาท';
    $text .= $dec > 0 ? n2t($dec,$ones,$tens,$pos).'สตางค์' : 'ถ้วน';
    return $text;
}

// ---- Prepare amounts ----
$subtotal    = array_sum(array_column($items, 'total'));
$include_vat = (bool)$doc['include_vat'];
$vat_amount  = $include_vat ? round($subtotal * 0.07, 2) : 0;
$grand_total = $subtotal + $vat_amount;

// ---- Document meta ----
$typeLabels = [
    'Quote' => 'ใบเสนอราคา / Quotation',
    'Invoice' => 'ใบแจ้งหนี้ / Invoice',
    'Receipt' => 'ใบเสร็จรับเงิน / Receipt'
];
$docTypeName = $typeLabels[$doc['type']] ?? 'เอกสาร';

// Signature Labels
$sig_left_text = 'ผู้สั่งซื้อ';
$sig_right_text = 'ผู้เสนอราคา';
if ($doc['type'] === 'Invoice') {
    $sig_left_text = 'ผู้รับของ';
    $sig_right_text = 'ผู้ส่งของ';
} else if ($doc['type'] === 'Receipt') {
    $sig_left_text = 'ผู้จ่ายเงิน';
    $sig_right_text = 'ผู้รับเงิน';
}

$payment_terms = htmlspecialchars($settings['payment_terms'] ?? 'ภายใน 30 วัน');

// Select warranty_terms based on document type
if ($doc['type'] === 'Invoice') {
    $raw_warranty = $settings['warranty_terms_invoice'] ?? $settings['warranty_terms'] ?? '';
} elseif ($doc['type'] === 'Receipt') {
    $raw_warranty = $settings['warranty_terms_receipt'] ?? $settings['warranty_terms'] ?? '';
} else {
    $raw_warranty = $settings['warranty_terms'] ?? '';
}
$warranty = nl2br(htmlspecialchars($raw_warranty));

$show_date = (bool)$doc['show_date'];

$logo_tag = '';
if (!empty($settings['logo_path']) && file_exists(__DIR__ . '/' . $settings['logo_path'])) {
    $logo_tag = '<img src="' . htmlspecialchars($settings['logo_path']) . '" style="max-height:80px;max-width:80px;border-radius:8px;object-fit:contain;">';
}

$stamp_tag = '';
if (!empty($settings['stamp_enabled']) && !empty($settings['stamp_path']) && file_exists(__DIR__ . '/' . $settings['stamp_path'])) {
    $stamp_tag = '<img src="' . htmlspecialchars($settings['stamp_path']) . '" style="max-height:110px;max-width:110px;opacity:0.85;">';
}

// ---- Build item rows ----
$item_rows = '';
foreach ($items as $i => $it) {
    $bg = ($i % 2 == 0) ? '#ffffff' : '#f8f9fa';
    $item_rows .= '<tr style="background: ' . $bg . ';">
        <td style="text-align: center; border: 1px solid #dee2e6; padding: 8px; color: #212529;">' . ($i + 1) . '</td>
        <td style="border: 1px solid #dee2e6; padding: 8px; color: #212529;">' . htmlspecialchars($it['item_name']) . '</td>
        <td style="text-align: center; border: 1px solid #dee2e6; padding: 8px; color: #212529;">' . number_format($it['quantity'], 2) . '</td>
        <td style="text-align: right; border: 1px solid #dee2e6; padding: 8px; color: #212529;">' . number_format($it['price'], 2) . '</td>
        <td style="text-align: right; border: 1px solid #dee2e6; padding: 8px; font-weight: bold; color: #212529;">' . number_format($it['total'], 2) . '</td>
    </tr>';
}

// ---- Build full HTML ----
$html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body {
            font-family: prompt, sans-serif;
            font-size: 9.5pt;
            color: #212529;
            margin: 0;
            padding: 0;
        }
        .invoice-header {
            border-bottom: 2px solid #84cc16;
            padding-bottom: 15px;
            margin-bottom: 25px;
        }
        .text-primary {
            color: #84cc16;
        }
        .text-muted {
            color: #6c757d;
        }
        .fw-bold {
            font-weight: bold;
        }
        .text-end {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .table-items {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            font-size: 9.5pt;
            border: 1px solid #dee2e6;
        }
        .table-items th {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px;
            font-weight: bold;
            color: #212529;
        }
        .table-items td {
            border: 1px solid #dee2e6;
            padding: 8px;
            vertical-align: middle;
        }
        .signature-container {
            margin-top: 50px;
            position: relative;
        }
        .signature-box {
            border-top: 1px dashed #ccc;
            text-align: center;
            padding-top: 8px;
            font-size: 9pt;
            color: #555;
            width: 220px;
            margin: 0 auto;
        }
    </style>
</head>
<body>

<!-- Invoice Header -->
<div class="invoice-header">
    <table width="100%">
    <tr>
        <td width="65%" style="vertical-align: middle;">
            <table width="100%">
            <tr>';
if ($logo_tag) {
    $html .= '<td width="80px" style="padding-right: 15px; vertical-align: middle;">' . $logo_tag . '</td>';
}
$html .= '<td style="vertical-align: middle;">
                    <div style="font-size: 15pt; font-weight: bold; color: #212529;">' . htmlspecialchars($settings['company_name_th'] ?? 'Jasmine Active') . '</div>';
if (!empty($settings['company_name_en'])) {
    $html .= '<div style="font-size: 9.5pt; color: #6c757d; margin-top: 2px;">' . htmlspecialchars($settings['company_name_en']) . '</div>';
}
$html .= '</td>
            </tr>
            </table>
        </td>
        <td width="35%" style="text-align: right; vertical-align: middle;">
            <div style="font-size: 16pt; font-weight: bold; color: #84cc16; margin-bottom: 4px;">' . htmlspecialchars($docTypeName) . '</div>
            <div style="font-size: 9pt; color: #6c757d; line-height: 1.4;">
                <strong>เลขที่เอกสาร:</strong> <span style="color: #212529; font-weight: bold;">' . htmlspecialchars($doc['doc_no']) . '</span><br>
                <strong>วันที่:</strong> ' . date('d/m/Y', strtotime($doc['date'])) . '<br>
                <strong>เงื่อนไขชำระเงิน:</strong> ' . $payment_terms . '
            </div>
        </td>
    </tr>
    </table>
</div>

<!-- Company and Customer Info -->
<table width="100%" style="margin-bottom: 25px;">
<tr>
    <td width="48%" style="vertical-align: top;">
        <div style="font-size: 9.5pt; font-weight: bold; color: #84cc16; border-bottom: 1px solid #dee2e6; padding-bottom: 4px; margin-bottom: 6px;">ข้อมูลผู้ขาย (Seller)</div>
        <div style="font-size: 9pt; color: #212529; line-height: 1.5;">
            <strong>' . htmlspecialchars($settings['company_name_th'] ?? 'Jasmine Active') . '</strong><br>
            ' . nl2br(htmlspecialchars($settings['company_address'] ?? '')) . '<br>';
if (!empty($settings['company_phone'])) {
    $html .= '<strong>โทร:</strong> ' . htmlspecialchars($settings['company_phone']) . '<br>';
}
if (!empty($settings['company_tax_id'])) {
    $html .= '<strong>เลขประจำตัวผู้เสียภาษี:</strong> ' . htmlspecialchars($settings['company_tax_id']);
}
$html .= '</div>
    </td>
    <td width="4%"></td>
    <td width="48%" style="vertical-align: top; text-align: right;">
        <div style="font-size: 9.5pt; font-weight: bold; color: #84cc16; border-bottom: 1px solid #dee2e6; padding-bottom: 4px; margin-bottom: 6px; text-align: right;">ข้อมูลลูกค้า (Customer)</div>
        <div style="font-size: 9pt; color: #212529; line-height: 1.5; text-align: right;">
            <strong>' . htmlspecialchars($doc['cust_name']) . '</strong><br>
            ' . nl2br(htmlspecialchars($doc['cust_address'] ?? '')) . '<br>';
if (!empty($doc['cust_phone'])) {
    $html .= '<strong>โทร:</strong> ' . htmlspecialchars($doc['cust_phone']) . '<br>';
}
if (!empty($doc['cust_tax_id'])) {
    $html .= '<strong>เลขประจำตัวผู้เสียภาษี:</strong> ' . htmlspecialchars($doc['cust_tax_id']);
}
$html .= '</div>
    </td>
</tr>
</table>

<!-- Document Items Table -->
<table class="table-items">
<thead>
    <tr>
        <th width="8%" class="text-center">#</th>
        <th style="text-align: left;">รายการสินค้า / คำอธิบาย</th>
        <th width="12%" class="text-center">จำนวน</th>
        <th width="20%" class="text-end">ราคาต่อหน่วย (บาท)</th>
        <th width="20%" class="text-end">ราคารวม (บาท)</th>
    </tr>
</thead>
<tbody>
    ' . $item_rows . '
</tbody>
</table>

<!-- Summary and Terms Section -->
<table width="100%" style="margin-top: 10px; margin-bottom: 25px;">
<tr>
    <td width="55%" style="vertical-align: top; padding-right: 20px;">
        <div style="font-size: 8.5pt; line-height: 1.4; color: #212529;">
            <strong>จำนวนเงิน (ตัวอักษร):</strong> ' . bahtText($grand_total) . '<br><br>';
if (!empty($raw_warranty)) {
    $html .= '<div style="font-weight: bold; color: #84cc16; margin-bottom: 4px;">เงื่อนไข / ข้อตกลง (Terms & Conditions)</div>
            <div style="color: #6c757d; font-size: 8pt; line-height: 1.35;">' . $warranty . '</div>';
}
$html .= '</div>
    </td>
    <td width="45%" style="vertical-align: top;">
        <table width="100%" style="border-collapse: collapse; font-size: 9.5pt; color: #212529;">
            <tr>
                <td style="padding: 5px 0; text-align: left;">ยอดรวมสุทธิ (Subtotal):</td>
                <td style="padding: 5px 0; text-align: right;">' . number_format($subtotal, 2) . ' บาท</td>
            </tr>';
if ($include_vat) {
    $html .= '<tr>
                <td style="padding: 5px 0; text-align: left;">ภาษีมูลค่าเพิ่ม (VAT 7%):</td>
                <td style="padding: 5px 0; text-align: right;">' . number_format($vat_amount, 2) . ' บาท</td>
            </tr>';
}
$html .= '<tr style="border-top: 1.5px solid #84cc16;">
                <td style="padding: 8px 0 0 0; text-align: left;"><strong style="color: #84cc16; font-size: 11pt;">ยอดรวมทั้งสิ้น (Grand Total):</strong></td>
                <td style="padding: 8px 0 0 0; text-align: right;"><strong style="color: #84cc16; font-size: 11pt;">' . number_format($grand_total, 2) . ' บาท</strong></td>
            </tr>
        </table>
    </td>
</tr>
</table>

<!-- Signatures and Stamp Section -->
<div class="signature-container" style="text-align: center; height: 110px;">';
if ($stamp_tag) {
    $html .= '<div style="position: absolute; right: 60px; top: -20px; z-index: 100;">' . $stamp_tag . '</div>';
}
$html .= '<table width="100%" style="font-size: 9.5pt; color: #212529;">
    <tr>
        <td width="45%" style="text-align: center; vertical-align: top; padding-top: 40px;">
            <div class="signature-box">
                ' . htmlspecialchars($sig_left_text) . '<br>';
if ($show_date) {
    $html .= '<span style="font-size: 8.5pt; color: #777;">วันที่ ____/____/____</span>';
}
$html .= '</div>
        </td>
        <td width="10%"></td>
        <td width="45%" style="text-align: center; vertical-align: top; padding-top: 40px;">
            <div class="signature-box">
                ' . htmlspecialchars($sig_right_text) . '<br>';
if ($show_date) {
    $html .= '<span style="font-size: 8.5pt; color: #777;">วันที่ ____/____/____</span>';
}
$html .= '</div>
        </td>
    </tr>
    </table>
</div>

</body>
</html>';

// ---- Add Cache Control Headers ----
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ---- Render PDF ----
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '120');

$defaultConfig = (new \Mpdf\Config\ConfigVariables())->getDefaults();
$fontDirs = $defaultConfig['fontDir'];

$defaultFontConfig = (new \Mpdf\Config\FontVariables())->getDefaults();
$fontData = $defaultFontConfig['fontdata'];

$mpdf = new \Mpdf\Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'margin_top'        => 10,
    'margin_bottom'     => 10,
    'margin_left'       => 12,
    'margin_right'      => 12,
    'fontDir'           => array_merge($fontDirs, [
        __DIR__ . '/assets/fonts',
    ]),
    'fontdata'          => array_merge($fontData, [
        'prompt' => [
            'R' => 'Prompt-Regular.ttf',
            'B' => 'Prompt-Bold.ttf',
        ]
    ]),
    'default_font'      => 'prompt',
    'default_font_size' => 10,
]);

// Set base path so mPDF can find images using relative paths
$mpdf->setBasePath(__DIR__ . '/');

// Split WriteHTML to stay under pcre.backtrack_limit
$mpdf->WriteHTML('', \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

$mpdf->Output($doc['doc_no'] . '.pdf', 'I');
