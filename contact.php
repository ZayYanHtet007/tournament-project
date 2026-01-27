<?php include('partial/header.php'); ?>

<style>
body { background:#0b0e14; color:#fff; font-family:Segoe UI; }
.page { padding:80px 8%; }
form {
    max-width:500px;
}
input, textarea {
    width:100%;
    padding:12px;
    margin:10px 0;
    background:#11141d;
    border:none;
    color:#fff;
}
button {
    background:#ff4655;
    padding:12px;
    border:none;
    width:100%;
    font-weight:bold;
}
</style>

<section class="page">
    <h1 style="color:#ff4655;">Contact Us</h1>

    <form>
        <input type="text" placeholder="Your Name">
        <input type="email" placeholder="Email">
        <textarea placeholder="Message"></textarea>
        <button>Send</button>
    </form>
</section>

<?php include('partial/footer.php'); ?>
