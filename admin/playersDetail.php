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

// Player Data
$user_sql = "SELECT * FROM users WHERE user_id = $user_id";
$user_result = mysqli_query($conn, $user_sql);
$player = mysqli_fetch_assoc($user_result);

if (!$player) {
    die("Player not found.");
}

$teams = [];
$tournaments = [];


$teams_sql = "
    SELECT DISTINCT
        t.team_id,
        t.team_name,
        t.logo,
        t.players,
        u.username AS leader_name,
        g.name AS game_name
    FROM team_members tm
    INNER JOIN teams t ON tm.team_id = t.team_id
    LEFT JOIN users u ON t.leader_id = u.user_id
    LEFT JOIN tournament_teams tt ON t.team_id = tt.team_id
    LEFT JOIN tournaments tr ON tt.tournament_id = tr.tournament_id
    LEFT JOIN games g ON tr.game_id = g.game_id
    WHERE tm.user_id = $user_id
";

$teams_res = mysqli_query($conn, $teams_sql);
while ($row = mysqli_fetch_assoc($teams_res)) {
    $teams[] = $row;
}

// 2. Tournaments Query
$tournaments_sql = "SELECT tr.title AS name, g.name AS game,tr.status FROM team_members tm
                    INNER JOIN teams t ON tm.team_id = t.team_id
                    INNER JOIN tournament_teams tt ON t.team_id = tt.team_id
                    INNER JOIN tournaments tr ON tt.tournament_id = tr.tournament_id
                    LEFT JOIN games g ON tr.game_id = g.game_id
                    WHERE tm.user_id = $user_id
                    GROUP BY tr.tournament_id";
$tournaments_res = mysqli_query($conn, $tournaments_sql);
while ($row = mysqli_fetch_assoc($tournaments_res)) {
    $tournaments[] = $row;
}
?>


<div class="container py-4">
    <div class="section-container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold mb-1">Player Management</h1>
                <p class="small">Manage details for <b><?= htmlspecialchars($player['username']) ?></b></p>
            </div>

            <a href="players.php" class="btn-custom btn-back">
                ← Back 
            </a>

        </div>


        <div class="glass-card">
            <div class="player-details-grid">
                <div class="detail-group">
                    <label>PLAYER ID</label>
                    <div class="detail-value">#<?= $player['user_id'] ?></div>
                </div>

                <div class="detail-group">
                    <label>PLAYER NAME</label>
                    <div class="detail-value"><?= htmlspecialchars($player['username']) ?></div>
                </div>
            </div>
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
        <div class="section-container">
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
                                    <p class=" x-small mb-1" style="font-size: 0.7rem;">GAME TITLE</p>
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