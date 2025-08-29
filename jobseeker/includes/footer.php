    </div> <!-- End main-content -->
    
    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Wait for the DOM to be fully loaded
        document.addEventListener('DOMContentLoaded', function() {
            // Only initialize collapse for mobile menu
            var collapseElements = document.querySelectorAll('.collapse');
            collapseElements.forEach(function(collapse) {
                new bootstrap.Collapse(collapse, {
                    toggle: false
                });
            });
            
            // Handle mobile menu closing when clicking outside
            document.addEventListener('click', function(event) {
                var navbarCollapse = document.getElementById('jobseekerNav');
                var targetElement = event.target;
                
                if (window.innerWidth < 992 && // Only on mobile
                    navbarCollapse && 
                    navbarCollapse.classList.contains('show') && // Menu is open
                    !navbarCollapse.contains(targetElement) && // Click outside menu
                    !targetElement.classList.contains('navbar-toggler')) { // Not the toggle button
                    
                    new bootstrap.Collapse(navbarCollapse).hide();
                }
            });
        });
    </script>
</body>
</html> 