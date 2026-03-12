<?php
/**
 * Training Management AJAX Handler
 * Handles all AJAX requests for training management operations
 */

require_once 'config/config.php';
requireLogin();

header('Content-Type: application/json');

$action = $_POST['action'] ?? $_GET['action'] ?? '';
$pdo = getDBConnection();

try {
    switch ($action) {
        case 'create_program':
            createTrainingProgram($pdo);
            break;
            
        case 'update_program':
            updateTrainingProgram($pdo);
            break;
            
        case 'delete_program':
            deleteTrainingProgram($pdo);
            break;
            
        case 'get_program':
            getTrainingProgram($pdo);
            break;
            
        case 'enroll_participant':
            enrollParticipant($pdo);
            break;
            
        case 'update_participant':
            updateParticipant($pdo);
            break;
            
        case 'remove_participant':
            removeParticipant($pdo);
            break;
            
        case 'create_schedule':
            createTrainingSchedule($pdo);
            break;
            
        case 'update_schedule':
            updateTrainingSchedule($pdo);
            break;
            
        case 'delete_schedule':
            deleteTrainingSchedule($pdo);
            break;
            
        case 'get_participants':
            getProgramParticipants($pdo);
            break;
            
        case 'update_completion':
            updateCompletion($pdo);
            break;
        
        case 'get_schedule':
            getSchedule($pdo);
            break;
        
        case 'retake_program':
            retakeProgram($pdo);
            break;
            
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}

function createTrainingProgram($pdo) {
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $status = $_POST['status'] ?? 'Upcoming';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $instructor = $_POST['instructor'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($title)) {
        echo json_encode(['success' => false, 'message' => 'Title is required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO training_programs (title, category, duration, status, start_date, end_date, instructor, description)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $title,
        $category,
        $duration,
        $status,
        $start_date ?: null,
        $end_date ?: null,
        $instructor,
        $description
    ]);
    
    $programId = $pdo->lastInsertId();
    
    echo json_encode([
        'success' => true,
        'message' => 'Training program created successfully',
        'program_id' => $programId
    ]);
}

function updateTrainingProgram($pdo) {
    $id = $_POST['id'] ?? 0;
    $title = $_POST['title'] ?? '';
    $category = $_POST['category'] ?? '';
    $duration = $_POST['duration'] ?? '';
    $status = $_POST['status'] ?? 'Upcoming';
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $instructor = $_POST['instructor'] ?? '';
    $description = $_POST['description'] ?? '';
    
    if (empty($id) || empty($title)) {
        echo json_encode(['success' => false, 'message' => 'ID and title are required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE training_programs 
        SET title = ?, category = ?, duration = ?, status = ?, start_date = ?, end_date = ?, instructor = ?, description = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $title,
        $category,
        $duration,
        $status,
        $start_date ?: null,
        $end_date ?: null,
        $instructor,
        $description,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training program updated successfully'
    ]);
}

function deleteTrainingProgram($pdo) {
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM training_programs WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training program deleted successfully'
    ]);
}

function getTrainingProgram($pdo) {
    $id = $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("SELECT * FROM training_programs WHERE id = ?");
    $stmt->execute([$id]);
    $program = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($program) {
        echo json_encode([
            'success' => true,
            'program' => $program
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Training program not found'
        ]);
    }
}

function enrollParticipant($pdo) {
    $program_id = $_POST['program_id'] ?? 0;
    $employee_id = $_POST['employee_id'] ?? 0;
    
    if (empty($program_id) || empty($employee_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID and Employee ID are required']);
        return;
    }
    
    // Check if already enrolled
    $check = $pdo->prepare("SELECT id FROM training_participants WHERE training_program_id = ? AND employee_id = ?");
    $check->execute([$program_id, $employee_id]);
    
    if ($check->fetch()) {
        echo json_encode(['success' => false, 'message' => 'Employee is already enrolled in this program']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO training_participants (training_program_id, employee_id, status, completion_percentage)
        VALUES (?, ?, 'Enrolled', 0)
    ");
    
    $stmt->execute([$program_id, $employee_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Participant enrolled successfully'
    ]);
}

function updateParticipant($pdo) {
    $id = $_POST['id'] ?? 0;
    $status = $_POST['status'] ?? '';
    $completion_percentage = $_POST['completion_percentage'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $completed_at = ($status == 'Completed') ? date('Y-m-d H:i:s') : null;
    
    $stmt = $pdo->prepare("
        UPDATE training_participants 
        SET status = ?, completion_percentage = ?, completed_at = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $status,
        $completion_percentage,
        $completed_at,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Participant updated successfully'
    ]);
}

function removeParticipant($pdo) {
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM training_participants WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Participant removed successfully'
    ]);
}

/**
 * Return all schedule entries for a given program.
 * Used by the lectures modal when an employee finishes enrollment.
 */
function getSchedule($pdo) {
    $program_id = $_GET['program_id'] ?? 0;
    if (empty($program_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }

    // fetch the normal schedule entries
    $stmt = $pdo->prepare("SELECT session_date, session_time, session_type, location, instructor
                         FROM training_schedule
                         WHERE training_program_id = ?
                         ORDER BY session_date, session_time");
    $stmt->execute([$program_id]);
    $schedule = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // determine if we need to include a special orientation lecture
    $extraContent = '';
    $titleStmt = $pdo->prepare("SELECT title FROM training_programs WHERE id = ?");
    $titleStmt->execute([$program_id]);
    $program = $titleStmt->fetch(PDO::FETCH_ASSOC);
    if ($program && strtolower($program['title']) === 'fleet management orientation') {
        // embed the long lecture content as HTML; this will be inserted above the regular
        // schedule list on the frontend when the modal shows lectures for this program.
        $extraContent = <<<HTML
<div class="prose max-w-none mb-6">
    <h3>Fleet Management Orientation</h3>

    <details open class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Module Overview</summary>
        <p class="mt-2">Fleet management is an important function in many organizations that rely on vehicles to deliver goods, transport people, or perform services. Companies such as logistics providers, delivery services, transportation companies, construction firms, and government agencies depend heavily on effective fleet operations.</p>
        <p>Fleet management ensures that company vehicles are properly maintained, efficiently used, safe to operate, and compliant with legal regulations. A well-managed fleet helps organizations reduce operational costs, improve productivity, and maintain high levels of safety.</p>
        <p>This orientation module introduces the fundamental concepts of fleet management, the responsibilities of fleet managers, the importance of technology in fleet operations, and the policies required to manage vehicles and drivers effectively.</p>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Learning Objectives</summary>
        <ul class="mt-2 list-disc list-inside">
            <li>Define fleet management and explain its importance in organizations.</li>
            <li>Identify the key responsibilities of fleet managers.</li>
            <li>Understand the major components of fleet management systems.</li>
            <li>Explain the importance of vehicle maintenance and driver safety.</li>
            <li>Recognize how technology improves fleet monitoring and efficiency.</li>
            <li>Understand the policies and regulations involved in fleet operations.</li>
        </ul>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 1: Introduction to Fleet Management</summary>
        <p class="mt-2">Fleet management refers to the administration, coordination, and supervision of a group of vehicles used for business operations.</p>
        <p>A fleet can include different types of vehicles such as:</p>
        <ul class="list-disc list-inside">
            <li>Passenger cars</li>
            <li>Delivery vans</li>
            <li>Trucks</li>
            <li>Motorcycles</li>
            <li>Buses</li>
            <li>Heavy equipment vehicles</li>
        </ul>
        <p>These vehicles are used by companies for activities such as:</p>
        <ul class="list-disc list-inside">
            <li>Delivering products</li>
            <li>Transporting employees</li>
        <li>Providing transportation services</li>
        <li>Supporting field operations</li>
        <li>Managing logistics and supply chain activities</li>
    </ul>
    <p>Without proper management, fleet operations can become expensive, inefficient, and unsafe. Therefore, organizations establish fleet management programs to ensure vehicles are used properly and maintained regularly.</p>
    <p>Fleet management involves planning, monitoring, maintenance, driver supervision, fuel control, and vehicle tracking.</p>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 2: Role and Responsibilities of a Fleet Manager</summary>
        <p class="mt-2">The fleet manager is responsible for overseeing the entire fleet operation within an organization. Their role involves managing vehicles, drivers, maintenance schedules, and operational costs.</p>
        <p>Key responsibilities include:</p>
        <ul class="list-disc list-inside">
            <li><strong>Vehicle Procurement</strong> – Fleet managers determine the types of vehicles required by the organization. They analyze operational needs and choose vehicles based on:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Budget</li>
                <li>Fuel efficiency</li>
                <li>Durability</li>
                <li>Capacity</li>
                <li>Environmental impact</li>
            </ul>
            <li><strong>Vehicle Maintenance Management</strong> – Maintaining vehicles is critical for safety and performance. Fleet managers ensure that vehicles undergo regular preventive maintenance to avoid mechanical failures. Maintenance tasks include:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Oil changes</li>
                <li>Brake inspections</li>
                <li>Tire replacement</li>
                <li>Engine checks</li>
                <li>Battery testing</li>
                <li>Safety inspections</li>
            </ul>
            <li><strong>Driver Management</strong> – Drivers are an important part of fleet operations. Fleet managers are responsible for ensuring drivers:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Follow company policies</li>
                <li>Obey traffic laws</li>
                <li>Maintain safe driving behavior</li>
                <li>Report vehicle issues immediately</li>
            </ul>
            <li><strong>Cost Control</strong> – Fleet operations involve several costs such as:</li>
            <ul class="list-disc list-inside ml-6">
                <li>Fuel</li>
                <li>Maintenance</li>
                <li>Insurance</li>
                <li>Vehicle depreciation</li>
                <li>Repairs</li>
                <li>Licensing and registration</li>
            </ul>
        </ul>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 3: Vehicle Maintenance and Lifecycle Management</summary>
        <p class="mt-2">Vehicles go through a lifecycle that includes acquisition, operation, maintenance, and eventual replacement.</p>
        <p><strong>Preventive Maintenance</strong> is scheduled servicing that prevents major vehicle failures. It ensures that vehicles remain reliable and safe. Examples include:</p>
        <ul class="list-disc list-inside">
            <li>Engine servicing</li>
            <li>Oil and filter replacement</li>
            <li>Tire rotation</li>
            <li>Brake inspection</li>
            <li>Cooling system maintenance</li>
        </ul>
        <p><strong>Corrective Maintenance</strong> occurs when a vehicle experiences mechanical problems that require repair. This may include:</p>
        <ul class="list-disc list-inside">
            <li>Engine repair</li>
            <li>Transmission issues</li>
            <li>Electrical system failures</li>
            <li>Suspension problems</li>
        </ul>
        <p><strong>Vehicle Replacement</strong> – Over time, vehicles become less efficient and more expensive to maintain. Fleet managers analyze vehicle performance data to determine when it is more cost-effective to replace vehicles rather than repair them.</p>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 4: Fuel Management</summary>
        <p class="mt-2">Fuel is one of the largest operational expenses in fleet management.</p>
        <p>Effective fuel management helps companies monitor consumption and identify inefficiencies. Fuel management strategies include:</p>
        <ul class="list-disc list-inside">
            <li>Monitoring fuel usage</li>
            <li>Implementing fuel cards</li>
            <li>Analyzing fuel consumption reports</li>
            <li>Preventing fuel theft or misuse</li>
            <li>Promoting fuel-efficient driving habits</li>
        </ul>
        <p>Drivers may also be trained to adopt eco-driving techniques, such as avoiding excessive acceleration and maintaining steady speeds.</p>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 5: Route Planning and Dispatching</summary>
        <p class="mt-2">Route planning ensures that vehicles travel the most efficient routes to reach their destinations.</p>
        <p>Effective route planning helps organizations:</p>
        <ul class="list-disc list-inside">
            <li>Reduce fuel consumption</li>
            <li>Minimize travel time</li>
            <li>Avoid traffic congestion</li>
            <li>Improve delivery schedules</li>
        </ul>
        <p>Dispatchers coordinate with drivers to assign routes and manage schedules. They may also communicate with drivers in real time to adjust routes if necessary.</p>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 6: Technology in Fleet Management</summary>
        <p class="mt-2">Modern fleet operations rely on Fleet Management Systems (FMS) to monitor vehicles and drivers.</p>
        <p>These systems use technologies such as:</p>
        <ul class="list-disc list-inside">
            <li>GPS tracking</li>
            <li>Telematics systems</li>
            <li>Mobile communication</li>
            <li>Vehicle sensors</li>
        </ul>
        <p>Through these technologies, fleet managers can monitor:</p>
        <ul class="list-disc list-inside">
            <li>Vehicle location</li>
            <li>Speed and driving behavior</li>
            <li>Fuel consumption</li>
            <li>Engine performance</li>
            <li>Maintenance alerts</li>
        </ul>
        <p>Real-time monitoring improves operational efficiency and enhances safety.</p>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 7: Safety and Compliance</summary>
        <p class="mt-2">Safety is a major priority in fleet operations. Organizations must ensure that both vehicles and drivers comply with government regulations and company policies.</p>
        <p>Safety measures include:</p>
        <ul class="list-disc list-inside">
            <li>Regular vehicle inspections</li>
            <li>Driver safety training</li>
            <li>Monitoring driver behavior</li>
            <li>Enforcing speed limits</li>
            <li>Ensuring proper vehicle loading</li>
        </ul>
        <p>Companies must also comply with legal requirements such as:</p>
        <ul class="list-disc list-inside">
            <li>Vehicle registration</li>
            <li>Insurance coverage</li>
            <li>Emission standards</li>
            <li>Driver licensing requirements</li>
        </ul>
        <p>Failure to comply with these regulations may result in penalties, accidents, or operational disruptions.</p>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Lesson 8: Benefits of Effective Fleet Management</summary>
        <p class="mt-2">Organizations that implement effective fleet management practices experience several benefits. These include:</p>
        <ul class="list-disc list-inside">
            <li><strong>Reduced Costs</strong> – Proper monitoring and maintenance reduce fuel expenses, repair costs, and vehicle downtime.</li>
            <li><strong>Increased Productivity</strong> – Efficient route planning and vehicle tracking help drivers complete tasks faster.</li>
            <li><strong>Improved Safety</strong> – Monitoring driver behavior and maintaining vehicles reduces the risk of accidents.</li>
            <li><strong>Better Decision-Making</strong> – Fleet management systems provide data and reports that help managers make informed decisions.</li>
            <li><strong>Environmental Sustainability</strong> – Fuel-efficient vehicles and eco-driving practices help reduce environmental impact.</li>
        </ul>
    </details>

    <details class="mb-4 rounded border p-4">
        <summary class="font-bold text-lg">Module Summary</summary>
        <p class="mt-2">Fleet management is a critical function for organizations that rely on vehicles for daily operations. It involves managing vehicles, drivers, maintenance schedules, fuel usage, and operational costs.</p>
        <p>By implementing proper fleet management strategies and using modern technology, organizations can ensure that their fleet operates efficiently, safely, and cost-effectively.</p>
        <p>A well-organized fleet management system not only improves operational performance but also contributes to long-term business success.</p>
    </details>
</div>
HTML;
    }

    echo json_encode([
        'success' => true,
        'schedule' => $schedule,
        'extra_content' => $extraContent
    ]);
}

/**
 * Reset current user's progress on a program so they can retake orientation.
 */
function retakeProgram($pdo) {
    $program_id = $_POST['program_id'] ?? 0;
    $employee_id = $_SESSION['user_id'];

    if (empty($program_id) || empty($employee_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }

    // either update or insert a fresh enrollment
    $check = $pdo->prepare("SELECT id FROM training_participants WHERE training_program_id = ? AND employee_id = ?");
    $check->execute([$program_id, $employee_id]);
    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {
        $stmt = $pdo->prepare("UPDATE training_participants SET status = 'Enrolled', completion_percentage = 0, enrolled_at = NOW(), completed_at = NULL WHERE id = ?");
        $stmt->execute([$existing['id']]);
    } else {
        $stmt = $pdo->prepare("INSERT INTO training_participants (training_program_id, employee_id, status, completion_percentage, enrolled_at) VALUES (?, ?, 'Enrolled', 0, NOW())");
        $stmt->execute([$program_id, $employee_id]);
    }

    echo json_encode(['success' => true, 'message' => 'Orientation reset, you can retake it now']);
}

function createTrainingSchedule($pdo) {
    $program_id = $_POST['program_id'] ?? 0;
    $session_date = $_POST['session_date'] ?? '';
    $session_time = $_POST['session_time'] ?? '';
    $session_type = $_POST['session_type'] ?? '';
    $location = $_POST['location'] ?? '';
    $instructor = $_POST['instructor'] ?? '';
    
    if (empty($program_id) || empty($session_date) || empty($session_time)) {
        echo json_encode(['success' => false, 'message' => 'Program ID, date, and time are required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        INSERT INTO training_schedule (training_program_id, session_date, session_time, session_type, location, instructor)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $program_id,
        $session_date,
        $session_time,
        $session_type,
        $location,
        $instructor
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training schedule created successfully'
    ]);
}

function updateTrainingSchedule($pdo) {
    $id = $_POST['id'] ?? 0;
    $session_date = $_POST['session_date'] ?? '';
    $session_time = $_POST['session_time'] ?? '';
    $session_type = $_POST['session_type'] ?? '';
    $location = $_POST['location'] ?? '';
    $instructor = $_POST['instructor'] ?? '';
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        UPDATE training_schedule 
        SET session_date = ?, session_time = ?, session_type = ?, location = ?, instructor = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $session_date,
        $session_time,
        $session_type,
        $location,
        $instructor,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training schedule updated successfully'
    ]);
}

function deleteTrainingSchedule($pdo) {
    $id = $_POST['id'] ?? $_GET['id'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("DELETE FROM training_schedule WHERE id = ?");
    $stmt->execute([$id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Training schedule deleted successfully'
    ]);
}

function getProgramParticipants($pdo) {
    $program_id = $_GET['program_id'] ?? 0;
    
    if (empty($program_id)) {
        echo json_encode(['success' => false, 'message' => 'Program ID is required']);
        return;
    }
    
    $stmt = $pdo->prepare("
        SELECT tpar.*, u.full_name, u.employee_id, u.email, u.department
        FROM training_participants tpar
        JOIN users u ON tpar.employee_id = u.id
        WHERE tpar.training_program_id = ?
        ORDER BY u.full_name ASC
    ");
    
    $stmt->execute([$program_id]);
    $participants = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'participants' => $participants
    ]);
}

function updateCompletion($pdo) {
    $id = $_POST['id'] ?? 0;
    $completion_percentage = $_POST['completion_percentage'] ?? 0;
    
    if (empty($id)) {
        echo json_encode(['success' => false, 'message' => 'ID is required']);
        return;
    }
    
    $completion_percentage = max(0, min(100, intval($completion_percentage)));
    $status = $completion_percentage == 100 ? 'Completed' : ($completion_percentage > 0 ? 'In Progress' : 'Enrolled');
    $completed_at = $completion_percentage == 100 ? date('Y-m-d H:i:s') : null;
    
    $stmt = $pdo->prepare("
        UPDATE training_participants 
        SET completion_percentage = ?, status = ?, completed_at = ?
        WHERE id = ?
    ");
    
    $stmt->execute([
        $completion_percentage,
        $status,
        $completed_at,
        $id
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Completion updated successfully',
        'status' => $status
    ]);
}

