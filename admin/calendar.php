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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>BANAHIS | Calendar</title>
    <link rel="icon" type="image/png" href="../img/logo.png" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
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
            background-color: #f8f9fc;
            color: var(--text-color);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding-bottom: 20px;
        }
        
        .calendar-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
            padding: 15px;
            margin-top: 20px;
            max-width: 800px;
            margin-left: auto;
            margin-right: auto;
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
<body>
    <?php 
    if (file_exists('../navs/adminNav.php')) {
        include '../navs/adminNav.php'; 
    }
    ?>
    
    <div class="container mt-4">
        <div class="calendar-container">
            <div class="calendar-header">
                <h2 class="calendar-title"><i class="far fa-calendar-alt me-2"></i> Admin Calendar</h2>
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

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        });
    </script>
</body>
</html>