<?php
session_start();
require_once 'standardBracket.php';

// Try to pull actual teams from the session tournament if it exists
$tournament = isset($_SESSION['tournament']) ? unserialize($_SESSION['tournament']) : null;

// Determine current view based on selection or active tournament
$viewTeams = $_POST['viewTeams'] ?? ($tournament ? $tournament->totalTeams : 12);

// Configuration aligned with StandardTournament2.php logic
$config = [
    12 => ['groups' => 2, 'size' => 6, 'label' => '12 Teams (2 Groups of 6)'],
    16 => ['groups' => 4, 'size' => 4, 'label' => '16 Teams (4 Groups of 4)'],
    24 => ['groups' => 4, 'size' => 6, 'label' => '24 Teams (4 Groups of 6)']
];

$current = $config[$viewTeams];

/**
 * Generates Round Robin match-ups for scheduling display
 */
function getRoundRobinPairs($size) {
    $pairs = [];
    for ($i = 1; $i <= $size; $i++) {
        for ($j = $i + 1; $j <= $size; $j++) {
            $pairs[] = [$i, $j];
        }
    }
    return $pairs;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>TournaX - Schedule Planner</title>
    <style>
        :root { --bg:#0f0f14; --card:#1a1a24; --accent:#7b3fe4; --accent2:#e53935; --text:#f5f5f7; }
        body { margin:0; font-family:system-ui; background:var(--bg); color:var(--text); scroll-behavior: smooth; }
        header { padding:20px 28px; border-bottom:1px solid #242433; display:flex; justify-content:space-between; align-items:center; position: sticky; top: 0; background: var(--bg); z-index: 100; }
        main { padding:28px; display:flex; flex-direction:column; gap:40px; }
        
        .section-title { font-size:18px; margin-bottom:14px; display:flex; gap:10px; align-items:center; }
        .section-title::before { content:""; width:4px; height:18px; background:linear-gradient(180deg,var(--accent),var(--accent2)); border-radius:4px; }
        
        /* Styled Combo Box and Button */
        .setup-bar { background: var(--card); padding: 15px; border-radius: 12px; display: flex; gap: 15px; align-items: center; border: 1px solid #333; }
        select, .btn-print { padding: 10px 18px; border-radius: 12px; border: 1px solid #444; font-weight: 600; cursor: pointer; }
        select { background: #111; color: white; }
        button[type="submit"] { background: var(--accent); color: white; border: none; padding: 10px 20px; border-radius: 12px; cursor: pointer; font-weight: bold; }
        .btn-print { background: #21262d; color: white; }

        .schedule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(400px, 1fr)); gap: 20px; }
        .schedule-card { background: var(--card); border-radius: 16px; padding: 20px; border: 1px solid #242433; }
        
        .match-row { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 10px; background: #222235; border-radius: 10px; margin-bottom: 8px; font-size: 13px; 
        }
        
        .team-name { font-weight: 500; color: #fff; flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .vs-tag { font-size: 10px; font-weight: bold; color: var(--accent); margin: 0 10px; flex-shrink: 0; }
        
        .date-input {
            background: #000; color: #00ffcc; border: 1px solid #444;
            padding: 5px; border-radius: 6px; font-size: 11px; outline: none; margin-left: 10px;
        }

        @media print { 
            header, .setup-bar, button { display: none; } 
            body { background: white; color: black; }
            .schedule-card { border: 1px solid #ddd; background: white; }
            .match-row { background: #f9f9f9; border: 1px solid #eee; }
            .date-input { border: none; background: transparent; color: black; }
            .team-name { color: black; }
        }
    </style>
</head>
<body>

<header>
    <h1 style="font-size: 20px;">Tourna<span style="color:var(--accent)">X</span> Schedule Planner</h1>
    <button class="btn-print" onclick="window.print()">Download Schedule PDF</button>
</header>

<main>
    <div class="setup-bar">
        <form method="post" style="display:flex; gap:15px; align-items:center;">
            <span>Tournament Format:</span>
            <select name="viewTeams">
                <?php foreach($config as $val => $opt): ?>
                    <option value="<?= $val ?>" <?= $viewTeams == $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Update Layout</button>
        </form>
    </div>

    <section>
        <div class="section-title">Group Stage: Round Robin Scheduling</div>
        <div class="schedule-grid">
            <?php 
            $matches = getRoundRobinPairs($current['size']);
            for($i=0; $i < $current['groups']; $i++): 
                $gLetter = chr(65+$i); 
            ?>
            <div class="schedule-card">
                <h3 style="margin-top:0; color:var(--accent)">Group <?= $gLetter ?></h3>
                <?php foreach($matches as $pair): ?>
                <div class="match-row">
                    <span class="team-name">Team <?= $pair[0] ?></span>
                    <span class="vs-tag">VS</span>
                    <span class="team-name" style="text-align:right;">Team <?= $pair[1] ?></span>
                    <input type="datetime-local" class="date-input">
                </div>
                <?php endforeach; ?>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    <section>
        <div class="section-title">Knockout Phase Brackets</div>
        <div class="schedule-grid">
            
            <div class="schedule-card">
                <h3 style="color:var(--accent2); margin-top:0;">Quarter-Finals</h3>
                <?php 
                $labels = ['QF 1: A1 vs B4', 'QF 2: B2 vs A3', 'QF 3: B1 vs A4', 'QF 4: A2 vs B3'];
                if ($viewTeams > 12) $labels = ['QF 1: A1 vs C2', 'QF 2: B1 vs D2', 'QF 3: C1 vs A2', 'QF 4: D1 vs B2'];
                
                foreach($labels as $label): ?>
                <div class="match-row">
                    <span class="team-name" style="font-size:11px;"><?= $label ?></span>
                    <input type="datetime-local" class="date-input">
                </div>
                <?php endforeach; ?>
            </div>

            <div class="schedule-card">
                <h3 style="color:var(--accent2); margin-top:0;">Semi-Finals</h3>
                <div class="match-row">
                    <span class="team-name">Winner QF 1 vs QF 2</span>
                    <input type="datetime-local" class="date-input">
                </div>
                <div class="match-row">
                    <span class="team-name">Winner QF 3 vs QF 4</span>
                    <input type="datetime-local" class="date-input">
                </div>
            </div>

            <div class="schedule-card" style="border: 2px solid var(--accent);">
                <h3 style="color:var(--accent); margin-top:0;">Grand Finals</h3>
                <div class="match-row" style="background:rgba(229, 57, 53, 0.1)">
                    <span class="team-name">3RD PLACE MATCH</span>
                    <input type="datetime-local" class="date-input">
                </div>
                <div class="match-row" style="background:rgba(123, 63, 228, 0.2)">
                    <span class="team-name" style="font-weight:bold; color:var(--gold)">GRAND FINAL (BO5)</span>
                    <input type="datetime-local" class="date-input">
                </div>
            </div>

        </div>
    </section>
</main>

</body>
</html>