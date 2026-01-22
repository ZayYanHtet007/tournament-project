<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$sql = "SELECT * FROM users WHERE is_organizer = 1  AND organizer_status = 'approved' AND(  username LIKE '%$searchTerm%' OR email LIKE  '%$searchTerm%' OR user_id LIKE '%$searchTerm') ORDER BY user_id ASC;";
$result = mysqli_query($conn, $sql);

?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Tournament Management</h2>
            <p>View and manage all players.</p>
        </div>

        <div class="search-wrapper">
            <form method="GET" action="" class="search-form">
                <button type="submit" class="search-btn">
                    <i class="ph ph-magnifying-glass"></i>
                </button>

                <input type="text" name="search" placeholder="Search tournament, games or IDs..."
                    value="<?= htmlspecialchars($searchTerm) ?>">

                <?php if (!empty($searchTerm)): ?>
                    <a href="organizers.php" class="reset-btn">
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
                            <th>Organizer ID</th>
                            <th>Organizer Name</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)):
                        ?>
                            <tr onclick="window.location='organizerDetail.php?id=<?= $row['user_id'] ?>'">
                                <td><span class="id-badge">#<?= $row['user_id'] ?></span></td>
                                <td class="fw-bold text-dark"><?= htmlspecialchars($row['username']) ?></td>
                                <td class="fw-bold text"><?= htmlspecialchars($row['email']) ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($result) == 0): ?>
                            <tr>
                                <td colspan="9" class="text-center">No player found matching your search.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>