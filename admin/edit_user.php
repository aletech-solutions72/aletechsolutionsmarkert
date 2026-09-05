<?php
session_start();
require_once '../config/database.php';
require_once '../includes/auth_check.php';

check_admin_access();

$database = new Database();
$db = $database->getConnection();

$user_id = isset($_GET['id']) ? $_GET['id'] : 0;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = $_POST['full_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $role = $_POST['role'];
    $status = $_POST['status'];
    
    $query = "UPDATE users SET full_name = ?, email = ?, phone = ?, address = ?, role = ?, status = ? WHERE id = ?";
    $stmt = $db->prepare($query);
    
    if ($stmt->execute([$full_name, $email, $phone, $address, $role, $status, $user_id])) {
        log_admin_action($db, $_SESSION['user_id'], 'update_user', "Updated user ID: $user_id");
        $_SESSION['success'] = "User updated successfully!";
        header("Location: users.php");
        exit();
    }
}

// Get user data
$query = "SELECT * FROM users WHERE id = ?";
$stmt = $db->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header("Location: users.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User - Msika Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Inter', sans-serif; background: #f5f7fa; }
        .container {
            max-width: 800px;
            margin: 40px auto;
            padding: 20px;
        }
        .edit-form {
            background: white;
            padding: 30px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input, .form-group select, .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #e2e8f0;
            border-radius: 5px;
            font-size: 1rem;
        }
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #D32F2F;
        }
        .btn-submit {
            width: 100%;
            padding: 12px;
            background: #D32F2F;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-submit:hover {
            background: #B71C1C;
        }
        .btn-back {
            display: inline-block;
            margin-bottom: 20px;
            padding: 10px 20px;
            background: #64748b;
            color: white;
            text-decoration: none;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <a href="users.php" class="btn-back">
            <i class="fas fa-arrow-left"></i> Back to Users
        </a>
        
        <div class="edit-form">
            <h2 style="margin-bottom: 20px;">Edit User #<?php echo $user['id']; ?></h2>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                </div>
                
                <div class="form-group">
                    <label>Phone</label>
                    <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>">
                </div>
                
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="address" rows="3"><?php echo htmlspecialchars($user['address']); ?></textarea>
                </div>
                
                <div class="form-group">
                    <label>Role</label>
                    <select name="role">
                        <option value="user" <?php echo $user['role'] == 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="seller" <?php echo $user['role'] == 'seller' ? 'selected' : ''; ?>>Seller</option>
                        <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="active" <?php echo $user['status'] == 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="suspended" <?php echo $user['status'] == 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                        <option value="pending" <?php echo $user['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                    </select>
                </div>
                
                <button type="submit" class="btn-submit">
                    <i class="fas fa-save"></i> Update User
                </button>
            </form>
        </div>
    </div>
</body>
</html>