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

// Handle standard POST creation (fallback if JS disabled)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_order'])) {
    $orderNumber = 'ORD' . date('YmdHis');
    $items = json_decode($_POST['cart_items'], true);

    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("INSERT INTO orders (order_number, total_amount, status) VALUES (?, ?, 'completed')");
        $stmt->execute([$orderNumber, $totalAmount]);
        $orderId = $pdo->lastInsertId();

        $stmt = $pdo->prepare("INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (?, ?, ?, ?)");
        foreach ($items as $item) {
            $stmt->execute([$orderId, $item['product_id'], $item['quantity'], $item['price']]);
        }

        $pdo->commit();
        $success = "Order #$orderNumber created successfully! Total: ₱" . number_format($totalAmount, 2);
    } catch (Exception $e) {
        $pdo->rollBack();
        $error = "Error creating order: " . $e->getMessage();
    }
}

// Handle AJAX order creation (called after client-side alert)
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

<div class="container-fluid mt-4">
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
                                            <input type="number" min="1" value="" id="qty_<?php echo $product['id']; ?>" class="form-control form-control-sm qty-input" />
                                        </div>
                                        <button class="btn btn-add btn-sm w-100" onclick="addToCart(<?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>', <?php echo $product['price']; ?>)">
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

        <div class="col-lg-4">
            <div class="card sticky-card">
                <div class="card-body order-panel">
                    <h4 class="mb-3">Ordered Items</h4>

                    <div id="orderedList">
                        <div class="text-muted text-center">No items yet</div>
                    </div>

                    <div class="total-box mt-3">
                        Total: <span id="totalDisplay">0</span> PHP
                    </div>

                    <div class="payment-input">
                        <label class="form-label small text-muted">Cash</label>
                        <input id="cashInput" type="number" min="0" class="form-control" placeholder="Enter cash amount" />
                    </div>

                    <div class="d-flex mt-3">
                        <button id="payBtn" class="btn pay-btn ms-auto" onclick="handlePay()" disabled>Pay!</button>
                    </div>

                    <!-- fallback form (hidden) for non-JS users -->
                    <form id="fallbackForm" method="POST" style="display:none;">
                        <input type="hidden" name="cart_items" id="cartItemsInputFallback">
                        <button type="submit" name="create_order">Create order (fallback)</button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script>
    // Client-side cart
    let cart = [];

    // Add to cart triggered by Add to order button in product card
    function addToCart(productId, productName, price) {
        const qtyInput = document.getElementById('qty_' + productId);
        let qty = 1;
        if (qtyInput) {
            qty = parseInt(qtyInput.value) || 1;
            if (qty < 1) qty = 1;
        }

        const existing = cart.find(i => i.product_id === productId);
        if (existing) {
            existing.quantity += qty;
        } else {
            cart.push({
                product_id: productId,
                name: productName,
                price: parseFloat(price),
                quantity: qty
            });
        }
        updateUI();
    }

    // Remove from cart
    function removeFromCart(index) {
        cart.splice(index, 1);
        updateUI();
    }

    // Update ordered items list and total
    function updateUI() {
        const orderedList = document.getElementById('orderedList');
        const totalDisplay = document.getElementById('totalDisplay');
        const payBtn = document.getElementById('payBtn');

        if (cart.length === 0) {
            orderedList.innerHTML = '<div class="text-muted text-center">No items yet</div>';
            totalDisplay.textContent = '0';
            payBtn.disabled = true;
            return;
        }

        let html = '';
        let total = 0;
        cart.forEach((item, idx) => {
            const itemTotal = item.price * item.quantity;
            total += itemTotal;

            html += `
                <div class="ordered-item d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size:18px; font-weight:500;">${escapeHtml(item.name)}</div>
                    </div>
                    <div style="text-align:right;">
                        <div class="qty">Qty: ${item.quantity}</div>
                        <div class="small text-muted">₱${formatNumber(item.price)} x ${item.quantity}</div>
                        <div class="mt-2">
                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${idx})"><i class="fas fa-times"></i></button>
                        </div>
                    </div>
                </div>
            `;
        });

        orderedList.innerHTML = html;
        totalDisplay.textContent = formatNumber(total);
        payBtn.disabled = false;
    }

    // Escaping utility
    function escapeHtml(text) {
        if (!text) return '';
        return text.replace(/[&<>"']/g, function(m) { return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]; });
    }

    function formatNumber(n) {
        // show integer when whole number (like screenshot) else two decimals
        if (Math.abs(n - Math.round(n)) < 0.001) return Math.round(n);
        return n.toFixed(2);
    }

    // Pay handler: show alert with change, then send AJAX to create order, then clear cart
    async function handlePay() {
        const cashInput = document.getElementById('cashInput');
        const cash = parseFloat(cashInput.value) || 0;
        let total = 0;
        cart.forEach(i => { total += i.price * i.quantity; });

        if (cart.length === 0) {
            alert('Cart is empty.');
            return;
        }

        if (cash < total) {
            alert('Insufficient cash.');
            return;
        }

        const change = cash - total;
        // format change representation like "600 pesos change" as integer when applicable
        const changeStr = Number.isInteger(change) ? change : change.toFixed(2);
        // Show the alert BEFORE sending to server (as requested)
        alert(`Thanks for ordering! Here's your ${changeStr} pesos change`);

        // Send to server to create order (AJAX)
        try {
            const formData = new FormData();
            formData.append('create_order_ajax', '1');
            formData.append('cart_items', JSON.stringify(cart));

            const resp = await fetch('', {
                method: 'POST',
                body: formData
            });

            const data = await resp.json();

            if (data.status === 'success') {
                // Clear cart and UI
                cart = [];
                updateUI();
                document.getElementById('cashInput').value = '';
                // Optional: show a small success message (not required)
                console.log('Order created:', data.order_number);
            } else {
                // server error
                alert('Server error creating order: ' + (data.message || 'unknown'));
            }
        } catch (err) {
            alert('Network error: ' + err.message);
        }
    }

    // Initialize empty UI
    updateUI();
</script>

<!-- Bootstrap bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
