<?php
include 'includes/config.php';
redirectIfNotLoggedIn();

// Only HR can access this page
if (!isHR()) {
    header('Location: dashboard.php');
    exit();
}

$success = '';
$error = '';

// Handle leave approval/rejection
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $leave_id = $_POST['leave_id'];
    $action = $_POST['action'];
    
    try {
        $status = ($action == 'approve') ? 'approved' : 'rejected';
        
        // Get leave details first
        $leave_stmt = $pdo->prepare("SELECT * FROM leave_applications WHERE id = ?");
        $leave_stmt->execute([$leave_id]);
        $leave_details = $leave_stmt->fetch();
        
        if ($leave_details) {
            // Update leave application status
            $stmt = $pdo->prepare("UPDATE leave_applications SET status = ?, reviewed_by = ?, reviewed_at = NOW() WHERE id = ?");
            $stmt->execute([$status, $_SESSION['user_id'], $leave_id]);
            
            // If approved, update employee's leave balance
            if ($status == 'approved') {
                // Calculate leave days
                $start = new DateTime($leave_details['start_date']);
                $end = new DateTime($leave_details['end_date']);
                $leave_days = $start->diff($end)->days + 1;
                
                // Update employee's leave balance
                $update_stmt = $pdo->prepare("UPDATE employees SET leave_balance = leave_balance - ?, leave_taken = leave_taken + ? WHERE id = ?");
                $update_stmt->execute([$leave_days, $leave_days, $leave_details['employee_id']]);
            }
        }
        
        $success = "Leave application " . $status . " successfully!";
        
    } catch (PDOException $e) {
        $error = "Error processing leave application: " . $e->getMessage();
    }
}

// Get all leave applications with employee info
$stmt = $pdo->query("
    SELECT la.*, 
           e.first_name, e.last_name, e.position, e.department,
           e.employee_id, e.leave_balance
    FROM leave_applications la 
    JOIN employees e ON la.employee_id = e.id 
    ORDER BY 
        CASE WHEN la.status = 'pending' THEN 1 ELSE 2 END,
        la.applied_at DESC
");
$leave_applications = $stmt->fetchAll();

// Count by status
$pending_count = 0;
$approved_count = 0;
$rejected_count = 0;

foreach ($leave_applications as $leave) {
    if ($leave['status'] == 'pending') $pending_count++;
    if ($leave['status'] == 'approved') $approved_count++;
    if ($leave['status'] == 'rejected') $rejected_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Leave Approval - HR System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/hr.css">
</head>
<body>
    <header>
        <div class="container">
            <h1>HR Management System</h1>
            <nav>
                <ul>
                    <li><a href="dashboard.php">Home</a></li>
                    <li><a href="hr-employees.php" class="active">Employees</a></li>
                    <li><a href="hr-leave-approval.php">Leave Approval</a></li>
                    <li><a href="hr-attendance.php">Attendance</a></li>
                    <li><a href="hr-salary.php">Salary</a></li>
                    <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Leave Application Approval</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- Leave Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Applications</h3>
                <p><?php echo count($leave_applications); ?></p>
            </div>
            <div class="stat-card pending">
                <h3>Pending</h3>
                <p><?php echo $pending_count; ?></p>
            </div>
            <div class="stat-card approved">
                <h3>Approved</h3>
                <p><?php echo $approved_count; ?></p>
            </div>
            <div class="stat-card rejected">
                <h3>Rejected</h3>
                <p><?php echo $rejected_count; ?></p>
            </div>
        </div>

        <div class="hr-section">
            <h3>Leave Applications</h3>
            
            <?php if ($leave_applications): ?>
                <div class="leave-applications">
                    <?php foreach ($leave_applications as $leave): 
                        // Calculate leave days for display
                        $start = new DateTime($leave['start_date']);
                        $end = new DateTime($leave['end_date']);
                        $duration = $start->diff($end)->days + 1;
                    ?>
                    <div class="leave-card status-<?php echo $leave['status']; ?>">
                        <div class="leave-header">
                            <div class="employee-info">
                                <h4><?php echo htmlspecialchars($leave['first_name'] . ' ' . $leave['last_name']); ?></h4>
                                <p class="employee-details">
                                    ID: <?php echo htmlspecialchars($leave['employee_id']); ?> | 
                                    <?php echo htmlspecialchars($leave['position']); ?> | 
                                    <?php echo htmlspecialchars($leave['department']); ?>
                                    <?php if ($leave['status'] == 'pending'): ?>
                                    | <strong>Leave Balance: <?php echo $leave['leave_balance']; ?> days</strong>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div class="leave-status">
                                <span class="status-badge status-<?php echo $leave['status']; ?>">
                                    <?php echo ucfirst($leave['status']); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="leave-details">
                            <div class="detail-item">
                                <label>Leave Type:</label>
                                <span class="leave-type <?php echo $leave['leave_type']; ?>">
                                    <?php echo ucfirst($leave['leave_type']); ?> Leave
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Duration:</label>
                                <span>
                                    <?php echo date('M j, Y', strtotime($leave['start_date'])); ?> 
                                    to 
                                    <?php echo date('M j, Y', strtotime($leave['end_date'])); ?>
                                    (<?php echo $duration . ' day' . ($duration > 1 ? 's' : ''); ?>)
                                </span>
                            </div>
                            <div class="detail-item">
                                <label>Applied On:</label>
                                <span><?php echo date('M j, Y g:i A', strtotime($leave['applied_at'])); ?></span>
                            </div>
                            <div class="detail-item full-width">
                                <label>Reason:</label>
                                <p class="reason"><?php echo htmlspecialchars($leave['reason']); ?></p>
                            </div>
                            
                            <?php if ($leave['status'] == 'pending'): ?>
                            <div class="leave-actions">
                                <form method="POST" action="" class="action-form">
                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                    <input type="hidden" name="action" value="approve">
                                    <button type="submit" class="btn-approve" onclick="return confirm('Approve this leave application of <?php echo $duration; ?> days?')">Approve</button>
                                </form>
                                <form method="POST" action="" class="action-form">
                                    <input type="hidden" name="leave_id" value="<?php echo $leave['id']; ?>">
                                    <input type="hidden" name="action" value="reject">
                                    <button type="submit" class="btn-reject" onclick="return confirm('Reject this leave application?')">Reject</button>
                                </form>
                            </div>
                            <?php elseif ($leave['reviewed_at']): ?>
                            <div class="review-info">
                                <p><strong>Reviewed on:</strong> <?php echo date('M j, Y g:i A', strtotime($leave['reviewed_at'])); ?></p>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>No leave applications found.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>