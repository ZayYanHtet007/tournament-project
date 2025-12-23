<?php
include('partial/header.php');
?>

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

<script type="importmap">
{
  "imports": {
    "three": "https://unpkg.com/three@0.160.0/build/three.module.js",
    "three/addons/": "https://unpkg.com/three@0.160.0/examples/jsm/"
  }
}
</script>

<script type="module">
import * as THREE from 'three';
import { GLTFLoader } from 'three/addons/loaders/GLTFLoader.js';

// SCENE
const scene = new THREE.Scene();

// CAMERA
const camera = new THREE.PerspectiveCamera(
    70,
    window.innerWidth / window.innerHeight,
    0.1,
    2000
);
camera.position.set(0, 1.5, 6);

// Clock for animations
const clock = new THREE.Clock();

// RENDERER
const renderer = new THREE.WebGLRenderer({
    canvas: document.getElementById("bg"),
    antialias: true,
    alpha: true
});
renderer.setSize(window.innerWidth, window.innerHeight);
renderer.setPixelRatio(window.devicePixelRatio);

// 🌌 GALAXY BACKGROUND
function galaxy(color, count, size, spread) {
    const geo = new THREE.BufferGeometry();
    const pos = [];

    for (let i = 0; i < count; i++) {
        pos.push(
            (Math.random() - 0.5) * spread,
            (Math.random() - 0.5) * spread,
            (Math.random() - 0.5) * spread
        );
    }

    geo.setAttribute("position", new THREE.Float32BufferAttribute(pos, 3));

    return new THREE.Points(
        geo,
        new THREE.PointsMaterial({
            color,
            size,
            transparent: true,
            opacity: 0.8
        })
    );
}

scene.add(galaxy(0xffffff, 2500, 0.6, 1200));
scene.add(galaxy(0x6a0dad, 1800, 1.4, 900));
scene.add(galaxy(0x00bfff, 1500, 1.2, 800));
scene.add(galaxy(0xff4500, 800, 1.8, 700));
scene.add(galaxy(0x32cd32, 600, 1.6, 500));

// Add fog for depth
scene.fog = new THREE.FogExp2(0x000011, 0.0005);

// 💡 LIGHTS
scene.add(new THREE.AmbientLight(0x4444ff, 0.4));

const light = new THREE.PointLight(0xffffff, 1.5);
light.position.set(5, 6, 8);
scene.add(light);

const dirLight = new THREE.DirectionalLight(0xffffff, 0.5);
dirLight.position.set(-1, 1, 1);
scene.add(dirLight);

// 🧍 CHARACTER (REAL 3D MODEL)
const loader = new GLTFLoader();
let character;
let mixer;

loader.load(
    'https://threejs.org/examples/models/gltf/RobotExpressive/RobotExpressive.glb',
    function (gltf) {
        character = gltf.scene;
        character.position.set(-1.8, -1, 0);
        character.scale.set(0.5, 0.5, 0.5);
        scene.add(character);

        // Add animation
        mixer = new THREE.AnimationMixer(character);
        if (gltf.animations.length > 0) {
            const action = mixer.clipAction(gltf.animations[0]);
            action.play();
        }
    },
    undefined,
    function (error) {
        console.error('An error happened loading the model:', error);
        // Fallback to cube
        const charGeo = new THREE.BoxGeometry(1, 2, 1);
        const charMat = new THREE.MeshStandardMaterial({ color: 0xff6600 });
        character = new THREE.Mesh(charGeo, charMat);
        character.position.set(-1.8, -1, 0);
        scene.add(character);
    }
);

// 🖱 MOUSE FOLLOW
let mouseX = 0;
let mouseY = 0;

window.addEventListener("mousemove", e => {
    mouseX = (e.clientX / window.innerWidth - 0.5) * 0.6;
    mouseY = (e.clientY / window.innerHeight - 0.5) * 0.6;
});

// 🖱 SCROLL → MOVE LEFT ONLY
let scrollTarget = -1.8;
const maxLeft = -3;

const heroSection = document.querySelector(".hero-3d");

// SCROLL → MOVE LEFT ONLY (LOCAL)
window.addEventListener("scroll", () => {
    const heroHeight = heroSection.offsetHeight;
    const scrollY = Math.min(window.scrollY, heroHeight);

    scrollTarget = Math.max(-3, -1.8 - scrollY * 0.002);
});


// 🎥 ANIMATE
function animate() {
    requestAnimationFrame(animate);

    const delta = clock.getDelta();

    scene.rotation.y += 0.0003;

    if (character) {
        character.position.x += (scrollTarget - character.position.x) * 0.05;
        character.rotation.y += (mouseX - character.rotation.y) * 0.05;
        character.rotation.x += (-mouseY - character.rotation.x) * 0.05;
    }

    if (mixer) mixer.update(delta);

    renderer.render(scene, camera);
}

animate();

// RESIZE
window.addEventListener("resize", () => {
    camera.aspect = window.innerWidth / window.innerHeight;
    camera.updateProjectionMatrix();
    renderer.setSize(window.innerWidth, window.innerHeight);
});

</script>

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