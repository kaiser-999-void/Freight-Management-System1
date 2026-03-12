<?php
require_once 'config/config.php';
requireLogin();

$pageTitle = 'Learning Management';
$pdo = getDBConnection();

$activeTab = $_GET['tab'] ?? 'catalog';
$userId = $_SESSION['user_id'];

// Handle course enrollment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'enroll') {
    $courseId = $_POST['course_id'] ?? 0;
    
    if ($courseId) {
        // Check if already enrolled
        $checkStmt = $pdo->prepare("SELECT id FROM course_enrollments WHERE course_id = ? AND employee_id = ?");
        $checkStmt->execute([$courseId, $userId]);
        
        if (!$checkStmt->fetch()) {
            // Get course details for module count
            $courseStmt = $pdo->prepare("SELECT modules_count FROM learning_courses WHERE id = ?");
            $courseStmt->execute([$courseId]);
            $courseData = $courseStmt->fetch(PDO::FETCH_ASSOC);
            
            // Enroll the user
            $enrollStmt = $pdo->prepare("
                INSERT INTO course_enrollments (course_id, employee_id, progress, completed_modules, total_modules, status)
                VALUES (?, ?, 0, 0, ?, 'Enrolled')
            ");
            $enrollStmt->execute([$courseId, $userId, $courseData['modules_count'] ?? 0]);
            
            $_SESSION['message'] = 'Successfully enrolled in course!';
        } else {
            $_SESSION['message'] = 'You are already enrolled in this course.';
        }
    }
    
    header('Location: learning.php?tab=catalog');
    exit;
}

// Get courses
$courses = $pdo->prepare("
    SELECT lc.*, 
           ce.progress, ce.status as enrollment_status, ce.completed_modules, ce.total_modules, ce.last_accessed
    FROM learning_courses lc
    LEFT JOIN course_enrollments ce ON lc.id = ce.course_id AND ce.employee_id = ?
    ORDER BY lc.created_at DESC
");
$courses->execute([$userId]);
$allCourses = $courses->fetchAll();

// Get my progress
$myProgress = $pdo->prepare("
    SELECT ce.*, lc.title as course_name
    FROM course_enrollments ce
    JOIN learning_courses lc ON ce.course_id = lc.id
    WHERE ce.employee_id = ?
    ORDER BY ce.last_accessed DESC
");
$myProgress->execute([$userId]);
$progress = $myProgress->fetchAll();

// Get certificates
$certificates = $pdo->prepare("SELECT * FROM certificates WHERE employee_id = ? ORDER BY issue_date DESC");
$certificates->execute([$userId]);
$myCertificates = $certificates->fetchAll();

// Get badges
$badges = $pdo->prepare("SELECT * FROM badges WHERE employee_id = ? ORDER BY earned_date DESC");
$badges->execute([$userId]);
$myBadges = $badges->fetchAll();

// Get examinations
$examinations = $pdo->prepare("
    SELECT e.*, lc.title as course_name
    FROM examinations e
    JOIN learning_courses lc ON e.course_id = lc.id
    WHERE e.employee_id = ?
    ORDER BY e.exam_date DESC
");
$examinations->execute([$userId]);
$myExams = $examinations->fetchAll();

ob_start();
?>

<div class="p-8">
    <div class="mb-8">
        <h1 class="text-3xl font-bold text-gray-900 mb-2">Learning Management System</h1>
        <p class="text-gray-600">
            Browse courses, track your progress, and earn certifications
        </p>
    </div>

    <!-- Tabs -->
    <div class="mb-6 border-b border-gray-200">
        <div class="flex gap-4 overflow-x-auto">
            <a href="?tab=catalog" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'catalog' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-book-open"></i>
                    <span>Course Catalog</span>
                </div>
            </a>
            <a href="?tab=progress" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'progress' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-chart-line"></i>
                    <span>My Progress</span>
                </div>
            </a>
            <a href="?tab=certificates" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'certificates' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-award"></i>
                    <span>Certificates & Badges</span>
                </div>
            </a>
            <a href="?tab=examinations" class="pb-3 px-2 border-b-2 transition-colors whitespace-nowrap <?php echo $activeTab == 'examinations' ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-gray-600 hover:text-gray-900'; ?>">
                <div class="flex items-center gap-2">
                    <i class="fas fa-file-alt"></i>
                    <span>Examinations</span>
                </div>
            </a>
        </div>
    </div>

    <!-- Course Catalog Tab -->
    <?php if ($activeTab == 'catalog'): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <div class="flex items-center justify-between mb-6">
                <h2 class="text-xl font-bold text-gray-900">Available Courses</h2>
            </div>

            <!-- Search and Filter Section -->
            <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-200">
                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Search Courses</label>
                        <input 
                            type="text" 
                            id="courseSearch" 
                            placeholder="Search by title or description..." 
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent"
                            onkeyup="filterCourses()"
                        >
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Category</label>
                        <select id="categoryFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" onchange="filterCourses()">
                            <option value="">All Categories</option>
                            <?php 
                            $categories = array_unique(array_column($allCourses, 'category'));
                            foreach ($categories as $cat): 
                            ?>
                                <option value="<?php echo htmlspecialchars($cat); ?>"><?php echo htmlspecialchars($cat); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Level</label>
                        <select id="levelFilter" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-indigo-500 focus:border-transparent" onchange="filterCourses()">
                            <option value="">All Levels</option>
                            <option value="Beginner">Beginner</option>
                            <option value="Intermediate">Intermediate</option>
                            <option value="Advanced">Advanced</option>
                        </select>
                    </div>
                </div>
            </div>

            <?php if (isset($_SESSION['message'])): ?>
                <div class="mb-4 p-4 bg-green-50 border border-green-200 rounded-lg text-green-800">
                    <?php echo htmlspecialchars($_SESSION['message']); ?>
                </div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>

            <div id="coursesContainer" class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <?php foreach ($allCourses as $course): ?>
                    <div class="border border-gray-200 rounded-lg p-6 course-card" data-title="<?php echo htmlspecialchars(strtolower($course['title'])); ?>" data-description="<?php echo htmlspecialchars(strtolower($course['description'])); ?>" data-category="<?php echo htmlspecialchars($course['category']); ?>" data-level="<?php echo htmlspecialchars($course['level']); ?>">
                        <div class="flex items-start justify-between mb-3">
                            <div class="flex-1">
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($course['title']); ?></h3>
                                <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($course['description']); ?></p>
                            </div>
                        </div>
                        <div class="flex items-center gap-3 mb-4 text-sm text-gray-600">
                            <span class="px-2 py-1 bg-purple-100 text-purple-700 rounded text-sm">
                                <?php echo htmlspecialchars($course['category']); ?>
                            </span>
                            <span class="px-2 py-1 bg-blue-100 text-blue-700 rounded text-sm">
                                <?php echo htmlspecialchars($course['level']); ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 mb-4">
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Duration</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($course['duration']); ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Modules</p>
                                <p class="text-gray-900"><?php echo $course['modules_count']; ?></p>
                            </div>
                            <div>
                                <p class="text-gray-600 text-sm mb-1">Instructor</p>
                                <p class="text-gray-900 text-sm"><?php echo htmlspecialchars($course['instructor']); ?></p>
                            </div>
                            <div>
                                <div class="flex items-center gap-1">
                                    <i class="fas fa-star text-yellow-500"></i>
                                    <span class="text-gray-900"><?php echo $course['rating']; ?></span>
                                    <span class="text-gray-600 text-sm">(<?php echo $course['reviews_count']; ?>)</span>
                                </div>
                            </div>
                        </div>
                        <?php if ($course['enrollment_status']): ?>
                            <div class="mb-4">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-gray-700 text-sm">Your Progress</p>
                                    <span class="text-gray-900 text-sm"><?php echo $course['progress'] ?? 0; ?>%</span>
                                </div>
                                <div class="bg-gray-200 rounded-full h-2">
                                    <div class="bg-green-500 h-2 rounded-full course-progress-bar" style="width: 0%" data-target="<?php echo $course['progress'] ?? 0; ?>"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <button onclick="showCoursePreview(<?php echo htmlspecialchars(json_encode([
                            'id' => $course['id'],
                            'title' => $course['title'],
                            'description' => $course['description'],
                            'category' => $course['category'],
                            'level' => $course['level'],
                            'duration' => $course['duration'],
                            'modules_count' => $course['modules_count'],
                            'instructor' => $course['instructor'],
                            'rating' => $course['rating'],
                            'reviews_count' => $course['reviews_count'],
                            'enrolled' => (bool)$course['enrollment_status']
                        ])); ?>)" class="w-full flex items-center justify-center gap-2 px-4 py-2 rounded-lg <?php echo $course['enrollment_status'] ? 'bg-indigo-600 text-white hover:bg-indigo-700' : 'border border-indigo-600 text-indigo-600 hover:bg-indigo-50'; ?>">
                            <?php if ($course['enrollment_status']): ?>
                                <i class="fas fa-play"></i>
                                <span>Continue Learning</span>
                            <?php else: ?>
                                <span>Enroll Now</span>
                            <?php endif; ?>
                        </button>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Progress Tab -->
    <?php if ($activeTab == 'progress'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">My Learning Progress</h2>
            <div class="space-y-4">
                <?php foreach ($progress as $prog): ?>
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($prog['course_name']); ?></h3>
                                <p class="text-gray-600 text-sm">
                                    Last accessed: <?php echo formatDate($prog['last_accessed']); ?>
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($prog['status']); ?>">
                                <?php echo htmlspecialchars($prog['status']); ?>
                            </span>
                        </div>
                        <div class="grid grid-cols-3 gap-4 mb-4">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Modules Completed</p>
                                <p class="text-gray-900"><?php echo $prog['completed_modules']; ?>/<?php echo $prog['total_modules']; ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Time Spent</p>
                                <p class="text-gray-900"><?php echo htmlspecialchars($prog['time_spent'] ?? 'N/A'); ?></p>
                            </div>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Progress</p>
                                <p class="text-gray-900"><?php echo $prog['progress']; ?>%</p>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="bg-gray-200 rounded-full h-3">
                                <div class="h-3 rounded-full learning-progress-bar <?php echo $prog['progress'] == 100 ? 'bg-green-500' : 'bg-indigo-500'; ?>" style="width: 0%" data-target="<?php echo $prog['progress']; ?>"></div>
                            </div>
                        </div>
                        <?php if ($prog['status'] != 'Completed'): ?>
                            <button class="flex items-center gap-2 text-indigo-600 hover:text-indigo-700">
                                <i class="fas fa-play"></i>
                                <span>Continue Course</span>
                            </button>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Certificates & Badges Tab -->
    <?php if ($activeTab == 'certificates'): ?>
        <div class="bg-white rounded-lg shadow p-6 mb-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">My Certificates</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <?php if (empty($myCertificates)): ?>
                    <!-- sample certificate card when no data -->
                    <div class="border-2 border-indigo-200 rounded-lg p-6 bg-gradient-to-br from-indigo-50 to-white">
                        <div class="flex items-start justify-between mb-4">
                            <i class="fas fa-award text-indigo-600 text-3xl"></i>
                            <span class="text-indigo-600 font-semibold">Score: 95%</span>
                        </div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2">Safety & Compliance Fundamentals</h3>
                        <p class="text-gray-600 text-sm mb-1">Instructor: Sarah Thompson</p>
                        <p class="text-gray-600 text-sm mb-4">
                            Issued: 12/28/2024
                        </p>
                        <p class="text-gray-500 text-sm mb-4">ID: CERT-2024-12345</p>
                        <button class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Download Certificate
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($myCertificates as $cert): ?>
                        <div class="border-2 border-indigo-200 rounded-lg p-6 bg-gradient-to-br from-indigo-50 to-white">
                            <div class="flex items-start justify-between mb-4">
                                <i class="fas fa-award text-indigo-600 text-3xl"></i>
                                <span class="text-indigo-600 font-semibold">Score: <?php echo $cert['score']; ?>%</span>
                            </div>
                            <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($cert['certificate_name']); ?></h3>
                            <p class="text-gray-600 text-sm mb-1">Instructor: <?php echo htmlspecialchars($cert['instructor']); ?></p>
                            <p class="text-gray-600 text-sm mb-4">
                                Issued: <?php echo formatDate($cert['issue_date']); ?>
                            </p>
                            <p class="text-gray-500 text-sm mb-4">ID: <?php echo htmlspecialchars($cert['certificate_id']); ?></p>
                            <button onclick="downloadCertificate('<?php echo htmlspecialchars($cert['certificate_id']); ?>')" class="w-full px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                Download Certificate
                            </button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Achievement Badges</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <?php foreach ($myBadges as $badge): ?>
                    <div class="border border-gray-200 rounded-lg p-6 text-center hover:border-indigo-300 transition-colors">
                        <div class="text-5xl mb-3"><?php echo htmlspecialchars($badge['icon']); ?></div>
                        <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($badge['badge_name']); ?></h3>
                        <p class="text-gray-600 text-sm mb-3"><?php echo htmlspecialchars($badge['description']); ?></p>
                        <p class="text-gray-500 text-sm">
                            Earned: <?php echo formatDate($badge['earned_date']); ?>
                        </p>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Examinations Tab -->
    <?php if ($activeTab == 'examinations'): ?>
        <div class="bg-white rounded-lg shadow p-6">
            <h2 class="text-xl font-bold text-gray-900 mb-6">Course Examinations</h2>
            <div class="space-y-4">
                <?php if (empty($myExams)): ?>
                    <!-- sample examination card -->
                    <div class="border border-gray-200 rounded-lg p-6">
                        <div class="flex items-start justify-between mb-4">
                            <div>
                                <h3 class="text-lg font-semibold text-gray-900 mb-2">Advanced Fleet Management Techniques</h3>
                                <p class="text-gray-600 text-sm">
                                    Exam Date: 01/20/2025
                                </p>
                            </div>
                            <span class="px-3 py-1 rounded-full text-sm bg-gray-200 text-gray-700">
                                Scheduled
                            </span>
                        </div>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Duration</p>
                                <p class="text-gray-900">90 minutes</p>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Passing Score</p>
                                <p class="text-gray-900">80%</p>
                            </div>
                            <div class="bg-gray-50 p-3 rounded-lg">
                                <p class="text-gray-600 text-sm mb-1">Attempts Allowed</p>
                                <p class="text-gray-900">3</p>
                            </div>
                        </div>
                        <button class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                            Start Examination
                        </button>
                    </div>
                <?php else: ?>
                    <?php foreach ($myExams as $exam): ?>
                        <div class="border border-gray-200 rounded-lg p-6">
                            <div class="flex items-start justify-between mb-4">
                                <div>
                                    <h3 class="text-lg font-semibold text-gray-900 mb-2"><?php echo htmlspecialchars($exam['course_name']); ?></h3>
                                    <p class="text-gray-600 text-sm">
                                        Exam Date: <?php echo formatDate($exam['exam_date']); ?>
                                    </p>
                                </div>
                                <span class="px-3 py-1 rounded-full text-sm <?php echo getStatusBadge($exam['status']); ?>">
                                    <?php echo htmlspecialchars($exam['status']); ?>
                                </span>
                            </div>
                            <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-4">
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-gray-600 text-sm mb-1">Duration</p>
                                    <p class="text-gray-900"><?php echo htmlspecialchars($exam['duration']); ?></p>
                                </div>
                                <div class="bg-gray-50 p-3 rounded-lg">
                                    <p class="text-gray-600 text-sm mb-1">Passing Score</p>
                                    <p class="text-gray-900"><?php echo $exam['passing_score']; ?>%</p>
                                </div>
                                <?php if ($exam['status'] == 'Passed' && $exam['score']): ?>
                                    <div class="bg-green-50 p-3 rounded-lg">
                                        <p class="text-gray-600 text-sm mb-1">Your Score</p>
                                        <p class="text-green-700 font-semibold"><?php echo $exam['score']; ?>%</p>
                                    </div>
                                <?php endif; ?>
                                <?php if ($exam['status'] == 'Scheduled'): ?>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <p class="text-gray-600 text-sm mb-1">Attempts Allowed</p>
                                        <p class="text-gray-900"><?php echo $exam['attempts_allowed']; ?></p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <?php if ($exam['status'] == 'Scheduled'): ?>
                                <button onclick="startExamination(<?php echo $exam['id']; ?>)" class="px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                    Start Examination
                                </button>
                            <?php endif; ?>
                            <?php if ($exam['status'] == 'Passed'): ?>
                                <div class="flex items-center gap-2 text-green-600">
                                    <i class="fas fa-check-circle"></i>
                                    <span>Examination passed successfully!</span>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Course Preview Modal -->
<div id="coursePreviewModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-2xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-2xl font-bold text-gray-900" id="previewTitle">Course Title</h3>
            <button onclick="closeCoursePreview()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6 space-y-6" id="previewContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-between">
            <button onclick="closeCoursePreview()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
            <button id="enrollButton" onclick="confirmEnrollment()" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-semibold">
                Enroll Now
            </button>
        </div>
    </div>
</div>

<!-- Course Module Modal -->
<div id="courseModuleModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-4xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <div>
                <h3 class="text-2xl font-bold text-gray-900" id="moduleTitle">Course Module</h3>
                <p class="text-gray-600 text-sm" id="moduleSubtitle">Module X of Y</p>
            </div>
            <button onclick="closeCourseModule()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="moduleContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-between">
            <button id="prevModuleBtn" onclick="navigateModule('prev')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors" disabled>
                <i class="fas fa-arrow-left mr-2"></i>Previous Module
            </button>
            <div class="flex gap-2">
                <button onclick="closeCourseModule()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                    Close
                </button>
                <button id="nextModuleBtn" onclick="navigateModule('next')" class="px-6 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors font-semibold">
                    Next Module <i class="fas fa-arrow-right ml-2"></i>
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Course Details Modal -->

<div id="courseDetailsModal" class="fixed inset-0 bg-black bg-opacity-50 hidden z-50 flex items-center justify-center">
    <div class="bg-white rounded-lg shadow-xl max-w-3xl w-full mx-4 max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-gray-200 flex items-center justify-between">
            <h3 class="text-xl font-bold text-gray-900" id="courseModalTitle">Course Details</h3>
            <button onclick="closeCourseDetailsModal()" class="text-gray-400 hover:text-gray-600">
                <i class="fas fa-times text-xl"></i>
            </button>
        </div>
        <div class="p-6" id="courseDetailsContent">
            <!-- Content will be populated by JavaScript -->
        </div>
        <div class="p-6 border-t border-gray-200 flex justify-end">
            <button onclick="closeCourseDetailsModal()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition-colors">
                Close
            </button>
        </div>
    </div>
</div>

<script>
// Animate progress bars on page load
document.addEventListener('DOMContentLoaded', function() {
    // Animate course progress bars
    const courseBars = document.querySelectorAll('.course-progress-bar');
    courseBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target')) || 0;
        animateProgressBar(bar, target);
    });

    // Animate learning progress bars
    const learningBars = document.querySelectorAll('.learning-progress-bar');
    learningBars.forEach(bar => {
        const target = parseInt(bar.getAttribute('data-target')) || 0;
        animateProgressBar(bar, target);
    });

    // Add hover effects to course cards
    const courseCards = document.querySelectorAll('.border.border-gray-200.rounded-lg');
    courseCards.forEach(card => {
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

    // Add hover effects to badge cards
    const badgeCards = document.querySelectorAll('.border.border-gray-200.rounded-lg.text-center');
    badgeCards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.05)';
            this.style.transition = 'transform 0.2s ease';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
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

// Global variable to store current course being previewed
let currentCoursePreview = null;

function showCoursePreview(courseData) {
    if (courseData.enrolled) {
        // If already enrolled, continue learning
        window.location.href = `learning.php?tab=progress&course_id=${courseData.id}`;
        return;
    }
    
    currentCoursePreview = courseData;
    const modal = document.getElementById('coursePreviewModal');
    const title = document.getElementById('previewTitle');
    const content = document.getElementById('previewContent');
    
    title.textContent = courseData.title;
    
    // Create lecture/syllabus content
    let lectureContent = `
        <div class="space-y-6">
            <!-- Course Header Info -->
            <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                    <div>
                        <p class="text-indigo-600 text-sm font-semibold">Category</p>
                        <p class="text-gray-900 font-medium">${courseData.category}</p>
                    </div>
                    <div>
                        <p class="text-indigo-600 text-sm font-semibold">Level</p>
                        <p class="text-gray-900 font-medium">${courseData.level}</p>
                    </div>
                    <div>
                        <p class="text-indigo-600 text-sm font-semibold">Duration</p>
                        <p class="text-gray-900 font-medium">${courseData.duration}</p>
                    </div>
                    <div>
                        <p class="text-indigo-600 text-sm font-semibold">Modules</p>
                        <p class="text-gray-900 font-medium">${courseData.modules_count}</p>
                    </div>
                </div>
            </div>
            
            <!-- Course Description -->
            <div>
                <h4 class="text-lg font-bold text-gray-900 mb-3">Course Overview</h4>
                <p class="text-gray-700 leading-relaxed">${courseData.description}</p>
            </div>
            
            <!-- Instructor Info -->
            <div class="bg-gray-50 rounded-lg p-4 border border-gray-200">
                <h4 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                    <i class="fas fa-user-tie text-indigo-600"></i>
                    Instructor
                </h4>
                <p class="text-gray-700">${courseData.instructor}</p>
            </div>
            
            <!-- Course Syllabus -->
            <div>
                <h4 class="text-lg font-bold text-gray-900 mb-3">Course Syllabus</h4>
                <div class="space-y-3">
                    <div class="border-l-4 border-indigo-600 pl-4 py-2">
                        <p class="font-semibold text-gray-900">Module 1: Introduction & Fundamentals</p>
                        <p class="text-sm text-gray-600">Learn the basics and key concepts of the course</p>
                    </div>
                    <div class="border-l-4 border-indigo-600 pl-4 py-2">
                        <p class="font-semibold text-gray-900">Module 2: Core Concepts & Applications</p>
                        <p class="text-sm text-gray-600">Deep dive into main topics and real-world applications</p>
                    </div>
                    <div class="border-l-4 border-indigo-600 pl-4 py-2">
                        <p class="font-semibold text-gray-900">Module 3: Advanced Topics</p>
                        <p class="text-sm text-gray-600">Explore advanced techniques and best practices</p>
                    </div>
                    <div class="border-l-4 border-indigo-600 pl-4 py-2">
                        <p class="font-semibold text-gray-900">Module ${courseData.modules_count}: Final Project & Assessment</p>
                        <p class="text-sm text-gray-600">Apply your knowledge with a comprehensive project</p>
                    </div>
                </div>
            </div>
            
            <!-- What You'll Learn -->
            <div>
                <h4 class="text-lg font-bold text-gray-900 mb-3">What You'll Learn</h4>
                <ul class="space-y-2">
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-600 mt-1"></i>
                        <span class="text-gray-700">Master the core principles and methodologies</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-600 mt-1"></i>
                        <span class="text-gray-700">Develop practical skills through hands-on projects</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-600 mt-1"></i>
                        <span class="text-gray-700">Gain industry-relevant knowledge and certifications</span>
                    </li>
                    <li class="flex items-start gap-3">
                        <i class="fas fa-check-circle text-green-600 mt-1"></i>
                        <span class="text-gray-700">Network with professionals in the field</span>
                    </li>
                </ul>
            </div>
            
            <!-- Rating & Reviews -->
            <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                <div class="flex items-center gap-2 mb-2">
                    <i class="fas fa-star text-yellow-500"></i>
                    <span class="font-semibold text-gray-900">${courseData.rating} / 5.0</span>
                    <span class="text-gray-600 text-sm">(${courseData.reviews_count} reviews)</span>
                </div>
                <p class="text-sm text-gray-700">Highly rated course with positive student feedback</p>
            </div>
            
            <!-- Requirements -->
            <div>
                <h4 class="text-lg font-bold text-gray-900 mb-3">Requirements</h4>
                <ul class="space-y-2 text-gray-700">
                    <li class="flex items-center gap-2"><i class="fas fa-check text-indigo-600"></i> Basic understanding of the subject</li>
                    <li class="flex items-center gap-2"><i class="fas fa-check text-indigo-600"></i> Computer with internet access</li>
                    <li class="flex items-center gap-2"><i class="fas fa-check text-indigo-600"></i> Commitment to complete all modules</li>
                </ul>
            </div>
        </div>
    `;
    
    content.innerHTML = lectureContent;
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCoursePreview() {
    const modal = document.getElementById('coursePreviewModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentCoursePreview = null;
}

function confirmEnrollment() {
    if (!currentCoursePreview) return;
    
    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '';
    
    const actionInput = document.createElement('input');
    actionInput.type = 'hidden';
    actionInput.name = 'action';
    actionInput.value = 'enroll';
    form.appendChild(actionInput);
    
    const courseInput = document.createElement('input');
    courseInput.type = 'hidden';
    courseInput.name = 'course_id';
    courseInput.value = currentCoursePreview.id;
    form.appendChild(courseInput);
    
    document.body.appendChild(form);
    form.submit();
}

// Handle clicking outside modal to close
document.addEventListener('click', function(event) {
    const modal = document.getElementById('coursePreviewModal');
    if (modal && event.target === modal) {
        closeCoursePreview();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCoursePreview();
        closeCourseModule();
    }
});

// Course Module Functions
let currentCourseData = null;
let currentModuleIndex = 0;

function startCourseModule(courseData) {
    currentCourseData = courseData;
    currentModuleIndex = courseData.completed_modules || 0;

    // If they've completed all modules, show completion message
    if (currentModuleIndex >= courseData.total_modules) {
        alert('Congratulations! You have completed all modules in this course.');
        return;
    }

    showModuleContent();
}

function showModuleContent() {
    const modal = document.getElementById('courseModuleModal');
    const title = document.getElementById('moduleTitle');
    const subtitle = document.getElementById('moduleSubtitle');
    const content = document.getElementById('moduleContent');
    const prevBtn = document.getElementById('prevModuleBtn');
    const nextBtn = document.getElementById('nextModuleBtn');

    // Update title and subtitle
    title.textContent = currentCourseData.name;
    subtitle.textContent = `Module ${currentModuleIndex + 1} of ${currentCourseData.total_modules}`;

    // Generate module content based on course name and module number
    const moduleContent = generateModuleContent(currentCourseData.name, currentModuleIndex + 1);

    content.innerHTML = moduleContent;

    // Update navigation buttons
    prevBtn.disabled = currentModuleIndex === 0;
    nextBtn.innerHTML = currentModuleIndex + 1 >= currentCourseData.total_modules ?
        'Complete Course <i class="fas fa-check ml-2"></i>' :
        'Next Module <i class="fas fa-arrow-right ml-2"></i>';

    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function generateModuleContent(courseName, moduleNumber) {
    const modules = {
        'New Hire Orientation': [
            {
                title: 'Welcome to Costa Cargo Freight System',
                content: `
                    <div class="space-y-6">
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-xl font-bold text-blue-900 mb-3">Module 1: Company Overview</h4>
                            <p class="text-blue-800 leading-relaxed mb-4">
                                Welcome to Costa Cargo Freight System! This module introduces you to our company history,
                                mission, values, and organizational structure.
                            </p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h5 class="font-semibold text-blue-900 mb-2">Our Mission</h5>
                                    <p class="text-sm text-blue-700">To provide reliable, efficient, and sustainable freight solutions worldwide.</p>
                                </div>
                                <div>
                                    <h5 class="font-semibold text-blue-900 mb-2">Our Values</h5>
                                    <p class="text-sm text-blue-700">Safety, Integrity, Excellence, and Customer Focus</p>
                                </div>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-6">
                            <h4 class="text-lg font-bold text-gray-900 mb-4">Key Learning Points</h4>
                            <ul class="space-y-3">
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Understanding company history and evolution</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Company mission, vision, and core values</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Organizational structure and key departments</span>
                                </li>
                                <li class="flex items-start gap-3">
                                    <i class="fas fa-check-circle text-green-600 mt-1"></i>
                                    <span class="text-gray-700">Company culture and workplace expectations</span>
                                </li>
                            </ul>
                        </div>

                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <div class="flex items-start gap-3">
                                <i class="fas fa-lightbulb text-yellow-600 text-xl"></i>
                                <div>
                                    <h5 class="font-semibold text-yellow-900">Did You Know?</h5>
                                    <p class="text-yellow-800 text-sm">Costa Cargo has been serving customers for over 25 years, with operations in 15 countries and a fleet of over 500 vehicles.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Workplace Safety & Compliance',
                content: `
                    <div class="space-y-6">
                        <div class="bg-red-50 rounded-lg p-6 border border-red-200">
                            <h4 class="text-xl font-bold text-red-900 mb-3">Module 2: Safety First</h4>
                            <p class="text-red-800 leading-relaxed mb-4">
                                Safety is our top priority at Costa Cargo. This module covers essential safety protocols,
                                emergency procedures, and compliance requirements.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-gray-50 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-shield-alt text-blue-600"></i>
                                    Safety Equipment
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Safety vests and helmets</li>
                                    <li>• Steel-toed boots</li>
                                    <li>• High-visibility clothing</li>
                                    <li>• Personal protective equipment (PPE)</li>
                                </ul>
                            </div>

                            <div class="bg-gray-50 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-exclamation-triangle text-orange-600"></i>
                                    Emergency Procedures
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Emergency exit locations</li>
                                    <li>• First aid kit locations</li>
                                    <li>• Emergency contact numbers</li>
                                    <li>• Evacuation procedures</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <h5 class="font-semibold text-green-900 mb-2">Safety Checklist</h5>
                            <div class="space-y-2">
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded">
                                    <span class="text-sm text-green-800">I understand the safety protocols</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded">
                                    <span class="text-sm text-green-800">I know the location of emergency exits</span>
                                </label>
                                <label class="flex items-center gap-2">
                                    <input type="checkbox" class="rounded">
                                    <span class="text-sm text-green-800">I am familiar with PPE requirements</span>
                                </label>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Freight Management System Basics',
                content: `
                    <div class="space-y-6">
                        <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                            <h4 class="text-xl font-bold text-indigo-900 mb-3">Module 3: FMS Overview</h4>
                            <p class="text-indigo-800 leading-relaxed mb-4">
                                Learn the fundamentals of our Freight Management System (FMS) and how it supports
                                our daily operations and customer service.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-truck text-3xl text-indigo-600 mb-2"></i>
                                <h6 class="font-semibold text-gray-900">Shipment Tracking</h6>
                                <p class="text-sm text-gray-600">Monitor cargo from pickup to delivery</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-route text-3xl text-green-600 mb-2"></i>
                                <h6 class="font-semibold text-gray-900">Route Optimization</h6>
                                <p class="text-sm text-gray-600">Efficient delivery planning</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-chart-bar text-3xl text-blue-600 mb-2"></i>
                                <h6 class="font-semibold text-gray-900">Analytics</h6>
                                <p class="text-sm text-gray-600">Performance insights and reporting</p>
                            </div>
                        </div>

                        <div class="bg-gray-50 rounded-lg p-6">
                            <h5 class="font-semibold text-gray-900 mb-3">Key FMS Features</h5>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <h6 class="text-indigo-600 font-medium mb-2">For Employees:</h6>
                                    <ul class="text-sm text-gray-700 space-y-1">
                                        <li>• Shipment status updates</li>
                                        <li>• Route planning tools</li>
                                        <li>• Customer communication</li>
                                        <li>• Performance tracking</li>
                                    </ul>
                                </div>
                                <div>
                                    <h6 class="text-indigo-600 font-medium mb-2">For Customers:</h6>
                                    <ul class="text-sm text-gray-700 space-y-1">
                                        <li>• Real-time tracking</li>
                                        <li>• Online booking</li>
                                        <li>• Invoice management</li>
                                        <li>• Support portal</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Employee Code of Conduct',
                content: `
                    <div class="space-y-6">
                        <div class="bg-purple-50 rounded-lg p-6 border border-purple-200">
                            <h4 class="text-xl font-bold text-purple-900 mb-3">Module 4: Professional Standards</h4>
                            <p class="text-purple-800 leading-relaxed mb-4">
                                Understanding our code of conduct ensures we maintain the highest standards of
                                professionalism and integrity in all our operations.
                            </p>
                        </div>

                        <div class="space-y-4">
                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-handshake text-green-600"></i>
                                    Professional Conduct
                                </h5>
                                <p class="text-sm text-gray-700">Maintain professional relationships with colleagues, customers, and partners. Respect diversity and promote a positive work environment.</p>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-lock text-blue-600"></i>
                                    Confidentiality
                                </h5>
                                <p class="text-sm text-gray-700">Protect sensitive company and customer information. Never share proprietary data or customer details without authorization.</p>
                            </div>

                            <div class="border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-2 flex items-center gap-2">
                                    <i class="fas fa-balance-scale text-orange-600"></i>
                                    Ethics & Compliance
                                </h5>
                                <p class="text-sm text-gray-700">Follow all laws, regulations, and company policies. Report any violations or concerns through proper channels.</p>
                            </div>
                        </div>

                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <h5 class="font-semibold text-yellow-900 mb-2">Remember</h5>
                            <p class="text-yellow-800 text-sm">Your actions reflect on the entire Costa Cargo team. Always act with integrity, professionalism, and respect for others.</p>
                        </div>
                    </div>
                `
            }
        ],
        'Leadership Development Program': [
            {
                title: 'Leadership Foundations',
                content: `
                    <div class="space-y-6">
                        <div class="bg-indigo-50 rounded-lg p-6 border border-indigo-200">
                            <h4 class="text-xl font-bold text-indigo-900 mb-3">Module 1: What is Leadership?</h4>
                            <p class="text-indigo-800 leading-relaxed mb-4">
                                Leadership is the art of inspiring and guiding others toward achieving common goals.
                                This module explores different leadership styles and their applications.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Leadership Styles</h5>
                                <ul class="space-y-2 text-sm">
                                    <li><strong>Autocratic:</strong> Centralized decision-making</li>
                                    <li><strong>Democratic:</strong> Team-based decisions</li>
                                    <li><strong>Laissez-faire:</strong> Minimal supervision</li>
                                    <li><strong>Transformational:</strong> Inspiring change</li>
                                </ul>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Key Leadership Traits</h5>
                                <ul class="space-y-2 text-sm">
                                    <li>• Vision and purpose</li>
                                    <li>• Communication skills</li>
                                    <li>• Emotional intelligence</li>
                                    <li>• Decision-making ability</li>
                                    <li>• Integrity and ethics</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-green-50 rounded-lg p-4 border border-green-200">
                            <h5 class="font-semibold text-green-900 mb-2">Leadership Self-Assessment</h5>
                            <p class="text-green-800 text-sm mb-3">Rate yourself on these leadership qualities (1-5 scale):</p>
                            <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-sm">
                                <div>Communication: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Empathy: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Vision: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Decision Making: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Team Building: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                                <div>Integrity: <input type="number" min="1" max="5" class="w-12 border rounded text-center"></div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Team Building & Motivation',
                content: `
                    <div class="space-y-6">
                        <div class="bg-blue-50 rounded-lg p-6 border border-blue-200">
                            <h4 class="text-xl font-bold text-blue-900 mb-3">Module 2: Building High-Performing Teams</h4>
                            <p class="text-blue-800 leading-relaxed mb-4">
                                Effective leaders understand how to build, motivate, and maintain high-performing teams.
                                This module covers essential team building strategies and motivation techniques.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-users text-blue-600"></i>
                                    Team Building Activities
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Icebreaker exercises</li>
                                    <li>• Trust-building activities</li>
                                    <li>• Problem-solving challenges</li>
                                    <li>• Team retreats and workshops</li>
                                    <li>• Cross-functional projects</li>
                                </ul>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3 flex items-center gap-2">
                                    <i class="fas fa-bullseye text-green-600"></i>
                                    Motivation Strategies
                                </h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Clear goal setting</li>
                                    <li>• Recognition and rewards</li>
                                    <li>• Career development opportunities</li>
                                    <li>• Work-life balance support</li>
                                    <li>• Meaningful work assignments</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-yellow-50 rounded-lg p-4 border border-yellow-200">
                            <h5 class="font-semibold text-yellow-900 mb-2">Motivation Theory</h5>
                            <p class="text-yellow-800 text-sm mb-3">Understanding what drives people is key to effective leadership:</p>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-sm">
                                <div>
                                    <strong>Intrinsic Motivation:</strong> Internal satisfaction from the work itself
                                </div>
                                <div>
                                    <strong>Extrinsic Motivation:</strong> External rewards and recognition
                                </div>
                            </div>
                        </div>
                    </div>
                `
            },
            {
                title: 'Communication & Conflict Resolution',
                content: `
                    <div class="space-y-6">
                        <div class="bg-green-50 rounded-lg p-6 border border-green-200">
                            <h4 class="text-xl font-bold text-green-900 mb-3">Module 3: Effective Communication</h4>
                            <p class="text-green-800 leading-relaxed mb-4">
                                Communication is the foundation of leadership. Learn to communicate clearly,
                                listen actively, and resolve conflicts constructively.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-comments text-blue-600 text-2xl mb-2"></i>
                                <h6 class="font-semibold text-gray-900 mb-1">Verbal</h6>
                                <p class="text-sm text-gray-600">Clear, concise speech</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-envelope text-green-600 text-2xl mb-2"></i>
                                <h6 class="font-semibold text-gray-900 mb-1">Written</h6>
                                <p class="text-sm text-gray-600">Professional documentation</p>
                            </div>
                            <div class="bg-white border border-gray-200 rounded-lg p-4 text-center">
                                <i class="fas fa-users text-purple-600 text-2xl mb-2"></i>
                                <h6 class="font-semibold text-gray-900 mb-1">Non-verbal</h6>
                                <p class="text-sm text-gray-600">Body language & presence</p>
                            </div>
                        </div>

                        <div class="bg-red-50 rounded-lg p-4 border border-red-200">
                            <h5 class="font-semibold text-red-900 mb-3 flex items-center gap-2">
                                <i class="fas fa-gavel"></i>
                                Conflict Resolution Steps
                            </h5>
                            <ol class="list-decimal list-inside space-y-2 text-sm text-red-800">
                                <li>Identify the conflict and involved parties</li>
                                <li>Understand each perspective</li>
                                <li>Find common ground and shared goals</li>
                                <li>Brainstorm mutually beneficial solutions</li>
                                <li>Agree on a resolution and follow-up plan</li>
                                <li>Monitor progress and adjust as needed</li>
                            </ol>
                        </div>
                    </div>
                `
            },
            {
                title: 'Strategic Leadership & Change Management',
                content: `
                    <div class="space-y-6">
                        <div class="bg-purple-50 rounded-lg p-6 border border-purple-200">
                            <h4 class="text-xl font-bold text-purple-900 mb-3">Module 4: Leading Through Change</h4>
                            <p class="text-purple-800 leading-relaxed mb-4">
                                Strategic leadership involves guiding organizations through change while maintaining
                                stability and achieving long-term objectives.
                            </p>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Strategic Planning Process</h5>
                                <ol class="list-decimal list-inside space-y-2 text-sm text-gray-700">
                                    <li>Assess current situation</li>
                                    <li>Define vision and goals</li>
                                    <li>Analyze opportunities and threats</li>
                                    <li>Develop action plans</li>
                                    <li>Implement and monitor progress</li>
                                    <li>Adjust strategies as needed</li>
                                </ol>
                            </div>

                            <div class="bg-white border border-gray-200 rounded-lg p-4">
                                <h5 class="font-semibold text-gray-900 mb-3">Change Management</h5>
                                <ul class="space-y-2 text-sm text-gray-700">
                                    <li>• Communicate vision clearly</li>
                                    <li>• Address resistance proactively</li>
                                    <li>• Provide support and training</li>
                                    <li>• Celebrate small wins</li>
                                    <li>• Maintain open communication</li>
                                    <li>• Lead by example</li>
                                </ul>
                            </div>
                        </div>

                        <div class="bg-indigo-50 rounded-lg p-4 border border-indigo-200">
                            <h5 class="font-semibold text-indigo-900 mb-2">Leadership Legacy</h5>
                            <p class="text-indigo-800 text-sm">Great leaders create lasting impact by:</p>
                            <ul class="list-disc list-inside space-y-1 text-sm text-indigo-700 mt-2">
                                <li>Developing future leaders</li>
                                <li>Building sustainable systems</li>
                                <li>Fostering innovation and growth</li>
                                <li>Creating positive organizational culture</li>
                                <li>Driving meaningful change</li>
                            </ul>
                        </div>
                    </div>
                `
            }
        ]
    };

    // Get course-specific modules or use default
    const courseModules = modules[courseName] || modules['New Hire Orientation'];

    // Return the appropriate module content
    if (moduleNumber <= count(courseModules)) {
        return courseModules[moduleNumber - 1].content;
    }

    // Fallback content
    return `
        <div class="text-center py-8">
            <i class="fas fa-book-open text-4xl text-gray-300 mb-4"></i>
            <p class="text-gray-600">Module content for "${courseName}" is being prepared.</p>
            <p class="text-gray-500 text-sm mt-2">Please check back later or contact your instructor.</p>
        </div>
    `;
}

function navigateModule(direction) {
    if (direction === 'prev' && currentModuleIndex > 0) {
        currentModuleIndex--;
        showModuleContent();
    } else if (direction === 'next') {
        if (currentModuleIndex + 1 >= currentCourseData.total_modules) {
            // Complete the course
            completeCourse();
        } else {
            currentModuleIndex++;
            showModuleContent();
        }
    }
}

function completeCourse() {
    // Update progress in database
    const formData = new FormData();
    formData.append('action', 'update_progress');
    formData.append('course_id', currentCourseData.id);
    formData.append('completed_modules', currentCourseData.total_modules);
    formData.append('progress', 100);

    fetch('learning-ajax.php', {
        method: 'POST',
        body: formData
    })
    .then(resp => resp.json())
    .then(data => {
        if (data.success) {
            alert('Congratulations! You have completed the course.');
            closeCourseModule();
            // Refresh the page to show updated progress
            window.location.reload();
        } else {
            alert('Error updating progress: ' + data.message);
        }
    })
    .catch(err => {
        console.error(err);
        alert('Error completing course');
    });
}

function closeCourseModule() {
    const modal = document.getElementById('courseModuleModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
    currentCourseData = null;
    currentModuleIndex = 0;
}


// Download Certificate
function downloadCertificate(certificateId) {
    // You can implement certificate download logic here
    window.location.href = `learning.php?action=download_certificate&cert_id=${certificateId}`;
}

// Start Examination
function startExamination(examId) {
    if (confirm('Are you ready to start the examination? You will not be able to pause once started.')) {
        window.location.href = `learning.php?tab=examinations&exam_id=${examId}&start=1`;
    }
}

// Course Details Modal (for future use)
function showCourseDetailsModal(courseData) {
    const modal = document.getElementById('courseDetailsModal');
    const title = document.getElementById('courseModalTitle');
    const content = document.getElementById('courseDetailsContent');
    
    title.textContent = courseData.title;
    content.innerHTML = `
        <div class="space-y-4">
            <p class="text-gray-600">${courseData.description}</p>
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <p class="text-gray-600 text-sm">Duration</p>
                    <p class="text-gray-900">${courseData.duration}</p>
                </div>
                <div>
                    <p class="text-gray-600 text-sm">Modules</p>
                    <p class="text-gray-900">${courseData.modules_count}</p>
                </div>
            </div>
        </div>
    `;
    
    modal.classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function closeCourseDetailsModal() {
    const modal = document.getElementById('courseDetailsModal');
    modal.classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.addEventListener('click', function(event) {
    const modal = document.getElementById('courseDetailsModal');
    if (event.target === modal) {
        closeCourseDetailsModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeCourseDetailsModal();
    }
});

// Course Catalog Filter Functions
function filterCourses() {
    const searchQuery = document.getElementById('courseSearch').value.toLowerCase();
    const categoryFilter = document.getElementById('categoryFilter').value;
    const levelFilter = document.getElementById('levelFilter').value;
    
    const courseCards = document.querySelectorAll('.course-card');
    let visibleCount = 0;
    
    courseCards.forEach(card => {
        const title = card.dataset.title;
        const description = card.dataset.description;
        const category = card.dataset.category;
        const level = card.dataset.level;
        
        // Check if card matches all filters
        const matchesSearch = title.includes(searchQuery) || description.includes(searchQuery);
        const matchesCategory = !categoryFilter || category === categoryFilter;
        const matchesLevel = !levelFilter || level === levelFilter;
        
        if (matchesSearch && matchesCategory && matchesLevel) {
            card.style.display = '';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Show/hide "no results" message
    const container = document.getElementById('coursesContainer');
    let noResultsMessage = container.querySelector('.no-results-message');
    
    if (visibleCount === 0) {
        if (!noResultsMessage) {
            noResultsMessage = document.createElement('div');
            noResultsMessage.className = 'no-results-message col-span-full text-center py-12';
            noResultsMessage.innerHTML = `
                <i class="fas fa-search text-4xl text-gray-300 mb-4"></i>
                <p class="text-gray-600 text-lg">No courses match your filters</p>
                <p class="text-gray-500 text-sm">Try adjusting your search criteria</p>
            `;
            container.appendChild(noResultsMessage);
        }
    } else if (noResultsMessage) {
        noResultsMessage.remove();
    }
}

// Clear all filters
function clearFilters() {
    document.getElementById('courseSearch').value = '';
    document.getElementById('categoryFilter').value = '';
    document.getElementById('levelFilter').value = '';
    filterCourses();
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', function() {
    // Animate progress bars
    animateProgressBars();
});

function animateProgressBars() {
    const progressBars = document.querySelectorAll('[class*="progress-bar"]');
    progressBars.forEach(bar => {
        const target = parseFloat(bar.dataset.target) || 0;
        let current = 0;
        const increment = target / 50;
        
        const timer = setInterval(() => {
            current += increment;
            if (current >= target) {
                bar.style.width = target + '%';
                clearInterval(timer);
            } else {
                bar.style.width = current + '%';
            }
        }, 30);
    });
}
</script>

<?php
$content = ob_get_clean();
require_once 'includes/layout.php';
require_once 'includes/footer.php';
?>
