<?php
require_once __DIR__ . '/../database/dbConfig.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: AdminLogin.php');
    exit;
}

function pm_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pm_bind_dyn(mysqli_stmt $stmt, string $types, array &$params): void
{
    if ($types === '' || empty($params)) {
        return;
    }

    $bind = [$types];
    foreach ($params as $index => &$value) {
        $bind[] = &$value;
    }
    call_user_func_array([$stmt, 'bind_param'], $bind);
}

function pm_build_query(array $params): string
{
    $clean = [];
    foreach ($params as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $clean[$key] = $value;
    }
    return http_build_query($clean);
}

function pm_team_status_key(?string $status): string
{
    $normalized = strtolower(trim((string)$status));
    if ($normalized === 'disban') {
        return 'disbanded';
    }
    if ($normalized === 'ban') {
        return 'banned';
    }
    if ($normalized === '') {
        return 'unknown';
    }
    return $normalized;
}

function pm_team_status_label(?string $status): string
{
    return strtoupper(pm_team_status_key($status));
}

function pm_team_status_class(?string $status): string
{
    $normalized = pm_team_status_key($status);
    if ($normalized === 'active') {
        return 'pb-status-active';
    }
    if ($normalized === 'disbanded') {
        return 'pb-status-disbanded';
    }
    if ($normalized === 'banned') {
        return 'pb-status-banned';
    }
    return 'pb-status-other';
}

function pm_get_games(mysqli $conn): array
{
    $games = [];
    $result = $conn->query("SELECT name FROM games WHERE game_status = 'available' ORDER BY name ASC");
    if (!$result) {
        return $games;
    }

    while ($row = $result->fetch_assoc()) {
        $games[] = (string)($row['name'] ?? '');
    }
    return $games;
}

function pm_get_teams(mysqli $conn, int $limit, int $page, string $search = '', string $gameType = ''): array
{
    $limit = max(1, $limit);
    $page = max(1, $page);
    $offset = ($page - 1) * $limit;
    $search = trim($search);
    $gameType = trim($gameType);

    $where = [];
    $params = [];
    $types = '';

    if ($search !== '') {
        $where[] = '(t.team_name LIKE ? OR t.short_name LIKE ?)';
        $like = '%' . $search . '%';
        $params[] = $like;
        $params[] = $like;
        $types .= 'ss';
    }

    if ($gameType !== '') {
        $where[] = 'g.name = ?';
        $params[] = $gameType;
        $types .= 's';
    }

    $whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

    $countSql = "SELECT COUNT(DISTINCT t.team_id) AS total
                 FROM teams t
                 LEFT JOIN games g ON g.game_id = t.aim_for
                 $whereSql";
    $countStmt = $conn->prepare($countSql);
    if (!$countStmt) {
        return ['rows' => [], 'page' => 1, 'total_pages' => 1];
    }

    if ($types !== '') {
        $countParams = $params;
        pm_bind_dyn($countStmt, $types, $countParams);
    }

    $countStmt->execute();
    $countRow = $countStmt->get_result()->fetch_assoc();
    $countStmt->close();

    $total = (int)($countRow['total'] ?? 0);
    $totalPages = max(1, (int)ceil($total / $limit));
    if ($page > $totalPages) {
        $page = $totalPages;
        $offset = ($page - 1) * $limit;
    }

    $sql = "SELECT
                t.team_id,
                t.team_name,
                t.short_name,
                t.status,
                COALESCE(g.name, 'General') AS game_name,
                COALESCE(u.username, 'N/A') AS leader_name,
                COUNT(DISTINCT tm.user_id) AS player_count
            FROM teams t
            LEFT JOIN games g ON g.game_id = t.aim_for
            LEFT JOIN users u ON u.user_id = t.leader_id
            LEFT JOIN team_members tm ON tm.team_id = t.team_id
            $whereSql
            GROUP BY t.team_id, t.team_name, t.short_name, t.status, g.name, u.username
            ORDER BY t.team_id DESC
            LIMIT ? OFFSET ?";
    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return ['rows' => [], 'page' => 1, 'total_pages' => 1];
    }

    $queryParams = $params;
    $queryParams[] = $limit;
    $queryParams[] = $offset;
    pm_bind_dyn($stmt, $types . 'ii', $queryParams);

    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return [
        'rows' => $rows,
        'page' => $page,
        'total_pages' => $totalPages,
    ];
}

function pm_get_team_with_players(mysqli $conn, int $teamId): array
{
    $teamStmt = $conn->prepare("SELECT
                                    t.team_id,
                                    t.team_name,
                                    t.short_name,
                                    t.status,
                                    COALESCE(g.name, 'General') AS game_name,
                                    COALESCE(u.username, 'N/A') AS leader_name
                                FROM teams t
                                LEFT JOIN games g ON g.game_id = t.aim_for
                                LEFT JOIN users u ON u.user_id = t.leader_id
                                WHERE t.team_id = ?
                                LIMIT 1");
    if (!$teamStmt) {
        return ['team' => null, 'players' => []];
    }
    $teamStmt->bind_param('i', $teamId);
    $teamStmt->execute();
    $team = $teamStmt->get_result()->fetch_assoc();
    $teamStmt->close();

    if (!$team) {
        return ['team' => null, 'players' => []];
    }

    $playerSql = "SELECT p.user_id, p.username, p.email, p.role, p.is_banned
                  FROM (
                      SELECT
                          u.user_id,
                          u.username,
                          u.email,
                          tm.role,
                          u.is_banned,
                          CASE WHEN tm.role = 'leader' THEN 1 ELSE 0 END AS role_rank
                      FROM team_members tm
                      JOIN users u ON u.user_id = tm.user_id
                      WHERE tm.team_id = ?
                        AND u.is_organizer = 0

                      UNION ALL

                      SELECT
                          u.user_id,
                          u.username,
                          u.email,
                          'leader' AS role,
                          u.is_banned,
                          1 AS role_rank
                      FROM teams t
                      JOIN users u ON u.user_id = t.leader_id
                      WHERE t.team_id = ?
                        AND u.is_organizer = 0
                        AND NOT EXISTS (
                            SELECT 1
                            FROM team_members tm2
                            WHERE tm2.team_id = t.team_id
                              AND tm2.user_id = t.leader_id
                        )
                  ) p
                  ORDER BY p.role_rank DESC,
                           CASE p.role
                               WHEN 'leader' THEN 1
                               WHEN 'coach' THEN 2
                               WHEN 'member' THEN 3
                               WHEN 'sub' THEN 4
                               ELSE 5
                           END,
                           p.username ASC";
    $playerStmt = $conn->prepare($playerSql);
    if (!$playerStmt) {
        return ['team' => $team, 'players' => []];
    }

    $playerStmt->bind_param('ii', $teamId, $teamId);
    $playerStmt->execute();
    $players = $playerStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $playerStmt->close();

    return ['team' => $team, 'players' => $players];
}

$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$search = trim((string)($_GET['search'] ?? ''));
$gameType = trim((string)($_GET['game_type'] ?? ''));
$games = pm_get_games($conn);

$listContext = [
    'search' => $search,
    'game_type' => $gameType,
    'page' => $page > 1 ? $page : null,
];

$teamsData = ['rows' => [], 'page' => 1, 'total_pages' => 1];
$teamPayload = ['team' => null, 'players' => []];

if ($teamId > 0) {
    $teamPayload = pm_get_team_with_players($conn, $teamId);
} else {
    $teamsData = pm_get_teams($conn, 9, $page, $search, $gameType);
    $page = (int)$teamsData['page'];
    $listContext['page'] = $page > 1 ? $page : null;
}

$backToTeamsQuery = pm_build_query($listContext);
$backToTeamsUrl = 'players.php' . ($backToTeamsQuery !== '' ? '?' . $backToTeamsQuery : '');

require_once __DIR__ . '/sidebar.php';
?>

<div class="main-content">
    <div class="main-content-container players-browser">
        
            <header class="pb-hero">
                <div>
                    <p class="pb-eyebrow">Player Management</p>
                    <?php if ($teamId > 0 && $teamPayload['team']): ?>
                        <h1><?= pm_h((string)$teamPayload['team']['team_name']) ?> Players</h1>
                        <p>Choose a player to open full profile, joined teams, and tournaments.</p>
                    <?php elseif ($teamId > 0): ?>
                        <h1>Team Not Found</h1>
                        <p>This team does not exist or has been removed.</p>
                    <?php else: ?>
                        <h1>Teams</h1>
                        <p>Select a team first, then pick a player from the next page.</p>
                    <?php endif; ?>
                </div>

                <?php if ($teamId > 0): ?>
                    <a class="pb-link-btn" href="<?= pm_h($backToTeamsUrl) ?>">
                        <i class="fa-solid fa-arrow-left"></i>
                        Back To Teams
                    </a>
                <?php endif; ?>
            </header>

            <?php if ($teamId <= 0): ?>
                <form class="pb-filter" method="GET" action="players.php">
                    <label class="pb-input-wrap" for="pb-search">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input id="pb-search" type="text" name="search" value="<?= pm_h($search) ?>" placeholder="Search team name...">
                    </label>

                    <label class="pb-select-wrap" for="pb-game">
                        <i class="fa-solid fa-gamepad"></i>
                        <select id="pb-game" name="game_type">
                            <option value="">All Games</option>
                            <?php foreach ($games as $game): ?>
                                <option value="<?= pm_h($game) ?>" <?= $gameType === $game ? 'selected' : '' ?>>
                                    <?= pm_h($game) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>

                    <button type="submit" class="pb-submit-btn">
                        <i class="fa-solid fa-filter"></i>
                        Filter
                    </button>

                    <?php if ($search !== '' || $gameType !== ''): ?>
                        <a class="pb-reset-btn" href="players.php">Reset</a>
                    <?php endif; ?>
                </form>

                <?php if (!empty($teamsData['rows'])): ?>
                    <div class="pb-grid pb-grid-teams">
                        <?php foreach ($teamsData['rows'] as $team): ?>
                            <?php
                            $teamQuery = pm_build_query(array_merge(
                                $listContext,
                                ['team_id' => (int)$team['team_id']]
                            ));
                            $teamUrl = 'players.php?' . $teamQuery;
                            $teamStatusRaw = (string)($team['status'] ?? 'unknown');
                            $teamStatusClass = pm_team_status_class($teamStatusRaw);
                            $teamStatusLabel = pm_team_status_label($teamStatusRaw);
                            ?>
                            <a class="pb-card pb-team-card" href="<?= pm_h($teamUrl) ?>">
                                <div class="pb-card-top">
                                    <span class="pb-tag"><?= pm_h((string)$team['game_name']) ?></span>
                                    <span class="pb-status-pill <?= pm_h($teamStatusClass) ?>"><?= pm_h($teamStatusLabel) ?></span>
                                </div>
                                <h3><?= pm_h((string)$team['team_name']) ?></h3>
                                <p class="pb-subtitle">
                                    <?= pm_h((string)($team['short_name'] ?: 'No short name')) ?>
                                </p>
                                <p class="pb-meta">
                                    Leader: <strong><?= pm_h((string)$team['leader_name']) ?></strong>
                                </p>
                                <p class="pb-meta">
                                    Players: <strong><?= (int)$team['player_count'] ?></strong>
                                </p>
                            </a>
                        <?php endforeach; ?>
                    </div>

                    <?php
                    $totalPages = (int)$teamsData['total_pages'];
                    if ($totalPages > 1):
                        $currentPage = (int)$teamsData['page'];
                        $start = max(1, $currentPage - 2);
                        $end = min($totalPages, $start + 4);
                        if (($end - $start) < 4) {
                            $start = max(1, $end - 4);
                        }
                    ?>
                        <nav class="pb-pagination" aria-label="Teams pagination">
                            <?php if ($currentPage > 1): ?>
                                <?php
                                $prevQuery = pm_build_query(array_merge($listContext, ['page' => $currentPage - 1]));
                                ?>
                                <a href="players.php?<?= pm_h($prevQuery) ?>" class="pb-page-btn">Prev</a>
                            <?php endif; ?>

                            <?php for ($i = $start; $i <= $end; $i++): ?>
                                <?php
                                $pageQuery = pm_build_query(array_merge($listContext, ['page' => $i]));
                                ?>
                                <a href="players.php?<?= pm_h($pageQuery) ?>" class="pb-page-btn <?= $i === $currentPage ? 'is-active' : '' ?>">
                                    <?= $i ?>
                                </a>
                            <?php endfor; ?>

                            <?php if ($currentPage < $totalPages): ?>
                                <?php
                                $nextQuery = pm_build_query(array_merge($listContext, ['page' => $currentPage + 1]));
                                ?>
                                <a href="players.php?<?= pm_h($nextQuery) ?>" class="pb-page-btn">Next</a>
                            <?php endif; ?>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="pb-empty">
                        <h3>No teams found</h3>
                        <p>Try a different keyword or game filter.</p>
                    </div>
                <?php endif; ?>
            <?php else: ?>
                <?php if (!$teamPayload['team']): ?>
                    <div class="pb-empty">
                        <h3>Team not found</h3>
                        <p>Go back and select another team.</p>
                    </div>
                <?php else: ?>
                    <?php
                    $team = $teamPayload['team'];
                    $players = $teamPayload['players'];
                    $teamStatusRaw = (string)($team['status'] ?? 'unknown');
                    $teamStatusClass = pm_team_status_class($teamStatusRaw);
                    $teamStatusLabel = pm_team_status_label($teamStatusRaw);
                    ?>
                    <div class="pb-team-meta">
                        <span><i class="fa-solid fa-shield-halved"></i> <?= pm_h((string)$team['game_name']) ?></span>
                        <span><i class="fa-solid fa-crown"></i> <?= pm_h((string)$team['leader_name']) ?></span>
                        <span><i class="fa-solid fa-users"></i> <?= count($players) ?> Player(s)</span>
                        <span class="pb-status-pill <?= pm_h($teamStatusClass) ?>">
                            <i class="fa-solid fa-circle-info"></i>
                            <?= pm_h($teamStatusLabel) ?>
                        </span>
                    </div>

                    <?php if (!empty($players)): ?>
                        <div class="pb-grid pb-grid-players">
                            <?php foreach ($players as $player): ?>
                                <?php
                                $playerQuery = pm_build_query([
                                    'id' => (int)$player['user_id'],
                                    'team_id' => $teamId,
                                    'search' => $search,
                                    'game_type' => $gameType,
                                    'page' => $page > 1 ? $page : null,
                                ]);
                                $playerUrl = 'playersDetail.php?' . $playerQuery;
                                $isBanned = (int)$player['is_banned'] === 1;
                                ?>
                                <a class="pb-card pb-player-card <?= $isBanned ? 'is-banned' : '' ?>" href="<?= pm_h($playerUrl) ?>">
                                    <div class="pb-player-main">
                                        <div class="pb-avatar">
                                            <?= pm_h(strtoupper(substr((string)$player['username'], 0, 1))) ?>
                                        </div>
                                        <div>
                                            <h3><?= pm_h((string)$player['username']) ?></h3>
                                            <p class="pb-subtitle"><?= pm_h(strtoupper((string)$player['role'])) ?></p>
                                        </div>
                                    </div>
                                    <div class="pb-card-foot">
                                        <span><?= pm_h((string)$player['email']) ?></span>
                                        <?php if ($isBanned): ?>
                                            <span class="pb-danger-pill">BANNED</span>
                                        <?php endif; ?>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pb-empty">
                            <h3>No players in this team</h3>
                            <p>This team does not have member records yet.</p>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
    </div>
</div>
