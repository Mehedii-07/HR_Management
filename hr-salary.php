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
$selected_month = $_GET['month'] ?? date('Y-m');

// Helper to detect AJAX
function is_ajax() {
    return !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
}

// ---------------------------
// Handle salary allocation
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['allocate_salary'])) {
    $employee_id = (int) ($_POST['employee_id'] ?? 0);
    $basic_salary = (float) ($_POST['basic_salary'] ?? 0);
    $allowances = (float) ($_POST['allowances'] ?? 0);
    $deductions = (float) ($_POST['deductions'] ?? 0);
    $month_year = $_POST['month_year'] ?? '';
    $net_salary = $basic_salary + $allowances - $deductions;
    $payroll_id = null;

    try {
        // Check if salary already allocated for this month
        $check_stmt = $pdo->prepare("SELECT id FROM payroll WHERE employee_id = ? AND month_year = ?");
        $check_stmt->execute([$employee_id, $month_year]);
        $existing = $check_stmt->fetch();

        if ($existing) {
            // Update existing salary and ensure status = pending
            $stmt = $pdo->prepare("UPDATE payroll SET basic_salary = ?, allowances = ?, deductions = ?, net_salary = ?, status = 'pending' WHERE id = ?");
            $stmt->execute([$basic_salary, $allowances, $deductions, $net_salary, $existing['id']]);
            $payroll_id = (int)$existing['id'];
            $success = "Salary updated successfully!";
        } else {
            // Insert new salary (pending)
            $stmt = $pdo->prepare("INSERT INTO payroll (employee_id, month_year, basic_salary, allowances, deductions, net_salary, status) VALUES (?, ?, ?, ?, ?, ?, 'pending')");
            $stmt->execute([$employee_id, $month_year, $basic_salary, $allowances, $deductions, $net_salary]);
            $payroll_id = (int)$pdo->lastInsertId();
            $success = "Salary allocated successfully!";
        }

        if (is_ajax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $success,
                'payroll_id' => $payroll_id,
                'net_salary' => $net_salary,
                'employee_id' => $employee_id,
                'status' => 'pending'
            ]);
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error allocating salary: " . $e->getMessage();
        if (is_ajax()) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

// ---------------------------
// Handle salary processing (mark as paid)
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_payment'])) {
    $payroll_id = (int) ($_POST['payroll_id'] ?? 0);

    try {
        // fetch net_salary and employee_id for response / stats update
        $fetch = $pdo->prepare("SELECT net_salary, employee_id FROM payroll WHERE id = ?");
        $fetch->execute([$payroll_id]);
        $row = $fetch->fetch();
        $net_salary = $row ? (float)$row['net_salary'] : 0.0;
        $employee_id = $row ? (int)$row['employee_id'] : null;

        $stmt = $pdo->prepare("UPDATE payroll SET status = 'paid', payment_date = CURDATE() WHERE id = ?");
        $stmt->execute([$payroll_id]);
        $success = "Salary marked as paid!";

        if (is_ajax()) {
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'message' => $success,
                'payroll_id' => $payroll_id,
                'net_salary' => $net_salary,
                'employee_id' => $employee_id,
                'status' => 'paid'
            ]);
            exit;
        }
    } catch (PDOException $e) {
        $error = "Error processing payment: " . $e->getMessage();
        if (is_ajax()) {
            header('Content-Type: application/json', true, 500);
            echo json_encode(['success' => false, 'message' => $error]);
            exit;
        }
    }
}

// ---------------------------
// Fetch data (optimized)
// ---------------------------

// 1) All employees
$employees_stmt = $pdo->query("
    SELECT e.id, e.user_id, e.employee_id AS emp_code, e.first_name, e.last_name, e.salary, e.department
    FROM employees e
    ORDER BY e.department, e.first_name
");
$employees = $employees_stmt->fetchAll(PDO::FETCH_ASSOC);

// Build employee id list
$employee_ids = array_map(function($r){ return (int)$r['id']; }, $employees);

// 2) Payroll for selected month (single query), map by employee_id
$payroll_data = [];
$total_paid = 0.0;
$total_pending = 0.0;

if (!empty($employee_ids)) {
    $payroll_stmt = $pdo->prepare("
        SELECT p.*, p.employee_id AS emp_id
        FROM payroll p
        WHERE p.month_year = ?
        AND p.employee_id IN (" . implode(',', array_fill(0, count($employee_ids), '?')) . ")
    ");
    $params = array_merge([$selected_month], $employee_ids);
    $payroll_stmt->execute($params);
    $rows = $payroll_stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($rows as $r) {
        $payroll_data[(int)$r['emp_id']] = $r;
        if ($r['status'] == 'paid') $total_paid += (float)$r['net_salary'];
        else $total_pending += (float)$r['net_salary'];
    }
} else {
    $payroll_data = [];
}

// 3) Attendance: run one grouped query for absent days for the month
$attendance_summary = [];
if (!empty($employee_ids)) {
    // Prepare placeholders
    $placeholders = implode(',', array_fill(0, count($employee_ids), '?'));
    $sql = "
        SELECT employee_id, COUNT(*) as absent_days
        FROM attendance
        WHERE DATE_FORMAT(date, '%Y-%m') = ?
          AND status = 'absent'
          AND employee_id IN ($placeholders)
        GROUP BY employee_id
    ";
    $params = array_merge([$selected_month], $employee_ids);
    $att_stmt = $pdo->prepare($sql);
    $att_stmt->execute($params);
    $att_rows = $att_stmt->fetchAll(PDO::FETCH_ASSOC);
    // initialize with zeros
    foreach ($employee_ids as $eid) {
        $attendance_summary[$eid] = ['absent_days' => 0, 'deduction_amount' => 0.0];
    }
    foreach ($att_rows as $ar) {
        $eid = (int)$ar['employee_id'];
        $days = (int)$ar['absent_days'];
        // ded = days * salary/30 (salary will be fetched from employee)
        $attendance_summary[$eid] = ['absent_days' => $days, 'deduction_amount' => $days]; // placeholder; final compute below
    }
    // Now compute deduction_amount using employee salary
    foreach ($employees as $emp) {
        $eid = (int)$emp['id'];
        $salary = (float)$emp['salary'];
        $days = $attendance_summary[$eid]['absent_days'] ?? 0;
        $attendance_summary[$eid]['deduction_amount'] = $days * ($salary / 30.0);
    }
} else {
    $attendance_summary = [];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Management - HR System</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/hr.css">
    <style>
        /* small helpers */
        .status-badge { padding:4px 8px; border-radius:6px; display:inline-block; font-size:0.9rem; }
        .status-not_allocated { background:#eee; color:#333; }
        .status-pending { background:#fff4cc; color:#8a6d00; }
        .status-paid { background:#dff0d8; color:#3c763d; }
        .btn-pay { background:#28a745; color:#fff; padding:6px 10px; border:none; border-radius:4px; cursor:pointer; }
        .btn-edit { background:#007bff; color:#fff; padding:6px 10px; border:none; border-radius:4px; cursor:pointer; margin-right:6px; }
    </style>
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
                    <li><a href="logout.php">Logout (<?php echo htmlspecialchars($_SESSION['username']); ?>)</a></li>
                </ul>
            </nav>
        </div>
    </header>

    <div class="container">
        <h2>Salary Management</h2>

        <?php if ($success && !is_ajax()): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error && !is_ajax()): ?>
            <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <!-- Salary Statistics -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Employees</h3>
                <p id="stat-total-employees"><?php echo count($employees); ?></p>
            </div>
            <div class="stat-card paid">
                <h3>Paid This Month</h3>
                <p id="stat-paid">$<?php echo number_format($total_paid, 2); ?></p>
            </div>
            <div class="stat-card pending">
                <h3>Pending Payment</h3>
                <p id="stat-pending">$<?php echo number_format($total_pending, 2); ?></p>
            </div>
            <div class="stat-card">
                <h3>Selected Month</h3>
                <p><?php echo date('F Y', strtotime($selected_month . '-01')); ?></p>
            </div>
        </div>

        <div class="hr-section">
            <!-- Month Selection -->
            <div class="date-selection">
                <form method="GET" action="" class="date-form">
                    <label for="month">Select Month:</label>
                    <input type="month" id="month" name="month" value="<?php echo htmlspecialchars($selected_month); ?>" max="<?php echo date('Y-m'); ?>">
                    <button type="submit" class="btn btn-primary">Load Salary Data</button>
                </form>
            </div>

            <h3>Salary Allocation for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></h3>

            <?php if ($employees): ?>
                <div class="salary-table-container">
                    <table class="salary-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Basic Salary</th>
                                <th>Absent Days</th>
                                <th>Deductions</th>
                                <th>Allowances</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="salary-table-body">
                            <?php foreach ($employees as $employee):
                                $eid = (int)$employee['id'];
                                $payroll = $payroll_data[$eid] ?? null;
                                $attendance = $attendance_summary[$eid] ?? ['absent_days'=>0,'deduction_amount'=>0.0];
                                $basic_salary = $payroll ? (float)$payroll['basic_salary'] : (float)$employee['salary'];
                                $allowances = $payroll ? (float)$payroll['allowances'] : 0.0;
                                $deductions = $payroll ? (float)$payroll['deductions'] : (float)$attendance['deduction_amount'];
                                $net_salary = $basic_salary + $allowances - $deductions;
                                $status = $payroll ? $payroll['status'] : 'not_allocated';
                                // short safe values for data attributes
                                $emp_first = htmlspecialchars($employee['first_name'], ENT_QUOTES);
                                $emp_last = htmlspecialchars($employee['last_name'], ENT_QUOTES);
                                $dept = htmlspecialchars($employee['department'], ENT_QUOTES);
                            ?>
                            <tr id="row-emp-<?php echo $eid; ?>" data-emp-id="<?php echo $eid; ?>"
                                data-emp-first="<?php echo $emp_first; ?>" data-emp-last="<?php echo $emp_last; ?>"
                                data-emp-salary="<?php echo (float)$employee['salary']; ?>" data-emp-dept="<?php echo $dept; ?>"
                                <?php if ($payroll): ?>
                                    data-payroll-id="<?php echo (int)$payroll['id']; ?>"
                                    data-payroll-basic="<?php echo (float)$basic_salary; ?>"
                                    data-payroll-allowances="<?php echo (float)$allowances; ?>"
                                    data-payroll-deductions="<?php echo (float)$deductions; ?>"
                                    data-payroll-net="<?php echo (float)$net_salary; ?>"
                                    data-payroll-status="<?php echo htmlspecialchars($status, ENT_QUOTES); ?>"
                                <?php endif; ?>>
                                <td>
                                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                    <br><small>ID: <?php echo htmlspecialchars($employee['emp_code']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td>$<?php echo number_format($basic_salary, 2); ?></td>
                                <td class="<?php echo $attendance['absent_days'] > 0 ? 'text-warning' : ''; ?>">
                                    <?php echo (int)$attendance['absent_days']; ?> days
                                </td>
                                <td class="text-danger">$<?php echo number_format($deductions, 2); ?></td>
                                <td class="text-success">$<?php echo number_format($allowances, 2); ?></td>
                                <td class="text-primary"><strong>$<?php echo number_format($net_salary, 2); ?></strong></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($status); ?>" id="status-<?php echo $eid; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $status)); ?>
                                    </span>
                                </td>
                                <td class="actions" id="actions-<?php echo $eid; ?>">
                                    <?php if (!$payroll): ?>
                                        <button class="btn-edit allocate-btn" data-eid="<?php echo $eid; ?>">Allocate</button>
                                    <?php else: ?>
                                        <?php if ($payroll['status'] === 'pending'): ?>
                                            <button class="btn-edit edit-btn" data-eid="<?php echo $eid; ?>">Edit</button>
                                            <form method="POST" action="" class="pay-form" style="display:inline;">
                                                <input type="hidden" name="payroll_id" value="<?php echo (int)$payroll['id']; ?>">
                                                <button type="submit" name="process_payment" class="btn-pay">Pay</button>
                                            </form>
                                        <?php elseif ($payroll['status'] === 'paid'): ?>
                                            <span class="text-muted">Paid</span>
                                        <?php else: ?>
                                            <button class="btn-edit edit-btn" data-eid="<?php echo $eid; ?>">Edit</button>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>No employees found in the system.</p>
                    <a href="hr-employees.php" class="btn btn-primary">Manage Employees</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Salary Summary -->
        <div class="hr-section">
            <h3>Salary Summary for <?php echo date('F Y', strtotime($selected_month . '-01')); ?></h3>
            <div class="summary-cards">
                <div class="summary-card">
                    <h4>Total Basic Salary</h4>
                    <p class="amount">$<?php 
                        $total_basic = array_sum(array_column($employees, 'salary'));
                        echo number_format($total_basic, 2); 
                    ?></p>
                </div>
                <div class="summary-card">
                    <h4>Total Allowances</h4>
                    <p class="amount text-success">$<?php 
                        $total_allowances = array_sum(array_column($payroll_data, 'allowances'));
                        echo number_format($total_allowances, 2); 
                    ?></p>
                </div>
                <div class="summary-card">
                    <h4>Total Deductions</h4>
                    <p class="amount text-danger">$<?php 
                        $total_deductions = array_sum(array_column($payroll_data, 'deductions'));
                        echo number_format($total_deductions, 2); 
                    ?></p>
                </div>
                <div class="summary-card">
                    <h4>Net Payable</h4>
                    <p class="amount text-primary">$<?php 
                        $net_payable = $total_basic + $total_allowances - $total_deductions;
                        echo number_format($net_payable, 2); 
                    ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Salary Allocation Modal -->
    <div id="salaryModal" class="modal" style="display:none;">
        <div class="modal-content">
            <span class="close" style="cursor:pointer;">&times;</span>
            <h3>Allocate Salary</h3>
            <form method="POST" action="" id="salaryForm">
                <input type="hidden" name="employee_id" id="modal_employee_id">
                <input type="hidden" name="month_year" id="modal_month_year" value="<?php echo htmlspecialchars($selected_month); ?>">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="modal_employee_name">Employee Name</label>
                        <input type="text" id="modal_employee_name" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_department">Department</label>
                        <input type="text" id="modal_department" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_basic_salary">Basic Salary *</label>
                        <input type="number" id="modal_basic_salary" name="basic_salary" step="0.01" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_allowances">Allowances</label>
                        <input type="number" id="modal_allowances" name="allowances" step="0.01" value="0">
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_absent_days">Absent Days</label>
                        <input type="text" id="modal_absent_days" readonly>
                    </div>
                    
                    <div class="form-group">
                        <label for="modal_deductions">Deductions</label>
                        <input type="number" id="modal_deductions" name="deductions" step="0.01" value="0">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="modal_net_salary">Net Salary</label>
                    <input type="text" id="modal_net_salary" readonly class="net-salary-display">
                </div>

                <div class="form-actions">
                    <button type="submit" name="allocate_salary" class="btn btn-primary">Save Salary</button>
                    <button type="button" class="btn btn-secondary" id="modalCancelBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

<script>
/* Lightweight JS: delegated handlers, small payloads, minimal DOM work */

// helper money parse/format
document.addEventListener("DOMContentLoaded", function () {

    const modal = document.getElementById("salaryModal");
    const closeBtn = document.querySelector(".modal .close");
    const cancelBtn = document.getElementById("modalCancelBtn");
    const form = document.getElementById("salaryForm");

    function openModal() {
        modal.style.display = "block";
    }
    function closeModal() {
        modal.style.display = "none";
    }

    closeBtn.onclick = closeModal;
    cancelBtn.onclick = closeModal;

    // ---------- AUTO CALCULATE NET SALARY ----------
    function updateNet() {
        let b = parseFloat(document.getElementById("modal_basic_salary").value) || 0;
        let a = parseFloat(document.getElementById("modal_allowances").value) || 0;
        let d = parseFloat(document.getElementById("modal_deductions").value) || 0;
        document.getElementById("modal_net_salary").value = (b + a - d).toFixed(2);
    }

    document.getElementById("modal_basic_salary").addEventListener("input", updateNet);
    document.getElementById("modal_allowances").addEventListener("input", updateNet);
    document.getElementById("modal_deductions").addEventListener("input", updateNet);

    // ---------- OPEN MODAL (ALLOCATE) ----------
    document.querySelectorAll(".allocate-btn, .edit-btn").forEach(btn => {
        btn.addEventListener("click", function () {
            const id = this.dataset.eid;
            const row = document.getElementById("row-emp-" + id);

            // Fill modal
            document.getElementById("modal_employee_id").value = id;
            document.getElementById("modal_employee_name").value =
                row.dataset.empFirst + " " + row.dataset.empLast;
            document.getElementById("modal_department").value = row.dataset.empDept;

            let basic = row.dataset.payrollBasic || row.dataset.empSalary;
            document.getElementById("modal_basic_salary").value = basic;

            document.getElementById("modal_allowances").value = row.dataset.payrollAllowances || 0;
            document.getElementById("modal_deductions").value = row.dataset.payrollDeductions || 0;

            document.getElementById("modal_absent_days").value =
                row.querySelector("td:nth-child(4)").textContent;

            updateNet();
            openModal();
        });
    });

    // ---------- AJAX SUBMIT ----------
    form.addEventListener("submit", function (e) {
        e.preventDefault();

        let formData = new FormData(form);
        formData.append("allocate_salary", "1");

        fetch("", {
            method: "POST",
            headers: {"X-Requested-With": "XMLHttpRequest"},
            body: formData
        })
            .then(res => res.json())
            .then(data => {
                if (!data.success) return;

                const id = data.employee_id;
                const row = document.getElementById("row-emp-" + id);
                const tds = row.querySelectorAll("td");

                const basic = parseFloat(formData.get("basic_salary")).toFixed(2);
                const allowances = parseFloat(formData.get("allowances")).toFixed(2);
                const deductions = parseFloat(formData.get("deductions")).toFixed(2);
                const net = parseFloat(data.net_salary).toFixed(2);

                // UPDATE TABLE CELLS
                tds[2].innerHTML = "$" + basic;        // Basic Salary
                tds[4].innerHTML = "$" + deductions;   // Deductions
                tds[5].innerHTML = "$" + allowances;   // Allowances
                tds[6].innerHTML = "<strong>$" + net + "</strong>"; // Net Salary

                // UPDATE STATUS
                tds[7].innerHTML =
                    `<span class="status-badge status-pending" id="status-${id}">Pending</span>`;

                // UPDATE ACTION BUTTONS
                tds[8].innerHTML =
                    `<button class="btn-edit edit-btn" data-eid="${id}">Edit</button>
                     <form method="POST" action="" class="pay-form" style="display:inline;">
                         <input type="hidden" name="payroll_id" value="${data.payroll_id}">
                         <button type="submit" name="process_payment" class="btn-pay">Pay</button>
                     </form>`;

                closeModal();
            })
            .catch(err => console.log("AJAX ERROR:", err));
    });

});

</script>
</body>
</html>
