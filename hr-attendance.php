<?php
include 'includes/config.php';
redirectIfNotLoggedIn();

date_default_timezone_set('Asia/Dhaka');


if (!isHR()) {
    header('Location: dashboard.php');
    exit();
}

$success = '';
$error = '';
$selected_date = $_GET['date'] ?? date('Y-m-d');
$today = date('Y-m-d');

// Check if selected date is editable
$is_editable = ($selected_date === $today);

// Handle attendance submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_attendance']) && $is_editable) {
    $attendance_date = $_POST['attendance_date'];
    $attendances = $_POST['attendance'];

    try {
        $pdo->beginTransaction();

        foreach ($attendances as $employee_id => $status) {
            $check_stmt = $pdo->prepare("
                SELECT id 
                FROM attendance 
                WHERE employee_id = ? 
                AND date = ?
            ");
            $check_stmt->execute([$employee_id, $attendance_date]);
            $existing = $check_stmt->fetch();

            if ($existing) {
                $stmt = $pdo->prepare("
                    UPDATE attendance 
                    SET status = ?, recorded_by = ?, recorded_at = NOW() 
                    WHERE id = ?
                ");
                $stmt->execute([$status, $_SESSION['user_id'], $existing['id']]);
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO attendance (employee_id, date, status, recorded_by, recorded_at)
                    VALUES (?, ?, ?, ?, NOW())
                ");
                $stmt->execute([$employee_id, $attendance_date, $status, $_SESSION['user_id']]);
            }
        }

        $pdo->commit();
        $success = "Attendance marked successfully for " . date('M j, Y', strtotime($attendance_date)) . "!";
    } catch (PDOException $e) {
        $pdo->rollBack();
        $error = "Error marking attendance: " . $e->getMessage();
    }
}

// Fetch employees
$employees_stmt = $pdo->query("
    SELECT e.*
    FROM employees e
    ORDER BY e.department, e.first_name
");
$employees = $employees_stmt->fetchAll();
$total_employees = count($employees);

// Fetch attendance for the selected date
$attendance_data = [];
if ($employees) {
    $employee_ids = array_column($employees, 'id');
    $placeholders = str_repeat('?,', count($employee_ids) - 1) . '?';
    
    $attendance_stmt = $pdo->prepare("
        SELECT employee_id, status 
        FROM attendance 
        WHERE employee_id IN ($placeholders) AND date = ?
    ");
    $attendance_stmt->execute(array_merge($employee_ids, [$selected_date]));

    while ($row = $attendance_stmt->fetch()) {
        $attendance_data[$row['employee_id']] = $row['status'];
    }
}

// Attendance statistics
try {
    $stat_query = $pdo->prepare("
        SELECT status, COUNT(*) as count 
        FROM attendance 
        WHERE date = ?
        GROUP BY status
    ");
    $stat_query->execute([$selected_date]);
    $attendance_stats = $stat_query->fetchAll();
} catch (PDOException $e) {
    $attendance_stats = [];
}

// Calculate Present and Absent
$present_count = 0;
foreach ($attendance_stats as $stat) {
    if ($stat['status'] === 'present') $present_count = (int)$stat['count'];
}
$absent_count = max(0, $total_employees - $present_count);
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Attendance Management - HR System</title>
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
                <li><a href="hr-employees.php">Employees</a></li>
                <li><a href="hr-leave-approval.php">Leave Approval</a></li>
                <li><a href="hr-attendance.php" class="active">Attendance</a></li>
                <li><a href="hr-salary.php">Salary</a></li>
                <li><a href="logout.php">Logout (<?php echo $_SESSION['username']; ?>)</a></li>
            </ul>
        </nav>
    </div>
</header>

<div class="container">
<h2>Attendance Management</h2>

<?php if ($success): ?>
<div class="alert alert-success"><?php echo $success; ?></div>
<?php endif; ?>
<?php if ($error): ?>
<div class="alert alert-error"><?php echo $error; ?></div>
<?php endif; ?>

<!-- Attendance Statistics -->
<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Employees</h3>
        <p><?php echo $total_employees; ?></p>
    </div>
    <div class="stat-card present">
        <h3>Present (<?php echo date('M j, Y', strtotime($selected_date)); ?>)</h3>
        <p><?php echo $present_count; ?></p>
    </div>
    <div class="stat-card absent">
        <h3>Absent (<?php echo date('M j, Y', strtotime($selected_date)); ?>)</h3>
        <p><?php echo $absent_count; ?></p>
    </div>
</div>

<div class="hr-section">
    <div class="date-selection">
        <form method="GET" action="" class="date-form">
            <label for="date">Select Date:</label>
            <input type="date" id="date" name="date" value="<?php echo $selected_date; ?>" max="<?php echo date('Y-m-d'); ?>">
            <button type="submit" class="btn btn-primary">Load Attendance</button>
        </form>
    </div>

    <?php if (!$is_editable): ?>
        <div class="alert alert-info">
            Attendance for <?php echo date('M j, Y', strtotime($selected_date)); ?> is <strong>read-only</strong>.
        </div>
    <?php endif; ?>

    <?php if ($employees): ?>
    <form method="POST" action="">
        <input type="hidden" name="attendance_date" value="<?php echo $selected_date; ?>">

        <div class="attendance-table-container">
            <table class="attendance-table">
                <thead>
                    <tr>
                        <th>Employee ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Last Marked</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($employees as $employee): 
                        $current_status = $attendance_data[$employee['id']] ?? 'absent';

                        $last_record = null;
                        try {
                            $last_attendance = $pdo->prepare("
                                SELECT recorded_at 
                                FROM attendance 
                                WHERE employee_id = ? 
                                ORDER BY date DESC LIMIT 1
                            ");
                            $last_attendance->execute([$employee['id']]);
                            $last_record = $last_attendance->fetch();
                        } catch (PDOException $e) { }
                    ?>
                    <tr>
                        <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                        <td><strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong></td>
                        <td><?php echo htmlspecialchars($employee['department']); ?></td>
                        <td><?php echo htmlspecialchars($employee['position']); ?></td>
                        <td>
                            <select name="attendance[<?php echo $employee['id']; ?>]" class="status-select status-<?php echo $current_status; ?>" <?php echo !$is_editable ? 'disabled' : ''; ?>>
                                <option value="present" <?php echo $current_status == 'present' ? 'selected' : ''; ?>>Present</option>
                                <option value="absent" <?php echo $current_status == 'absent' ? 'selected' : ''; ?>>Absent</option>
                            </select>
                        </td>
                        <td>
                            <?php if ($last_record && $last_record['recorded_at']): ?>
                                <?php echo date('M j, Y', strtotime($last_record['recorded_at'])); ?>
                            <?php else: ?>
                                Never
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($is_editable): ?>
        <div class="form-actions">
            <button type="submit" name="mark_attendance" class="btn btn-primary">Save Attendance</button>
        </div>
        <?php endif; ?>
    </form>
    <?php else: ?>
        <div class="no-data">
            <p>No employees found in the system.</p>
            <a href="hr-employees.php" class="btn btn-primary">Manage Employees</a>
        </div>
    <?php endif; ?>
</div>

<script>
document.querySelectorAll('.status-select').forEach(select => {
    select.addEventListener('change', function() {
        this.className = 'status-select status-' + this.value;
    });
});
document.getElementById('date').max = new Date().toISOString().split('T')[0];
</script>
</body>
</html>
