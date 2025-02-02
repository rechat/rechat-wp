<?php
$property_types_array = !empty($atts['property_types']) ? explode(',', $atts['property_types']) : [];
if (isset($atts['show_filter_bar']) && $atts['show_filter_bar'] === '1') {
    // Include the template if the condition is met
    include RCH_PLUGIN_DIR . 'templates/archive/template-part/listing-filters.php';
}
?>

<div id="listing-list" class="rch-listing-list"></div>
<!-- <div id="map" style="height: 400px; width: 100%;"></div> -->
<!-- Loading spinner -->
<div id="rch-loading-listing" style="display: none;" class="rch-listing-skeleton-loader">
    <?php
    // Loop to display 6 skeleton items
    for ($i = 0; $i < 6; $i++) : ?>
        <div class="rch-listing-item-skeleton">
            <div class="rch-skeleton-image"></div>
            <h3 class="rch-skeleton-text rch-skeleton-price"></h3>
            <p class="rch-skeleton-text rch-skeleton-address"></p>
            <ul>
                <li class="rch-skeleton-text rch-skeleton-list-item"></li>
                <li class="rch-skeleton-text rch-skeleton-list-item"></li>
                <li class="rch-skeleton-text rch-skeleton-list-item"></li>
            </ul>
        </div>
    <?php endfor; ?>
</div>

<?php include RCH_PLUGIN_DIR . 'templates/archive/template-part/listing-pagination.php'; ?>

</div>

<script>
    let currentPage = 1; // Initialize current page
    let listingPerPage = <?php echo esc_js($listingPerPage); ?>; // Set number of listings per page
    let totalListing = <?php echo esc_js($totalLisitng); ?>; // Set total number of listings
    let totalPages = Math.ceil(totalListing / listingPerPage); // Calculate total pages

    // Extract filters passed through the shortcode attributes
    

    // JavaScript object to hold your filters
    let defaultFilters = {
        brand: "<?php echo esc_js($atts['own_listing'] == 1 ? $atts['brand'] : ''); ?>",
        minimum_price: "<?php echo esc_js($atts['minimum_price']); ?>",
        maximum_price: "<?php echo esc_js($atts['maximum_price']); ?>",
        minimum_lot_square_meters: "<?php echo esc_js($atts['minimum_lot_square_meters']); ?>",
        maximum_lot_square_meters: "<?php echo esc_js($atts['maximum_lot_square_meters']); ?>",
        minimum_bathrooms: "<?php echo esc_js($atts['minimum_bathrooms']); ?>",
        maximum_bathrooms: "<?php echo esc_js($atts['maximum_bathrooms']); ?>",
        minimum_square_meters: "<?php echo esc_js($atts['minimum_square_meters']); ?>",
        maximum_square_meters: "<?php echo esc_js($atts['maximum_square_meters']); ?>",
        minimum_year_built: "<?php echo esc_js($atts['minimum_year_built']); ?>",
        maximum_year_built: "<?php echo esc_js($atts['maximum_year_built']); ?>",
        minimum_bedrooms: "<?php echo esc_js($atts['minimum_bedrooms']); ?>",
        maximum_bedrooms: "<?php echo esc_js($atts['maximum_bedrooms']); ?>",
        listing_statuses: "<?php echo esc_js($atts['listing_statuses']); ?>",
        property_types: <?php echo json_encode($property_types_array); ?>, // Convert PHP array to JavaScript array
    };
    let filters = {
        brand: "<?php echo esc_js($atts['own_listing'] == 1 ? $atts['brand'] : ''); ?>",
        minimum_price: "<?php echo esc_js($atts['minimum_price']); ?>",
        maximum_price: "<?php echo esc_js($atts['maximum_price']); ?>",
        minimum_lot_square_meters: "<?php echo esc_js($atts['minimum_lot_square_meters']); ?>",
        maximum_lot_square_meters: "<?php echo esc_js($atts['maximum_lot_square_meters']); ?>",
        minimum_bathrooms: "<?php echo esc_js($atts['minimum_bathrooms']); ?>",
        maximum_bathrooms: "<?php echo esc_js($atts['maximum_bathrooms']); ?>",
        minimum_square_meters: "<?php echo esc_js($atts['minimum_square_meters']); ?>",
        maximum_square_meters: "<?php echo esc_js($atts['maximum_square_meters']); ?>",
        minimum_year_built: "<?php echo esc_js($atts['minimum_year_built']); ?>",
        maximum_year_built: "<?php echo esc_js($atts['maximum_year_built']); ?>",
        minimum_bedrooms: "<?php echo esc_js($atts['minimum_bedrooms']); ?>",
        maximum_bedrooms: "<?php echo esc_js($atts['maximum_bedrooms']); ?>",
        listing_statuses: "<?php echo esc_js($atts['listing_statuses']); ?>",
        minimum_parking_spaces: "",
        minimum_year_built: "",
        maximum_year_built: "",
        postal_codes: [],
        property_types: <?php echo json_encode($property_types_array); ?>, // Convert PHP array to JavaScript array
        minimum_square_meters: "",
        maximum_square_meters: "",
        content: "",
    };

    // Initialize Google Map
    // let map;

    // // Initialize the Google map
    // function initMap() {
    //     map = new google.maps.Map(document.getElementById('map'), {
    //         center: {
    //             lat: 37.7749,
    //             lng: -122.4194
    //         }, // Center of the map
    //         zoom: 10,
    //     });

    //     // Listener for map bounds change when dragging ends
    //     google.maps.event.addListener(map, 'dragend', function() {
    //         // Get the bounds of the map
    //         const bounds = map.getBounds();
    //         const ne = bounds.getNorthEast(); // North-East
    //         const sw = bounds.getSouthWest(); // South-West

    //         // Calculate the South-East and North-West points
    //         const se = new google.maps.LatLng(sw.lat(), ne.lng()); // South-East
    //         const nw = new google.maps.LatLng(ne.lat(), sw.lng()); // North-West

    //         // Create an array of coordinates
    //         const latLngs = [{
    //                 lat: ne.lat(),
    //                 lng: sw.lng()
    //             }, // North-West
    //             {
    //                 lat: ne.lat(),
    //                 lng: ne.lng()
    //             }, // North-East
    //             {
    //                 lat: sw.lat(),
    //                 lng: ne.lng()
    //             }, // South-East
    //             {
    //                 lat: sw.lat(),
    //                 lng: sw.lng()
    //             }, // South-West
    //             {
    //                 lat: ne.lat(),
    //                 lng: sw.lng()
    //             }, // South-West
    //         ];

    //         // Convert latLngs to a query string
    //         const queryString = latLngs
    //             .map(point => `${point.lat},${point.lng}`) // Convert each point to "lat,lng"
    //             .join('|'); // Join with "|" separator

    //         // Update filters with serialized points
    //         filters.points = queryString;
    //         // Call the function to update listings
    //         updateListingList();
    //     });
    // }


    function updateListingList() {
        const listingList = document.getElementById('listing-list');
        const loading = document.getElementById('rch-loading-listing');
        const pagination = document.getElementById('pagination');
        listingList.style.display = 'none';
        listingList.innerHTML = ''; // Clear the listing content
        loading.style.display = 'grid'; // Show loading spinner
        pagination.style.display = 'none'; // Hide pagination while loading
        let queryString = `?action=rch_fetch_listing&page=${currentPage}&listing_per_page=${listingPerPage}`;
        Object.keys(filters).forEach(key => {
            const filterValue = filters[key];
            if (filterValue || filterValue === 0) {
                if (Array.isArray(filterValue) && filterValue.length > 0) {
                    filterValue.forEach(value => {
                        queryString += `&${key}[]=${encodeURIComponent(value)}`;
                    });
                } else {
                    queryString += `&${key}=${encodeURIComponent(filterValue)}`;
                }
            }
        });
        console.log(filters)
        fetch('<?php echo esc_url(admin_url('admin-ajax.php')); ?>', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'rch_fetch_listing',
                    page: currentPage,
                    listing_per_page: listingPerPage,
                    ...filters,
                }),
            })

            .then(response => response.json())
            .then(data => {
                loading.style.display = 'none'; // Hide the loading spinner
                listingList.style.display = 'grid'; // Show the listing container
                pagination.style.display = 'flex'; // Show pagination

                if (data.data.listings && Array.isArray(data.data.listings) && data.data.listings.length > 0) {
                    const listings = data.data.listings;
                    const mapMarkers = []; // To store map markers
                    // Clear previous content in listingList
                    listingList.innerHTML = '';

                    // Loop through listings
                    listings.forEach(listing => {
                        // Inject the HTML content into the listing container
                        listingList.innerHTML += listing.content;
                        // Check if lat and lng are valid
                        // if (listing.lat && listing.lng) {
                        //     const latLng = {
                        //         lat: listing.lat,
                        //         lng: listing.lng
                        //     };

                        //     // Add a marker to the map (if lat and lng are valid)
                        //     const marker = new google.maps.Marker({
                        //         position: latLng,
                        //         map: map, // Ensure you have a map variable initialized
                        //         title: `Listing at ${latLng.lat}, ${latLng.lng}`
                        //     });

                        //     // Optionally, add an info window to the marker
                        //     const infoWindow = new google.maps.InfoWindow({
                        //         content: `Listing at ${latLng.lat}, ${latLng.lng}`
                        //     });

                        //     marker.addListener('click', function() {
                        //         infoWindow.open(map, marker);
                        //     });

                        //     mapMarkers.push(marker); // Store the marker if you want to manage them later
                        // }
                    });

                    totalListing = data.data.total;
                    listingPerPage = data.data.listingPerPage;
                    totalPages = Math.ceil(totalListing / listingPerPage);

                    updatePagination(); // Update pagination
                } else {
                    listingList.innerHTML = `<p class="rch-no-listing">${data.data.message || 'No listings available.'}</p>`;
                    pagination.style.display = 'none'; // Hide pagination
                }
            })
            .catch(error => {
                loading.style.display = 'none'; // Hide loading spinner on error
                console.error('Error fetching listing:', error);
                listingList.innerHTML = '<p>Error loading listing.</p>';
            });
    }
    document.addEventListener('DOMContentLoaded', () => {
        // var statusesArray = filters.listing_statuses;
        // // Check if any status in the array matches a value in the radio buttons
        // statusesArray.forEach(function(status) {
        //     // Check if the status matches any radio button's value
        //     var radioButton = document.querySelector('input[name="property_types"][value*="' + status.trim() + '"]');
        //     if (radioButton) {
        //         radioButton.checked = true; // Set the matching radio button as checked
        //     }
        // });
        // // Check if minimum_bedrooms has a value
        // const minBedrooms = document.getElementById('minimum_bedrooms');
        // if (minBedrooms.value) {
        //     minBedrooms.dispatchEvent(new Event('change'));
        // }

        // // Check if maximum_bedrooms has a value
        // const maxBedrooms = document.getElementById('maximum_bedrooms');
        // if (maxBedrooms.value) {
        //     maxBedrooms.dispatchEvent(new Event('change'));
        // }
        // applyFilters()
    })

    // Handle filter updates
</script>
<!-- <script src="https://maps.googleapis.com/maps/api/js?key=<?php echo get_option('rch_rechat_google_map_api_key'); ?>&callback=initMap" loading=async defer></script> -->