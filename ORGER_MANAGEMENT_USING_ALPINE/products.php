<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Handle product creation - ALLOW BOTH ADMIN AND SUPER ADMIN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_product'])) {
    $name = $_POST['name'];
    $price = $_POST['price'];
    
    // Handle image upload
    $imagePath = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === 0) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileName = time() . '_' . $_FILES['image']['name'];
        $imagePath = $uploadDir . $fileName;
        move_uploaded_file($_FILES['image']['tmp_name'], $imagePath);
    }
    
    $stmt = $pdo->prepare("INSERT INTO products (name, price, image_path, added_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$name, $price, $imagePath, $_SESSION['user_id']]);
    $success = "Product added successfully!";
}

// Fetch all products
$products = $pdo->query("SELECT p.*, u.username FROM products p JOIN users u ON p.added_by = u.id ORDER BY p.date_added DESC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Products Management</title>
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
                        <a class="list-group-item list-group-item-action">
                            <i class="fas fa-users"></i> User Management
                        </a>
                    <?php endif; ?>
                    <a href="products.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-box"></i> Products
                    </a>
                    <a href="pos.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-cash-register"></i> POS
                    </a>
                    <a href="reports.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-chart-bar"></i> Reports
                    </a>
                </div>
            </div>
            
            <div class="col-md-9">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h4>Products Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createProductModal">
                            <i class="fas fa-plus"></i> Add Product
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <?php if (empty($products)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                <p class="text-muted">No products found. Add your first product!</p>
                            </div>
                        <?php else: ?>
                            <div class="row">
                                <?php foreach ($products as $product): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card h-100">
                                        <?php if ($product['image_path']): ?>
                                            <img src="<?php echo $product['image_path']; ?>" class="card-img-top" style="height: 200px; object-fit: cover;">
                                        <?php else: ?>
                                            <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                                <i class="fas fa-image fa-3x text-muted"></i>
                                            </div>
                                        <?php endif; ?>
                                        <div class="card-body">
                                            <h5 class="card-title"><?php echo $product['name']; ?></h5>
                                            <p class="card-text">Price: ₱<?php echo number_format($product['price'], 2); ?></p>
                                            <small class="text-muted">
                                                Added by: <?php echo $product['username']; ?><br>
                                                <?php echo $product['date_added']; ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Product Modal - ACCESSIBLE TO BOTH ADMIN AND SUPER ADMIN -->
    <div class="modal fade" id="createProductModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Product</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Product Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Price (PHP)</label>
                            <input type="number" name="price" step="0.01" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Product Image</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="create_product" class="btn btn-primary">Add Product</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>