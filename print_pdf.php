<?php
require_once 'config.php';
require_once './fpdf/fpdf.php';
// Get sale ID from GET or session
$saleId = (int)($_GET['id'] ?? $_SESSION['last_sale_id'] ?? 0);

if (!$saleId) {
    die('Invalid sale ID. <a href="index.html">Go Back</a>');
}

$conn = getDBConnection();

// Fetch sale
$stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->bind_param('i', $saleId);
$stmt->execute();
$sale = $stmt->get_result()->fetch_assoc();

if (!$sale) {
    die('Sale not found.');
}

// Fetch items
$stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$stmt->bind_param('i', $saleId);
$stmt->execute();
$items = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

$conn->close();

// =============================================
// Custom FPDF class with header/footer
// =============================================
class MedicalBillPDF extends FPDF {
    var $invoiceNo;
    var $invoiceDate;

    function Header() {
        // Header background
        $this->SetFillColor(25, 118, 210);
        $this->Rect(0, 0, 210, 30, 'F');

        // Clinic Name
        $this->SetFont('Arial', 'B', 18);
        $this->SetTextColor(255, 255, 255);
        $this->SetXY(10, 6);
        $this->Cell(120, 8, 'MediCare Pharmacy & Clinic', 0, 0);

        // Invoice label
        $this->SetFont('Arial', 'B', 14);
        $this->SetXY(130, 4);
        $this->Cell(70, 7, 'SALES INVOICE', 0, 1, 'R');

        $this->SetFont('Arial', '', 9);
        $this->SetXY(130, 11);
        $this->Cell(70, 5, 'Invoice No: ' . $this->invoiceNo, 0, 1, 'R');
        $this->SetXY(130, 17);
        $this->Cell(70, 5, 'Date: ' . $this->invoiceDate, 0, 1, 'R');

        // Address line
        $this->SetFont('Arial', '', 8);
        $this->SetXY(10, 14);
        $this->Cell(120, 5, '123 Health Street, Medical Hub, City - 600001 | Ph: 044-12345678', 0, 0);

        $this->SetTextColor(0, 0, 0);
        $this->Ln(10);
    }

    function Footer() {
        $this->SetY(-15);
        $this->SetFont('Arial', 'I', 8);
        $this->SetTextColor(128, 128, 128);
        $this->Cell(0, 5, 'Thank you for choosing MediCare! This is a computer-generated invoice.', 0, 0, 'C');
        $this->Ln(4);
        $this->Cell(0, 5, 'Page ' . $this->PageNo() . ' | Generated: ' . date('d-M-Y H:i:s'), 0, 0, 'C');
    }

    function SectionHeader($text) {
        $this->SetFillColor(232, 245, 253);
        $this->SetDrawColor(25, 118, 210);
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(25, 118, 210);
        $this->Cell(0, 7, '  ' . $text, 1, 1, 'L', true);
        $this->SetTextColor(0, 0, 0);
    }

    function TableHeader($cols, $widths, $aligns = []) {
        $this->SetFillColor(46, 125, 50);
        $this->SetTextColor(255, 255, 255);
        $this->SetFont('Arial', 'B', 8);
        foreach ($cols as $i => $col) {
            $align = $aligns[$i] ?? 'C';
            $this->Cell($widths[$i], 7, $col, 1, 0, $align, true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
    }
}

// =============================================
// Generate PDF
// =============================================
$pdf = new MedicalBillPDF('P', 'mm', 'A4');
$pdf->invoiceNo = $sale['invoice_no'];
$pdf->invoiceDate = date('d-M-Y', strtotime($sale['invoice_date']));
$pdf->SetMargins(10, 35, 10);
$pdf->SetAutoPageBreak(true, 20);
$pdf->AddPage();
$pdf->SetFont('Arial', '', 9);

// =============================================
// Patient & Doctor Details
// =============================================
$pdf->SectionHeader('PATIENT DETAILS');
$pdf->Ln(2);

// Row 1
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Patient Name:', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(75, 5, $sale['patient_name'] ?: 'N/A', 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Phone:', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(65, 5, $sale['patient_phone'] ?: 'N/A', 0, 1);

// Row 2
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Address:', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(165, 5, $sale['patient_address'] ?: 'N/A', 0, 1);
$pdf->Ln(3);

// Doctor Details
$pdf->SectionHeader('DOCTOR DETAILS');
$pdf->Ln(2);

$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Doctor Name:', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(75, 5, $sale['doctor_name'] ?: 'N/A', 0, 0);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(25, 5, 'Reminder:', 0, 0);
$pdf->SetFont('Arial', '', 8);
$pdf->Cell(65, 5, $sale['reminder'] ? 'Yes' : 'No', 0, 1);
$pdf->Ln(3);

// =============================================
// Product Details Table
// =============================================
$pdf->SectionHeader('PRODUCT DETAILS');
$pdf->Ln(2);

$cols = ['#', 'Product Name', 'Salt', 'Batch', 'Exp', 'Qty', 'Rate', 'GST%', 'MRP', 'Disc%', 'Total'];
$widths = [8, 38, 30, 15, 12, 8, 15, 12, 15, 12, 15];
$aligns = ['C', 'L', 'L', 'C', 'C', 'C', 'R', 'C', 'R', 'C', 'R'];
$pdf->TableHeader($cols, $widths, $aligns);

$rowNum = 1;
$fill = false;
foreach ($items as $item) {
    $pdf->SetFillColor(245, 250, 245);
    $pdf->SetFont('Arial', '', 7.5);
    $pdf->SetDrawColor(200, 200, 200);

    $pdf->Cell($widths[0], 6, $rowNum++, 1, 0, 'C', $fill);
    $pdf->Cell($widths[1], 6, mb_strimwidth($item['product_name'], 0, 22), 1, 0, 'L', $fill);
    $pdf->Cell($widths[2], 6, mb_strimwidth($item['salt'], 0, 18), 1, 0, 'L', $fill);
    $pdf->Cell($widths[3], 6, $item['batch_no'], 1, 0, 'C', $fill);
    $pdf->Cell($widths[4], 6, $item['expiry_date'], 1, 0, 'C', $fill);
    $pdf->Cell($widths[5], 6, $item['qty'], 1, 0, 'C', $fill);
    $pdf->Cell($widths[6], 6, number_format($item['rate'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($widths[7], 6, $item['gst_percent'] . '%', 1, 0, 'C', $fill);
    $pdf->Cell($widths[8], 6, number_format($item['mrp'], 2), 1, 0, 'R', $fill);
    $pdf->Cell($widths[9], 6, $item['disc_percent'] . '%', 1, 0, 'C', $fill);
    $pdf->Cell($widths[10], 6, number_format($item['total'], 2), 1, 1, 'R', $fill);

    $fill = !$fill;
}
$pdf->Ln(4);

// =============================================
// Additional Charges + Summary
// =============================================
$pdf->SectionHeader('ADDITIONAL CHARGES & SUMMARY');
$pdf->Ln(3);

// Left side: Additional charges
$leftX = 10;
$rightX = 120;
$pdf->SetXY($leftX, $pdf->GetY());

// Additional charges box
$pdf->SetDrawColor(200, 200, 200);
$pdf->SetFont('Arial', 'B', 8);
$pdf->Cell(50, 6, 'Charge Type', 1, 0, 'C', false);
$pdf->Cell(30, 6, 'Amount (Rs.)', 1, 1, 'C', false);

$charges = [
    'Lab Charge' => $sale['lab_charge'],
    'Doctor Charge' => $sale['doctor_charge'],
    'Injection Charge' => $sale['injection_charge'],
    'Nursing Charge' => $sale['nursing_charge'],
];

$pdf->SetFont('Arial', '', 8);
foreach ($charges as $label => $amount) {
    $pdf->Cell(50, 6, $label, 1, 0, 'L', false);
    $pdf->Cell(30, 6, number_format($amount, 2), 1, 1, 'R', false);
}

// Right side: Summary
$summaryY = $pdf->GetY() - (count($charges) + 1) * 6 - 3;
$summaryX = 120;
$pdf->SetXY($summaryX, $summaryY);

$pdf->SetFont('Arial', '', 8);
$summaryItems = [
    ['Total Discount:', number_format($sale['total_discount'], 2), false, [220, 0, 0]],
    ['Product Subtotal:', number_format($sale['product_subtotal'], 2), false, [0, 0, 0]],
    ['Additional Charges:', number_format($sale['additional_charges'], 2), false, [0, 0, 0]],
    ['Rounding Off:', number_format($sale['rounding_off'], 2), false, [0, 0, 0]],
];

foreach ($summaryItems as $row) {
    [$label, $value, $bold, $color] = $row;
    $pdf->SetXY($summaryX, $pdf->GetY());
    $pdf->SetFont('Arial', $bold ? 'B' : '', 8);
    $pdf->SetTextColor($color[0], $color[1], $color[2]);
    $pdf->Cell(55, 6, $label, 1, 0, 'L', false);
    $pdf->Cell(25, 6, $value, 1, 1, 'R', false);
}

// Grand Total
$pdf->SetXY($summaryX, $pdf->GetY());
$pdf->SetFillColor(46, 125, 50);
$pdf->SetTextColor(255, 255, 255);
$pdf->SetFont('Arial', 'B', 10);
$pdf->Cell(55, 8, 'GRAND TOTAL:', 1, 0, 'L', true);
$pdf->Cell(25, 8, 'Rs. ' . number_format($sale['grand_total'], 2), 1, 1, 'R', true);

$pdf->SetTextColor(0, 0, 0);
$pdf->Ln(8);

// =============================================
// Signature Section
// =============================================
$pdf->SetDrawColor(180, 180, 180);
$pdf->SetFont('Arial', '', 8);

$pdf->Cell(60, 15, '', 1, 0, 'C');
$pdf->Cell(10, 15, '', 0, 0);
$pdf->Cell(60, 15, '', 1, 0, 'C');
$pdf->Cell(10, 15, '', 0, 0);
$pdf->Cell(50, 15, '', 1, 1, 'C');

$pdf->Cell(60, 5, 'Patient / Receiver Signature', 0, 0, 'C');
$pdf->Cell(10, 5, '', 0, 0);
$pdf->Cell(60, 5, 'Doctor Signature', 0, 0, 'C');
$pdf->Cell(10, 5, '', 0, 0);
$pdf->Cell(50, 5, 'Authorized Signature', 0, 1, 'C');

// Terms
$pdf->Ln(5);
$pdf->SetFont('Arial', 'I', 7);
$pdf->SetTextColor(100, 100, 100);
$pdf->MultiCell(0, 4, 'Terms & Conditions: Goods once sold will not be taken back. This invoice is valid for billing purposes only. All disputes subject to local jurisdiction.', 0, 'C');

// =============================================
// Output PDF
// =============================================
$filename = 'Invoice_' . $sale['invoice_no'] . '.pdf';
$pdf->Output('I', $filename);
?>
