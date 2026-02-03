<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

$message = "";
$messageType = "";

// --- 1. SET PAGINATION VARIABLES ---
$limit = 10; // Tournaments per page
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Handle Fee Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_fee_btn'])) {
    $new_fee = floatval($_POST['new_fee']);
    $update_sql = "UPDATE creation_fee SET tournament_create_value = '$new_fee'";
    if (mysqli_query($conn, $update_sql)) {
        $message = "Tournament fee updated successfully to $" . number_format($new_fee, 2);
        $messageType = "success";
    } else {
        $message = "Error updating fee: " . mysqli_error($conn);
        $messageType = "error";
    }
}

// Get Current Fee
$current_fee = 0;
$fee_sql = "SELECT tournament_create_value FROM creation_fee LIMIT 1";
$fee_result = mysqli_query($conn, $fee_sql);
if ($fee_result && mysqli_num_rows($fee_result) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_result);
    $current_fee = $fee_row['tournament_create_value'];
}

$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// --- 2. GET TOTAL COUNT FOR PAGINATION ---
$count_sql = "SELECT COUNT(*) AS total FROM tournaments t 
              INNER JOIN games g ON t.game_id = g.game_id 
              WHERE (t.title LIKE '%$searchTerm%' OR g.name LIKE '%$searchTerm%' OR t.tournament_id LIKE '%$searchTerm%')";
$count_result = mysqli_query($conn, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// --- 3. FETCH PAGINATED DATA ---
// Added LIMIT and OFFSET to the query
$sql = "SELECT t.*, g.name AS game_name 
        FROM tournaments t
        INNER JOIN games g ON t.game_id = g.game_id 
        WHERE (t.title LIKE '%$searchTerm%' 
           OR g.name LIKE '%$searchTerm%' 
           OR t.tournament_id LIKE '%$searchTerm%') 
        ORDER BY CASE WHEN t.admin_status = 'pending' THEN 1 ELSE 2 END ASC, t.tournament_id ASC
        LIMIT $limit OFFSET $offset";

$result = mysqli_query($conn, $sql);
$tournaments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $tournaments[] = $row;
}
?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Tournament Management</h2>
            <p>View and manage all active and upcoming tournaments.</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <div class="alert-content">
                    <span><?= htmlspecialchars($message) ?></span>
                    <button type="button" class="alert-close" onclick="this.parentElement.parentElement.style.display='none'">×</button>
                </div>
            </div>
        <?php endif; ?>

        <div class="action-bar-container">
            <div class="search-wrapper">
                <form method="GET" action="" class="search-form">
                    <button type="submit" class="search-btn"><i class="ph ph-magnifying-glass"></i></button>
                    <input type="text" name="search" placeholder="Search..." value="<?= htmlspecialchars($searchTerm) ?>">
                    <?php if (!empty($searchTerm)): ?>
                        <a href="tournaments.php" class="reset-btn"><i class="ph ph-x-circle"></i></a>
                    <?php endif; ?>
                </form>
            </div>
            <button id="openFeeModal" class="btn-create-fee"><i class="ph ph-currency-dollar"></i> Set Create Fee</button>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tournament Title</th>
                            <th>Game</th>
                            <th>Participants</th>
                            <th>Entry Fee</th>
                            <th>Start Date</th>
                            <th>Status</th>
                            <th>Approval</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tournaments as $row): 
                            $statusClass = "status-" . $row['status'];
                            $approvalClass = "approval-" . ($row['admin_status'] == 'approved' ? 'success' : ($row['admin_status'] == 'rejected' ? 'danger' : 'pending'));
                        ?>
                            <tr onclick="window.location='tournamentsDetail.php?id=<?= $row['tournament_id'] ?>'" style="cursor: pointer;">
                                <td><span>#<?= $row['tournament_id'] ?></span></td>
                                <td><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['game_name']) ?></td>
                                <td><?= number_format($row['max_participants']) ?> Players</td>
                                <td class="fee-text">$<?= number_format($row['fee'], 2) ?></td>
                                <td><?= date('d M Y', strtotime($row['start_date'])) ?></td>
                                <td><span class="custom-badge <?= $statusClass ?>"><?= strtoupper($row['status']) ?></span></td>
                                <td><span class="custom-badge <?= $approvalClass ?>"><?= strtoupper($row['admin_status']) ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>" class="pg-link"><i class="fas fa-chevron-left"></i> PREV</a>
            <?php endif; ?>

            <?php 
            // Show up to 5 page numbers for cleaner UI
            $start_loop = max(1, $page - 2);
            $end_loop = min($total_pages, $page + 2);
            
            for ($i = $start_loop; $i <= $end_loop; $i++): ?>
                <a href="?page=<?= $i ?>&search=<?= urlencode($searchTerm) ?>" class="pg-link <?= ($i == $page) ? 'active' : '' ?>"><?= $i ?></a>
            <?php endfor; ?>

            <?php if ($page < $total_pages): ?>
                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($searchTerm) ?>" class="pg-link">NEXT <i class="fas fa-chevron-right"></i></a>
            <?php endif; ?>
        </div>
    </div>
</div>