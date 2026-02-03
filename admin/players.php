<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

// 1. Define Search Term
$searchTerm = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';

// 2. Pagination Setup (Must come before the main SQL query)
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// 3. Get Total Count for Pagination UI
$count_sql = "SELECT COUNT(*) AS total FROM users 
              WHERE is_organizer = 0 
              AND organizer_status = 'pending' 
              AND (username LIKE '%$searchTerm%' 
              OR email LIKE '%$searchTerm%' 
              OR user_id LIKE '%$searchTerm%')";
$count_result = mysqli_query($conn, $count_sql);
$total_rows = mysqli_fetch_assoc($count_result)['total'];
$total_pages = ceil($total_rows / $limit);

// 4. Fetch Paginated Data (Now $limit and $offset are defined)
$sql = "SELECT * FROM users 
        WHERE is_organizer = 0  
        AND organizer_status = 'pending' 
        AND (username LIKE '%$searchTerm%' 
        OR email LIKE '%$searchTerm%' 
        OR user_id LIKE '%$searchTerm%') 
        ORDER BY user_id ASC 
        LIMIT $limit OFFSET $offset";
$result = mysqli_query($conn, $sql);
?>

<div class="main-content">
    <div class="main-content-container">
        <div class="tournament-header-section">
            <h2>Player Management</h2> <p>View and manage all pending players.</p>
        </div>

        <div class="search-wrapper">
            <form method="GET" action="" class="search-form">
                <button type="submit" class="search-btn">
                    <i class="ph ph-magnifying-glass"></i>
                </button>
                <input type="text" name="search" placeholder="Search name, email or IDs..."
                    value="<?= htmlspecialchars($searchTerm) ?>">
                <?php if (!empty($searchTerm)): ?>
                    <a href="players.php" class="reset-btn">
                        <i class="ph ph-x-circle"></i>
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Player ID</th>
                            <th>Player Name</th>
                            <th>Email</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <tr onclick="window.location='playersDetail.php?id=<?= $row['user_id'] ?>'" style="cursor:pointer;">
                                <td><span>#<?= $row['user_id'] ?></span></td>
                                <td><?= htmlspecialchars($row['username']) ?></td>
                                <td class="fw-bold text"><?= htmlspecialchars($row['email']) ?></td>
                            </tr>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($result) == 0): ?>
                            <tr>
                                <td colspan="3" class="text-center">No player found matching your search.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pagination">
            <?php if ($page > 1): ?>
                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($searchTerm) ?>" class="pg-link"><i class="fas fa-chevron-left"></i> PREV</a>
            <?php endif; ?>

            <?php
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