<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';


$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
    $ban_id = intval($_POST['user_id']);

    $ban_stmt = $conn->prepare("UPDATE users SET is_banned = 1 WHERE user_id = ?");
    $ban_stmt->bind_param("i", $ban_id);
    $ban_stmt->execute();
    echo "<script>alert('Organizer has been banned successfully!'); window.location.href='organizerDetail.php?id=$ban_id';</script>";
}


$user_sql = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$organizer = mysqli_fetch_assoc($user_result);


if (!$organizer) {
    die("Organizer not found.");
}


$tournaments = [
    ['name' => 'Summer Tournament', 'game' => 'Mobile Legends', 'status' => 'UPCOMING'],
    ['name' => 'MPL Season 10', 'game' => 'Mobile Legends', 'status' => 'ONGOING'],
    ['name' => 'Pubg National', 'game' => 'PUBG Mobile', 'status' => 'COMPLETED'],
    ['name' => 'Summer Tournament', 'game' => 'Mobile Legends', 'status' => 'UPCOMING'],
    ['name' => 'MPL Season 10', 'game' => 'Mobile Legends', 'status' => 'ONGOING'],
    ['name' => 'Pubg National', 'game' => 'PUBG Mobile', 'status' => 'COMPLETED'],
    ['name' => 'Pubg National', 'game' => 'PUBG Mobile', 'status' => 'COMPLETED'],
    ['name' => 'Summer Tournament', 'game' => 'Mobile Legends', 'status' => 'UPCOMING'],
    ['name' => 'MPL Season 10', 'game' => 'Mobile Legends', 'status' => 'ONGOING'],
    ['name' => 'Pubg National', 'game' => 'PUBG Mobile', 'status' => 'COMPLETED']
];

?>


<div class="container py-4">
    <div class="glass-card">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Organizer Management</h1>
                <p class="small">Manage details for <b><?= htmlspecialchars($organizer['username']) ?></b></p>
            </div>
            <a href="organizers.php" class="btn btn-secondary" style="width: auto;">
                Back
            </a>
        </div>

        <div class="player-info-box shadow-sm mb-4">
            <div class="glass-card">
                <div class="player-details-grid">
                    <div class="detail-group">
                        <label>Organizer ID</label>
                        <div class="detail-value">#<?= $organizer['user_id'] ?></div>
                    </div>

                    <div class="detail-group">
                        <label>PLAYER NAME</label>
                        <div class="detail-value"><?= htmlspecialchars($organizer['username']) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <form method="POST" onsubmit="return confirm('Ban this Organizer?');" class="mb-4">
            <input type="hidden" name="user_id" value="<?= $organizer['user_id'] ?>">
            <button type="submit" name="ban_user" class="btn-ban-organizer">
                <i class="bi bi-slash-circle"></i> BAN Organizer
            </button>
            <?php
            if (isset($_SESSION['user_id'])) {
                $uid = $_SESSION['user_id'];
                $check = mysqli_query($conn, "SELECT is_banned FROM users WHERE user_id = $uid");
                $data = mysqli_fetch_assoc($check);

                if ($data && $data['is_banned'] == 1) {
                    session_destroy();
                    header("Location: login.php?error=banned");
                    exit;
                }
            }
            ?>
        </form>

        <div class="section-container">
            <h3 class="section-title">Created Tournaments</h3>

            <div class="scroll-grid-container">
                <div class="row g-3">
                    <?php foreach ($tournaments as $tour): ?>
                        <?php
                        $status = strtolower($tour['status']);
                        $badge = ($status == 'upcoming') ? 'status-upcoming' : (($status == 'ongoing') ? 'status-ongoing' : 'status-completed');
                        ?>
                        <div class="col-md-4">
                            <div class="item-card">
                                <span class="custom-badge <?= $badge ?> d-inline-block mb-3"><?= strtoupper($status) ?></span>
                                <h6 class="fw-bold"><?= htmlspecialchars($tour['name']) ?></h6>
                                <div class="mt-2 pt-2  d-flex justify-content-between small">
                                    <span>Game</span>
                                    <span class="fw-bold"><?= htmlspecialchars($tour['game']) ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>