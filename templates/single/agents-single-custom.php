<?php
get_header();

// Get the current post ID
$post_id = get_the_ID();

// Retrieve the meta values
$website = get_post_meta($post_id, 'website', true);
$instagram = get_post_meta($post_id, 'instagram', true);
$twitter = get_post_meta($post_id, 'twitter', true);
$linkedin = get_post_meta($post_id, 'linkedin', true);
$youtube = get_post_meta($post_id, 'youtube', true);
$facebook = get_post_meta($post_id, 'facebook', true);
$phone_number = get_post_meta($post_id, 'phone_number', true);
$email = get_post_meta($post_id, 'email', true);
$profile_image_url = get_post_meta($post_id, 'profile_image_url', true);
$timezone = get_post_meta($post_id, 'timezone', true);
$agents = get_post_meta($post_id, 'agents', true);
?>

<div id="primary" class="content-area rch-primary-content">
    <main id="main" class="site-main content-container site-container">

        <?php
        while (have_posts()) : the_post();
        ?>
            <div class="rch-main-layout-single-agent">
                <div class="rch-left-main-layout-single-agent">
                    <div class="rch-top-single-agent">
                        <div class="rch-left-top-single-agent">
                            <?php if ($timezone) : ?>
                                <div class="rch-image-container">
                                    <picture>
                                        <a href="<?php the_permalink() ?>">
                                            <div class="rch-loader"></div>
                                            <img src="<?php echo esc_url($profile_image_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>" class="rch-profile-image">
                                        </a>
                                    </picture>
                                </div>
                            <?php endif; ?>
                            <div class="rch-data-agent">
                                <?php the_title('<h1>', '</h1>') ?>

                                        <?php if ($license_number) : ?>
                                            <span>
                                                License Number:
                                                <?php echo esc_html($license_number); ?>
                                            </span>
                                        <?php endif; ?>

                                <?php if ($phone_number) : ?>
                                    <span>
                                        Phone:
                                        <?php echo esc_html($phone_number); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($email) : ?>
                                    <span>
                                        Email:
                                        <?php echo esc_html($email); ?>
                                    </span>
                                <?php endif; ?>

                                <?php if ($website) : ?>
                                    <span>
                                        Website:
                                        <a href="<?php echo esc_url($website); ?>" target="_blank"><?php echo esc_html($website); ?></a>
                                    </span>
                                <?php endif; ?>

                                <?php if ($instagram || $twitter || $linkedin || $youtube || $facebook) : ?>
                                    <span>
                                        Social Media:
                                    </span>
                                    <ul class="rch-single-agents-social">
                                        <?php if ($instagram) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($instagram); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'instagram.svg'); ?>" alt="Instagram">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($twitter) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($twitter); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'x.svg'); ?>" alt="Twitter">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($linkedin) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($linkedin); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'linkedin.svg'); ?>" alt="LinkedIn">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($youtube) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($youtube); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'youtube.svg'); ?>" alt="YouTube">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($facebook) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($facebook); ?>" target="_blank">
                                                    <img src="<?php echo esc_url(RCH_PLUGIN_ASSETS_URL_IMG . 'facebook.svg'); ?>" alt="Facebook">
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                    <div class="rch-main-content">
                        <?php the_content(); ?>
                    </div>
                </div>
                <div class="rch-right-main-layout-single-agent">
                    <div class="rch-inner-right-agents" id="leadCaptureForm">
                        <form action="" method="post">
                            <h2>Get in Touch with <?php echo esc_html(get_the_title()); ?></h2>
                            <!-- First Name -->
                            <div class="form-group">
                                <label for="first_name">First Name</label>
                                <input type="text" id="first_name" name="first_name" placeholder="Enter your first name" required>
                            </div>

                            <!-- Last Name -->
                            <div class="form-group">
                                <label for="last_name">Last Name</label>
                                <input type="text" id="last_name" name="last_name" placeholder="Enter your last name" required>
                            </div>

                            <!-- Phone Number -->
                            <div class="form-group">
                                <label for="phone_number">Phone Number</label>
                                <input type="tel" id="phone_number" name="phone_number" placeholder="Enter your phone number" required>
                            </div>

                            <!-- Email Address -->
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" placeholder="Enter your email address" required>
                            </div>

                            <!-- Note -->
                            <div class="form-group">
                                <label for="note">Note</label>
                                <textarea id="note" name="note" placeholder="Write your note here" required></textarea>
                            </div>

                            <!-- Submit Button -->
                            <button type="submit">Submit Request</button>
                            <div id="loading-spinner" class="rch-loading-spinner-form" style="display: none;"></div>
                            <div id="rch-listing-success-sdk" class="rch-success-box-listing">
                                Thank you! Your data has been successfully sent.
                            </div>
                            <div id="rch-listing-cancel-sdk" class="rch-error-box-listing">
                                Something went wrong. Please try again.
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <div class="rch-agents-list">
                <h2><?php the_title(); ?>'s Properties</h2>
                <!-- 
                    Agent Properties Section
                    This section displays all properties associated with the current agent.
                    Properties are loaded via AJAX by sending the agent's matrix IDs to the API.
                    The CSS styling for this grid is located in rch-global.css.
                -->
                <div class="rch-agents-list-items rch-listing-list" id="agent-properties-list">
                    <!-- Properties will be loaded here via AJAX -->
                </div>
                <div id="loading-properties" class="rch-loader" style="display: block;"></div>
                <?php include(RCH_PLUGIN_DIR . '/templates/single/template-part/agent-properties-pagination.php'); ?>
            </div>
        <?php endwhile; ?>

    </main><!-- #main -->
</div><!-- #primary -->
<?php get_footer(); ?>

<script src="https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js"></script>
<script>
    const sdk = new Rechat.Sdk();
    const channel = {
        lead_channel: '<?php echo esc_js(get_option("rch_agents_lead_channels")); ?>'
    };

    document.getElementById('leadCaptureForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent form from submitting normally

        const input = {
            first_name: document.getElementById('first_name').value,
            last_name: document.getElementById('last_name').value,
            phone_number: document.getElementById('phone_number').value,
            email: document.getElementById('email').value,
            note: document.getElementById('note').value,
            tag: <?php echo wp_json_encode(explode(',', get_option("rch_agents_selected_tags"))); ?>, // Convert comma-separated string to array
            source_type: 'Website',
            agent_emails: '<?php echo esc_html($email); ?>',
            referer_url: window.location.href

        };

        // Hide success, error alerts, and show loading spinner
        document.getElementById('rch-listing-success-sdk').style.display = 'none';
        document.getElementById('rch-listing-cancel-sdk').style.display = 'none';
        document.getElementById('loading-spinner').style.display = 'block';

        sdk.Leads.capture(channel, input)
            .then(() => {
                // Hide loading spinner and show success message
                document.getElementById('loading-spinner').style.display = 'none';
                document.getElementById('rch-listing-success-sdk').style.display = 'block';
            })
            .catch((e) => {
                // Hide loading spinner and show error message
                document.getElementById('loading-spinner').style.display = 'none';
                document.getElementById('rch-listing-cancel-sdk').style.display = 'block';
                console.log('Error:', e);
            });
    });

    // Load agent properties
    document.addEventListener('DOMContentLoaded', function() {
        // Initialize pagination variables
        let agentCurrentPage = 1; 
        let agentTotalPages = 1;
        let agentListingPerPage = 12; // Number of listings per page
        
        const agentMatrixIds = <?php echo json_encode($agents); ?>;
        const propertiesList = document.getElementById('agent-properties-list');
        const loadingElement = document.getElementById('loading-properties');
        const paginationElement = document.getElementById('agent-pagination');
        const adminAjaxUrl = "<?php echo esc_url(admin_url('admin-ajax.php')); ?>";
        const brandId = "<?php echo esc_js(get_option('rch_rechat_brand_id')); ?>";
        
        if (!agentMatrixIds || agentMatrixIds.length === 0) {
            // No agent IDs available
            propertiesList.innerHTML = '<li class="no-properties">No properties found for this agent.</li>';
            loadingElement.style.display = 'none';
            paginationElement.style.display = 'none';
            return;
        }
        
        window.agentChangePage = function(direction) {
            agentCurrentPage += direction;
            
            // Update pagination UI immediately to show the new active page
            updateActivePage();
            
            // Then fetch the listings for the new page
            updateAgentPropertiesList();
        };
        
        window.agentGoToPage = function(page) {
            agentCurrentPage = page;
            
            // Update pagination UI immediately to show the new active page
            updateActivePage();
            
            // Then fetch the listings for the new page
            updateAgentPropertiesList();
        };
        
        // Function to update just the active page in the pagination without redrawing everything
        function updateActivePage() {
            // First, remove active class from all buttons
            const pageButtons = paginationElement.querySelectorAll('button');
            pageButtons.forEach(button => {
                if (button.textContent && !isNaN(parseInt(button.textContent))) {
                    if (parseInt(button.textContent) === agentCurrentPage) {
                        button.classList.add('active');
                    } else {
                        button.classList.remove('active');
                    }
                }
            });
        }
        
        window.updateAgentPagination = function() {
            const currentPaginationVisible = paginationElement.style.display === 'flex';
            paginationElement.innerHTML = ''; // Clear existing pagination
            
            const prevIconPath = `<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>left-arrow.svg`; // Dynamic path for the previous icon
            const nextIconPath = `<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>right-arrow.svg`; // Dynamic path for the next icon
            
            if (agentTotalPages > 1) {
                // Show "Previous" button if the current page is greater than 1
                if (agentCurrentPage > 1) {
                    const prevButton = document.createElement('button');
                    const prevIcon = document.createElement('img');
                    prevIcon.src = prevIconPath;
                    prevIcon.alt = 'Previous';
                    prevIcon.className = 'pagination-icon'; // Optional CSS class for styling
                    prevButton.appendChild(prevIcon);
                    prevButton.onclick = () => window.agentChangePage(-1);
                    paginationElement.appendChild(prevButton);
                }
                
                // Generate page numbers dynamically
                for (let page = 1; page <= agentTotalPages; page++) {
                    if (
                        page === 1 || // Always show the first page
                        page === agentTotalPages || // Always show the last page
                        Math.abs(page - agentCurrentPage) <= 2 // Show 2 pages before and after the current page
                    ) {
                        const pageButton = document.createElement('button');
                        pageButton.textContent = page;
                        pageButton.className = page === agentCurrentPage ? 'active' : '';
                        pageButton.onclick = () => window.agentGoToPage(page);
                        paginationElement.appendChild(pageButton);
                    } else if (
                        page === agentCurrentPage - 3 || // Add dots before skipped pages
                        page === agentCurrentPage + 3 // Add dots after skipped pages
                    ) {
                        const dots = document.createElement('span');
                        dots.textContent = '...';
                        paginationElement.appendChild(dots);
                    }
                }
                
                // Show "Next" button if the current page is less than the total pages
                if (agentCurrentPage < agentTotalPages) {
                    const nextButton = document.createElement('button');
                    const nextIcon = document.createElement('img');
                    nextIcon.src = nextIconPath;
                    nextIcon.alt = 'Next';
                    nextIcon.className = 'pagination-icon'; // Optional CSS class for styling
                    nextButton.appendChild(nextIcon);
                    nextButton.onclick = () => window.agentChangePage(1);
                    paginationElement.appendChild(nextButton);
                }
                
                // Only change display style if it wasn't already visible
                if (!currentPaginationVisible) {
                    paginationElement.style.display = 'flex';
                }
            } else {
                paginationElement.style.display = 'none';
            }
        };
        
        // Function to fetch the total count for pagination
        function fetchAgentTotalCount() {
            // Only show loading indicator when the fetch operation has started
            paginationElement.innerHTML = '<div class="rch-pagination-loading">Loading pagination...</div>';
            paginationElement.style.display = 'flex';
            
            return fetch(adminAjaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'rch_fetch_total_listing_count',
                    brand: brandId,
                    agents: agentMatrixIds
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const totalProperties = data.data.total;
                    agentTotalPages = Math.ceil(totalProperties / agentListingPerPage);
                    window.updateAgentPagination();
                } else {
                    paginationElement.innerHTML = '<p>Error loading pagination.</p>';
                    paginationElement.style.display = 'flex';
                }
            })
            .catch(error => {
                console.error('Error fetching total count:', error);
                paginationElement.innerHTML = '<p>Error loading pagination.</p>';
                paginationElement.style.display = 'flex';
            });
        }
        
        // Function to update agent properties list
        function updateAgentPropertiesList() {
            propertiesList.innerHTML = ''; // Clear previous content
            loadingElement.style.display = 'block'; // Show loading spinner
            
            // Don't hide or reset the pagination - keep it visible with current page active
            
            fetch(adminAjaxUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: new URLSearchParams({
                    action: 'rch_fetch_listing',
                    page: agentCurrentPage,
                    listing_per_page: agentListingPerPage,
                    brand: brandId,
                    agents: agentMatrixIds
                })
            })
            .then(response => response.json())
            .then(data => {
                loadingElement.style.display = 'none';
                
                if (data.success && data.data.listings && data.data.listings.length > 0) {
                    const listings = data.data.listings;
                    
                    listings.forEach(listing => {
                        propertiesList.innerHTML += listing.content;
                    });
                    
                    // Only fetch the total count if we don't already have pagination
                    // or if this is the first page (to ensure we have the latest count)
                    if (!paginationElement.hasChildNodes() || agentCurrentPage === 1) {
                        fetchAgentTotalCount();
                    }
                } else {
                    propertiesList.innerHTML = '<div class="no-properties">No properties found for this agent.</div>';
                    paginationElement.style.display = 'none';
                }
            })
            .catch(error => {
                console.error('Error fetching agent properties:', error);
                loadingElement.style.display = 'none';
                propertiesList.innerHTML = '<div class="no-properties">Error loading properties. Please try again later.</div>';
                paginationElement.style.display = 'none';
            });
        }
        
        // Initial load of agent properties
        updateAgentPropertiesList();
    });
</script>