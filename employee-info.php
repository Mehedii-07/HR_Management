<?php
include 'includes/config.php';
redirectIfNotLoggedIn();

// Check if user is employee and hasn't completed profile
if (!isEmployee()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if profile already completed
if ($_SESSION['profile_completed']) {
    header('Location: dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Collect all form data
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $emergency_contact_name = $_POST['emergency_contact_name'];
    $emergency_contact_phone = $_POST['emergency_contact_phone'];
    $position = $_POST['position'];
    $department = $_POST['department'];
    $salary = $_POST['salary'];
    $bank_account = $_POST['bank_account'];
    $join_date = $_POST['join_date'];
    
    // Generate employee ID
    $employee_id = 'EMP' . str_pad($user_id, 4, '0', STR_PAD_LEFT);
    
    try {
        // Insert employee data
        $stmt = $pdo->prepare("INSERT INTO employees (user_id, employee_id, first_name, last_name, email, phone, address, date_of_birth, gender, emergency_contact_name, emergency_contact_phone, position, department, salary, bank_account, join_date) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $user_id, $employee_id, $first_name, $last_name, $email, $phone, $address, 
            $date_of_birth, $gender, $emergency_contact_name, $emergency_contact_phone,
            $position, $department, $salary, $bank_account, $join_date
        ]);
        
        // Update user profile as completed
        $stmt = $pdo->prepare("UPDATE users SET profile_completed = 1 WHERE id = ?");
        $stmt->execute([$user_id]);
        
        // Update session
        $_SESSION['profile_completed'] = true;
        
        $success = "Profile completed successfully! Redirecting to dashboard...";
        
        // Redirect after 2 seconds
        header("Refresh: 2; URL=dashboard.php");
        
    } catch (PDOException $e) {
        $error = "Error saving data: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Your Profile - HR Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/employee-info.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>HR Management System</h1>
            <nav>
                <ul>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Complete Your Employee Profile</h2>
        <p>Please fill in all your details to complete your profile setup.</p>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php else: ?>
        
        <form method="POST" action="">
            <!-- Personal Information -->
            <div class="form-section">
                <h3 class="section-title">Personal Information</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="first_name">First Name *</label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="last_name">Last Name *</label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email *</label>
                        <input type="email" id="email" name="email" value="<?php echo $_SESSION['email']; ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="date_of_birth">Date of Birth</label>
                        <input type="date" id="date_of_birth" name="date_of_birth">
                    </div>
                    
                    <div class="form-group">
                        <label for="gender">Gender</label>
                        <select id="gender" name="gender">
                            <option value="">Select Gender</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="address">Address</label>
                    <textarea id="address" name="address" rows="3" placeholder="Enter your full address"></textarea>
                </div>
            </div>

            <!-- Emergency Contact -->
            <div class="form-section">
                <h3 class="section-title">Emergency Contact</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="emergency_contact_name">Emergency Contact Name</label>
                        <input type="text" id="emergency_contact_name" name="emergency_contact_name">
                    </div>
                    
                    <div class="form-group">
                        <label for="emergency_contact_phone">Emergency Contact Phone</label>
                        <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone">
                    </div>
                </div>
            </div>

            <!-- Employment Details -->
            <div class="form-section">
                <h3 class="section-title">Employment Details</h3>
                <div class="form-grid">
                    <div class="form-group">
                        <label for="position">Position *</label>
                        <input type="text" id="position" name="position" required placeholder="e.g., Software Developer">
                    </div>
                    
                    <div class="form-group">
                        <label for="department">Department *</label>
                        <select id="department" name="department" required>
                            <option value="">Select Department</option>
                            <option value="IT">Information Technology</option>
                            <option value="HR">Human Resources</option>
                            <option value="Finance">Finance</option>
                            <option value="Marketing">Marketing</option>
                            <option value="Sales">Sales</option>
                            <option value="Operations">Operations</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="salary">Salary *</label>
                        <input type="number" id="salary" name="salary" step="0.01" required placeholder="0.00">
                    </div>
                    
                    <div class="form-group">
                        <label for="join_date">Join Date *</label>
                        <input type="date" id="join_date" name="join_date" required>
                    </div>
                    
                    <div class="form-group form-full">
                        <label for="bank_account">Bank Account Number</label>
                        <input type="text" id="bank_account" name="bank_account" placeholder="For payroll purposes">
                    </div>
                </div>
            </div>

            <button type="submit" class="btn btn-primary">Complete Profile</button>
        </form>
        <?php endif; ?>
    </div>

    <script>
        // Set join date to today by default
        document.getElementById('join_date').valueAsDate = new Date();
    </script>
</body>
</html>