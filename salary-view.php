<?php
include 'includes/config.php';
redirectIfNotLoggedIn();

// Only employees can access this page
if (!isEmployee()) {
    header('Location: dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$selected_month = $_GET['month'] ?? date('Y-m');

// Get employee data
$stmt = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
$stmt->execute([$user_id]);
$employee = $stmt->fetch();

if (!$employee) {
    header('Location: employee-info.php');
    exit();
}

// Get payroll data for selected month
$payroll = null;
try {
    $payroll_stmt = $pdo->prepare("
        SELECT * FROM payroll 
        WHERE employee_id = ? AND month_year = ?
    ");
    $payroll_stmt->execute([$employee['id'], $selected_month]);
    $payroll = $payroll_stmt->fetch();
} catch (PDOException $e) {
    // If payroll table doesn't exist, continue
}

// Get attendance for salary deduction calculation
$absent_days = 0;
$daily_salary = $employee['salary'] / 30;
$deduction_amount = 0;

try {
    $absent_stmt = $pdo->prepare("
        SELECT COUNT(*) as absent_days 
        FROM attendance 
        WHERE employee_id = ? 
        AND DATE_FORMAT(date, '%Y-%m') = ? 
        AND status = 'absent'
    ");
    $absent_stmt->execute([$employee['id'], $selected_month]);
    $absent_data = $absent_stmt->fetch();
    $absent_days = $absent_data['absent_days'];
    $deduction_amount = $absent_days * $daily_salary;
} catch (PDOException $e) {
    // If attendance table doesn't exist, use default values
    $absent_days = 0;
    $deduction_amount = 0;
}

// Get payroll history
$payroll_history = [];
try {
    $history_stmt = $pdo->prepare("
        SELECT * FROM payroll 
        WHERE employee_id = ? 
        ORDER BY month_year DESC 
        LIMIT 6
    ");
    $history_stmt->execute([$employee['id']]);
    $payroll_history = $history_stmt->fetchAll();
} catch (PDOException $e) {
    // If payroll table doesn't exist, show empty
}

// If payroll exists, use its values, otherwise calculate
if ($payroll) {
    $basic_salary = $payroll['basic_salary'];
    $allowances = $payroll['allowances'];
    $deductions = $payroll['deductions'];
    $net_salary = $payroll['net_salary'];
    $status = $payroll['status'];
    $payment_date = $payroll['payment_date'];
} else {
    $basic_salary = $employee['salary'];
    $allowances = 0;
    $deductions = $deduction_amount;
    $net_salary = $basic_salary + $allowances - $deductions;
    $status = 'not_processed';
    $payment_date = null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Salary - HR Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/salary.css">
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
        <h2>My Salary</h2>

        <!-- Month Selection -->
        <div class="month-selection">
            <form method="GET" action="" class="month-form">
                <label for="month">Select Month:</label>
                <input type="month" id="month" name="month" value="<?php echo $selected_month; ?>" max="<?php echo date('Y-m'); ?>">
                <button type="submit" class="btn btn-primary">Load Salary</button>
            </form>
        </div>

        <!-- Salary Overview -->
        <div class="salary-overview">
            <h3>Salary Overview for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></h3>
            
            <div class="salary-cards">
                <div class="salary-card basic">
                    <h4>Basic Salary</h4>
                    <p class="amount">$<?php echo number_format($basic_salary, 2); ?></p>
                </div>
                
                <div class="salary-card allowances">
                    <h4>Allowances</h4>
                    <p class="amount">+ $<?php echo number_format($allowances, 2); ?></p>
                </div>
                
                <div class="salary-card deductions">
                    <h4>Deductions</h4>
                    <p class="amount">- $<?php echo number_format($deductions, 2); ?></p>
                    <?php if ($absent_days > 0): ?>
                    <small><?php echo $absent_days; ?> absent day(s) @ $<?php echo number_format($daily_salary, 2); ?>/day</small>
                    <?php endif; ?>
                </div>
                
                <div class="salary-card net">
                    <h4>Net Salary</h4>
                    <p class="amount">$<?php echo number_format($net_salary, 2); ?></p>
                    <div class="salary-status status-<?php echo $status; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                        <?php if ($payment_date): ?>
                            <br><small>Paid on: <?php echo date('M j, Y', strtotime($payment_date)); ?></small>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Salary Breakdown -->
        <div class="salary-breakdown">
            <h4>Salary Breakdown</h4>
            <div class="breakdown-grid">
                <div class="breakdown-item">
                    <label>Employee ID:</label>
                    <span><?php echo htmlspecialchars($employee['employee_id']); ?></span>
                </div>
                <div class="breakdown-item">
                    <label>Employee Name:</label>
                    <span><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></span>
                </div>
                <div class="breakdown-item">
                    <label>Position:</label>
                    <span><?php echo htmlspecialchars($employee['position']); ?></span>
                </div>
                <div class="breakdown-item">
                    <label>Department:</label>
                    <span><?php echo htmlspecialchars($employee['department']); ?></span>
                </div>
                <div class="breakdown-item">
                    <label>Bank Account:</label>
                    <span><?php echo htmlspecialchars($employee['bank_account'] ?: 'Not provided'); ?></span>
                </div>
                <div class="breakdown-item">
                    <label>Absent Days:</label>
                    <span class="<?php echo $absent_days > 0 ? 'text-warning' : ''; ?>">
                        <?php echo $absent_days; ?> day(s)
                    </span>
                </div>
            </div>
        </div>

        <!-- Salary History -->
        <div class="salary-history">
            <h4>Recent Salary History</h4>
            <?php if ($payroll_history): ?>
                <div class="history-table-container">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>Month</th>
                                <th>Basic Salary</th>
                                <th>Allowances</th>
                                <th>Deductions</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Payment Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payroll_history as $history): ?>
                            <tr>
                                <td><?php echo date('F Y', strtotime($history['month_year'] . '-01')); ?></td>
                                <td>$<?php echo number_format($history['basic_salary'], 2); ?></td>
                                <td class="text-success">$<?php echo number_format($history['allowances'], 2); ?></td>
                                <td class="text-danger">$<?php echo number_format($history['deductions'], 2); ?></td>
                                <td class="text-primary"><strong>$<?php echo number_format($history['net_salary'], 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo $history['status']; ?>">
                                        <?php echo ucfirst($history['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo $history['payment_date'] ? date('M j, Y', strtotime($history['payment_date'])) : 'Not Paid'; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>No salary history found.</p>
                    <?php if (empty($payroll_history) && !$payroll): ?>
                    <p><small>Salary data will appear here once HR processes payroll for this month.</small></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Salary Information -->
        <div class="salary-info">
            <h4>Salary Information</h4>
            <div class="info-cards">
                <div class="info-card">
                    <h5>Payment Schedule</h5>
                    <p>Salaries are processed on the last working day of each month.</p>
                </div>
                <div class="info-card">
                    <h5>Deductions Policy</h5>
                    <p>Salary deductions are calculated based on absent days (excluding approved leaves).</p>
                </div>
                <div class="info-card">
                    <h5>Contact HR</h5>
                    <p>For any salary-related queries, please contact the HR department.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Set max month to current month
        document.getElementById('month').max = new Date().toISOString().slice(0, 7);
    </script>
</body>
</html>