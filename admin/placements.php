<?php
session_start();
require_once '../config/database.php';

// Restrict to admin users
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../index.php");
    exit();
}

// Filters
$company_filter = isset($_GET['company']) ? (int)$_GET['company'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : '';
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : '';

$placements = [];
$companies = [];
$error = null;

try {
    // Companies for filter
    $stmt = $conn->query("SELECT company_id, company_name FROM companies ORDER BY company_name");
    $companies = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Build query for hired applications (placements)
    $query = "SELECT a.application_id, a.application_date, a.status,
                     u.user_id AS jobseeker_id, u.email,
                     up.first_name, up.last_name,
                     j.job_id, j.title AS job_title,
                     c.company_id, c.company_name,
                     br.education AS education_json
              FROM applications a
              JOIN jobs j ON a.job_id = j.job_id
              JOIN companies c ON j.company_id = c.company_id
              JOIN users u ON a.jobseeker_id = u.user_id
              LEFT JOIN user_profiles up ON u.user_id = up.user_id
              LEFT JOIN basic_resumes br ON br.user_id = u.user_id
              WHERE a.status = 'hired'";
    $params = [];

    if ($company_filter) {
        $query .= " AND c.company_id = ?";
        $params[] = $company_filter;
    }

    // Removed server-side search; client-side DataTables search is used

    if ($start_date !== '' && $end_date !== '') {
        if (strtotime($end_date) < strtotime($start_date)) {
            // Swap if invalid
            $tmp = $start_date; $start_date = $end_date; $end_date = $tmp;
        }
        $query .= " AND DATE(a.application_date) BETWEEN ? AND ?";
        $params[] = $start_date;
        $params[] = $end_date;
    } elseif ($start_date !== '') {
        $query .= " AND DATE(a.application_date) >= ?";
        $params[] = $start_date;
    } elseif ($end_date !== '') {
        $query .= " AND DATE(a.application_date) <= ?";
        $params[] = $end_date;
    }

    $query .= " ORDER BY a.application_date DESC";

    $stmt = $conn->prepare($query);
    $stmt->execute($params);
    $rawRows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Post-process education JSON to extract best-known college/major/degree/year
    foreach ($rawRows as $row) {
        $college = null;
        $degree = null;
        $grad_year = null;
        $major = null;

        if (!empty($row['education_json'])) {
            // education_json may be either an array of entries or JSON string; attempt to decode
            $edu = json_decode($row['education_json'], true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($edu) && count($edu) > 0) {
                // Heuristic: pick the most recent COLLEGE/UNIVERSITY entry only
                $chosen = null;
                foreach ($edu as $entry) {
                    if (!is_array($entry)) continue;
                    $schoolName = strtolower(trim(($entry['school'] ?? $entry['institution'] ?? '')));
                    $hasDegreeSignals = !empty($entry['degree']) || !empty($entry['field_of_study']) || !empty($entry['course']);
                    $isCollegeLike = $hasDegreeSignals
                        || (strpos($schoolName, 'university') !== false)
                        || (strpos($schoolName, 'college') !== false)
                        || (strpos($schoolName, 'institute') !== false)
                        || (strpos($schoolName, 'polytechnic') !== false)
                        || (strpos($schoolName, 'academy') !== false)
                        || (strpos($schoolName, 'institute of technology') !== false);
                    $isNonCollege = (strpos($schoolName, 'high school') !== false)
                        || (strpos($schoolName, 'secondary') !== false)
                        || (strpos($schoolName, 'elementary') !== false)
                        || (strpos($schoolName, 'primary') !== false)
                        || (strpos($schoolName, 'senior high') !== false)
                        || (strpos($schoolName, 'junior high') !== false);
                    if (!$isCollegeLike || $isNonCollege) {
                        continue; // skip non-college entries
                    }
                    $y = null;
                    if (isset($entry['year']) && $entry['year'] !== '') { $y = $entry['year']; }
                    if (isset($entry['gradYear']) && $entry['gradYear'] !== '') { $y = $entry['gradYear']; }
                    if (isset($entry['end_date']) && $entry['end_date'] !== '') { $y = substr($entry['end_date'], 0, 4); }
                    if ($chosen === null) {
                        $chosen = $entry;
                        $chosen['_y'] = $y;
                    } else {
                        $prevY = $chosen['_y'] ?? null;
                        if ($y !== null && (int)$y > (int)($prevY ?? 0)) {
                            $chosen = $entry;
                            $chosen['_y'] = $y;
                        }
                    }
                }
                if ($chosen !== null) {
                    $college = $chosen['school'] ?? ($chosen['institution'] ?? null);
                    $degree = $chosen['degree'] ?? null;
                    $grad_year = $chosen['_y'] ?? null;
                    $major = $chosen['field_of_study'] ?? ($chosen['course'] ?? null);
                }
            }
        }

        // Only include in placements if we detected a college-level education entry
        if (empty($college) && empty($degree) && empty($major)) {
            continue;
        }

        $placements[] = [
            'application_id' => $row['application_id'],
            'application_date' => $row['application_date'],
            'email' => $row['email'],
            'first_name' => $row['first_name'] ?? '',
            'last_name' => $row['last_name'] ?? '',
            'job_title' => $row['job_title'],
            'company_id' => $row['company_id'],
            'company_name' => $row['company_name'],
            'college' => $college,
            'degree' => $degree,
            'grad_year' => $grad_year,
            'major' => $major
        ];
    }
} catch (PDOException $e) {
    $error = "Error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Graduate Placements - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.css" rel="stylesheet">
    <link href="../assets/css/sidebar.css" rel="stylesheet">
    <link href="assets/css/admin-modern.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/buttons/2.2.2/css/buttons.bootstrap5.min.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h3>Admin Panel</h3>
            <button class="toggle-btn">
                <i class="fas fa-bars"></i>
            </button>
        </div>
        <div class="text-center mb-2 mt-1">
            <img src="../assets/images/new Peso logo.jpg" alt="PESO Logo" class="rounded-circle" style="width: 50px; height: 50px; object-fit: cover; border: 1px solid #fff; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
        </div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="users.php">
                    <i class="fas fa-users"></i>
                    <span>Users</span>
                </a>
            </li>
            <li>
                <a href="jobs.php">
                    <i class="fas fa-briefcase"></i>
                    <span>Jobs</span>
                </a>
            </li>
            <li>
                <a href="categories.php">
                    <i class="fas fa-tags"></i>
                    <span>Job Categories</span>
                </a>
            </li>
            <li>
                <a href="companies.php">
                    <i class="fas fa-building"></i>
                    <span>Companies</span>
                </a>
            </li>
            <li>
                <a href="applications.php">
                    <i class="fas fa-file-alt"></i>
                    <span>Applications</span>
                </a>
            </li>
            <li>
                <a href="job-fair-events.php">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Job Fair Events</span>
                </a>
            </li>
            <li>
                <a href="placements.php" class="active">
                    <i class="fas fa-user-graduate"></i>
                    <span>Graduate Placements</span>
                </a>
            </li>
            <li>
                <a href="reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
            <li>
                <a href="profile.php">
                    <i class="fas fa-user"></i>
                    <span>My Profile</span>
                </a>
            </li>
            <li>
                <a href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="main-content page-transition">
        <?php include 'includes/header.php'; ?>

        <div class="container-fluid">
            <div class="page-header" data-aos="fade-down">
                <div class="container-fluid">
                    <div class="d-flex justify-content-between align-items-center position-relative">
                        <div>
                            <h1 class="page-title">
                                <i class="fas fa-user-graduate me-3"></i>
                                Graduate Placements
                            </h1>
                            <p class="page-subtitle">Monitor hired graduates and their destination companies</p>
                        </div>
                        <div class="d-flex gap-3">
                            <div class="text-center">
                                <h3 class="text-white mb-0"><?php echo count($placements); ?></h3>
                                <small class="opacity-75">Total Placements</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-modern" data-aos="fade-up">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="100">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <select class="form-select form-control-modern" name="company">
                                <option value="">All Companies</option>
                                <?php foreach ($companies as $company): ?>
                                    <option value="<?php echo $company['company_id']; ?>" <?php echo $company_filter == $company['company_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($company['company_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control form-control-modern" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>">
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-calendar"></i></span>
                                <input type="date" class="form-control form-control-modern" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>">
                            </div>
                        </div>
                        <div class="col-md-2 d-flex">
                            <button type="submit" class="btn btn-modern btn-modern-primary w-100">
                                <i class="fas fa-filter me-2"></i> Apply
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Search -->
            <div class="filter-card" data-aos="fade-up" data-aos-delay="150">
                <div class="card-body">
                    <div class="row g-3 align-items-center">
                        <div class="col-md-4">
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" id="quickSearch" class="form-control form-control-modern" placeholder="Quick search in results... (name, email, company, job)" autocomplete="off">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <small class="text-muted">Live filters the table below without reloading.</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Placements Table -->
            <div class="modern-table-container" data-aos="fade-up" data-aos-delay="200">
                <div class="table-responsive">
                    <table class="table modern-table" id="placementsTable">
                        <thead>
                            <tr>
                                <th>Date Hired</th>
                                <th>Graduate</th>
                                <th>Email</th>
                                <th>College</th>
                                <th>Major/Course</th>
                                <th>Degree</th>
                                <th>Batch Year</th>
                                <th>Company</th>
                                <th>Job Title</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($placements)): ?>
                                <tr>
                                    <td colspan="7" class="text-center py-4">
                                        <div class="empty-state">
                                            <i class="fas fa-user-graduate"></i>
                                            <h6>No placements found</h6>
                                            <p class="text-muted">No hired applications match your filters.</p>
                                        </div>
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php $delay = 0; foreach ($placements as $p): $delay += 30; ?>
                                    <tr class="table-row-animated" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
                                        <td>
                                            <div class="d-flex align-items-center">
                                                <i class="fas fa-calendar-check text-muted me-2"></i>
                                                <?php echo date('M d, Y', strtotime($p['application_date'])); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="fw-bold">
                                                <?php echo htmlspecialchars(trim(($p['first_name'] . ' ' . $p['last_name'])) ?: 'N/A'); ?>
                                            </div>
                                        </td>
                                        <td>
                                            <a href="mailto:<?php echo htmlspecialchars($p['email']); ?>" class="text-decoration-none">
                                                <?php echo htmlspecialchars($p['email']); ?>
                                            </a>
                                        </td>
                                        <td><?php echo htmlspecialchars($p['college'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars($p['major'] ?? '—'); ?></td>
                                        <td><?php echo htmlspecialchars(($p['degree'] ?? '') ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars(($p['grad_year'] ?? '') ?: '—'); ?></td>
                                        <td><?php echo htmlspecialchars($p['company_name']); ?></td>
                                        <td><?php echo htmlspecialchars($p['job_title']); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/aos/2.3.4/aos.js"></script>
    <script src="../assets/js/sidebar.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/dataTables.buttons.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.bootstrap5.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/2.2.2/js/buttons.print.min.js"></script>
    <script>
        AOS.init({ duration: 800, easing: 'ease-out-cubic', once: true, offset: 100 });
        // Initialize DataTables + export buttons; wire quick search
        (function(){
            if (!window.jQuery) return;
            var dt = jQuery('#placementsTable').DataTable({
                responsive: true,
                // Remove default filter input ('f') from dom to avoid duplicate search box
                dom: 'Brtip',
                buttons: ['copy', 'excel', 'pdf', 'print']
            });
            var input = document.getElementById('quickSearch');
            if (input) {
                input.addEventListener('input', function(){
                    dt.search(input.value || '').draw();
                });
            }
        })();
    </script>
</body>
</html>


