<?php
require_once 'config/config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

// Helper to format price like screenshot: show no decimals when .00
function displayPrice($price) {
    if (number_format($price, 2) === number_format((int)$price, 2)) {
        return number_format((int)$price);
    }
    return number_format($price, 2);
}

// Fetch products
$products = $pdo->query("SELECT * FROM products ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX order creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order_ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    $items = json_decode($_POST['cart_items'], true);

    if (empty($items)) {
        echo json_encode(['status' => 'error', 'message' => 'Cart empty']);
        exit;
    }

    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
    }

    try {
        $pdo->beginTransaction();

        $orderNumber = 'ORD' . date('YmdHis');
        $stmt = $pdo->prepare("INSERT INTO orders (order_number, total_amount, status) VALUES (?, ?, 'completed')");
        $stmt->execute([$orderNumber, $totalAmount]);
        $orderId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
        }

        $pdo->commit();
        echo json_encode(['status' => 'success', 'order_number' => $orderNumber, 'total' => $totalAmount]);
    } catch (Exception $e) {
        $pdo->rollBack();
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8" />
    <title>POS - Order Management</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- Bootstrap + Font Awesome -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <!-- Alpine.js -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>

    <style>
        body { background: #f6f6f6; }
        .menu-title { font-size: 28px; margin-bottom: 18px; font-weight: 600; }
        .product-card { cursor: default; border-radius: 6px; transition: transform .12s; height: 100%; }
        .product-card:hover { transform: translateY(-2px); }
        .product-card .card-img-top { height: 160px; object-fit: cover; border-top-left-radius: 6px; border-top-right-radius: 6px; }

        .product-name { font-size: 28px; margin: 8px 0 0; font-weight: 400; }
        .product-price { font-size: 18px; color: #555; margin-bottom: 12px; }

        .btn-add { background: #1976d2; color: #fff; border: none; }
        .btn-add:active, .btn-add:focus { outline: none; box-shadow: none; }

        .order-panel { padding: 18px; }
        .ordered-item { border: 1px solid #e7e7e7; border-radius: 8px; padding: 18px; margin-bottom: 12px; background: #fff; }
        .ordered-item .qty { color: green; font-weight: 600; float: right; }
        .total-box { font-size: 24px; font-weight: 700; margin-top: 12px; }

        .payment-input { margin-top: 10px; }
        .pay-btn { background: green; color: #fff; border: none; }

        .sticky-card { position: sticky; top: 18px; }

        .qty-input { width: 100%; }

        @media (max-width: 991px) {
            .product-card .card-img-top { height: 140px; }
        }
    </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold">POS System</a>
        <div class="ms-auto navbar-nav d-flex align-items-center">
            <span class="navbar-text me-3">
                Welcome, <?php echo $_SESSION['username']; ?> (<?php echo $_SESSION['role']; ?>)
            </span>
            <a class="nav-link" href="logout.php">Logout</a>
        </div>
    </div>
</nav>

<div class="container-fluid mt-4" x-data="posApp()">
    <div class="row">
        <!-- Left: Menu -->
        <div class="col-lg-8">
            <div class="card p-3">
                <div class="menu-title">Menu</div>
                <div class="row g-3">
                    <?php foreach ($products as $product): ?>
                        <div class="col-lg-3 col-md-4 col-sm-6">
                            <div class="card product-card h-100">
                                <?php if (!empty($product['image_path'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image_path']); ?>" class="card-img-top" alt="<?php echo htmlspecialchars($product['name']); ?>">
                                <?php else: ?>
                                    <div class="card-img-top bg-light d-flex align-items-center justify-content-center" style="height:160px;">
                                        <i class="fas fa-image fa-2x text-muted"></i>
                                    </div>
                                <?php endif; ?>

                                <div class="card-body d-flex flex-column">
                                    <div class="product-name"><?php echo htmlspecialchars($product['name']); ?></div>
                                    <div class="product-price"><?php echo displayPrice($product['price']); ?> PHP</div>

                                    <div class="mt-auto">
                                        <div class="mb-2">
                                            <input type="number" min="1" x-model.number="qtys[<?php echo $product['id']; ?>]" placeholder="Qty" class="form-control form-control-sm qty-input">
                                        </div>
                                        <button class="btn btn-add btn-sm w-100"
                                                @click="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>)">
                                            Add to order
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (empty($products)): ?>
                        <div class="col-12"><div class="text-muted">No products yet.</div></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Right: Order Panel -->
        <div class="col-lg-4">
            <div class="card sticky-card">
                <div class="card-body order-panel">
                    <h4 class="mb-3">Ordered Items</h4>

                    <template x-if="cart.length === 0">
                        <div class="text-muted text-center">No items yet</div>
                    </template>

                    <template x-for="(item, index) in cart" :key="item.product_id">
                        <div class="ordered-item d-flex align-items-center justify-content-between">
                            <div>
                                <div style="font-size:18px; font-weight:500;" x-text="item.name"></div>
                            </div>
                            <div style="text-align:right;">
                                <div class="qty" x-text="'Qty: ' + item.quantity"></div>
                                <div class="small text-muted" x-text="'₱' + formatNumber(item.price) + ' x ' + item.quantity"></div>
                                <div class="mt-2">
                                    <button class="btn btn-sm btn-outline-danger" @click="removeFromCart(index)">
                                        <i class="fas fa-times"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <div class="total-box mt-3">
                        Total: <span x-text="formatNumber(total)"></span> PHP
                    </div>

                    <div class="payment-input">
                        <label class="form-label small text-muted">Cash</label>
                        <input type="number" min="0" x-model.number="cash" class="form-control" placeholder="Enter cash amount">
                    </div>

                    <div class="d-flex mt-3">
                        <button class="btn pay-btn ms-auto" :disabled="cart.length === 0" @click="handlePay()">Pay!</button>
                    </div>

                    <!-- fallback form for non-JS users -->
                    <form id="fallbackForm" method="POST" style="display:none;">
                        <input type="hidden" name="cart_items" id="cartItemsInputFallback">
                        <button type="submit" name="create_order">Create order (fallback)</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Alpine.js POS logic -->
<script>
function posApp() {
    return {
        cart: [],
        qtys: {},
        cash: 0,
        get total() {
            return this.cart.reduce((sum, i) => sum + i.price * i.quantity, 0);
        },
        addToCart(productId, name, price) {
            let qty = this.qtys[productId] || 1;
            if (qty < 1) qty = 1;

            const existing = this.cart.find(i => i.product_id === productId);
            if (existing) {
                existing.quantity += qty;
            } else {
                this.cart.push({ product_id: productId, name, price: parseFloat(price), quantity: qty });
            }

            this.qtys[productId] = null; // reset input
        },
        removeFromCart(index) {
            this.cart.splice(index, 1);
        },
        formatNumber(n) {
            return (Math.abs(n - Math.round(n)) < 0.001) ? Math.round(n) : n.toFixed(2);
        },
        async handlePay() {
            if (this.cart.length === 0) {
                alert('Cart is empty.');
                return;
            }

            if (this.cash < this.total) {
                alert('Insufficient cash.');
                return;
            }

            const change = this.cash - this.total;
            alert(`Thanks for ordering! Here's your ${Number.isInteger(change) ? change : change.toFixed(2)} pesos change`);

            try {
                const formData = new FormData();
                formData.append('create_order_ajax', '1');
                formData.append('cart_items', JSON.stringify(this.cart));

                const resp = await fetch('', { method: 'POST', body: formData });
                const data = await resp.json();

                if (data.status === 'success') {
                    this.cart = [];
                    this.cash = 0;
                    console.log('Order created:', data.order_number);
                } else {
                    alert('Server error: ' + (data.message || 'unknown'));
                }
            } catch (err) {
                alert('Network error: ' + err.message);
            }
        }
    }
}
</script>

<!-- Bootstrap bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
