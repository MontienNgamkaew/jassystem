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
$type_map  = ['Quote' => 'ใบเสนอราคา / Quotation', 'Invoice' => 'ใบส่งของ/ใบแจ้งหนี้<br><span style="font-size:10pt;">Delivery Order / Invoice</span>', 'Receipt' => 'ใบเสร็จรับเงิน / Receipt'];
$doc_title = $type_map[$doc['type']] ?? $doc['type'];

// Signature Labels and Colors
$sig_left_text = 'ผู้สั่งซื้อ';
$sig_right_text = 'ผู้เสนอราคา';
$title_bg_color = '#c8e6c9'; // Green for Quote
if ($doc['type'] === 'Invoice') {
    $sig_left_text = 'ผู้รับของ';
    $sig_right_text = 'ผู้ส่งของ';
    $title_bg_color = '#ffe0b2'; // Orange for Invoice
} else if ($doc['type'] === 'Receipt') {
    $sig_left_text = 'ผู้จ่ายเงิน';
    $sig_right_text = 'ผู้รับเงิน';
    $title_bg_color = '#bbdefb'; // Blue for Receipt
}
$doc_date  = date('d/m/', strtotime($doc['date'])) . (date('Y', strtotime($doc['date'])) + 543);
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

// ---- Image tags (use relative path - mPDF basePath will resolve them) ----
$show_date = !empty($settings['show_date_in_signature']);
$date_row  = $show_date ? '<div style="margin-top:12px;">วันที่ &nbsp;&nbsp;&nbsp;&nbsp;........../ ........../ ..............</div>' : '';
$logo_tag = '';
if (!empty($settings['logo_path']) && file_exists(__DIR__ . '/' . $settings['logo_path'])) {
    $logo_tag = '<img src="' . htmlspecialchars($settings['logo_path']) . '" style="max-height:70px;max-width:130px;">';
}

$stamp_tag = '';
if (!empty($settings['stamp_enabled']) && !empty($settings['stamp_path']) && file_exists(__DIR__ . '/' . $settings['stamp_path'])) {
    $stamp_tag = '<img src="' . htmlspecialchars($settings['stamp_path']) . '" style="max-height:100px;max-width:120px;">';
}

// ---- Build item rows ----
$item_rows = '';
foreach ($items as $i => $it) {
    $bg = ($i % 2 == 0) ? '#fff' : '#f9f9f9';
    $item_rows .= '<tr style="background:'.$bg.';">
        <td style="text-align:center;border:1px solid #ccc;padding:4px;">'.($i+1).'</td>
        <td style="border:1px solid #ccc;padding:4px 8px;">'.htmlspecialchars($it['item_name']).'</td>
        <td style="text-align:center;border:1px solid #ccc;padding:4px;">'.number_format($it['quantity']).'</td>
        <td style="text-align:center;border:1px solid #ccc;padding:4px;"></td>
        <td style="text-align:right;border:1px solid #ccc;padding:4px 8px;">'.number_format($it['price'],2).'</td>
        <td style="text-align:right;border:1px solid #ccc;padding:4px 8px;">'.number_format($it['total'],2).'</td>
    </tr>';
}

// ---- VAT row ----
$vat_val  = $include_vat ? number_format($vat_amount, 2) : '-';
$vat_color = $include_vat ? '#222' : '#999';

// ---- Build full HTML ----
$html = '<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family:garuda,freesans;font-size:10pt;color:#222;">

<!-- HEADER: Company Info + Logo -->
<table width="100%" style="border-bottom:2px solid #333;margin-bottom:5px;padding-bottom:4px;">
<tr>
    <td width="72%" style="vertical-align:top;">
        <div style="font-size:16pt;font-weight:bold;">'.htmlspecialchars($settings['company_name_th']).'</div>
        <div style="font-size:10pt;color:#555;">['.htmlspecialchars($settings['company_name_en'] ?? '').']</div>
        <div style="font-size:9pt;margin-top:2px;">'.htmlspecialchars($settings['company_address']).'</div>
        <div style="font-size:9pt;">โทร : '.htmlspecialchars($settings['company_phone']).'  อีเมล์ : '.htmlspecialchars($settings['company_email']).'</div>
        <div style="font-size:9pt;">เลขประจำตัวผู้เสียภาษีอากร '.htmlspecialchars($settings['company_tax_id']).'</div>
    </td>
    <td width="28%" style="text-align:right;vertical-align:middle;">'.$logo_tag.'</td>
</tr>
</table>

<!-- DOCUMENT TITLE -->
<div style="background:'.$title_bg_color.';text-align:center;font-size:12pt;font-weight:bold;padding:5px 0;margin-bottom:7px;">
    '.$doc_title.'
</div>

<!-- CUSTOMER INFO + DOC META -->
<table width="100%" style="margin-bottom:8px;font-size:9.5pt;">
<tr>
    <td width="58%" style="vertical-align:top;padding-right:10px;">
        <table width="100%">
            <tr><td width="28%" style="color:#666;">ชื่อลูกค้า</td><td style="font-weight:bold;">'.htmlspecialchars($doc['cust_name']).'</td></tr>
            <tr><td style="color:#666;">ที่อยู่</td><td>'.nl2br(htmlspecialchars($doc['cust_address'] ?? '')).'</td></tr>
            <tr><td style="color:#666;">โทร</td><td>'.htmlspecialchars($doc['cust_phone'] ?? '-').'</td></tr>
        </table>
    </td>
    <td width="42%" style="vertical-align:top;">
        <table width="100%">
            <tr><td width="55%" style="color:#666;">เลขที่เอกสาร</td><td style="font-weight:bold;color:#b02020;">'.htmlspecialchars($doc['doc_no']).'</td></tr>
            '.($doc['show_date'] ? '<tr><td style="color:#666;">วันที่</td><td>'.$doc_date.'</td></tr>' : '').'
            <tr><td style="color:#666;">เงื่อนไขการชำระเงิน</td><td style="font-weight:bold;">'.$payment_terms.'</td></tr>
            <tr><td style="color:#666;">เครดิต</td><td></td></tr>
        </table>
    </td>
</tr>
</table>

<!-- ITEMS TABLE -->
<table width="100%" style="border-collapse:collapse;margin-bottom:4px;font-size:9.5pt;">
<thead>
    <tr style="background:#e0e0e0;">
        <th width="6%"  style="text-align:center;border:1px solid #aaa;padding:5px 3px;">ลำดับ</th>
        <th width="44%" style="text-align:center;border:1px solid #aaa;padding:5px 8px;">รายละเอียด</th>
        <th width="8%"  style="text-align:center;border:1px solid #aaa;padding:5px 3px;">จำนวน</th>
        <th width="8%"  style="text-align:center;border:1px solid #aaa;padding:5px 3px;">หน่วย</th>
        <th width="16%" style="text-align:center;border:1px solid #aaa;padding:5px 3px;">ราคา/หน่วย</th>
        <th width="18%" style="text-align:center;border:1px solid #aaa;padding:5px 8px;">จำนวนเงิน</th>
    </tr>
</thead>
<tbody>
'.$item_rows.'
</tbody>
</table>

<!-- TOTALS TABLE (separate from items to avoid rowspan issues) -->
<table width="100%" style="border-collapse:collapse;margin-bottom:6px;font-size:9.5pt;">
<tr>
    <td width="60%" rowspan="4" style="border:1px solid #aaa;padding:8px;vertical-align:middle;">
        <span style="font-weight:bold;">(ตัวอักษร)</span> '.bahtText($grand_total).'
    </td>
    <td width="24%" style="text-align:right;border:1px solid #aaa;padding:5px 8px;font-weight:bold;">รวมเงิน</td>
    <td width="16%" style="text-align:right;border:1px solid #aaa;padding:5px 8px;">'.number_format($subtotal,2).'</td>
</tr>
<tr>
    <td style="text-align:right;border:1px solid #aaa;padding:5px 8px;font-weight:bold;">ภาษีมูลค่าเพิ่ม 7%</td>
    <td style="text-align:right;border:1px solid #aaa;padding:5px 8px;color:'.$vat_color.';">'.$vat_val.'</td>
</tr>
<tr>
    <td style="text-align:right;border:1px solid #aaa;padding:6px 8px;font-weight:bold;background:#f0f0f0;">รวมราคาทั้งสิ้น</td>
    <td style="text-align:right;border:1px solid #aaa;padding:6px 8px;font-weight:bold;font-size:11pt;">'.number_format($grand_total,2).'</td>
</tr>
</table>

<!-- WARRANTY / TERMS -->
<div style="margin-top:6px;font-size:9pt;color:#333;line-height:1.6;">
'.$warranty.'
</div>

<!-- SIGNATURES -->
<table width="100%" style="margin-top:20px;font-size:9.5pt;">
<tr>
    <td width="45%" style="text-align:center;vertical-align:bottom;padding:8px;">
        <div style="font-weight:bold;margin-bottom:4px;">'.$sig_left_text.'</div>
        <div style="margin-bottom:65px;">&nbsp;<br>&nbsp;<br>&nbsp;</div>
        <div style="margin-top:8px;">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
        '.$date_row.'
    </td>
    <td width="10%"></td>
    <td width="45%" style="text-align:center;vertical-align:bottom;padding:8px;">
        <div style="font-weight:bold;margin-bottom:4px;">'.$sig_right_text.'</div>
        <div style="margin-bottom:5px;">&nbsp;<br>&nbsp;</div>
        '.$stamp_tag.'
        <div style="margin-top:8px;">( &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp; )</div>
        '.$date_row.'
    </td>
</tr>
</table>

</body>
</html>';

// ---- Render PDF ----
ini_set('memory_limit', '256M');
ini_set('max_execution_time', '120');

$mpdf = new \Mpdf\Mpdf([
    'mode'              => 'utf-8',
    'format'            => 'A4',
    'margin_top'        => 10,
    'margin_bottom'     => 10,
    'margin_left'       => 12,
    'margin_right'      => 12,
    'default_font'      => 'garuda',
    'default_font_size' => 10,
]);

// Set base path so mPDF can find images using relative paths
$mpdf->setBasePath(__DIR__ . '/');

// Split WriteHTML to stay under pcre.backtrack_limit
$mpdf->WriteHTML('', \Mpdf\HTMLParserMode::HEADER_CSS);
$mpdf->WriteHTML($html, \Mpdf\HTMLParserMode::HTML_BODY);

$mpdf->Output($doc['doc_no'] . '.pdf', 'I');
