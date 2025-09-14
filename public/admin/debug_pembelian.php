<?php
session_start();
require_once __DIR__ . '/../../config/koneksi.php';

echo "<h2>Debug Pembelian</h2>";

// Check session contents
echo "<h3>Session Contents:</h3>";
echo "<pre>";
print_r($_SESSION);
echo "</pre>";

// Check table structure
echo "<h3>Struktur Tabel bahan_request:</h3>";
$result = mysqli_query($conn, "DESCRIBE bahan_request");
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

echo "<h3>Struktur Tabel bahan_request_detail:</h3>";
$result = mysqli_query($conn, "DESCRIBE bahan_request_detail");
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

echo "<h3>Struktur Tabel bahan_biaya:</h3>";
$result = mysqli_query($conn, "DESCRIBE bahan_biaya");
echo "<table border='1'><tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
while ($row = mysqli_fetch_assoc($result)) {
    echo "<tr>";
    foreach ($row as $value) {
        echo "<td>" . htmlspecialchars($value) . "</td>";
    }
    echo "</tr>";
}
echo "</table>";

// Test query creation
echo "<h3>Test Query Creation:</h3>";
$kode_request = 'PO-' . date('ymd') . '-001';
$grand_total = 100000;
$id_user = 1;

$test_sql = "INSERT INTO bahan_request (kode_request, tanggal_request, grand_total, id_user, status) VALUES ('$kode_request', NOW(), $grand_total, $id_user, '0')";
echo "<p><strong>Test SQL:</strong> $test_sql</p>";

// Check if we can insert with these values
echo "<h3>Test Insert (without executing):</h3>";
$stmt = mysqli_prepare($conn, "INSERT INTO bahan_request (kode_request, tanggal_request, grand_total, id_user, status) VALUES (?, NOW(), ?, ?, '0')");
if ($stmt) {
    echo "<p style='color: green;'>Statement preparation successful</p>";
    mysqli_stmt_bind_param($stmt, "sdi", $kode_request, $grand_total, $id_user);
    echo "<p>Parameters bound successfully</p>";
} else {
    echo "<p style='color: red;'>Statement preparation failed: " . mysqli_error($conn) . "</p>";
}
?>