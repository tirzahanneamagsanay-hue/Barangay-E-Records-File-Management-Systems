<?php

require_once 'includes/auth.php';
requireLogin();
require_once 'includes/db.php';


//  Handle API requests (POST with action)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $conn = getConnection();
    $action = $_POST['action'];

    function nullIfEmpty(?string $val): ?string {
        $val = trim($val ?? '');
        return $val !== '' ? $val : null;
    }

    // Shared data
    $fn = trim($_POST['first_name'] ?? '');
    $ln = trim($_POST['last_name'] ?? '');
    $mn = nullIfEmpty($_POST['middle_name'] ?? '');
    $bd = nullIfEmpty($_POST['birthdate'] ?? '');
    $gn = $_POST['gender'] ?? 'Male';
    $cs = $_POST['civil_status'] ?? 'Single';
    $ad = trim($_POST['address'] ?? '');
    $ct = nullIfEmpty($_POST['contact_no'] ?? '');
    $em = nullIfEmpty($_POST['email'] ?? '');

    // ADD
    if ($action === 'add') {
        if ($fn === '' || $ln === '' || $ad === '') {
            echo json_encode(['ok' => false, 'msg' => 'First name, last name, and address are required.']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("INSERT INTO residents (first_name, last_name, middle_name, birthdate, gender, civil_status, address, contact_no, email) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param('sssssssss', $fn, $ln, $mn, $bd, $gn, $cs, $ad, $ct, $em);
        $ok = $stmt->execute();
        echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Resident added successfully.' : ('Database error: ' . $stmt->error)]);
        $stmt->close();
        $conn->close();
        exit;
    }

    // EDIT
    if ($action === 'edit') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0 || $fn === '' || $ln === '' || $ad === '') {
            echo json_encode(['ok' => false, 'msg' => 'Required fields missing.']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("UPDATE residents SET first_name=?, last_name=?, middle_name=?, birthdate=?, gender=?, civil_status=?, address=?, contact_no=?, email=? WHERE id=?");
        $stmt->bind_param('sssssssssi', $fn, $ln, $mn, $bd, $gn, $cs, $ad, $ct, $em, $id);
        $ok = $stmt->execute();
        echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Resident updated successfully.' : ('Update failed: ' . $stmt->error)]);
        $stmt->close();
        $conn->close();
        exit;
    }

    // DELETE
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid resident ID.']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("DELETE FROM residents WHERE id = ?");
        $stmt->bind_param('i', $id);
        $ok = $stmt->execute();
        echo json_encode(['ok' => $ok, 'msg' => $ok ? 'Resident deleted successfully.' : ('Delete failed: ' . $stmt->error)]);
        $stmt->close();
        $conn->close();
        exit;
    }

    // GET (fetch single resident)
    if ($action === 'get') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'msg' => 'Invalid resident ID.']);
            $conn->close();
            exit;
        }
        $stmt = $conn->prepare("SELECT * FROM residents WHERE id = ? LIMIT 1");
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        $conn->close();
        echo json_encode(['ok' => (bool)$row, 'data' => $row]);
        exit;
    }

    echo json_encode(['ok' => false, 'msg' => 'Unknown action.']);
    $conn->close();
    exit;
}


//  If NOT a POST API request then display the HTML interface
$conn = getConnection();
$residents = $conn->query("SELECT * FROM residents ORDER BY created_at DESC");
$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Residents Management — Barangay E-Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600&family=Source+Sans+3:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: 'Source Sans 3', sans-serif;
            background: #f0f3f9;
            color: #1a2a4a;
        }
        /* same sidebar styling as dashboard */
        .sidebar {
            position: fixed; left: 0; top: 0; bottom: 0;
            width: 240px;
            background: #0a2a5e;
            display: flex;
            flex-direction: column;
            border-right: 4px solid #c8a84b;
            z-index: 100;
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
        }
        .nav-section { padding: 1rem 0; flex: 1; }
        .nav-label {
            font-size: 10px;
            color: rgba(255,255,255,0.3);
            letter-spacing: 0.12em;
            text-transform: uppercase;
            padding: 0 1.25rem;
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
        }
        .nav-item:hover { background: rgba(255,255,255,0.08); color: #fff; }
        .nav-item.active { background: rgba(200,168,75,0.15); color: #f0d080; border-right: 3px solid #c8a84b; }
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
            color: #f0d080;
        }
        .user-info p { font-size: 13px; color: #fff; font-weight: 500; }
        .user-info span { font-size: 11px; color: rgba(255,255,255,0.45); }
        .btn-logout {
            display: block;
            margin: 0.5rem 1rem 1rem;
            background: rgba(255,255,255,0.07);
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 6px;
            padding: 8px;
            color: rgba(255,255,255,0.55);
            text-align: center;
            text-decoration: none;
            font-size: 12px;
        }
        .btn-logout:hover { background: rgba(255,0,0,0.15); color: #ff9999; }
        .main { margin-left: 240px; min-height: 100vh; }
        .topbar {
            background: #fff;
            border-bottom: 1px solid #dde3f0;
            padding: 1rem 2rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar h1 { font-family: 'Playfair Display', serif; font-size: 20px; color: #0a2a5e; }
        .content { padding: 2rem; }
        .card {
            background: #fff;
            border-radius: 12px;
            border: 1px solid #dde3f0;
            margin-bottom: 2rem;
            overflow: hidden;
        }
        .card-header {
            padding: 1rem 1.5rem;
            background: #f7f9fc;
            border-bottom: 1px solid #eef0f6;
            font-weight: 600;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
            gap: 1rem;
            padding: 1.5rem;
        }
        .form-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #2a5a7a;
            margin-bottom: 4px;
        }
        .form-group input, .form-group select {
            width: 100%;
            padding: 8px 10px;
            border: 1px solid #d0dae8;
            border-radius: 8px;
            font-family: inherit;
            font-size: 13px;
        }
        button, .btn {
            background: #0a2a5e;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 40px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 500;
        }
        button:hover { background: #1a407a; }
        .btn-danger { background: #b33; }
        .btn-danger:hover { background: #a22; }
        .resident-table {
            width: 100%;
            border-collapse: collapse;
        }
        .resident-table th, .resident-table td {
            padding: 12px 1rem;
            text-align: left;
            border-bottom: 1px solid #eef0f6;
            font-size: 13px;
        }
        .resident-table th {
            background: #f7f9fc;
            font-weight: 600;
            color: #2a4a6e;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-buttons button {
            padding: 4px 12px;
            font-size: 12px;
        }
        .modal {
            display: none;
            position: fixed;
            top: 0; left: 0;
            width: 100%; height: 100%;
            background: rgba(0,0,0,0.5);
            align-items: center;
            justify-content: center;
            z-index: 1000;
        }
        .modal-content {
            background: white;
            max-width: 600px;
            width: 90%;
            border-radius: 16px;
            max-height: 90vh;
            overflow-y: auto;
        }
        .modal-header {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #ddd;
            font-weight: bold;
            display: flex;
            justify-content: space-between;
        }
        .close-modal {
            cursor: pointer;
            font-size: 20px;
        }
        @media (max-width: 768px) {
            .sidebar { width: 100%; position: relative; height: auto; }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>

<div class="sidebar">
    <div class="sidebar-logo">
        <h2>Barangay E-Records<br>File Management</h2>
    </div>
    <nav class="nav-section">
        <div class="nav-label">Main</div>
        <a class="nav-item" href="dashboard.php"><span class="icon">🏠</span> Dashboard</a>
        <div class="nav-label">Records</div>
        <a class="nav-item active" href="residents.php"><span class="icon">👥</span> Residents</a>
        <a class="nav-item" href="cases.php"><span class="icon">📃</span> Cases & Complaints</a>
        <div class="nav-label">Reports</div>
        <a class="nav-item" href="reports.php"><span class="icon">📄</span> Generate Reports</a>
    </nav>
    <div class="sidebar-user">
        <div class="avatar"><?= htmlspecialchars(strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1))) ?></div>
        <div class="user-info">
            <p><?= htmlspecialchars($_SESSION['full_name'] ?? 'User') ?></p>
            <span>Secretary</span>
        </div>
    </div>
    <a href="logout.php" class="btn-logout">➡ Sign Out</a>
</div>

<div class="main">
    <div class="topbar">
        <h1>Residents Management</h1>   
    </div>
    <div class="content">

        <!-- Add Resident Button -->
        <div style="margin-bottom: 1.5rem;">
            <button id="openAddModalBtn">+ Add New Resident</button>
        </div>

        <!-- Residents List -->
        <div class="card">
            <div class="card-header">All Residents</div>
            <div style="overflow-x: auto;">
                <table class="resident-table">
                    <thead>
                        <tr>
                            <th>ID</th><th>Full Name</th><th>Gender</th><th>Civil Status</th><th>Address</th><th>Contact</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="residentsTableBody">
                        <?php while ($row = $residents->fetch_assoc()): ?>
                        <tr data-id="<?= $row['id'] ?>">
                            <td><?= $row['id'] ?></td>
                            <td><?= htmlspecialchars($row['first_name'] . ' ' . ($row['middle_name'] ? $row['middle_name'][0] . '. ' : '') . $row['last_name']) ?></td>
                            <td><?= $row['gender'] ?></td>
                            <td><?= $row['civil_status'] ?></td>
                            <td><?= htmlspecialchars($row['address']) ?></td>
                            <td><?= htmlspecialchars($row['contact_no'] ?? '—') ?></td>
                            <td class="action-buttons">
                                <button class="editBtn" data-id="<?= $row['id'] ?>">Edit</button>
                                <button class="deleteBtn" data-id="<?= $row['id'] ?>">Delete</button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                        <?php if ($residents->num_rows === 0): ?>
                        <tr><td colspan="7" style="text-align:center;">No residents found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal for Add/Edit Resident -->
<div id="residentModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <span id="modalTitle">Add New Resident</span>
            <span class="close-modal">&times;</span>
        </div>
        <form id="residentForm">
            <input type="hidden" name="id" id="residentId">
            <div class="form-grid">
                <div class="form-group"><label>First Name *</label><input type="text" name="first_name" id="first_name" required></div>
                <div class="form-group"><label>Middle Name</label><input type="text" name="middle_name" id="middle_name"></div>
                <div class="form-group"><label>Last Name *</label><input type="text" name="last_name" id="last_name" required></div>
                <div class="form-group"><label>Birthdate</label><input type="date" name="birthdate" id="birthdate"></div>
                <div class="form-group"><label>Gender</label>
                    <select name="gender" id="gender"><option>Male</option><option>Female</option></select>
                </div>
                <div class="form-group"><label>Civil Status</label>
                    <select name="civil_status" id="civil_status"><option>Single</option><option>Married</option><option>Widowed</option><option>Divorced</option></select>
                </div>
                <div class="form-group"><label>Address *</label><input type="text" name="address" id="address" required></div>
                <div class="form-group"><label>Contact No.</label><input type="text" name="contact_no" id="contact_no"></div>
                <div class="form-group"><label>Email</label><input type="email" name="email" id="email"></div>
            </div>
            <div style="padding: 1rem 1.5rem 1.5rem; text-align: right;">
                <button type="button" id="cancelModalBtn">Cancel</button>
                <button type="submit" id="saveBtn">Save Resident</button>
            </div>
        </form>
    </div>
</div>

<script>
    // Helper: fetch JSON from API
    async function apiRequest(action, formData) {
        formData.append('action', action);
        const res = await fetch(window.location.href, {
            method: 'POST',
            body: formData
        });
        return res.json();
    }

    // Refresh page after successful operation
    function refreshPage() {
        location.reload();
    }

    // Add / Edit form submission
    document.getElementById('residentForm').addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const formData = new FormData(form);
        const id = document.getElementById('residentId').value;
        const action = id ? 'edit' : 'add';
        if (action === 'edit') formData.append('id', id);
        const result = await apiRequest(action, formData);
        if (result.ok) {
            alert(result.msg);
            refreshPage();
        } else {
            alert('Error: ' + result.msg);
        }
    });

    // Open modal for Add
    document.getElementById('openAddModalBtn').onclick = () => {
        document.getElementById('modalTitle').innerText = 'Add New Resident';
        document.getElementById('residentForm').reset();
        document.getElementById('residentId').value = '';
        document.getElementById('residentModal').style.display = 'flex';
    };

    // Edit buttons
    document.querySelectorAll('.editBtn').forEach(btn => {
        btn.onclick = async () => {
            const id = btn.getAttribute('data-id');
            const formData = new FormData();
            formData.append('id', id);
            const result = await apiRequest('get', formData);
            if (result.ok && result.data) {
                const d = result.data;
                document.getElementById('residentId').value = d.id;
                document.getElementById('first_name').value = d.first_name;
                document.getElementById('middle_name').value = d.middle_name || '';
                document.getElementById('last_name').value = d.last_name;
                document.getElementById('birthdate').value = d.birthdate || '';
                document.getElementById('gender').value = d.gender;
                document.getElementById('civil_status').value = d.civil_status;
                document.getElementById('address').value = d.address;
                document.getElementById('contact_no').value = d.contact_no || '';
                document.getElementById('email').value = d.email || '';
                document.getElementById('modalTitle').innerText = 'Edit Resident';
                document.getElementById('residentModal').style.display = 'flex';
            } else {
                alert('Could not load resident data.');
            }
        };
    });

    // Delete buttons
    document.querySelectorAll('.deleteBtn').forEach(btn => {
        btn.onclick = async () => {
            if (!confirm('Are you sure you want to delete this resident?')) return;
            const id = btn.getAttribute('data-id');
            const formData = new FormData();
            formData.append('id', id);
            const result = await apiRequest('delete', formData);
            alert(result.msg);
            if (result.ok) refreshPage();
        };
    });

    // Close modal
    function closeModal() {
        document.getElementById('residentModal').style.display = 'none';
    }
    document.querySelectorAll('.close-modal, #cancelModalBtn').forEach(el => el.addEventListener('click', closeModal));
    window.onclick = (e) => { if (e.target === document.getElementById('residentModal')) closeModal(); };
</script>
</body>
</html>