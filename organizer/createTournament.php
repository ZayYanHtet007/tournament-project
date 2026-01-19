<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tournament</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/organizer/createtour.css">
    <link rel="stylesheet" href="../css/user/responsive.css">
</head>
<body>
<div id="root"></div>

<script>
/* -------------------- DATA -------------------- */

const GAMES = [
    { name: 'League of Legends', type: 'MOBA' },
    { name: 'Dota 2', type: 'MOBA' },
    { name: 'Counter-Strike 2', type: 'FPS' },
    { name: 'Valorant', type: 'FPS' },
    { name: 'Overwatch 2', type: 'FPS' },
    { name: 'Rocket League', type: 'Sports' },
    { name: 'FIFA 24', type: 'Sports' },
    { name: 'Street Fighter 6', type: 'Fighting' },
    { name: 'Tekken 8', type: 'Fighting' },
    { name: 'Fortnite', type: 'Battle Royale' },
    { name: 'Apex Legends', type: 'Battle Royale' },
    { name: 'Minecraft', type: 'Sandbox' },
    { name: 'Starcraft II', type: 'RTS' },
    { name: 'Age of Empires IV', type: 'RTS' },
    { name: 'Hearthstone', type: 'Card Game' },
    { name: 'Magic: The Gathering Arena', type: 'Card Game' },
];

let currentStep = 1;

const formData = {
    title: '',
    description: '',
    gameName: '',
    gameType: '',
    maxParticipants: null,
    entryFee: '',
    registrationStartDate: '',
    registrationDeadline: '',
    gameStartDate: '',
    status: 'upcoming'
};

const root = document.getElementById('root');

/* -------------------- RENDER -------------------- */

function render() {
    root.innerHTML = `
        <div class="min-h-screen p-8">
            <div class="max-w-4xl mx-auto">
                <h1 class="text-3xl font-bold text-center mb-8">Create Tournament</h1>

                <div class="card">
                    <div class="card-header">
                        <h2 class="text-xl font-semibold">Step ${currentStep} of 3</h2>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width:${currentStep * 33.33}%"></div>
                        </div>
                    </div>
                    <div class="card-content" id="stepContent"></div>
                </div>
            </div>
        </div>
    `;

    renderStep();
}

/* -------------------- STEP RENDERERS -------------------- */

function renderStep() {
    const container = document.getElementById('stepContent');

    if (currentStep === 1) container.innerHTML = step1();
    if (currentStep === 2) container.innerHTML = step2();
    if (currentStep === 3) container.innerHTML = step3();

}

/* -------------------- STEP 1 -------------------- */

function step1() {
    return `
        <div class="space-y-6">
            <div>
                <label class="block mb-2 font-medium">Tournament Title</label>
                <input class="input" value="${formData.title}" 
                    oninput="formData.title=this.value">
            </div>

            <div>
                <label class="block mb-2 font-medium">Description</label>
                <textarea class="input" rows="4"
                    oninput="formData.description=this.value">${formData.description}</textarea>
            </div>

            <div>
                <label class="block mb-2 font-medium">Game</label>
                <select class="input" onchange="selectGame(this.value)">
                    <option value="">Select game...</option>
                    ${GAMES.map(g =>
                        `<option value="${g.name}" ${g.name===formData.gameName?'selected':''}>${g.name}</option>`
                    ).join('')}
                </select>
            </div>

            ${formData.gameType ? `
                <div>
                    <label class="block mb-2 font-medium">Game Type</label>
                    <input class="input" disabled value="${formData.gameType}">
                </div>
            ` : ''}

            <div class="flex justify-end">
                <button class="btn btn-primary" onclick="nextStep()" 
                    ${!formData.title || !formData.description || !formData.gameName ? 'disabled':''}>
                    Next
                </button>
            </div>
        </div>
    `;
}

function selectGame(name) {
    const game = GAMES.find(g => g.name === name);
    formData.gameName = game?.name || '';
    formData.gameType = game?.type || '';
    render();
}

/* -------------------- STEP 2 -------------------- */

function step2() {
    const options = [12, 16, 24];

    return `
        <div class="space-y-6">
            <div>
                <label class="block mb-2 font-medium">Max Participants</label>
                <div class="grid grid-cols-3 gap-4">
                    ${options.map(o => `
                        <div class="participant-card ${formData.maxParticipants===o?'selected':''}"
                            onclick="setParticipants(${o})">
                            <div class="text-2xl font-bold">${o}</div>
                            <div class="text-sm text-gray-500">Participants</div>
                        </div>
                    `).join('')}
                </div>
            </div>

            ${formData.maxParticipants ? bracketPreview(formData.maxParticipants) : ''}

            <div>
                <label class="block mb-2 font-medium">Entry Fee</label>
                <input class="input" type="number" value="${formData.entryFee}"
                    oninput="formData.entryFee=this.value">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <input class="input" type="datetime-local"
                    value="${formData.registrationStartDate}"
                    onchange="formData.registrationStartDate=this.value">
                <input class="input" type="datetime-local"
                    value="${formData.registrationDeadline}"
                    onchange="formData.registrationDeadline=this.value">
            </div>

            <div class="flex justify-between">
                <button class="btn btn-outline" onclick="prevStep()">Back</button>
                <button class="btn btn-primary" onclick="nextStep()">Next</button>
            </div>
        </div>
    `;
}

function setParticipants(n) {
    formData.maxParticipants = n;
    render();
}

/* -------------------- BRACKET -------------------- */

function bracketPreview(players) {
    const rounds = Math.log2(players);

    return `
        <div>
            <label class="block mb-2 font-medium">Bracket Preview</label>
            <div class="bracket-container">
                ${Array.from({length: rounds}).map((_, i) => `
                    <div class="bracket-round">
                        <strong>${i+1===rounds?'Final':`Round ${i+1}`}</strong>
                        ${Array.from({length: players / (2 ** (i+1))}).map(() =>
                            `<div class="bracket-match">
                                <div class="bracket-player">Player</div>
                                <div class="bracket-player">Player</div>
                            </div>`
                        ).join('')}
                    </div>
                `).join('')}
            </div>
        </div>
    `;
}

/* -------------------- STEP 3 -------------------- */

function step3() {
    return `
        <div class="space-y-6">
            <div>
                <label class="block mb-2 font-medium">Game Start Date</label>
                <input class="input" type="datetime-local"
                    value="${formData.gameStartDate}"
                    onchange="formData.gameStartDate=this.value">
            </div>

            <div class="bg-gray-50 p-6 rounded">
                <h3 class="font-semibold mb-4">Summary</h3>
                <p><strong>${formData.title}</strong></p>
                <p>${formData.gameName} (${formData.gameType})</p>
                <p>Participants: ${formData.maxParticipants}</p>
                <p>Entry Fee: $${formData.entryFee || 0}</p>
            </div>

            <div class="flex justify-between">
                <button class="btn btn-outline" onclick="prevStep()">Back</button>
                <div class="flex gap-2">
                    <button class="btn btn-outline" onclick="saveDraft()">💾 Save Draft</button>
                    <button class="btn btn-primary" onclick="createTournament()">🏆 Create</button>
                </div>
            </div>
        </div>
    `;
}

/* -------------------- NAV -------------------- */

function nextStep() {
    if (currentStep < 3) currentStep++;
    render();
}

function prevStep() {
    if (currentStep > 1) currentStep--;
    render();
}

function saveDraft() {
    console.log('Draft:', formData);
    alert('Saved as draft');
}

function createTournament() {
    console.log('Created:', formData);
    alert('Tournament created!');
}

/* -------------------- INIT -------------------- */

render();
</script>
</body>
</html>
