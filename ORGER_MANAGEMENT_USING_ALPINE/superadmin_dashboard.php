<?php
require_once 'config/config.php';

if (!isLoggedIn() || !isSuperAdmin()) {
    redirect('login.php');
}

// Handle user creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $username = $_POST['username'];
    $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $role = 'admin';
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
    $stmt->execute([$username, $password, $role]);
    $success = "Admin user created successfully!";
}

// Handle user suspension
if (isset($_GET['suspend'])) {
    $userId = $_GET['suspend'];
    $stmt = $pdo->prepare("UPDATE users SET is_active = FALSE WHERE id = ? AND role = 'admin'");
    $stmt->execute([$userId]);
}

// Handle user activation
if (isset($_GET['activate'])) {
    $userId = $_GET['activate'];
    $stmt = $pdo->prepare("UPDATE users SET is_active = TRUE WHERE id = ? AND role = 'admin'");
    $stmt->execute([$userId]);
}

// Fetch all users
$users = $pdo->query("SELECT * FROM users WHERE role = 'admin' ORDER BY date_added DESC")->fetchAll();
?>

<!DOCTYPE html>
<html>
<head>
    <title>Super Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="superadmin_dashboard.php">Order Management System</a>
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
                    <a href="superadmin_dashboard.php" class="list-group-item list-group-item-action active">
                        <i class="fas fa-users"></i> User Management
                    </a>
                    <a href="products.php" class="list-group-item list-group-item-action">
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
                        <h4>Admin User Management</h4>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                            <i class="fas fa-plus"></i> Create Admin
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (isset($success)): ?>
                            <div class="alert alert-success"><?php echo $success; ?></div>
                        <?php endif; ?>
                        
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Username</th>
                                    <th>Status</th>
                                    <th>Date Added</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                <tr>
                                    <td><?php echo $user['id']; ?></td>
                                    <td><?php echo $user['username']; ?></td>
                                    <td>
                                        <span class="badge <?php echo $user['is_active'] ? 'bg-success' : 'bg-danger'; ?>">
                                            <?php echo $user['is_active'] ? 'Active' : 'Suspended'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $user['date_added']; ?></td>
                                    <td>
                                        <?php if ($user['is_active']): ?>
                                            <a href="?suspend=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">
                                                <i class="fas fa-pause"></i> Suspend
                                            </a>
                                        <?php else: ?>
                                            <a href="?activate=<?php echo $user['id']; ?>" class="btn btn-success btn-sm">
                                                <i class="fas fa-play"></i> Activate
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create Admin User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label>Username</label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label>Password</label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>