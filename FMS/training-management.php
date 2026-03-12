<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Training Management';
$pdo = getDBConnection();

$activeTab = $_GET['tab'] ?? 'orientation';
$scheduleView = $_GET['view'] ?? 'week'; // week or month
$searchQuery = $_GET['search'] ?? '';

// Get training programs
$programs = $pdo->query("
    SELECT tp.*, 
           COUNT(DISTINCT tpar.id) as participants,
           AVG(tpar.completion_percentage) as avg_completion
    FROM training_programs tp
    LEFT JOIN training_participants tpar ON tp.id = tpar.training_program_id
    GROUP BY tp.id
    ORDER BY tp.start_date DESC
")->fetchAll();

// Get training schedule with date filtering
$scheduleDateFilter = $scheduleView == 'week' 
    ? "AND ts.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)"
    : "AND ts.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";

$programFilter = '';
if (isset($_GET['program']) && is_numeric($_GET['program'])) {
    $programFilter = ' AND ts.training_program_id = ' . intval($_GET['program']);
}

// build schedule query; if filtering by program show all its sessions, otherwise apply date window
if ($programFilter) {
    $schedule = $pdo->query("
        SELECT ts.*, tp.title as program_title, tp.description
        FROM training_schedule ts
        JOIN training_programs tp ON ts.training_program_id = tp.id
        WHERE ts.training_program_id = " . intval($_GET['program']) . "
        ORDER BY ts.session_date, ts.session_time ASC
    ")->fetchAll();
} else {
    $schedule = $pdo->query("
        SELECT ts.*, tp.title as program_title, tp.description
        FROM training_schedule ts
        JOIN training_programs tp ON ts.training_program_id = tp.id
        WHERE ts.session_date >= CURDATE() $scheduleDateFilter $programFilter
        ORDER BY ts.session_date, ts.session_time ASC
    ")->fetchAll();
}

// if user has a program filter, check whether current user is enrolled and count sessions
$isEnrolled = false;
$myEnrollment = null;
$sessionCount = 0;
if (!empty($programFilter)) {
    // derive program id from GET param (already validated earlier)
    $progId = intval($_GET['program']);
    $stmt = $pdo->prepare("SELECT * FROM training_participants WHERE training_program_id = ? AND employee_id = ?");
    $stmt->execute([$progId, $_SESSION['user_id']]);
    $myEnrollment = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($myEnrollment) {
        $isEnrolled = true;
    }
    $cntStmt = $pdo->prepare("SELECT COUNT(*) FROM training_schedule WHERE training_program_id = ?");
    $cntStmt->execute([$progId]);
    $sessionCount = (int)$cntStmt->fetchColumn();
}


// Get all employees with training records statistics
if ($searchQuery) {
    $employeeRecords = $pdo->prepare("
        SELECT 
            u.id,
            u.full_name,
            u.employee_id,
            COUNT(CASE WHEN tpar.status = 'Completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN tpar.status = 'In Progress' THEN 1 END) as in_progress_count,
            COUNT(DISTINCT c.id) as certifications_count,
            MAX(tpar.completed_at) as last_completed_date,
            (SELECT tp.title 
             FROM training_participants tpar2 
             JOIN training_programs tp ON tpar2.training_program_id = tp.id 
             WHERE tpar2.employee_id = u.id AND tpar2.status = 'Completed' 
             ORDER BY tpar2.completed_at DESC 
             LIMIT 1) as last_training
        FROM users u
        LEFT JOIN training_participants tpar ON u.id = tpar.employee_id
        LEFT JOIN certificates c ON u.id = c.employee_id
        WHERE u.role != 'admin' AND (u.full_name LIKE :searchName OR u.employee_id LIKE :searchId)
        GROUP BY u.id, u.full_name, u.employee_id
        ORDER BY u.full_name ASC
    ");
    // bind both named parameters explicitly to avoid mismatch when placeholders repeat
    $employeeRecords->execute([
        'searchName' => "%$searchQuery%",
        'searchId'   => "%$searchQuery%"
    ]);
} else {
    $employeeRecords = $pdo->query("
        SELECT 
            u.id,
            u.full_name,
            u.employee_id,
            COUNT(CASE WHEN tpar.status = 'Completed' THEN 1 END) as completed_count,
            COUNT(CASE WHEN tpar.status = 'In Progress' THEN 1 END) as in_progress_count,
            COUNT(DISTINCT c.id) as certifications_count,
            MAX(tpar.completed_at) as last_completed_date,
            (SELECT tp.title 
             FROM training_participants tpar2 
             JOIN training_programs tp ON tpar2.training_program_id = tp.id 
             WHERE tpar2.employee_id = u.id AND tpar2.status = 'Completed' 
             ORDER BY tpar2.completed_at DESC 
             LIMIT 1) as last_training
        FROM users u
        LEFT JOIN training_participants tpar ON u.id = tpar.employee_id
        LEFT JOIN certificates c ON u.id = c.employee_id
        WHERE u.role != 'admin'
        GROUP BY u.id, u.full_name, u.employee_id
        ORDER BY u.full_name ASC
    ");
}
$allEmployeeRecords = $employeeRecords->fetchAll();

// Get training records for current user
$trainingRecords = $pdo->prepare("
    SELECT tp.*, tpar.completion_percentage, tpar.status
    FROM training_participants tpar
    JOIN training_programs tp ON tpar.training_program_id = tp.id
    WHERE tpar.employee_id = ?
    ORDER BY tp.start_date DESC
");
$trainingRecords->execute([$_SESSION['user_id']]);
$myRecords = $trainingRecords->fetchAll();

// build a set of program IDs the current user is enrolled in
$enrolledProgramIds = array_column($myRecords, 'id');

// Handle AJAX request for employee history
$employeeId = $_GET['employee_id'] ?? null;
$isAjax = isset($_GET['ajax']) && $_GET['ajax'] == '1';

if ($isAjax && $employeeId) {
    $employeeHistory = $pdo->prepare("
        SELECT tp.*, tpar.completion_percentage, tpar.status, tpar.enrolled_at, tpar.completed_at
        FROM training_participants tpar
        JOIN training_programs tp ON tpar.training_program_id = tp.id
        WHERE tpar.employee_id = ?
        ORDER BY tpar.enrolled_at DESC
    ");
    $employeeHistory->execute([$employeeId]);
    $historyRecords = $employeeHistory->fetchAll();
    
    header('Content-Type: text/html');
    if (empty($historyRecords)) {
        echo '<div class="text-center py-8 text-gray-500">
            <i class="fas fa-inbox text-4xl mb-4 text-gray-400"></i>
            <p>No training history found for this employee.</p>
        </div>';
    } else {
        echo '<div class="space-y-4">';
        foreach ($historyRecords as $record) {
            echo '<div class="border border-gray-200 rounded-lg p-6">';
            echo '<div class="flex items-start justify-between mb-4">';
            echo '<div class="flex-1">';
            echo '<h4 class="text-lg font-semibold text-gray-900 mb-1">' . htmlspecialchars($record['title']) . '</h4>';
            echo '<p class="text-gray-600 text-sm mb-2">Category: ' . htmlspecialchars($record['category']) . '</p>';
            echo '</div>';
            echo '<span class="px-3 py-1 rounded-full text-sm ' . getStatusBadge($record['status']) . '">';
            echo htmlspecialchars($record['status']);
            echo '</span>';
            echo '</div>';
            
            echo '<div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">';
            echo '<div class="bg-green-50 p-3 rounded-lg">';
            echo '<p class="text-gray-600 text-sm mb-1">Completion</p>';
            echo '<p class="text-green-700 font-semibold">' . $record['completion_percentage'] . '%</p>';
            echo '</div>';
            echo '<div class="bg-blue-50 p-3 rounded-lg">';
            echo '<p class="text-gray-600 text-sm mb-1">Status</p>';
            echo '<p class="text-blue-700">' . htmlspecialchars($record['status']) . '</p>';
            echo '</div>';
            echo '<div class="bg-gray-50 p-3 rounded-lg">';
            echo '<p class="text-gray-600 text-sm mb-1">Enrolled</p>';
            echo '<p class="text-gray-900 text-sm">' . formatDate($record['enrolled_at']) . '</p>';
            echo '</div>';
            if ($record['completed_at']) {
                echo '<div class="bg-purple-50 p-3 rounded-lg">';
                echo '<p class="text-gray-600 text-sm mb-1">Completed</p>';
                echo '<p class="text-purple-700 text-sm">' . formatDate($record['completed_at']) . '</p>';
                echo '</div>';
            }
            echo '</div>';
            
            if ($record['description']) {
                echo '<p class="text-gray-600 text-sm">' . htmlspecialchars($record['description']) . '</p>';
            }
            echo '</div>';
        }
        echo '</div>';
    }
    exit;
}

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Training Management</h1>
        <p class="text-gray-600">
            Manage training programs, schedules, and monitor employee development
        </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <div class="flex gap-4">
            <a href="?tab=orientation" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'orientation' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-graduation-cap"></i>
                    <span>Training Orientation</span>
                </div>
            </a>
            <a href="?tab=schedule" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'schedule' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-calendar"></i>
                    <span>Schedule</span>
                </div>
            </a>
            <a href="?tab=records" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'records' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-file-alt"></i>
                    <span>Training Records</span>
                </div>
            </a>
            <a href="?tab=recommendations" class="pb-3 px-2 border-b-2 transition-colors <?php echo $activeTab == 'recommendations' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-chart-line"></i>
                    <span>Recommendations</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Training Programs Tab -->
    <?php if ($activeTab == 'orientation'): ?>
        <div class="bg-white rounded-lg shadow mb-6 p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Training Orientation</h2>
                <button onclick="showCreateProgramModal()" class="flex items-center gap-2 px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-plus"></i>
                    <span>Create Orientation</span>
                </button>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($programs as $program): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($program['title']); ?></h3>
                                <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm">
                                    <?php echo htmlspecialchars($program['category']); ?>
                                </span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($program['status']); ?>">
                                    <?php echo htmlspecialchars($program['status']); ?>
                                </span>
                                <div class="relative">
                                    <button onclick="showProgramActions(<?php echo $program['id']; ?>)" class="p-2 text-gray-600 hover:text-gray-900 hover:bg-gray-100 rounded-lg">
                                        <i class="fas fa-ellipsis-v"></i>
                                    </button>
                                    <div id="actions-<?php echo $program['id']; ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-lg shadow-lg border border-gray-200 z-10">
                                        <button onclick="editProgram(<?php echo $program['id']; ?>)" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-edit mr-2"></i> Edit
                                        </button>
                                        <button onclick="manageParticipants(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-users mr-2"></i> Manage Participants
                                        </button>
                                        <button onclick="addSchedule(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                            <i class="fas fa-calendar-plus mr-2"></i> Add Schedule
                                        </button>
                                        <button onclick="retakeOrientation(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-indigo-700 hover:bg-indigo-50">
                                            <i class="fas fa-redo mr-2"></i> Retake Orientation
                                        </button>
<?php if (!in_array($program['id'], $enrolledProgramIds)): ?>
                                        <button onclick="showTakeTrainingModal(<?php echo $program['id']; ?>, '<?php echo htmlspecialchars($program['title']); ?>')" class="w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-green-50">
                                            <i class="fas fa-play mr-2"></i> Take Training
                                        </button>
<?php else: ?>
                                        <button disabled class="w-full text-left px-4 py-2 text-sm text-gray-400">
                                            <i class="fas fa-check mr-2"></i> Enrolled
                                        </button>
<?php endif;?>
                                        <hr class="my-1">
                                        <button onclick="deleteProgram(<?php echo $program['id']; ?>)" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50">
                                            <i class="fas fa-trash mr-2"></i> Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Duration</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($program['duration']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Participants</p>
                                <p class="text-gray-900"><?php echo $program['participants']; ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Start Date</p>
                                <p class="text-gray-900"><?php echo formatDate($program['start_date']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Instructor</p>
                                <p class="text-gray-900 text-sm"><?php echo htmlspecialchars($program['instructor']); ?></p>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-gray-700">Completion</p>
                                <span class="text-gray-900"><?php echo round($program['avg_completion'] ?? 0); ?>%</span>
                            </div>
                            <div class="bg-gray-200 rounded-full h-2">
                                <div
                                    class="bg-purple-500 h-2 rounded-full"
                                    style="width: <?php echo round($program['avg_completion'] ?? 0); ?>%"
                                ></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Schedule Tab -->
    <?php if ($activeTab == 'schedule'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Training Schedule</h2>
                <div class="flex gap-2">
                    <a href="?tab=schedule&view=week" class="px-4 py-2 rounded-lg transition-colors <?php echo $scheduleView == 'week' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        This Week
                    </a>
                    <a href="?tab=schedule&view=month" class="px-4 py-2 rounded-lg transition-colors <?php echo $scheduleView == 'month' ? 'bg-indigo-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200'; ?>">
                        This Month
                    </a>
                </div>
            </div>

            <?php if ($isEnrolled): ?>
                <div class="mb-4 p-4 bg-green-50 rounded-lg">
                    <strong>Your progress:</strong> <?php echo intval($myEnrollment['completion_percentage']); ?>% &mdash; <?php echo $sessionCount; ?> lecture<?php echo $sessionCount !== 1 ? 's' : ''; ?>
                </div>
            <?php endif; ?>
            <div class="space-y-4">
                <?php if (empty($schedule)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No training sessions scheduled for <?php echo $scheduleView == 'week' ? 'this week' : 'this month'; ?>.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($schedule as $session): ?>
                        <div class="border border-gray-200 rounded-lg p-6 hover:border-indigo-300 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex gap-6 flex-1">
                                    <div class="text-center min-w-[80px]">
                                        <p class="text-gray-600 text-sm mb-1">
                                            <?php echo date('M', strtotime($session['session_date'])); ?>
                                        </p>
                                        <p class="text-2xl font-bold text-gray-900">
                                            <?php echo date('d', strtotime($session['session_date'])); ?>
                                        </p>
                                        <p class="text-gray-600 text-sm mt-1">
                                            <?php echo date('h:i A', strtotime($session['session_time'])); ?>
                                        </p>
                                    </div>

                                    <div class="flex-1">
                                        <h3 class="text-lg font-semibold text-gray-900 mb-1"><?php echo htmlspecialchars($session['program_title']); ?></h3>
                                        <?php if (!empty($session['session_type'])): ?>
                                            <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($session['session_type']); ?></p>
                                        <?php endif; ?>
                                        <div class="flex items-center gap-4 text-sm text-gray-600">
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-user"></i>
                                                <span><?php echo htmlspecialchars($session['instructor']); ?></span>
                                            </div>
                                            <div class="flex items-center gap-2">
                                                <i class="fas fa-map-marker-alt"></i>
                                                <span><?php echo htmlspecialchars($session['location']); ?></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button 
                                        onclick="showTrainingDetails(<?php echo htmlspecialchars(json_encode([
                                            'title' => $session['program_title'],
                                            'date' => $session['session_date'],
                                            'time' => $session['session_time'],
                                            'type' => $session['session_type'] ?? '',
                                            'instructor' => $session['instructor'],
                                            'location' => $session['location'],
                                            'description' => $session['description'] ?? ''
                                        ])); ?>)"
                                        class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                                    >
                                        View Details
                                    </button>
                                    <?php if ($isEnrolled): ?>
                                        <button onclick="completeSession(<?php echo $session['id']; ?>, <?php echo isset($progId) ? $progId : 0; ?>)" class="px-3 py-1 bg-green-600 text-white rounded-lg hover:bg-green-700 text-sm">
                                            Mark Lecture Complete
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Training Records Tab -->
    <?php if ($activeTab == 'records'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Employee Training Records</h2>
                <form method="GET" action="" class="flex items-center gap-2">
                    <input type="hidden" name="tab" value="records">
                    <div class="relative">
                        <input 
                            type="text" 
                            id="employeeSearch"
                            name="search" 
                            value="<?php echo htmlspecialchars($searchQuery); ?>" 
                            placeholder="Search employees..." 
                            class="pl-10 pr-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            onkeyup="handleSearch(event)"
                        >
                        <i class="fas fa-search absolute left-3 top-1/2 transform -translate-y-1/2 text-gray-400"></i>
                    </div>
                    <?php if ($searchQuery): ?>
                        <a href="?tab=records" class="px-4 py-2 text-gray-600 hover:text-gray-900">
                            <i class="fas fa-times"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <div class="space-y-4">
                <?php if (empty($allEmployeeRecords)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <p>No employee records found.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($allEmployeeRecords as $employee): ?>
                        <div class="border border-gray-200 rounded-lg p-6 hover:border-indigo-300 transition-colors">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-4">
                                        <h3 class="text-lg font-semibold text-gray-900"><?php echo htmlspecialchars($employee['full_name']); ?></h3>
                                        <span class="text-gray-500 text-sm">(ID: <?php echo htmlspecialchars($employee['employee_id']); ?>)</span>
                                    </div>
                                    
                                    <div class="flex items-center gap-3 mb-4">
                                        <span class="px-3 py-1 bg-green-100 text-green-700 rounded-full text-sm">
                                            Completed: <?php echo $employee['completed_count']; ?>
                                        </span>
                                        <span class="px-3 py-1 bg-blue-100 text-blue-700 rounded-full text-sm">
                                            In Progress: <?php echo $employee['in_progress_count']; ?>
                                        </span>
                                        <span class="px-3 py-1 bg-purple-100 text-purple-700 rounded-full text-sm">
                                            Certifications: <?php echo $employee['certifications_count']; ?>
                                        </span>
                                    </div>

                                    <div class="flex items-center gap-2 text-sm text-gray-600 mb-2">
                                        <?php if ($employee['last_completed_date']): ?>
                                            <i class="fas fa-clock text-gray-400"></i>
                                            <span>Last completed: <?php echo formatDate($employee['last_completed_date']); ?></span>
                                        <?php else: ?>
                                            <i class="fas fa-clock text-gray-400"></i>
                                            <span>No completed trainings</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($employee['last_training']): ?>
                                        <p class="text-sm text-gray-700">
                                            Last Training: <span class="font-medium"><?php echo htmlspecialchars($employee['last_training']); ?></span>
                                        </p>
                                    <?php endif; ?>
                                </div>
                                
                                <button 
                                    onclick="showEmployeeHistory(<?php echo $employee['id']; ?>, '<?php echo htmlspecialchars($employee['full_name']); ?>')"
                                    class="ml-4 text-indigo-600 hover:text-indigo-700 font-medium cursor-pointer"
                                >
                                    View Full History
                                </button>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Recommendations Tab -->
    <?php if ($activeTab == 'recommendations'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Training Recommendations</h2>
            </div>
            <div class="text-center py-8 text-gray-500">
                <p>Training recommendations will be displayed here.</p>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Training Details Modal -->
<div id="trainingDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900">Training Details</h3>
            <button onclick="closeTrainingDetails()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div id="trainingDetailsContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeTrainingDetails()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Employee History Modal -->
<div id="employeeHistoryModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="employeeHistoryTitle">Training History</h3>
            <button onclick="closeEmployeeHistory()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div id="employeeHistoryContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i>
                    <p class="text-gray-600">Loading training history...</p>
                </div>
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeEmployeeHistory()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Create/Edit Program Modal -->
<div id="programModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="programModalTitle">Create Training Program</h3>
            <button onclick="closeProgramModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="programForm" onsubmit="saveProgram(event)" class="p-6">
            <input type="hidden" id="programId" name="id">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Title *</label>
                    <input type="text" id="programTitle" name="title" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Category</label>
                        <input type="text" id="programCategory" name="category" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Duration</label>
                        <input type="text" id="programDuration" name="duration" placeholder="e.g., 2 days" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Start Date</label>
                        <input type="date" id="programStartDate" name="start_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">End Date</label>
                        <input type="date" id="programEndDate" name="end_date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="programStatus" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                            <option value="Upcoming">Upcoming</option>
                            <option value="In Progress">In Progress</option>
                            <option value="Completed">Completed</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructor</label>
                        <input type="text" id="programInstructor" name="instructor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Description</label>
                    <textarea id="programDescription" name="description" rows="4" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeProgramModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Save Program
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Participants Modal -->
<div id="participantsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="participantsModalTitle">Manage Participants</h3>
            <button onclick="closeParticipantsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6">
            <div class="mb-4 flex items-center justify-between">
                <button onclick="showEnrollParticipantForm()" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                    <i class="fas fa-user-plus mr-2"></i> Enroll Participant
                </button>
            </div>
            <div id="participantsContent">
                <div class="text-center py-8">
                    <i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i>
                    <p class="text-gray-600">Loading participants...</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Take Training Modal -->
<div id="takeTrainingModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-lg w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="takeTrainingModalTitle">Enroll in Training</h3>
            <button onclick="closeTakeTrainingModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="takeTrainingForm" onsubmit="submitTakeTraining(event)" class="p-6">
            <input type="hidden" id="takeProgramId" name="program_id">
            <input type="hidden" name="employee_id" value="<?php echo $_SESSION['user_id']; ?>">
            <div class="space-y-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                    <input type="text" id="takeFullName" name="full_name" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" value="<?php echo htmlspecialchars(getUserName()); ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Health Condition</label>
                    <textarea id="takeHealth" name="health_condition" rows="3" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"></textarea>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeTakeTrainingModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition-colors">
                    Process Enrollment
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Lectures Modal (shown after enrollment) -->
<div id="lecturesModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="lecturesModalTitle">Program Lectures</h3>
            <button onclick="closeLecturesModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div id="lecturesContent" class="p-6">
            <div class="text-center py-8">
                <i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i>
                <p class="text-gray-600">Loading lectures...</p>
            </div>
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeLecturesModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<!-- Schedule Modal -->
<div id="scheduleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="scheduleModalTitle">Add Training Schedule</h3>
            <button onclick="closeScheduleModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <form id="scheduleForm" onsubmit="saveSchedule(event)" class="p-6">
            <input type="hidden" id="scheduleProgramId" name="program_id">
            <input type="hidden" id="scheduleId" name="id">
            <div class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Session Date *</label>
                        <input type="date" id="scheduleDate" name="session_date" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Session Time *</label>
                        <input type="time" id="scheduleTime" name="session_time" required class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Session Type</label>
                    <input type="text" id="scheduleType" name="session_type" placeholder="e.g., Workshop, Lecture" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Location</label>
                        <input type="text" id="scheduleLocation" name="location" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Instructor</label>
                        <input type="text" id="scheduleInstructor" name="instructor" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                    </div>
                </div>
            </div>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" onclick="closeScheduleModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Cancel
                </button>
                <button type="submit" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors">
                    Save Schedule
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Take Training Modal Functions
let currentProgramTitleForLecture = '';

function showTakeTrainingModal(programId, programTitle) {
    currentProgramTitleForLecture = programTitle;
    document.getElementById('takeTrainingModalTitle').textContent = `Enroll in ${programTitle}`;
    document.getElementById('takeProgramId').value = programId;
    document.getElementById('takeHealth').value = '';
    document.getElementById('takeTrainingModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTakeTrainingModal() {
    document.getElementById('takeTrainingModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function submitTakeTraining(event) {
    event.preventDefault();
    const form = event.target;
    const formData = new FormData(form);
    formData.append('action', 'enroll_participant');
    // using enroll_participant which now accepts full_name and health_condition
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeTakeTrainingModal();
            // show lectures for the program instead of navigating away
            const pid = formData.get('program_id');
            showLecturesModal(pid, currentProgramTitleForLecture);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error processing enrollment');
    });
}

// Training Details Modal Functions
function showTrainingDetails(session) {
    const modal = document.getElementById('trainingDetailsModal');
    const content = document.getElementById('trainingDetailsContent');
    
    const date = new Date(session.date);
    const formattedDate = date.toLocaleDateString('en-US', { 
        weekday: 'long', 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
    
    const time = new Date('1970-01-01T' + session.time + 'Z');
    const formattedTime = time.toLocaleTimeString('en-US', { 
        hour: 'numeric', 
        minute: '2-digit',
        hour12: true 
    });
    
    content.innerHTML = `
        <div class="space-y-4">
            <div>
                <h4 class="text-lg font-semibold text-gray-900 mb-2">${session.title}</h4>
                ${session.type ? `<p class="text-gray-600 text-sm mb-4">${session.type}</p>` : ''}
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div class="flex items-start gap-3">
                    <i class="fas fa-calendar-alt text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Date</p>
                        <p class="text-gray-900 font-medium">${formattedDate}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-clock text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Time</p>
                        <p class="text-gray-900 font-medium">${formattedTime}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-user text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Instructor</p>
                        <p class="text-gray-900 font-medium">${session.instructor}</p>
                    </div>
                </div>
                
                <div class="flex items-start gap-3">
                    <i class="fas fa-map-marker-alt text-indigo-600 mt-1"></i>
                    <div>
                        <p class="text-gray-600 text-sm">Location</p>
                        <p class="text-gray-900 font-medium">${session.location}</p>
                    </div>
                </div>
            </div>
            
            ${session.description ? `
                <div class="pt-4 border-t border-gray-200">
                    <p class="text-gray-600 text-sm mb-2">Description</p>
                    <p class="text-gray-700">${session.description}</p>
                </div>
            ` : ''}
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeTrainingDetails() {
    const modal = document.getElementById('trainingDetailsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Employee History Modal Functions
function showEmployeeHistory(employeeId, employeeName) {
    const modal = document.getElementById('employeeHistoryModal');
    const title = document.getElementById('employeeHistoryTitle');
    const content = document.getElementById('employeeHistoryContent');
    
    title.textContent = `Training History - ${employeeName}`;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    
    // Load employee history via AJAX
    fetch(`?tab=records&employee_id=${employeeId}&ajax=1`)
        .then(response => response.text())
        .then(html => {
            content.innerHTML = html;
        })
        .catch(error => {
            content.innerHTML = `
                <div class="text-center py-8 text-red-600">
                    <i class="fas fa-exclamation-circle text-3xl mb-4"></i>
                    <p>Error loading training history. Please try again.</p>
                </div>
            `;
            console.error('Error:', error);
        });
}

function closeEmployeeHistory() {
    const modal = document.getElementById('employeeHistoryModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Search functionality with debouncing
let searchTimeout;
function handleSearch(event) {
    clearTimeout(searchTimeout);
    
    // If Enter is pressed, submit immediately
    if (event.key === 'Enter') {
        event.target.closest('form').submit();
        return;
    }
    
    // Otherwise, debounce the search
    searchTimeout = setTimeout(() => {
        const searchValue = event.target.value.trim();
        const url = new URL(window.location.href);
        
        if (searchValue) {
            url.searchParams.set('search', searchValue);
        } else {
            url.searchParams.delete('search');
        }
        url.searchParams.set('tab', 'records');
        
        window.location.href = url.toString();
    }, 500); // Wait 500ms after user stops typing
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const trainingModal = document.getElementById('trainingDetailsModal');
    const historyModal = document.getElementById('employeeHistoryModal');
    
    if (event.target === trainingModal) {
        closeTrainingDetails();
    }
    
    if (event.target === historyModal) {
        closeEmployeeHistory();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeTrainingDetails();
        closeEmployeeHistory();
    }
});

// Smooth scroll for tabs
document.addEventListener('DOMContentLoaded', function() {
    // Add smooth transitions
    const tabs = document.querySelectorAll('a[href*="tab="]');
    tabs.forEach(tab => {
        tab.addEventListener('click', function(e) {
            // Add loading state if needed
            const targetTab = this.getAttribute('href').split('tab=')[1]?.split('&')[0];
            if (targetTab) {
                // You can add loading indicators here
            }
        });
    });
    
    // Auto-focus search input when records tab is active
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('tab') === 'records') {
        const searchInput = document.getElementById('employeeSearch');
        if (searchInput && !urlParams.get('search')) {
            // Don't auto-focus if there's already a search query
            // searchInput.focus();
        }
    }

    // Animate progress bars in schedule tab
    const progressBars = document.querySelectorAll('.progress-bar');
    progressBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target')) || 0;
        const progressText = bar.closest('.training-item')?.querySelector('.progress-text');
        if (target > 0) {
            animateProgressBar(bar, target, progressText);
        }
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
});

// Animate Progress Bar
function animateProgressBar(element, target, percentageSpan) {
    if (!element) return;
    let current = 0;
    const increment = target / 50;
    const timer = setInterval(() => {
        current += increment;
        if (current >= target) {
            element.style.width = target + '%';
            if (percentageSpan) percentageSpan.textContent = target + '%';
            clearInterval(timer);
        } else {
            element.style.width = current + '%';
            if (percentageSpan) percentageSpan.textContent = Math.floor(current) + '%';
        }
    }, 30);
}

// Training Program CRUD Functions
function showCreateProgramModal() {
    document.getElementById('programModalTitle').textContent = 'Create Training Program';
    document.getElementById('programForm').reset();
    document.getElementById('programId').value = '';
    document.getElementById('programModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function editProgram(programId) {
    fetch(`training-ajax.php?action=get_program&id=${programId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const program = data.program;
                document.getElementById('programModalTitle').textContent = 'Edit Training Program';
                document.getElementById('programId').value = program.id;
                document.getElementById('programTitle').value = program.title || '';
                document.getElementById('programCategory').value = program.category || '';
                document.getElementById('programDuration').value = program.duration || '';
                document.getElementById('programStatus').value = program.status || 'Upcoming';
                document.getElementById('programStartDate').value = program.start_date || '';
                document.getElementById('programEndDate').value = program.end_date || '';
                document.getElementById('programInstructor').value = program.instructor || '';
                document.getElementById('programDescription').value = program.description || '';
                document.getElementById('programModal').classList.remove('hidden');
                document.body.style.overflow = 'hidden';
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('Error loading program details');
        });
}

function saveProgram(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', document.getElementById('programId').value ? 'update_program' : 'create_program');
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeProgramModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving program');
    });
}

function deleteProgram(programId) {
    if (!confirm('Are you sure you want to delete this training program? This action cannot be undone.')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_program');
    formData.append('id', programId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error deleting program');
    });
}

function retakeOrientation(programId, programTitle) {
    if (!confirm(`Are you sure you want to retake "${programTitle}"? This will reset your progress.`)) {
        return;
    }
    const formData = new FormData();
    formData.append('action', 'retake_program');
    formData.append('program_id', programId);

    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            // optionally show lectures modal again
            showLecturesModal(programId, programTitle);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error resetting program');
    });
}

function closeProgramModal() {
    document.getElementById('programModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function showProgramActions(programId) {
    const actionsMenu = document.getElementById(`actions-${programId}`);
    // Close all other action menus
    document.querySelectorAll('[id^="actions-"]').forEach(menu => {
        if (menu.id !== `actions-${programId}`) {
            menu.classList.add('hidden');
        }
    });
    actionsMenu.classList.toggle('hidden');
}

// Close action menus when clicking outside
document.addEventListener('click', function(event) {
    if (!event.target.closest('[id^="actions-"]') && !event.target.closest('button[onclick*="showProgramActions"]')) {
        document.querySelectorAll('[id^="actions-"]').forEach(menu => {
            menu.classList.add('hidden');
        });
    }
});

// Participants Management
let currentProgramId = null;

function manageParticipants(programId, programTitle) {
    currentProgramId = programId;
    document.getElementById('participantsModalTitle').textContent = `Manage Participants - ${programTitle}`;
    document.getElementById('participantsModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
    loadParticipants(programId);
}

function loadParticipants(programId) {
    const content = document.getElementById('participantsContent');
    content.innerHTML = '<div class="text-center py-8"><i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i><p class="text-gray-600">Loading participants...</p></div>';
    
    fetch(`training-ajax.php?action=get_participants&program_id=${programId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                if (data.participants.length === 0) {
                    content.innerHTML = '<div class="text-center py-8 text-gray-500"><p>No participants enrolled yet.</p></div>';
                } else {
                    let html = '<div class="space-y-4">';
                    data.participants.forEach(participant => {
                        const nameToShow = participant.enrolled_name || participant.user_name || participant.full_name || 'Unknown';
                        html += `
                            <div class="border border-gray-200 rounded-lg p-4">
                                <div class="flex items-center justify-between">
                                    <div class="flex-1">
                                        <h4 class="font-semibold text-gray-900">${nameToShow}</h4>
                                        <p class="text-sm text-gray-600">${participant.employee_id ? participant.employee_id + ' • ' : ''}${participant.department || ''}</p>
                                    </div>
                                    <div class="flex items-center gap-4">
                                        <div>
                                            <label class="text-sm text-gray-600">Completion</label>
                                            <input type="range" min="0" max="100" value="${participant.completion_percentage}" 
                                                onchange="updateCompletion(${participant.id}, this.value)" 
                                                class="w-32">
                                            <span class="text-sm font-medium">${participant.completion_percentage}%</span>
                                        </div>
                                        <select onchange="updateParticipantStatus(${participant.id}, this.value)" class="px-3 py-1 border rounded-lg">
                                            <option value="Enrolled" ${participant.status === 'Enrolled' ? 'selected' : ''}>Enrolled</option>
                                            <option value="In Progress" ${participant.status === 'In Progress' ? 'selected' : ''}>In Progress</option>
                                            <option value="Completed" ${participant.status === 'Completed' ? 'selected' : ''}>Completed</option>
                                            <option value="Dropped" ${participant.status === 'Dropped' ? 'selected' : ''}>Dropped</option>
                                        </select>
                                        <button onclick="removeParticipant(${participant.id})" class="text-red-600 hover:text-red-700">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    content.innerHTML = html;
                }
            } else {
                content.innerHTML = `<div class="text-center py-8 text-red-600"><p>Error: ${data.message}</p></div>`;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = '<div class="text-center py-8 text-red-600"><p>Error loading participants</p></div>';
        });
}

function updateCompletion(participantId, percentage) {
    const formData = new FormData();
    formData.append('action', 'update_completion');
    formData.append('id', participantId);
    formData.append('completion_percentage', percentage);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update the status if needed
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating completion');
    });
}

function updateParticipantStatus(participantId, status) {
    const formData = new FormData();
    formData.append('action', 'update_participant');
    formData.append('id', participantId);
    formData.append('status', status);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error updating participant status');
    });
}

// mark a specific session complete for the current user
function completeSession(sessionId, programId) {
    const formData = new FormData();
    formData.append('action', 'complete_session');
    formData.append('session_id', sessionId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert('Lecture marked complete (+' + data.added + '%)');
            // refresh the page so progress bar updates
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error marking lecture complete');
    });
}

// display a read‑only list of all sessions for a program in a modal
function showLecturesModal(programId, programTitle) {
    const modal = document.getElementById('lecturesModal');
    const titleElem = document.getElementById('lecturesModalTitle');
    const content = document.getElementById('lecturesContent');
    titleElem.textContent = programTitle || 'Program Lectures';
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';

    // show an exam button at top of modal
    content.innerHTML = `
        <div class="mb-4 text-right">
            <button onclick="startExam(${programId}, '${programTitle.replace(/'/g, "\\'")}')" class="px-4 py-2 bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors text-sm">
                Start Exam: ${programTitle}
            </button>
        </div>
        <div class="text-center py-8"><i class="fas fa-spinner fa-spin text-3xl text-indigo-600 mb-4"></i><p class="text-gray-600">Loading lectures...</p></div>
    `;
    fetch(`training-ajax.php?action=get_schedule&program_id=${programId}`)
        .then(resp => resp.json())
        .then(data => {
            if (data.success) {
                // if server returned an extra_content field, prepend it
                if (data.extra_content) {
                    content.innerHTML = data.extra_content;
                }

                if (data.schedule.length === 0) {
                    content.innerHTML += '<div class="text-center py-8 text-gray-500"><p>No lectures available for this program.</p></div>';
                } else {
                    let html = '<ul class="space-y-4">';
                    data.schedule.forEach(s => {
                        html += `<li class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex justify-between items-center">
                                        <div>
                                            <div class="font-semibold">${s.session_type || 'Lecture'}</div>
                                            <div class="text-sm text-gray-600">${s.session_date} ${s.session_time}</div>
                                            <div class="text-sm text-gray-600">${s.location || ''}</div>
                                        </div>
                                    </div>
                                </li>`;
                    });
                    html += '</ul>';
                    content.innerHTML += html;
                }
            } else {
                content.innerHTML = `<div class="text-center py-8 text-red-600"><p>Error: ${data.message}</p></div>`;
            }
        })
        .catch(err => {
            console.error(err);
            content.innerHTML = '<div class="text-center py-8 text-red-600"><p>Error loading lectures</p></div>';
        });
}

function startExam(programId, programTitle) {
    // redirect to a hypothetical exam page; title passed as query for convenience
    window.location.href = `training-exam.php?program_id=${programId}&title=${encodeURIComponent(programTitle)}`;
}

function closeLecturesModal() {
    const modal = document.getElementById('lecturesModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

function removeParticipant(participantId) {
    if (!confirm('Are you sure you want to remove this participant?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'remove_participant');
    formData.append('id', participantId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error removing participant');
    });
}

function showEnrollParticipantForm() {
    // Get list of employees (you may want to create an endpoint for this)
    const employeeId = prompt('Enter Employee ID to enroll:');
    if (!employeeId) return;
    
    const formData = new FormData();
    formData.append('action', 'enroll_participant');
    formData.append('program_id', currentProgramId);
    formData.append('employee_id', employeeId);
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            loadParticipants(currentProgramId);
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error enrolling participant');
    });
}

function closeParticipantsModal() {
    document.getElementById('participantsModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentProgramId = null;
}

// Schedule Management
function addSchedule(programId, programTitle) {
    document.getElementById('scheduleModalTitle').textContent = `Add Schedule - ${programTitle}`;
    document.getElementById('scheduleForm').reset();
    document.getElementById('scheduleProgramId').value = programId;
    document.getElementById('scheduleId').value = '';
    document.getElementById('scheduleModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function saveSchedule(event) {
    event.preventDefault();
    const formData = new FormData(event.target);
    formData.append('action', document.getElementById('scheduleId').value ? 'update_schedule' : 'create_schedule');
    
    fetch('training-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert(data.message);
            closeScheduleModal();
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Error saving schedule');
    });
}

function closeScheduleModal() {
    document.getElementById('scheduleModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modals when clicking outside
document.addEventListener('click', function(event) {
    const programModal = document.getElementById('programModal');
    const participantsModal = document.getElementById('participantsModal');
    const scheduleModal = document.getElementById('scheduleModal');
    
    if (event.target === programModal) {
        closeProgramModal();
    }
    if (event.target === participantsModal) {
        closeParticipantsModal();
    }
    if (event.target === scheduleModal) {
        closeScheduleModal();
    }
});

// Close modals with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeProgramModal();
        closeParticipantsModal();
        closeScheduleModal();
    }
});
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>
