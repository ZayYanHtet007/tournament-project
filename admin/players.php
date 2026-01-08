<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';


$sql = "SELECT * FROM users WHERE is_organizer = 0 ORDER BY user_id ASC;";
$result = mysqli_query($conn, $sql);

?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Tournament Management</h2>
            <p>View and manage all active and upcoming tournaments.</p>
        </div>

        <div class="search-wrapper">
            <i class="ph ph-magnifying-glass"></i>
            <input type="text" placeholder="Search tournament, games or IDs...">
        </div>


        <div class="tournament-card-wrapper">
            <div class="table-responsive">
                <table class="table table-hover align-middle custom-tournament-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>PlayerName</th>
                        </tr>
                    </thead>
                    <tbody>

                        <?php while ($row = mysqli_fetch_assoc($result)):
                        ?>
                            <tr onclick="window.location='playersDetail.php?id=<?= $row['user_id'] ?>'">
                                <td><span class="id-badge">#<?= $row['user_id'] ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['username']) ?></td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>