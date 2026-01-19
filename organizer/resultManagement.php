<?php
include("header.php");
?>

<body class="txg-body">
<div class="txg-container">

    <div class="txg-header">
        <h1>Group Stage Standings</h1>
    </div>

    <div class="txg-grid">
        <!-- GROUP A -->
        <div class="txg-card">
            <div class="txg-title">Group A</div>
            <table class="txg-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>PTS</th>
                        <th>Win</th>
                        <th>Lose</th>
                        <th>Net</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="txg-qualified">
                        <td>Team Alpha</td><td>6</td><td>2</td><td>0</td><td>+5</td><td>32:10</td>
                    </tr>
                    <tr class="txg-qualified">
                        <td>Team Bravo</td><td>4</td><td>1</td><td>1</td><td>+1</td><td>30:55</td>
                    </tr>
                    <tr>
                        <td>Team Cobra</td><td>1</td><td>0</td><td>2</td><td>-4</td><td>29:40</td>
                    </tr>
                    <tr>
                        <td>Team Delta</td><td>0</td><td>0</td><td>2</td><td>-6</td><td>27:10</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- GROUP B -->
        <div class="txg-card">
            <div class="txg-title">Group B</div>
            <table class="txg-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>PTS</th>
                        <th>Win</th>
                        <th>Lose</th>
                        <th>Net</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="txg-qualified">
                        <td>Team Eagle</td><td>6</td><td>2</td><td>0</td><td>+7</td><td>33:22</td>
                    </tr>
                    <tr class="txg-qualified">
                        <td>Team Falcon</td><td>3</td><td>1</td><td>1</td><td>+2</td><td>31:05</td>
                    </tr>
                    <tr>
                        <td>Team Ghost</td><td>1</td><td>0</td><td>2</td><td>-3</td><td>28:44</td>
                    </tr>
                    <tr>
                        <td>Team Hydra</td><td>0</td><td>0</td><td>2</td><td>-6</td><td>26:59</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- GROUP A -->
        <div class="txg-card">
            <div class="txg-title">Group A</div>
            <table class="txg-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>PTS</th>
                        <th>Win</th>
                        <th>Lose</th>
                        <th>Net</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="txg-qualified">
                        <td>Team Alpha</td><td>6</td><td>2</td><td>0</td><td>+5</td><td>32:10</td>
                    </tr>
                    <tr class="txg-qualified">
                        <td>Team Bravo</td><td>4</td><td>1</td><td>1</td><td>+1</td><td>30:55</td>
                    </tr>
                    <tr>
                        <td>Team Cobra</td><td>1</td><td>0</td><td>2</td><td>-4</td><td>29:40</td>
                    </tr>
                    <tr>
                        <td>Team Delta</td><td>0</td><td>0</td><td>2</td><td>-6</td><td>27:10</td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- GROUP B -->
        <div class="txg-card">
            <div class="txg-title">Group B</div>
            <table class="txg-table">
                <thead>
                    <tr>
                        <th>Team</th>
                        <th>PTS</th>
                        <th>Win</th>
                        <th>Lose</th>
                        <th>Net</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="txg-qualified">
                        <td>Team Eagle</td><td>6</td><td>2</td><td>0</td><td>+7</td><td>33:22</td>
                    </tr>
                    <tr class="txg-qualified">
                        <td>Team Falcon</td><td>3</td><td>1</td><td>1</td><td>+2</td><td>31:05</td>
                    </tr>
                    <tr>
                        <td>Team Ghost</td><td>1</td><td>0</td><td>2</td><td>-3</td><td>28:44</td>
                    </tr>
                    <tr>
                        <td>Team Hydra</td><td>0</td><td>0</td><td>2</td><td>-6</td><td>26:59</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- GROUP STAGE MATCHES -->
    <div class="txg-header">
        <h1>GROUP STAGE MATCHES</h1>
    </div>
    <div class="txg-grid" id="groupMatches"></div>

    <!-- QUARTERFINALS -->
    <div class="txg-header">
        <h1>QUARTERFINALS</h1>
    </div>
    <div class="txg-grid" id="quarterMatches"></div>

    <!-- SEMIFINALS -->
    <div class="txg-header">
        <h1>SEMIFINALS</h1>
    </div>
    <div class="txg-grid" id="semiMatches"></div>

    <!-- 3rd RunnerUp -->
    <div class="txg-header">
        <h1>3rd RunnerUp</h1>
    </div>
    <div class="txg-grid" id="thirdPlaceMatch"></div>

    <!-- FINALS -->
    <div class="txg-header">
        <h1>FINALS</h1>
    </div>
    <div class="txg-grid" id="finalMatch"></div>

    <!-- SAVE BUTTON -->
    <div class="tx-save-wrap">
        <button class="tx-save-btn">💾 SAVE TOURNAMENT</button>
    </div>

</div>

<script>
// CREATE MATCH CARD
function matchCard(teamA, teamB){
    return `
    <div class="txg-card">
        <div class="txg-title">${teamA} vs ${teamB}</div>
        <div style="display:flex;justify-content:space-between;align-items:center;">
            <span>${teamA}</span>
            <input class="tx-score1" type="number" min="0" max="2" placeholder="0">
            <strong>VS</strong>
            <input class="tx-score2" type="number" min="0" max="2" placeholder="0">
            <span>${teamB}</span>
        </div>
        <br>
        <span class="text">Duration min/sec</span>
        <input type="number" class="duration">
        <input type="number" class="duration">
    </div>`;
}

// GENERATE ROUND ROBIN GROUP MATCHES
function groupMatches(teams){
    let html = "";
    for(let i=0;i<teams.length;i++){
        for(let j=i+1;j<teams.length;j++){
            html += matchCard(teams[i],teams[j]);
        }
    }
    return html;
}

// GROUP TEAMS
const groupA = ["Team Alpha","Team Bravo","Team Cobra","Team Delta"];
const groupB = ["Team Eagle","Team Falcon","Team Ghost","Team Hydra"];

// INSERT GROUP MATCHES
document.getElementById("groupMatches").innerHTML =
    groupMatches(groupA) + groupMatches(groupB);

// QUARTERFINALS EXAMPLE PAIRINGS
const quarterfinals = [
    ["Team Alpha","Team Falcon"],
    ["Team Bravo","Team Eagle"],
    ["Team Cobra","Team Hydra"],
    ["Team Delta","Team Ghost"]
];
document.getElementById("quarterMatches").innerHTML = quarterfinals.map(m=>matchCard(m[0],m[1])).join("");

// SEMIFINALS EXAMPLE PAIRINGS
const semifinals = [
    ["Winner QF1","Winner QF2"],
    ["Winner QF3","Winner QF4"]
];
document.getElementById("semiMatches").innerHTML = semifinals.map(m=>matchCard(m[0],m[1])).join("");

// 3RD PLACE
document.getElementById("thirdPlaceMatch").innerHTML = matchCard("Loser SF1","Loser SF2");

// FINAL
document.getElementById("finalMatch").innerHTML = matchCard("Winner SF1","Winner SF2");

// HANDLE DATE PICKER
document.addEventListener("change", function(e){
    if(e.target.classList.contains("match-date")){
        const val = e.target.value;
        const display = e.target.nextElementSibling;
        if(val){
            display.textContent = "Scheduled: " + val.replace("T"," ");
            display.style.display = "block";
            e.target.style.display = "none"; // hide picker after selection
        }
    }
});
</script>

<?php include("footer.php"); ?>
