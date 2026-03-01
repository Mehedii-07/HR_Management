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

// Check leave balance
$leave_balance = $employee['leave_balance'];
$leave_taken = $employee['leave_taken'];

// Handle leave application
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $leave_type = $_POST['leave_type'];
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $reason = $_POST['reason'];
    
    // Calculate number of days
    $start = new DateTime($start_date);
    $end = new DateTime($end_date);
    $requested_days = $start->diff($end)->days + 1;
    
    // Check if enough leave balance
    if ($requested_days > $leave_balance) {
        $error = "Insufficient leave balance! You have $leave_balance days left, but requested $requested_days days.";
    } elseif (empty($start_date) || empty($end_date)) {
        $error = "Please select both start and end dates.";
    } elseif ($start_date > $end_date) {
        $error = "End date cannot be before start date.";
    } else {
        try {
            $stmt = $pdo->prepare("INSERT INTO leave_applications (employee_id, leave_type, start_date, end_date, reason) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$employee['id'], $leave_type, $start_date, $end_date, $reason]);
            
            $success = "Leave application submitted successfully! It is now pending approval.";
            
        } catch (PDOException $e) {
            $error = "Error submitting leave application: " . $e->getMessage();
        }
    }
}

// Get leave applications for status tab
$stmt = $pdo->prepare("SELECT * FROM leave_applications WHERE employee_id = ? ORDER BY applied_at DESC");
$stmt->execute([$employee['id']]);
$leave_applications = $stmt->fetchAll();

// Calculate statistics for status tab
$total_leaves = count($leave_applications);
$approved_leaves = 0;
$pending_leaves = 0;
$rejected_leaves = 0;

foreach ($leave_applications as $leave) {
    if ($leave['status'] == 'approved') $approved_leaves++;
    if ($leave['status'] == 'pending') $pending_leaves++;
    if ($leave['status'] == 'rejected') $rejected_leaves++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Apply for Leave - HR Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/leave.css">
    <link rel="stylesheet" href="css/tabs.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>HR Management System</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Home</a></li>
                    <li><a href="profile.php">My Profile</a></li>
                    <li><a href="apply-leave.php" class="active">Apply Leave</a></li>
                    <li><a href="attendance-view.php">My Attendance</a></li>
                    <li><a href="salary-view.php">My Salary</a></li>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Leave Management</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Feature Tabs -->
        <div class="feature-tabs">
            <button class="tab-button active" onclick="openTab('apply')">Apply Leave</button>
            <button class="tab-button" onclick="openTab('status')">Leave Status</button>
            <button class="tab-button" onclick="openTab('policy')">Leave Policy</button>
        </div>

        <!-- Apply Leave Tab -->
        <div id="apply" class="tab-content active">
            <div class="leave-form-container">
                <!-- Leave Balance Display -->
                <div class="leave-balance-info">
                    <h4>Your Leave Balance</h4>
                    <div class="balance-display">
                        <span class="balance-label">Available:</span>
                        <span class="balance-value"><?php echo $leave_balance; ?> days</span>
                        <span class="balance-label">Taken:</span>
                        <span class="balance-value"><?php echo $leave_taken; ?> days</span>
                    </div>
                </div>

                <form method="POST" action="">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="leave_type">Leave Type *</label>
                            <select id="leave_type" name="leave_type" required>
                                <option value="">Select Leave Type</option>
                                <option value="sick">Sick Leave</option>
                                <option value="casual">Casual Leave</option>
                                <option value="annual">Annual Leave</option>
                                <option value="emergency">Emergency Leave</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label for="start_date">Start Date *</label>
                            <input type="date" id="start_date" name="start_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                        
                        <div class="form-group">
                            <label for="end_date">End Date *</label>
                            <input type="date" id="end_date" name="end_date" required min="<?php echo date('Y-m-d'); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="reason">Reason for Leave *</label>
                        <textarea id="reason" name="reason" rows="4" required placeholder="Please provide a reason for your leave application..."></textarea>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary">Submit Leave Application</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Leave Status Tab -->
        <div id="status" class="tab-content">
            <div class="leave-status-container">
                <h3>My Leave Applications</h3>
                
                <!-- Leave Statistics -->
                <div class="leave-stats">
                    <div class="stat-card">
                        <h3>Total Applications</h3>
                        <p><?php echo $total_leaves; ?></p>
                    </div>
                    <div class="stat-card approved">
                        <h3>Approved</h3>
                        <p><?php echo $approved_leaves; ?></p>
                    </div>
                    <div class="stat-card pending">
                        <h3>Pending</h3>
                        <p><?php echo $pending_leaves; ?></p>
                    </div>
                    <div class="stat-card rejected">
                        <h3>Rejected</h3>
                        <p><?php echo $rejected_leaves; ?></p>
                    </div>
                </div>
                
                <?php if ($leave_applications): ?>
                    <div class="status-table-container">
                        <table class="status-table">
                            <thead>
                                <tr>
                                    <th>Leave Type</th>
                                    <th>Start Date</th>
                                    <th>End Date</th>
                                    <th>Duration</th>
                                    <th>Reason</th>
                                    <th>Applied On</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leave_applications as $leave): ?>
                                <tr>
                                    <td>
                                        <span class="leave-type <?php echo $leave['leave_type']; ?>">
                                            <?php echo ucfirst($leave['leave_type']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($leave['start_date'])); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($leave['end_date'])); ?></td>
                                    <td>
                                        <?php 
                                        $start = new DateTime($leave['start_date']);
                                        $end = new DateTime($leave['end_date']);
                                        $duration = $start->diff($end)->days + 1;
                                        echo $duration . ' day' . ($duration > 1 ? 's' : '');
                                        ?>
                                    </td>
                                    <td class="reason"><?php echo htmlspecialchars($leave['reason']); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($leave['applied_at'])); ?></td>
                                    <td>
                                        <span class="status status-<?php echo $leave['status']; ?>">
                                            <?php echo ucfirst($leave['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="no-data">
                        <p>No leave applications found.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Leave Policy Tab -->
        <div id="policy" class="tab-content">
            <div class="policy-container">
                <h3>Company Leave Policy</h3>
                
                <!-- Leave Balance Card -->
                <div class="leave-balance-card">
                    <h4>Your Leave Balance</h4>
                    <div class="balance-grid">
                        <div class="balance-item">
                            <h5>Total Yearly Leaves</h5>
                            <p class="total">30 Days</p>
                        </div>
                        <div class="balance-item">
                            <h5>Leaves Available</h5>
                            <p class="available"><?php echo $leave_balance; ?> Days</p>
                        </div>
                        <div class="balance-item">
                            <h5>Leaves Taken</h5>
                            <p class="taken"><?php echo $leave_taken; ?> Days</p>
                        </div>
                    </div>
                </div>

                <!-- Leave Policy Details -->
                <div class="policy-section">
                    <h4>Leave Policy Details</h4>
                    
                    <div class="policy-cards">
                        <div class="policy-card annual">
                            <h5>🏖 Annual Leave</h5>
                            <p class="days">20 Days</p>
                            <p>For vacations, personal work, family functions. Apply at least 2 days in advance.</p>
                        </div>
                        <div class="policy-card casual">
                            <h5>😊 Casual Leave</h5>
                            <p class="days">20 Days</p>
                            <p>For personal reasons, short notice situations. Flexible usage throughout the year.</p>
                        </div>

                        <div class="policy-card sick">
                            <h5>🤒 Sick Leave</h5>
                            <p class="days">7 Days</p>
                            <p>For health/medical reasons. Doctor's certificate may be required for more than 2 days.</p>
                        </div>
                        
                        <div class="policy-card emergency">
                            <h5>🚨 Emergency Leave</h5>
                            <p class="days">3 Days</p>
                            <p>For urgent, unexpected situations. Short notice accepted with valid reason.</p>
                        </div>
                    </div>
                </div>

                <!-- Policy Rules -->
                <div class="rules-section">
                    <h4>Important Rules</h4>
                    <div class="rules-list">
                        <div class="rule-item">
                            <span class="rule-icon">📅</span>
                            <div class="rule-content">
                                <h6>Year Reset</h6>
                                <p>Leaves refresh every January 1st. Unused leaves (max 5) can be carried forward.</p>
                            </div>
                        </div>
                        
                        <div class="rule-item">
                            <span class="rule-icon">⏰</span>
                            <div class="rule-content">
                                <h6>Application Time</h6>
                                <p>Apply at least 2 days in advance for annual leave. Emergency leave can be applied on same day.</p>
                            </div>
                        </div>
                        
                        <div class="rule-item">
                            <span class="rule-icon">📋</span>
                            <div class="rule-content">
                                <h6>Documentation</h6>
                                <p>Medical certificate required for sick leave exceeding 2 consecutive days.</p>
                            </div>
                        </div>
                        
                        <div class="rule-item">
                            <span class="rule-icon">💰</span>
                            <div class="rule-content">
                                <h6>Salary Impact</h6>
                                <p>Approved leaves don't affect salary. Unapproved absences lead to salary deduction.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Leave Calculation Section -->
                <div class="calculation-section">
                    <h4>Leave Balance Calculation</h4>
                    <div class="calculation-formula">
                        <div class="formula-card">
                            <h5>📊 Leave Balance Formula</h5>
                            <p><strong>Remaining Leaves = Total Yearly Leaves (30) - Leaves Taken</strong></p>
                            <div class="example">
                                <p><em>Example: If you've taken 8 days leave, you have 22 days remaining (30 - 8 = 22).</em></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Per-Day Salary Deduction Display -->
                <div class="per-day-deduction-section">
                    <h4>Salary Deduction Per Day</h4>
                    <div class="per-day-card">
                        <h5>💵 Daily Leave Deduction Amount</h5>
                        
                        <?php
                        $employee_salary = $employee['salary'] ?? 0;
                        $daily_salary = $employee_salary / 30;
                        ?>
                        
                        <div class="daily-calculation">
                            <div class="salary-breakdown">
                                <div class="breakdown-item">
                                    <span class="label">Your Monthly Salary:</span>
                                    <span class="value">$<?php echo number_format($employee_salary, 2); ?></span>
                                </div>
                                <div class="breakdown-item">
                                    <span class="label">Monthly Working Days:</span>
                                    <span class="value">30 days</span>
                                </div>
                                <div class="breakdown-item total">
                                    <span class="label">Deduction Per Day:</span>
                                    <span class="value text-danger">$<?php echo number_format($daily_salary, 2); ?></span>
                                </div>
                            </div>
                            
                            <div class="calculation-formula">
                                <p><strong>Formula:</strong> $<?php echo number_format($employee_salary, 2); ?> ÷ 30 = <strong class="text-danger">$<?php echo number_format($daily_salary, 2); ?></strong> per day</p>
                            </div>
                            
                            <div class="deduction-note">
                                <p><strong>Important:</strong> Each unapproved leave day deducts exactly <strong>$<?php echo number_format($daily_salary, 2); ?></strong> from your monthly salary.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        function openTab(tabName) {
            // Hide all tab contents
            var tabContents = document.getElementsByClassName('tab-content');
            for (var i = 0; i < tabContents.length; i++) {
                tabContents[i].classList.remove('active');
            }
            
            // Remove active class from all buttons
            var tabButtons = document.getElementsByClassName('tab-button');
            for (var i = 0; i < tabButtons.length; i++) {
                tabButtons[i].classList.remove('active');
            }
            
            // Show the specific tab content and activate the button
            document.getElementById(tabName).classList.add('active');
            event.currentTarget.classList.add('active');
        }

        // Set minimum dates to today
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('start_date').min = today;
        document.getElementById('end_date').min = today;

        // Update end date min when start date changes
        document.getElementById('start_date').addEventListener('change', function() {
            document.getElementById('end_date').min = this.value;
        });
    </script>
</body>
</html>