<?php
/** Fallback template. @package Leadwerk_Theme */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
echo '<main class="subpage-section"><div class="container">';
while ( have_posts() ) { the_post(); if ( is_page() ) { echo leadwerk_theme_render_page( get_the_ID() ); } else { echo '<article>'; the_title( '<h1>', '</h1>' ); the_content(); echo '</article>'; } }
echo '</div></main>';
get_footer();

