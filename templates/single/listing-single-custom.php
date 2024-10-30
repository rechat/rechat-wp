<?php get_header() ?>
<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main content-container site-container">
        <div id="rch-house-detail" class="rch-house-main-details">
            <div class="rch-top-img-slider">
                <div class="rch-left-top-slider">
                    <?php if (is_array($house_detail['gallery_image_urls']) && !empty($house_detail['gallery_image_urls'])) { ?>
                        <picture data-slider="0" id="myBtn">
                            <img src="<?php echo esc_url($house_detail['cover_image_url']); ?>" alt="Image of House">
                        </picture>
                        <button id="myBtn" data-slider="0" class="rch-load-images">
                            <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>gallery.svg" alt="">
                            View all Photos
                        </button>
                    <?php
                    }
                    ?>
                </div>
                <div class="rch-right-top-slider">
                    <?php
                    // Ensure the gallery_image_urls is an array and has values
                    if (is_array($house_detail['gallery_image_urls']) && !empty($house_detail['gallery_image_urls'])) {
                        // Loop through the first 4 images
                        $i = 1;
                        foreach (array_slice($house_detail['gallery_image_urls'], 1, 4) as $image_url) {
                    ?>
                            <picture data-slider="<?php echo $i; ?>" id="myBtn">
                                <img src="<?php echo esc_url($image_url); ?>" alt="Gallery of House">
                            </picture>
                    <?php
                            $i++;
                        }
                    } else {
                        // Fallback message if no images are available
                        echo '';
                    }
                    ?>
                </div>
            </div>
            <h1 class="rch-single-address">

                <?php
                // Check if address property exists
                if (isset($house_detail['property']['address']['full_address'])) {
                    $address = $house_detail['property']['address']['full_address'];
                    $full_address = '';
                    echo esc_html($address);
                } else {
                    // Fallback message if address is not set
                    echo '<p>Address information not available.</p>';
                }
                ?>
            </h1>
            <div class="rch-single-price-house">
                <?php echo '$' . number_format(floatval($house_detail['price'])); ?>
            </div>
            <div class="rch-single-house-main-layout">
                <div class="rch-single-left-main-layout">
                    <div class="rch-top-main-nav-layout">
                        <div class="rch-house-menu">
                            <a href="#rch-overview">
                                Overview
                            </a>
                            <a href="#rch-facilities">
                                Facilities and Features
                            </a>
                            <a href="#rch-location">
                                Location on the Map
                            </a>
                        </div>
                        <div class="main-des-single-house" id="rch-overview">
                            <h2>
                                Property Description
                            </h2>
                            <p>
                                <?php echo esc_html($house_detail['property']['description']); ?>
                            </p>
                        </div>
                    </div>

                    <hr />
                    <div class="facilities-in-single-houses" id="rch-facilities">
                        <h2>
                            Facilities and Features
                        </h2>
                        <ul>
                            <li>
                                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>bedroomsingle.svg" alt="">
                                <?php echo esc_html($house_detail['property']['bedroom_count']); ?>
                                Bedrooms
                            </li>
                            <li>
                                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>fbathsingle.svg" alt="">
                                <?php echo esc_html($house_detail['property']['full_bathroom_count']); ?>
                                Full Bathrooms
                            </li>
                            <li>
                                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>hbathsingle.svg" alt="">
                                <?php echo esc_html($house_detail['property']['half_bathroom_count']); ?>
                                Half Bathrooms
                            </li>
                            <li>
                                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>areasingle.svg" alt="">
                                <?php echo esc_html($house_detail['property']['square_meters']); ?>
                                meter
                            </li>
                            <li>
                                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>yearsingle.svg" alt="">
                                <?php echo esc_html($house_detail['property']['year_built']); ?>
                                Year Built
                            </li>

                            <?php
                            if (!empty($house_detail['property']['pool_features'])) { ?>
                                <li>
                                    <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>poolsingle.svg" alt="">
                                    Pool
                                </li>
                            <?php
                            }
                            ?>
                            <?php
                            if (!empty($house_detail['property']['security_features'])) { ?>
                                <li>
                                    <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>securitysingle.svg" alt="">
                                    Security
                                </li>
                            <?php
                            }
                            ?>
                            <li>
                                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>garagesingle.svg" alt="">
                                <?php echo esc_html($house_detail['property']['number_of_parking_spaces']); ?>
                                Parking
                            </li>
                            <li>
                                <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>outdoor-activity.svg" alt="">
                                <?php echo esc_html($house_detail['property']['construction_materials']); ?>
                            </li>
                        </ul>
                    </div>
                    <hr />
                    <div class="location-single-houser" id="rch-location">
                        <h2>
                            Location on the Map
                        </h2>
                        <p>
                            Located in one of the city's most desirable neighborhoods, this home offers both serenity and convenience.
                        </p>
                        <div id="map" class="rch-map-single"></div> <!-- Map Container -->

                    </div>
                </div>
                <div class="rch-single-right-main-layout">
                </div>
            </div>
        </div>
    </main><!-- #main -->
</div><!-- #primary -->
<div id="myModal" class="rch-imgs-modal">

    <!-- Modal content -->
    <div class="rch-modal-content">
        <span class="rch-img-modal-close">&times;</span>
        <div class="swiper rch-houses-mySwiper2">
            <div class="swiper-wrapper">
                <?php foreach ($house_detail['gallery_image_urls'] as $attachment_url) { ?>
                    <div class="swiper-slide">
                        <picture>
                            <img src="<?php echo esc_url($attachment_url); ?>" alt="">
                        </picture>
                    </div>
                <?php } ?>
            </div>
            <div class="swiper-button-next"></div>
            <div class="swiper-button-prev"></div>
        </div>
        <div thumbsSlider="" class="swiper rch-houses-mySwiper">
            <div class="swiper-wrapper">
                <?php foreach ($house_detail['gallery_image_urls'] as $attachment_url) { ?>
                    <div class="swiper-slide">
                        <picture>
                            <img src="<?php echo esc_url($attachment_url); ?>" alt="">
                        </picture>
                    </div>
                <?php } ?>
            </div>
        </div>
    </div>

</div>
<?php get_footer() ?>
<script src="https://maps.googleapis.com/maps/api/js?key=API_KEY&callback=initMap&language=en"></script>
<script>
    function initMap() {
        // Get latitude and longitude from PHP
        var latitude = <?php echo json_encode($house_detail['property']['address']['location']['latitude']); ?>;
        var longitude = <?php echo json_encode($house_detail['property']['address']['location']['longitude']); ?>;
        var location = {
            lat: latitude,
            lng: longitude
        };

        // Create the map centered at the specified location
        var map = new google.maps.Map(document.getElementById('map'), {
            zoom: 13, // Adjust the zoom level as needed
            center: location
        });

        // Add a marker at the location
        var marker = new google.maps.Marker({
            position: location,
            map: map
        });
    }

    // Call the initMap function when the window loads
    window.onload = initMap;
    var swiper = new Swiper(".rch-houses-mySwiper", {
    spaceBetween: 10,
    slidesPerView: 8, // Default for desktop
    freeMode: true,
    watchSlidesProgress: true,
    
    // Add responsive breakpoints
    breakpoints: {
        // When the window width is >= 320px (small mobile)
        320: {
            slidesPerView: 3,  // 1 slide per view on small screens
        },
        // When the window width is >= 480px (mobile)
        480: {
            slidesPerView: 4,  // 2 slides per view on mobile screens
        },
        // When the window width is >= 768px (tablets)
        768: {
            slidesPerView: 5,  // 4 slides per view on tablet screens
        },
        // When the window width is >= 1024px (desktops)
        1024: {
            slidesPerView: 6,  // 6 slides per view on large tablets or small desktops
        },
        // When the window width is >= 1280px (desktops)
        1280: {
            slidesPerView: 8,  // Full slides per view on desktop
        }
    }
});

    var swiper2 = new Swiper(".rch-houses-mySwiper2", {
        spaceBetween: 10,
        navigation: {
            nextEl: ".swiper-button-next",
            prevEl: ".swiper-button-prev",
        },
        thumbs: {
            swiper: swiper,
        },
    });
    // Get the modal
    var modal = document.getElementById("myModal");

    // Get the button that opens the modal
    var btns = document.querySelectorAll("[id='myBtn']");

    // Get the <span> element that closes the modal
    var span = document.getElementsByClassName("rch-img-modal-close")[0];

    // When the user clicks the button, open the modal 
    for (const btn of btns) {
        btn.addEventListener('click', function(event) {
            const data = this.getAttribute('data-slider');
            modal.style.display = "flex";
            swiper2.update();
            swiper2.slideTo(data);
            swiper2.update();
        })
    }

    // When the user clicks on <span> (x), close the modal
    span.onclick = function() {
        modal.style.display = "none";
    }

    // When the user clicks anywhere outside of the modal, close it
    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = "none";
        }
    }
</script>