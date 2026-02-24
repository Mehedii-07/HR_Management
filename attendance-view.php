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

// Get attendance for selected month
$attendance_records = [];
try {
    $attendance_stmt = $pdo->prepare("
        SELECT date, status, check_in, check_out, notes 
        FROM attendance 
        WHERE employee_id = ? AND DATE_FORMAT(date, '%Y-%m') = ?
        ORDER BY date DESC
    ");
    $attendance_stmt->execute([$employee['id'], $selected_month]);
    $attendance_records = $attendance_stmt->fetchAll();
} catch (PDOException $e) {
    // If attendance table doesn't exist, show empty
    $attendance_records = [];
}

// Calculate attendance statistics
$present_count = 0;
$absent_count = 0;
$half_day_count = 0;
$total_working_days = 0;

// Get all days in the selected month
$start_date = new DateTime($selected_month . '-01');
$end_date = new DateTime($selected_month . '-' . $start_date->format('t'));
$interval = new DateInterval('P1D');
$period = new DatePeriod($start_date, $interval, $end_date);

foreach ($period as $date) {
    // Count only weekdays (Monday to Friday)
    if ($date->format('N') < 6) {
        $total_working_days++;
    }
}

foreach ($attendance_records as $record) {
    if ($record['status'] == 'present') $present_count++;
    if ($record['status'] == 'absent') $absent_count++;
    if ($record['status'] == 'half_day') $half_day_count++;
}

$attendance_percentage = $total_working_days > 0 ? round(($present_count / $total_working_days) * 100, 2) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Attendance - HR Management System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/attendance.css">
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
        <h2>My Attendance</h2>

        <!-- Attendance Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Working Days</h3>
                <p><?php echo $total_working_days; ?></p>
                <small>This Month</small>
            </div>
            <div class="stat-card present">
                <h3>Present</h3>
                <p><?php echo $present_count; ?></p>
                <small>Days</small>
            </div>
            <div class="stat-card absent">
                <h3>Absent</h3>
                <p><?php echo $absent_count; ?></p>
                <small>Days</small>
            </div>
            <div class="stat-card percentage">
                <h3>Attendance</h3>
                <p><?php echo $attendance_percentage; ?>%</p>
                <small>Rate</small>
            </div>
        </div>

        <!-- Month Selection -->
        <div class="month-selection">
            <form method="GET" action="" class="month-form">
                <label for="month">Select Month:</label>
                <input type="month" id="month" name="month" value="<?php echo $selected_month; ?>" max="<?php echo date('Y-m'); ?>">
                <button type="submit" class="btn btn-primary">Load Attendance</button>
            </form>
        </div>

        <div class="attendance-section">
            <h3>Attendance Records for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></h3>
            
            <?php if ($attendance_records): ?>
                <div class="attendance-calendar">
                    <div class="calendar-header">
                        <div class="day-header">Sun</div>
                        <div class="day-header">Mon</div>
                        <div class="day-header">Tue</div>
                        <div class="day-header">Wed</div>
                        <div class="day-header">Thu</div>
                        <div class="day-header">Fri</div>
                        <div class="day-header">Sat</div>
                    </div>
                    <div class="calendar-grid">
                        <?php
                        $first_day = new DateTime($selected_month . '-01');
                        $last_day = new DateTime($selected_month . '-' . $first_day->format('t'));
                        
                        // Add empty cells for days before the first day of month
                        $first_day_of_week = $first_day->format('w');
                        for ($i = 0; $i < $first_day_of_week; $i++) {
                            echo '<div class="calendar-day empty"></div>';
                        }
                        
                        // Create calendar days
                        $current_date = clone $first_day;
                        while ($current_date <= $last_day) {
                            $date_str = $current_date->format('Y-m-d');
                            $day_attendance = null;
                            
                            // Find attendance for this day
                            foreach ($attendance_records as $record) {
                                if ($record['date'] == $date_str) {
                                    $day_attendance = $record;
                                    break;
                                }
                            }
                            
                            $status_class = $day_attendance ? 'status-' . $day_attendance['status'] : 'status-future';
                            $is_weekend = $current_date->format('N') >= 6;
                            $is_today = $date_str == date('Y-m-d');
                            
                            echo '<div class="calendar-day ' . $status_class . ' ' . ($is_weekend ? 'weekend' : '') . ' ' . ($is_today ? 'today' : '') . '">';
                            echo '<div class="day-number">' . $current_date->format('j') . '</div>';
                            if ($day_attendance) {
                                echo '<div class="day-status">' . ucfirst($day_attendance['status']) . '</div>';
                            } elseif ($current_date < new DateTime() && !$is_weekend) {
                                echo '<div class="day-status">Not Marked</div>';
                            }
                            echo '</div>';
                            
                            $current_date->modify('+1 day');
                        }
                        ?>
                    </div>
                </div>

                <!-- Attendance Details Table -->
                <div class="attendance-details">
                    <h4>Detailed Attendance Records</h4>
                    <table class="attendance-table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Day</th>
                                <th>Status</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($attendance_records as $record): ?>
                            <tr>
                                <td><?php echo date('M j, Y', strtotime($record['date'])); ?></td>
                                <td><?php echo date('l', strtotime($record['date'])); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo $record['status']; ?>">
                                        <?php echo ucfirst($record['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo $record['check_in'] ?: 'N/A'; ?></td>
                                <td><?php echo $record['check_out'] ?: 'N/A'; ?></td>
                                <td><?php echo htmlspecialchars($record['notes'] ?: 'No notes'); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>No attendance records found for <?php echo date('F Y', strtotime($selected_month . '-01')); ?>.</p>
                    <p>Please contact HR if you believe this is an error.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Attendance Legend -->
        <div class="attendance-legend">
            <h4>Legend</h4>
            <div class="legend-items">
                <div class="legend-item">
                    <span class="status-indicator status-present"></span>
                    <span>Present</span>
                </div>
                <div class="legend-item">
                    <span class="status-indicator status-absent"></span>
                    <span>Absent</span>
                </div>
                <div class="legend-item">
                    <span class="status-indicator status-half_day"></span>
                    <span>Half Day</span>
                </div>
                <div class="legend-item">
                    <span class="status-indicator status-future"></span>
                    <span>Future/Weekend</span>
                </div>
                <div class="legend-item">
                    <span class="status-indicator today"></span>
                    <span>Today</span>
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