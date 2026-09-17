<?php
/** Page template. @package Leadwerk_Theme */
if ( ! defined( 'ABSPATH' ) ) { exit; }
get_header();
while ( have_posts() ) { the_post(); echo leadwerk_theme_render_page( get_the_ID() ); }
get_footer();

