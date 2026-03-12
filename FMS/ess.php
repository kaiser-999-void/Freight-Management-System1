<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Employee Self Service';
$pdo = getDBConnection();

$activeTab = $_GET['tab'] ?? 'personal-info';
$userId = $_SESSION['user_id'];

// Get personal information
$userInfo = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$userInfo->execute([$userId]);
$personalInfo = $userInfo->fetch();

// Get payslips
$payslips = $pdo->prepare("SELECT * FROM payslips WHERE employee_id = ? ORDER BY period_start DESC LIMIT 3");
$payslips->execute([$userId]);
$myPayslips = $payslips->fetchAll();

// Get leave requests
$leaveRequests = $pdo->prepare("SELECT lr.*, u.full_name as approver_name FROM leave_requests lr LEFT JOIN users u ON lr.approver_id = u.id WHERE lr.employee_id = ? ORDER BY lr.applied_date DESC");
$leaveRequests->execute([$userId]);
$myLeaves = $leaveRequests->fetchAll();

// Get leave balance
$leaveBalanceStmt = $pdo->prepare("SELECT * FROM leave_balance WHERE employee_id = ? AND year = ?");
$leaveBalanceStmt->execute([$userId, date('Y')]);
$balance = $leaveBalanceStmt->fetch();

if (!$balance) {
    // Create default balance
    $createBalance = $pdo->prepare("INSERT INTO leave_balance (employee_id, year) VALUES (?, ?)");
    $createBalance->execute([$userId, date('Y')]);
    $leaveBalanceStmt->execute([$userId, date('Y')]);
    $balance = $leaveBalanceStmt->fetch();
}

// Get attendance records
$attendance = $pdo->prepare("SELECT * FROM attendance_records WHERE employee_id = ? ORDER BY attendance_date DESC LIMIT 10");
$attendance->execute([$userId]);
$myAttendance = $attendance->fetchAll();

// Get notifications
$notifications = $pdo->prepare("SELECT * FROM notifications WHERE employee_id = ? ORDER BY created_at DESC");
$notifications->execute([$userId]);
$myNotifications = $notifications->fetchAll();

$unreadCount = count(array_filter($myNotifications, fn($n) => !$n['is_read']));

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Employee Self Service (ESS)</h1>
        <p class="text-gray-600">
            Manage your personal information, view payslips, and submit leave requests
        </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <div class="flex gap-4 overflow-x-auto">
            <a href="?tab=personal-info" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'personal-info' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-user"></i>
                    <span>Personal Information</span>
                </div>
            </a>
            <a href="?tab=payroll" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'payroll' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-dollar-sign"></i>
                    <span>Payslips & Payroll</span>
                </div>
            </a>
            <a href="?tab=leave" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'leave' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-calendar"></i>
                    <span>Leave & Attendance</span>
                </div>
            </a>
            <a href="?tab=notifications" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'notifications' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($unreadCount > 0): ?>
                        <span class="px-2 py-0.5 bg-red-500 text-white rounded-full text-xs"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </div>
            </a>
        </div>
    </div>

    <!-- Personal Information Tab -->
    <?php if ($activeTab == 'personal-info'): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Personal Information</h2>
                <button onclick="showEditInfoModal()" class="flex items-center gap-2 px-4 py-2 border border-gray-300 rounded-lg hover:bg-gray-50">
                    <i class="fas fa-edit"></i>
                    <span>Edit Information</span>
                </button>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div class="flex items-start gap-3">
                    <div class="bg-indigo-100 p-2 rounded-lg">
                        <i class="fas fa-user text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Full Name</p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['full_name']); ?></p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="bg-indigo-100 p-2 rounded-lg">
                        <i class="fas fa-id-card text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Employee ID</p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['employee_id']); ?></p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="bg-indigo-100 p-2 rounded-lg">
                        <i class="fas fa-envelope text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Email Address</p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['email']); ?></p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="bg-indigo-100 p-2 rounded-lg">
                        <i class="fas fa-phone text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Phone Number</p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['phone'] ?? 'N/A'); ?></p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="bg-indigo-100 p-2 rounded-lg">
                        <i class="fas fa-map-marker-alt text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Address</p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['address'] ?? 'N/A'); ?></p>
                        <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['city'] ?? ''); ?></p>
                    </div>
                </div>

                <div class="flex items-start gap-3">
                    <div class="bg-indigo-100 p-2 rounded-lg">
                        <i class="fas fa-birthday-cake text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-gray-600 text-sm mb-1">Date of Birth</p>
                        <p class="text-gray-900"><?php echo formatDate($personalInfo['date_of_birth']); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Employment Details</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <p class="text-gray-600 text-sm mb-1">Department</p>
                    <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['department']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm mb-1">Position</p>
                    <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['position']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm mb-1">Hire Date</p>
                    <p class="text-gray-900"><?php echo formatDate($personalInfo['hire_date']); ?></p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm mb-1">Employment Type</p>
                    <p class="text-gray-900"><?php echo htmlspecialchars($personalInfo['employment_type']); ?></p>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <!-- Payroll Tab -->
    <?php if ($activeTab == 'payroll'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Payslips & Payroll Information</h2>
            <div class="space-y-4">
                <?php foreach ($myPayslips as $payslip): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($payslip['month']); ?></h3>
                                <p class="text-gray-600 text-sm"><?php echo formatDate($payslip['period_start']); ?> - <?php echo formatDate($payslip['period_end']); ?></p>
                            </div>
                            <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                                <?php echo htmlspecialchars($payslip['status']); ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                            <div class="bg-blue-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Gross Pay</p>
                                <p class="text-blue-700 font-semibold"><?php echo formatCurrency($payslip['gross_pay']); ?></p>
                            </div>
                            <div class="bg-red-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Deductions</p>
                                <p class="text-red-700 font-semibold"><?php echo formatCurrency($payslip['deductions']); ?></p>
                            </div>
                            <div class="bg-green-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Net Pay</p>
                                <p class="text-green-700 font-semibold"><?php echo formatCurrency($payslip['net_pay']); ?></p>
                            </div>
                        </div>
                        <button class="flex items-center gap-2 text-indigo-600 hover:text-indigo-700">
                            <i class="fas fa-download"></i>
                            <span>Download Payslip</span>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Leave & Attendance Tab -->
    <?php if ($activeTab == 'leave'): ?>
        <?php if ($balance): ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Annual Leave</h3>
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total</span>
                            <span class="text-gray-900"><?php echo $balance['annual_total']; ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Used</span>
                            <span class="text-red-600"><?php echo $balance['annual_used']; ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Remaining</span>
                            <span class="text-green-600"><?php echo $balance['annual_remaining']; ?> days</span>
                        </div>
                    </div>
                    <div class="bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full leave-bar" style="width: 0%" data-target="<?php echo ($balance['annual_remaining'] / $balance['annual_total']) * 100; ?>"></div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Sick Leave</h3>
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total</span>
                            <span class="text-gray-900"><?php echo $balance['sick_total']; ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Used</span>
                            <span class="text-red-600"><?php echo $balance['sick_used']; ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Remaining</span>
                            <span class="text-green-600"><?php echo $balance['sick_remaining']; ?> days</span>
                        </div>
                    </div>
                    <div class="bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full leave-bar" style="width: 0%" data-target="<?php echo ($balance['sick_remaining'] / $balance['sick_total']) * 100; ?>"></div>
                    </div>
                </div>

                <div class="bg-white rounded-lg shadow p-6">
                    <h3 class="text-lg font-semibold text-gray-900 mb-4">Personal Leave</h3>
                    <div class="space-y-2 mb-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Total</span>
                            <span class="text-gray-900"><?php echo $balance['personal_total']; ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Used</span>
                            <span class="text-red-600"><?php echo $balance['personal_used']; ?> days</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Remaining</span>
                            <span class="text-green-600"><?php echo $balance['personal_remaining']; ?> days</span>
                        </div>
                    </div>
                    <div class="bg-gray-200 rounded-full h-2">
                        <div class="bg-green-500 h-2 rounded-full leave-bar" style="width: 0%" data-target="<?php echo ($balance['personal_remaining'] / $balance['personal_total']) * 100; ?>"></div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Leave Requests</h2>
                <button onclick="showRequestLeaveModal()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    Request Leave
                </button>
            </div>
            <div class="space-y-4">
                <?php foreach ($myLeaves as $leave): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($leave['leave_type']); ?></h3>
                                <p class="text-gray-600 text-sm">
                                    <?php echo formatDate($leave['start_date']); ?> - <?php echo formatDate($leave['end_date']); ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($leave['status']); ?>">
                                <?php echo htmlspecialchars($leave['status']); ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Duration</p>
                                <p class="text-gray-900"><?php echo $leave['days']; ?> days</p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Applied Date</p>
                                <p class="text-gray-900"><?php echo formatDate($leave['applied_date']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Approver</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($leave['approver_name'] ?? 'N/A'); ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Recent Attendance</h2>
            <div class="overflow-x-auto">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-200">
                            <th class="text-left py-3 px-4 text-gray-700">Date</th>
                            <th class="text-left py-3 px-4 text-gray-700">Check In</th>
                            <th class="text-left py-3 px-4 text-gray-700">Check Out</th>
                            <th class="text-left py-3 px-4 text-gray-700">Hours</th>
                            <th class="text-left py-3 px-4 text-gray-700">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myAttendance as $record): ?>
                            <tr class="border-b border-gray-100">
                                <td class="py-3 px-4 text-gray-900"><?php echo formatDate($record['attendance_date']); ?></td>
                                <td class="py-3 px-4 text-gray-900"><?php echo $record['check_in'] ? date('h:i A', strtotime($record['check_in'])) : '-'; ?></td>
                                <td class="py-3 px-4 text-gray-900"><?php echo $record['check_out'] ? date('h:i A', strtotime($record['check_out'])) : '-'; ?></td>
                                <td class="py-3 px-4 text-gray-900"><?php echo $record['hours'] ?? '0'; ?></td>
                                <td class="py-3 px-4">
                                    <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($record['status']); ?>">
                                        <?php echo htmlspecialchars($record['status']); ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <!-- Notifications Tab -->
    <?php if ($activeTab == 'notifications'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center gap-3">
                    <i class="fas fa-bell text-indigo-600 text-xl"></i>
                    <h2 class="text-xl font-bold text-gray-900">Notifications</h2>
                    <?php if ($unreadCount > 0): ?>
                        <span class="px-2 py-1 bg-red-500 text-white rounded-full text-sm"><?php echo $unreadCount; ?></span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="space-y-3">
                <?php foreach ($myNotifications as $notification): ?>
                    <div class="border rounded-lg p-4 <?php echo !$notification['is_read'] ? 'border-indigo-300 bg-indigo-50' : 'border-gray-200 hover:bg-gray-50'; ?>">
                        <div class="flex items-start gap-4">
                            <div class="p-2 rounded-lg bg-indigo-100 text-indigo-600">
                                <i class="fas fa-<?php echo $notification['type'] == 'training' ? 'graduation-cap' : ($notification['type'] == 'promotion' ? 'chart-line' : 'bell'); ?>"></i>
                            </div>
                            <div class="flex-1">
                                <div class="flex items-start justify-between mb-1">
                                    <h3 class="text-gray-900 font-semibold"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                    <?php if ($notification['priority'] == 'high'): ?>
                                        <span class="px-2 py-1 bg-red-100 text-red-700 rounded text-sm">High Priority</span>
                                    <?php endif; ?>
                                </div>
                                <p class="text-gray-600 text-sm mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                                <span class="text-gray-500 text-sm"><?php echo formatDateTime($notification['created_at']); ?></span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Edit Information Modal -->
<div id="editInfoModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Edit Personal Information</h3>
            <button onclick="closeEditInfoModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="editInfoForm" method="POST" action="" class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Phone Number</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($personalInfo['phone'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Address</label>
                    <textarea name="address" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"><?php echo htmlspecialchars($personalInfo['address'] ?? ''); ?></textarea>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">City</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($personalInfo['city'] ?? ''); ?>" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeEditInfoModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Save Changes
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Request Leave Modal -->
<div id="requestLeaveModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Request Leave</h3>
            <button onclick="closeRequestLeaveModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="requestLeaveForm" method="POST" action="" class="p-6">
            <div class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Leave Type *</label>
                    <select name="leave_type" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500">
                        <option value="">Select Leave Type</option>
                        <option value="Annual">Annual Leave</option>
                        <option value="Sick">Sick Leave</option>
                        <option value="Personal">Personal Leave</option>
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">Start Date *</label>
                        <input type="date" name="start_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="calculateDays()">
                    </div>
                    <div>
                        <label class="block text-gray-700 text-sm font-medium mb-2">End Date *</label>
                        <input type="date" name="end_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500" onchange="calculateDays()">
                    </div>
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Number of Days</label>
                    <input type="number" name="days" id="leave_days" readonly class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-medium mb-2">Reason (Optional)</label>
                    <textarea name="reason" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeRequestLeaveModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Submit Request
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Animate leave progress bars
document.addEventListener('DOMContentLoaded', function() {
    const leaveBars = document.querySelectorAll('.leave-bar');
    leaveBars.forEach(bar => {
        const target = parseFloat(bar.getAttribute('data-target')) || 0;
        animateProgressBar(bar, target);
    });

    // Add hover effects to cards
    const cards = document.querySelectorAll('.border.border-gray-200.rounded-lg');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'translateY(-2px)';
            this.style.transition = 'transform 0.2s ease, box-shadow 0.2s ease';
            this.style.boxShadow = '0 4px 6px rgba(0, 0, 0, 0.1)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'translateY(0)';
            this.style.boxShadow = '';
        });
    });

    // Mark notifications as read on click
    const notifications = document.querySelectorAll('.border.rounded-lg.p-4');
    notifications.forEach(notification => {
        if (notification.classList.contains('bg-indigo-50')) {
            notification.addEventListener('click', function() {
                // You can add AJAX call here to mark as read
                this.classList.remove('bg-indigo-50', 'border-indigo-300');
                this.classList.add('bg-gray-50', 'border-gray-200');
            });
        }
    });
});

// Animate Progress Bar
function animateProgressBar(element, target) {
    if (!element) return;
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.style.width = target + '%';
            clearInterval(timer);
        } else {
            element.style.width = current + '%';
        }
    }, 30);
}

// Calculate leave days
function calculateDays() {
    const startDate = document.querySelector('[name="start_date"]').value;
    const endDate = document.querySelector('[name="end_date"]').value;
    const daysInput = document.getElementById('leave_days');
    
    if (startDate && endDate) {
        const start = new Date(startDate);
        const end = new Date(endDate);
        if (end >= start) {
            const diffTime = Math.abs(end - start);
            const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24)) + 1;
            daysInput.value = diffDays;
        } else {
            daysInput.value = 0;
            alert('End date must be after start date');
        }
    }
}

// Edit Information Modal
function showEditInfoModal() {
    const modal = document.getElementById('editInfoModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeEditInfoModal() {
    const modal = document.getElementById('editInfoModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Request Leave Modal
function showRequestLeaveModal() {
    const modal = document.getElementById('requestLeaveModal');
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeRequestLeaveModal() {
    const modal = document.getElementById('requestLeaveModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    document.getElementById('requestLeaveForm').reset();
    document.getElementById('leave_days').value = '';
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const editModal = document.getElementById('editInfoModal');
    const leaveModal = document.getElementById('requestLeaveModal');
    
    if (event.target === editModal) {
        closeEditInfoModal();
    }
    
    if (event.target === leaveModal) {
        closeRequestLeaveModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeEditInfoModal();
        closeRequestLeaveModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>
