<?php
session_start();
include '../config.php';

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    if ($_GET['action'] === 'get_events') {
        // Get events for a specific date or month
        if (isset($_GET['date'])) {
            $date = $conn->real_escape_string($_GET['date']);
            $sql = "SELECT * FROM events WHERE event_date = '$date' ORDER BY created_at DESC";
        } elseif (isset($_GET['month']) && isset($_GET['year'])) {
            $month = $conn->real_escape_string($_GET['month']);
            $year = $conn->real_escape_string($_GET['year']);
            $sql = "SELECT * FROM events WHERE MONTH(event_date) = '$month' AND YEAR(event_date) = '$year' ORDER BY event_date ASC";
        } else {
            echo json_encode([]);
            exit;
        }

        $result = $conn->query($sql);
        $events = [];
        
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                $events[] = $row;
            }
        }
        
        echo json_encode($events);
        exit;
    }
    
    // New action to get holidays
    if ($_GET['action'] === 'get_holidays') {
        $year = isset($_GET['year']) ? intval($_GET['year']) : date('Y');
        $country = isset($_GET['country']) ? $_GET['country'] : 'PH'; // Default to Philippines
        
        $holidays = getHolidays($year, $country);
        echo json_encode($holidays);
        exit;
    }
}

// Function to fetch holidays from API
function getHolidays($year, $country = 'PH') {
    $cache_file = "../cache/holidays_{$country}_{$year}.json";
    
    // Check if we have cached data (cache for 7 days)
    if (file_exists($cache_file) && (time() - filemtime($cache_file)) < 604800) {
        return json_decode(file_get_contents($cache_file), true);
    }
    
    $holidays = [];
    
    // Try different holiday APIs
    $holidays = tryNagerDateAPI($year, $country);
    
    if (empty($holidays)) {
        $holidays = tryCalendarificAPI($year, $country);
    }
    
    // Ensure cache directory exists
    if (!file_exists('../cache')) {
        mkdir('../cache', 0755, true);
    }
    
    // Cache the results
    file_put_contents($cache_file, json_encode($holidays));
    
    return $holidays;
}

// Try Nager.Date API (free, no API key required)
function tryNagerDateAPI($year, $country) {
    $url = "https://date.nager.at/api/v3/PublicHolidays/{$year}/{$country}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $holidays = [];
        
        foreach ($data as $holiday) {
            $holidays[] = [
                'date' => $holiday['date'],
                'title' => $holiday['name'],
                'type' => 'holiday',
                'description' => isset($holiday['localName']) ? $holiday['localName'] : ''
            ];
        }
        
        return $holidays;
    }
    
    return [];
}

// Try Calendarific API (requires API key - you'll need to sign up for a free key)
function tryCalendarificAPI($year, $country) {
    // You need to get a free API key from https://calendarific.com/
    $api_key = '?api_key=baa9dc110aa712sd3a9fa2a3dwb6c01d4c875950dc32vs'; // Replace with your actual API key
    
    if ($api_key === '?api_key=baa9dc110aa712sd3a9fa2a3dwb6c01d4c875950dc32vs') {
        return []; // Return empty if no API key configured
    }
    
    $url = "https://calendarific.com/api/v2/holidays?api_key={$api_key}&country={$country}&year={$year}";
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($http_code === 200) {
        $data = json_decode($response, true);
        $holidays = [];
        
        if (isset($data['response']['holidays'])) {
            foreach ($data['response']['holidays'] as $holiday) {
                $holidays[] = [
                    'date' => $holiday['date']['iso'],
                    'title' => $holiday['name'],
                    'type' => 'holiday',
                    'description' => isset($holiday['description']) ? $holiday['description'] : ''
                ];
            }
        }
        
        return $holidays;
    }
    
    return [];
}

// Rest of your existing POST handlers remain the same...
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_event') {
    header('Content-Type: application/json');
    
    $title = $_POST['title'] ?? '';
    $date = $_POST['date'] ?? '';
    $description = $_POST['description'] ?? '';
    $category = $_POST['category'] ?? 'task';
    
    if (!empty($title) && !empty($date)) {
        // Prepare and bind
        $stmt = $conn->prepare("INSERT INTO events (title, event_date, description, category) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $title, $date, $description, $category);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event saved successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error saving event: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Title and date are required']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_event') {
    header('Content-Type: application/json');
    
    if (isset($_POST['id'])) {
        $id = intval($_POST['id']);
        
        // Prepare and execute delete statement
        $stmt = $conn->prepare("DELETE FROM events WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Event deleted successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error deleting event: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Event ID is required']);
    }
    exit;
}

// Handle announcement form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_announcement'])) {
    $title = mysqli_real_escape_string($conn, $_POST['title']);
    $content = mysqli_real_escape_string($conn, $_POST['content']);
    $target_audience = mysqli_real_escape_string($conn, $_POST['target_audience']);
    
    // Insert announcement into database
    $query = "INSERT INTO announcements (title, content, date, target_audience, author_id) 
              VALUES ('$title', '$content', NOW(), '$target_audience', '{$_SESSION['user_id']}')";
    
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Announcement sent successfully!";
    } else {
        $_SESSION['error'] = "Error: " . mysqli_error($conn);
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Handle announcement deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_announcement'])) {
    $announcement_id = mysqli_real_escape_string($conn, $_POST['announcement_id']);
    $delete_query = "DELETE FROM announcements WHERE id = '$announcement_id'";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "Announcement deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting announcement: " . mysqli_error($conn);
    }
    header("Location: ".$_SERVER['PHP_SELF']);
    exit;
}

// Handle search and filter for announcements
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$audience_filter = isset($_GET['audience_filter']) ? mysqli_real_escape_string($conn, $_GET['audience_filter']) : '';

// Build query with filters
$announcements_query = "SELECT a.*, u.Email as author_email 
                        FROM announcements a 
                        LEFT JOIN user u ON a.author_id = u.UserID 
                        WHERE 1=1";

if (!empty($search)) {
    $announcements_query .= " AND (a.title LIKE '%$search%' OR a.content LIKE '%$search%')";
}

if (!empty($audience_filter)) {
    $announcements_query .= " AND a.target_audience = '$audience_filter'";
}

$announcements_query .= " ORDER BY a.date DESC LIMIT 10"; // Show only recent 10 announcements

$announcements_result = mysqli_query($conn, $announcements_query);
$total_announcements = mysqli_num_rows($announcements_result);

// Get counts for stats
$today = date('Y-m-d');
$today_query = "SELECT COUNT(*) as count FROM announcements WHERE DATE(date) = '$today'";
$today_result = mysqli_query($conn, $today_query);
$today_count = mysqli_fetch_assoc($today_result)['count'];

// Get total announcements count
$total_query = "SELECT COUNT(*) as count FROM announcements";
$total_result = mysqli_query($conn, $total_query);
$total_count = mysqli_fetch_assoc($total_result)['count'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BANAHIS | Calendar & Announcements</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --accent-color: #36b9cc;
            --text-color: #5a5c69;
            --holiday-color: #e74a3b;
        }
        
        body {
            background-color: #d0d3dbff;
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 20px;
        }
        
        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 15px;
            margin-bottom: 20px;
            height: 100%;
        }
        
        .calendar-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e3e6f0;
        }
        
        .calendar-nav button {
            background: var(--primary-color);
            color: white;
            border: none;
            border-radius: 5px;
            padding: 4px 10px;
            margin: 0 3px;
            transition: all 0.3s;
            font-size: 0.85rem;
        }
        
        .calendar-nav button:hover {
            background: #2e59d9;
        }
        
        .calendar-weekdays {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            text-align: center;
            font-weight: bold;
            margin-bottom: 8px;
            color: var(--primary-color);
            font-size: 0.9rem;
        }
        
        .calendar-days {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 4px;
        }
        
        .calendar-day {
            height: 70px;
            border: 1px solid #e3e6f0;
            padding: 5px;
            border-radius: 5px;
            background: white;
            overflow: hidden;
            transition: all 0.2s;
            font-size: 0.85rem;
            cursor: pointer;
            position: relative;
        }
        
        .calendar-day:hover {
            background: var(--secondary-color);
            transform: translateY(-2px);
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        
        .day-number {
            font-weight: bold;
            margin-bottom: 3px;
            text-align: right;
            font-size: 0.9rem;
        }
        
        .other-month {
            color: #b7b9cc;
            background: #f8f9fc;
        }
        
        .today {
            background: #e3f2fd;
            border: 2px solid var(--primary-color);
        }
        
        .holiday {
            background: #ffeaea;
            border-left: 3px solid var(--holiday-color);
        }
        
        .event-indicator {
            border-radius: 2px;
            padding: 1px 3px;
            font-size: 0.7rem;
            margin-bottom: 2px;
            cursor: pointer;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .event-indicator.holiday {
            background: var(--holiday-color);
            color: white;
        }
        
        .event-indicator:not(.holiday) {
            background: var(--primary-color);
            color: white;
        }
        
        .event-indicator:hover {
            opacity: 0.9;
        }
        
        .announcement-card {
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .announcement-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1) !important;
        }
        
        .bg-gradient-primary {
            background: linear-gradient(135deg, #1e3c72 0%, #2a5298 100%);
        }
        
        .modal-content {
            border-radius: 8px;
            border: none;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: var(--primary-color);
            color: white;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            padding: 10px 15px;
        }
        
        .btn-primary {
            background: var(--primary-color);
            border: none;
            padding: 5px 12px;
            font-size: 0.9rem;
        }
        
        .btn-primary:hover {
            background: #2e59d9;
        }
        
        .event-list {
            max-height: 250px;
            overflow-y: auto;
        }
        
        .calendar-title {
            font-size: 1.3rem;
            margin: 0;
        }
        
        .alert-notification {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1050;
            min-width: 300px;
            animation: fadeIn 0.5s, fadeOut 0.5s 2.5s;
        }
        
        .country-selector {
            max-width: 200px;
            margin-left: 10px;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        @keyframes fadeOut {
            from { opacity: 1; }
            to { opacity: 0; }
        }
        
        @media (max-width: 768px) {
            .calendar-day {
                height: 60px;
                font-size: 0.8rem;
                padding: 3px;
            }
            
            .event-indicator {
                font-size: 0.65rem;
                padding: 1px 2px;
            }
            
            .calendar-title {
                font-size: 1.1rem;
            }
            
            .calendar-container {
                padding: 10px;
            }
            
            .alert-notification {
                left: 20px;
                right: 20px;
                min-width: auto;
            }
            
            .country-selector {
                max-width: 150px;
                margin-left: 5px;
            }
        }
        
        @media (max-width: 576px) {
            .calendar-day {
                height: 50px;
            }
            
            .day-number {
                font-size: 0.8rem;
            }
            
            .calendar-header {
                flex-direction: column;
                gap: 10px;
                align-items: stretch;
            }
            
            .calendar-nav {
                display: flex;
                justify-content: center;
            }
            
            .country-selector {
                max-width: 100%;
                margin-left: 0;
                margin-top: 10px;
            }
        }
    </style>
</head>
<body class="bg-light">
    <?php include '../navs/adminNav.php'; ?>

    <div class="container-fluid py-4">

        <!-- Welcome Header with School Year Badge -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card border-0 shadow position-relative">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col">
                            <h4 class="card-title mb-1">
                                <i class="bi bi-calendar me-2"></i>Calendar & Announcements
                            </h4>
                            <p class="card-text mb-0">Event and announcement management for the school community</p>
                        </div>
                        <div class="col-auto">
                            <span class="badge bg-light text-secondary fs-6"><?php echo date('l, F j, Y'); ?></span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
        <!-- Alerts -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $_SESSION['success']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i><?php echo $_SESSION['error']; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="row">
            <!-- Calendar Section (Left Side) -->
            <div class="col-lg-6 mb-4">
                <div class="calendar-container">
                    <div class="calendar-header">
                        <h2 class="calendar-title"><i class="far fa-calendar-alt me-2"></i> School Calendar</h2>
                        <div class="d-flex justify-content-between align-items-center flex-wrap">
                            <div class="calendar-nav">
                                <button id="prev-month"><i class="fas fa-chevron-left"></i></button>
                                <button id="current-month">Today</button>
                                <button id="next-month"><i class="fas fa-chevron-right"></i></button>
                            </div>
                            <div class="d-flex align-items-center">
                                <select class="form-select form-select-sm country-selector" id="countrySelector">
                                    <option value="PH">Philippines</option>
                                    <option value="US">United States</option>
                                    <option value="GB">United Kingdom</option>
                                    <option value="CA">Canada</option>
                                    <option value="AU">Australia</option>
                                    <option value="JP">Japan</option>
                                    <option value="SG">Singapore</option>
                                </select>
                                <button class="btn btn-primary ms-2 rounded-circle" data-bs-toggle="modal" data-bs-target="#addEventModal">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="calendar-weekdays">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>
                    
                    <div class="calendar-days" id="calendar-days">
                        <!-- Calendar days will be generated by JavaScript -->
                    </div>
                </div>
            </div>

            <!-- Announcements Section (Right Side) -->
            <div class="col-lg-6">
                <div class="card shadow">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-bullhorn me-2"></i>Recent Announcements
                        </h5>
                        <div>
                            <span class="badge bg-primary me-2"><?php echo $total_announcements; ?> recent</span>
                            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addAnnouncementModal">
                                <i class="fas fa-plus me-1"></i> New Announcement
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- Search and Filter -->
                        <form method="GET" class="row g-3 mb-4">
                            <div class="col-md-5">
                                <label for="search" class="form-label">Search</label>
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search announcements..." value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="audience_filter" class="form-label">Audience</label>
                                <select class="form-select" id="audience_filter" name="audience_filter">
                                    <option value="">All</option>
                                    <option value="teachers" <?php echo $audience_filter === 'teacher' ? 'selected' : ''; ?>>Teachers</option>
                                    <option value="student" <?php echo $audience_filter === 'student' ? 'selected' : ''; ?>>Students</option>
                                </select>
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <div class="">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search me-1"></i>
                                    </button>
                                    <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-times me-1"></i>
                                    </a>
                                </div>
                            </div>
                        </form>

                        <!-- Announcements List -->
                        <?php if (mysqli_num_rows($announcements_result) > 0): ?>
                            <div class="row">
                                <?php while ($announcement = mysqli_fetch_assoc($announcements_result)): ?>
                                    <div class="col-12 mb-3">
                                        <div class="card announcement-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <h6 class="card-title text-primary mb-0">
                                                        <?php echo htmlspecialchars($announcement['title']); ?>
                                                    </h6>
                                                    <div class="d-flex gap-2">
                                                        <span class="badge bg-info">
                                                            <?php echo ucfirst(str_replace('_', ' ', $announcement['target_audience'])); ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                
                                                <p class="card-text text-muted small mb-2">
                                                    <i class="fas fa-user me-1"></i>By: <?php echo htmlspecialchars($announcement['author_email']); ?>
                                                    <i class="fas fa-clock ms-3 me-1"></i><?php echo date('M j, Y g:i A', strtotime($announcement['date'])); ?>
                                                </p>
                                                
                                                <p class="card-text">
                                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                                </p>
                                                
                                                <div class="d-flex justify-content-end">
                                                    <form method="POST" action="" 
                                                          onsubmit="return confirm('Are you sure you want to delete this announcement?');">
                                                        <input type="hidden" name="announcement_id" value="<?php echo $announcement['id']; ?>">
                                                        <button type="submit" name="delete_announcement" 
                                                                class="btn btn-danger btn-sm">
                                                            <i class="fas fa-trash me-1"></i>Delete
                                                        </button>
                                                    </form>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-5">
                                <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No Announcements Found</h5>
                                <p class="text-muted">There are no announcements matching your criteria.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Add Event Modal -->
    <div class="modal fade" id="addEventModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Event</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="eventForm">
                        <div class="mb-3">
                            <label for="eventTitle" class="form-label">Event Title</label>
                            <input type="text" class="form-control" id="eventTitle" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventDate" class="form-label">Date</label>
                            <input type="date" class="form-control" id="eventDate" required>
                        </div>
                        <div class="mb-3">
                            <label for="eventDescription" class="form-label">Description</label>
                            <textarea class="form-control" id="eventDescription" rows="2"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="eventCategory" class="form-label">Category</label>
                            <select class="form-select" id="eventCategory">
                                <option value="meeting">Meeting</option>
                                <option value="task">Task</option>
                                <option value="reminder">Reminder</option>
                                <option value="event">Event</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" id="saveEvent">Save Event</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- View Events Modal -->
    <div class="modal fade" id="viewEventsModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-sm">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventsModalTitle">Events</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="eventsList" class="event-list">
                        <!-- Events will be listed here -->
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="addEventForDate">Add Event</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Announcement Modal -->
    <div class="modal fade" id="addAnnouncementModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Announcement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="POST" action="" id="announcementForm">
                        <div class="mb-3">
                            <label for="announcementTitle" class="form-label">Announcement Title</label>
                            <input type="text" class="form-control" id="announcementTitle" name="title" required 
                                   placeholder="Enter announcement title">
                        </div>
                        
                        <div class="mb-3">
                            <label for="announcementContent" class="form-label">Announcement Content</label>
                            <textarea class="form-control" id="announcementContent" name="content" rows="5" required 
                                      placeholder="Enter your announcement details..."></textarea>
                            <div class="form-text">
                                <span id="charCount">0</span> characters
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="modalTargetAudience" class="form-label">Target Audience</label>
                            <select class="form-select" id="modalTargetAudience" name="target_audience" required>
                                <option value="all">All Users</option>
                                <option value="teachers">Teachers Only</option>
                                <option value="students">Students Only</option>
                                <option value="head_teachers">Head Teachers</option>
                            </select>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_announcement" form="announcementForm" class="btn btn-primary">
                        <i class="fas fa-paper-plane me-2"></i>Send Announcement
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    
    <script>
        // Calendar functionality
        $(document).ready(function() {
            let currentDate = new Date();
            let currentMonth = currentDate.getMonth();
            let currentYear = currentDate.getFullYear();
            let selectedDate = null;
            let holidays = [];
            let selectedCountry = 'PH';
            
            // Generate calendar
            async function generateCalendar(month, year) {
                const calendarDays = $('#calendar-days');
                calendarDays.empty();
                
                // Set modal title
                const monthNames = ["January", "February", "March", "April", "May", "June",
                    "July", "August", "September", "October", "November", "December"
                ];
                $('.calendar-title').html(`<i class="far fa-calendar-alt me-2"></i> ${monthNames[month]} ${year}`);
                
                // Get first day of month (0 = Sunday, 1 = Monday, etc.)
                const firstDay = new Date(year, month, 1).getDay();
                
                // Get days in month
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                
                // Get days from previous month to show
                const daysFromPrevMonth = firstDay;
                const prevMonthLastDay = new Date(year, month, 0).getDate();
                
                // Add days from previous month
                for (let i = prevMonthLastDay - daysFromPrevMonth + 1; i <= prevMonthLastDay; i++) {
                    calendarDays.append(createDayElement(i, month - 1, year, true));
                }
                
                // Add days from current month
                for (let i = 1; i <= daysInMonth; i++) {
                    const isToday = i === currentDate.getDate() && month === currentDate.getMonth() && year === currentDate.getFullYear();
                    calendarDays.append(createDayElement(i, month, year, false, isToday));
                }
                
                // Calculate how many days from next month to show
                const totalCells = 35; // 5 weeks * 7 days
                const daysFromNextMonth = totalCells - (daysFromPrevMonth + daysInMonth);
                
                // Add days from next month
                for (let i = 1; i <= daysFromNextMonth; i++) {
                    calendarDays.append(createDayElement(i, month + 1, year, true));
                }
                
                // Load events and holidays for the month
                await loadEvents(month, year);
                await loadHolidays(year);
                
                // Display both events and holidays
                displayEventsAndHolidays();
            }
            
            // Create a day element
            function createDayElement(day, month, year, isOtherMonth, isToday = false) {
                const dayElement = $('<div>').addClass('calendar-day');
                
                if (isOtherMonth) {
                    dayElement.addClass('other-month');
                }
                
                if (isToday) {
                    dayElement.addClass('today');
                }
                
                const dayNumber = $('<div>').addClass('day-number').text(day);
                dayElement.append(dayNumber);
                
                // Add data attribute for date
                const fullDate = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                dayElement.attr('data-date', fullDate);
                
                // Add click event to view events
                dayElement.on('click', function() {
                    selectedDate = fullDate;
                    viewEvents(fullDate);
                });
                
                return dayElement;
            }
            
            // Load events for the month from the server
            function loadEvents(month, year) {
                return new Promise((resolve) => {
                    $.ajax({
                        url: '?action=get_events&month=' + (month + 1) + '&year=' + year,
                        type: 'GET',
                        success: function(response) {
                            try {
                                window.events = typeof response === 'string' ? JSON.parse(response) : response;
                                resolve();
                            } catch (e) {
                                console.error('Error parsing events:', e);
                                window.events = [];
                                resolve();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading events:', error);
                            window.events = [];
                            resolve();
                        }
                    });
                });
            }
            
            // Load holidays for the year from the API
            function loadHolidays(year) {
                return new Promise((resolve) => {
                    $.ajax({
                        url: `?action=get_holidays&year=${year}&country=${selectedCountry}`,
                        type: 'GET',
                        success: function(response) {
                            try {
                                holidays = typeof response === 'string' ? JSON.parse(response) : response;
                                resolve();
                            } catch (e) {
                                console.error('Error parsing holidays:', e);
                                holidays = [];
                                resolve();
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error loading holidays:', error);
                            holidays = [];
                            resolve();
                        }
                    });
                });
            }
            
            // Display events and holidays on the calendar
            function displayEventsAndHolidays() {
                $('.calendar-day').each(function() {
                    const date = $(this).data('date');
                    
                    // Clear any existing events
                    $(this).find('.event-indicator').remove();
                    
                    // Check if this date has holidays
                    const dayHolidays = holidays.filter(holiday => holiday.date === date);
                    
                    // Check if this date has events
                    const dayEvents = window.events.filter(event => event.event_date === date);
                    
                    // Mark holiday dates
                    if (dayHolidays.length > 0) {
                        $(this).addClass('holiday');
                    } else {
                        $(this).removeClass('holiday');
                    }
                    
                    // Combine events and holidays for display (limit to 3 items)
                    const allItems = [...dayHolidays.map(h => ({...h, type: 'holiday'})), ...dayEvents];
                    
                    if (allItems.length > 0) {
                        // Display up to 3 items
                        for (let i = 0; i < Math.min(allItems.length, 3); i++) {
                            const item = allItems[i];
                            const eventElement = $('<div>').addClass('event-indicator')
                                .text(item.title)
                                .css('background-color', getCategoryColor(item.type === 'holiday' ? 'holiday' : item.category));
                            
                            if (item.type === 'holiday') {
                                eventElement.addClass('holiday');
                            }
                            
                            $(this).append(eventElement);
                        }
                        
                        // If there are more than 3 items, show a counter
                        if (allItems.length > 3) {
                            const moreEvents = $('<div>').addClass('event-indicator')
                                .text(`+${allItems.length - 3} more`)
                                .css('background-color', '#6c757d');
                            $(this).append(moreEvents);
                        }
                    }
                });
            }
            
            // Get color based on event category
            function getCategoryColor(category) {
                const colors = {
                    'meeting': '#4e73df',
                    'task': '#1cc88a',
                    'reminder': '#f6c23e',
                    'event': '#e74a3b',
                    'holiday': '#e74a3b' // Red for holidays
                };
                
                return colors[category] || '#4e73df';
            }
            
            // View events for a specific date
            function viewEvents(date) {
                // Filter events and holidays for the selected date
                const dayEvents = window.events.filter(event => event.event_date === date);
                const dayHolidays = holidays.filter(holiday => holiday.date === date);
                
                const modalTitle = $('#eventsModalTitle');
                const eventsList = $('#eventsList');
                
                // Format date for display
                const dateObj = new Date(date);
                const options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
                const formattedDate = dateObj.toLocaleDateString('en-US', options);
                
                modalTitle.text(`Events for ${formattedDate}`);
                eventsList.empty();
                
                if (dayEvents.length === 0 && dayHolidays.length === 0) {
                    eventsList.append('<p>No events or holidays scheduled for this day.</p>');
                } else {
                    // Display holidays first
                    dayHolidays.forEach(holiday => {
                        const holidayElement = $('<div>').addClass('card mb-2 border-danger');
                        const holidayHeader = $('<div>').addClass('card-header py-2 d-flex justify-content-between align-items-center bg-danger text-white');
                        
                        const holidayTitle = $('<h6>').addClass('m-0').html(`<i class="fas fa-flag me-1"></i> ${holiday.title}`);
                        const holidayBadge = $('<span>').addClass('badge bg-light text-dark').text('Holiday');
                        
                        holidayHeader.append(holidayTitle, holidayBadge);
                        
                        const holidayBody = $('<div>').addClass('card-body py-2');
                        if (holiday.description) {
                            holidayBody.append($('<p>').addClass('card-text mb-1 small').text(holiday.description));
                        }
                        
                        holidayElement.append(holidayHeader, holidayBody);
                        eventsList.append(holidayElement);
                    });
                    
                    // Display events
                    dayEvents.forEach(event => {
                        const eventElement = $('<div>').addClass('card mb-2');
                        const eventHeader = $('<div>').addClass('card-header py-2 d-flex justify-content-between align-items-center')
                            .css('background-color', getCategoryColor(event.category));
                        
                        const eventTitle = $('<h6>').addClass('m-0 text-white').text(event.title);
                        const eventCategory = $('<span>').addClass('badge bg-light text-dark').text(event.category);
                        
                        eventHeader.append(eventTitle, eventCategory);
                        
                        const eventBody = $('<div>').addClass('card-body py-2');
                        if (event.description) {
                            eventBody.append($('<p>').addClass('card-text mb-1 small').text(event.description));
                        }
                        
                        // Add delete button for events (not for holidays)
                        const deleteBtn = $('<button>').addClass('btn btn-sm btn-outline-danger mt-2')
                            .html('<i class="fas fa-trash me-1"></i> Delete')
                            .on('click', function() {
                                deleteEvent(event.id);
                            });
                        
                        eventBody.append(deleteBtn);
                        
                        eventElement.append(eventHeader, eventBody);
                        eventsList.append(eventElement);
                    });
                }
                
                $('#viewEventsModal').modal('show');
            }
            
            // Delete an event
            function deleteEvent(eventId) {
                if (confirm('Are you sure you want to delete this event?')) {
                    $.ajax({
                        url: '?action=delete_event',
                        type: 'POST',
                        data: {
                            action: 'delete_event',
                            id: eventId
                        },
                        success: function(response) {
                            try {
                                const result = typeof response === 'string' ? JSON.parse(response) : response;
                                if (result.success) {
                                    // Reload calendar
                                    generateCalendar(currentMonth, currentYear);
                                    
                                    // Close modal
                                    $('#viewEventsModal').modal('hide');
                                    
                                    // Show success message
                                    showNotification('Event deleted successfully!');
                                } else {
                                    showNotification('Error deleting event: ' + result.message, 'danger');
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                showNotification('Error deleting event', 'danger');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error deleting event:', error);
                            showNotification('Error deleting event. Please try again.', 'danger');
                        }
                    });
                }
            }
            
            // Show notification message
            function showNotification(message, type = 'success') {
                // Remove any existing notifications
                $('.alert-notification').remove();
                
                const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
                const icon = type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle';
                
                const notification = $(
                    `<div class="alert ${alertClass} alert-dismissible fade show alert-notification" role="alert">
                        <i class="fas ${icon} me-2"></i>
                        ${message}
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>`
                );
                
                $('body').append(notification);
                
                // Auto remove after 3 seconds
                setTimeout(() => {
                    notification.alert('close');
                }, 3000);
            }
            
            // Save event to database
            $('#saveEvent').on('click', function() {
                const title = $('#eventTitle').val();
                const date = $('#eventDate').val();
                const description = $('#eventDescription').val();
                const category = $('#eventCategory').val();
                
                if (title && date) {
                    $.ajax({
                        url: '?action=save_event',
                        type: 'POST',
                        data: {
                            action: 'save_event',
                            title: title,
                            date: date,
                            description: description,
                            category: category
                        },
                        success: function(response) {
                            try {
                                const result = typeof response === 'string' ? JSON.parse(response) : response;
                                if (result.success) {
                                    // Close modal and reset form
                                    $('#addEventModal').modal('hide');
                                    $('#eventForm')[0].reset();
                                    
                                    // Reload calendar
                                    generateCalendar(currentMonth, currentYear);
                                    
                                    // Show success message
                                    showNotification('Event saved successfully!');
                                } else {
                                    showNotification('Error saving event: ' + result.message, 'danger');
                                }
                            } catch (e) {
                                console.error('Error parsing response:', e);
                                showNotification('Error saving event', 'danger');
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('Error saving event:', error);
                            showNotification('Error saving event. Please try again.', 'danger');
                        }
                    });
                } else {
                    showNotification('Please fill in all required fields.', 'danger');
                }
            });
            
            // Add event from view events modal
            $('#addEventForDate').on('click', function() {
                $('#viewEventsModal').modal('hide');
                
                // Set the date in the add event form
                if (selectedDate) {
                    $('#eventDate').val(selectedDate);
                }
                
                // Show the add event modal
                $('#addEventModal').modal('show');
            });
            
            // Navigation handlers
            $('#prev-month').on('click', async function() {
                currentMonth--;
                if (currentMonth < 0) {
                    currentMonth = 11;
                    currentYear--;
                }
                await generateCalendar(currentMonth, currentYear);
            });
            
            $('#next-month').on('click', async function() {
                currentMonth++;
                if (currentMonth > 11) {
                    currentMonth = 0;
                    currentYear++;
                }
                await generateCalendar(currentMonth, currentYear);
            });
            
            $('#current-month').on('click', async function() {
                currentDate = new Date();
                currentMonth = currentDate.getMonth();
                currentYear = currentDate.getFullYear();
                await generateCalendar(currentMonth, currentYear);
            });
            
            // Country selector change handler
            $('#countrySelector').on('change', async function() {
                selectedCountry = $(this).val();
                await generateCalendar(currentMonth, currentYear);
            });
            
            // Set today's date as default in the form
            const today = new Date();
            const formattedDate = today.toISOString().substr(0, 10);
            $('#eventDate').val(formattedDate);
            
            // Initialize calendar
            generateCalendar(currentMonth, currentYear);
            
            // Character counter for announcement content
            const contentTextarea = document.getElementById('announcementContent');
            const charCount = document.getElementById('charCount');
            
            function updateCharCounter() {
                const length = contentTextarea.value.length;
                charCount.textContent = length;
                
                if (length > 500) {
                    charCount.className = 'text-danger';
                } else if (length > 300) {
                    charCount.className = 'text-warning';
                } else {
                    charCount.className = 'text-muted';
                }
            }
            
            if (contentTextarea) {
                contentTextarea.addEventListener('input', updateCharCounter);
                updateCharCounter(); // Initialize counter
            }
        });
    </script>
</body>
</html>