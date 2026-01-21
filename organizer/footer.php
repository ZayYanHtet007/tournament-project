<footer>
  © 2025 TournaX. ALL RIGHTS RESERVED.
</footer>

<script>
  // Scroll progress bar
  const progressBar = document.getElementById("bar");
  if (progressBar) {
    window.addEventListener("scroll", function() {
      const scrollTop = window.pageYOffset || document.documentElement.scrollTop;
      const documentHeight = document.documentElement.scrollHeight - window.innerHeight;
      const scrollPercent = (scrollTop / documentHeight) * 100;
      progressBar.style.height = scrollPercent + "%";
    });
  }
</script>

</body>

</html>