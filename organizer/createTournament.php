<?php
session_start();
require_once "../database/dbConfig.php";

if (
    !isset($_SESSION['user_id']) ||
    !$_SESSION['is_organizer'] ||
    $_SESSION['organizer_status'] !== 'approved'
) {
    header("Location: ../login.php");
    exit;
}

$message = "";

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['btnCreate'])) {

    $organizer_id = $_SESSION['user_id'];

    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $game_name = trim($_POST['game_name']);
    $game_type = $_POST['game_type'];
    $match_type = $_POST['match_type'];
    $format = $_POST['format'];
    $max_participants = (int)$_POST['max_participants'];
    $fee = (float)$_POST['fee'];
    $registration_deadline = $_POST['registration_deadline'];
    $start_date = $_POST['start_date'];

    if (empty($title) || empty($description) || empty($game_name)) {
        $message = "❌ Please fill all required fields";
    } else {

        $stmt = $conn->prepare("
            INSERT INTO tournaments
            (organizer_id, title, description, game_name, game_type, match_type, format,
             max_participants, fee, registration_deadline, start_date,
             status, admin_status, created_at, last_update)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'upcoming', 'pending', NOW(), NOW())
        ");

        $stmt->bind_param(
            "issssssidss",
            $organizer_id,
            $title,
            $description,
            $game_name,
            $game_type,
            $match_type,
            $format,
            $max_participants,
            $fee,
            $registration_deadline,
            $start_date
        );

        if ($stmt->execute()) {
            $tournament_id = $stmt->insert_id;
            header("Location: stripe-payment.php?tournament_id=$tournament_id");
            exit;
        } else {
            $message = "❌ Database error";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tournament</title>
    <script crossorigin src="https://unpkg.com/react@18/umd/react.production.min.js"></script>
    <script crossorigin src="https://unpkg.com/react-dom@18/umd/react-dom.production.min.js"></script>
    <script src="https://unpkg.com/@babel/standalone/babel.min.js"></script>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="../css/organizer/createtour.css">
</head>

<body>
    <div id="root"></div>

    <script type="text/babel">
        const { useState } = React;

        // Mock game data
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

        // GameSelector Component
        function GameSelector({ value, onChange, gameType }) {
            const [open, setOpen] = useState(false);
            const [search, setSearch] = useState('');

            const filteredGames = GAMES.filter(game =>
                game.name.toLowerCase().includes(search.toLowerCase())
            );

            const handleSelect = (game) => {
                onChange(game);
                setOpen(false);
                setSearch('');
            };

            return (
                <div className="select-box">
                    <button
                        type="button"
                        className="select-display"
                        onClick={() => setOpen(!open)}
                    >
                        {value || "Select game..."}
                        <span style={{ position: 'absolute', right: '0.75rem' }}>▼</span>
                    </button>
                    {open && (
                        <div className="select-dropdown">
                            <input
                                type="text"
                                className="select-search"
                                placeholder="Search game..."
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                autoFocus
                            />
                            {filteredGames.length === 0 ? (
                                <div style={{ padding: '1rem', textAlign: 'center', color: '#6b7280' }}>
                                    No game found
                                </div>
                            ) : (
                                filteredGames.map((game) => (
                                    <div
                                        key={game.name}
                                        className={`select-option ${value === game.name ? 'selected' : ''}`}
                                        onClick={() => handleSelect(game)}
                                    >
                                        {value === game.name && '✓ '}
                                        {game.name}
                                    </div>
                                ))
                            )}
                        </div>
                    )}
                </div>
            );
        }

        // BracketPreview Component
        function BracketPreview({ participants }) {
            const rounds = Math.log2(participants);
            
            const renderRound = (roundNumber) => {
                const matchesInRound = participants / Math.pow(2, roundNumber);
                
                return (
                    <div key={roundNumber} className="bracket-round">
                        <div style={{ fontSize: '0.875rem', fontWeight: '600', marginBottom: '0.5rem', textAlign: 'center' }}>
                            {roundNumber === rounds ? 'Final' : `Round ${roundNumber}`}
                        </div>
                        {Array.from({ length: matchesInRound }).map((_, matchIndex) => (
                            <div key={matchIndex} className="bracket-match" style={{ height: '5rem' }}>
                                <div className="bracket-player">
                                    Player {matchIndex * 2 + 1}
                                </div>
                                <div className="bracket-player">
                                    Player {matchIndex * 2 + 2}
                                </div>
                            </div>
                        ))}
                    </div>
                );
            };

            return (
                <div className="bracket-container">
                    <div className="bracket-rounds">
                        {Array.from({ length: rounds }).map((_, index) => (
                            <React.Fragment key={index}>
                                {renderRound(index + 1)}
                                {index < rounds - 1 && (
                                    <div className="round-arrow" style={{ display: 'flex', alignItems: 'center', justifyContent: 'center', padding: '0 0.5rem' }}>
                                        <svg width="28" height="56" viewBox="0 0 28 56" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
                                            <path d="M4 4 L4 44 L12 36 L20 44 L20 4" stroke="#9CA3AF" strokeWidth="2" fill="none" strokeLinecap="round" strokeLinejoin="round"/>
                                        </svg>
                                    </div>
                                )}
                            </React.Fragment>
                        ))}
                        <div style={{ display: 'flex', flexDirection: 'column' }}>
                            <div style={{ fontSize: '0.875rem', fontWeight: '600', marginBottom: '0.5rem', textAlign: 'center' }}>
                                Winner
                            </div>
                            <div className="winner-box">
                                🏆 Champion
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        // Step 1: Tournament Details
        function TournamentDetailsStep({ formData, updateFormData, onNext }) {
            const handleGameSelect = (game) => {
                updateFormData({
                    gameName: game.name,
                    gameType: game.type
                });
            };

            const isFormValid = formData.title && formData.description && formData.gameName;

            return (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                    <div>
                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                            Tournament Title
                        </label>
                        <input
                            type="text"
                            className="input"
                            placeholder="Enter tournament title"
                            value={formData.title}
                            onChange={(e) => updateFormData({ title: e.target.value })}
                        />
                    </div>

                    <div>
                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                            Description
                        </label>
                        <textarea
                            className="input"
                            placeholder="Enter tournament description"
                            value={formData.description}
                            onChange={(e) => updateFormData({ description: e.target.value })}
                            rows="4"
                        />
                    </div>

                    <div>
                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                            Game Name
                        </label>
                        <GameSelector
                            value={formData.gameName}
                            onChange={handleGameSelect}
                        />
                    </div>

                    {formData.gameType && (
                        <div>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                                Game Type
                            </label>
                            <input
                                type="text"
                                className="input"
                                value={formData.gameType}
                                disabled
                            />
                        </div>
                    )}

                    <div style={{ display: 'flex', justifyContent: 'flex-end' }}>
                        <button className="btn btn-primary" onClick={onNext} disabled={!isFormValid}>
                            Next
                        </button>
                    </div>
                </div>
            );
        }

        // Step 2: Tournament Configuration
        function TournamentConfigStep({ formData, updateFormData, onNext, onBack }) {
            const PARTICIPANT_OPTIONS = [12, 16, 24];

            const isFormValid = 
                formData.maxParticipants !== null && 
                formData.entryFee && 
                formData.registrationStartDate && 
                formData.registrationDeadline;

            return (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                    <div>
                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                            Max Participants
                        </label>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '1rem' }}>
                            {PARTICIPANT_OPTIONS.map((count) => (
                                <div
                                    key={count}
                                    className={`participant-card ${formData.maxParticipants === count ? 'selected' : ''}`}
                                    onClick={() => updateFormData({ maxParticipants: count })}
                                >
                                    <div style={{ fontSize: '1.5rem', fontWeight: 'bold' }}>{count}</div>
                                    <div style={{ fontSize: '0.875rem', color: '#6b7280' }}>Participants</div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {formData.maxParticipants && (
                        <div>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                                Bracket Preview
                            </label>
                            <BracketPreview participants={formData.maxParticipants} />
                            <p style={{ fontSize: '0.875rem', color: '#6b7280', marginTop: '0.5rem' }}>
                                This will create a {formData.maxParticipants}-player single-elimination bracket
                            </p>
                        </div>
                    )}

                    <div>
                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                            Entry Fee
                        </label>
                        <input
                            type="number"
                            className="input"
                            placeholder="0.00"
                            value={formData.entryFee}
                            onChange={(e) => updateFormData({ entryFee: e.target.value })}
                        />
                    </div>

                    <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '1rem' }}>
                        <div>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                                Registration Start Date
                            </label>
                            <input
                                type="datetime-local"
                                className="input"
                                value={formData.registrationStartDate}
                                onChange={(e) => updateFormData({ registrationStartDate: e.target.value })}
                            />
                        </div>

                        <div>
                            <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                                Registration Deadline
                            </label>
                            <input
                                type="datetime-local"
                                className="input"
                                value={formData.registrationDeadline}
                                onChange={(e) => updateFormData({ registrationDeadline: e.target.value })}
                            />
                        </div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between' }}>
                        <button className="btn btn-outline" onClick={onBack}>
                            Back
                        </button>
                        <button className="btn btn-primary" onClick={onNext} disabled={!isFormValid}>
                            Next
                        </button>
                    </div>
                </div>
            );
        }

        // Step 3: Final Step
        function TournamentFinalStep({ formData, updateFormData, onBack, onSaveDraft, onCreateTournament }) {
            const STATUS_OPTIONS = [
                { value: 'upcoming', label: 'Upcoming' },
                { value: 'ongoing', label: 'Ongoing' },
                { value: 'completed', label: 'Completed' },
            ];

            const isFormValid = formData.gameStartDate && formData.status;

            return (
                <div style={{ display: 'flex', flexDirection: 'column', gap: '1.5rem' }}>
                    <div>
                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                            Game Start Date
                        </label>
                        <input
                            type="datetime-local"
                            className="input"
                            value={formData.gameStartDate}
                            onChange={(e) => updateFormData({ gameStartDate: e.target.value })}
                        />
                    </div>

                    <div>
                        <label style={{ display: 'block', marginBottom: '0.5rem', fontSize: '0.875rem', fontWeight: '500' }}>
                            Tournament Status
                        </label>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(3, 1fr)', gap: '1rem' }}>
                            {STATUS_OPTIONS.map((option) => (
                                <div
                                    key={option.value}
                                    className={`status-card ${option.value === 'upcoming' ? `status-${option.value}` : ''}`}
                                    style={option.value !== 'upcoming' ? { opacity: 0.5, cursor: 'not-allowed' } : {}}
                                >
                                    {option.label}
                                </div>
                            ))}
                        </div>
                    </div>

                    <div style={{ backgroundColor: '#f9fafb', borderRadius: '0.5rem', padding: '1.5rem' }}>
                        <h3 style={{ fontWeight: '600', fontSize: '1.125rem', marginBottom: '1rem' }}>
                            Tournament Summary
                        </h3>
                        <div style={{ display: 'grid', gridTemplateColumns: 'repeat(2, 1fr)', gap: '1rem', fontSize: '0.875rem' }}>
                            <div>
                                <span style={{ color: '#6b7280' }}>Title:</span>
                                <p style={{ fontWeight: '500' }}>{formData.title || '-'}</p>
                            </div>
                            <div>
                                <span style={{ color: '#6b7280' }}>Game:</span>
                                <p style={{ fontWeight: '500' }}>{formData.gameName || '-'}</p>
                            </div>
                            <div>
                                <span style={{ color: '#6b7280' }}>Game Type:</span>
                                <p style={{ fontWeight: '500' }}>{formData.gameType || '-'}</p>
                            </div>
                            <div>
                                <span style={{ color: '#6b7280' }}>Max Participants:</span>
                                <p style={{ fontWeight: '500' }}>{formData.maxParticipants || '-'}</p>
                            </div>
                            <div>
                                <span style={{ color: '#6b7280' }}>Entry Fee:</span>
                                <p style={{ fontWeight: '500' }}>${formData.entryFee || '0'}</p>
                            </div>
                            <div>
                                <span style={{ color: '#6b7280' }}>Status:</span>
                                <p style={{ fontWeight: '500', textTransform: 'capitalize' }}>{formData.status}</p>
                            </div>
                        </div>
                    </div>

                    <div style={{ display: 'flex', justifyContent: 'space-between', gap: '1rem' }}>
                        <button className="btn btn-outline" onClick={onBack}>
                            Back
                        </button>
                        <div style={{ display: 'flex', gap: '0.5rem' }}>
                            <button className="btn btn-outline" onClick={onSaveDraft}>
                                💾 Save as Draft
                            </button>
                            <button className="btn btn-primary" onClick={onCreateTournament} disabled={!isFormValid}>
                                🏆 Create Tournament
                            </button>
                        </div>
                    </div>
                </div>
            );
        }

        // Main App Component
        function App() {
            const [currentStep, setCurrentStep] = useState(1);
            const [formData, setFormData] = useState({
                title: '',
                description: '',
                gameName: '',
                gameType: '',
                maxParticipants: null,
                entryFee: '',
                registrationStartDate: '',
                registrationDeadline: '',
                gameStartDate: '',
                status: 'upcoming',
            });

            const totalSteps = 3;
            const progress = (currentStep / totalSteps) * 100;

            const updateFormData = (data) => {
                setFormData((prev) => ({ ...prev, ...data }));
            };

            const handleNext = () => {
                if (currentStep < totalSteps) {
                    setCurrentStep(currentStep + 1);
                }
            };

            const handleBack = () => {
                if (currentStep > 1) {
                    setCurrentStep(currentStep - 1);
                }
            };

            const handleSaveDraft = () => {
                console.log('Saving as draft:', formData);
                alert('Tournament saved as draft!');
            };

            const handleCreateTournament = () => {
                console.log('Creating tournament:', formData);
                alert('Tournament created successfully!');
            };

            return (
                <div style={{ minHeight: '100vh', padding: '2rem' }}>
                    <div style={{ maxWidth: '56rem', margin: '0 auto' }}>
                        <h1 style={{ fontSize: '2rem', fontWeight: 'bold', textAlign: 'center', marginBottom: '2rem' }}>
                            Create Tournament
                        </h1>
                        
                        <div className="card">
                            <div className="card-header">
                                <h2 style={{ fontSize: '1.25rem', fontWeight: '600' }}>
                                    Step {currentStep} of {totalSteps}
                                </h2>
                                <div className="progress-bar">
                                    <div className="progress-fill" style={{ width: `${progress}%` }}></div>
                                </div>
                            </div>
                            <div className="card-content">
                                {currentStep === 1 && (
                                    <TournamentDetailsStep
                                        formData={formData}
                                        updateFormData={updateFormData}
                                        onNext={handleNext}
                                    />
                                )}
                                {currentStep === 2 && (
                                    <TournamentConfigStep
                                        formData={formData}
                                        updateFormData={updateFormData}
                                        onNext={handleNext}
                                        onBack={handleBack}
                                    />
                                )}
                                {currentStep === 3 && (
                                    <TournamentFinalStep
                                        formData={formData}
                                        updateFormData={updateFormData}
                                        onBack={handleBack}
                                        onSaveDraft={handleSaveDraft}
                                        onCreateTournament={handleCreateTournament}
                                    />
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            );
        }

        // Render the app
        ReactDOM.render(<App />, document.getElementById('root'));
    </script>
</body>


</html>