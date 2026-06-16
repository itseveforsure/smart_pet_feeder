<?php
require_once 'config.php';

if (!isAdminLoggedIn()) {
    redirect('admin_login.php');
}

// SAFE QUERY - Check if schedule_id column exists
try {
    $checkColumn = $pdo->query("SHOW COLUMNS FROM feeder_history LIKE 'schedule_id'");
    $hasScheduleId = $checkColumn->rowCount() > 0;
    
    if ($hasScheduleId) {
        $stmt = $pdo->prepare("SELECT fh.*, u.username, p.pet_name,
                                      CASE 
                                          WHEN fh.schedule_id IS NULL THEN 'Manual'
                                          ELSE 'Scheduled'
                                      END as feeding_type
                               FROM feeder_history fh 
                               JOIN feeder_users u ON fh.user_id = u.id 
                               JOIN feeder_pets p ON fh.pet_id = p.id 
                               ORDER BY fh.feed_time DESC");
    } else {
        $stmt = $pdo->prepare("SELECT fh.*, u.username, p.pet_name,
                                      'Manual' as feeding_type
                               FROM feeder_history fh 
                               JOIN feeder_users u ON fh.user_id = u.id 
                               JOIN feeder_pets p ON fh.pet_id = p.id 
                               ORDER BY fh.feed_time DESC");
    }
    $stmt->execute();
    $all_feeds = $stmt->fetchAll();
} catch (PDOException $e) {
    $stmt = $pdo->prepare("SELECT fh.*, u.username, p.pet_name 
                           FROM feeder_history fh 
                           JOIN feeder_users u ON fh.user_id = u.id 
                           JOIN feeder_pets p ON fh.pet_id = p.id 
                           ORDER BY fh.feed_time DESC");
    $stmt->execute();
    $all_feeds = $stmt->fetchAll();
    foreach ($all_feeds as &$feed) {
        $feed['feeding_type'] = 'Manual';
    }
}

// Handle delete
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM feeder_history WHERE id = ?");
    $stmt->execute([$_GET['delete']]);
    redirect('admin_feeding_history.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding History - Admin</title>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f8f9fa;
            color: #212529;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            padding: 20px 0;
        }
        .sidebar-header { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 22px; }
        .nav-menu { list-style: none; padding: 0 15px; }
        .nav-menu a {
            display: block;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
        }
        .nav-menu a:hover { background: rgba(255,255,255,0.1); }
        .nav-menu a.active { background: linear-gradient(135deg, #667eea, #764ba2); }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title h1 {
            font-size: 24px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logout-btn {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            border: 1px solid #e9ecef;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        .btn-group { display: flex; gap: 10px; }
        .btn-print, .btn-pdf {
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 14px;
        }
        .btn-print { background: #6c757d; color: white; }
        .btn-pdf { background: #dc3545; color: white; }
        .btn-delete {
            padding: 4px 10px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-size: 12px;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background: #f8f9fa; font-weight: 600; position: sticky; top: 0; }
        .badge-manual { background: #17a2b8; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .badge-scheduled { background: #28a745; color: white; padding: 4px 10px; border-radius: 20px; font-size: 12px; display: inline-block; }
        .pagination {
            margin-top: 20px;
            display: flex;
            justify-content: center;
            gap: 10px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            text-decoration: none;
            color: #667eea;
        }
        .pagination .active {
            background: #667eea;
            color: white;
            border-color: #667eea;
        }
        .filter-bar {
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-bar input, .filter-bar select {
            padding: 8px 12px;
            border: 1px solid #e9ecef;
            border-radius: 8px;
        }
        .table-container {
            overflow-x: auto;
            max-height: 500px;
        }
        @media print {
            .sidebar, .top-bar, .btn-group, .logout-btn, .nav-menu, .sidebar-header, .filter-bar, .pagination, .btn-delete {
                display: none !important;
            }
            .main-content { margin: 0 !important; padding: 0 !important; }
            .section-card { padding: 0 !important; }
            th { background: #f2f2f2; }
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2 { display: none; }
            .main-content { margin-left: 70px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Smart Feeder</h2></div>
        <ul class="nav-menu">
            <li><a href="admin_dashboard.php">📊 Dashboard</a></li>
            <li><a href="admin_users.php">👥 Users</a></li>
            <li><a href="admin_pets.php">🐾 Pets</a></li>
            <li><a href="admin_schedules.php">⏰ Schedules</a></li>
            <li><a href="admin_feeding_history.php" class="active">📋 Feeding History</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title"><h1>Feeding History</h1></div>
            <div><span>Welcome, <?php echo $_SESSION['admin_username']; ?></span> <a href="admin_logout.php" class="logout-btn">Logout</a></div>
        </div>
        <div class="section-card" id="feeding-history-section">
            <div class="section-title">
                <span>📋 All Feeding Records</span>
                <div class="btn-group">
                    <button class="btn-print" onclick="printFeedingHistory()">🖨️ Print</button>
                    <button class="btn-pdf" onclick="exportToPDF()">📄 Export PDF</button>
                </div>
            </div>
            
            <!-- Filter Bar -->
            <div class="filter-bar">
                <input type="text" id="searchInput" placeholder="🔍 Search user or pet..." onkeyup="filterTable()">
                <select id="typeFilter" onchange="filterTable()">
                    <option value="">All Types</option>
                    <option value="Manual">Manual</option>
                    <option value="Scheduled">Scheduled</option>
                </select>
                <select id="statusFilter" onchange="filterTable()">
                    <option value="">All Status</option>
                    <option value="success">Success</option>
                    <option value="failed">Failed</option>
                </select>
            </div>
            
            <div class="table-container">
                <table id="feeding-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User</th>
                            <th>Pet</th>
                            <th>Portion</th>
                            <th>Time</th>
                            <th>Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($all_feeds as $feed): ?>
                        <tr>
                            <td><?php echo $feed['id']; ?></td>
                            <td><?php echo htmlspecialchars($feed['username']); ?></td>
                            <td><?php echo htmlspecialchars($feed['pet_name']); ?></td>
                            <td><?php echo $feed['portion_size']; ?>g</td>
                            <td><?php echo date('M j, Y g:i A', strtotime($feed['feed_time'])); ?></td>
                            <td>
                                <?php if ($feed['feeding_type'] == 'Scheduled'): ?>
                                    <span class="badge-scheduled">⏰ Scheduled</span>
                                <?php else: ?>
                                    <span class="badge-manual">🔘 Manual</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $feed['status'] == 'success' ? '✅ Success' : '❌ Failed'; ?></td>
                            <td><a href="?delete=<?php echo $feed['id']; ?>" class="btn-delete" onclick="return confirm('Delete this record?')">Delete</a></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (count($all_feeds) === 0): ?>
                        <tr><td colspan="8" style="text-align: center;">No feeding records found</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        function printFeedingHistory() {
            const printWindow = window.open('', '_blank');
            printWindow.document.write('<html><head><title>Feeding History Report</title>');
            printWindow.document.write('<style>');
            printWindow.document.write(`
                body { font-family: Arial, sans-serif; margin: 20px; }
                h2 { color: #333; }
                .report-meta { color: #666; margin-bottom: 20px; border-bottom: 1px solid #ddd; padding-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; }
                th, td { border: 1px solid #ddd; padding: 10px; text-align: left; }
                th { background-color: #f2f2f2; }
                .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #999; }
            `);
            printWindow.document.write('</style></head><body>');
            printWindow.document.write('<h2>Smart Pet Feeder - Feeding History Report</h2>');
            printWindow.document.write('<div class="report-meta">Generated on: <?php echo date('F j, Y, g:i A'); ?><br>Total Records: <?php echo count($all_feeds); ?></div>');
            const table = document.getElementById('feeding-table').cloneNode(true);
            // Remove action column for print
            if (table.rows[0]) {
                for (let i = 0; i < table.rows.length; i++) {
                    if (table.rows[i].cells.length > 0) {
                        table.rows[i].deleteCell(-1);
                    }
                }
            }
            printWindow.document.write(table.outerHTML);
            printWindow.document.write('<div class="footer">Smart Pet Feeder System - Official Report</div>');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.print();
            printWindow.close();
        }

        function exportToPDF() {
            const element = document.getElementById('feeding-history-section');
            const cloneElement = element.cloneNode(true);
            
            // Remove action column and buttons
            const btns = cloneElement.querySelector('.btn-group');
            if (btns) btns.remove();
            const filterBar = cloneElement.querySelector('.filter-bar');
            if (filterBar) filterBar.remove();
            
            const table = cloneElement.querySelector('table');
            if (table && table.rows[0]) {
                for (let i = 0; i < table.rows.length; i++) {
                    if (table.rows[i].cells.length > 0) {
                        table.rows[i].deleteCell(-1);
                    }
                }
            }
            
            const opt = {
                margin: [0.5, 0.5, 0.5, 0.5],
                filename: 'feeding_history_<?php echo date('Y-m-d'); ?>.pdf',
                image: { type: 'jpeg', quality: 0.98 },
                html2canvas: { scale: 2 },
                jsPDF: { unit: 'in', format: 'a4', orientation: 'landscape' }
            };
            html2pdf().set(opt).from(cloneElement).save();
        }

        function filterTable() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const typeFilter = document.getElementById('typeFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const rows = document.querySelectorAll('#feeding-table tbody tr');
            
            rows.forEach(row => {
                const userPet = (row.cells[1]?.innerText + ' ' + row.cells[2]?.innerText).toLowerCase();
                const type = row.cells[5]?.innerText;
                const status = row.cells[6]?.innerText;
                
                let show = true;
                if (searchTerm && !userPet.includes(searchTerm)) show = false;
                if (typeFilter && !type.includes(typeFilter)) show = false;
                if (statusFilter && !status.toLowerCase().includes(statusFilter)) show = false;
                
                row.style.display = show ? '' : 'none';
            });
        }
    </script>
</body>
</html>