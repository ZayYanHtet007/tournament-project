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


<section class="section">
    <h2>Upcoming Tournaments</h2>
    <div class="tournaments">
        <div class="card">
            <div class="card-text">
                <h3>Valorant Cup</h3>
                <p>5v5 competitive tournament with top teams.</p>
                <span>Prize Pool: $5,000</span>
            </div>
        </div>
        <div class="card">
            <div class="card-text">
                <h3>PUBG Showdown</h3>
                <p>Battle royale tournament for squads.</p>
                <span>Prize Pool: $3,000</span>
            </div>
        </div>
        <div class="card">
            <div class="card-text">
                <h3>CS2 Masters</h3>
                <p>High-level tactical FPS competition.</p>
                <span>Prize Pool: $4,000</span>
            </div>
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