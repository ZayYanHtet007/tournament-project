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
<?php
include('partial/footer.php');
?>