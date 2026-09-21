<?php

require_once 'config.php';

$conn = getDBConnection();

$result = $conn->query(
"SELECT *
FROM sales
ORDER BY id DESC"
);

?>

<!DOCTYPE html>
<html>
<head>
<title>Sales List</title>
<style>
table{
width:100%;
border-collapse:collapse;
}
th,td{
border:1px solid #ddd;
padding:8px;
}
</style>
</head>
<body>

<h2>Sales List</h2>

<table>

<tr>
<th>Invoice</th>
<th>Date</th>
<th>Patient</th>
<th>Total</th>
<th>Status</th>
<th>Action</th>
</tr>

<?php while(
$row =
$result->fetch_assoc()
): ?>

<tr>

<td>
<?= $row['invoice_no'] ?>
</td>

<td>
<?= $row['invoice_date'] ?>
</td>

<td>
<?= htmlspecialchars(
$row['patient_name']
) ?>
</td>

<td>
₹<?= number_format(
$row['grand_total'],
2
) ?>
</td>

<td>
<?= $row['status'] ?>
</td>

<td>

<a href="print_pdf.php?id=<?= $row['id'] ?>">
Print
</a>

</td>

</tr>

<?php endwhile; ?>

</table>

</body>
</html>