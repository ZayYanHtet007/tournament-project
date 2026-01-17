<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';


$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

$sql = "SELECT * FROM tournaments WHERE (title LIKE '%$searchTerm%' OR game_name LIKE '%$searchTerm%' OR tournament_id LIKE '%$searchTerm%') ORDER BY CASE WHEN status = 'pending' THEN 1 ELSE 2 END ASC, tournament_id ASC;";

$result = mysqli_query($conn, $sql);

$tournaments = [];
while ($row = mysqli_fetch_assoc($result)) {
    $row['calculated_approval'] = ($row['admin_status']) ;
    $tournaments[] = $row;
}

//Sort
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

        <!--Search bar-->
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

        <div class="tournament-card-wrapper">
            <div class="table-responsive">
                <table class="table table-hover align-middle custom-tournament-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Tournament Title</th>
                            <th>Game</th>
                            <th>Format</th>
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
                                <td><span class="id-badge">#<?= $row['tournament_id'] ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['title']) ?></td>
                                <td><?= htmlspecialchars($row['game_name']) ?></td>
                                <td><span class="format-tag"><?= str_replace('_', ' ', htmlspecialchars($row['format'])) ?></span></td>
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