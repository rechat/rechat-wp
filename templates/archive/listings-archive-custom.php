
    <div id="houses-list" class="rch-houses-list">
    </div>

    <!-- Loading spinner -->
    <div id="rch-loading-houses" style="display: none;" class="rch-loader">

    </div>

    <div id="pagination" class="rch-house-pagination">
        <button id="prev" onclick="changePage(-1)" disabled>Previous</button>
        <span id="page-info">Page 1 of <?php echo ceil($totalHouses / $housesPerPage); ?></span>
        <button id="next" onclick="changePage(1)">Next</button>
    </div>

    <script>
    let currentPage = 1; // Initialize current page
    const housesPerPage = <?php echo $housesPerPage; ?>; // Set number of houses per page
    const totalHouses = <?php echo $totalHouses; ?>; // Set total number of houses
    const totalPages = Math.ceil(totalHouses / housesPerPage); // Calculate total pages
    // Extract filters passed through the shortcode attributes
    const filters = {
        minimum_price: "<?php echo $atts['minimum_price']; ?>",
        maximum_price: "<?php echo $atts['maximum_price']; ?>",
        minimum_lot_square_meters: "<?php echo $atts['minimum_lot_square_meters']; ?>",
        maximum_lot_square_meters: "<?php echo $atts['maximum_lot_square_meters']; ?>",
        minimum_bathrooms: "<?php echo $atts['minimum_bathrooms']; ?>",
        maximum_bathrooms: "<?php echo $atts['maximum_bathrooms']; ?>",
        minimum_square_meters: "<?php echo $atts['minimum_square_meters']; ?>",
        maximum_square_meters: "<?php echo $atts['maximum_square_meters']; ?>",
        minimum_year_built: "<?php echo $atts['minimum_year_built']; ?>",
        maximum_year_built: "<?php echo $atts['maximum_year_built']; ?>",
        minimum_bedrooms: "<?php echo $atts['minimum_bedrooms']; ?>",
        maximum_bedrooms: "<?php echo $atts['maximum_bedrooms']; ?>"
    };

    function changePage(direction) {
        currentPage += direction; // Update current page
        updateHousesList(); // Update the house list based on current page
    }

    function updateHousesList() {
        const housesList = document.getElementById('houses-list');
        const loading = document.getElementById('rch-loading-houses'); // Get the loading element

        housesList.innerHTML = ''; // Clear existing houses
        loading.style.display = 'block'; // Show the loading indicator

        // Construct the query string with filters and pagination
        let queryString = `?action=rch_fetch_listing&page=${currentPage}&houses_per_page=${housesPerPage}`;
        Object.keys(filters).forEach(key => {
            if (filters[key]) queryString += `&${key}=${filters[key]}`;
        });

        // Fetch new houses for the current page with filters
        fetch(`<?php echo admin_url('admin-ajax.php'); ?>${queryString}`)
            .then(response => response.text()) // Read the response as text
            .then(html => {
                loading.style.display = 'none'; // Hide the loading indicator
                housesList.innerHTML = html; // Insert the returned HTML
                // Update pagination info
                document.getElementById('page-info').textContent = `Page ${currentPage} of ${totalPages}`;

                // Enable or disable pagination buttons
                document.getElementById('prev').disabled = currentPage === 1;
                document.getElementById('next').disabled = currentPage === totalPages;
            })
            .catch(error => {
                loading.style.display = 'none'; // Hide the loading indicator on error
                console.error('Error fetching houses:', error);
                housesList.innerHTML = '<p>Error loading houses.</p>';
            });
    }

    window.onload = function() {
        updateHousesList(); // Call updateHousesList when the window has loaded
    };
</script>
