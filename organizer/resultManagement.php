<?php
session_start();
require 'standardBracket.php';

if (!isset($_SESSION['tournament']) || isset($_POST['generate'])) {
    $count = (int)($_POST['teamCount'] ?? 12);
    $teams = [];
    for ($i = 1; $i <= $count; $i++) $teams[] = new Team($i, "Team $i");
    $_SESSION['tournament'] = serialize(new StandardTournament($teams));
}

$tournament = unserialize($_SESSION['tournament']);
$err = "";

if (isset($_POST['save_groups'])) {
    foreach ($tournament->groups as $gi => $g)
        foreach ($g->matches as $mi => $m) {
            $s = $_POST['score'][$gi][$mi];
            if ($s[0] !== '' && $s[1] !== '') $m->setScore((int)$s[0], (int)$s[1], 2);
        }
} elseif (isset($_POST['save_ko'])) {
    $st = $_POST['stage'];
    $ms = ($st === 'FINALS') ? $tournament->knockoutStages['FINALS'] : $tournament->knockoutStages[$st];
    foreach ($ms as $k => $m) {
        $s = $_POST['ko_score'][$k];
        if ($s[0] !== '' && $s[1] !== '') {
            $lim = ($st === 'FINALS' && $k === 'GRAND') ? 3 : 2;
            $m->setScore((int)$s[0], (int)$s[1], $lim);
        }
    }
    if ($st === 'FINALS' && $tournament->knockoutStages['FINALS']['GRAND']->finished) $tournament->isFinished = true;
}

if (isset($_POST['gen_bracket'])) if (!$tournament->finishGroups()) $err = "Finish all group matches!";
if (isset($_POST['go_semis'])) if (!$tournament->advanceToSemis()) $err = "Finish Quarters!";
if (isset($_POST['go_finals'])) if (!$tournament->advanceToFinals()) $err = "Finish Semis!";

$_SESSION['tournament'] = serialize($tournament);
?>
<!DOCTYPE html>
<html>
<head>
    <style>
        :root { --bg:#0b0e14; --card:#161b22; --accent:#7b3fe4; --gold:#f0b90b; --silver:#8b949e; --bronze:#d29922; }
        body { background:var(--bg); color:#c9d1d9; font-family: 'Segoe UI', sans-serif; padding:30px; }
        
        /* Combo Box & Button Styling */
        select, .btn { 
            padding: 12px 24px; border-radius: 12px; border: 1px solid #30363d;
            background: #21262d; color: white; font-weight: bold; cursor: pointer; font-size: 14px;
        }
        .btn-primary { background: linear-gradient(135deg, var(--accent), #4f1db0); border: none; }
        
        /* Layout */
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(450px, 1fr)); gap: 25px; margin-bottom: 40px; }
        .card { background: var(--card); border-radius: 16px; padding: 20px; border: 1px solid #30363d; }
        
        /* Points Table Styling */
        .pt-table { width: 100%; border-collapse: collapse; margin-top: 15px; background: #0d1117; border-radius: 10px; overflow: hidden; }
        .pt-table th { background: #21262d; color: var(--gold); padding: 10px; text-align: left; font-size: 12px; }
        .pt-table td { padding: 10px; border-bottom: 1px solid #30363d; font-size: 13px; }
        .top-rank { background: rgba(35, 134, 54, 0.15); border-left: 4px solid #238636; }

        .match { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; padding: 10px; background: #1c2128; border-radius: 8px; }
        .score-input { width: 40px; height: 30px; text-align: center; background: #000; color: #fff; border: 1px solid var(--accent); border-radius: 5px; font-weight: bold; }

        /* Podium Cards */
        .podium { display: flex; align-items: flex-end; justify-content: center; gap: 20px; margin-top: 60px; padding: 40px; }
        .p-card { background: var(--card); border-radius: 20px; padding: 25px; text-align: center; border: 2px solid #30363d; flex: 1; }
        .p-winner { flex: 1.3; border-color: var(--gold); transform: translateY(-20px); box-shadow: 0 10px 30px rgba(240, 185, 11, 0.2); }
        .p-winner h2 { color: var(--gold); font-size: 28px; }
        .p-runner { border-color: var(--silver); }
        .p-third { border-color: var(--bronze); }
        
        h3 { margin-top: 0; color: var(--accent); }
    </style>
</head>
<body>

    <header style="display:flex; justify-content:space-between; align-items:center; margin-bottom:40px;">
        <h2>Tourna<span style="color:var(--accent)">X</span> Master</h2>
        <form method="post">
            <select name="teamCount">
                <option value="12" <?= $tournament->totalTeams == 12 ? 'selected' : '' ?>>12 Teams (2x6)</option>
                <option value="16" <?= $tournament->totalTeams == 16 ? 'selected' : '' ?>>16 Teams (4x4)</option>
                <option value="24" <?= $tournament->totalTeams == 24 ? 'selected' : '' ?>>24 Teams (4x6)</option>
            </select>
            <button name="generate" class="btn btn-primary">Reset Tournament</button>
        </form>
    </header>

    <?php if($err): ?> <div style="background:#442323; color:#ff7b72; padding:15px; border-radius:10px; margin-bottom:20px;"><?= $err ?></div> <?php endif; ?>

    <form method="post">
        <div class="grid">
            <?php foreach ($tournament->groups as $gi => $g): ?>
                <div class="card">
                    <h3><?= $g->name ?></h3>
                    <?php foreach ($g->matches as $mi => $m): ?>
                        <div class="match">
                            <span style="flex:1;"><?= $m->team1->name ?></span>
                            <div>
                                <input class="score-input" type="number" name="score[<?= $gi ?>][<?= $mi ?>][0]" value="<?= $m->finished?$m->wins1:'' ?>" min="0" max="2">
                                :
                                <input class="score-input" type="number" name="score[<?= $gi ?>][<?= $mi ?>][1]" value="<?= $m->finished?$m->wins2:'' ?>" min="0" max="2">
                            </div>
                            <span style="flex:1; text-align:right;"><?= $m->team2->name ?></span>
                        </div>
                    <?php endforeach; ?>
                    
                    <table class="pt-table">
                        <tr><th>Rank</th><th>Team</th><th>PTS</th><th>NET</th><th>W-L</th></tr>
                        <?php 
                        $adv = ($tournament->totalTeams == 12) ? 4 : 2;
                        foreach ($g->getStandings() as $idx => $t): 
                        ?>
                            <tr class="<?= $idx < $adv ? 'top-rank' : '' ?>">
                                <td>#<?= $idx+1 ?></td>
                                <td><strong><?= $t->name ?></strong></td>
                                <td style="color:var(--gold)"><?= $t->points ?></td>
                                <td><?= $t->getNet() ?></td>
                                <td><?= $t->gWon ?>-<?= $t->gLost ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            <?php endforeach; ?>
        </div>
        <button name="save_groups" class="btn">Update Standings</button>
        <button name="gen_bracket" class="btn btn-primary">Unlock Knockout Stage</button>
    </form>

    <?php if ($tournament->state !== 'GROUP'): ?>
    <hr style="border:1px solid #30363d; margin:50px 0;">
    <form method="post">
        <h3>Quarter Finals (BO3)</h3>
        <div class="grid">
            <?php foreach ($tournament->knockoutStages['QUARTERS'] as $k => $m): ?>
                <div class="card match">
                    <span><?= $m->team1->name ?></span>
                    <input class="score-input" type="number" name="ko_score[<?= $k ?>][0]" value="<?= $m->finished?$m->wins1:'' ?>" min="0" max="2">
                    :
                    <input class="score-input" type="number" name="ko_score[<?= $k ?>][1]" value="<?= $m->finished?$m->wins2:'' ?>" min="0" max="2">
                    <span><?= $m->team2->name ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="stage" value="QUARTERS">
        <button name="save_ko" class="btn">Save Quarters</button>
        <button name="go_semis" class="btn btn-primary">Unlock Semi Finals</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($tournament->knockoutStages['SEMIS'])): ?>
    <hr style="border:1px solid #30363d; margin:50px 0;">
    <form method="post">
        <h3>Semi Finals (BO3)</h3>
        <div class="grid">
            <?php foreach ($tournament->knockoutStages['SEMIS'] as $k => $m): ?>
                <div class="card match">
                    <span><?= $m->team1->name ?></span>
                    <input class="score-input" type="number" name="ko_score[<?= $k ?>][0]" value="<?= $m->finished?$m->wins1:'' ?>" min="0" max="2">
                    :
                    <input class="score-input" type="number" name="ko_score[<?= $k ?>][1]" value="<?= $m->finished?$m->wins2:'' ?>" min="0" max="2">
                    <span><?= $m->team2->name ?></span>
                </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="stage" value="SEMIS">
        <button name="save_ko" class="btn">Save Semis</button>
        <button name="go_finals" class="btn btn-primary">Unlock Grand Final</button>
    </form>
    <?php endif; ?>

    <?php if (!empty($tournament->knockoutStages['FINALS'])): ?>
    <hr style="border:1px solid #30363d; margin:50px 0;">
    <form method="post">
        <h3>Final Stage (Grand Final BO5)</h3>
        <div class="grid">
            <?php foreach ($tournament->knockoutStages['FINALS'] as $type => $m): ?>
                <div class="card">
                    <h4 style="margin:0 0 10px 0; color:<?= $type=='GRAND'?'var(--gold)':'var(--bronze)' ?>"><?= $type ?></h4>
                    <div class="match">
                        <span><?= $m->team1->name ?></span>
                        <?php $lim = ($type == 'GRAND' ? 3 : 2); ?>
                        <input class="score-input" type="number" name="ko_score[<?= $type ?>][0]" value="<?= $m->finished?$m->wins1:'' ?>" min="0" max="<?= $lim ?>">
                        :
                        <input class="score-input" type="number" name="ko_score[<?= $type ?>][1]" value="<?= $m->finished?$m->wins2:'' ?>" min="0" max="<?= $lim ?>">
                        <span><?= $m->team2->name ?></span>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <input type="hidden" name="stage" value="FINALS">
        <button name="save_ko" class="btn btn-primary">Finish Tournament</button>
    </form>
    <?php endif; ?>

    <?php if ($tournament->isFinished): 
        $g = $tournament->knockoutStages['FINALS']['GRAND'];
        $t = $tournament->knockoutStages['FINALS']['THIRD'];
    ?>
    <div class="podium">
        <div class="p-card p-runner">
            <h4 style="color:var(--silver)">2nd Place</h4>
            <h2><?= $g->getLoser()->name ?></h2>
        </div>
        <div class="p-card p-winner">
            <h4 style="color:var(--gold)">🏆 CHAMPION 🏆</h4>
            <h2><?= $g->getWinner()->name ?></h2>
        </div>
        <div class="p-card p-third">
            <h4 style="color:var(--bronze)">3rd Place</h4>
            <h2><?= $t->getWinner()->name ?></h2>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>