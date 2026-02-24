<?php
include 'includes/config.php';
redirectIfNotLoggedIn();

// Get user-specific data
$user_id = $_SESSION['user_id'];
$role = $_SESSION['role'];

if (isHR()) {
    // HR Dashboard Data (FIXED QUERIES)
    $total_employees = $pdo->query("SELECT COUNT(*) FROM employees")->fetchColumn();
    $pending_leaves = $pdo->query("SELECT COUNT(*) FROM leave_applications WHERE status = 'pending'")->fetchColumn();
    
    // Fixed attendance query - handle case when table doesn't exist yet
    try {
        $today_attendance = $pdo->query("SELECT COUNT(*) FROM attendance WHERE date = CURDATE() AND status = 'present'")->fetchColumn();
    } catch (PDOException $e) {
        $today_attendance = 0; // Default to 0 if table doesn't exist
    }
    
    // Recent leave applications
    try {
        $recent_leaves = $pdo->query("SELECT la.*, e.first_name, e.last_name 
                                     FROM leave_applications la 
                                     JOIN employees e ON la.employee_id = e.id 
                                     WHERE la.status = 'pending' 
                                     ORDER BY la.applied_at DESC 
                                     LIMIT 5")->fetchAll();
    } catch (PDOException $e) {
        $recent_leaves = []; // Empty array if error
    }
    $employee_absence_query = $pdo->query("
            SELECT e.id, e.first_name, e.last_name, e.position,
            (
                SELECT COUNT(*) 
                FROM attendance a 
                WHERE a.employee_id = e.id
                AND a.status = 'absent'
                AND MONTH(a.date) = MONTH(CURDATE())
                AND YEAR(a.date) = YEAR(CURDATE())
            ) AS total_absent
            FROM employees e
            ORDER BY e.first_name
        ");
        $employee_absence_data = $employee_absence_query->fetchAll();
} else {
    // Employee Dashboard Data
    $employee_data = $pdo->prepare("SELECT * FROM employees WHERE user_id = ?");
    $employee_data->execute([$user_id]);
    $employee = $employee_data->fetch();
    
    if ($employee) {
        $my_leaves = $pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE employee_id = ?");
        $my_leaves->execute([$employee['id']]);
        $my_leaves_count = $my_leaves->fetchColumn();
        
        $approved_leaves = $pdo->prepare("SELECT COUNT(*) FROM leave_applications WHERE employee_id = ? AND status = 'approved'");
        $approved_leaves->execute([$employee['id']]);
        $approved_leaves_count = $approved_leaves->fetchColumn();
        
        // Fixed attendance query for employees
        try {
            $this_month_attendance = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE employee_id = ? AND MONTH(date) = MONTH(CURDATE()) AND YEAR(date) = YEAR(CURDATE()) AND status = 'present'");
            $this_month_attendance->execute([$employee['id']]);
            $attendance_count = $this_month_attendance->fetchColumn();
        } catch (PDOException $e) {
            $attendance_count = 0;
        }
        
        // Recent leave applications by this employee
        $my_recent_leaves = $pdo->prepare("SELECT * FROM leave_applications WHERE employee_id = ? ORDER BY applied_at DESC LIMIT 5");
        $my_recent_leaves->execute([$employee['id']]);
        $recent_leaves = $my_recent_leaves->fetchAll();

            
    }
    
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - HR Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>HR Management System</h1>
            <nav>
                <ul>
                    <!-- Single Home link for both roles -->
                    <li><a href="dashboard.php" class="active">Home</a></li>
                    
                    <?php if (isHR()): ?>
                        <!-- HR Menu -->
                        <li><a href="hr-employees.php">Employees</a></li>
                        <li><a href="hr-leave-approval.php">Leave Approval</a></li>
                        <li><a href="hr-attendance.php">Attendance</a></li>
                        <li><a href="hr-salary.php">Salary</a></li>
                    <?php else: ?>
                        <!-- Employee Menu -->
                        <li><a href="profile.php">My Profile</a></li>
                        <li><a href="apply-leave.php">Apply Leave</a></li>
                        <!-- LEAVE STATUS & POLICY REMOVED -->
                        <li><a href="attendance-view.php">My Attendance</a></li>
                        <li><a href="salary-view.php">My Salary</a></li>
                    <?php endif; ?>
                    
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Welcome to Dashboard, <?php echo $_SESSION['username']; ?>!</h2>
        
        <div class="dashboard-cards">
            <?php if (isHR()): ?>
                <!-- HR Dashboard Cards -->
                <div class="card">
                    <h3>Total Employees</h3>
                    <p><?php echo $total_employees; ?></p>
                    <small>Registered in system</small>
                </div>
                
                <div class="card">
                    <h3>Pending Leaves</h3>
                    <p><?php echo $pending_leaves; ?></p>
                    <small>Awaiting approval</small>
                </div>
                
                <div class="card">
                    <h3>Today's Attendance</h3>
                    <p><?php echo $today_attendance; ?></p>
                    <small>Employees present</small>
                </div>
                
                <div class="card">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="hr-attendance.php" class="btn">Mark Attendance</a>
                        <a href="hr-leave-approval.php" class="btn">Review Leaves</a>
                    </div>
                </div>
                
            <?php else: ?>
                <!-- Employee Dashboard Cards -->
                <?php if ($employee): ?>
                <div class="card">
                    <h3>My Information</h3>
                    <p><?php echo $employee['first_name'] . ' ' . $employee['last_name']; ?></p>
                    <small><?php echo $employee['position']; ?></small>
                </div>
                
                <div class="card">
                    <h3>Leave Applications</h3>
                    <p><?php echo $my_leaves_count; ?></p>
                    <small><?php echo $approved_leaves_count; ?> Approved</small>
                </div>
                
                <div class="card">
                    <h3>This Month Attendance</h3>
                    <p><?php echo $attendance_count; ?></p>
                    <small>Days Present</small>
                </div>
                
                <div class="card">
                    <h3>Quick Actions</h3>
                    <div class="quick-actions">
                        <a href="apply-leave.php" class="btn">Apply for Leave</a>
                        <a href="attendance-view.php" class="btn">View Attendance</a>
                    </div>
                </div>
                <?php else: ?>
                <div class="card profile-incomplete">
                    <h3>Profile Incomplete</h3>
                    <p>Please complete your employee profile to access all features</p>
                    <a href="employee-info.php" class="btn btn-primary">Complete Profile Now</a>
                </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <!-- Recent Activities Section -->
        <div class="recent-activities">
            <h3>Recent Activities</h3>
            <?php if (isHR()): ?>
                <?php if ($recent_leaves): ?>
                    <?php foreach ($recent_leaves as $leave): ?>
                    <div class="activity-item">
                        <strong><?php echo $leave['first_name'] . ' ' . $leave['last_name']; ?></strong> 
                        applied for <?php echo $leave['leave_type']; ?> leave 
                        (<?php echo $leave['start_date'] . ' to ' . $leave['end_date']; ?>)
                        <span class="status-pending">- Pending</span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No pending leave applications.</p>
                <?php endif; ?>
            <?php else: ?>
                <?php if (isset($recent_leaves) && $recent_leaves): ?>
                    <?php foreach ($recent_leaves as $leave): ?>
                    <div class="activity-item">
                        You applied for <strong><?php echo $leave['leave_type']; ?> leave</strong>
                        (<?php echo $leave['start_date'] . ' to ' . $leave['end_date']; ?>)
                        <span class="status-<?php echo $leave['status']; ?>">
                            - <?php echo ucfirst($leave['status']); ?>
                        </span>
                    </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <p>No recent leave applications.</p>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
    </div>
    <?php if (isHR()): ?>
            <div class="recent-activities">
                <h3>Employee Attendance Summary (This Month)</h3>

                <?php if ($employee_absence_data): ?>
                <table class="attendance-table" border="1" cellpadding="10" cellspacing="0" width="100%">
                    <tr>
                        <th>Employee Name</th>
                        <th>Position</th>
                        <th>Total Absences</th>
                    </tr>

                    <?php foreach ($employee_absence_data as $row): ?>
                    <tr>
                        <td><?php echo $row['first_name'].' '.$row['last_name']; ?></td>
                        <td><?php echo $row['position']; ?></td>
                        <td><?php echo $row['total_absent']; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </table>
                <?php else: ?>
                    <p>No employees found.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
</body>
</html>