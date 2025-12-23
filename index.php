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
</div>
<div class="hero-bg">
                <img src="https://images.unsplash.com/photo-1553492206-f609eddc33dd?crop=entropy&cs=tinysrgb&fit=max&fm=jpg&ixid=M3w3Nzg4Nzd8MHwxfHNlYXJjaHwxfHxlc3BvcnRzJTIwZ2FtaW5nJTIwYXJlbmF8ZW58MXx8fHwxNzY2Mjg1MzkxfDA&ixlib=rb-4.1.0&q=80&w=1080" alt="Gaming Arena">
                <div class="hero-overlay"></div>
            </div>

<!-- 3D HERO SECTION -->
<section class="hero-3d">
    <canvas id="bg"></canvas>

    <div class="hero-text">
        <h1>Galactic Tournaments</h1>
        <p>
            Enter the cosmic arena. Battle for supremacy in the stars.
        </p>
    </div>
</section>

<!-- NORMAL CONTENT BELOW -->
<section class="normal-content">
</section>


<section class="hero">
    <div class="hero-bg"></div>
    <div class="hero-content">
        <h1>Compete. Win. Dominate.</h1>
        <p>The ultimate platform for competitive gaming tournaments. Join events, challenge top players, and claim your victory.</p>
        <button><span>Join Tournament</span></button>
        <button><span>Register Tour</span></button>
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

<script>
    const header = document.querySelector("header");

    window.addEventListener("scroll", () => {
        if (window.scrollY > 50) {
            header.classList.add("scrolled");
        } else {
            header.classList.remove("scrolled");
        }
    });

    const heroBg = document.querySelector('.hero-bg');

    window.addEventListener('scroll', () => {
        const scrollPosition = window.scrollY;
        // Move background up/down slowly relative to scroll
        heroBg.style.transform = `translateY(${scrollPosition * 0.3}px)`;
    });
</script>

<?php
include('partial/footer.php');
?>