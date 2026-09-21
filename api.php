
<?php

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

require_once 'config.php';



error_reporting(E_ALL);
ini_set('display_errors', 1);

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'get_doctors':
    getDoctors();
    break;
    case 'search_product':
        searchProduct();
        break;
    case 'get_invoice_no':
        getInvoiceNo();
        break;
    case 'save_sale':
        saveSale();
        break;
    case 'get_sales':
        getSales();
        break;
    case 'get_sale_detail':
        getSaleDetail();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Invalid action']);
}


// -----------------------------------------------
// Search Products (Autocomplete)
// -----------------------------------------------
function searchProduct() {
    $conn = getDBConnection();
    $query = $_GET['q'] ?? '';
    $useSalt = $_GET['salt'] ?? '0';

    if (strlen($query) < 1) {
        echo json_encode([]);
        return;
    }

    $query = '%' . $conn->real_escape_string($query) . '%';

    if ($useSalt === '1') {
        // Search by salt (generic name)
        $sql = "SELECT * FROM products WHERE salt LIKE ? OR name LIKE ? ORDER BY name LIMIT 10";
    } else {
        // Search by product name
        $sql = "SELECT * FROM products WHERE name LIKE ? OR salt LIKE ? ORDER BY name LIMIT 10";
    }

    $stmt = $conn->prepare($sql);
    $stmt->bind_param('ss', $query, $query);
    $stmt->execute();
    $result = $stmt->get_result();

    $products = [];
    while ($row = $result->fetch_assoc()) {
        $products[] = $row;
    }

    echo json_encode($products);
    $conn->close();
}

// -----------------------------------------------
// Get Next Invoice Number
// -----------------------------------------------
function getInvoiceNo() {
    $conn = getDBConnection();

    $today = date('Y-m-d');
    $dateFormatted = date('Ymd'); // 20260608

    $stmt = $conn->prepare("SELECT last_number FROM invoice_counter LIMIT 1");
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    $lastNum = $row ? (int)$row['last_number'] : 777;
    $invoiceNo = 'SAL-' . $dateFormatted . '-' . $lastNum;

    echo json_encode([
        'success' => true,
        'invoice_no' => $invoiceNo,
        'invoice_date' => date('d-M-Y')
    ]);

    $conn->close();
}


// -----------------------------------------------
// Save Sale
// -----------------------------------------------
function saveSale() {
    $conn = getDBConnection();

    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        $input = $_POST;
    }

    $invoiceDate = $input['invoice_date'] ?? date('Y-m-d');
    $patientName = $conn->real_escape_string($input['patient_name'] ?? '');
    $patientPhone = $conn->real_escape_string($input['patient_phone'] ?? '');
    $patientAddress = $conn->real_escape_string($input['patient_address'] ?? '');
    
    $doctorName = $conn->real_escape_string($input['doctor_name'] ?? '');
    $reminder = (int)($input['reminder'] ?? 0);
    $labCharge = (float)($input['lab_charge'] ?? 0);
    $doctorCharge = (float)($input['doctor_charge'] ?? 0);
    $injectionCharge = (float)($input['injection_charge'] ?? 0);
    $nursingCharge = (float)($input['nursing_charge'] ?? 0);
    $totalDiscount = (float)($input['total_discount'] ?? 0);
    $productSubtotal = (float)($input['product_subtotal'] ?? 0);
    $additionalCharges = (float)($input['additional_charges'] ?? 0);
    $roundingOff = (float)($input['rounding_off'] ?? 0);
    $grandTotal = (float)($input['grand_total'] ?? 0);
    $items = $input['items'] ?? [];
    $status = $input['status'] ?? 'saved';

    // Generate Invoice Number
    $dateFormatted = date('Ymd', strtotime($invoiceDate));
    // -----------------------------------------------
// Add Validation 222222222222222222
// -----------------------------------------------
if(empty(trim($patientName)))
{
    $patientName = "Walk-in Customer";
}

if(empty($items))
{
    throw new Exception(
        "Please add products"
    );
}

    $conn->begin_transaction();
    try {
        // Get and increment invoice counter
        $stmt = $conn->prepare("SELECT last_number FROM invoice_counter LIMIT 1 FOR UPDATE");
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $lastNum = $row ? (int)$row['last_number'] : 777;
        $nextNum = $lastNum + 1;

        $invoiceNo = 'SAL-' . $dateFormatted . '-' . $nextNum;

        // Update counter
        $stmt = $conn->prepare("UPDATE invoice_counter SET last_number = ?, last_date = ?");
        $stmt->bind_param('is', $nextNum, $invoiceDate);
        $stmt->execute();

        // Insert Sale
        $sql = "INSERT INTO sales (invoice_no, invoice_date, patient_name, patient_phone, patient_address,
                doctor_name, reminder, lab_charge, doctor_charge, injection_charge, nursing_charge,
                total_discount, product_subtotal, additional_charges, rounding_off, grand_total, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param(
    'ssssssiddddddddds',
    $invoiceNo,
    $invoiceDate,
    $patientName,
    $patientPhone,
    $patientAddress,
    $doctorName,
    $reminder,
    $labCharge,
    $doctorCharge,
    $injectionCharge,
    $nursingCharge,
    $totalDiscount,
    $productSubtotal,
    $additionalCharges,
    $roundingOff,
    $grandTotal,
    $status
);
        $stmt->execute();
        $saleId = $conn->insert_id;

        // Insert Sale Items
        foreach ($items as $item) {
            $productName = $conn->real_escape_string($item['product_name'] ?? '');
            $salt = $conn->real_escape_string($item['salt'] ?? '');
            $batchNo = $conn->real_escape_string($item['batch_no'] ?? '');
            $expiryDate = $conn->real_escape_string($item['expiry_date'] ?? '');
            $qty = (int)($item['qty'] ?? 1);
            $rate = (float)($item['rate'] ?? 0);
            $gstPercent = (float)($item['gst_percent'] ?? 0);
            $mrp = (float)($item['mrp'] ?? 0);
            $discPercent = (float)($item['disc_percent'] ?? 0);
            $total = (float)($item['total'] ?? 0);

            $itemSql = "INSERT INTO sale_items (sale_id, product_name, salt, batch_no, expiry_date,
                        qty, rate, gst_percent, mrp, disc_percent, total)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            $itemStmt = $conn->prepare($itemSql);
            $itemStmt->bind_param(
                'issssiddddd',
                $saleId, $productName, $salt, $batchNo, $expiryDate,
                $qty, $rate, $gstPercent, $mrp, $discPercent, $total
            );
            $itemStmt->execute();
        }

        $conn->commit();

        // Store in session
        $_SESSION['last_sale_id'] = $saleId;
        $_SESSION['last_invoice_no'] = $invoiceNo;

        echo json_encode([
            'success' => true,
            'sale_id' => $saleId,
            'invoice_no' => $invoiceNo,
            'message' => 'Sale saved successfully'
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }

    $conn->close();
}




// -----------------------------------------------
// Get All Sales
// -----------------------------------------------
function getSales() {
    $conn = getDBConnection();

    $stmt = $conn->prepare("SELECT * FROM sales ORDER BY created_at DESC LIMIT 50");
    $stmt->execute();
    $result = $stmt->get_result();

    $sales = [];
    while ($row = $result->fetch_assoc()) {
        $sales[] = $row;
    }

    echo json_encode(['success' => true, 'data' => $sales]);
    $conn->close();
}

// -----------------------------------------------
// Get Sale Detail (for PDF)
// -----------------------------------------------
function getSaleDetail() {
    $conn = getDBConnection();
    $saleId = (int)($_GET['id'] ?? 0);

    if (!$saleId) {
        echo json_encode(['success' => false, 'message' => 'Invalid sale ID']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM sales WHERE id = ?");
    $stmt->bind_param('i', $saleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $sale = $result->fetch_assoc();

    if (!$sale) {
        echo json_encode(['success' => false, 'message' => 'Sale not found']);
        return;
    }

    $stmt = $conn->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
    $stmt->bind_param('i', $saleId);
    $stmt->execute();
    $result = $stmt->get_result();

    $items = [];
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }

    $sale['items'] = $items;
    echo json_encode(['success' => true, 'data' => $sale]);
    $conn->close();
}
// -----------------------------------------------
// DOCTOR GET
// -----------------------------------------------

function getDoctors()
{
    $conn = getDBConnection();

    $result = $conn->query("
        SELECT id,name
        FROM doctors
        ORDER BY name
    ");

    $doctors = [];

    while($row = $result->fetch_assoc())
    {
        $doctors[] = $row;
    }

    echo json_encode($doctors);
}
?>
