
    <div id="listing-list" class="rch-houses-list">
    </div>

    <!-- Loading spinner -->
    <div id="rch-loading-listing" style="display: none;" class="rch-loader">

    </div>

    <div id="pagination" class="rch-house-pagination">
        <button id="prev" onclick="changePage(-1)" disabled>Previous</button>
        <span id="page-info">Page 1 of <?php echo ceil($totalLisitng / $listingPerPage); ?></span>
        <button id="next" onclick="changePage(1)">Next</button>
    </div>

    <script>
    let currentPage = 1; // Initialize current page
    const listingPerPage = <?php echo $listingPerPage; ?>; // Set number of listing per page
    const totalListing = <?php echo $totalLisitng; ?>; // Set total number of listing
    const totalPages = Math.ceil(totalListing / listingPerPage); // Calculate total pages
    // Extract filters passed through the shortcode attributes
    const filters = {
        brand: "<?php echo $atts['brand']; ?>",
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
        maximum_bedrooms: "<?php echo $atts['maximum_bedrooms']; ?>",
        
    };

    function changePage(direction) {
        currentPage += direction; // Update current page
        updateListingList(); // Update the listing list based on current page
    }

    function updateListingList() {
        const listingList = document.getElementById('listing-list');
        const loading = document.getElementById('rch-loading-listing'); // Get the loading element

        listingList.innerHTML = ''; // Clear existing listing
        loading.style.display = 'block'; // Show the loading indicator

        // Construct the query string with filters and pagination
        
        let queryString = `?action=rch_fetch_listing&page=${currentPage}&listing_per_page=${listingPerPage}`;
        Object.keys(filters).forEach(key => {
            if (filters[key]) queryString += `&${key}=${filters[key]}`;
        });
        // Fetch new listing for the current page with filters
        fetch(`<?php echo admin_url('admin-ajax.php'); ?>${queryString}`)
            .then(response => response.text()) // Read the response as text
            .then(html => {
                loading.style.display = 'none'; // Hide the loading indicator
                listingList.innerHTML = html; // Insert the returned HTML
                // Update pagination info
                document.getElementById('page-info').textContent = `Page ${currentPage} of ${totalPages}`;

                // Enable or disable pagination buttons
                document.getElementById('prev').disabled = currentPage === 1;
                document.getElementById('next').disabled = currentPage === totalPages;
            })
            .catch(error => {
                loading.style.display = 'none'; // Hide the loading indicator on error
                console.error('Error fetching listing:', error);
                listingList.innerHTML = '<p>Error loading listing.</p>';
            });
    }

    window.onload = function() {
        updateListingList(); // Call updatelistingsList when the window has loaded
    };
</script>
