<?php
/**
 * Plugin Name: WordPress.org GP contributor moderation
 * Plugin URI: https://wordpress.org
 * Description: Blocks specific users from accessing the translation system for submitting incorrect translations repeatedly.
 * Version: 1.0.0
 * Author: WordPress.org
 * Text Domain: wporg-gp-contributor-moderation
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package WPORG_GP_Contributor_Moderation
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPORG_GP_CONTRIBUTOR_MODERATION_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class WPORG_GP_Contributor_Moderation {

	/**
	 * Array of blocked usernames and the reason URL to ban them.
	 *
	 * Add usernames and the reason URL to this array to block them from translate.wordpress.org.
	 * This is uppercase sensitive, so ensure usernames are added exactly as they appear.
	 *
	 * @var array
	 */
	private const BLOCKED_USERS = array(
		'jesusamieiro' => 'https://make.wordpress.org/polyglots/2025/04/30/can-users-be-blocked-by-someone/',
	);
	
	/**
	 * Target domain to block access to.
	 *
	 * @var string
	 */
	private const TARGET_DOMAIN = 'translate.wordpress.org';
	
	/**
	 * Instance of this class.
	 *
	 * @var WPORG_GP_Contributor_Moderation|null
	 */
	private static $instance = null;
	
	/**
	 * Get singleton instance.
	 *
	 * @return WPORG_GP_Contributor_Moderation Instance of this class.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	
	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'gp_before_translation_table', array( $this, 'show_banned_message' ) );
		add_filter( 'gp_pre_can_user', array( $this, 'block_translation_contributions' ), 10, 2 );
	}
	
	/**
	 * Check if we're on the target domain.
	 *
	 * @return bool True if on target domain, false otherwise.
	 */
	private function is_target_domain() {
		$current_host = isset( $_SERVER['HTTP_HOST'] ) ? sanitize_text_field( $_SERVER['HTTP_HOST'] ) : '';
		return $current_host === self::TARGET_DOMAIN;
	}
	
	/**
	 * Check if username is in blocked list.
	 *
	 * @param string $username The username to check.
	 * @return bool True if user is blocked, false otherwise.
	 */
	private function is_user_blocked( $username ) {
		$blocked_users = apply_filters( 'wporg_gp_contributor_moderation_block_users', self::BLOCKED_USERS );
		return array_key_exists( $username, $blocked_users );
	}
    
    /**
     * Get the alert message.
	 * 
	 * @return string The alert message.
     */
    private function get_alert_message() {
		$current_user = wp_get_current_user();
		$username = $current_user->user_login;
		$reason_url = 'https://make.wordpress.org/polyglots/';
		
		if ( isset( self::BLOCKED_USERS[$username] ) ) {
			$reason_url = self::BLOCKED_USERS[$username];
		}
		 
		$profile_url = sprintf( 'https://profiles.wordpress.org/%s/', rawurlencode( $username ) );
		/* translators: 1: User profile URL, 2: Username, 3: Discussion URL, 4: Slack channel URL, 5: Make WordPress.org URL */
		$message = __('Because you (<a href="%1$s" target="_blank">%2$s</a>) have repeatedly submitted bad translations, currently you cannot submit new translations to <a href="https://translate.wordpress.org/" target="_blank">translate.wordpress.org</a>. You can see the full discussion <a href="%3$s" target="_blank">here</a>.<br> If you believe this is a mistake, please request assistance in this <a href="%4$s" target="_blank">Slack channel</a> or submit an appeal at <a href="%5$s" target="_blank">Make WordPress.org</a>.', 'wporg-gp-contributor-moderation');

		return sprintf(
			$message,
			esc_url( $profile_url ),
			esc_html( $username ),
			esc_url( $reason_url ),
			esc_url( 'https://wordpress.slack.com/archives/C02RP50LK' ),
			esc_url( 'https://make.wordpress.org/polyglots/' )
		);
    }
    
    /**
     * Display a message for banned users in the translation table.
     * 
     * @return void
     */
    public function show_banned_message(): void {
        if ( ! $this->is_target_domain() ) {
            return;
        }
        
        if ( ! is_user_logged_in() ) {
            return;
        }
        
        $current_user = wp_get_current_user();
        if ( ! $current_user || ! $current_user->exists() || ! $this->is_user_blocked( $current_user->user_login ) ) {
            return;
        }
        
        wp_enqueue_style(
            'wporg-gp-contributor-moderation',
            WPORG_GP_CONTRIBUTOR_MODERATION_PLUGIN_URL . 'assets/css/style.css',
            array(),
            filemtime( plugin_dir_path( __FILE__ ) . 'assets/css/style.css' )
        );

        $message = $this->get_alert_message();

		$content = '<div id="show_banned_message" class="show_banned_message">';
        $content .= $message;
        $content .= '</div>';

        echo wp_kses(
            $content,
            array(
                'div'  => array(
                    'id'    => array(),
                    'class' => array(),
                    'style' => array(),
                ),
                'span' => array(
                    'class' => array(),
                    'style' => array(),
                ),
                'a'    => array(
                    'href'   => array(),
                    'target' => array(),
                    'style'  => array(),
                ),
                'br'   => array(),
            )
        );
    }
    
    /**
     * Block users from submitting translations.
     *
     * @param bool|null  $can    Whether the user can perform the action, or null if it hasn't been determined yet.
     * @param array      $action An array with the action the user is trying to perform (edit, approve, etc.)
     * @return bool|null Whether the user can perform the action.
     */
    public function block_translation_contributions( $can, $action ) {
        if ( ! $this->is_target_domain() ) {
            return $can;
        }
        
        $blocked_actions = array( 'edit', 'write', 'approve', 'import-waiting' );
        if ( ! in_array( $action['action'], $blocked_actions, true ) ) {
            return $can;
        }
        
        $current_user = wp_get_current_user();
        if ( ! $current_user || ! $current_user->exists() ) {
            return $can;
        }
        
        if ( $this->is_user_blocked( $current_user->user_login ) ) {
            return false;
        }
        
        return $can;
    }
}

add_action( 'plugins_loaded', function() {
    WPORG_GP_Contributor_Moderation::get_instance();
}, 1 );
