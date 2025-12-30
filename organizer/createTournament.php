<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Tournament</title>
    <link rel="stylesheet" href="../css/createtour.css">
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <div class="header-icon">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                    <path d="M6 9H4.5a2.5 2.5 0 1 0 0 5H6m0-5v5m0-5h3m-3 5h3m9-5h1.5a2.5 2.5 0 0 1 0 5H18m0-5v5m0-5h-3m3 5h-3m-3-5v5m0 0v7a2 2 0 1 1-4 0v-7m4 0H9"/>
                </svg>
            </div>
            <h1>Create Tournament</h1>
            <p>Set up your gaming tournament and start competing</p>
        </div>

        <!-- Form Card -->
        <div class="card">
            <div class="card-header">
                <h2 class="card-title">Tournament Details</h2>
                <p class="card-description">Fill in the information below to create your tournament</p>
            </div>
            <div class="card-content">
                <form id="tournamentForm">
                    <!-- Basic Information -->
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="title">Tournament Title</label>
                                <input type="text" id="title" name="title" placeholder="e.g., Summer Championship 2024" required>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="description">Description</label>
                                <textarea id="description" name="description" placeholder="Describe your tournament..." required></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- Game Information -->
                    <div class="form-section">
                        <div class="form-row two-cols">
                            <div class="form-group">
                                <label for="game_name">Game Name</label>
                                <input type="text" id="game_name" name="game_name" placeholder="e.g., League of Legends" required>
                            </div>

                            <div class="form-group">
                                <label for="game_type">Game Type</label>
                                <select id="game_type" name="game_type" required>
                                    <option value="">Select game type</option>
                                    <option value="moba">MOBA</option>
                                    <option value="fps">FPS</option>
                                    <option value="battle_royale">Battle Royale</option>
                                    <option value="sports">Sports</option>
                                    <option value="fighting">Fighting</option>
                                    <option value="strategy">Strategy</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Tournament Format -->
                    <div class="form-section">
                        <div class="form-row two-cols">
                            <div class="form-group">
                                <label for="match_type">Match Type</label>
                                <select id="match_type" name="match_type" required>
                                    <option value="">Select match type</option>
                                    <option value="solo">Solo</option>
                                    <option value="duo">Duo</option>
                                    <option value="squad">Squad</option>
                                    <option value="team_5v5">Team 5v5</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label for="format">Tournament Format</label>
                                <select id="format" name="format" required>
                                    <option value="">Select format</option>
                                    <option value="single_elimination">Single Elimination</option>
                                    <option value="double_elimination">Double Elimination</option>
                                    <option value="round_robin">Round Robin</option>
                                    <option value="swiss">Swiss</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Participants & Fee -->
                    <div class="form-section">
                        <div class="form-row two-cols">
                            <div class="form-group">
                                <label for="max_participants">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                                        <circle cx="9" cy="7" r="4"/>
                                        <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                    </svg>
                                    Max Participants
                                </label>
                                <input type="number" id="max_participants" name="max_participants" placeholder="e.g., 64" required>
                            </div>

                            <div class="form-group">
                                <label for="fee">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <line x1="12" y1="1" x2="12" y2="23"/>
                                        <path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"/>
                                    </svg>
                                    Entry Fee
                                </label>
                                <input type="number" id="fee" name="fee" step="0.01" placeholder="e.g., 10.00" required>
                            </div>
                        </div>
                    </div>

                    <!-- Dates -->
                    <div class="form-section">
                        <div class="form-row two-cols">
                            <div class="form-group">
                                <label for="registration_deadline">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    Registration Deadline
                                </label>
                                <input type="date" id="registration_deadline" name="registration_deadline" required>
                            </div>

                            <div class="form-group">
                                <label for="start_date">
                                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                        <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                                        <line x1="16" y1="2" x2="16" y2="6"/>
                                        <line x1="8" y1="2" x2="8" y2="6"/>
                                        <line x1="3" y1="10" x2="21" y2="10"/>
                                    </svg>
                                    Start Date
                                </label>
                                <input type="date" id="start_date" name="start_date" required>
                            </div>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="form-section">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="status">Status</label>
                                <select id="status" name="status" required>
                                    <option value="draft">Draft</option>
                                    <option value="published">Published</option>
                                    <option value="registration_open">Registration Open</option>
                                    <option value="ongoing">Ongoing</option>
                                    <option value="completed">Completed</option>
                                    <option value="cancelled">Cancelled</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="button-group">
                        <button type="submit" class="btn-primary">Create Tournament</button>
                        <button type="button" class="btn-secondary" onclick="saveDraft()">Save as Draft</button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Info Cards -->
        <div class="info-cards">
            <div class="info-card">
                <div class="info-card-content">
                    <div class="info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">
                            <path d="M6 9H4.5a2.5 2.5 0 1 0 0 5H6m0-5v5m0-5h3m-3 5h3m9-5h1.5a2.5 2.5 0 0 1 0 5H18m0-5v5m0-5h-3m3 5h-3m-3-5v5m0 0v7a2 2 0 1 1-4 0v-7m4 0H9"/>
                        </svg>
                    </div>
                    <div class="info-text">
                        <h3>Format</h3>
                        <p>Choose your style</p>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-content">
                    <div class="info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/>
                            <circle cx="9" cy="7" r="4"/>
                            <path d="M22 21v-2a4 4 0 0 0-3-3.87"/>
                            <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                        </svg>
                    </div>
                    <div class="info-text">
                        <h3>Participants</h3>
                        <p>Set your limits</p>
                    </div>
                </div>
            </div>

            <div class="info-card">
                <div class="info-card-content">
                    <div class="info-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <rect x="3" y="4" width="18" height="18" rx="2" ry="2"/>
                            <line x1="16" y1="2" x2="16" y2="6"/>
                            <line x1="8" y1="2" x2="8" y2="6"/>
                            <line x1="3" y1="10" x2="21" y2="10"/>
                        </svg>
                    </div>
                    <div class="info-text">
                        <h3>Schedule</h3>
                        <p>Plan ahead</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Form submission handler
        document.getElementById('tournamentForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const data = Object.fromEntries(formData.entries());
            
            console.log('Tournament Data:', data);
            alert('Tournament created successfully!\n\nCheck console for details.');
            
            // You can add your API call or further processing here
        });

        // Save as draft function
        function saveDraft() {
            const formData = new FormData(document.getElementById('tournamentForm'));
            const data = Object.fromEntries(formData.entries());
            
            console.log('Draft saved:', data);
            alert('Tournament saved as draft!');
        }
    </script>
</body>
</html>
