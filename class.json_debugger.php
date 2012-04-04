<?php
/*
Plugin Name: JSON Debugger
Description: Outputs requested GLOBALS in JSON format for Admins when WP_DEBUG is true. A quick Google lead me to JSONView, which is a nifty looking plugin available for Chrome and Firefox.
Author: unFocus Projects
Author URI: http://www.unfocus.com/
Version: 0.1
License: GPLv2 or later
*/

class JSON_Debugger 
{
	const VERSION = '0.1';
	function init() {
		// This possibly exposes senstive data. Do me a favor and only use locally and for debugging.
		if ( ! WP_DEBUG || ! current_user_can( 'manage_options' ) ) return;
		
		add_filter( 'query_vars',          array( __CLASS__, 'json_query_vars') );
		add_filter( 'request',             array( __CLASS__, 'json_request' ) );
		add_action( 'template_redirect',   array( __CLASS__, 'json_template_redirect' ) );
	}
	
	// Allow JSON Debug Query Variable
	function json_query_vars( $query_vars ) {
		if ( ! in_array( 'json_debug', $query_vars) )
			$query_vars[] = 'json_debug';

		return $query_vars;
	}
	function json_request( $query_vars ) {
		if ( isset( $query_vars['json_debug'] ) ) {
			$json = sanitize_html_class( $query_vars[ 'json_debug' ] );
			$query_vars[ 'json_debug' ] = ( '' == $json ) ? true: $json;
		}
		return $query_vars;
	}
	function json_template_redirect() {
		global $wp_query, $post, $comment;
		
		$json = get_query_var( 'json_debug' );
		$data = array();
		
		
		switch ( (string) $json ) {
			case '': // JSON wasn't set. End here.
				return true;
				break;
			
			case 'debug': // Returns much Debugging info (basically non-complex values only)...
				$data = self::debug();
				break;
			case 'debugall': // Returns all Debugging info...
				$data = self::debug(1);
				break;
			
			// Whitelist for Local Testing only please...
			case "_COOKIE":
			case "_ENV":
			case "_FILES":
			case "_GET":
			case "_POST":
			case "_REQUEST":
			case "_SERVER":
			case "_wp_additional_image_sizes":
			case "_wp_default_headers":
			case "_wp_deprecated_widgets_callbacks":
			case "_wp_post_type_features":
			case "_wp_registered_nav_menus":
			case "_wp_sidebars_widgets":
			case "_wp_theme_features":
			case 'allowedentitynames':
			case 'allowedposttags':
			case 'allowedtags':
			case "category__and":
			case "category__in":
			case "category__not_in":
			case "current_user":
			case "l10n":
			case "merged_filters":
			case "month":
			case "month_abbrev":
			case "post":
			case "post__in":
			case "post__not_in":
			case "posts":
			case "shortcode_tags":
			case "sidebars_widgets":
			case "tag__and":
			case "tag__in":
			case "tag__not_in":
			case "tag_slug__and":
			case "tag_slug__in":
			case "userdata":
			case "weekday":
			case "weekday_abbrev":
			case "weekday_initial":
			case "wp":
			case "wp_actions":
			case "wp_admin_bar":
			case "wp_current_filter":
			case "wp_embed":
			case "wp_filter":
			case "wp_header_to_desc":
			case "wp_locale":
			case "wp_object_cache":
			case "wp_post_statuses":
			case "wp_post_types":
			case "wp_query":
			case "wp_registered_sidebars":
			case "wp_registered_widget_controls":
			case "wp_registered_widget_updates":
			case "wp_registered_widgets":
			case "wp_rewrite":
			case "wp_roles":
			case "wp_scripts":
			case "wp_styles":
			case "wp_taxonomies":
			case "wp_the_query":
			case "wp_theme_directories":
			case "wp_widget_factory":
			case "wpsmiliestrans":
				global $$json;
				$data[ '$'.$json ] = $$json;
				break;
				
			default:
				// General Post data stuff. See http://core.trac.wordpress.org/ticket/16303
				$_post = array();
				$_comment = array();
				$post_keys = array( 'ID', 'post_author', 'post_title', 'post_content', 'post_date_gmt', 'post_status', 'post_name', 'post_type', 'comment_count' );
				$comment_keys = array( 'comment_ID', 'comment_author', 'comment_content', 'comment_date_gmt', 'comment_type' );
				
				if ( have_posts() ){  while ( have_posts() ) { the_post(); // "The Loop."
					
					foreach ( $post_keys as $_key )
						$_post[ $_key ] = $post->$_key;
					
					if ( post_type_supports( $_post[ 'post_type' ], 'comments' ) ) {
						$wp_query->comments = get_comments( array(
							'post_id' => $post->ID,
							'status'  => 'approve',
							'order'   => 'ASC'
						) );
						$wp_query->comment_count = count( $wp_query->comments );
						update_comment_cache( $wp_query->comments );
						
						$_post[ 'comments' ] = array();
						
						while ( have_comments() ) {
							the_comment();
							foreach ( $comment_keys as $_key )
								$_comment[ $_key ] = $comment->$_key;
							
							$_post[ 'comments' ][ $_comment[ 'comment_type' ] . '-' . $_comment[ 'comment_ID' ] ] = $_comment;
						}
					}
					
					$data[ $_post[ 'post_type' ] . '-' . $_post[ 'ID' ] ] = $_post;
					
				} } // End "The Loop."

				
			break;
		}
		// else We will be returning JSON
		define( 'DOING_AJAX', true ); // Enables wp_die usage (with all the accompaning filters etc). Its essentially AJAX-ish, right?!
		header( 'Content-Type: application/json; charset=UTF-8' );
		wp_die( json_encode( $data ) );
	}
	function debug( $all = false ) {
		$data = array();
		foreach ( $GLOBALS as $key => $value ) {
			if ( // Blacklist...
				'GLOBALS' === $key
				|| 'PHP_SELF' === $key
				|| 'wpdb' === $key
				|| ( ! $all && ( is_object( $value ) || is_array( $value ) ) ) // See Whitelist for Objects and Arrays
			) continue;
			$data[ $key ] = $value;
		}
		ksort( $data );
		return $data;
	}
}
add_action( 'plugins_loaded', array( 'JSON_Debugger', 'init' ) );
?>