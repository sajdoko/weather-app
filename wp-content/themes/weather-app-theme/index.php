<?php
get_header(); // Include the header
?>
<main class="flex-shrink-0">
    <div class="container mt-3">
        <h1 class="text-center mb-4">Cities Weather Data</h1>
        <div class="row">
            <div class="col-12">
                <!-- Display weather data using the shortcode -->
                <?php echo do_shortcode('[weather_app]'); ?>
            </div>
        </div>
    </div>
</main>
<?php
get_footer(); // Include the footer
?>
