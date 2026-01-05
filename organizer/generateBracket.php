<?php
session_start();

// Handle team count selection for the schedule view
// This is independent of the actual tournament state in the other file
$viewTeams = $_POST['viewTeams'] ?? 12;

// Layout Configuration
$config = [
    12 => ['groups' => 4, 'size' => 3, 'hasQuarters' => false, 'label' => '12 Teams (4 Groups of 3)'],
    16 => ['groups' => 4, 'size' => 4, 'hasQuarters' => true,  'label' => '16 Teams (4 Groups of 4)'],
    24 => ['groups' => 8, 'size' => 3, 'hasQuarters' => true,  'label' => '24 Teams (8 Groups of 3)']
];

$current = $config[$viewTeams];
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
        
        .setup-bar { background: var(--card); padding: 15px; border-radius: 10px; display: flex; gap: 15px; align-items: center; border: 1px solid #333; }
        select, button { padding: 10px; border-radius: 6px; border: none; font-weight: 600; }
        select { background: #111; color: white; border: 1px solid #444; }
        button { background: var(--accent); color: white; cursor: pointer; }

        .schedule-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(380px, 1fr)); gap: 20px; }
        .schedule-card { background: var(--card); border-radius: 14px; padding: 16px; border: 1px solid #242433; }
        
        .match-row { 
            display: flex; justify-content: space-between; align-items: center; 
            padding: 10px; background: #222235; border-radius: 10px; margin-bottom: 8px; font-size: 13px; 
        }
        
        .team-placeholder { font-weight: 500; color: #aaa; flex: 1; }
        .vs-tag { font-size: 10px; font-weight: bold; color: var(--accent); margin: 0 10px; }
        
        .date-input {
            background: #000; color: var(--accent); border: 1px solid #444;
            padding: 4px; border-radius: 4px; font-size: 11px; outline: none;
        }

        @media print { 
            header, .setup-bar { display: none; } 
            .date-input { border: none; background: transparent; }
        }
    </style>
</head>
<body>

<header>
    <h1>Tourna<span>X</span> Schedule Planner</h1>
    <button onclick="window.print()">Print PDF</button>
</header>

<main>
    <div class="setup-bar">
        <form method="post" style="display:flex; gap:10px; align-items:center;">
            <span>Select Tournament Format:</span>
            <select name="viewTeams">
                <?php foreach($config as $val => $opt): ?>
                    <option value="<?= $val ?>" <?= $viewTeams == $val ? 'selected' : '' ?>><?= $opt['label'] ?></option>
                <?php endforeach; ?>
            </select>
            <button type="submit">Update Layout</button>
        </form>
    </div>

    <section>
        <div class="section-title">Group Stage Matches (Round Robin)</div>
        <div class="schedule-grid">
            <?php for($i=0; $i < $current['groups']; $i++): $gName = chr(65+$i); ?>
            <div class="schedule-card">
                <h3>Group <?= $gName ?></h3>
                <?php 
                // Simple logic to show 3 matches for a group of 3, or 6 for a group of 4
                $matchCount = ($current['size'] == 3) ? 3 : 6;
                for($m=1; $m <= $matchCount; $m++): 
                ?>
                <div class="match-row">
                    <span class="team-placeholder">T<?= $m ?> (Grp <?= $gName ?>)</span>
                    <span class="vs-tag">VS</span>
                    <span class="team-placeholder" style="text-align:right;">T<?= $m+1 ?> (Grp <?= $gName ?>)</span>
                    <input type="datetime-local" class="date-input" style="margin-left:10px;">
                </div>
                <?php endfor; ?>
            </div>
            <?php endfor; ?>
        </div>
    </section>

    

    <section>
        <div class="section-title">Knockout Brackets</div>
        <div class="schedule-grid">
            
            <?php if ($current['hasQuarters']): ?>
            <div class="schedule-card">
                <h3 style="color:var(--accent2)">Quarter-Finals</h3>
                <?php for($q=1; $q<=4; $q++): ?>
                <div class="match-row">
                    <span class="team-placeholder">Winner Match <?= $q ?></span>
                    <span class="vs-tag">VS</span>
                    <span class="team-placeholder" style="text-align:right;">Winner Match <?= $q+1 ?></span>
                    <input type="datetime-local" class="date-input" style="margin-left:10px;">
                </div>
                <?php endfor; ?>
            </div>
            <?php endif; ?>

            <div class="schedule-card">
                <h3 style="color:var(--accent2)">Semi-Finals</h3>
                <?php for($s=1; $s<=2; $s++): ?>
                <div class="match-row">
                    <span class="team-placeholder">Winner QF <?= $s ?></span>
                    <span class="vs-tag">VS</span>
                    <span class="team-placeholder" style="text-align:right;">Winner QF <?= $s+2 ?></span>
                    <input type="datetime-local" class="date-input" style="margin-left:10px;">
                </div>
                <?php endfor; ?>
            </div>

            <div class="schedule-card" style="border: 2px solid var(--accent);">
                <h3 style="color:var(--accent)">Championship Sunday</h3>
                <div class="match-row" style="background:rgba(123, 63, 228, 0.1)">
                    <span class="team-placeholder">Loser Semi 1</span>
                    <span class="vs-tag" style="color:var(--accent2)">3RD PLACE</span>
                    <span class="team-placeholder" style="text-align:right;">Loser Semi 2</span>
                    <input type="datetime-local" class="date-input" style="margin-left:10px;">
                </div>
                <div class="match-row" style="background:rgba(123, 63, 228, 0.2)">
                    <span class="team-placeholder">Winner Semi 1</span>
                    <span class="vs-tag">GRAND FINAL</span>
                    <span class="team-placeholder" style="text-align:right;">Winner Semi 2</span>
                    <input type="datetime-local" class="date-input" style="margin-left:10px;">
                </div>
            </div>

        </div>
    </section>
</main>

</body>
</html>