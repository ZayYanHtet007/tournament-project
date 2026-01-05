<?php
session_start();
require 'standardBracket.php';

// Check if a reset is requested OR if no tournament exists
if (!isset($_SESSION['tournament']) || isset($_POST['generate'])) {
    $count = $_POST['teamCount'] ?? 12;
    $teams = [];
    for ($i = 1; $i <= $count; $i++) $teams[] = new Team($i, "Team $i");
    $_SESSION['tournament'] = serialize(new StandardTournament($teams));
}

$tournament = unserialize($_SESSION['tournament']);

// FIX: The dropdown now always knows the correct count from the object
$currentTeamCount = $tournament->totalTeams; 

$error_msg = "";
$error_loc = "";

// SAVE LOGIC
if (isset($_POST['save_groups'])) {
    foreach ($tournament->groups as $g => $group) {
        foreach ($group->matches as $m => $match) {
            $w1 = $_POST['score'][$g][$m][0] ?? ''; $w2 = $_POST['score'][$g][$m][1] ?? '';
            if ($w1 !== '' && $w2 !== '') $match->setScore((int)$w1, (int)$w2, 2);
        }
    }
} elseif (isset($_POST['save_ko'])) {
    $stage = $_POST['stage'];
    $matches = ($stage === 'FINALS') ? $tournament->knockoutStages['FINALS'] : $tournament->knockoutStages[$stage];
    foreach ($matches as $m => $match) {
        $w1 = $_POST['ko_score'][$m][0] ?? ''; $w2 = $_POST['ko_score'][$m][1] ?? '';
        if ($w1 !== '' && $w2 !== '') {
            $limit = ($stage === 'FINALS' && $m === 'GRAND') ? 3 : 2;
            $match->setScore((int)$w1, (int)$w2, $limit);
        }
    }
    if ($stage === 'FINALS' && $tournament->knockoutStages['FINALS']['GRAND']->finished && $tournament->knockoutStages['FINALS']['THIRD']->finished) {
        $tournament->isFinished = true;
    } else if ($stage === 'FINALS') {
        $error_msg = "Please fill all scores correctly."; $error_loc = "finals";
    }
}

if (isset($_POST['gen_bracket'])) {
    if (!$tournament->finishGroups()) { $error_msg = "Error: Finish all group matches first."; $error_loc = "groups"; }
}
if (isset($_POST['go_semis'])) {
    if (!$tournament->advanceToSemis()) { $error_msg = "Error: Finish all Quarter-Finals."; $error_loc = "quarters"; }
}
if (isset($_POST['go_finals'])) {
    if (!$tournament->advanceToFinals()) { $error_msg = "Error: Finish all Semi-Finals."; $error_loc = "semis"; }
}

$_SESSION['tournament'] = serialize($tournament);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    :root {
       --bg:#0f0f14; --card:#1a1a24;
       --accent:#7b3fe4; --accent2:#e53935; 
       --text:#f5f5f7; --gold:#ffd700; 
       --silver:#c0c0c0; 
       --bronze:#cd7f32; 
      }
    body { 
      margin:0; 
      font-family:system-ui; 
      background:var(--bg); 
      color:var(--text); 
      scroll-behavior: smooth; 
    }
    header { 
      padding:20px 28px; 
      border-bottom:1px solid #242433; 
    }
    header h1 { 
      margin:0; 
      font-size:22px 
    }
    header span { 
      color:var(--accent) }
    main { 
      padding:28px; 
      display:flex; 
      flex-direction:column; 
      gap:40px; 
    }
    .section-title { 
      font-size:18px; 
      margin-bottom:14px; 
      display:flex; gap:10px; 
      align-items:center; 
    }
    .section-title::before { 
      content:""; 
      width:4px; 
      height:18px; 
      background:linear-gradient(180deg,var(--accent),var(--accent2)); 
      border-radius:4px; 
    }
    input, select, button { 
      padding: 8px 12px; 
      border-radius: 6px; 
      border: none; 
    }
    button {
       padding: 10px 18px; 
       border-radius: 10px; 
       background: linear-gradient(135deg, var(--accent), var(--accent2)); 
       color: #fff; 
       font-weight: 600; 
       cursor: pointer; 
    }
    .error-inline { 
      color: var(--accent2); 
      font-size: 13px; 
      font-weight: bold;
     margin-left: 15px;
     }
    .groups { 
      display: grid; 
      grid-template-columns: 
      repeat(auto-fit, minmax(450px, 1fr)); 
      gap: 20px; 
    }
    .group-card { 
      background: var(--card); 
      border-radius: 14px; 
      padding: 16px; 
    }
    .match { 
      display: flex; 
      justify-content: space-between; 
      align-items: center; 
      padding: 10px; 
      background: #222235; 
      border-radius: 10px; 
      margin-bottom: 8px; 
      font-size: 14px; 
    }
    .score-input { 
      width: 45px; 
      text-align: center; 
      background: #111; 
      color: #fff; 
      border: 1px solid var(--accent); 
      font-weight: bold; 
    }
    .knockout { 
      display: grid; 
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); 
      gap: 20px; 
    }
    .ko-card { 
      background-image: url('https://img.freepik.com/free-vector/abstract-neon-lights-background_23-2148995392.jpg'); 
      background-size: cover; 
      background-position: center; 
      border-radius: 14px; 
      padding: 20px; 
      text-align: center; 
      position: relative; 
      overflow: hidden; 
      min-height: 200px; 
      display: flex; 
      flex-direction: column; 
      justify-content: center; 
    }
    .ko-card::before { 
      content: ""; 
      position: absolute; 
      top:0; left:0; right:0; bottom:0; 
      background: rgba(15, 15, 20, 0.85); 
      z-index: 0; 
    }
    .ko-content { 
      position: relative; 
      z-index: 1; display: flex; 
      flex-direction: column; 
      gap: 15px; 
    }
    .podium { 
      display: flex; 
      justify-content: center; 
      align-items: flex-end; 
      gap: 20px; 
      padding: 40px 0; 
    }
    .podium-card { 
      background: var(--card); 
      border-radius: 14px; 
      padding: 20px; 
      text-align: center; 
      border: 2px solid transparent; 
      }
</style>
</head>
<body>
<header><h1>Tourna<span>X</span> Organizer</h1></header>
<main>

    <form method="post" action="#setup" id="setup">
      <select name="teamCount">
          <option value="12" <?= $currentTeamCount == 12 ? 'selected' : '' ?>>12 Teams</option>
          <option value="16" <?= $currentTeamCount == 16 ? 'selected' : '' ?>>16 Teams</option>
          <option value="24" <?= $currentTeamCount == 24 ? 'selected' : '' ?>>24 Teams</option>
      </select>
      <button name="generate">Reset / New Tournament</button>
    </form>

    <form method="post" action="#groups" id="groups">
        <div class="section-title">Groups (BO3)</div>
        <div class="groups">
            <?php foreach ($tournament->groups as $g => $group): ?>
                <div class="group-card"><h3><?=$group->name?></h3>
                <?php foreach ($group->matches as $m => $match): ?>
                  <div class="match">
                    <span style="flex:1;"><?=$match->team1->name?></span>
                    <div class="score"><input class="score-input" type="number" min="0" max="2" name="score[<?=$g?>][<?=$m?>][0]" value="<?=$match->finished?$match->wins1:''?>"> : <input class="score-input" type="number" min="0" max="2" name="score[<?=$g?>][<?=$m?>][1]" value="<?=$match->finished?$match->wins2:''?>"></div>
                    <span style="flex:1; text-align:right;"><?=$match->team2->name?></span>
                  </div>
                <?php endforeach; ?></div>
            <?php endforeach; ?>
        </div>
        <div style="margin-top:15px;">
            <button name="save_groups">Save Groups</button> 
            <button name="gen_bracket" style="margin-left:10px;">Generate Knockout</button>
            <?php if($error_loc == "groups"): ?> <span class="error-inline"><?=$error_msg?></span> <?php endif; ?>
        </div>
    </form>

    <?php if (!empty($tournament->knockoutStages['QUARTERS'])): ?>
    <form method="post" action="#quarters" id="quarters">
        <div class="section-title">Quarter-Finals (BO3)</div>
        <div class="knockout">
        <?php foreach ($tournament->knockoutStages['QUARTERS'] as $m => $match): ?>
          <div class="ko-card">
            <div class="ko-content">
              <div class="ko-team">
                <?=$match->team1->name?>
            </div>
            <div style="display:flex; justify-content:center; gap:10px;">
              <input class="score-input" type="number" min="0" max="2" name="ko_score[<?=$m?>][0]" value="<?=$match->finished?$match->wins1:''?>"> VS 
          <input class="score-input" type="number" min="0" max="2" name="ko_score[<?=$m?>][1]" value="<?=$match->finished?$match->wins2:''?>"></div><div class="ko-team"><?=$match->team2->name?></div></div></div>
        <?php endforeach; ?>
        </div><input type="hidden" name="stage" value="QUARTERS">
        <div style="margin-top:15px;"><button name="save_ko">Save Scores</button> <button name="go_semis" style="margin-left:10px;">Advance to Semis</button>
        <?php if($error_loc == "quarters"): ?> <span class="error-inline"><?=$error_msg?></span> <?php endif; ?></div>
    </form>
    <?php endif; ?>

    <?php if (!empty($tournament->knockoutStages['SEMIS'])): ?>
    <form method="post" action="#semis" id="semis">
        <div class="section-title">Semi-Finals (BO3)</div>
        <div class="knockout">
        <?php foreach ($tournament->knockoutStages['SEMIS'] as $m => $match): ?>
            <div class="ko-card"><div class="ko-content"><div class="ko-team"><?=$match->team1->name?></div><div style="display:flex; justify-content:center; gap:10px;"><input class="score-input" type="number" min="0" max="2" name="ko_score[<?=$m?>][0]" value="<?=$match->finished?$match->wins1:''?>"> VS <input class="score-input" type="number" min="0" max="2" name="ko_score[<?=$m?>][1]" value="<?=$match->finished?$match->wins2:''?>"></div><div class="ko-team"><?=$match->team2->name?></div></div></div>
        <?php endforeach; ?>
        </div><input type="hidden" name="stage" value="SEMIS">
        <div style="margin-top:15px;"><button name="save_ko">Save Scores</button> <button name="go_finals" style="margin-left:10px;">Advance to Finals</button>
        <?php if($error_loc == "semis"): ?> <span class="error-inline"><?=$error_msg?></span> <?php endif; ?></div>
    </form>
    <?php endif; ?>

    <?php if (!empty($tournament->knockoutStages['FINALS'])): ?>
    <form method="post" action="#finals" id="finals">
        <div class="section-title">Finals (Grand Final BO5)</div>
        <div class="knockout">
        <?php foreach ($tournament->knockoutStages['FINALS'] as $type => $match): ?>
            <div class="ko-card"><div class="ko-content">
                <h3 style="color:var(--accent);margin:0"><?=$type==='GRAND'?'GRAND FINAL':'3rd PLACE'?></h3>
                <div class="ko-team">
                  <?=$match->team1->name?>
                </div>
                <div style="display:flex; justify-content:center; gap:10px;">
                    <?php $max = ($type === 'GRAND') ? 3 : 2; ?>
                    <input class="score-input" type="number" min="0" max="<?=$max?>" name="ko_score[<?=$type?>][0]" value="<?=$match->finished?$match->wins1:''?>"> VS <input class="score-input" type="number" min="0" max="<?=$max?>" name="ko_score[<?=$type?>][1]" value="<?=$match->finished?$match->wins2:''?>">
                </div>
                <div class="ko-team"><?=$match->team2->name?></div>
            </div></div>
        <?php endforeach; ?>
        </div><input type="hidden" name="stage" value="FINALS">
        <div style="margin-top:15px;"><button name="save_ko">Save Final Results</button>
        <?php if($error_loc == "finals"): ?> <span class="error-inline"><?=$error_msg?></span> <?php endif; ?></div>
    </form>
    <?php endif; ?>

    <?php if ($tournament->isFinished): 
        $grandMatch = $tournament->knockoutStages['FINALS']['GRAND'];
        $thirdMatch = $tournament->knockoutStages['FINALS']['THIRD'];
    ?>
    
    <section id="results" style="margin-top: 60px; border-top: 2px dashed #242433; padding-top: 40px;">
        <div class="podium">
            <div class="podium-card" style="width:240px; border-color:var(--silver);"><div style="background:var(--silver); color:#000; padding:5px; border-radius:20px; font-size:11px; font-weight:bold;">Runner Up</div><div class="ko-team"><?= $grandMatch->getLoser()->name ?></div></div>
            <div class="podium-card" style="width:320px; border-color:var(--gold);"><div style="background:var(--gold); color:#000; padding:5px; border-radius:20px; font-size:11px; font-weight:bold;">Champion</div><div class="ko-team" style="font-size: 1.5em;"><?= $grandMatch->getWinner()->name ?></div></div>
            <div class="podium-card" style="width:240px; border-color:var(--bronze);"><div style="background:var(--bronze); color:#000; padding:5px; border-radius:20px; font-size:11px; font-weight:bold;">3rd Place</div><div class="ko-team"><?= $thirdMatch->getWinner()->name ?></div></div>
        </div>
    </section>
    <?php endif; ?>

</main>
</body>
</html>