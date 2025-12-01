<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

if (!isset($_GET['id'])) {
    redirect('reports.php');
}

$orderId = $_GET['id'];

// Fetch order details
$orderStmt = $pdo->prepare("
    SELECT o.* 
    FROM orders o 
    WHERE o.id = ?
");
$orderStmt->execute([$orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    die("Order not found!");
}

// Fetch order items
$itemsStmt = $pdo->prepare("
    SELECT oi.*, p.name as product_name, p.image_path 
    FROM order_items oi 
    JOIN products p ON oi.product_id = p.id 
    WHERE oi.order_id = ?
");
$itemsStmt->execute([$orderId]);
$items = $itemsStmt->fetchAll();

// Calculate total from items (for verification)
$calculatedTotal = 0;
foreach ($items as $item) {
    $calculatedTotal += $item['price'] * $item['quantity'];
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Order Details - #<?php echo $order['order_number']; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .receipt {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border: 1px solid #ddd;
        }
        .receipt-header {
            text-align: center;
            border-bottom: 2px dashed #333;
            padding-bottom: 15px;
            margin-bottom: 15px;
        }
        .receipt-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
        .receipt-total {
            border-top: 2px solid #333;
            margin-top: 15px;
            padding-top: 15px;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="superadmin_dashboard.php">Order Management System</a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)
                </span>
                <a class="nav-link" href="reports.php"><i class="fas fa-arrow-left"></i> Back to Reports</a>
                <a class="nav-link" href="logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Order Details</h4>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print"></i> Print Receipt
                        </button>
                    </div>
                    <div class="card-body">
                        <!-- Receipt Style Display -->
                        <div class="receipt">
                            <div class="receipt-header">
                                <h2>Order Management System</h2>
                                <p>Order Receipt</p>
                                <p><strong>Order #:</strong> <?php echo $order['order_number']; ?></p>
                                <p><strong>Date:</strong> <?php echo $order['date_added']; ?></p>
                                <p>
                                    <span class="badge bg-<?php 
                                        echo $order['status'] === 'completed' ? 'success' : 
                                             ($order['status'] === 'pending' ? 'warning' : 'danger'); 
                                    ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </p>
                            </div>

                            <div class="receipt-items">
                                <h5>Items Ordered:</h5>
                                <?php foreach ($items as $item): ?>
                                <div class="receipt-item">
                                    <div class="item-info">
                                        <strong><?php echo $item['product_name']; ?></strong>
                                        <br>
                                        <small>₱<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></small>
                                    </div>
                                    <div class="item-total">
                                        ₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="receipt-total">
                                <div class="receipt-item">
                                    <div>Subtotal:</div>
                                    <div>₱<?php echo number_format($calculatedTotal, 2); ?></div>
                                </div>
                                <div class="receipt-item">
                                    <div><strong>GRAND TOTAL:</strong></div>
                                    <div><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></div>
                                </div>
                            </div>

                            <?php if ($calculatedTotal != $order['total_amount']): ?>
                            <div class="alert alert-warning mt-3">
                                <small>Note: Calculated total (₱<?php echo number_format($calculatedTotal, 2); ?>) differs from stored total. This might be due to price changes after order was placed.</small>
                            </div>
                            <?php endif; ?>

                            <div class="text-center mt-4">
                                <p>Thank you for your business!</p>
                                <small>Generated on: <?php echo date('Y-m-d H:i:s'); ?></small>
                            </div>
                        </div>

                        <!-- Detailed Table View -->
                        <div class="mt-4">
                            <h5>Detailed Information</h5>
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Order Number</th>
                                    <td><?php echo $order['order_number']; ?></td>
                                </tr>
                                <tr>
                                    <th>Order Date</th>
                                    <td><?php echo $order['date_added']; ?></td>
                                </tr>
                                <tr>
                                    <th>Status</th>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $order['status'] === 'completed' ? 'success' : 
                                                 ($order['status'] === 'pending' ? 'warning' : 'danger'); 
                                        ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th>Total Amount</th>
                                    <td>₱<?php echo number_format($order['total_amount'], 2); ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Items Table -->
                        <div class="mt-4">
                            <h5>Order Items</h5>
                            <table class="table table-striped">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Price</th>
                                        <th>Quantity</th>
                                        <th>Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): ?>
                                    <tr>
                                        <td>
                                            <?php if ($item['image_path']): ?>
                                                <img src="<?php echo $item['image_path']; ?>" width="50" height="50" style="object-fit: cover;" class="me-2">
                                            <?php endif; ?>
                                            <?php echo $item['product_name']; ?>
                                        </td>
                                        <td>₱<?php echo number_format($item['price'], 2); ?></td>
                                        <td><?php echo $item['quantity']; ?></td>
                                        <td>₱<?php echo number_format($item['price'] * $item['quantity'], 2); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <tr class="table-success">
                                        <td colspan="3" class="text-end"><strong>Grand Total:</strong></td>
                                        <td><strong>₱<?php echo number_format($order['total_amount'], 2); ?></strong></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>