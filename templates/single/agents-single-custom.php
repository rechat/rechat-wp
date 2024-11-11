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
                                            <img src="<?php echo esc_url($profile_image_url); ?>" alt="<?php the_title() ?>" class="rch-profile-image">
                                        </a>
                                    </picture>
                                </div>
                            <?php endif; ?>
                            <div class="rch-data-agent">
                                <?php the_title('<h1>', '</h1>') ?>

                                <?php if ($timezone) : ?>
                                    <span>
                                        Location:
                                        <?php echo esc_html($timezone); ?>
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
                                                    <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>instagram.svg" alt="">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($twitter) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($twitter); ?>" target="_blank">
                                                    <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>x.svg" alt="">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($linkedin) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($linkedin); ?>" target="_blank">
                                                    <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>linkedin.svg" alt="">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($youtube) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($youtube); ?>" target="_blank">
                                                    <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>youtube.svg" alt="">
                                                </a>
                                            </li>
                                        <?php endif; ?>

                                        <?php if ($facebook) : ?>
                                            <li>
                                                <a href="<?php echo esc_url($facebook); ?>" target="_blank">
                                                    <img src="<?php echo RCH_PLUGIN_ASSETS_URL_IMG ?>facebook.svg" alt="">
                                                </a>
                                            </li>
                                        <?php endif; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                    <div class="rch-main-content">
                        <?php the_content() ?>
                    </div>
                </div>
                <div class="rch-right-main-layout-single-agent">
                    <div class="rch-inner-right-agents" id="leadCaptureForm">
                    <form action="" method="post">
                            <h2>Get in Touch with <?php the_title() ?></h2>
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

        <?php
        endwhile;
        ?>

    </main><!-- #main -->
</div><!-- #primary -->

<?php
get_footer();
?>
<script src="https://unpkg.com/@rechat/sdk@latest/dist/rechat.min.js"></script>
<script>
    const sdk = new Rechat.Sdk();

    const channel = {
        lead_channel: '<?php echo get_option("rch_lead_channels"); ?>'
    };

    document.getElementById('leadCaptureForm').addEventListener('submit', function(event) {
        event.preventDefault(); // Prevent form from submitting normally

        const input = {
            first_name: document.getElementById('first_name').value,
            last_name: document.getElementById('last_name').value,
            phone_number: document.getElementById('phone_number').value,
            email: document.getElementById('email').value,
            note: document.getElementById('note').value,
            tag: <?php echo get_option("rch_selected_tags"); ?>, // Convert comma-separated string to array
            source_type: 'Website',
            agent_emails:'<?php echo esc_html($email); ?>'
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
</script>