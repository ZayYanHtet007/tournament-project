<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';

$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) {
    $page = 1;
}
$offset = ($page - 1) * $limit;

$countSql = "SELECT COUNT(*) AS total FROM messages";
$countResult = $conn->query($countSql);
$totalRows = ($countResult && $countResult->num_rows > 0) ? (int)$countResult->fetch_assoc()['total'] : 0;
$totalPages = (int)ceil($totalRows / $limit);
if ($totalPages < 1) {
    $totalPages = 1;
}
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $limit;
}

$sql = "SELECT * FROM messages ORDER BY created_at DESC LIMIT $limit OFFSET $offset";
$result = $conn->query($sql);
?>





<div class="main-content">
    <div class="main-content-container">

        <div class="tournament-header-section">
            <h2>Tournament messages</h2>
        </div>

        <div class="glass-card">
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Sender</th>
                            <th>Email</th>
                            <th>Message Content</th>
                            <th>Timestamp</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        if ($result && $result->num_rows > 0) {
                            while ($row = $result->fetch_assoc()) {
                                // Profile image မရှိရင် default ပုံပြမယ်

                                echo "<tr>";
                                echo "<td>
                            <div class='user-info'>
                                <strong>" . htmlspecialchars($row["name"]) . "</strong>
                            </div>
                          </td>";
                                echo "<td>" . htmlspecialchars($row["email"]) . "</td>";
                                echo "<td class='msg-text'>\"" . htmlspecialchars($row["message"]) . "\"</td>";
                                echo "<td class='date-col'>" . date('M d, Y h:i A', strtotime($row["created_at"])) . "</td>";
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align:center; padding: 40px;'>No messages found in the database.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="pg-link">PREV</a>
                <?php endif; ?>

                <?php
                $startLoop = max(1, $page - 2);
                $endLoop = min($totalPages, $page + 2);
                for ($i = $startLoop; $i <= $endLoop; $i++):
                ?>
                    <a href="?page=<?= $i ?>" class="pg-link <?= ($i === $page) ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="pg-link">NEXT</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>


    </div>
</div>
