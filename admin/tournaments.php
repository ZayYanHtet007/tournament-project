<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

$message = "";
$messageType = "";


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

$current_fee = 0;
$fee_sql = "SELECT tournament_create_value FROM creation_fee LIMIT 1";
$fee_result = mysqli_query($conn, $fee_sql);
if ($fee_result && mysqli_num_rows($fee_result) > 0) {
    $fee_row = mysqli_fetch_assoc($fee_result);
    $current_fee = $fee_row['tournament_create_value'];
}

$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$sql = "SELECT t.*, g.name AS game_name 
        FROM tournaments t
        INNER JOIN games g ON t.game_id = g.game_id 
        WHERE (t.title LIKE '%$searchTerm%' 
           OR g.name LIKE '%$searchTerm%' 
           OR t.tournament_id LIKE '%$searchTerm%') 
        ORDER BY CASE WHEN t.admin_status = 'pending' THEN 1 ELSE 2 END ASC, t.tournament_id ASC";

$result = mysqli_query($conn, $sql);

if (!$result) {
    die("Query Failed: " . mysqli_error($conn));
}

$tournaments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['calculated_approval'] = $row['admin_status'];
    $tournaments[] = $row;
}

// Sort logic
usort($tournaments, function ($a, $b) {
    $order = ['pending' => 1, 'approved' => 2, 'rejected' => 3];
    $statusA = $order[$a['calculated_approval']] ?? 4;
    $statusB = $order[$b['calculated_approval']] ?? 4;

    if ($statusA == $statusB) {
        return $a['tournament_id'] <=> $b['tournament_id'];
    }
    return $statusA <=> $statusB;
});
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
                    <button type="button" class="alert-close" onclick="this.parentElement.parentElement.style.display='none'">
                        ×
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <div class="action-bar-container">
            <div class="search-wrapper">
                <form method="GET" action="" class="search-form">
                    <button type="submit" class="search-btn">
                        <i class="ph ph-magnifying-glass"></i>
                    </button>
                    <input type="text" name="search" placeholder="Search tournament, games or IDs..."
                        value="<?= htmlspecialchars($searchTerm) ?>">
                    <?php if (!empty($searchTerm)): ?>
                        <a href="tournaments.php" class="reset-btn">
                            <i class="ph ph-x-circle"></i>
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <button id="openFeeModal" class="btn-create-fee">
                <i class="ph ph-currency-dollar"></i> Set Create Fee
            </button>
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
                            $approval_status = $row['admin_status'];
                            $statusClass = match ($row['status']) {
                                'upcoming' => 'status-upcoming',
                                'ongoing' => 'status-ongoing',
                                'completed' => 'status-completed',
                                default => 'status-default'
                            };
                            $approvalClass = match ($approval_status) {
                                'approved' => 'approval-success',
                                'rejected' => 'approval-danger',
                                'pending' => 'approval-pending',
                                default => 'approval-pending'
                            };
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

                        <?php if (empty($tournaments)): ?>
                            <tr>
                                <td colspan="9" class="text-center">No tournaments found matching your search.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<div id="feeModal" class="custom-modal-overlay">
    <div class="custom-modal-box">
        <span class="close-modal">&times;</span>
        <div class="modal-header">
            <p>Set the cost required to create a tournament.</p>
        </div>

        <div class="current-fee-display">
            <span class="label">Original Fee</span>
            <span class="amount">$<?php echo number_format($current_fee, 2); ?></span>
        </div>

        <form method="POST" action="tournaments.php">
            <div class="input-group">
                <label>New Fee Amount ($)</label>
                <input type="number" name="new_fee" step="0.01" min="0" placeholder="Enter new fee" required>
            </div>
            <button type="submit" name="update_fee_btn" class="btn-save-fee">Update</button>
        </form>
    </div>
</div>

<script>
    

    document.addEventListener('DOMContentLoaded', function() {
        const modal = document.getElementById("feeModal");
        const btn = document.getElementById("openFeeModal");
        const closeBtn = document.querySelector(".close-modal");

        btn.onclick = () => modal.style.display = "flex";
        closeBtn.onclick = () => modal.style.display = "none";
        window.onclick = (event) => {
            if (event.target == modal) modal.style.display = "none";
        }
    });
</script>