<?php

// auth.php handles session_start() safely
require_once 'includes/auth.php';
requireLogin();

require_once 'includes/db.php';
$conn = getConnection();


// Get total counts (Residents & Complaints)
$total_residents = (int) $conn->query("SELECT COUNT(*) AS c FROM residents")->fetch_assoc()['c'];

$case_stats = $conn->query("
    SELECT
        COUNT(*) AS total_complaints,
        SUM(status = 'Pending') AS pending_complaints,
        SUM(status = 'Resolved') AS resolved_complaints
    FROM cases
")->fetch_assoc();

$total_complaints    = (int) $case_stats['total_complaints'];
$pending_complaints  = (int) $case_stats['pending_complaints'];
$resolved_complaints = (int) $case_stats['resolved_complaints'];

// Handle search query
$search_term = isset($_GET['q']) ? trim($_GET['q']) : '';
$search_results = null;
$show_search_results = !empty($search_term);

if ($show_search_results) {
    // Prepare search pattern for LIKE queries
    $search_pattern = '%' . $search_term . '%';
    
    // Search Residents (by first name, last name, or address)
    $stmt_residents = $conn->prepare("
        SELECT first_name, last_name, address, created_at
        FROM residents
        WHERE first_name LIKE ? OR last_name LIKE ? OR address LIKE ?
        ORDER BY created_at DESC
        LIMIT 20
    ");
    $stmt_residents->bind_param("sss", $search_pattern, $search_pattern, $search_pattern);
    $stmt_residents->execute();
    $search_residents = $stmt_residents->get_result();
    
    // Search Cases/Complaints (by case number, complainant name, or status)
    $stmt_cases = $conn->prepare("
        SELECT c.case_number, r.first_name, r.last_name, c.status, c.date_filed
        FROM cases c
        JOIN residents r ON c.complainant_id = r.id
        WHERE c.case_number LIKE ? 
           OR r.first_name LIKE ? 
           OR r.last_name LIKE ? 
           OR c.status LIKE ?
        ORDER BY c.created_at DESC
        LIMIT 20
    ");
    $stmt_cases->bind_param("ssss", $search_pattern, $search_pattern, $search_pattern, $search_pattern);
    $stmt_cases->execute();
    $search_cases = $stmt_cases->get_result();
    
    $search_results = [
        'residents' => $search_residents,
        'cases'     => $search_cases
    ];
} else {
    // No search: fetch recent residents and cases for dashboard preview
    $recent_residents = $conn->query("
        SELECT first_name, last_name, address, created_at
        FROM residents
        ORDER BY created_at DESC
        LIMIT 5
    ");
    
    $recent_cases = $conn->query("
        SELECT c.case_number, r.first_name, r.last_name, c.status, c.date_filed
        FROM cases c
        JOIN residents r ON c.complainant_id = r.id
        ORDER BY c.created_at DESC
        LIMIT 5
    ");
}

$conn->close();

// Helper: badge CSS class for case status
function getBadgeClass(string $status): string {
    return match($status) {
        'Pending'             => 'badge-pending',
        'Resolved'            => 'badge-resolved',
        'Dismissed'           => 'badge-dismissed',
        'Under Investigation' => 'badge-under',
        default               => 'badge-other',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard — Barangay E-Records System</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Source Sans 3', sans-serif;
            background: #f0f3f9;
            color: #1a2a4a;
            min-height: 100vh;
        }

        /* ---- Sidebar (Fixed & Fully Functional) ---- */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: 240px;
            background: #0a2a5e;
            display: flex;
            flex-direction: column;
            z-index: 100;
            border-right: 4px solid #c8a84b;
        }

        .sidebar-logo {
            padding: 1.5rem 1.25rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-logo h2 {
            font-family: 'Playfair Display', serif;
            font-size: 14px;
            color: #f0d080;
            line-height: 1.4;
        }

        .sidebar-logo p {
            font-size: 10px;
            color: rgba(255,255,255,0.4);
            letter-spacing: 0.08em;
            text-transform: uppercase;
            margin-top: 4px;
        }

        .nav-section {
            padding: 1rem 0;
            flex: 1;
        }

        .nav-label {
            font-size: 10px;
            color: rgba(255,255,255,0.3);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0 1.25rem;
            margin-bottom: 4px;
            margin-top: 12px;
        }

        .nav-item {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 10px 1.25rem;
            color: rgba(255,255,255,0.65);
            text-decoration: none;
            font-size: 14px;
            transition: background 0.15s, color 0.15s;
        }

        .nav-item:hover  { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(200,168,75,0.15); color: #f0d080; border-right: 3px solid #c8a84b; }
        .nav-item .icon  { font-size: 16px; width: 20px; text-align: center; }

        .sidebar-user {
            padding: 1rem 1.25rem;
            border-top: 1px solid rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .avatar {
            width: 36px; height: 36px;
            border-radius: 50%;
            background: rgba(200,168,75,0.2);
            border: 1.5px solid #c8a84b;
            display: flex; align-items: center; justify-content: center;
            font-size: 14px;
            font-weight: 600;
            color: #f0d080;
            flex-shrink: 0;
        }

        .user-info p    { font-size: 13px; color: #fff; font-weight: 500; }
        .user-info span { font-size: 11px; color: rgba(255,255,255,0.45); }

        .btn-logout {
            display: block;
            margin: 0.5rem 1rem 1rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 6px;
            padding: 8px;
            color: rgba(255,255,255,0.55);
            font-size: 12px;
            text-align: center;
            cursor: pointer;
            text-decoration: none;
            transition: background 0.15s, color 0.15s;
        }
        .btn-logout:hover { background: rgba(255,0,0,0.15); color: #ff9999; }

        /* ---- Main Content ---- */
        .main { margin-left: 240px; min-height: 100vh; }

        .topbar {
            background: #fff;
            border-bottom: 1px solid #dde3f0;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .topbar h1 {
            font-family: 'Playfair Display', serif;
            font-size: 20px;
            color: #0a2a5e;
        }

        .topbar-date { font-size: 13px; color: #7a8aaa; }

        /* Search Bar Style */
        .search-form {
            display: flex;
            align-items: center;
            background: #f0f3f9;
            border-radius: 40px;
            padding: 2px 2px 2px 16px;
            border: 1px solid #dde3f0;
        }
        .search-input {
            border: none;
            background: transparent;
            font-size: 14px;
            padding: 8px 0;
            width: 220px;
            outline: none;
            font-family: inherit;
        }
        .search-btn {
            background: #0a2a5e;
            border: none;
            border-radius: 40px;
            padding: 6px 14px;
            color: white;
            font-weight: 500;
            cursor: pointer;
            font-size: 13px;
            transition: background 0.15s;
        }
        .search-btn:hover { background: #1a407a; }
        .search-clear {
            font-size: 12px;
            color: #7a8aaa;
            margin-left: 8px;
            text-decoration: none;
        }
        .search-clear:hover { color: #0a2a5e; }

        .content { padding: 2rem; }

        /* ---- Stat cards ---- */
        .stat-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dde3f0;
            padding: 1.25rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .stat-icon {
            width: 48px; height: 48px;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
            flex-shrink: 0;
        }

        .stat-icon.blue   { background: #e6eefa; }
        .stat-icon.gold   { background: #fef8e6; }
        .stat-icon.orange { background: #fff0e6; }
        .stat-icon.green  { background: #e6f5ee; }

        .stat-val   { font-size: 28px; font-weight: 600; color: #0a2a5e; line-height: 1; }
        .stat-label { font-size: 13px; color: #7a8aaa; margin-top: 3px; }

        /* ---- Tables & Cards ---- */
        .section-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        @media (max-width: 900px) {
            .section-grid { grid-template-columns: 1fr; }
        }

        .table-card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dde3f0;
            overflow: hidden;
        }

        .table-card-header {
            padding: 1rem 1.25rem;
            border-bottom: 1px solid #eef0f6;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .table-card-header h3 { font-size: 15px; font-weight: 600; color: #0a2a5e; }

        .table-card-header a {
            font-size: 12px;
            color: #2a5ea0;
            text-decoration: none;
            font-weight: 500;
        }

        table { width: 100%; border-collapse: collapse; }

        th {
            font-size: 11px;
            font-weight: 600;
            color: #8a98b4;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            padding: 8px 1.25rem;
            background: #f7f9fc;
            text-align: left;
        }

        td {
            padding: 10px 1.25rem;
            font-size: 13px;
            color: #2a3a5a;
            border-bottom: 1px solid #eef0f6;
        }

        tr:last-child td { border-bottom: none; }
        tr:hover td { background: #f7f9fc; }

        .badge {
            display: inline-block;
            padding: 2px 9px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-pending    { background: #fff3e0; color: #b36200; }
        .badge-resolved   { background: #e6f5ee; color: #1a6e3a; }
        .badge-dismissed  { background: #f5f5f5; color: #555; }
        .badge-under      { background: #e6eefa; color: #1a3a7a; }
        .badge-other      { background: #f0f0f0; color: #555; }

        .no-records {
            padding: 2rem;
            text-align: center;
            color: #a8b4cc;
            font-size: 13px;
        }

        .search-result-meta {
            background: #eef2f9;
            padding: 10px 1.25rem;
            font-size: 13px;
            border-bottom: 1px solid #dde3f0;
            color: #2a5a7a;
        }

        /* ---- Responsive Sidebar ---- */
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; }
            .main    { margin-left: 0; }
        }
    </style>
</head>
<body>

<!-- ---- Sidebar (Fully Functional Navigation) ---- -->
<div class="sidebar">
    <div class="sidebar-logo">
        <h2>Barangay E-Records<br>File Management</h2>
    </div>

    <nav class="nav-section">
        <div class="nav-label">Main</div>
        <a class="nav-item active" href="dashboard.php">
            <span class="icon">🏠</span> Dashboard
        </a>

        <div class="nav-label">Records</div>
        <a class="nav-item" href="residents.php">
            <span class="icon">👥</span> Residents
        </a>
        <a class="nav-item" href="cases.php">
            <span class="icon">📃</span> Cases & Complaints
        </a>

        <div class="nav-label">Reports</div>
        <a class="nav-item" href="reports.php">
            <span class="icon">📄</span> Generate Reports
        </a>
    </nav>

    <div class="sidebar-user">
        <div class="avatar">
            <?= htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))) ?>
        </div>
        <div class="user-info">
            <p><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></p>
            <span>Secretary</span>
        </div>
    </div>
    <a href="logout.php" class="btn-logout">➡ Sign Out</a>
</div>

<!-- ---- Main Dashboard Area ---- -->
<div class="main">
    <div class="topbar">
        <h1>Dashboard</h1>
        <div style="display: flex; align-items: center; gap: 1rem;">
            <!-- Search Bar (GET request preserves stats) -->
            <form method="GET" action="dashboard.php" class="search-form">
                <input type="text" name="q" class="search-input" placeholder="🔍 Search residents or complaints..." value="<?= htmlspecialchars($search_term) ?>">
                <button type="submit" class="search-btn">Search</button>
                <?php if ($show_search_results): ?>
                    <a href="dashboard.php" class="search-clear">✖ Clear</a>
                <?php endif; ?>
            </form>
        </div>
    </div>

    <div class="content">

        <!-- Stat Cards: Number of Residents & Number of Complaints -->
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-icon blue">👥</div>
                <div>
                    <div class="stat-val"><?= $total_residents ?></div>
                    <div class="stat-label">Total Residents</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon gold">📋</div>
                <div>
                    <div class="stat-val"><?= $total_complaints ?></div>
                    <div class="stat-label">Total Complaints</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon orange">⚠️</div>
                <div>
                    <div class="stat-val"><?= $pending_complaints ?></div>
                    <div class="stat-label">Pending Complaints</div>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-icon green">✅</div>
                <div>
                    <div class="stat-val"><?= $resolved_complaints ?></div>
                    <div class="stat-label">Resolved Complaints</div>
                </div>
            </div>
        </div>

        <?php if ($show_search_results): ?>
            <!-- ========== SEARCH RESULTS SECTION ========== -->
            <div style="margin-bottom: 1rem;">
                <h3 style="font-weight: 600; font-size: 18px;">Search results for: “<?= htmlspecialchars($search_term) ?>”</h3>
            </div>
            
            <div class="section-grid">
                <!-- Residents Search Results -->
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>👥 Residents matching</h3>
                        <a href="residents.php">Manage residents</a>
                    </div>
                    <?php if ($search_results['residents']->num_rows > 0): ?>
                        <div class="search-result-meta">
                            Found <?= $search_results['residents']->num_rows ?> resident(s)
                        </div>
                        <table>
                            <thead>
                                <tr><th>Name</th><th>Address</th><th>Date Added</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($r = $search_results['residents']->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                    <td><?= htmlspecialchars(mb_strlen($r['address']) > 35 ? mb_substr($r['address'], 0, 35) . '…' : $r['address']) ?></td>
                                    <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-records">No residents match your search.</div>
                    <?php endif; ?>
                </div>

                <!-- Complaints Search Results -->
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>📋 Complaints matching</h3>
                        <a href="cases.php">View all cases</a>
                    </div>
                    <?php if ($search_results['cases']->num_rows > 0): ?>
                        <div class="search-result-meta">
                            Found <?= $search_results['cases']->num_rows ?> complaint(s)
                        </div>
                        <table>
                            <thead>
                                <tr><th>Case No.</th><th>Complainant</th><th>Status</th></tr>
                            </thead>
                            <tbody>
                            <?php while ($c = $search_results['cases']->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['case_number']) ?></td>
                                    <td><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                    <td><span class="badge <?= getBadgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-records">No complaints match your search.</div>
                    <?php endif; ?>
                </div>
            </div>

        <?php else: ?>
            <!-- ========== DEFAULT DASHBOARD VIEW (Recent residents & complaints) ========== -->
            <div class="section-grid">

                <!-- Recent Residents -->
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>👥 Recent Residents</h3>
                        <a href="residents.php">View all</a>
                    </div>
                    <?php if ($recent_residents && $recent_residents->num_rows > 0): ?>
                        <table>
                            <thead><tr><th>Name</th><th>Address</th><th>Date Added</th></tr></thead>
                            <tbody>
                            <?php while ($r = $recent_residents->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($r['first_name'] . ' ' . $r['last_name']) ?></td>
                                    <td><?= htmlspecialchars(mb_strlen($r['address']) > 28 ? mb_substr($r['address'], 0, 28) . '…' : $r['address']) ?></td>
                                    <td><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-records">No residents added yet.</div>
                    <?php endif; ?>
                </div>

                <!-- Recent Complaints (Cases) -->
                <div class="table-card">
                    <div class="table-card-header">
                        <h3>📋 Recent Complaints</h3>
                        <a href="cases.php">View all</a>
                    </div>
                    <?php if ($recent_cases && $recent_cases->num_rows > 0): ?>
                        <table>
                            <thead><tr><th>Case No.</th><th>Complainant</th><th>Status</th></tr></thead>
                            <tbody>
                            <?php while ($c = $recent_cases->fetch_assoc()): ?>
                                <tr>
                                    <td><?= htmlspecialchars($c['case_number']) ?></td>
                                    <td><?= htmlspecialchars($c['first_name'] . ' ' . $c['last_name']) ?></td>
                                    <td><span class="badge <?= getBadgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                                </tr>
                            <?php endwhile; ?>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <div class="no-records">No complaints filed yet.</div>
                    <?php endif; ?>
                </div>

            </div><!-- /.section-grid -->
        <?php endif; ?>
    </div><!-- /.content -->
</div><!-- /.main -->

</body>
</html>