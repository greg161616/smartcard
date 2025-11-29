<?php
// File: admin_view_grades.php
session_start();
require '../config.php';

// Ensure admin is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'principal') {
    header('Location: ../login.php');
    exit;
}

// Get available school years from section_enrollment
$school_years_query = "SELECT DISTINCT SchoolYear FROM section_enrollment ORDER BY SchoolYear DESC";
$school_years_result = $conn->query($school_years_query);
$available_school_years = [];
while ($row = $school_years_result->fetch_assoc()) {
    $available_school_years[] = $row['SchoolYear'];
}

// Set default school year (current or most recent)
$selected_school_year = isset($_GET['school_year']) ? $_GET['school_year'] : 
    (!empty($available_school_years) ? $available_school_years[0] : date('Y') . '-' . (date('Y') + 1));

// Get distinct grade levels for filter dropdown
$grade_levels_query = "SELECT DISTINCT GradeLevel FROM section ORDER BY GradeLevel";
$grade_levels_result = $conn->query($grade_levels_query);
$grade_levels = [];
while ($row = $grade_levels_result->fetch_assoc()) {
    $grade_levels[] = $row['GradeLevel'];
}

// If this is an AJAX request, return JSON data only
if (isset($_GET['ajax']) && $_GET['ajax'] == 1) {
    $selected_grade_level = isset($_GET['grade_level']) ? $_GET['grade_level'] : '';
    $search_term = isset($_GET['search']) ? trim($_GET['search']) : '';
    
    // Build the base query to get ALL sections with student count for selected school year
    $query = "
        SELECT 
            s.SectionID,
            s.SectionName,
            s.GradeLevel,
            s.AdviserID,
            COALESCE(se_counts.StudentCount, 0) as StudentCount,
            t.fName as TeacherFirstName,
            t.lName as TeacherLastName,
            t.mName as TeacherMiddleName,
            t.surfix as TeacherSuffix
        FROM section s
        LEFT JOIN teacher t ON s.AdviserID = t.TeacherID
        LEFT JOIN (
            SELECT SectionID, COUNT(StudentID) as StudentCount 
            FROM section_enrollment 
            WHERE SchoolYear = ? AND status = 'active'
            GROUP BY SectionID
        ) se_counts ON s.SectionID = se_counts.SectionID
    ";

    // Add WHERE conditions based on filters
    $conditions = [];
    $params = ["s"]; // Start with school year parameter type
    $param_values = [$selected_school_year];

    // Grade level filter
    if (!empty($selected_grade_level)) {
        $conditions[] = "s.GradeLevel = ?";
        $params[0] .= "s";
        $param_values[] = $selected_grade_level;
    }

    // Search filter
    if (!empty($search_term)) {
        $conditions[] = "(s.SectionName LIKE ? OR t.fName LIKE ? OR t.lName LIKE ? OR CONCAT(t.fName, ' ', t.lName) LIKE ?)";
        $params[0] .= "ssss";
        $search_pattern = "%" . $search_term . "%";
        $param_values[] = $search_pattern;
        $param_values[] = $search_pattern;
        $param_values[] = $search_pattern;
        $param_values[] = $search_pattern;
    }

    // Add WHERE clause if there are conditions
    if (!empty($conditions)) {
        $query .= " WHERE " . implode(" AND ", $conditions);
    }

    // Add ordering
    $query .= " ORDER BY s.GradeLevel, s.SectionName";

    // Prepare and execute the query
    $stmt = $conn->prepare($query);

    // Dynamic binding based on number of parameters
    if (!empty($params[0])) {
        $stmt->bind_param($params[0], ...$param_values);
    }

    $stmt->execute();
    $result = $stmt->get_result();

    $sections = [];
    if ($result) {
        while ($section = $result->fetch_assoc()) {
            // Build teacher name
            $teacherName = "Not assigned";
            if ($section['TeacherFirstName']) {
                $teacherName = $section['TeacherLastName'] . ', ' . $section['TeacherFirstName'];
                if ($section['TeacherMiddleName']) {
                    $teacherName .= ' ' . substr($section['TeacherMiddleName'], 0, 1) . '.';
                }
                if ($section['TeacherSuffix']) {
                    $teacherName .= ' ' . $section['TeacherSuffix'];
                }
            }
            
            $sections[] = [
                'SectionID' => $section['SectionID'],
                'SectionName' => $section['SectionName'],
                'GradeLevel' => $section['GradeLevel'],
                'StudentCount' => $section['StudentCount'],
                'TeacherName' => $teacherName
            ];
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'sections' => $sections,
        'count' => count($sections),
        'filters' => [
            'grade_level' => $selected_grade_level,
            'search' => $search_term,
            'school_year' => $selected_school_year
        ]
    ]);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Admin - View Grades by Section</title>
  <link rel="icon" type="image/png" href="../img/logo.png" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    .section-card {
      transition: transform 0.3s, box-shadow 0.3s;
      margin-bottom: 20px;
      height: 100%;
      border-radius: 12px;
      overflow: hidden;
    }
    .section-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 20px rgba(0,0,0,0.15);
    }
    .card-header {
      color: white;
      font-weight: bold;
      padding: 15px 20px;
    }
    .grade-badge {
      font-size: 1.2rem;
      padding: 8px 18px;
      border-radius: 50px;
      background: rgba(255, 255, 255, 0.2);
    }
    .student-count {
      font-size: 0.9rem;
      color: #6c757d;
      display: flex;
      align-items: center;
    }
    .teacher-info {
      background-color: #f8f9fa;
      padding: 12px;
      border-radius: 8px;
      margin-bottom: 15px;
    }
    .stats-icon {
      font-size: 1.5rem;
      color: #4e73df;
      margin-right: 10px;
    }
    .school-year-badge {
      background: rgba(255, 255, 255, 0.2);
      color: white;
      padding: 8px 16px;
      border-radius: 20px;
      font-weight: 600;
    }
    .filter-section {
      background: #f8f9fa;
      padding: 20px;
      border-radius: 12px;
      margin-bottom: 25px;
    }
    .results-count {
      font-size: 0.9rem;
      color: #6c757d;
      font-style: italic;
    }
    .loading-spinner {
      display: none;
      text-align: center;
      padding: 20px;
    }
    #sectionsContainer {
      min-height: 200px;
    }
    .search-input-container {
      position: relative;
    }
    .search-spinner {
      position: absolute;
      right: 10px;
      top: 50%;
      transform: translateY(-50%);
      display: none;
    }
    .error-message {
      color: #dc3545;
      font-size: 0.9rem;
      margin-top: 5px;
    }
  </style>
</head>
<body>
  <?php include '../navs/adminNav.php'; ?>

  <div class="container">
    <!-- School Year Selector -->
    <div class="school-year-selector">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h4 class="mb-1"><i class="fas fa-graduation-cap me-2"></i>View Grades by Section</h4>
          <p class="mb-0">Select school year to view sections and grades</p>
        </div>
        <div class="col-md-4">
          <form method="GET" action="" id="schoolYearForm">
            <select name="school_year" id="schoolYear" class="form-select">
              <?php if (empty($available_school_years)): ?>
                <option value="<?php echo date('Y') . '-' . (date('Y') + 1); ?>">
                  <?php echo date('Y') . '-' . (date('Y') + 1); ?>
                </option>
              <?php else: ?>
                <?php foreach ($available_school_years as $school_year): ?>
                  <option value="<?php echo htmlspecialchars($school_year); ?>" 
                    <?php echo ($school_year == $selected_school_year) ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($school_year); ?>
                  </option>
                <?php endforeach; ?>
              <?php endif; ?>
            </select>
          </form>
        </div>
      </div>
    </div>

    <!-- Current School Year Badge -->
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h5 class="mb-0">Showing data for school year:</h5>
      <span class="school-year-badge bg-primary">
        <i class="fas fa-calendar me-1"></i>
        <span id="currentSchoolYear"><?php echo htmlspecialchars($selected_school_year); ?></span>
      </span>
    </div>

    <!-- Filter Section -->
    <div class="filter-section">
      <form id="filterForm">
        <div class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Grade Level</label>
            <select name="grade_level" id="gradeLevel" class="form-select">
              <option value="">All Grade Levels</option>
              <?php foreach ($grade_levels as $grade): ?>
                <option value="<?= htmlspecialchars($grade) ?>">
                  Grade <?= htmlspecialchars($grade) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-5">
            <label class="form-label">Search Sections or Advisers</label>
            <div class="search-input-container">
              <input type="text" name="search" id="searchInput" class="form-control" 
                     placeholder="Search by section name or adviser name...">
              <div class="search-spinner spinner-border spinner-border-sm" role="status">
                <span class="visually-hidden">Loading...</span>
              </div>
            </div>
          </div>
          <div class="col-md-3">
            <button type="button" id="clearFilters" class="btn btn-outline-secondary w-100">
              <i class="fas fa-times me-1"></i> Clear Filters
            </button>
          </div>
        </div>
      </form>
    </div>

    <!-- Results Section -->
    <div id="resultsSection">
      <div class="results-count mb-3" id="resultsCount"></div>
      
      <div class="loading-spinner" id="loadingSpinner">
        <div class="spinner-border text-primary" role="status">
          <span class="visually-hidden">Loading...</span>
        </div>
        <p class="mt-2">Loading sections...</p>
      </div>

      <div id="sectionsContainer" class="row">
        <!-- Sections will be loaded here via AJAX -->
      </div>

      <div id="noResults" class="alert alert-info" style="display: none;">
        <i class="fas fa-info-circle me-2"></i> 
        <span id="noResultsMessage"></span>
      </div>

      <div id="errorMessage" class="alert alert-danger" style="display: none;">
        <i class="fas fa-exclamation-triangle me-2"></i> 
        <span id="errorMessageText"></span>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    $(document).ready(function() {
      let searchTimeout;
      const debounceDelay = 500; // milliseconds

      // Load initial sections
      loadSections();

      // School year change handler
      $('#schoolYear').on('change', function() {
        $('#currentSchoolYear').text($(this).val());
        loadSections();
      });

      // Grade level change handler
      $('#gradeLevel').on('change', loadSections);

      // Search input handler with debounce
      $('#searchInput').on('input', function() {
        $('.search-spinner').show();
        
        // Clear previous timeout
        clearTimeout(searchTimeout);
        
        // Set new timeout
        searchTimeout = setTimeout(() => {
          loadSections();
          $('.search-spinner').hide();
        }, debounceDelay);
      });

      // Clear filters handler
      $('#clearFilters').on('click', function() {
        $('#gradeLevel').val('');
        $('#searchInput').val('');
        loadSections();
      });

      // Function to load sections via AJAX
      function loadSections() {
        const schoolYear = $('#schoolYear').val();
        const gradeLevel = $('#gradeLevel').val();
        const searchTerm = $('#searchInput').val();

        // Show loading spinner
        $('#loadingSpinner').show();
        $('#sectionsContainer').hide();
        $('#noResults').hide();
        $('#errorMessage').hide();

        $.ajax({
          url: '<?php echo $_SERVER['PHP_SELF']; ?>',
          type: 'GET',
          data: {
            ajax: 1,
            school_year: schoolYear,
            grade_level: gradeLevel,
            search: searchTerm
          },
          dataType: 'json',
          success: function(response) {
            $('#loadingSpinner').hide();
            
            if (response.success) {
              updateResultsDisplay(response);
            } else {
              showError('Failed to load sections: ' + (response.error || 'Unknown error'));
            }
          },
          error: function(xhr, status, error) {
            $('#loadingSpinner').hide();
            showError('Error loading sections. Please try again. (' + error + ')');
          }
        });
      }

      // Function to update the results display
      function updateResultsDisplay(response) {
        const container = $('#sectionsContainer');
        const resultsCount = $('#resultsCount');
        const noResults = $('#noResults');
        const noResultsMessage = $('#noResultsMessage');
        
        // Update results count
        let countText = `Showing ${response.count} section(s)`;
        if (response.filters.grade_level) {
          countText += ` in Grade ${response.filters.grade_level}`;
        }
        if (response.filters.search) {
          countText += ` matching "${response.filters.search}"`;
        }
        resultsCount.text(countText);

        // Clear container
        container.empty();

        if (response.sections.length === 0) {
          let message = 'No sections found in the system.';
          if (response.filters.grade_level || response.filters.search) {
            message = 'No sections found matching your filters. Clear filters to see all sections.';
          }
          noResultsMessage.text(message);
          noResults.show();
          container.hide();
        } else {
          noResults.hide();
          
          // Add sections to container
          response.sections.forEach(section => {
            const sectionCard = createSectionCard(section, response.filters.school_year);
            container.append(sectionCard);
          });
          
          container.show();
        }
      }

      // Function to create section card HTML
      function createSectionCard(section, schoolYear) {
        return `
          <div class="col-md-6 col-lg-4 mb-4">
            <div class="card section-card">
              <div class="card-header d-flex justify-content-between align-items-center bg-info text-white">
                <span><h4 class="mb-0">Grade</h4></span>
                <span class="grade-badge">${section.GradeLevel}</span>
              </div>
              <div class="card-body">
                <h5 class="card-title">${section.SectionName} Section</h5>
                
                <div class="teacher-info">
                  <h6 class="mb-1"><i class="fas fa-user-tie me-2"></i>Adviser</h6>
                  <p class="mb-0">${section.TeacherName}</p>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-3">
                  <span class="student-count">
                    <i class="fas fa-users stats-icon"></i>
                    ${section.StudentCount} enrolled students
                  </span>
                </div>
                
                <div class="d-grid">
                  <a href="view_subjects_grades.php?section_id=${section.SectionID}&school_year=${encodeURIComponent(schoolYear)}" 
                     class="btn btn-success">
                    <i class="fas fa-book-open me-1"></i> View Subjects & Grades
                  </a>
                </div>
              </div>
            </div>
          </div>
        `;
      }

      // Function to show error message
      function showError(message) {
        $('#sectionsContainer').hide();
        $('#noResults').hide();
        $('#errorMessageText').text(message);
        $('#errorMessage').show();
      }
    });
  </script>
</body>
</html>