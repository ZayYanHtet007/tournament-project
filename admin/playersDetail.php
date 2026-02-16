<?php
require_once __DIR__ . '/../database/dbConfig.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['admin_id'])) {
    header('Location: AdminLogin.php');
    exit;
}

function pd_h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function pd_build_query(array $params): string
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

function pd_status_class(string $status): string
{
    $normalized = strtolower(trim($status));
    if ($normalized === 'upcoming') {
        return 'pdp-status-upcoming';
    }
    if ($normalized === 'ongoing') {
        return 'pdp-status-ongoing';
    }
    if ($normalized === 'completed') {
        return 'pdp-status-completed';
    }
    return 'pdp-status-other';
}

function pd_team_status_key(string $status): string
{
    $normalized = strtolower(trim($status));
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

function pd_team_status_class(string $status): string
{
    $normalized = pd_team_status_key($status);
    if ($normalized === 'active') {
        return 'pdp-team-status-active';
    }
    if ($normalized === 'disbanded') {
        return 'pdp-team-status-disbanded';
    }
    if ($normalized === 'banned') {
        return 'pdp-team-status-banned';
    }
    return 'pdp-team-status-other';
}

function pd_team_status_label(string $status): string
{
    return strtoupper(pd_team_status_key($status));
}

function pd_get_context_team(mysqli $conn, int $teamId, int $userId): ?array
{
    if ($teamId <= 0 || $userId <= 0) {
        return null;
    }

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
                                  AND (
                                      t.leader_id = ?
                                      OR EXISTS (
                                          SELECT 1
                                          FROM team_members tm
                                          WHERE tm.team_id = t.team_id
                                            AND tm.user_id = ?
                                      )
                                  )
                                LIMIT 1");
    if (!$teamStmt) {
        return null;
    }

    $teamStmt->bind_param('iii', $teamId, $userId, $userId);
    $teamStmt->execute();
    $team = $teamStmt->get_result()->fetch_assoc();
    $teamStmt->close();

    return $team ?: null;
}

function pd_format_date(?string $date): string
{
    if (!$date) {
        return '-';
    }

    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return $date;
    }

    return date('M d, Y', $timestamp);
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$teamId = isset($_GET['team_id']) ? (int)$_GET['team_id'] : 0;
$search = trim((string)($_GET['search'] ?? ''));
$gameType = trim((string)($_GET['game_type'] ?? ''));
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;

$listContext = [
    'search' => $search,
    'game_type' => $gameType,
    'page' => $page > 1 ? $page : null,
];

$teamsUrl = 'players.php';
$teamsQuery = pd_build_query($listContext);
if ($teamsQuery !== '') {
    $teamsUrl .= '?' . $teamsQuery;
}

$teamPlayersUrl = $teamsUrl;
if ($teamId > 0) {
    $teamPlayersQuery = pd_build_query(array_merge($listContext, ['team_id' => $teamId]));
    $teamPlayersUrl = 'players.php?' . $teamPlayersQuery;
}

$selfParams = [
    'id' => $userId > 0 ? $userId : null,
    'team_id' => $teamId > 0 ? $teamId : null,
    'search' => $search,
    'game_type' => $gameType,
    'page' => $page > 1 ? $page : null,
];
$selfUrl = 'playersDetail.php';
$selfQuery = pd_build_query($selfParams);
if ($selfQuery !== '') {
    $selfUrl .= '?' . $selfQuery;
}

$contextTeam = pd_get_context_team($conn, $teamId, $userId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $notice = '';
    $postAction = strtolower(trim((string)($_POST['action'] ?? '')));
    $isBanUserRequest = $postAction === 'ban_user' || isset($_POST['ban_user']) || isset($_POST['user_id']);
    $isBanTeamRequest = $postAction === 'ban_team' || isset($_POST['ban_team']);

    if ($isBanUserRequest) {
        $banUserId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
        $notice = 'ban-failed';

        if ($banUserId > 0 && $banUserId === $userId) {
            $checkStmt = $conn->prepare("SELECT is_banned FROM users WHERE user_id = ? AND is_organizer = 0 LIMIT 1");
            if ($checkStmt) {
                $checkStmt->bind_param('i', $banUserId);
                $checkStmt->execute();
                $existing = $checkStmt->get_result()->fetch_assoc();
                $checkStmt->close();

                if ($existing) {
                    if ((int)$existing['is_banned'] === 1) {
                        $notice = 'already-banned';
                    } else {
                        $banStmt = $conn->prepare("UPDATE users SET is_banned = 1 WHERE user_id = ? AND is_organizer = 0");
                        if ($banStmt) {
                            $banStmt->bind_param('i', $banUserId);
                            $ok = $banStmt->execute();
                            $affected = $banStmt->affected_rows;
                            $banStmt->close();
                            if ($ok && $affected > 0) {
                                $notice = 'banned';
                            }
                        }
                    }
                }
            }
        }
    } elseif ($isBanTeamRequest) {
        $banTeamId = isset($_POST['team_id']) ? (int)$_POST['team_id'] : 0;
        $notice = 'team-ban-failed';

        if ($banTeamId > 0 && $banTeamId === $teamId && $contextTeam) {
            $teamStatus = pd_team_status_key((string)$contextTeam['status']);
            if ($teamStatus === 'banned') {
                $notice = 'team-already-banned';
            } else {
                $conn->begin_transaction();
                try {
                    $teamUpdated = false;
                    foreach (['banned', 'ban'] as $banValue) {
                        $teamStmt = $conn->prepare("UPDATE teams SET status = ? WHERE team_id = ?");
                        if (!$teamStmt) {
                            throw new RuntimeException('Unable to prepare team update query.');
                        }

                        $teamStmt->bind_param('si', $banValue, $banTeamId);
                        $teamOk = $teamStmt->execute();
                        $teamError = (string)$teamStmt->error;
                        $teamAffected = $teamStmt->affected_rows;
                        $teamStmt->close();

                        if ($teamOk && $teamAffected > 0) {
                            $teamUpdated = true;
                            break;
                        }

                        // Try legacy enum token only when current value is not accepted.
                        $isEnumError = stripos($teamError, 'truncated') !== false
                            || stripos($teamError, 'incorrect') !== false
                            || stripos($teamError, 'enum') !== false;
                        if (!$isEnumError) {
                            break;
                        }
                    }

                    if (!$teamUpdated) {
                        throw new RuntimeException('Team status update failed.');
                    }

                    $memberStmt = $conn->prepare("UPDATE users
                                                  SET is_banned = 1
                                                  WHERE is_organizer = 0
                                                    AND user_id IN (
                                                        SELECT tm.user_id
                                                        FROM team_members tm
                                                        WHERE tm.team_id = ?

                                                        UNION

                                                        SELECT t.leader_id
                                                        FROM teams t
                                                        WHERE t.team_id = ?
                                                    )");
                    if (!$memberStmt) {
                        throw new RuntimeException('Unable to prepare member update query.');
                    }
                    $memberStmt->bind_param('ii', $banTeamId, $banTeamId);
                    $memberStmt->execute();
                    $memberStmt->close();

                    $conn->commit();
                    $notice = 'team-banned';
                } catch (Throwable $error) {
                    $conn->rollback();
                }
            }
        } else {
            $notice = 'team-not-found';
        }
    }

    if ($notice !== '') {
        $redirectParams = array_merge($selfParams, ['notice' => $notice]);
        $redirectQuery = pd_build_query($redirectParams);
        header('Location: playersDetail.php?' . $redirectQuery);
        exit;
    }
}

$player = null;
if ($userId > 0) {
    $playerStmt = $conn->prepare("SELECT user_id, username, email, is_banned
                                  FROM users
                                  WHERE user_id = ? AND is_organizer = 0
                                  LIMIT 1");
    if ($playerStmt) {
        $playerStmt->bind_param('i', $userId);
        $playerStmt->execute();
        $player = $playerStmt->get_result()->fetch_assoc();
        $playerStmt->close();
    }
}

$joinedTeams = [];
$joinedTournaments = [];

if ($player) {
    $teamsStmt = $conn->prepare("SELECT
                                    p.team_id,
                                    p.team_name,
                                    p.short_name,
                                    p.member_role,
                                    p.game_name,
                                    p.leader_name,
                                    p.team_status
                                 FROM (
                                     SELECT
                                         t.team_id,
                                         t.team_name,
                                         t.short_name,
                                         tm.role AS member_role,
                                         COALESCE(g.name, 'General') AS game_name,
                                         COALESCE(u.username, 'N/A') AS leader_name,
                                         t.status AS team_status
                                     FROM team_members tm
                                     JOIN teams t ON t.team_id = tm.team_id
                                     LEFT JOIN games g ON g.game_id = t.aim_for
                                     LEFT JOIN users u ON u.user_id = t.leader_id
                                     WHERE tm.user_id = ?

                                     UNION ALL

                                     SELECT
                                         t.team_id,
                                         t.team_name,
                                         t.short_name,
                                         'leader' AS member_role,
                                         COALESCE(g.name, 'General') AS game_name,
                                         COALESCE(u.username, 'N/A') AS leader_name,
                                         t.status AS team_status
                                     FROM teams t
                                     LEFT JOIN games g ON g.game_id = t.aim_for
                                     LEFT JOIN users u ON u.user_id = t.leader_id
                                     WHERE t.leader_id = ?
                                       AND NOT EXISTS (
                                           SELECT 1
                                           FROM team_members tm2
                                           WHERE tm2.team_id = t.team_id
                                             AND tm2.user_id = t.leader_id
                                       )
                                 ) p
                                 ORDER BY
                                     CASE p.member_role
                                         WHEN 'leader' THEN 1
                                         WHEN 'coach' THEN 2
                                         WHEN 'member' THEN 3
                                         WHEN 'sub' THEN 4
                                         ELSE 5
                                     END,
                                     p.team_name ASC");

    if ($teamsStmt) {
        $teamsStmt->bind_param('ii', $userId, $userId);
        $teamsStmt->execute();
        $joinedTeams = $teamsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $teamsStmt->close();
    }

    $tournamentStmt = $conn->prepare("SELECT
                                        tr.tournament_id,
                                        tr.title,
                                        tr.status,
                                        tr.start_date,
                                        COALESCE(g.name, 'Unknown') AS game_name,
                                        GROUP_CONCAT(DISTINCT t.team_name ORDER BY t.team_name SEPARATOR ', ') AS team_names
                                      FROM tournament_teams tt
                                      JOIN tournaments tr ON tr.tournament_id = tt.tournament_id
                                      JOIN teams t ON t.team_id = tt.team_id
                                      LEFT JOIN games g ON g.game_id = tr.game_id
                                      JOIN (
                                          SELECT tm.team_id
                                          FROM team_members tm
                                          WHERE tm.user_id = ?

                                          UNION

                                          SELECT t2.team_id
                                          FROM teams t2
                                          WHERE t2.leader_id = ?
                                      ) joined_team_ids ON joined_team_ids.team_id = tt.team_id
                                      GROUP BY tr.tournament_id, tr.title, tr.status, tr.start_date, g.name
                                      ORDER BY tr.start_date DESC, tr.tournament_id DESC");
    if ($tournamentStmt) {
        $tournamentStmt->bind_param('ii', $userId, $userId);
        $tournamentStmt->execute();
        $joinedTournaments = $tournamentStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $tournamentStmt->close();
    }
}

$noticeType = '';
$noticeMessage = '';
$noticeKey = (string)($_GET['notice'] ?? '');
if ($noticeKey === 'banned') {
    $noticeType = 'success';
    $noticeMessage = 'Player account has been banned successfully.';
} elseif ($noticeKey === 'already-banned') {
    $noticeType = 'warning';
    $noticeMessage = 'This player account is already banned.';
} elseif ($noticeKey === 'ban-failed') {
    $noticeType = 'danger';
    $noticeMessage = 'Failed to ban this player account.';
} elseif ($noticeKey === 'team-banned') {
    $noticeType = 'success';
    $noticeMessage = 'Team has been banned and all members are now banned.';
} elseif ($noticeKey === 'team-already-banned') {
    $noticeType = 'warning';
    $noticeMessage = 'This team is already banned.';
} elseif ($noticeKey === 'team-not-found') {
    $noticeType = 'warning';
    $noticeMessage = 'Team not found for this player context.';
} elseif ($noticeKey === 'team-ban-failed') {
    $noticeType = 'danger';
    $noticeMessage = 'Failed to ban this team.';
}

require_once __DIR__ . '/sidebar.php';
?>

<div class="main-content">
    <div class="main-content-container player-detail-page">
        <section class="pdp-shell">
            <header class="pdp-hero">
                <div>
                    <p class="pdp-eyebrow">Player Detail</p>
                    <?php if ($player): ?>
                        <h1><?= pd_h((string)$player['username']) ?></h1>
                        <p>Review this player profile, joined teams, and tournaments.</p>
                    <?php else: ?>
                        <h1>Player Not Found</h1>
                        <p>This player was not found in player records.</p>
                    <?php endif; ?>
                </div>
                <div class="pdp-actions">
                    <?php if ($teamId > 0): ?>
                        <a class="pdp-link-btn" href="<?= pd_h($teamPlayersUrl) ?>">
                            <i class="fa-solid fa-arrow-left"></i>
                            Back To Team Players
                        </a>
                    <?php endif; ?>
                    <a class="pdp-link-btn" href="<?= pd_h($teamsUrl) ?>">
                        <i class="fa-solid fa-layer-group"></i>
                        Back To Teams
                    </a>
                    <?php if ($contextTeam): ?>
                        <?php
                        $teamContextStatus = (string)($contextTeam['status'] ?? 'unknown');
                        $teamContextClass = pd_team_status_class($teamContextStatus);
                        $teamContextLabel = pd_team_status_label($teamContextStatus);
                        $teamContextIsBanned = pd_team_status_key($teamContextStatus) === 'banned';
                        ?>
                        <div class="pdp-team-tools">
                            <span class="pdp-team-context">
                                <i class="fa-solid fa-users"></i>
                                <?= pd_h((string)$contextTeam['team_name']) ?>
                                <span class="pdp-pill <?= pd_h($teamContextClass) ?>">
                                    <?= pd_h($teamContextLabel) ?>
                                </span>
                            </span>
                            <form method="POST" action="<?= pd_h($selfUrl) ?>" id="pdpTeamBanForm" class="pdp-inline-form">
                                <input type="hidden" name="action" value="ban_team">
                                <input type="hidden" name="team_id" value="<?= (int)$contextTeam['team_id'] ?>">
                                <button type="submit" name="ban_team" class="pdp-ban-btn" <?= $teamContextIsBanned ? 'disabled' : '' ?>>
                                    <i class="fa-solid fa-ban"></i>
                                    <?= $teamContextIsBanned ? 'Team Already Banned' : 'Ban Team' ?>
                                </button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>
            </header>

            <?php if ($noticeMessage !== ''): ?>
                <div class="pdp-alert <?= pd_h($noticeType) ?>">
                    <?= pd_h($noticeMessage) ?>
                </div>
            <?php endif; ?>

            <?php if (!$player): ?>
                <div class="pdp-empty">
                    <h3>No data available</h3>
                    <p>Select another player from the team list.</p>
                </div>
            <?php else: ?>
                <?php $isBanned = (int)$player['is_banned'] === 1; ?>
                <article class="pdp-summary">
                    <div class="pdp-summary-main">
                        <div class="pdp-avatar">
                            <?= pd_h(strtoupper(substr((string)$player['username'], 0, 1))) ?>
                        </div>
                        <div>
                            <h2><?= pd_h((string)$player['username']) ?></h2>
                            <p><?= pd_h((string)$player['email']) ?></p>
                            <span class="pdp-state <?= $isBanned ? 'is-banned' : 'is-active' ?>">
                                <?= $isBanned ? 'BANNED' : 'ACTIVE' ?>
                            </span>
                        </div>
                    </div>

                    <form method="POST" action="<?= pd_h($selfUrl) ?>" id="pdpBanForm">
                        <input type="hidden" name="action" value="ban_user">
                        <input type="hidden" name="user_id" value="<?= (int)$player['user_id'] ?>">
                        <button type="submit" name="ban_user" class="pdp-ban-btn" <?= $isBanned ? 'disabled' : '' ?>>
                            <i class="fa-solid fa-user-slash"></i>
                            <?= $isBanned ? 'Already Banned' : 'Ban Player' ?>
                        </button>
                    </form>
                </article>

                <section class="pdp-section">
                    <div class="pdp-section-head">
                        <h3>Joined Teams</h3>
                        <span><?= count($joinedTeams) ?> Team(s)</span>
                    </div>

                    <?php if (!empty($joinedTeams)): ?>
                        <div class="pdp-grid pdp-team-grid">
                            <?php foreach ($joinedTeams as $team): ?>
                                <?php
                                $teamStatusRaw = (string)($team['team_status'] ?? 'unknown');
                                $teamStatusClass = pd_team_status_class($teamStatusRaw);
                                $teamStatusLabel = pd_team_status_label($teamStatusRaw);
                                ?>
                                <article class="pdp-card">
                                    <div class="pdp-card-top">
                                        <span class="pdp-pill"><?= pd_h(strtoupper((string)$team['member_role'])) ?></span>
                                        <span class="pdp-sub-pill"><?= pd_h((string)$team['game_name']) ?></span>
                                        <span class="pdp-pill <?= pd_h($teamStatusClass) ?>"><?= pd_h($teamStatusLabel) ?></span>
                                    </div>
                                    <h4><?= pd_h((string)$team['team_name']) ?></h4>
                                    <p class="pdp-muted">
                                        <?= pd_h((string)($team['short_name'] ?: 'No short name')) ?>
                                    </p>
                                    <p class="pdp-meta">Leader: <strong><?= pd_h((string)$team['leader_name']) ?></strong></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pdp-empty">
                            <h3>No joined teams</h3>
                            <p>This player has not joined any teams yet.</p>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="pdp-section">
                    <div class="pdp-section-head">
                        <h3>Joined Tournaments</h3>
                        <span><?= count($joinedTournaments) ?> Tournament(s)</span>
                    </div>

                    <?php if (!empty($joinedTournaments)): ?>
                        <div class="pdp-grid pdp-tour-grid">
                            <?php foreach ($joinedTournaments as $tournament): ?>
                                <?php
                                $statusRaw = (string)($tournament['status'] ?? 'unknown');
                                $statusClass = pd_status_class($statusRaw);
                                ?>
                                <article class="pdp-card">
                                    <div class="pdp-card-top">
                                        <span class="pdp-pill <?= pd_h($statusClass) ?>">
                                            <?= pd_h(strtoupper($statusRaw)) ?>
                                        </span>
                                        <span class="pdp-sub-pill"><?= pd_h((string)$tournament['game_name']) ?></span>
                                    </div>
                                    <h4><?= pd_h((string)$tournament['title']) ?></h4>
                                    <p class="pdp-meta">Teams: <strong><?= pd_h((string)$tournament['team_names']) ?></strong></p>
                                    <p class="pdp-muted">Start Date: <?= pd_h(pd_format_date((string)$tournament['start_date'])) ?></p>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="pdp-empty">
                            <h3>No joined tournaments</h3>
                            <p>This player is not in any tournament right now.</p>
                        </div>
                    <?php endif; ?>
                </section>
            <?php endif; ?>
        </section>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof Swal === 'undefined') {
            return;
        }

        const bindBanConfirmation = function(formId, buttonSelector, title, text) {
            const form = document.getElementById(formId);
            if (!form) {
                return;
            }

            const button = form.querySelector(buttonSelector);
            if (!button || button.disabled) {
                return;
            }

            form.addEventListener('submit', async function(event) {
                event.preventDefault();

                const result = await Swal.fire({
                    title: title,
                    text: text,
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonText: 'Yes, proceed',
                    cancelButtonText: 'Cancel',
                    customClass: {
                        container: 'player-ban-backdrop',
                        popup: 'player-ban-popup',
                        icon: 'player-ban-icon',
                        title: 'player-ban-title',
                        actions: 'player-ban-actions',
                        confirmButton: 'player-ban-confirm',
                        cancelButton: 'player-ban-cancel'
                    },
                    buttonsStyling: false
                });

                if (result.isConfirmed) {
                    form.submit();
                }
            });
        };

        bindBanConfirmation(
            'pdpBanForm',
            'button[name="ban_user"]',
            'Confirm player ban?',
            'This player account will be marked as banned.'
        );
        bindBanConfirmation(
            'pdpTeamBanForm',
            'button[name="ban_team"]',
            'Confirm team ban?',
            'This will set team status to BANNED and ban all team members.'
        );
    });
</script>
