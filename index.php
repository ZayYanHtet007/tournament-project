<?php
include('partial/header.php');
?>

<?php
// Optional: character color from PHP
$characterColor = 0xff6600;
?>

<section>
    <div class="title">
        Tournaments
    </div>
</section>

<script src="https://cdn.jsdelivr.net/npm/three@0.155.0/build/three.min.js"></script>

<script>
    const scene = new THREE.Scene();
    const camera = new THREE.PerspectiveCamera(75, window.innerWidth / window.innerHeight, 0.1, 1000);
    const renderer = new THREE.WebGLRenderer({
        antialias: true
    });
    renderer.setSize(window.innerWidth, window.innerHeight);
    document.body.appendChild(renderer.domElement);

    // Add a plane for reference
    const planeGeometry = new THREE.PlaneGeometry(100, 100);
    const planeMaterial = new THREE.MeshBasicMaterial({
        color: 0x222222,
        side: THREE.DoubleSide
    });
    const plane = new THREE.Mesh(planeGeometry, planeMaterial);
    plane.rotation.x = -Math.PI / 2;
    scene.add(plane);

    // Create a simple character (cube)
    const characterGeometry = new THREE.BoxGeometry(1, 2, 1);
    const characterMaterial = new THREE.MeshBasicMaterial({
        color: <?php echo $characterColor; ?>
    });
    const character = new THREE.Mesh(characterGeometry, characterMaterial);
    character.position.y = 1;
    scene.add(character);

    camera.position.set(0, 5, 10);
    camera.lookAt(0, 0, 0);

    // Track mouse
    let mouse = {
        x: 0,
        y: 0
    };
    document.addEventListener('mousemove', event => {
        // Convert screen coordinates to normalized device coordinates (-1 to 1)
        mouse.x = (event.clientX / window.innerWidth) * 2 - 1;
        mouse.y = -(event.clientY / window.innerHeight) * 2 + 1;
    });

    // Raycaster to find cursor position on the plane
    const raycaster = new THREE.Raycaster();

    function animate() {
        requestAnimationFrame(animate);

        // Update raycaster
        raycaster.setFromCamera(mouse, camera);
        const intersects = raycaster.intersectObject(plane);

        if (intersects.length > 0) {
            const point = intersects[0].point;
            // Make character look at cursor
            character.lookAt(point.x, character.position.y, point.z);
        }

        renderer.render(scene, camera);
    }

    animate();

    // Handle resize
    window.addEventListener('resize', () => {
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
        heroBg.style.transform = translateY($ {
                scrollPosition * 0.3
            }
            px);
    });
</script>


<?php
include('partial/footer.php');
?>