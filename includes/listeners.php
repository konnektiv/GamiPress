<?php
/**
 * Activity Listeners
 *
 * @package     GamiPress\Listeners
 * @author      GamiPress <contact@gamipress.com>, Ruben Garcia <rubengcdev@gmail.com>
 * @since       1.0.0
 */
// Exit if accessed directly
if( !defined( 'ABSPATH' ) ) exit;

/**
 * Listener for user log in
 *
 * Triggers: gamipress_register
 *
 * @since   1.5.9
 *
 * @param int $user_id New registered user ID.
 */
function gamipress_register_listener( $user_id ) {

    // Sometimes this event is triggered before gamipress_load_activity_triggers()
    // so to avoid issues, method has changed to trigger the event directly
    do_action( 'gamipress_register', $user_id );

    gamipress_trigger_event( array(
        'event' => 'gamipress_register',
        'user_id' => $user_id,
    ) );

}
add_action( 'user_register', 'gamipress_register_listener' );

/**
 * Listener for user log in
 *
 * Triggers: gamipress_login
 *
 * @since   1.1.0
 * @updated 1.4.3 Changed event triggering by calling directly to gamipress_trigger_event()
 *
 * @param string  $user_login Username.
 * @param WP_User $user       WP_User object of the logged-in user.
 */
function gamipress_login_listener( $user_login, $user = null ) {

    // Some login form plugins call this hook just passing the user ID so let's check if user is registered
    if( ! $user ) {
        $user = get_user_by( 'email', $user_login );

        // Bail if user can't be found through email
        if( ! $user ) {
            return;
        }
    }

    // Sometimes this event is triggered before gamipress_load_activity_triggers()
    // so to avoid issues, method has changed to trigger the event directly

    // Action is maintained for backward compatibility
    do_action( 'gamipress_login', $user->ID, $user );

    gamipress_trigger_event( array(
        'event' => 'gamipress_login',
        'user_id' => $user->ID,
    ) );

}
add_action( 'wp_login', 'gamipress_login_listener', 10, 2 );

/**
 * Listener for content publishing
 *
 * Triggers: gamipress_publish_{$post_type}
 *
 * @since  1.0.0
 *
 * @param  string   $new_status The new post status
 * @param  string   $old_status The old post status
 * @param  WP_Post  $post       The post
 *
 * @return void
 */
function gamipress_transition_post_status_listener( $new_status, $old_status, $post ) {

    // Statuses to check the old status
    $old_statuses = apply_filters( 'gamipress_publish_listener_old_status', array( 'new', 'auto-draft', 'draft', 'private', 'pending', 'future' ), $post->ID );

    // Statuses to check the new status
    $new_statuses = apply_filters( 'gamipress_publish_listener_new_status', array( 'publish', 'private' ), $post->ID );

    // Check if post status transition come to publish
    if ( in_array( $old_status, $old_statuses ) && in_array( $new_status, $new_statuses ) ) {

        // Trigger content publishing actions
        do_action( "gamipress_publish_{$post->post_type}", $post->ID, $post->post_author, $post );
    }

}
add_action( 'transition_post_status', 'gamipress_transition_post_status_listener', 10, 3 );

/**
 * Listener for content deletion
 *
 * Triggers: gamipress_delete_{$post_type}
 *
 * @since  1.3.7
 *
 * @param  integer  $post_id The deleted post ID
 *
 * @return void
 */
function gamipress_delete_post_listener( $post_id ) {

    $post = get_post( $post_id );

    // Trigger content deletion actions
    do_action( "gamipress_delete_{$post->post_type}", $post->ID, $post->post_author, $post );

}
add_action( 'trashed_post', 'gamipress_delete_post_listener' );
add_action( 'before_delete_post', 'gamipress_delete_post_listener' );

/**
 * Listener for comment publishing
 *
 * Triggers: gamipress_new_comment, gamipress_specific_new_comment
 *
 * @since  1.0.0
 *
 * @param  integer      $comment_ID The comment ID
 * @param  array|object $comment    The comment array
 *
 * @return void
 */
function gamipress_approved_comment_listener( $comment_ID, $comment ) {

    // Ensure comment as array (wp_insert_comment uses object, comment_{status}_comment uses array)
    if ( is_object( $comment ) )
        $comment = get_object_vars( $comment );

    // Check if comment is approved
    if ( (int) $comment['comment_approved'] !== 1 )
        return;

    // Setup vars
    $comment_id = (int) $comment_ID;
    $user_id = (int) $comment['user_id'];
    $post_id = absint( $comment[ 'comment_post_ID' ] );

    // Trigger comment actions
    do_action( 'gamipress_new_comment', $comment_id, $user_id, $post_id, $comment );
    do_action( 'gamipress_specific_new_comment', $comment_id, $user_id, $post_id, $comment );

    if( $post_id !== 0 ) {

        $post_author = absint( get_post_field( 'post_author', $post_id ) );

        // Trigger comment actions to author
        do_action( 'gamipress_user_post_comment', $comment_id, $post_author, $post_id, $comment );
        do_action( 'gamipress_user_specific_post_comment', $comment_id, $post_author, $post_id, $comment );
    }

}
add_action( 'comment_approved_', 'gamipress_approved_comment_listener', 10, 2 );
add_action( 'comment_approved_comment', 'gamipress_approved_comment_listener', 10, 2 );
add_action( 'wp_insert_comment', 'gamipress_approved_comment_listener', 10, 2 );

/**
 * Listener for comment marked as spam
 *
 * Triggers: gamipress_spam_comment, gamipress_specific_spam_comment
 *
 * @since  1.7.3
 *
 * @param  integer      $comment_ID The comment ID
 * @param  array|object $comment    The comment array
 *
 * @return void
 */
function gamipress_spam_comment_listener( $comment_ID, $comment ) {

    // Ensure comment as array
    if ( is_object( $comment ) )
        $comment = get_object_vars( $comment );

    // Setup vars
    $comment_id = (int) $comment_ID;
    $user_id = (int) $comment['user_id'];
    $post_id = absint( $comment[ 'comment_post_ID' ] );

    // Trigger comment actions
    do_action( 'gamipress_spam_comment', $comment_id, $user_id, $post_id, $comment );
    do_action( 'gamipress_specific_spam_comment', $comment_id, $user_id, $post_id, $comment );

}
add_action( 'comment_spam_', 'gamipress_spam_comment_listener', 10, 2 );
add_action( 'comment_spam_comment', 'gamipress_spam_comment_listener', 10, 2 );

/**
 * Listener for daily visits
 *
 * Triggers: gamipress_site_visit, gamipress_specific_post_visit, gamipress_user_post_visit, gamipress_user_specific_post_visit
 *
 * @since   1.0.0
 * @updated 1.5.1 Now is triggered through ajax
 *
 * @return void
 */
function gamipress_site_visit_listener() {
    // Security check, forces to die if not security passed
    check_ajax_referer( 'gamipress', 'nonce' );

    $events_triggered = array();

    // Check current logged in user ID
    $user_id = get_current_user_id();

    // Bail if there is no user to award
    if( $user_id === 0 ) {
        // Return an array of events triggered
        wp_send_json_success( $events_triggered );
        exit;
    }

    // Current time
    $now = strtotime( date( 'Y-m-d 00:00:00', current_time( 'timestamp' ) ) );

    // ---------------------------
    // Website daily visit
    // ---------------------------

    /**
     * Filter to set if site visits should be tracked or not
     *
     * @since 1.5.1
     *
     * @param bool  $track_visits   Whatever if visits should be tracked (default true)
     * @param int   $user_id        The user ID that made the visit
     *
     * @return bool
     */
    $track_visits = apply_filters( 'gamipress_track_site_visits', true, $user_id );

    if( $track_visits ) {

        $count = gamipress_get_user_trigger_count( $user_id, 'gamipress_site_visit', $now );

        // Trigger daily visit action if not triggered today
        if( $count === 0 ) {
            do_action( 'gamipress_site_visit', $user_id );

            $events_triggered['gamipress_site_visit'] = array( $user_id );
        }

    }

    // Check the given post ID
    $post_id = absint( $_REQUEST['post_id'] );

    if( $post_id === 0 ) {
        // Return an array of events triggered
        wp_send_json_success( $events_triggered );
        exit;
    }

    // Check if post really exists
    $post = get_post( $post_id );

    if( ! $post ) {
        // Return an array of events triggered
        wp_send_json_success( $events_triggered );
        exit;
    }

    // ---------------------------
    // Post daily visit
    // ---------------------------

    /**
     * Filter to set if post visits should be tracked or not
     *
     * Note: Post visits event will award to the visitor
     *
     * @since 1.5.1
     *
     * @param bool  $track_visits   Whatever if post visits should be tracked (default true)
     * @param int   $user_id        The user ID that made the visit
     * @param int   $post_id        The post ID that has been visited
     * @param int   $post           The post object that has been visited
     *
     * @return bool
     */
    $track_post_visits = apply_filters( 'gamipress_track_post_visits', true, $user_id, $post_id, $post );

    if( $track_post_visits ) {

        $log_meta = array(
            'type'          => 'event_trigger',
            'trigger_type'  => 'gamipress_post_visit',
            'post_id'       => $post->ID,
        );

        // Get the trigger count
        $count = absint( gamipress_get_user_log_count( $user_id, $log_meta, $now ) );

        // Trigger daily post visit action if not triggered today
        if( $count === 0 ) {

            // Trigger any post visit
            do_action( 'gamipress_post_visit', $post->ID, $user_id, $post );

            $events_triggered['gamipress_post_visit'] = array( $post->ID, $user_id, $post );

        }

        $specific_count = gamipress_get_user_trigger_count( $user_id, 'gamipress_specific_post_visit', $now, 0, array( $post->ID, $user_id, $post ) );

        // Trigger daily specific post visit action if not triggered today
        if( $specific_count === 0 ) {

            // Trigger specific post visit
            do_action( 'gamipress_specific_post_visit', $post->ID, $user_id, $post );

            $events_triggered['gamipress_specific_post_visit'] = array( $post->ID, $user_id, $post );

        }

    }

    // ---------------------------
    // User post daily visit
    // ---------------------------

    $post_author = absint( $post->post_author );

    /**
     * Filter to set if user post visits should be tracked or not
     *
     * Note: User post visits event will award to the post author
     *
     * @since 1.5.1
     *
     * @param bool  $track_visits   Whatever if user post visits should be tracked (default true)
     * @param int   $user_id        The user ID that made the visit
     * @param int   $post_author    The post author ID that receive the visit
     * @param int   $post_id        The post ID that has been visited
     * @param int   $post           The post object that has been visited
     *
     * @return bool
     */
    $track_user_post_visits = apply_filters( 'gamipress_track_user_post_visits', true, $user_id, $post_author, $post_id, $post );

    if( $track_user_post_visits ) {

        // Trigger user post visit action to the author if visitor not is the author
        if( $post_author && $post_author !== $user_id ) {

            // Trigger user post visit
            do_action( 'gamipress_user_post_visit', $post->ID, $post_author, $user_id, $post );

            $events_triggered['gamipress_user_post_visit'] = array( $post->ID, $post_author, $user_id, $post );

            // Trigger user specific post visit
            do_action( 'gamipress_user_specific_post_visit', $post->ID, $post_author, $user_id, $post );

            $events_triggered['gamipress_user_specific_post_visit'] = array( $post->ID, $post_author, $user_id, $post );

        }

    }

    // Return an array of events triggered
    wp_send_json_success( $events_triggered );
    exit;

}
add_action( 'wp_ajax_gamipress_track_visit', 'gamipress_site_visit_listener' );

/**
 * Listener for expend points
 *
 * Triggers: gamipress_expend_points
 *
 * @since  1.3.7
 *
 * @param integer $post_id 	        The item unlocked ID (achievement or rank)
 * @param integer $user_id 			The user ID
 * @param integer $points 			The amount of points expended
 * @param string  $points_type 		The points type of the amount of points expended
 *
 * @return void
 */
function gamipress_expend_points_listener( $post_id, $user_id, $points, $points_type ) {

    // Trigger user expend points action
    do_action( 'gamipress_expend_points', $post_id, $user_id, $points, $points_type );

}
add_action( 'gamipress_achievement_unlocked_with_points', 'gamipress_expend_points_listener', 10, 4 );
add_action( 'gamipress_rank_unlocked_with_points', 'gamipress_expend_points_listener', 10, 4 );