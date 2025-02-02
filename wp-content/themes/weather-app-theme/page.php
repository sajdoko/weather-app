<?php
get_header();
if (have_posts()) :
    while (have_posts()) : the_post();
?>
<div class="container mt-5">
    <h1 class="mb-4"><?php the_title(); ?></h1>
    <div class="content">
        <?php the_content(); ?>
    </div>
</div>
<?php
    endwhile;
endif;
get_footer();
?>
