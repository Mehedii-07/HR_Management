<?php
include 'includes/config.php';
redirectIfNotLoggedIn();

// Only employees can access this page
if (!isEmployee()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$success = '';
$error = '';

// Get employee data
$stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: employee-info.php');
    exit();
}

// Update profile
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];
    $date_of_birth = $_POST['date_of_birth'];
    $gender = $_POST['gender'];
    $emergency_contact_name = $_POST['emergency_contact_name'];
    $emergency_contact_phone = $_POST['emergency_contact_phone'];
    $bank_account = $_POST['bank_account'];
    
    try {
        $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, phone = ?, address = ?, date_of_birth = ?, gender = ?, emergency_contact_name = ?, emergency_contact_phone = ?, bank_account = ? WHERE user_id = ?");
        
        $stmt->execute([
            $first_name, $last_name, $phone, $address, $date_of_birth, $gender,
            $emergency_contact_name, $emergency_contact_phone, $bank_account, $user_id
        ]);
        
        $success = "Profile updated successfully!";
        
        // Refresh employee data
        $stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
        $stmt->execute([$user_id]);
        $employee = $stmt->fetch();
        
    } catch (PDOException $e) {
        $error = "Error updating profile: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - HR Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>HR Management System</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Home</a></li>                <!-- CHANGED -->
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="apply-leave.php">Apply Leave</a></li>
                    <!-- LEAVE STATUS & POLICY REMOVED -->
                    <li><a href="attendance-view.php">My Attendance</a></li>
                    <li><a href="salary-view.php">My Salary</a></li>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>My Profile</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="profile-container">
            <!-- Profile View -->
            <div class="profile-view">
                <h3>Personal Information</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Employee ID:</label>
                        <span><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Name:</label>
                        <span><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Email:</label>
                        <span><?php echo htmlspecialchars($employee['email']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Phone:</label>
                        <span><?php echo htmlspecialchars($employee['phone'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Date of Birth:</label>
                        <span><?php echo $employee['date_of_birth'] ? date('M j, Y', strtotime($employee['date_of_birth'])) : 'Not provided'; ?></span>
                    </div>
                    <div class="info-item">
                        <label>Gender:</label>
                        <span><?php echo htmlspecialchars($employee['gender'] ? ucfirst($employee['gender']) : 'Not provided'); ?></span>
                    </div>
                </div>

                <h3>Employment Details</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Position:</label>
                        <span><?php echo htmlspecialchars($employee['position']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Department:</label>
                        <span><?php echo htmlspecialchars($employee['department']); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Salary:</label>
                        <span>$<?php echo number_format($employee['salary'], 2); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Join Date:</label>
                        <span><?php echo date('M j, Y', strtotime($employee['join_date'])); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Bank Account:</label>
                        <span><?php echo htmlspecialchars($employee['bank_account'] ?: 'Not provided'); ?></span>
                    </div>
                </div>

                <h3>Emergency Contact</h3>
                <div class="info-grid">
                    <div class="info-item">
                        <label>Contact Name:</label>
                        <span><?php echo htmlspecialchars($employee['emergency_contact_name'] ?: 'Not provided'); ?></span>
                    </div>
                    <div class="info-item">
                        <label>Contact Phone:</label>
                        <span><?php echo htmlspecialchars($employee['emergency_contact_phone'] ?: 'Not provided'); ?></span>
                    </div>
                </div>

                <h3>Address</h3>
                <div class="info-item full-width">
                    <label>Full Address:</label>
                    <span><?php echo htmlspecialchars($employee['address'] ?: 'Not provided'); ?></span>
                </div>
            </div>

            <!-- Profile Edit Form -->
            <div class="profile-edit">
                <h3>Update Profile Information</h3>
                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="first_name">First Name *</label>
                            <input type="text" id="first_name" name="first_name" value="<?php echo htmlspecialchars($employee['first_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="last_name">Last Name *</label>
                            <input type="text" id="last_name" name="last_name" value="<?php echo htmlspecialchars($employee['last_name']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="phone">Phone Number</label>
                            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($employee['phone']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="date_of_birth">Date of Birth</label>
                            <input type="date" id="date_of_birth" name="date_of_birth" value="<?php echo $employee['date_of_birth']; ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="gender">Gender</label>
                            <select id="gender" name="gender">
                                <option value="">Select Gender</option>
                                <option value="male" <?php echo $employee['gender'] == 'male' ? 'selected' : ''; ?>>Male</option>
                                <option value="female" <?php echo $employee['gender'] == 'female' ? 'selected' : ''; ?>>Female</option>
                                <option value="other" <?php echo $employee['gender'] == 'other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="bank_account">Bank Account</label>
                            <input type="text" id="bank_account" name="bank_account" value="<?php echo htmlspecialchars($employee['bank_account']); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3"><?php echo htmlspecialchars($employee['address']); ?></textarea>
                    </div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="emergency_contact_name">Emergency Contact Name</label>
                            <input type="text" id="emergency_contact_name" name="emergency_contact_name" value="<?php echo htmlspecialchars($employee['emergency_contact_name']); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="emergency_contact_phone">Emergency Contact Phone</label>
                            <input type="tel" id="emergency_contact_phone" name="emergency_contact_phone" value="<?php echo htmlspecialchars($employee['emergency_contact_phone']); ?>">
                        </div>
                    </div>

                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>