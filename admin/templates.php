<?php
declare(strict_types=1);

require_once __DIR__ . '/../src/bootstrap.php';

use Nutronyx\Database;
use Nutronyx\Context;
use Nutronyx\Services\AuthService;
use Nutronyx\Services\TemplateService;

AuthService::requireAuth();

$activePage = 'templates';
$activeWebsite = Context::getWebsite();
$allWebsites = Context::getAllWebsites();
$db = Database::getConnection();

$notice = '';
$error = '';

// Active Tab navigation: 'invoice', 'receipt', 'company', 'preferences'
$currentTab = $_GET['tab'] ?? ($_GET['doc'] ?? 'invoice');
if (!in_array($currentTab, ['invoice', 'receipt', 'company', 'preferences'], true)) {
    $currentTab = 'invoice';
}
$currentDoc = ($currentTab === 'receipt') ? 'receipt' : 'invoice';

// -------------------------------------------------------------------------
// Handle POST Actions
// -------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. Save Full Template from Editor
    if ($action === 'save_template') {
        $saveDocType = $_POST['doc_type'] ?? $currentDoc;
        $postData = $_POST;

        // Handle Custom Logo Upload if present
        if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['logo_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

            if (in_array($ext, $allowed, true) && $file['size'] <= 3 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../assets/images/uploads';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                    $postData['logo_url'] = 'assets/images/uploads/' . $filename;
                }
            }
        }

        // Handle Signature Upload if present
        $sigFile = $_FILES['signature_file'] ?? $_FILES['signatory_file'] ?? null;
        if ($sigFile && $sigFile['error'] === UPLOAD_ERR_OK) {
            $file = $sigFile;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];

            if (in_array($ext, $allowed, true) && $file['size'] <= 3 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../assets/images/uploads';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = 'sig_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                    $postData['signature_url'] = 'assets/images/uploads/' . $filename;
                }
            }
        }

        if (TemplateService::saveTemplate($saveDocType, $postData)) {
            $notice = ucfirst($saveDocType) . ' template settings, branding, and signatory updated successfully.';
            $currentDoc = $saveDocType;
            $currentTab = $saveDocType;
        } else {
            $error = 'Failed to save template preferences.';
        }
    }

    // 2. Set Default / Active Preset (Instant Activation)
    if ($action === 'set_active_preset') {
        $doc = $_POST['doc_type'] ?? 'invoice';
        $preset = $_POST['preset'] ?? 'classic_executive';
        $curr = TemplateService::getTemplate($doc);
        $curr['theme_style'] = $preset;

        // Also ensure preset is active
        $disabled = json_decode($curr['disabled_themes'] ?? '[]', true) ?: [];
        $disabled = array_values(array_diff($disabled, [$preset]));
        $curr['disabled_themes'] = json_encode($disabled);

        if (TemplateService::saveTemplate($doc, $curr)) {
            $notice = "✓ Activated '" . ucwords(str_replace('_', ' ', $preset)) . "' as your default {$doc} template!";
            $currentDoc = $doc;
            $currentTab = $doc;
        }
    }

    // 3. Toggle Preset Active/Inactive Status
    if ($action === 'toggle_preset_status') {
        $doc = $_POST['doc_type'] ?? 'invoice';
        $preset = $_POST['preset'] ?? 'classic_executive';
        $curr = TemplateService::getTemplate($doc);
        $disabled = json_decode($curr['disabled_themes'] ?? '[]', true) ?: [];

        if (in_array($preset, $disabled, true)) {
            $disabled = array_values(array_diff($disabled, [$preset]));
            $notice = "✓ Template '" . ucwords(str_replace('_', ' ', $preset)) . "' is now Active.";
        } else {
            // Cannot deactivate the currently active default template
            if (($curr['theme_style'] ?? 'classic_executive') === $preset) {
                $error = "Cannot deactivate the currently default template. Please activate another template first.";
            } else {
                $disabled[] = $preset;
                $notice = "Template '" . ucwords(str_replace('_', ' ', $preset)) . "' is now Inactive.";
            }
        }

        $curr['disabled_themes'] = json_encode($disabled);
        TemplateService::saveTemplate($doc, $curr);
        $currentTab = $doc;
    }

    // 4. Save Company Information (with Optional GST and Signature Upload)
    if ($action === 'save_company_info') {
        $inv = TemplateService::getTemplate('invoice');
        $rec = TemplateService::getTemplate('receipt');

        $hasGst = !empty($_POST['has_gst']) ? 1 : 0;
        $taxId = trim($_POST['tax_id'] ?? '');

        $updates = [
            'company_name' => trim($_POST['company_name'] ?? $inv['company_name']),
            'brand_name' => trim($_POST['brand_name'] ?? $inv['brand_name']),
            'company_address' => trim($_POST['company_address'] ?? $inv['company_address']),
            'contact_phone' => trim($_POST['contact_phone'] ?? $inv['contact_phone']),
            'contact_email' => trim($_POST['contact_email'] ?? $inv['contact_email']),
            'website_url' => trim($_POST['website_url'] ?? $inv['website_url']),
            'has_gst' => $hasGst,
            'tax_id' => $hasGst ? $taxId : '',
            'show_tax_id' => $hasGst ? 1 : 0,
            'custom_header_title' => $hasGst ? 'TAX INVOICE' : 'INVOICE / BILL OF SUPPLY',
            'signatory_name' => trim($_POST['signatory_name'] ?? $inv['signatory_name']),
            'signatory_title' => trim($_POST['signatory_title'] ?? $inv['signatory_title']),
            'show_signatory' => !empty($_POST['show_signatory']) ? 1 : 0
        ];

        // Handle Logo Upload if present
        if (isset($_FILES['company_logo_file']) && $_FILES['company_logo_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['company_logo_file'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            if (in_array($ext, $allowed, true) && $file['size'] <= 3 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../assets/images/uploads';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = 'logo_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                    $updates['logo_url'] = 'assets/images/uploads/' . $filename;
                }
            }
        }

        // Handle Signature Upload if present
        $sigFile = $_FILES['signatory_file'] ?? $_FILES['signature_file'] ?? null;
        if ($sigFile && $sigFile['error'] === UPLOAD_ERR_OK) {
            $file = $sigFile;
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'svg', 'webp'];
            if (in_array($ext, $allowed, true) && $file['size'] <= 3 * 1024 * 1024) {
                $uploadDir = __DIR__ . '/../assets/images/uploads';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0777, true);
                $filename = 'sig_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
                if (move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
                    $updates['signature_url'] = 'assets/images/uploads/' . $filename;
                }
            }
        }

        // Handle Signature Remove
        if (!empty($_POST['remove_signature'])) {
            $updates['signature_url'] = null;
        }

        TemplateService::saveTemplate('invoice', array_merge($inv, $updates));
        TemplateService::saveTemplate('receipt', array_merge($rec, $updates));
        $notice = "Company information, optional GST preferences, and authorized signature saved successfully.";
        $currentTab = 'company';
    }

    // 5. Save Document Preferences
    if ($action === 'save_preferences') {
        $inv = TemplateService::getTemplate('invoice');
        $rec = TemplateService::getTemplate('receipt');

        $invUpdates = [
            'invoice_prefix' => trim($_POST['invoice_prefix'] ?? 'INV'),
            'theme_style' => $_POST['default_invoice_theme'] ?? $inv['theme_style']
        ];
        $recUpdates = [
            'invoice_prefix' => trim($_POST['receipt_prefix'] ?? 'REC'),
            'receipt_width' => $_POST['default_receipt_width'] ?? '80mm',
            'theme_style' => $_POST['default_receipt_theme'] ?? $rec['theme_style']
        ];

        TemplateService::saveTemplate('invoice', array_merge($inv, $invUpdates));
        TemplateService::saveTemplate('receipt', array_merge($rec, $recUpdates));
        $notice = "Document numbering and layout preferences saved successfully.";
        $currentTab = 'preferences';
    }
}

// Fetch Templates
$invTpl = TemplateService::getTemplate('invoice');
$recTpl = TemplateService::getTemplate('receipt');
$tpl = ($currentDoc === 'receipt') ? $recTpl : $invTpl;

$hasGst = !empty($tpl['has_gst']) && !empty($tpl['tax_id']);
$signatureUrl = !empty($tpl['signature_url']) ? $tpl['signature_url'] : null;

// Real sample order for realistic preview
$sampleOrderStmt = $db->query("SELECT * FROM orders ORDER BY created_at DESC LIMIT 1");
$sampleOrder = $sampleOrderStmt ? $sampleOrderStmt->fetch() : [];

$customerName = !empty($sampleOrder['shipping_name']) ? $sampleOrder['shipping_name'] : (!empty($sampleOrder['customer_name']) ? $sampleOrder['customer_name'] : 'Ravi Kumar');
$customerEmail = !empty($sampleOrder['shipping_email']) ? $sampleOrder['shipping_email'] : (!empty($sampleOrder['customer_email']) ? $sampleOrder['customer_email'] : 'ravi.kumar@gmail.com');
$customerPhone = !empty($sampleOrder['shipping_phone']) ? $sampleOrder['shipping_phone'] : (!empty($sampleOrder['customer_phone']) ? $sampleOrder['customer_phone'] : '+91 98765 43210');
$shippingAddr = trim(($sampleOrder['shipping_address_line1'] ?? '') . ' ' . ($sampleOrder['shipping_city'] ?? '') . ' ' . ($sampleOrder['shipping_state'] ?? '') . ' ' . ($sampleOrder['shipping_postal_code'] ?? ''));
if (empty($shippingAddr)) {
    $shippingAddr = 'D.No. 12-4-56, Lakshmi Nagar, Rajahmundry, Andhra Pradesh - 533101';
}
$orderIdForPreview = $sampleOrder['id'] ?? 'ord_1049';

// Definition of Invoice Presets with Dynamic Status
$activeTheme = $invTpl['theme_style'] ?? 'classic_executive';
$disabledThemes = json_decode($invTpl['disabled_themes'] ?? '[]', true) ?: [];

$invoicePresets = [
    'classic_executive' => [
        'name' => 'Classic Executive',
        'desc' => 'A professional and detailed invoice for all business types.',
        'modified' => 'Nov 10, 2026',
        'color' => '#144D29',
        'bg_style' => 'classic'
    ],
    'botanical_luxury' => [
        'name' => 'Botanical Signature',
        'desc' => 'A premium, nature-inspired invoice with a signature look.',
        'modified' => 'Nov 8, 2026',
        'color' => '#166534',
        'bg_style' => 'botanical'
    ],
    'modern_minimal' => [
        'name' => 'Modern Minimalist',
        'desc' => 'A clean and modern invoice with a minimal layout.',
        'modified' => 'Nov 6, 2026',
        'color' => '#1E293B',
        'bg_style' => 'minimal'
    ],
    'b2b_wholesale' => [
        'name' => 'B2B Wholesale Hub',
        'desc' => 'A structured invoice designed for bulk and wholesale orders.',
        'modified' => 'Oct 28, 2026',
        'color' => '#0F172A',
        'bg_style' => 'b2b'
    ]
];

// Definition of Receipt Presets with Dynamic Status
$activeReceiptTheme = $recTpl['theme_style'] ?? 'thermal_standard';
$disabledReceiptThemes = json_decode($recTpl['disabled_themes'] ?? '[]', true) ?: [];

$receiptPresets = [
    'thermal_standard' => [
        'name' => 'Standard Receipt',
        'desc' => 'Full-width structured customer fulfillment and payment slip.',
        'format' => 'Standard Slip',
        'paper' => 'A4 / Half-Sheet',
        'modified' => 'Nov 10, 2026',
        'width' => '80mm'
    ],
    'thermal_compact' => [
        'name' => 'Compact Receipt',
        'desc' => 'Clean summary receipt optimized for fast counter collection.',
        'format' => 'Compact Slip',
        'paper' => 'A5 Slip',
        'modified' => 'Nov 9, 2026',
        'width' => '80mm'
    ],
    'thermal_58mm' => [
        'name' => 'Thermal Receipt 58mm',
        'desc' => 'Optimized for 2-inch mini portable Bluetooth thermal printers.',
        'format' => 'Thermal 58mm',
        'paper' => '58mm Roll',
        'modified' => 'Nov 7, 2026',
        'width' => '58mm'
    ],
    'thermal_80mm' => [
        'name' => 'Thermal Receipt 80mm',
        'desc' => 'Standard 3-inch point-of-sale thermal counter roll receipt.',
        'format' => 'Thermal 80mm',
        'paper' => '80mm Roll',
        'modified' => 'Oct 29, 2026',
        'width' => '80mm'
    ]
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice &amp; Receipt Templates &mdash; Nutronyx Admin Core</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@600;700&family=Playfair+Display:wght@500;600;700&family=Plus+Jakarta+Sans:wght@300;400;500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20261119_white">
    <style>
        :root {
            --brand-forest: #144D29;
            --brand-forest-dark: #0F3A1F;
            --brand-forest-light: #ECFDF5;
            --border-light: #E5E7EB;
            --text-dark: #111827;
            --text-muted: #6B7280;
        }

        .admin-main-stage {
            background: #F9FAFB;
            min-height: calc(100vh - 68px);
            padding: clamp(20px, 2.5vw, 36px) clamp(24px, 3.5vw, 48px);
        }

        /* Top Header Action Buttons */
        .btn-header-store {
            background: #FFFFFF;
            border: 1px solid #D1D5DB;
            border-radius: 9999px;
            padding: 8px 16px;
            font-size: 13px;
            font-weight: 600;
            color: #1F2937;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            transition: all 0.2s;
        }
        .btn-header-store:hover {
            border-color: #9CA3AF;
            background: #F9FAFB;
        }

        .btn-preview-docs {
            background: #FFFFFF;
            border: 1px solid #D1D5DB;
            border-radius: 9999px;
            padding: 8px 18px;
            font-size: 13px;
            font-weight: 600;
            color: #1F2937;
            display: inline-flex;
            align-items: center;
            gap: 7px;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(0,0,0,0.03);
            transition: all 0.2s;
        }
        .btn-preview-docs:hover {
            background: #F9FAFB;
            border-color: var(--brand-forest);
            color: var(--brand-forest);
        }

        .btn-create-template {
            background: var(--brand-forest);
            border: 1px solid var(--brand-forest);
            border-radius: 9999px;
            padding: 8px 20px;
            font-size: 13px;
            font-weight: 600;
            color: #FFFFFF;
            display: inline-flex;
            align-items: center;
            gap: 6px;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(20, 77, 41, 0.2);
            transition: background 0.2s;
        }
        .btn-create-template:hover {
            background: var(--brand-forest-dark);
        }

        /* 4 Segmented Navigation Tabs */
        .segmented-tabs-bar {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 5px;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 24px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
            overflow-x: auto;
        }

        .seg-tab-btn {
            flex: 1;
            padding: 10px 18px;
            border-radius: 9px;
            font-size: 13.5px;
            font-weight: 600;
            color: #4B5563;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            transition: all 0.2s;
            white-space: nowrap;
            border: none;
            background: transparent;
            cursor: pointer;
        }

        .seg-tab-btn:hover {
            color: #111827;
            background: #F9FAFB;
        }

        .seg-tab-btn.active {
            background: var(--brand-forest);
            color: #FFFFFF;
            box-shadow: 0 1px 3px rgba(20, 77, 41, 0.2);
        }

        .seg-tab-btn.active svg {
            stroke: #FFFFFF;
        }

        /* Workspace 2-Column Grid */
        .workspace-grid {
            display: grid;
            grid-template-columns: 1.22fr 0.98fr;
            gap: 24px;
            align-items: start;
            margin-bottom: 32px;
        }

        @media (max-width: 1120px) {
            .workspace-grid {
                grid-template-columns: 1fr;
            }
        }

        .workspace-left-container {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 24px 26px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
        }

        .workspace-left-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            flex-wrap: wrap;
            gap: 14px;
        }

        /* 2x2 Template Cards Grid */
        .templates-card-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        @media (max-width: 768px) {
            .templates-card-grid {
                grid-template-columns: 1fr;
            }
        }

        .template-preset-card {
            background: #FFFFFF;
            border: 1.5px solid #E5E7EB;
            border-radius: 14px;
            padding: 16px;
            display: flex;
            gap: 14px;
            position: relative;
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .template-preset-card:hover {
            border-color: #D1D5DB;
            box-shadow: 0 4px 12px rgba(0,0,0,0.04);
            transform: translateY(-1px);
        }

        .template-preset-card.selected {
            border-color: #10B981;
            box-shadow: 0 0 0 1.5px #10B981, 0 4px 14px rgba(16, 185, 129, 0.1);
            background: #FCFDFD;
        }

        .preset-radio-indicator {
            position: absolute;
            top: 14px;
            right: 14px;
            width: 22px;
            height: 22px;
            border-radius: 50%;
            border: 1.5px solid #D1D5DB;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #FFFFFF;
            transition: all 0.2s;
        }

        .template-preset-card.selected .preset-radio-indicator {
            background: #10B981;
            border-color: #10B981;
            color: #FFFFFF;
        }

        /* Mini Thumbnail Previews */
        .template-mini-thumb {
            width: 108px;
            height: 146px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 6px;
            padding: 8px 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.04);
            flex-shrink: 0;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            font-size: 6px;
            overflow: hidden;
            position: relative;
        }

        .thumb-botanical-header {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 20px;
            background: linear-gradient(135deg, rgba(20,77,41,0.15) 0%, rgba(34,197,94,0.06) 100%);
            border-bottom: 0.5px solid #E5E7EB;
        }

        .preset-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            flex: 1;
            min-width: 0;
        }

        .preset-title {
            font-size: 15px;
            font-weight: 700;
            color: #111827;
            margin: 0 0 6px 0;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .badge-pill-default {
            background: #FEF3C7;
            color: #92400E;
            font-size: 11px;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .badge-pill-active {
            background: #ECFDF5;
            color: #065F46;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .badge-pill-inactive {
            background: #F3F4F6;
            color: #6B7280;
            font-size: 11px;
            font-weight: 600;
            padding: 2px 7px;
            border-radius: 4px;
        }

        .preset-desc {
            font-size: 12px;
            color: #4B5563;
            line-height: 1.4;
            margin: 6px 0;
        }

        .preset-date {
            font-size: 11px;
            color: #9CA3AF;
            margin-bottom: 10px;
        }

        .preset-actions-row {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .btn-card-action {
            background: #FFFFFF;
            border: 1px solid #D1D5DB;
            border-radius: 6px;
            padding: 5px 10px;
            font-size: 12px;
            font-weight: 600;
            color: #374151;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 4px;
            transition: all 0.15s;
        }
        .btn-card-action:hover {
            background: #F9FAFB;
            border-color: #9CA3AF;
            color: #111827;
        }

        /* Right Realistic Live Document Preview */
        .workspace-right-container {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 16px;
            padding: 22px 24px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.02);
            position: sticky;
            top: 86px;
        }

        .preview-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
        }

        .paper-document-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            padding: 26px 24px;
            box-shadow: 0 4px 18px rgba(0,0,0,0.05);
            font-family: 'Plus Jakarta Sans', sans-serif;
            position: relative;
            overflow: hidden;
            transition: all 0.25s ease;
        }

        /* Leaf watermark removed per design specification */

        /* Bottom Value Proposition Strip */
        .value-props-strip {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
            gap: 16px;
            margin-top: 24px;
        }

        .value-prop-card {
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 12px;
            padding: 16px 18px;
            display: flex;
            align-items: center;
            gap: 14px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.02);
        }

        .value-prop-icon {
            width: 42px;
            height: 42px;
            border-radius: 10px;
            background: #F0FDF4;
            color: var(--brand-forest);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            border: 1px solid #DCFCE7;
        }

        /* Modal Styles */
        .editor-modal-backdrop {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(17, 24, 39, 0.6);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .editor-modal-card {
            background: #FFFFFF;
            border-radius: 18px;
            width: 100%;
            max-width: 1080px;
            max-height: 90vh;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
        }

        .editor-modal-header {
            padding: 18px 24px;
            border-bottom: 1px solid #E5E7EB;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FAFBF9;
        }

        .editor-modal-body {
            display: grid;
            grid-template-columns: 1.1fr 1fr;
            overflow-y: auto;
            flex: 1;
        }

        @media (max-width: 900px) {
            .editor-modal-body {
                grid-template-columns: 1fr;
            }
        }

        .editor-controls-panel {
            padding: 22px 24px;
            border-right: 1px solid #E5E7EB;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
        }

        .editor-preview-panel {
            padding: 22px 24px;
            background: #F9FAFB;
            overflow-y: auto;
            max-height: calc(90vh - 140px);
            display: flex;
            align-items: flex-start;
            justify-content: center;
        }

        .form-section-title {
            font-size: 13.5px;
            font-weight: 700;
            color: #111827;
            margin: 18px 0 10px 0;
            display: flex;
            align-items: center;
            gap: 6px;
            border-bottom: 1px solid #F3F4F6;
            padding-bottom: 6px;
        }
        .form-section-title:first-child {
            margin-top: 0;
        }
    </style>
</head>
<body class="admin-body">

    <?php include __DIR__ . '/includes/header.php'; ?>

    <div class="admin-layout-container">
        <?php include __DIR__ . '/includes/sidebar.php'; ?>

        <main class="admin-main-stage">
            
            <!-- Page Header Matching Mockup -->
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 22px; flex-wrap: wrap; gap: 16px;">
                <div>
                    <h1 style="font-family: 'Playfair Display', Georgia, serif; font-size: 28px; font-weight: 700; color: #111827; margin: 0; letter-spacing: -0.02em;">
                        Invoice &amp; Receipt Templates
                    </h1>
                    <p style="font-size: 13.5px; color: #6B7280; margin: 4px 0 0 0;">
                        Create, customize, and manage professional invoice and receipt templates for your store.
                    </p>
                </div>

                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <!-- Store Selector Dropdown -->
                    <button type="button" class="btn-header-store" onclick="window.toggleStoreDropdown(event)">
                        <span>🏬</span>
                        <span><?= htmlspecialchars($activeWebsite['name'] ?? 'Nutronyx Store') ?></span>
                        <span style="font-size: 10px; color: #6B7280;">▼</span>
                    </button>

                    <!-- Preview Documents Secondary Button -->
                    <button type="button" class="btn-preview-docs" onclick="window.openDocumentPreviewModal()">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                        <span>Preview Documents</span>
                    </button>

                    <!-- Create Template Primary Button -->
                    <button type="button" class="btn-create-template" onclick="window.openTemplateEditor('<?= $currentDoc ?>')">
                        <span style="font-size: 16px; line-height: 1;">+</span>
                        <span>Create Template</span>
                    </button>
                </div>
            </div>

            <!-- Feedback Notifications -->
            <?php if (!empty($notice)): ?>
                <div style="background: #ECFDF5; border: 1px solid #A7F3D0; color: #065F46; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    <span><?= htmlspecialchars($notice) ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div style="background: #FEF2F2; border: 1px solid #FECACA; color: #991B1B; border-radius: 10px; padding: 12px 18px; margin-bottom: 20px; font-weight: 600; font-size: 13.5px; display: flex; align-items: center; gap: 10px;">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <!-- Segmented 4-Tab Navigation Bar -->
            <div class="segmented-tabs-bar">
                <a href="templates.php?tab=invoice" class="seg-tab-btn <?= ($currentTab === 'invoice') ? 'active' : '' ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                    </svg>
                    <span>Invoice Templates</span>
                </a>

                <a href="templates.php?tab=receipt" class="seg-tab-btn <?= ($currentTab === 'receipt') ? 'active' : '' ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"></rect>
                        <line x1="6" y1="8" x2="18" y2="8"></line>
                        <line x1="6" y1="12" x2="18" y2="12"></line>
                        <line x1="6" y1="16" x2="12" y2="16"></line>
                    </svg>
                    <span>Receipt Templates</span>
                </a>

                <a href="templates.php?tab=company" class="seg-tab-btn <?= ($currentTab === 'company') ? 'active' : '' ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                        <line x1="9" y1="6" x2="9" y2="6.01"></line>
                        <line x1="15" y1="6" x2="15" y2="6.01"></line>
                        <line x1="9" y1="10" x2="9" y2="10.01"></line>
                        <line x1="15" y1="10" x2="15" y2="10.01"></line>
                        <line x1="9" y1="14" x2="9" y2="14.01"></line>
                        <line x1="15" y1="14" x2="15" y2="14.01"></line>
                    </svg>
                    <span>Company Information</span>
                </a>

                <a href="templates.php?tab=preferences" class="seg-tab-btn <?= ($currentTab === 'preferences') ? 'active' : '' ?>">
                    <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <line x1="4" y1="21" x2="4" y2="14"></line>
                        <line x1="4" y1="10" x2="4" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="12"></line>
                        <line x1="12" y1="8" x2="12" y2="3"></line>
                        <line x1="20" y1="21" x2="20" y2="16"></line>
                        <line x1="20" y1="12" x2="20" y2="3"></line>
                        <line x1="1" y1="14" x2="7" y2="14"></line>
                        <line x1="9" y1="8" x2="15" y2="8"></line>
                        <line x1="17" y1="16" x2="23" y2="16"></line>
                    </svg>
                    <span>Document Preferences</span>
                </a>
            </div>

            <!-- TAB 1: INVOICE TEMPLATES WORKSPACE -->
            <?php if ($currentTab === 'invoice'): ?>
                <div class="workspace-grid">
                    
                    <!-- Left: Invoice Presets Card Grid -->
                    <div class="workspace-left-container">
                        <div class="workspace-left-header">
                            <div>
                                <h2 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 3px 0;">
                                    Invoice Templates (<?= count($invoicePresets) ?>)
                                </h2>
                                <p style="font-size: 13px; color: #6B7280; margin: 0;">
                                    Design and manage invoice templates for your business. Click any card to activate as default.
                                </p>
                            </div>

                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="position: relative;">
                                    <input type="text" id="tplSearchInput" placeholder="Search templates..." oninput="window.filterTemplateCards(this.value)" style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 7px 12px 7px 30px; font-size: 12.5px; width: 170px; outline: none;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%);">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </div>

                                <select id="tplStatusFilter" onchange="window.filterTemplateStatus(this.value)" style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; padding: 7px 12px; font-size: 12.5px; color: #374151; font-weight: 600; outline: none; cursor: pointer;">
                                    <option value="ALL">All Status</option>
                                    <option value="ACTIVE">Active</option>
                                    <option value="INACTIVE">Inactive</option>
                                </select>
                            </div>
                        </div>

                        <!-- 2x2 Grid of Invoice Templates with Dynamic Activation -->
                        <div class="templates-card-grid" id="invoiceCardsContainer">
                            <?php foreach ($invoicePresets as $key => $p): 
                                $isDefault = ($activeTheme === $key);
                                $isInactive = in_array($key, $disabledThemes, true);
                                $cardStatus = $isInactive ? 'INACTIVE' : 'ACTIVE';
                            ?>
                                <div class="template-preset-card <?= $isDefault ? 'selected' : '' ?>" data-preset-id="<?= $key ?>" data-status="<?= $cardStatus ?>" onclick="window.selectPresetCard('invoice', '<?= $key ?>')">
                                    <div class="preset-radio-indicator">
                                        <?php if ($isDefault): ?>
                                            <span style="font-size: 12px; font-weight: 900;">✓</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Mini Invoice Thumbnail -->
                                    <div class="template-mini-thumb">
                                        <?php if ($key === 'botanical_luxury'): ?>
                                            <div class="thumb-botanical-header"></div>
                                        <?php endif; ?>
                                        <div style="display: flex; justify-content: space-between; align-items: center; border-bottom: 0.5px solid #E5E7EB; padding-bottom: 3px; margin-top: <?= ($key==='botanical_luxury') ? '14px' : '0' ?>;">
                                            <strong style="color: <?= $p['color'] ?>; font-size: 6.5px;">NUX</strong>
                                            <span style="color: #9CA3AF; font-size: 5px;"><?= $hasGst ? 'TAX INV' : 'INVOICE' ?></span>
                                        </div>
                                        <div style="background: #F9FAFB; padding: 3px; border-radius: 2px; margin: 4px 0;">
                                            <div style="height: 3px; width: 45px; background: #E5E7EB; border-radius: 1px; margin-bottom: 2px;"></div>
                                            <div style="height: 2.5px; width: 35px; background: #F3F4F6; border-radius: 1px;"></div>
                                        </div>
                                        <div style="background: <?= $p['color'] ?>; color: #FFF; padding: 2px 3px; border-radius: 2px; font-size: 5px; font-weight: 700;">
                                            <span># ITEM &bull; QTY &bull; AMT</span>
                                        </div>
                                        <div style="font-size: 5px; color: #4B5563; padding: 2px 0;">
                                            <div>ABC+ Blend ... ₹1,498</div>
                                            <div>Moringa ... ₹299</div>
                                        </div>
                                        <div style="border-top: 0.5px solid #E5E7EB; padding-top: 2px; text-align: right; font-weight: 700; color: <?= $p['color'] ?>; font-size: 6px;">
                                            TOTAL: <?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?>
                                        </div>
                                    </div>

                                    <!-- Preset Details -->
                                    <div class="preset-details">
                                        <div>
                                            <div class="preset-title">
                                                <span><?= htmlspecialchars($p['name']) ?></span>
                                            </div>
                                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                                <?php if ($isDefault): ?>
                                                    <span class="badge-pill-default">★ Default</span>
                                                <?php endif; ?>
                                                <?php if (!$isInactive): ?>
                                                    <span class="badge-pill-active"><span style="width: 5px; height: 5px; border-radius: 50%; background: #10B981;"></span> Active</span>
                                                <?php else: ?>
                                                    <span class="badge-pill-inactive">● Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="preset-desc">
                                                <?= htmlspecialchars($p['desc']) ?>
                                            </p>
                                        </div>

                                        <div>
                                            <div class="preset-date">
                                                Last modified: <?= htmlspecialchars($p['modified']) ?>
                                            </div>
                                            <div class="preset-actions-row" onclick="event.stopPropagation()">
                                                <button type="button" class="btn-card-action" onclick="window.previewSpecificPreset('<?= $key ?>')">
                                                    <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
                                                    <span>Preview</span>
                                                </button>
                                                <button type="button" class="btn-card-action" onclick="window.setAsDefault('invoice', '<?= $key ?>')" style="<?= $isDefault ? 'background: #ECFDF5; color: #166534; font-weight: 700; border-color: #A7F3D0;' : '' ?>">
                                                    <span><?= $isDefault ? '★ Active Default' : 'Activate' ?></span>
                                                </button>
                                                <div style="position: relative; display: inline-block;">
                                                    <button type="button" class="btn-card-action" onclick="window.toggleCardMenu(this, event)">
                                                        <span>•••</span>
                                                    </button>
                                                    <div class="card-dropdown-menu" style="display: none; position: absolute; left: 0; bottom: 28px; background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); width: 160px; z-index: 10;">
                                                        <a href="javascript:void(0)" onclick="window.setAsDefault('invoice', '<?= $key ?>')" style="display: block; padding: 8px 12px; font-size: 12px; font-weight: 600; color: #166534; text-decoration: none;">★ Set as Default Active</a>
                                                        <a href="javascript:void(0)" onclick="window.toggleStatusAction('invoice', '<?= $key ?>')" style="display: block; padding: 8px 12px; font-size: 12px; color: #374151; text-decoration: none;">
                                                            <?= $isInactive ? '● Activate Template' : '● Set Inactive' ?>
                                                        </a>
                                                        
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Right: Realistic Document Preview Matching Mockup -->
                    <div class="workspace-right-container">
                        <div class="preview-header-bar">
                            <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin: 0; display: flex; align-items: center; gap: 8px;">
                                <span>Template Preview</span>
                                <span style="font-size: 11.5px; font-weight: normal; color: #6B7280;">(<?= htmlspecialchars($invoicePresets[$activeTheme]['name'] ?? 'Classic Executive') ?>)</span>
                            </h3>
                            <button type="button" onclick="window.openFullscreenPreview()" title="Open Fullscreen View" style="background: none; border: none; cursor: pointer; color: #6B7280; padding: 4px; display: flex; align-items: center;">
                                <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="15 3 21 3 21 9"></polyline><polyline points="9 21 3 21 3 15"></polyline><line x1="21" y1="3" x2="14" y2="10"></line><line x1="3" y1="21" x2="10" y2="14"></line></svg>
                            </button>
                        </div>

                        <!-- Paper Canvas Realistic Card -->
                        <!-- Paper Canvas Realistic Card with Distinct Preset Layouts -->
                        <div class="paper-document-card" id="mainInvoicePreviewCanvas" style="<?= ($activeTheme === 'botanical_luxury') ? 'border-top: 5px solid #166534;' : (($activeTheme === 'modern_minimal') ? 'border: 1px solid #CBD5E1;' : '') ?>">

                            <?php if ($activeTheme === 'b2b_wholesale'): ?>
                                <!-- ================= B2B WHOLESALE LIVE PREVIEW ================= -->
                                <div style="background: #0F172A; color: #FFF; padding: 6px; text-align: center; font-size: 10px; font-weight: 800; letter-spacing: 1px; border-radius: 3px; margin-bottom: 12px;">
                                    <?= $hasGst ? 'COMMERCIAL TAX INVOICE' : 'BILL OF SUPPLY / COMMERCIAL INVOICE' ?>
                                </div>
                                <div style="display: grid; grid-template-columns: 1fr 1fr; border: 1px solid #94A3B8; font-size: 10px; margin-bottom: 12px;">
                                    <div style="padding: 8px; border-right: 1px solid #94A3B8;">
                                        <strong style="color: #0F172A; font-size: 8.5px; text-transform: uppercase;">Consignor (Supplier):</strong>
                                        <div style="font-weight: 800; color: #0F172A; font-size: 11px;"><?= htmlspecialchars($invTpl['company_name']) ?></div>
                                        <div>Whitefield Corridor, Bengaluru</div>
                                        <?php if ($hasGst): ?><div style="font-weight: 700;"><?= htmlspecialchars($invTpl['tax_id']) ?></div><?php endif; ?>
                                    </div>
                                    <div style="padding: 8px;">
                                        <div style="display: flex; justify-content: space-between;">
                                            <strong style="color: #0F172A; font-size: 8.5px; text-transform: uppercase;">Consignee (Buyer):</strong>
                                            <span style="font-weight: 700;">INV-001245</span>
                                        </div>
                                        <div style="font-weight: 800; color: #0F172A; font-size: 11px;"><?= htmlspecialchars($customerName) ?></div>
                                        <div><?= htmlspecialchars($shippingAddr) ?></div>
                                        <div style="color: #64748B;">Date: Nov 10, 2026</div>
                                    </div>
                                </div>
                                <table style="width: 100%; border-collapse: collapse; font-size: 10px; margin-bottom: 10px;">
                                    <thead>
                                        <tr style="background: #F1F5F9; color: #0F172A; font-weight: 700;">
                                            <th style="padding: 4px; border: 1px solid #94A3B8; text-align: center; width: 18px;">#</th>
                                            <th style="padding: 4px; border: 1px solid #94A3B8; text-align: left;">Product</th>
                                            <th style="padding: 4px; border: 1px solid #94A3B8; text-align: center; width: 45px;">HSN</th>
                                            <th style="padding: 4px; border: 1px solid #94A3B8; text-align: center; width: 25px;">Qty</th>
                                            <th style="padding: 4px; border: 1px solid #94A3B8; text-align: right; width: 55px;">Rate</th>
                                            <th style="padding: 4px; border: 1px solid #94A3B8; text-align: right; width: 65px;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: center;">1</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1;"><strong>ABC+ Health Blend (500 g)</strong></td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: center; font-family: monospace;">2106</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: center;">2</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: right;">₹749</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: right; font-weight: 700;">₹1,498</td>
                                        </tr>
                                        <tr>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: center;">2</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1;"><strong>Moringa Powder (200 g)</strong></td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: center; font-family: monospace;">2106</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: center;">1</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: right;">₹299</td>
                                            <td style="padding: 5px; border: 1px solid #CBD5E1; text-align: right; font-weight: 700;">₹299</td>
                                        </tr>
                                    </tbody>
                                </table>
                                <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; font-size: 10px;">
                                    <div style="width: 180px; border: 1px solid #94A3B8; padding: 6px;">
                                        <div style="display: flex; justify-content: space-between;"><span>Taxable Value:</span><span>₹1,797.00</span></div>
                                        <?php if ($hasGst): ?><div style="display: flex; justify-content: space-between; color: #475569;"><span>Total GST (5%):</span><span>₹89.85</span></div><?php endif; ?>
                                        <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 12px; color: #0F172A; border-top: 1px solid #0F172A; padding-top: 4px; margin-top: 4px;">
                                            <span>Invoice Value:</span><span><?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?></span>
                                        </div>
                                    </div>
                                </div>
                                <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #94A3B8; padding-top: 8px;">
                                    <div style="font-size: 9px; color: #64748B; max-width: 250px;">Statutory Declaration: All particulars true &amp; correct.</div>
                                    <div style="text-align: right; width: 140px;">
                                        <?php if (!empty($signatureUrl)): ?>
                                            <img src="../<?= htmlspecialchars($signatureUrl) ?>" alt="Signature" style="max-height: 36px; max-width: 120px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                                        <?php endif; ?>
                                        <div style="height: 24px; border-bottom: 1px solid #0F172A; margin-bottom: 3px;"></div>
                                        <strong style="color: #0F172A; font-size: 10px;"><?= htmlspecialchars($invTpl['signatory_name'] ?? 'Authorized Signatory') ?></strong>
                                    </div>
                                </div>

                            <?php elseif ($activeTheme === 'botanical_luxury'): ?>
                                <!-- ================= BOTANICAL SIGNATURE LIVE PREVIEW (NO LEAF) ================= -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1px solid #BBF7D0; padding-bottom: 12px; margin-bottom: 14px;">
                                    <div>
                                        <div style="font-family: 'Cormorant Garamond', Georgia, serif; font-size: 24px; font-weight: 700; color: #166534; letter-spacing: 0.5px;">
                                            <?= htmlspecialchars(!empty($invTpl['brand_name']) ? $invTpl['brand_name'] : 'Nutronyx Botanicals') ?>
                                        </div>
                                        <div style="font-size: 9.5px; color: #166534; font-family: 'Cormorant Garamond', serif; font-style: italic;">
                                            <?= htmlspecialchars($invTpl['company_name']) ?>
                                        </div>
                                        <?php if ($hasGst && !empty($invTpl['tax_id'])): ?>
                                            <div style="font-size: 9.5px; font-weight: 600; color: #166534; margin-top: 2px;"><?= htmlspecialchars($invTpl['tax_id']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-family: 'Cormorant Garamond', serif; font-size: 16px; font-weight: 700; color: #166534; letter-spacing: 1px;">
                                            <?= $hasGst ? 'TAX INVOICE' : 'INVOICE' ?>
                                        </div>
                                        <div style="font-size: 10px; color: #4B5563;">Invoice No: <strong style="color: #166534;">INV-001245</strong></div>
                                        <div style="font-size: 10px; color: #6B7280;">Date: Nov 10, 2026</div>
                                    </div>
                                </div>

                                <div style="background: #F0FDF4; border: 1px solid #BBF7D0; border-radius: 4px; padding: 10px 12px; margin-bottom: 14px; display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 10.5px;">
                                    <div>
                                        <span style="font-family: 'Cormorant Garamond', serif; font-size: 11px; font-weight: 700; color: #166534; text-transform: uppercase;">Billed To</span>
                                        <div style="font-weight: 700; color: #14221A;"><?= htmlspecialchars($customerName) ?></div>
                                        <div style="color: #4B5563;"><?= htmlspecialchars($customerPhone) ?></div>
                                    </div>
                                    <div>
                                        <span style="font-family: 'Cormorant Garamond', serif; font-size: 11px; font-weight: 700; color: #166534; text-transform: uppercase;">Ship Destination</span>
                                        <div style="color: #4B5563;"><?= htmlspecialchars($shippingAddr) ?></div>
                                    </div>
                                </div>

                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10.5px;">
                                    <thead>
                                        <tr style="border-top: 1.5px solid #166534; border-bottom: 1.5px solid #166534; color: #166534; font-family: 'Cormorant Garamond', serif; font-size: 12px;">
                                            <th style="padding: 5px 6px; text-align: left;">#</th>
                                            <th style="padding: 5px 6px; text-align: left;">Botanical Selection</th>
                                            <th style="padding: 5px 6px; text-align: center;">Qty</th>
                                            <th style="padding: 5px 6px; text-align: right;">Rate</th>
                                            <th style="padding: 5px 6px; text-align: right;">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom: 1px solid #E5E7EB;">
                                            <td style="padding: 6px;">1</td>
                                            <td style="padding: 6px;"><strong>ABC+ Health Blend (500 g)</strong></td>
                                            <td style="padding: 6px; text-align: center;">2</td>
                                            <td style="padding: 6px; text-align: right;">₹749.00</td>
                                            <td style="padding: 6px; text-align: right; font-weight: 700; color: #166534;">₹1,498.00</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #E5E7EB;">
                                            <td style="padding: 6px;">2</td>
                                            <td style="padding: 6px;"><strong>Moringa Powder (200 g)</strong></td>
                                            <td style="padding: 6px; text-align: center;">1</td>
                                            <td style="padding: 6px; text-align: right;">₹299.00</td>
                                            <td style="padding: 6px; text-align: right; font-weight: 700; color: #166534;">₹299.00</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; font-size: 10.5px;">
                                    <div style="width: 170px;">
                                        <div style="display: flex; justify-content: space-between; color: #4B5563;"><span>Subtotal</span><span>₹1,797.00</span></div>
                                        <?php if ($hasGst): ?><div style="display: flex; justify-content: space-between; color: #4B5563;"><span>GST (5%)</span><span>₹89.85</span></div><?php endif; ?>
                                        <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 12.5px; color: #166534; border-top: 1.5px solid #166534; border-bottom: 1.5px solid #166534; padding: 4px 0; margin-top: 4px;">
                                            <span>Total Payable</span><span><?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #BBF7D0; padding-top: 8px;">
                                    <div style="font-size: 9.5px; color: #166534; font-family: 'Cormorant Garamond', serif; font-style: italic;">
                                        Thank you for choosing Nutronyx &mdash; Pure Botanical Quality.
                                    </div>
                                    <div style="text-align: right; width: 140px;">
                                        <?php if (!empty($signatureUrl)): ?>
                                            <img src="../<?= htmlspecialchars($signatureUrl) ?>" alt="Signature" style="max-height: 38px; max-width: 120px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                                        <?php endif; ?>
                                        <div style="height: 24px; border-bottom: 1px dashed #166534; margin-bottom: 3px;"></div>
                                        <strong style="color: #14221A; font-size: 10px;"><?= htmlspecialchars($invTpl['signatory_name'] ?? 'Authorized Signatory') ?></strong>
                                    </div>
                                </div>

                            <?php elseif ($activeTheme === 'modern_minimal'): ?>
                                <!-- ================= MODERN MINIMALIST LIVE PREVIEW ================= -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; border-bottom: 1.5px solid #0F172A; padding-bottom: 12px; margin-bottom: 14px;">
                                    <div>
                                        <div style="font-size: 18px; font-weight: 800; color: #0F172A; letter-spacing: -0.5px;">
                                            <?= htmlspecialchars(!empty($invTpl['brand_name']) ? $invTpl['brand_name'] : 'NUTRONYX') ?>
                                        </div>
                                        <div style="font-size: 9.5px; color: #64748B;"><?= htmlspecialchars($invTpl['company_name']) ?></div>
                                        <?php if ($hasGst && !empty($invTpl['tax_id'])): ?>
                                            <div style="font-size: 9px; font-weight: 700; color: #0F172A; margin-top: 1px;"><?= htmlspecialchars($invTpl['tax_id']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 15px; font-weight: 800; color: #0F172A;"><?= $hasGst ? 'TAX INVOICE' : 'INVOICE' ?></div>
                                        <div style="font-size: 10px; color: #64748B;"># <strong>INV-001245</strong></div>
                                        <div style="font-size: 9.5px; color: #94A3B8;">Nov 10, 2026</div>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; font-size: 10.5px;">
                                    <div>
                                        <div style="font-size: 8.5px; font-weight: 700; color: #94A3B8; text-transform: uppercase;">Billed To</div>
                                        <strong style="color: #0F172A;"><?= htmlspecialchars($customerName) ?></strong>
                                        <div style="color: #475569;"><?= htmlspecialchars($customerPhone) ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 8.5px; font-weight: 700; color: #94A3B8; text-transform: uppercase;">Ship Destination</div>
                                        <div style="color: #475569;"><?= htmlspecialchars($shippingAddr) ?></div>
                                    </div>
                                </div>

                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10.5px;">
                                    <thead>
                                        <tr style="border-bottom: 1.5px solid #0F172A; color: #64748B; font-size: 9px; text-transform: uppercase;">
                                            <th style="padding: 4px 0; text-align: left;">#</th>
                                            <th style="padding: 4px 0; text-align: left;">Product</th>
                                            <th style="padding: 4px 0; text-align: center;">Qty</th>
                                            <th style="padding: 4px 0; text-align: right;">Rate</th>
                                            <th style="padding: 4px 0; text-align: right;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom: 1px solid #F1F5F9;">
                                            <td style="padding: 6px 0; color: #94A3B8;">1</td>
                                            <td style="padding: 6px 0;"><strong style="color: #0F172A;">ABC+ Health Blend (500 g)</strong></td>
                                            <td style="padding: 6px 0; text-align: center;">2</td>
                                            <td style="padding: 6px 0; text-align: right;">₹749.00</td>
                                            <td style="padding: 6px 0; text-align: right; font-weight: 600; color: #0F172A;">₹1,498.00</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #F1F5F9;">
                                            <td style="padding: 6px 0; color: #94A3B8;">2</td>
                                            <td style="padding: 6px 0;"><strong style="color: #0F172A;">Moringa Powder (200 g)</strong></td>
                                            <td style="padding: 6px 0; text-align: center;">1</td>
                                            <td style="padding: 6px 0; text-align: right;">₹299.00</td>
                                            <td style="padding: 6px 0; text-align: right; font-weight: 600; color: #0F172A;">₹299.00</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; font-size: 10.5px;">
                                    <div style="width: 170px;">
                                        <div style="display: flex; justify-content: space-between; color: #64748B;"><span>Subtotal</span><span>₹1,797.00</span></div>
                                        <?php if ($hasGst): ?><div style="display: flex; justify-content: space-between; color: #64748B;"><span>GST (5%)</span><span>₹89.85</span></div><?php endif; ?>
                                        <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 12px; color: #0F172A; border-top: 1.5px solid #0F172A; padding: 4px 0; margin-top: 4px;">
                                            <span>Total Due</span><span><?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #E2E8F0; padding-top: 8px;">
                                    <div style="font-size: 9px; color: #94A3B8;">Payment: Prepaid UPI &bull; Order Ref: ord_1049</div>
                                    <div style="text-align: right; width: 130px;">
                                        <?php if (!empty($signatureUrl)): ?>
                                            <img src="../<?= htmlspecialchars($signatureUrl) ?>" alt="Signature" style="max-height: 36px; max-width: 120px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                                        <?php endif; ?>
                                        <div style="height: 22px; border-bottom: 1px solid #CBD5E1; margin-bottom: 3px;"></div>
                                        <strong style="color: #0F172A; font-size: 9.5px;"><?= htmlspecialchars($invTpl['signatory_name'] ?? 'Authorized Signatory') ?></strong>
                                    </div>
                                </div>

                            <?php else: ?>
                                <!-- ================= CLASSIC EXECUTIVE LIVE PREVIEW ================= -->
                                <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 16px; border-bottom: 2px solid #144D29; padding-bottom: 12px;">
                                    <div>
                                        <div style="display: flex; align-items: center; gap: 6px;">
                                            <strong style="font-size: 18px; font-weight: 800; color: #144D29; letter-spacing: -0.02em;">
                                                <?= htmlspecialchars(!empty($invTpl['brand_name']) ? $invTpl['brand_name'] : 'Nutronyx®') ?>
                                            </strong>
                                        </div>
                                        <div style="font-size: 9.5px; color: #6B7280; margin-top: 2px;">Premium Natural Nutrition</div>
                                        <?php if ($hasGst && !empty($invTpl['tax_id'])): ?>
                                            <div style="font-size: 9.5px; font-weight: 600; color: #166534; margin-top: 2px;"><?= htmlspecialchars($invTpl['tax_id']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div style="text-align: right;">
                                        <div style="font-size: 14px; font-weight: 800; color: #144D29; letter-spacing: 0.5px;">
                                            <?= $hasGst ? 'TAX INVOICE' : 'INVOICE' ?>
                                        </div>
                                        <div style="font-size: 10px; color: #6B7280; margin-top: 2px;">Invoice No: <strong>INV-001245</strong></div>
                                        <div style="font-size: 10px; color: #6B7280;">Date: <strong>Nov 10, 2026</strong></div>
                                    </div>
                                </div>

                                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 14px; margin-bottom: 14px; font-size: 10.5px; background: #F8FAF9; padding: 10px 12px; border-radius: 6px; border: 1px solid #D8DFDB;">
                                    <div>
                                        <div style="font-size: 9px; font-weight: 700; color: #144D29; text-transform: uppercase;">Bill To</div>
                                        <div style="font-weight: 700; color: #111827;"><?= htmlspecialchars($customerName) ?></div>
                                        <div style="color: #4B5563;"><?= htmlspecialchars($customerPhone) ?></div>
                                    </div>
                                    <div>
                                        <div style="font-size: 9px; font-weight: 700; color: #144D29; text-transform: uppercase;">Ship To</div>
                                        <div style="color: #4B5563;"><?= htmlspecialchars($shippingAddr) ?></div>
                                    </div>
                                </div>

                                <table style="width: 100%; border-collapse: collapse; margin-bottom: 12px; font-size: 10.5px;">
                                    <thead>
                                        <tr style="background: #144D29; color: #FFFFFF; font-size: 9.5px;">
                                            <th style="padding: 5px 6px; text-align: left; width: 18px;">#</th>
                                            <th style="padding: 5px 6px; text-align: left;">Product</th>
                                            <th style="padding: 5px 6px; text-align: center; width: 30px;">Qty</th>
                                            <th style="padding: 5px 6px; text-align: right; width: 55px;">Rate</th>
                                            <th style="padding: 5px 6px; text-align: right; width: 65px;">Amount</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr style="border-bottom: 1px solid #E5E7EB;">
                                            <td style="padding: 6px;">1</td>
                                            <td style="padding: 6px; font-weight: 600; color: #111827;">ABC+ Health Blend (500 g)</td>
                                            <td style="padding: 6px; text-align: center;">2</td>
                                            <td style="padding: 6px; text-align: right;">₹749.00</td>
                                            <td style="padding: 6px; text-align: right; font-weight: 600;">₹1,498.00</td>
                                        </tr>
                                        <tr style="border-bottom: 1px solid #E5E7EB;">
                                            <td style="padding: 6px;">2</td>
                                            <td style="padding: 6px; font-weight: 600; color: #111827;">Moringa Powder (200 g)</td>
                                            <td style="padding: 6px; text-align: center;">1</td>
                                            <td style="padding: 6px; text-align: right;">₹299.00</td>
                                            <td style="padding: 6px; text-align: right; font-weight: 600;">₹299.00</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div style="display: flex; justify-content: flex-end; margin-bottom: 12px; font-size: 10.5px;">
                                    <div style="width: 170px;">
                                        <div style="display: flex; justify-content: space-between; color: #4B5563;"><span>Subtotal</span><span>₹1,797.00</span></div>
                                        <?php if ($hasGst): ?><div style="display: flex; justify-content: space-between; color: #4B5563;"><span>GST (5%)</span><span>₹89.85</span></div><?php endif; ?>
                                        <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 12px; color: #144D29; border-top: 1.5px solid #144D29; border-bottom: 1.5px solid #144D29; padding: 4px 0; margin-top: 4px;">
                                            <span>Total Amount</span><span><?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?></span>
                                        </div>
                                    </div>
                                </div>

                                <div style="display: flex; justify-content: space-between; align-items: flex-end; border-top: 1px solid #E5E7EB; padding-top: 8px;">
                                    <div style="font-size: 9.5px; color: #6B7280;">Thank you for your purchase! &bull; support@nutronyx.com</div>
                                    <div style="text-align: right; width: 140px;">
                                        <?php if (!empty($signatureUrl)): ?>
                                            <img src="../<?= htmlspecialchars($signatureUrl) ?>" alt="Signature" style="max-height: 38px; max-width: 120px; object-fit: contain; margin-bottom: -6px; display: block; margin-left: auto;">
                                        <?php endif; ?>
                                        <div style="height: 24px; border-bottom: 1px dashed #111827; margin-bottom: 3px;"></div>
                                        <strong style="color: #111827; font-size: 10px;"><?= htmlspecialchars($invTpl['signatory_name'] ?? 'Authorized Signatory') ?></strong>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            <!-- TAB 2: RECEIPT TEMPLATES WORKSPACE -->
            <?php elseif ($currentTab === 'receipt'): ?>
                <div class="workspace-grid">
                    
                    <div class="workspace-left-container">
                        <div class="workspace-left-header">
                            <div>
                                <h2 style="font-size: 18px; font-weight: 700; color: #111827; margin: 0 0 3px 0;">
                                    Receipt Templates (<?= count($receiptPresets) ?>)
                                </h2>
                                <p style="font-size: 13px; color: #6B7280; margin: 0;">
                                    Design and manage point-of-sale and fulfillment receipts for your store. Click to activate.
                                </p>
                            </div>

                            <div style="display: flex; align-items: center; gap: 10px;">
                                <div style="position: relative;">
                                    <input type="text" placeholder="Search receipts..." oninput="window.filterTemplateCards(this.value)" style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 8px; padding: 7px 12px 7px 30px; font-size: 12.5px; width: 170px; outline: none;">
                                    <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="#9CA3AF" stroke-width="2" style="position: absolute; left: 10px; top: 50%; transform: translateY(-50%);">
                                        <circle cx="11" cy="11" r="8"></circle>
                                        <line x1="21" y1="21" x2="16.65" y2="16.65"></line>
                                    </svg>
                                </div>
                            </div>
                        </div>

                        <!-- 2x2 Grid of Receipt Templates with Dynamic Activation -->
                        <div class="templates-card-grid">
                            <?php foreach ($receiptPresets as $key => $p): 
                                $isDefault = ($activeReceiptTheme === $key);
                                $isInactive = in_array($key, $disabledReceiptThemes, true);
                            ?>
                                <div class="template-preset-card <?= $isDefault ? 'selected' : '' ?>" data-preset-id="<?= $key ?>" onclick="window.selectPresetCard('receipt', '<?= $key ?>')">
                                    <div class="preset-radio-indicator">
                                        <?php if ($isDefault): ?>
                                            <span style="font-size: 12px; font-weight: 900;">✓</span>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Mini Receipt Thumbnail -->
                                    <div class="template-mini-thumb" style="width: 90px; text-align: center; border-style: dashed;">
                                        <div style="font-weight: 800; font-size: 7px; color: #144D29;">NUTRONYX</div>
                                        <div style="font-size: 5px; color: #6B7280;">RECEIPT SLIP</div>
                                        <div style="border-top: 0.5px dashed #9CA3AF; margin: 3px 0;"></div>
                                        <div style="font-size: 5px; text-align: left;">
                                            <div>ABC+ Powder ... ₹1498</div>
                                            <div>Moringa ... ₹299</div>
                                        </div>
                                        <div style="border-top: 0.5px dashed #9CA3AF; margin: 3px 0;"></div>
                                        <div style="font-weight: 800; font-size: 6.5px; color: #111827;">TOTAL ₹1,886</div>
                                    </div>

                                    <div class="preset-details">
                                        <div>
                                            <div class="preset-title">
                                                <span><?= htmlspecialchars($p['name']) ?></span>
                                            </div>
                                            <div style="display: flex; gap: 6px; align-items: center; flex-wrap: wrap;">
                                                <?php if ($isDefault): ?>
                                                    <span class="badge-pill-default">★ Default</span>
                                                <?php endif; ?>
                                                <?php if (!$isInactive): ?>
                                                    <span class="badge-pill-active"><span style="width: 5px; height: 5px; border-radius: 50%; background: #10B981;"></span> <?= htmlspecialchars($p['paper']) ?></span>
                                                <?php else: ?>
                                                    <span class="badge-pill-inactive">● Inactive</span>
                                                <?php endif; ?>
                                            </div>
                                            <p class="preset-desc">
                                                <?= htmlspecialchars($p['desc']) ?>
                                            </p>
                                        </div>

                                        <div>
                                            <div class="preset-date">Format: <?= htmlspecialchars($p['format']) ?></div>
                                            <div class="preset-actions-row" onclick="event.stopPropagation()">
                                                <button type="button" class="btn-card-action" onclick="window.previewReceiptPreset('<?= $key ?>')">
                                                    <span>Preview</span>
                                                </button>
                                                <button type="button" class="btn-card-action" onclick="window.setAsDefault('receipt', '<?= $key ?>')" style="<?= $isDefault ? 'background: #ECFDF5; color: #166534; font-weight: 700; border-color: #A7F3D0;' : '' ?>">
                                                    <span><?= $isDefault ? '★ Active Default' : 'Activate' ?></span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Right: Thermal Receipt Preview -->
                    <div class="workspace-right-container">
                        <div class="preview-header-bar">
                            <h3 style="font-size: 16px; font-weight: 700; color: #111827; margin: 0;">
                                Receipt Preview
                            </h3>
                            <div style="display: flex; gap: 6px;">
                                <button type="button" class="btn-card-action" onclick="window.setReceiptWidth('58mm')" style="font-size: 11px;">58mm</button>
                                <button type="button" class="btn-card-action" onclick="window.setReceiptWidth('80mm')" style="font-size: 11px; font-weight: 700;">80mm</button>
                            </div>
                        </div>

                        <div class="paper-document-card" id="mainReceiptPreviewCanvas" style="<?= ($activeReceiptTheme === 'thermal_standard') ? 'max-width: 440px;' : (($activeReceiptTheme === 'thermal_58mm') ? 'max-width: 240px;' : 'max-width: 320px;') ?> margin: 0 auto; <?= ($activeReceiptTheme === 'thermal_58mm' || $activeReceiptTheme === 'thermal_80mm') ? 'border-style: dashed; font-family: monospace;' : '' ?> text-align: center;">

                            <?php if ($activeReceiptTheme === 'thermal_58mm'): ?>
                                <!-- 58mm Thermal Tape Preview -->
                                <div style="font-weight: 800; font-size: 14px; font-family: monospace;">NUTRONYX</div>
                                <div style="font-size: 9px; color: #4B5563;">Whitefield Store #4, BLR</div>
                                <?php if ($hasGst && !empty($recTpl['tax_id'])): ?>
                                    <div style="font-size: 8.5px;"><?= htmlspecialchars($recTpl['tax_id']) ?></div>
                                <?php endif; ?>
                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                                <div style="display: flex; justify-content: space-between; font-size: 9.5px; font-family: monospace;">
                                    <span>REC-0084</span><span><?= date('d/m/y') ?></span>
                                </div>
                                <div style="font-size: 9.5px; text-align: left; font-family: monospace;">Cust: <?= htmlspecialchars(substr($customerName, 0, 14)) ?></div>
                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                                <div style="font-size: 9.5px; text-align: left; font-family: monospace;">
                                    <div style="display: flex; justify-content: space-between;"><span>2x ABC+ Blend</span><span>₹1,498</span></div>
                                    <div style="display: flex; justify-content: space-between;"><span>1x Moringa 200g</span><span>₹299</span></div>
                                </div>
                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                                <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 11.5px; font-family: monospace;">
                                    <span>TOTAL:</span><span><?= $hasGst ? '₹1,886' : '₹1,797' ?></span>
                                </div>
                                <div style="font-size: 8.5px; color: #4B5563; margin-top: 4px;">PAID: UPI / STORE POS</div>
                                <div style="border-top: 1px dashed #000; margin: 6px 0;"></div>
                                <div style="font-size: 8.5px; color: #6B7280;">Thank you! Nutronyx</div>

                            <?php elseif ($activeReceiptTheme === 'thermal_compact'): ?>
                                <!-- Compact Counter Slip Preview -->
                                <div style="font-weight: 800; font-size: 15px; color: #144D29;">NUTRONYX BOTANICALS</div>
                                <div style="font-size: 10px; color: #6B7280;"><?= htmlspecialchars($recTpl['company_address']) ?></div>
                                <?php if ($hasGst && !empty($recTpl['tax_id'])): ?>
                                    <div style="font-size: 9.5px; font-weight: 700; color: #166534; margin-top: 1px;"><?= htmlspecialchars($recTpl['tax_id']) ?></div>
                                <?php endif; ?>
                                <div style="border-top: 1px solid #111; margin: 8px 0;"></div>
                                <div style="display: flex; justify-content: space-between; font-size: 10.5px; text-align: left;">
                                    <span>Slip: <strong>REC-2026-0084</strong></span>
                                    <span><?= date('d/m/Y') ?></span>
                                </div>
                                <div style="font-size: 10.5px; text-align: left; margin: 2px 0 6px 0;">Cust: <strong><?= htmlspecialchars($customerName) ?></strong></div>
                                <table style="width: 100%; border-collapse: collapse; font-size: 10.5px; margin-bottom: 6px;">
                                    <tr style="border-bottom: 1px solid #111; font-weight: 700;">
                                        <th style="text-align: left; padding: 2px 0;">Item</th>
                                        <th style="text-align: center; width: 30px;">Qty</th>
                                        <th style="text-align: right; width: 55px;">Amt</th>
                                    </tr>
                                    <tr><td style="text-align: left; padding: 3px 0;">ABC+ Health Blend</td><td style="text-align: center;">2</td><td style="text-align: right;">1,498.00</td></tr>
                                    <tr><td style="text-align: left; padding: 3px 0;">Moringa Powder 200g</td><td style="text-align: center;">1</td><td style="text-align: right;">299.00</td></tr>
                                </table>
                                <div style="border-top: 1px solid #111; padding-top: 4px; display: flex; justify-content: space-between; font-weight: 800; font-size: 12.5px; color: #144D29;">
                                    <span>Total:</span><span><?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?></span>
                                </div>
                                <div style="font-size: 9px; color: #6B7280; margin-top: 8px; border-top: 1px dashed #D1D5DB; padding-top: 4px;">Thank you! Valid for counter pickup.</div>

                            <?php elseif ($activeReceiptTheme === 'thermal_standard'): ?>
                                <!-- Standard Fulfillment Slip Preview -->
                                <div style="font-weight: 800; font-size: 16px; color: #144D29; letter-spacing: -0.5px;">NUTRONYX BOTANICALS</div>
                                <div style="font-size: 10.5px; color: #4B5563;"><?= htmlspecialchars($recTpl['company_address']) ?></div>
                                <?php if ($hasGst && !empty($recTpl['tax_id'])): ?>
                                    <div style="font-size: 10px; font-weight: 700; color: #166534; margin-top: 2px;"><?= htmlspecialchars($recTpl['tax_id']) ?></div>
                                <?php endif; ?>
                                <div style="background: #F8FAF9; padding: 8px; border-radius: 4px; margin: 10px 0; font-size: 10.5px; text-align: left; display: flex; justify-content: space-between;">
                                    <div><div>Slip: <strong>REC-2026-0084</strong></div><div>Date: <?= date('d M Y') ?></div></div>
                                    <div style="text-align: right;"><div>Cust: <strong><?= htmlspecialchars($customerName) ?></strong></div><div>Ref: ord_1049</div></div>
                                </div>
                                <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 8px;">
                                    <tr style="background: #144D29; color: #FFF; font-size: 10px;">
                                        <th style="text-align: left; padding: 4px 6px;">Product</th>
                                        <th style="text-align: center; width: 30px;">Qty</th>
                                        <th style="text-align: right; width: 60px;">Amount</th>
                                    </tr>
                                    <tr style="border-bottom: 1px solid #E5E7EB;"><td style="text-align: left; padding: 5px 6px;">ABC+ Health Blend</td><td style="text-align: center;">2</td><td style="text-align: right; font-weight: 700; color: #144D29;">₹1,498.00</td></tr>
                                    <tr style="border-bottom: 1px solid #E5E7EB;"><td style="text-align: left; padding: 5px 6px;">Moringa Powder 200g</td><td style="text-align: center;">1</td><td style="text-align: right; font-weight: 700; color: #144D29;">₹299.00</td></tr>
                                </table>
                                <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 13px; color: #144D29; border-top: 1.5px solid #144D29; padding-top: 6px; margin-top: 6px;">
                                    <span>Total Paid:</span><span><?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?></span>
                                </div>
                                <div style="text-align: center; font-size: 9.5px; color: #6B7280; margin-top: 10px; border-top: 1px solid #E5E7EB; padding-top: 6px;">Thank you for shopping with Nutronyx!</div>

                            <?php else: ?>
                                <!-- 80mm Standard POS Thermal Roll Preview -->
                                <div style="font-weight: 800; font-size: 16px; color: #144D29; letter-spacing: 0.5px;">* NUTRONYX *</div>
                                <div style="font-size: 10px; color: #4B5563;">Nutronyx Botanicals Pvt. Ltd.</div>
                                <div style="font-size: 9.5px; color: #6B7280;">Whitefield Store #4, Bengaluru</div>
                                <?php if ($hasGst && !empty($recTpl['tax_id'])): ?>
                                    <div style="font-size: 9.5px; font-weight: 600; color: #374151; margin-top: 2px;"><?= htmlspecialchars($recTpl['tax_id']) ?></div>
                                <?php endif; ?>

                                <div style="border-top: 1px dashed #9CA3AF; border-bottom: 1px dashed #9CA3AF; padding: 6px 0; margin: 10px 0; font-size: 10.5px; text-align: left;">
                                    <div style="display: flex; justify-content: space-between;">
                                        <span>Slip: <strong>REC-2026-0084</strong></span>
                                        <span><?= date('d/m/Y H:i') ?></span>
                                    </div>
                                    <div>Cust: <?= htmlspecialchars($customerName) ?></div>
                                </div>

                                <table style="width: 100%; border-collapse: collapse; font-size: 11px; margin-bottom: 8px;">
                                    <thead>
                                        <tr style="border-bottom: 1px solid #000; font-weight: 700;">
                                            <th style="text-align: left; padding: 3px 0;">ITEM</th>
                                            <th style="text-align: center; padding: 3px 0;">QTY</th>
                                            <th style="text-align: right; padding: 3px 0;">AMT</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="text-align: left; padding: 4px 0;">ABC+ Health Blend</td>
                                            <td style="text-align: center;">2</td>
                                            <td style="text-align: right;">1,498.00</td>
                                        </tr>
                                        <tr>
                                            <td style="text-align: left; padding: 4px 0;">Moringa Powder 200g</td>
                                            <td style="text-align: center;">1</td>
                                            <td style="text-align: right;">299.00</td>
                                        </tr>
                                    </tbody>
                                </table>

                                <div style="border-top: 1px dashed #9CA3AF; padding-top: 6px; margin-bottom: 10px; font-size: 11px;">
                                    <div style="display: flex; justify-content: space-between; font-weight: 800; font-size: 13.5px; color: #144D29;">
                                        <span>TOTAL:</span>
                                        <span><?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?></span>
                                    </div>
                                    <div style="font-size: 10px; color: #4B5563; text-align: right;">PAID VIA: UPI / STORE POS</div>
                                </div>

                                <div style="text-align: center; font-size: 10px; color: #6B7280; border-top: 1px dashed #9CA3AF; padding-top: 8px;">
                                    Thank you for visiting Nutronyx!<br>
                                    Keep this slip for order pickup or return.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                </div>

            <!-- TAB 3: COMPANY INFORMATION WORKSPACE (OPTIONAL GST & SIGNATURE UPLOAD) -->
            <?php elseif ($currentTab === 'company'): ?>
                <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); max-width: 920px; margin: 0 auto 32px auto;">
                    <div style="border-bottom: 1px solid #E5E7EB; padding-bottom: 16px; margin-bottom: 24px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 4px 0;">
                            Company Information &amp; Legal Identity
                        </h2>
                        <p style="font-size: 13px; color: #6B7280; margin: 0;">
                            Configure legal company details, tax identifiers, and registered contacts appearing on all customer invoices and receipts.
                        </p>
                    </div>

                    <form method="POST" action="templates.php?tab=company" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="save_company_info">

                        <!-- Business Identity -->
                        <h3 class="form-section-title">
                            <span>🏢</span> Business Identity
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Legal Entity Name *</label>
                                <input type="text" name="company_name" value="<?= htmlspecialchars($invTpl['company_name']) ?>" required class="form-control" style="border-radius: 8px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Brand / Trading Name *</label>
                                <input type="text" name="brand_name" value="<?= htmlspecialchars($invTpl['brand_name']) ?>" required class="form-control" style="border-radius: 8px;">
                            </div>
                        </div>

                        <!-- GST Configuration (Optional Support) -->
                        <div style="background: #F8FAF9; border: 1.5px solid #E2EBE5; border-radius: 12px; padding: 18px; margin-bottom: 20px;">
                            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 12px;">
                                <div>
                                    <div style="font-size: 13.5px; font-weight: 700; color: #111827;">Goods &amp; Services Tax (GST) Status</div>
                                    <div style="font-size: 12px; color: #6B7280;">Some small businesses or composition schemes do not require GST. Toggle based on your business status.</div>
                                </div>
                                <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; font-size: 13px; font-weight: 700; color: #144D29;">
                                    <input type="checkbox" name="has_gst" id="chkHasGst" value="1" <?= !empty($invTpl['has_gst']) ? 'checked' : '' ?> onchange="window.toggleGstField(this.checked)" style="accent-color: #144D29; width: 17px; height: 17px;">
                                    <span>GST Registered</span>
                                </label>
                            </div>

                            <div id="gstInputContainer" style="display: <?= !empty($invTpl['has_gst']) ? 'block' : 'none' ?>;">
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">GSTIN / Tax ID Number (Optional)</label>
                                <input type="text" name="tax_id" id="inpTaxId" value="<?= htmlspecialchars($invTpl['tax_id'] ?? '') ?>" placeholder="e.g. GSTIN: 29ABCDE1234F1Z5" class="form-control" style="border-radius: 8px; font-family: monospace;">
                                <small style="font-size: 11px; color: #6B7280;">When enabled, GSTIN and line-item tax breakdowns appear on formal invoices.</small>
                            </div>
                            <div id="noGstMessage" style="display: <?= empty($invTpl['has_gst']) ? 'block' : 'none' ?>; font-size: 12px; color: #166534; background: #ECFDF5; padding: 8px 12px; border-radius: 6px;">
                                ✓ <strong>Non-GST Mode Active:</strong> Invoices will be issued as clean Bill of Supply / Invoices without GST breakdown.
                            </div>
                        </div>

                        <!-- Brand Logo Configuration -->
                        <div style="background: #F9FAFB; border: 1px solid #E5E7EB; border-radius: 12px; padding: 18px; margin-bottom: 24px;">
                            <div style="font-weight: 700; font-size: 13.5px; color: #111827; margin-bottom: 10px;">Company Brand Logo</div>
                            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                <div style="background: #FFFFFF; border: 1px solid #D1D5DB; border-radius: 8px; padding: 10px 18px; height: 60px; display: flex; align-items: center; justify-content: center;">
                                    <img src="../<?= htmlspecialchars($invTpl['logo_url']) ?>" alt="Logo" style="max-height: 44px; max-width: 140px; object-fit: contain;">
                                </div>
                                <div style="flex: 1;">
                                    <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Upload New Logo (PNG, SVG, WEBP &bull; Max 3MB)</label>
                                    <input type="file" name="company_logo_file" accept="image/*" class="form-control" style="font-size: 12px; padding: 6px 10px;">
                                </div>
                            </div>
                        </div>

                        <!-- Contact Information -->
                        <h3 class="form-section-title">
                            <span>📍</span> Contact &amp; Registered Location
                        </h3>
                        <div style="margin-bottom: 16px;">
                            <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Registered Office Address</label>
                            <textarea name="company_address" rows="2" class="form-control" style="border-radius: 8px;"><?= htmlspecialchars($invTpl['company_address']) ?></textarea>
                        </div>

                        <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Support Phone</label>
                                <input type="text" name="contact_phone" value="<?= htmlspecialchars($invTpl['contact_phone']) ?>" class="form-control" style="border-radius: 8px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Support Email</label>
                                <input type="email" name="contact_email" value="<?= htmlspecialchars($invTpl['contact_email']) ?>" class="form-control" style="border-radius: 8px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Official Website</label>
                                <input type="url" name="website_url" value="<?= htmlspecialchars($invTpl['website_url']) ?>" class="form-control" style="border-radius: 8px;">
                            </div>
                        </div>

                        <!-- Authorized Signatory & SIGNATURE UPLOAD -->
                        <h3 class="form-section-title">
                            <span>✍️</span> Authorized Signatory &amp; Digital Signature
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 18px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Signatory Full Name</label>
                                <input type="text" name="signatory_name" value="<?= htmlspecialchars($invTpl['signatory_name'] ?? 'Authorized Signatory') ?>" class="form-control" style="border-radius: 8px;">
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Designation / Title</label>
                                <input type="text" name="signatory_title" value="<?= htmlspecialchars($invTpl['signatory_title'] ?? 'Compliance & Accounts Officer') ?>" class="form-control" style="border-radius: 8px;">
                            </div>
                        </div>

                        <!-- Digital Signature File Upload -->
                        <div style="background: #F9FAFB; border: 1.5px solid #E5E7EB; border-radius: 12px; padding: 18px; margin-bottom: 18px;">
                            <div style="font-weight: 700; font-size: 13.5px; color: #111827; margin-bottom: 4px;">Upload Authorized Signature Image (Optional)</div>
                            <p style="font-size: 12px; color: #6B7280; margin: 0 0 12px 0;">
                                Upload a scanned PNG with transparent background, JPG, or SVG to appear right above the signatory name on customer invoices.
                            </p>
                            
                            <div style="display: flex; gap: 20px; align-items: center; flex-wrap: wrap;">
                                <div style="background: #FFFFFF; border: 1px dashed #D1D5DB; border-radius: 8px; padding: 8px 16px; height: 60px; min-width: 140px; display: flex; align-items: center; justify-content: center;">
                                    <?php if (!empty($signatureUrl)): ?>
                                        <img src="../<?= htmlspecialchars($signatureUrl) ?>" alt="Signature" id="companySigPreview" style="max-height: 48px; max-width: 130px; object-fit: contain;">
                                    <?php else: ?>
                                        <span id="noSigText" style="font-size: 11px; color: #9CA3AF; font-style: italic;">No Signature Uploaded</span>
                                    <?php endif; ?>
                                </div>
                                <div style="flex: 1;">
                                    <input type="file" name="signatory_file" accept="image/*" class="form-control" style="font-size: 12px; padding: 6px 10px;">
                                    <?php if (!empty($signatureUrl)): ?>
                                        <div style="margin-top: 6px;">
                                            <label style="font-size: 11.5px; color: #991B1B; cursor: pointer; display: inline-flex; align-items: center; gap: 4px;">
                                                <input type="checkbox" name="remove_signature" value="1">
                                                <span>Remove current signature</span>
                                            </label>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 24px;">
                            <input type="checkbox" id="chkShowSig" name="show_signatory" value="1" <?= !empty($invTpl['show_signatory']) ? 'checked' : '' ?> style="accent-color: #144D29; width: 16px; height: 16px;">
                            <label for="chkShowSig" style="font-size: 13px; font-weight: 600; color: #1F2937; cursor: pointer;">
                                Display Authorized Signatory block on formal invoices
                            </label>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-action" style="background: var(--brand-forest); color: #FFF; padding: 11px 26px; border-radius: 8px; font-weight: 700;">
                                💾 Save Company Information
                            </button>
                        </div>
                    </form>
                </div>

            <!-- TAB 4: DOCUMENT PREFERENCES WORKSPACE -->
            <?php elseif ($currentTab === 'preferences'): ?>
                <div style="background: #FFFFFF; border: 1px solid #E5E7EB; border-radius: 16px; padding: 28px; box-shadow: 0 1px 3px rgba(0,0,0,0.02); max-width: 920px; margin: 0 auto 32px auto;">
                    <div style="border-bottom: 1px solid #E5E7EB; padding-bottom: 16px; margin-bottom: 24px;">
                        <h2 style="font-size: 20px; font-weight: 700; color: #111827; margin: 0 0 4px 0;">
                            Document Preferences &amp; Numbering
                        </h2>
                        <p style="font-size: 13px; color: #6B7280; margin: 0;">
                            Control sequential numbering schemes, paper sizes, and default document formats across your store.
                        </p>
                    </div>

                    <form method="POST" action="templates.php?tab=preferences">
                        <input type="hidden" name="action" value="save_preferences">

                        <h3 class="form-section-title">
                            <span>🔢</span> Numbering Configuration
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Invoice Prefix</label>
                                <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($invTpl['invoice_prefix']) ?>" class="form-control" style="border-radius: 8px; font-family: monospace; font-weight: 600;">
                                <small style="color: #6B7280; font-size: 11px;">Example output: <strong><?= htmlspecialchars($invTpl['invoice_prefix']) ?>-2026-001245</strong></small>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Receipt Slip Prefix</label>
                                <input type="text" name="receipt_prefix" value="<?= htmlspecialchars($recTpl['invoice_prefix']) ?>" class="form-control" style="border-radius: 8px; font-family: monospace; font-weight: 600;">
                                <small style="color: #6B7280; font-size: 11px;">Example output: <strong><?= htmlspecialchars($recTpl['invoice_prefix']) ?>-2026-00084</strong></small>
                            </div>
                        </div>

                        <h3 class="form-section-title">
                            <span>📐</span> Formatting &amp; Dimensions
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 16px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Default Invoice Paper Size</label>
                                <select name="default_paper_size" class="form-control" style="border-radius: 8px;">
                                    <option value="A4" selected>A4 (210 × 297 mm) &mdash; Standard International</option>
                                    <option value="LETTER">US Letter (8.5 × 11 in)</option>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Default Thermal Receipt Width</label>
                                <select name="default_receipt_width" class="form-control" style="border-radius: 8px;">
                                    <option value="80mm" <?= (($recTpl['receipt_width'] ?? '80mm') === '80mm') ? 'selected' : '' ?>>80mm (3-Inch POS Counter Roll)</option>
                                    <option value="58mm" <?= (($recTpl['receipt_width'] ?? '') === '58mm') ? 'selected' : '' ?>>58mm (2-Inch Mini Thermal Printer)</option>
                                </select>
                            </div>
                        </div>

                        <h3 class="form-section-title">
                            <span>⭐</span> Default Active Presets
                        </h3>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 24px;">
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Default Invoice Preset</label>
                                <select name="default_invoice_theme" class="form-control" style="border-radius: 8px;">
                                    <?php foreach ($invoicePresets as $k => $p): ?>
                                        <option value="<?= $k ?>" <?= ($invTpl['theme_style'] === $k) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display: block; font-size: 12.5px; font-weight: 600; color: #374151; margin-bottom: 6px;">Default Receipt Preset</label>
                                <select name="default_receipt_theme" class="form-control" style="border-radius: 8px;">
                                    <?php foreach ($receiptPresets as $k => $p): ?>
                                        <option value="<?= $k ?>" <?= (($recTpl['theme_style'] ?? 'thermal_standard') === $k) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($p['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="display: flex; justify-content: flex-end;">
                            <button type="submit" class="btn-action" style="background: var(--brand-forest); color: #FFF; padding: 11px 26px; border-radius: 8px; font-weight: 700;">
                                💾 Save Preferences
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>

            <!-- Bottom Value Proposition Strip Matching Mockup -->
            <div class="value-props-strip">
                <div class="value-prop-card">
                    <div class="value-prop-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <circle cx="12" cy="12" r="10"></circle>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 13.5px; color: #111827;">Custom Branding</div>
                        <div style="font-size: 12px; color: #6B7280;">Add your logo, colors and business details</div>
                    </div>
                </div>

                <div class="value-prop-card">
                    <div class="value-prop-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="3" width="18" height="18" rx="2"></rect>
                            <line x1="3" y1="9" x2="21" y2="9"></line>
                            <line x1="9" y1="21" x2="9" y2="9"></line>
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 13.5px; color: #111827;">Flexible Layouts</div>
                        <div style="font-size: 12px; color: #6B7280;">Choose from professional templates</div>
                    </div>
                </div>

                <div class="value-prop-card">
                    <div class="value-prop-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="4" y="2" width="16" height="20" rx="2"></rect>
                            <line x1="8" y1="6" x2="16" y2="6"></line>
                            <line x1="8" y1="10" x2="16" y2="10"></line>
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 13.5px; color: #111827;">Multi-Format Support</div>
                        <div style="font-size: 12px; color: #6B7280;">A4, Thermal (58mm / 80mm) and more</div>
                    </div>
                </div>

                <div class="value-prop-card">
                    <div class="value-prop-icon">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                        </svg>
                    </div>
                    <div>
                        <div style="font-weight: 700; font-size: 13.5px; color: #111827;">Auto Numbering</div>
                        <div style="font-size: 12px; color: #6B7280;">Custom prefixes and sequence control</div>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Hidden Forms to activate preset or toggle status via 1-click -->
    <form method="POST" action="templates.php?tab=<?= $currentTab ?>" id="setActivePresetForm" style="display: none;">
        <input type="hidden" name="action" value="set_active_preset">
        <input type="hidden" name="doc_type" id="postDocType" value="<?= $currentDoc ?>">
        <input type="hidden" name="preset" id="postPresetId" value="">
    </form>

    <form method="POST" action="templates.php?tab=<?= $currentTab ?>" id="toggleStatusForm" style="display: none;">
        <input type="hidden" name="action" value="toggle_preset_status">
        <input type="hidden" name="doc_type" id="toggleDocType" value="<?= $currentDoc ?>">
        <input type="hidden" name="preset" id="togglePresetId" value="">
    </form>

    <!-- Interactive Template Editor Split-Screen Modal -->
    <div class="editor-modal-backdrop" id="templateEditorModal">
        <div class="editor-modal-card">
            <div class="editor-modal-header">
                <div>
                    <h3 style="font-size: 17px; font-weight: 700; color: #111827; margin: 0;" id="editorModalTitle">
                        Customize Template
                    </h3>
                    <p style="font-size: 12.5px; color: #6B7280; margin: 2px 0 0 0;">
                        Fine-tune typography, branding colors, header notes, digital signatures, and visible fields.
                    </p>
                </div>
                <button type="button" onclick="window.closeTemplateEditor()" style="background: none; border: none; font-size: 24px; color: #6B7280; cursor: pointer; line-height: 1;">&times;</button>
            </div>

            <form method="POST" action="templates.php?tab=<?= $currentTab ?>" enctype="multipart/form-data" id="editorMainForm" style="display: flex; flex-direction: column; flex: 1; overflow: hidden; margin: 0;">
                <input type="hidden" name="action" value="save_template">
                <input type="hidden" name="doc_type" id="editorDocType" value="<?= $currentDoc ?>">
                <input type="hidden" name="theme_style" id="editorThemeStyle" value="<?= htmlspecialchars($tpl['theme_style'] ?? 'classic_executive') ?>">

                <div class="editor-modal-body">
                    <!-- Left Config Settings -->
                    <div class="editor-controls-panel">
                        <h4 class="form-section-title">🎨 Branding &amp; Colors</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 14px;">
                            <div>
                                <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Primary Brand Color</label>
                                <input type="color" name="primary_color" id="editorPrimaryColor" value="<?= htmlspecialchars($tpl['primary_color'] ?? '#144D29') ?>" class="form-control" style="height: 38px; padding: 3px;" onchange="window.syncEditorPreview()">
                            </div>
                            <div>
                                <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Accent Color</label>
                                <input type="color" name="accent_color" id="editorAccentColor" value="<?= htmlspecialchars($tpl['accent_color'] ?? '#2B6E4E') ?>" class="form-control" style="height: 38px; padding: 3px;">
                            </div>
                        </div>

                        <div style="margin-bottom: 14px;">
                            <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Header Title Text</label>
                            <input type="text" name="custom_header_title" id="editorHeaderTitle" value="<?= htmlspecialchars($tpl['custom_header_title'] ?? ($hasGst ? 'TAX INVOICE' : 'INVOICE')) ?>" class="form-control" oninput="window.syncEditorPreview()">
                        </div>

                        <h4 class="form-section-title">👁 Field Visibility</h4>
                        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px; margin-bottom: 16px; font-size: 12.5px;">
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_logo" value="1" <?= !empty($tpl['show_logo']) ? 'checked' : '' ?> style="accent-color: #144D29;">
                                <span>Store Logo</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_tax_id" value="1" <?= !empty($tpl['show_tax_id']) ? 'checked' : '' ?> style="accent-color: #144D29;">
                                <span>Tax / GST Details</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_carrier_info" value="1" <?= !empty($tpl['show_carrier_info']) ? 'checked' : '' ?> style="accent-color: #144D29;">
                                <span>Courier &amp; Tracking</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_payment_method" value="1" <?= !empty($tpl['show_payment_method']) ? 'checked' : '' ?> style="accent-color: #144D29;">
                                <span>Payment Mode</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_terms_and_conditions" value="1" <?= !empty($tpl['show_terms_and_conditions']) ? 'checked' : '' ?> style="accent-color: #144D29;">
                                <span>Terms &amp; Notes</span>
                            </label>
                            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                <input type="checkbox" name="show_signatory" value="1" <?= !empty($tpl['show_signatory']) ? 'checked' : '' ?> style="accent-color: #144D29;">
                                <span>Signatory Block</span>
                            </label>
                        </div>

                        <h4 class="form-section-title">✍️ Authorized Signature Image</h4>
                        <div style="margin-bottom: 14px;">
                            <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Upload Signature (PNG, SVG &bull; Optional)</label>
                            <input type="file" name="signature_file" accept="image/*" class="form-control" style="font-size: 12px;">
                        </div>

                        <h4 class="form-section-title">📝 Footer &amp; Terms</h4>
                        <div style="margin-bottom: 12px;">
                            <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Footer Note</label>
                            <input type="text" name="custom_footer_note" value="<?= htmlspecialchars($tpl['custom_footer_note'] ?? 'Thank you for choosing Nutronyx.') ?>" class="form-control">
                        </div>
                        <div>
                            <label style="font-size: 12px; font-weight: 600; color: #374151; display: block; margin-bottom: 4px;">Standard Terms &amp; Policies</label>
                            <textarea name="terms_text" rows="3" class="form-control"><?= htmlspecialchars($tpl['terms_text'] ?? '') ?></textarea>
                        </div>
                    </div>

                    <!-- Right Live Mini Preview in Modal -->
                    <div class="editor-preview-panel">
                        <div class="paper-document-card" style="width: 100%; max-width: 440px; font-size: 11px;">
                            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                                <strong style="color: #144D29; font-size: 16px;">Nutronyx&reg;</strong>
                                <div style="text-align: right;">
                                    <div style="font-size: 13px; font-weight: 800; color: #144D29;" id="modalPreviewTitle"><?= $hasGst ? 'TAX INVOICE' : 'INVOICE' ?></div>
                                    <div style="color: #6B7280; font-size: 10px;">INV-2026-001245</div>
                                </div>
                            </div>
                            <div style="background: #144D29; color: #FFF; padding: 4px 6px; font-size: 10px; font-weight: 700; margin-bottom: 8px;" id="modalPreviewHeader">
                                Line Items &amp; Calculations
                            </div>
                            <div style="font-size: 10px; color: #374151; margin-bottom: 12px;">
                                <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                                    <span>ABC+ Health Blend (500g) x2</span>
                                    <span>₹1,498.00</span>
                                </div>
                                <div style="display: flex; justify-content: space-between; padding: 2px 0;">
                                    <span>Moringa Powder (200g) x1</span>
                                    <span>₹299.00</span>
                                </div>
                            </div>
                            <div style="border-top: 1px solid #E5E7EB; padding-top: 6px; text-align: right; font-weight: 800; font-size: 12.5px; color: #144D29;">
                                Total Amount: <?= $hasGst ? '₹1,886.85' : '₹1,797.00' ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div style="padding: 14px 24px; border-top: 1px solid #E5E7EB; background: #FAFBF9; display: flex; justify-content: space-between; align-items: center;">
                    <button type="button" class="btn-card-action" onclick="window.closeTemplateEditor()">Cancel</button>
                    <button type="submit" class="btn-action" style="background: var(--brand-forest); color: #FFF; padding: 9px 22px; border-radius: 8px; font-weight: 700;">
                        💾 Save Template
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Document Preview & Direct Print Modal -->
    <div class="editor-modal-backdrop" id="documentPreviewModal">
        <div class="editor-modal-card" style="max-width: 820px;">
            <div class="editor-modal-header">
                <div>
                    <h3 style="font-size: 17px; font-weight: 700; color: #111827; margin: 0;">
                        Document Live Preview &amp; Export
                    </h3>
                    <p style="font-size: 12px; color: #6B7280; margin: 2px 0 0 0;">
                        Print or download high-fidelity PDF documents using real customer order data.
                    </p>
                </div>
                <button type="button" onclick="window.closeDocumentPreviewModal()" style="background: none; border: none; font-size: 24px; color: #6B7280; cursor: pointer;">&times;</button>
            </div>
            <div style="padding: 24px; text-align: center; background: #F9FAFB;">
                <div style="display: flex; justify-content: center; gap: 14px; margin-bottom: 20px;">
                    <a href="invoice.php?id=<?= urlencode($orderIdForPreview) ?>" target="_blank" class="btn-create-template" style="text-decoration: none;">
                        <span>📄 View Full A4 Invoice</span>
                    </a>
                    <a href="receipt.php?id=<?= urlencode($orderIdForPreview) ?>" target="_blank" class="btn-header-store" style="text-decoration: none;">
                        <span>🧾 View POS Receipt</span>
                    </a>
                </div>
                <div style="font-size: 12px; color: #6B7280;">
                    Documents open in a dedicated browser tab formatted with print stylesheets (Auto A4 and Thermal 80mm).
                </div>
            </div>
        </div>
    </div>

    <!-- Client-Side Navigation, Filtering, and Activation Scripts -->
    <script>
    // 1. One-click Preset Selection & Activation
    window.selectPresetCard = function(docType, presetId) {
        document.getElementById('postDocType').value = docType;
        document.getElementById('postPresetId').value = presetId;
        document.getElementById('setActivePresetForm').submit();
    };

    window.setAsDefault = function(docType, presetId) {
        window.selectPresetCard(docType, presetId);
    };

    window.toggleStatusAction = function(docType, presetId) {
        document.getElementById('toggleDocType').value = docType;
        document.getElementById('togglePresetId').value = presetId;
        document.getElementById('toggleStatusForm').submit();
    };

    // 2. GST Field Toggle in Company Info
    window.toggleGstField = function(isRegistered) {
        const inpBox = document.getElementById('gstInputContainer');
        const noGstMsg = document.getElementById('noGstMessage');
        if (inpBox) inpBox.style.display = isRegistered ? 'block' : 'none';
        if (noGstMsg) noGstMsg.style.display = isRegistered ? 'none' : 'block';
    };

    // 3. Filter template cards by search
    window.filterTemplateCards = function(query) {
        const q = query.toLowerCase().trim();
        document.querySelectorAll('.template-preset-card').forEach(card => {
            const title = card.querySelector('.preset-title')?.textContent.toLowerCase() || '';
            const desc = card.querySelector('.preset-desc')?.textContent.toLowerCase() || '';
            if (!q || title.includes(q) || desc.includes(q)) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    };

    // 4. Filter template cards by status
    window.filterTemplateStatus = function(status) {
        document.querySelectorAll('.template-preset-card').forEach(card => {
            const st = card.getAttribute('data-status') || '';
            if (status === 'ALL' || st === status) {
                card.style.display = 'flex';
            } else {
                card.style.display = 'none';
            }
        });
    };

    // 5. Modal Helpers
    window.openTemplateEditor = function(docType, presetId) {
        document.getElementById('editorDocType').value = docType;
        if (presetId) {
            document.getElementById('editorThemeStyle').value = presetId;
            document.getElementById('editorModalTitle').textContent = 'Customize ' + presetId.replace('_', ' ').toUpperCase() + ' Template';
        }
        document.getElementById('templateEditorModal').style.display = 'flex';
    };

    window.closeTemplateEditor = function() {
        document.getElementById('templateEditorModal').style.display = 'none';
    };

    window.openDocumentPreviewModal = function() {
        document.getElementById('documentPreviewModal').style.display = 'flex';
    };

    window.closeDocumentPreviewModal = function() {
        document.getElementById('documentPreviewModal').style.display = 'none';
    };

    window.openFullscreenPreview = function() {
        window.open('invoice.php?id=<?= urlencode($orderIdForPreview) ?>', '_blank');
    };

    window.previewSpecificPreset = function(presetId) {
        window.open('invoice.php?id=<?= urlencode($orderIdForPreview) ?>&preset=' + encodeURIComponent(presetId), '_blank');
    };

    window.previewReceiptPreset = function(presetId) {
        window.open('receipt.php?id=<?= urlencode($orderIdForPreview) ?>&preset=' + encodeURIComponent(presetId), '_blank');
    };

    window.setReceiptWidth = function(width) {
        const canvas = document.getElementById('mainReceiptPreviewCanvas');
        if (canvas) {
            canvas.style.maxWidth = (width === '58mm') ? '240px' : '320px';
        }
    };

    // Sync live editor changes to mini preview in modal
    window.syncEditorPreview = function() {
        const title = document.getElementById('editorHeaderTitle')?.value;
        const color = document.getElementById('editorPrimaryColor')?.value;
        if (title) document.getElementById('modalPreviewTitle').textContent = title;
        if (color) {
            document.getElementById('modalPreviewHeader').style.background = color;
            document.getElementById('modalPreviewTitle').style.color = color;
        }
    };

    window.toggleCardMenu = function(btn, e) {
        e.stopPropagation();
        const menu = btn.nextElementSibling;
        if (menu) {
            const isShown = menu.style.display === 'block';
            document.querySelectorAll('.card-dropdown-menu').forEach(m => m.style.display = 'none');
            menu.style.display = isShown ? 'none' : 'block';
        }
    };

    document.addEventListener('click', function() {
        document.querySelectorAll('.card-dropdown-menu').forEach(m => m.style.display = 'none');
    });
    </script>
</body>
</html>
