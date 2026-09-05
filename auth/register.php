<?php
session_start();
require_once '../config/database.php';
require_once '../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $database = new Database();
    $db = $database->getConnection();
    
    $username = sanitize_input($_POST['username']);
    $email = sanitize_input($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = sanitize_input($_POST['full_name']);
    $phone = sanitize_input($_POST['phone'] ?? '');
    $address = sanitize_input($_POST['address'] ?? '');
    $role = isset($_POST['become_seller']) ? 'seller' : 'user';
    
    // Validate inputs
    $errors = [];
    
    if (empty($username)) $errors[] = "Username is required";
    if (empty($email)) $errors[] = "Email is required";
    if (empty($password)) $errors[] = "Password is required";
    if ($password != $confirm_password) $errors[] = "Passwords do not match";
    if (strlen($password) < 8) $errors[] = "Password must be at least 8 characters";
    if (empty($full_name)) $errors[] = "Full name is required";
    
    // Check if username exists
    $check_username = "SELECT id FROM users WHERE username = ?";
    $stmt = $db->prepare($check_username);
    $stmt->execute([$username]);
    if ($stmt->rowCount() > 0) $errors[] = "Username already exists";
    
    // Check if email exists
    $check_email = "SELECT id FROM users WHERE email = ?";
    $stmt = $db->prepare($check_email);
    $stmt->execute([$email]);
    if ($stmt->rowCount() > 0) $errors[] = "Email already exists";
    
    if (empty($errors)) {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $insert_query = "INSERT INTO users (username, email, password, full_name, phone, address, role) 
                         VALUES (?, ?, ?, ?, ?, ?, ?)";
        $insert_stmt = $db->prepare($insert_query);
        
        if ($insert_stmt->execute([$username, $email, $hashed_password, $full_name, $phone, $address, $role])) {
            $_SESSION['success'] = "Registration successful! Please login.";
            
            // If seller, create seller application
            if ($role == 'seller') {
                $user_id = $db->lastInsertId();
                $business_name = sanitize_input($_POST['business_name'] ?? '');
                $business_type = sanitize_input($_POST['business_type'] ?? '');
                
                $application_query = "INSERT INTO seller_applications (user_id, business_name, business_type) 
                                      VALUES (?, ?, ?)";
                $application_stmt = $db->prepare($application_query);
                $application_stmt->execute([$user_id, $business_name, $business_type]);
            }
            
            header("Location: login.php");
            exit();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - Msika Premium</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .register-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        .register-header {
            background: linear-gradient(135deg, #D32F2F, #B71C1C);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .register-header h2 {
            font-size: 1.8rem;
            font-weight: 700;
        }
        .register-form {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
        }
        .form-group input, .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-group input:focus, .form-group textarea:focus {
            outline: none;
            border-color: #D32F2F;
            box-shadow: 0 0 0 3px rgba(211,47,47,0.1);
        }
        .register-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #D32F2F, #B71C1C);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .register-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(211,47,47,0.3);
        }
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .login-link {
            text-align: center;
            margin-top: 20px;
            font-size: 0.9rem;
        }
        .login-link a {
            color: #D32F2F;
            text-decoration: none;
            font-weight: 600;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }
        .checkbox-group input[type="checkbox"] {
            width: auto;
        }
    </style>
</head>
<body>
    <div class="register-container">
        <div class="register-header">
            <h2><i class="fas fa-user-plus"></i> Create Account</h2>
            <p>Join Msika Premium today</p>
        </div>
        <div class="register-form">
            <?php if(isset($errors) && count($errors) > 0): ?>
                <div class="alert alert-error">
                    <ul>
                        <?php foreach($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" required placeholder="Enter your full name">
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required placeholder="Choose a username">
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" name="email" required placeholder="Enter your email">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" name="phone" placeholder="Enter your phone number">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="Enter password (min 8 characters)">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="confirm_password" required placeholder="Confirm your password">
                </div>
                
                <div class="checkbox-group">
                    <input type="checkbox" name="become_seller" id="become_seller">
                    <label for="become_seller">Register as Seller</label>
                </div>
                
                <div id="seller_fields" style="display: none;">
                    <div class="form-group">
                        <label>Business Name</label>
                        <input type="text" name="business_name" placeholder="Enter your business name">
                    </div>
                    <div class="form-group">
                        <label>Business Type</label>
                        <input type="text" name="business_type" placeholder="e.g., Electronics, Fashion, etc.">
                    </div>
                </div>
                
                <button type="submit" class="register-btn">
                    <i class="fas fa-user-plus"></i> Create Account
                </button>
            </form>
            <div class="login-link">
                Already have an account? <a href="login.php">Login here</a>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('become_seller').addEventListener('change', function() {
            var sellerFields = document.getElementById('seller_fields');
            if(this.checked) {
                sellerFields.style.display = 'block';
            } else {
                sellerFields.style.display = 'none';
            }
        });
    </script>
</body>
</html>