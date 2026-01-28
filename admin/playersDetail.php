<?php
require_once __DIR__ . '/../database/dbConfig.php';
require_once __DIR__ . '/sidebar.php';


$user_id = isset($_GET['id']) ? intval($_GET['id']) : 0;


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ban_user'])) {
    $ban_id = intval($_POST['user_id']);

    $ban_stmt = $conn->prepare("UPDATE users SET is_banned = 1 WHERE user_id = ?");
    $ban_stmt->bind_param("i", $ban_id);
    $ban_stmt->execute();
    echo "<script>alert('Player has been banned successfully!'); window.location.href='playersDetail.php?id=$ban_id';</script>";
}


$user_sql = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$player = mysqli_fetch_assoc($user_result);


if (!$player) {
    die("Player not found.");
}


$teams = [
    ['team_name' => 'Falcon Esports', 'game_name' => 'Mobile Legends'],
    ['team_name' => 'Yangon Galacticos', 'game_name' => 'Dota 2'],
    ['team_name' => 'Burmese Ghouls', 'game_name' => 'PUBG Mobile'],
    ['team_name' => 'AI Esports', 'game_name' => 'Valorant'],
    ['team_name' => 'Team Flash', 'game_name' => 'Arena of Valor'],
    ['team_name' => 'Mythic Crew', 'game_name' => 'Free Fire'],
    ['team_name' => 'Liquid BG', 'game_name' => 'Mobile Legends'],
    ['team_name' => 'Yangon Galacticos', 'game_name' => 'PUBG Mobile'],
    ['team_name' => 'Team Flash', 'game_name' => 'Arena of Valor'],
    ['team_name' => 'Mythic Crew', 'game_name' => 'Free Fire'],
    ['team_name' => 'Liquid BG', 'game_name' => 'Mobile Legends'],
    ['team_name' => 'Old School', 'game_name' => 'Dota 2']
];


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
    <div class="section-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Player Management</h1>
                <p class="text-muted small">Manage details for <b><?= htmlspecialchars($player['username']) ?></b></p>
            </div>

            <a href="players.php" class="btn btn-secondary" style="width: auto; ">
                Back
            </a>

        </div>

        <div class="player-info-box shadow-sm mb-4">
            <table class="table align-middle m-0">
                <thead class="table-light">
                    <tr>
                        <th>Player ID</th>
                        <th>Player Name</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td><span class="id-badge">#<?= $player['user_id'] ?></span></td>
                        <td class="fw-bold"><?= htmlspecialchars($player['username']) ?></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <form method="POST" onsubmit="return confirm('Ban this player?');">
            <input type="hidden" name="user_id" value="<?= $player['user_id'] ?>">
            <button type="submit" name="ban_user" class="btn-ban">
                <i class="bi bi-slash-circle"></i> BAN PLAYER
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

        <div class="section-container mt-4">
            <h3 class="section-title">Joined Teams</h3>

            <div class="scroll-grid-container">
                <div class="row g-3">
                    <?php if (!empty($teams)): ?>
                        <?php foreach ($teams as $team): ?>
                            <div class="col-md-4">
                                <div class="item-card">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="fw-bold mb-0"><?= htmlspecialchars($team['team_name']) ?></h6>
                                        <i class="bi bi-people text-muted"></i>
                                    </div>
                                    <p class="text-muted x-small mb-1" style="font-size: 0.7rem;">GAME TITLE</p>
                                    <p class="fw-bold text-primary mb-0"><?= htmlspecialchars($team['game_name']) ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center py-4 text-muted">No teams found.</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="section-container">
            <h3 class="section-title">Joined Tournaments</h3>

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
                                <div class="mt-2 pt-2 border-top d-flex justify-content-between small">
                                    <span class="text-muted">Game</span>
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


