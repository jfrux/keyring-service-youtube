<?php

/*
Plugin Name: Keyring Service for YouTube Public API V3
Plugin URI: http://github.com/joshuairl/keyring-service-youtube
Description: Connects without authentication for public api calls to YouTube using just an API Key.
Version: 0.0.1
Author: Joshua F. Rountree
Author URI: http://www.joshuairl.com/
License: GPL2
Depends: Keyring
*/

function keyring_youtube_enable( $importers ) {
	$importers[] = plugin_dir_path( __FILE__ ) . 'keyring-service-youtube/youtube.php';
	
	return $importers;
}

add_filter( 'keyring', 'keyring_youtube_enable' );