<?php
include('partial/header.php');
?>

<div class="main-wrapper">
        <!-- Animated background gradient -->
        <div class="bg-gradient"></div>
        
        <!-- Grid overlay -->
        <div class="grid-overlay"></div>
        
        <!-- Floating 3D elements -->
        <div class="floating-elements">
            <?php for($i = 0; $i < 8; $i++): ?>
            <div class="floating-cube cube-<?php echo $i; ?>">
                <div class="cube-inner"></div>
            </div>
            <?php endfor; ?>
            
            <?php for($i = 0; $i < 5; $i++): ?>
            <div class="glowing-orb orb-<?php echo $i; ?>"></div>
            <?php endfor; ?>
            
            <?php for($i = 0; $i < 6; $i++): ?>
            <div class="hexagon hex-<?php echo $i; ?>">
                <svg viewBox="0 0 100 100">
                    <polygon points="50 1 95 25 95 75 50 99 5 75 5 25" fill="none" stroke-width="2" opacity="0.3"/>
                </svg>
            </div>
            <?php endfor; ?>
        </div>

        <!-- Hero Section -->
        <section class="hero">
            <div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1553492206-f609eddc33dd?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlc3BvcnRzJTIwZ2FtaW5nJTIwYXJlbmF8ZW58MXx8fHwxNzY2Mjg1MzkxfDA&ixlib=rb-4.1.0&q=80&w=1080" alt="Gaming Arena">
                <div class="hero-overlay"></div>
            </div>

            <div class="hero-shape-1"></div>
            <div class="hero-shape-2"></div>

            <div class="container hero-container">
                <div class="hero-content">

                    <h1 class="hero-title">
                        <span class="gradient-text">COMPETE</span>
                        <br>
                        <span>FOR GLORY</span>
                    </h1>

                    <p class="hero-subtitle">
                        Join the world's premier esports tournament platform. Compete against the best,
                        win epic prizes, and become a legend.
                    </p>

                    <div class="hero-cta">
                        <button class="btn-large btn-gradient">
                            <span>Join Tournament</span>
                        </button>
                        <button class="btn-large btn-outline">Watch Live</button>
                    </div>

                    <div class="hero-stats">
                        <div class="stat-card stat-card-1">
                            <div class="stat-icon icon-cyan">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                            </div>
                            <div class="stat-value gradient-cyan">$2.5M+</div>
                            <div class="stat-label">Total Prize Pool</div>
                        </div>

                        <div class="stat-card stat-card-2">
                            <div class="stat-icon icon-purple">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                            </div>
                            <div class="stat-value gradient-purple">150K+</div>
                            <div class="stat-label">Active Players</div>
                        </div>

                        <div class="stat-card stat-card-3">
                            <div class="stat-icon icon-pink">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><circle cx="12" cy="12" r="6"/><circle cx="12" cy="12" r="2"/></svg>
                            </div>
                            <div class="stat-value gradient-pink">500+</div>
                            <div class="stat-label">Live Tournaments</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="scroll-indicator">
                <div class="scroll-mouse">
                    <div class="scroll-wheel"></div>
                </div>
            </div>
        </section>

        <!-- Stats Section -->
        <section class="stats-section">
            <div class="container">
                <div class="stats-wrapper">
                    <div class="stats-bg-pattern"></div>
                    <div class="floating-shape-circle"></div>
                    <div class="floating-shape-square"></div>

                    <div class="stats-grid">
                        <?php 
                        $stats = [
                            ['value' => '2.5M+', 'label' => 'Registered Players', 'gradient' => 'cyan', 'icon' => 'users'],
                            ['value' => '15K+', 'label' => 'Tournaments Hosted', 'gradient' => 'purple', 'icon' => 'trophy'],
                            ['value' => '50+', 'label' => 'Supported Games', 'gradient' => 'yellow', 'icon' => 'gamepad'],
                            ['value' => '$100M+', 'label' => 'Total Prizes Awarded', 'gradient' => 'pink', 'icon' => 'zap']
                        ];
                        foreach($stats as $index => $stat): 
                        ?>
                        <div class="stats-item stats-item-<?php echo $index; ?>">
                            <div class="stats-icon-wrapper">
                                <div class="stats-icon icon-<?php echo $stat['gradient']; ?>">
                                    <?php if($stat['icon'] == 'users'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    <?php elseif($stat['icon'] == 'trophy'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                                    <?php elseif($stat['icon'] == 'gamepad'): ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="6" x2="10" y1="12" y2="12"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="15" x2="15.01" y1="13" y2="13"/><line x1="18" x2="18.01" y1="11" y2="11"/><rect width="20" height="12" x="2" y="6" rx="2"/></svg>
                                    <?php else: ?>
                                    <svg xmlns="http://www.w3.org/2000/svg" width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/></svg>
                                    <?php endif; ?>
                                </div>
                                <div class="stats-icon-ring"></div>
                            </div>
                            <div class="stats-value gradient-<?php echo $stat['gradient']; ?>"><?php echo $stat['value']; ?></div>
                            <div class="stats-label"><?php echo $stat['label']; ?></div>
                            <div class="stats-particles">
                                <div class="particle particle-1"></div>
                                <div class="particle particle-2"></div>
                                <div class="particle particle-3"></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </section>

        <!-- Tournaments Section -->
        <section class="tournaments-section" id="tournaments">
            <div class="container">
                <div class="section-header">
                    <h2 class="section-title">
                        <span class="gradient-text">FEATURED TOURNAMENTS</span>
                    </h2>
                    <p class="section-subtitle">Join the biggest esports competitions and prove your skills</p>
                </div>

                <div class="tournaments-grid">
                    <?php 
                    $tournaments = [
                        [
                            'title' => 'Apex Legends Championship',
                            'game' => 'Apex Legends',
                            'prize' => '$50,000',
                            'players' => '128/128',
                            'date' => 'Dec 28, 2025',
                            'status' => 'Live',
                            'image' => 'https://images.unsplash.com/photo-1688377051459-aebb99b42bff?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxjeWJlcnB1bmslMjBuZW9uJTIwY2l0eXxlbnwxfHx8fDE3NjYzNDI3MDJ8MA&ixlib=rb-4.1.0&q=80&w=1080',
                            'gradient' => 'red-orange'
                        ],
                        [
                            'title' => 'Valorant Masters',
                            'game' => 'Valorant',
                            'prize' => '$75,000',
                            'players' => '64/64',
                            'date' => 'Dec 30, 2025',
                            'status' => 'Upcoming',
                            'image' => 'https://images.unsplash.com/photo-1628089700970-0012c5718efc?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxnYW1pbmclMjBrZXlib2FyZCUyMGxpZ2h0c3xlbnwxfHx8fDE3NjYzNzI5NzF8MA&ixlib=rb-4.1.0&q=80&w=1080',
                            'gradient' => 'pink-purple'
                        ],
                        [
                            'title' => 'CS:GO Pro League',
                            'game' => 'Counter-Strike',
                            'prize' => '$100,000',
                            'players' => '32/32',
                            'date' => 'Jan 5, 2026',
                            'status' => 'Registration Open',
                            'image' => 'https://images.unsplash.com/photo-1553492206-f609eddc33dd?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlc3BvcnRzJTIwZ2FtaW5nJTIwYXJlbmF8ZW58MXx8fHwxNzY2Mjg1MzkxfDA&ixlib=rb-4.1.0&q=80&w=1080',
                            'gradient' => 'cyan-blue'
                        ]
                    ];
                    foreach($tournaments as $tournament): 
                    ?>
                    <div class="tournament-card">
                        <div class="tournament-image">
                            <img src="<?php echo $tournament['image']; ?>" alt="<?php echo $tournament['title']; ?>">
                            <div class="tournament-image-overlay"></div>
                            <div class="tournament-status status-<?php echo strtolower($tournament['status']); ?> gradient-<?php echo $tournament['gradient']; ?>">
                                <?php echo $tournament['status']; ?>
                            </div>
                            <?php if($tournament['status'] == 'Live'): ?>
                            <div class="tournament-live-indicator">
                                <span class="live-dot"></span>
                                <span>LIVE</span>
                            </div>
                            <?php endif; ?>
                        </div>

                        <div class="tournament-content">
                            <div class="tournament-game"><?php echo $tournament['game']; ?></div>
                            <h3 class="tournament-title"><?php echo $tournament['title']; ?></h3>

                            <div class="tournament-info">
                                <div class="info-item">
                                    <svg class="icon-yellow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>
                                    <span>Prize Pool: <strong><?php echo $tournament['prize']; ?></strong></span>
                                </div>
                                <div class="info-item">
                                    <svg class="icon-cyan" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                                    <span>Players: <strong><?php echo $tournament['players']; ?></strong></span>
                                </div>
                                <div class="info-item">
                                    <svg class="icon-purple" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>
                                    <span><?php echo $tournament['date']; ?></span>
                                </div>
                            </div>

                            <button class="btn-tournament gradient-<?php echo $tournament['gradient']; ?>">
                                View Tournament
                            </button>
                        </div>

                        <div class="tournament-glow"></div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <div class="section-footer">
                    <button class="btn-view-all">
                        <span>View All Tournaments</span>
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                    </button>
                </div>
            </div>
        </section>
    </div>
<?php
include('partial/footer.php');
?>