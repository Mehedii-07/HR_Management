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

// Get all employees with user info
$stmt = $pdo->query("
    SELECT e.*, u.username, u.email as user_email, u.created_at as user_created 
    FROM employees e 
    JOIN users u ON e.user_id = u.id 
    ORDER BY e.join_date DESC
");
$employees = $stmt->fetchAll();

// Handle employee update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_employee'])) {
    $employee_id = $_POST['employee_id'];
    $first_name = $_POST['first_name'];
    $last_name = $_POST['last_name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $position = $_POST['position'];
    $department = $_POST['department'];
    $salary = $_POST['salary'];
    
    try {
        $stmt = $pdo->prepare("UPDATE employees SET first_name = ?, last_name = ?, email = ?, phone = ?, position = ?, department = ?, salary = ? WHERE id = ?");
        $stmt->execute([$first_name, $last_name, $email, $phone, $position, $department, $salary, $employee_id]);
        
        $success = "Employee updated successfully!";
        
        // Refresh employee data
        $stmt = $pdo->query("
            SELECT e.*, u.username, u.email as user_email, u.created_at as user_created 
            FROM employees e 
            JOIN users u ON e.user_id = u.id 
            ORDER BY e.join_date DESC
        ");
        $employees = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = "Error updating employee: " . $e->getMessage();
    }
}

// Handle employee deletion
if (isset($_GET['delete_id'])) {
    $delete_id = $_GET['delete_id'];
    
    try {
        // First get user_id to delete from users table as well
        $stmt = $pdo->prepare("SELECT user_id FROM employees WHERE id = ?");
        $stmt->execute([$delete_id]);
        $employee = $stmt->fetch();
        
        if ($employee) {
            // Delete from employees table (cascade will handle related records)
            $stmt = $pdo->prepare("DELETE FROM employees WHERE id = ?");
            $stmt->execute([$delete_id]);
            
            // Also delete from users table
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $stmt->execute([$employee['user_id']]);
            
            $success = "Employee deleted successfully!";
            
            // Refresh employee data
            $stmt = $pdo->query("
                SELECT e.*, u.username, u.email as user_email, u.created_at as user_created 
                FROM employees e 
                JOIN users u ON e.user_id = u.id 
                ORDER BY e.join_date DESC
            ");
            $employees = $stmt->fetchAll();
        }
    } catch (PDOException $e) {
        $error = "Error deleting employee: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Management - HR System</title>
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
        <h2>Employee Management</h2>
        
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error"><?php echo $error; ?></div>
        <?php endif; ?>

        <div class="hr-section">
            <h3>All Employees (<?php echo count($employees); ?>)</h3>
            
            <?php if ($employees): ?>
                <div class="table-container">
                    <table class="hr-table">
                        <thead>
                            <tr>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Position</th>
                                <th>Department</th>
                                <th>Salary</th>
                                <th>Join Date</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($employee['employee_id']); ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($employee['first_name'] . ' ' . $employee['last_name']); ?></strong>
                                    <br><small><?php echo htmlspecialchars($employee['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($employee['position']); ?></td>
                                <td><?php echo htmlspecialchars($employee['department']); ?></td>
                                <td>$<?php echo number_format($employee['salary'], 2); ?></td>
                                <td><?php echo date('M j, Y', strtotime($employee['join_date'])); ?></td>
                                <td><?php echo htmlspecialchars($employee['phone'] ?: 'N/A'); ?></td>
                                <td class="actions">
                                    <button class="btn-edit" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($employee)); ?>)">Edit</button>
                                    <a href="hr-employees.php?delete_id=<?php echo $employee['id']; ?>" class="btn-delete" onclick="return confirm('Are you sure you want to delete this employee?')">Delete</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="no-data">
                    <p>No employees found in the system.</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Edit Employee Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h3>Edit Employee Details</h3>
            <form method="POST" action="">
                <input type="hidden" id="edit_employee_id" name="employee_id">
                
                <div class="form-grid">
                    <div class="form-group">
                        <label for="edit_first_name">First Name *</label>
                        <input type="text" id="edit_first_name" name="first_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_last_name">Last Name *</label>
                        <input type="text" id="edit_last_name" name="last_name" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_email">Email *</label>
                        <input type="email" id="edit_email" name="email" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_phone">Phone</label>
                        <input type="tel" id="edit_phone" name="phone">
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_position">Position *</label>
                        <input type="text" id="edit_position" name="position" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_department">Department *</label>
                        <select id="edit_department" name="department" required>
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
                        <label for="edit_salary">Salary *</label>
                        <input type="number" id="edit_salary" name="salary" step="0.01" required>
                    </div>
                </div>
                
                <div class="form-actions">
                    <button type="submit" name="update_employee" class="btn btn-primary">Update Employee</button>
                    <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal functions
        const modal = document.getElementById('editModal');
        
        function openEditModal(employee) {
            document.getElementById('edit_employee_id').value = employee.id;
            document.getElementById('edit_first_name').value = employee.first_name;
            document.getElementById('edit_last_name').value = employee.last_name;
            document.getElementById('edit_email').value = employee.email;
            document.getElementById('edit_phone').value = employee.phone || '';
            document.getElementById('edit_position').value = employee.position;
            document.getElementById('edit_department').value = employee.department;
            document.getElementById('edit_salary').value = employee.salary;
            
            modal.style.display = 'block';
        }
        
        function closeEditModal() {
            modal.style.display = 'none';
        }
        
        // Close modal when clicking on X
        document.querySelector('.close').onclick = closeEditModal;
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target == modal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>