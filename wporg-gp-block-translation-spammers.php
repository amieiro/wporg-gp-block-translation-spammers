<?php
/**
 * Plugin Name: WordPress.org GP Block Translation Spammers
 * Plugin URI: https://wordpress.org
 * Description: Blocks specific users from accessing the translation system for submitting incorrect translations repeatedly.
 * Version: 1.0.0
 * Author: WordPress.org
 * Text Domain: wporg-gp-block-translation-spammers
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package WPORG_GP_Block_Translation_Spammers
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WPORG_GP_BLOCK_TRANSLATION_SPAMMERS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Main plugin class
 */
class WPORG_GP_Block_Translation_Spammers {
	
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
	 * @var WPORG_GP_Block_Translation_Spammers|null
	 */
	private static $instance = null;
	
	/**
	 * Get singleton instance.
	 *
	 * @return WPORG_GP_Block_Translation_Spammers Instance of this class.
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
		$this->check_and_block_user();
	}
	
	/**
	 * Check if current user should be blocked and block if necessary.
	 */
	public function check_and_block_user() {
		if ( ! $this->is_target_domain() ) {
			return;
		}
		
		if ( ! is_user_logged_in() ) {
			return;
		}
		
		$current_user = wp_get_current_user();
		if ( ! $this->is_user_blocked( $current_user->user_login ) ) {
			return;
		}
		
		$this->display_block_message();
	}
	
	/**
	 * Check if we're on the target domain.
	 *
	 * @return bool True if on target domain, false otherwise.
	 */
	private function is_target_domain() {
		$current_host = isset( $_SERVER['HTTP_HOST'] ) ? $_SERVER['HTTP_HOST'] : '';
		return $current_host === self::TARGET_DOMAIN;
	}
	
	/**
	 * Check if username is in blocked list.
	 *
	 * @param string $username The username to check.
	 * @return bool True if user is blocked, false otherwise.
	 */
	private function is_user_blocked( $username ) {
		return array_key_exists( $username, self::BLOCKED_USERS );
	}
	
	/**
	 * Display the block message and exit
	 */
	public function display_block_message() {
		status_header( 403 );
		nocache_headers();

		$message = $this->get_ban_message();
		$this->render_block_page( $message );
		exit;
	}
    
    /**
     * Get the translatable ban message
	 * 
	 * @return string The ban message with placeholders replaced.
     */
    private function get_ban_message() {
		$current_user = wp_get_current_user();
		$username = $current_user->user_login;
		$reason_url = 'https://make.wordpress.org/polyglots/';
		
		if ( isset( self::BLOCKED_USERS[$username] ) ) {
			$reason_url = self::BLOCKED_USERS[$username];
		}
		 
        return __(
            sprintf(
                /* translators: 1: Username, 2: Discussion to ban the user. 3: Slack channel URL, 4: Make WordPress.org URL */
                'You (%1$s) have been banned from the translation system for repeatedly submitting incorrect or potentially AI-generated translations.<br> You can see the full discussion <a href="%2$s">here</a>.<br> If you believe this is a mistake, please request assistance in this <a href="%3$s">Slack channel</a> or submit an appeal at <a href="%4$s">Make WordPress.org</a>.',
                $current_user->user_login,
                $reason_url,
                'https://wordpress.slack.com/archives/C02RP50LK',
                'https://make.wordpress.org/polyglots/'
            ),
			'wporg-gp-block-translation-spammers'
		);
    }
    
    /**
     * Render the complete block page
     *
     * @param string $message The ban message to display.
	 * 
	 * @return void
     */
    private function render_block_page( $message ) {
        $title = esc_html__( 'Access Blocked', 'wporg-gp-block-translation-spammers' );
        $site_name = get_bloginfo( 'name' );
        $css_url = WPORG_GP_BLOCK_TRANSLATION_SPAMMERS_PLUGIN_URL . 'assets/css/style.css';
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo( 'charset' ); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title><?php echo esc_html( $title ); ?> - <?php echo esc_html( $site_name ); ?></title>
            <link rel="stylesheet" href="<?php echo esc_url( $css_url ); ?>" type="text/css" media="all">
        </head>
        <body>
            <div class="container">
                <h1><?php echo esc_html( $title ); ?></h1>
                
                <div class="message">
                    <p><?php echo wp_kses( $message, array( 'a' => array( 'href' => true, 'title' => true, 'target' => true ), 'br' => array() ) ); ?></p>
                </div>
                
                <div class="footer">
                    <p><?php echo esc_html( $site_name ); ?></p>
                </div>
            </div>
        </body>
        </html>
        <?php
    }
}

add_action( 'plugins_loaded', function() {
    WPORG_GP_Block_Translation_Spammers::get_instance();
}, 1 );
