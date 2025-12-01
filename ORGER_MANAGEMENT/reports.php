<?php
require_once 'config/config.php';

require_once 'vendor/autoload.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Set default date range (last 30 days)
$dateStart = $_GET['date_start'] ?? date('Y-m-d', strtotime('-30 days'));
$dateEnd = $_GET['date_end'] ?? date('Y-m-d');

// Build query for orders
$query = "SELECT o.* FROM orders o WHERE DATE(o.date_added) BETWEEN ? AND ? ORDER BY o.date_added DESC";
$params = [$dateStart, $dateEnd];

$orders = $pdo->prepare($query);
$orders->execute($params);
$orders = $orders->fetchAll();

// Calculate total
$totalStmt = $pdo->prepare("SELECT SUM(total_amount) as grand_total FROM orders WHERE DATE(date_added) BETWEEN ? AND ?");
$totalStmt->execute([$dateStart, $dateEnd]);
$grandTotal = $totalStmt->fetch()['grand_total'] ?? 0;

// Handle PDF generation
if (isset($_GET['generate_pdf'])) {
    require_once 'vendor/autoload.php';
    
    class PDF extends TCPDF {
        function Header() {
            $this->SetFont('helvetica', 'B', 16);
            $this->Cell(0, 15, 'Order Management System - Sales Report', 0, false, 'C', 0, '', 0, false, 'M', 'M');
            $this->Ln(10);
        }
        
        function Footer() {
            $this->SetY(-15);
            $this->SetFont('helvetica', 'I', 8);
            $this->Cell(0, 10, 'Page '.$this->getAliasNumPage().'/'.$this->getAliasNbPages(), 0, false, 'C', 0, '', 0, false, 'T', 'M');
        }
    }

    $pdf = new PDF();
    $pdf->SetCreator('Order Management System');
    $pdf->SetAuthor('OMS');
    $pdf->SetTitle('Sales Report');
    $pdf->AddPage();
    
    // Report header
    $pdf->SetFont('helvetica', '', 12);
    $pdf->Cell(0, 10, "Date Range: $dateStart to $dateEnd", 0, 1);
    $pdf->Ln(5);
    
    // Table header
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(30, 10, 'Order Number', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Date', 1, 0, 'C');
    $pdf->Cell(30, 10, 'Status', 1, 0, 'C');
    $pdf->Cell(40, 10, 'Total Amount', 1, 1, 'C');
    
    // Table data
    $pdf->SetFont('helvetica', '', 9);
    foreach ($orders as $order) {
        $pdf->Cell(30, 10, $order['order_number'], 1);
        $pdf->Cell(40, 10, $order['date_added'], 1);
        $pdf->Cell(30, 10, ucfirst($order['status']), 1);
        $pdf->Cell(40, 10, '₱' . number_format($order['total_amount'], 2), 1, 1);
    }
    
    // Grand total
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(100, 10, 'Grand Total:', 1, 0, 'R');
    $pdf->Cell(40, 10, '₱' . number_format($grandTotal, 2), 1, 1);
    
    $pdf->Output('sales_report_' . date('Y-m-d') . '.pdf', 'D');
    exit;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Reports</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
           <a class="navbar-brand">Order Management System</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)
                </span>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-3">
                <div class="list-group">
                    <?php if (isSuperAdmin()): ?>
                        <a href="superadmin_dashboard.php" class="list-group-item list-group-item-action">
                            <i class="fas fa-users"></i> User Management
                        </a>
                    <?php endif; ?>
                    <a href="products.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-box"></i> Products
                    </a>
                    <a href="pos.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cash-register"></i> POS
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header">
                        <h4>Sales Reports</h4>
                    </div>
                    <div class="card-body">
                        <!-- Date Filter Form -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-4">
                                <label>Date Start</label>
                                <input type="date" name="date_start" class="form-control" value="<?php echo $dateStart; ?>" required>
                            </div>
                            <div class="col-md-4">
                                <label>Date End</label>
                                <input type="date" name="date_end" class="form-control" value="<?php echo $dateEnd; ?>" required>
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-filter"></i> Filter
                                </button>
                                <a href="?date_start=<?php echo $dateStart; ?>&date_end=<?php echo $dateEnd; ?>&generate_pdf=1" 
                                   class="btn btn-danger">
                                    <i class="fas fa-file-pdf"></i> Generate PDF
                                </a>
                            </div>
                        </form>

                        <!-- Orders Table -->
                        <div class="table-responsive">
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Total Amount</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo $order['order_number']; ?></td>
                                        <td><?php echo $order['date_added']; ?></td>
                                        <td>
                                            <span class="badge bg-<?php 
                                                echo $order['status'] === 'completed' ? 'success' : 
                                                     ($order['status'] === 'pending' ? 'warning' : 'danger'); 
                                            ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <a href="order_details.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-eye"></i> View
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (count($orders) > 0): ?>
                                    <tr class="table-success">
                                        <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                        <td colspan="2"><strong>₱<?php echo number_format($grandTotal, 2); ?></strong></td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                            
                            <?php if (count($orders) === 0): ?>
                                <div class="text-center text-muted py-4">
                                    <i class="fas fa-inbox fa-3x mb-3"></i>
                                    <p>No orders found for the selected date range.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>