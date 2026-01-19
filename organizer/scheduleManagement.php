<?php
    include("header.php");
?>

<body class="tx-body">
<div class="tx-container">

<div class="tx-header">
    <h1>🏆 Tournament</h1>
    <div class="tx-select">
        <select id="txTeams" onchange="txGenerate()">
            <option value="">Select Teams</option>
            <option value="12">12 Teams</option>
            <option value="16">16 Teams</option>
            <option value="24">24 Teams</option>
        </select>
    </div>
</div>

<div id="txContent"></div>

<script>
function txShuffle(a){return a.sort(()=>Math.random()-0.5);}

function txMatch(a,b){
return `
<div class="tx-match">
    <div class="tx-match-row">
        <span class="tx-team">${a}</span>
        <span>vs</span>
        <span class="tx-team">${b}</span>

        <div class="tx-date-wrap">
            <input type="datetime-local" class="match-date" style="margin-top:10px;width:100%;">
        <div class="selected-date" style="display:none;margin-top:5px;font-weight:bold;"></div>
        </div>
    </div>
</div>`;
}

function txGroupMatches(teams){
    let html="";
    for(let i=0;i<teams.length;i++){
        for(let j=i+1;j<teams.length;j++){
            html+=txMatch(teams[i],teams[j]);
        }
    }
    return html;
}

function txGenerate(){
    const n=parseInt(document.getElementById("txTeams").value);
    const c=document.getElementById("txContent");
    c.innerHTML="";
    if(!n) return;

    const groupCount = n===24?4:4;
    let teams=[...Array(n)].map((_,i)=>`Team ${i+1}`);
    txShuffle(teams);

    let groups=[...Array(groupCount)].map(()=>[]);
    teams.forEach((t,i)=>groups[i%groupCount].push(t));

    /* GROUP STAGE */
    c.innerHTML+=`
    <div class="tx-section">
        <div class="tx-title">Group Stage</div>
        <div class="tx-grid">
        ${groups.map((g,i)=>`
            <div class="tx-card">
                <h3>Group ${String.fromCharCode(65+i)}</h3>
                ${txGroupMatches(g)}
            </div>`).join("")}
        </div>
    </div>`;


    /* QUALIFIED */
    let q=[];
    groups.forEach(g=>q.push(g[0],g[1]));

    /* QUARTERFINALS */
    c.innerHTML+=`
    <div class="tx-section">
        <div class="tx-title">Quarterfinals</div>
        <div class="tx-grid">
        ${q.map((_,i)=>i%2===0?`
            <div class="tx-card">
                <h3>Match ${i/2+1}</h3>
                ${txMatch(q[i],q[i+1])}
            </div>`:"").join("")}
        </div>
    </div>`;


    /* SEMIFINALS */
    c.innerHTML+=`
    <div class="tx-section">
        <div class="tx-title">Semifinals</div>
        <div class="tx-grid">
            <div class="tx-card"><h3>Semifinal 1</h3>${txMatch("Winner QF1","Winner QF2")}</div>
            <div class="tx-card"><h3>Semifinal 2</h3>${txMatch("Winner QF3","Winner QF4")}</div>
        </div>
    </div>`;


    /* FINALS */
    c.innerHTML+=`
    <div class="tx-section">
        <div class="tx-title">Finals</div>
        <div class="tx-grid">
            <div class="tx-card"><h3>Champion</h3>${txMatch("Winner SF1","Winner SF2")}</div>
            <div class="tx-card"><h3>3rd Place</h3>${txMatch("Loser SF1","Loser SF2")}</div>
        </div>
    </div>
    `;

    // ADD SAVE BUTTON
    c.innerHTML+=`
    <div style="text-align:center;margin-top:30px;">
        <button id="txSave" style="padding:10px 20px;font-size:16px;cursor:pointer;">💾 SAVE TOURNAMENT</button>
    </div>
    `;

    // SAVE BUTTON CLICK
    document.getElementById("txSave").addEventListener("click", function(){
        alert("Save functionality can be implemented here!");
    });
}
</script>

<?php
include('footer.php');
?>
