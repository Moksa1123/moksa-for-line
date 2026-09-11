<?php
/**
 * Front-end shortcodes and blocks.
 *
 * The login button posts to a server-side starter rather than embedding an
 * authorization URL in the page. That matters on any cached site: an embedded
 * URL carries a state value that was created when the page was generated, and
 * by the time a visitor clicks it, that state has long expired.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Frontend;

use Moksa\Line\Data\Users;
use Moksa\Line\Login\LoginModule;
use Moksa\Line\Support\Options;

defined( 'ABSPATH' ) || exit;

class ShortcodeModule {

	public function register(): void {
		add_shortcode( 'moksa_line_login', array( $this, 'login_button' ) );
		add_shortcode( 'moksa_line_add_friend', array( $this, 'add_friend' ) );
		add_shortcode( 'moksa_line_profile', array( $this, 'profile' ) );

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );

		// Offer the link control on the WordPress profile screen.
		add_action( 'show_user_profile', array( $this, 'profile_section' ) );
		add_action( 'edit_user_profile', array( $this, 'profile_section' ) );
	}

	public function register_assets(): void {
		wp_register_style(
			'moksa-line-front',
			MOKSA_LINE_URL . 'assets/css/front.css',
			array(),
			\Moksa\Line\Admin\AdminModule::asset_version( 'assets/css/front.css' )
		);

		// The dialog's own styles, shared with the admin screens.
		wp_register_style(
			'moksa-line-confirm',
			MOKSA_LINE_URL . 'assets/css/confirm.css',
			array(),
			\Moksa\Line\Admin\AdminModule::asset_version( 'assets/css/confirm.css' )
		);

		// The confirmation dialog is shared with the admin screens. The account
		// page needs it, and used to get it by loading the whole admin bundle.
		wp_register_script(
			'moksa-line-confirm',
			MOKSA_LINE_URL . 'assets/js/confirm.js',
			array( 'jquery' ),
			\Moksa\Line\Admin\AdminModule::asset_version( 'assets/js/confirm.js' ),
			true
		);

		wp_register_script(
			'moksa-line-account',
			MOKSA_LINE_URL . 'assets/js/account.js',
			array( 'jquery', 'moksa-line-confirm' ),
			\Moksa\Line\Admin\AdminModule::asset_version( 'assets/js/account.js' ),
			true
		);

		// Attached here rather than where the panel renders. The account page
		// draws inside the page content, and by then this script's tag can
		// already have been printed -- so the data never reached it and every
		// string in the unlink dialog fell back to its English default.
		wp_localize_script(
			'moksa-line-account',
			'moksaLineAccount',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'strings' => array(
					'confirmUnlink' => __( 'Unlink your LINE account? You will stop getting order updates in LINE.', 'moksa-line' ),
					'unlinkAction'  => _x( 'Unlink', 'confirmation button', 'moksa-line' ),
					'confirmYes'    => __( 'Yes, do it', 'moksa-line' ),
					'confirmNo'     => __( 'Cancel', 'moksa-line' ),
					'confirmReissue' => __( 'Replace your membership card? The code you have now will stop working.', 'moksa-line' ),
					'reissueAction'  => _x( 'Replace it', 'confirmation button', 'moksa-line' ),
					'failed'        => __( 'That did not work.', 'moksa-line' ),
				),
			)
		);
	}

	/**
	 * [moksa_line_login] -- the sign-in button.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function login_button( $atts ): string {
		$atts = shortcode_atts(
			array(
				'label'         => __( 'Log in with LINE', 'moksa-line' ),
				'logged_in'     => '',
				'redirect'      => '',
				'class'         => '',
				'size'          => 'medium',
			),
			(array) $atts,
			'moksa_line_login'
		);

		wp_enqueue_style( 'moksa-line-front' );

		if ( '' === (string) Options::get( 'channel_id' ) ) {
			return current_user_can( 'manage_options' )
				? '<p class="moksa-line-notice">' . esc_html__( 'LINE Login is not configured yet.', 'moksa-line' ) . '</p>'
				: '';
		}

		if ( is_user_logged_in() ) {
			if ( '' !== $atts['logged_in'] ) {
				return '<p class="moksa-line-notice">' . esc_html( $atts['logged_in'] ) . '</p>';
			}

			return '';
		}

		$url = add_query_arg(
			array_filter(
				array(
					'action'      => 'moksa_line_start',
					'redirect_to' => '' !== $atts['redirect'] ? rawurlencode( $atts['redirect'] ) : '',
				)
			),
			admin_url( 'admin-ajax.php' )
		);

		$configured = trim( (string) Options::get( 'button_text' ) );
		$label      = '' !== $configured ? $configured : $atts['label'];

		return sprintf(
			'<a class="moksa-line-button moksa-line-button--%1$s %2$s" href="%3$s" rel="nofollow"%4$s>%5$s%6$s</a>',
			esc_attr( $atts['size'] ),
			esc_attr( $atts['class'] ),
			esc_url( $url ),
			self::style_attribute(),
			$this->logo(),
			esc_html( $label )
		);
	}

	/**
	 * The configured colours, as custom properties.
	 *
	 * Colour is the only thing this plugin sets on the button. Shape, width and
	 * type belong to the theme -- they used to be settings, and every site that
	 * touched them ended up with one control that matched nothing around it.
	 *
	 * An option left at its default contributes nothing and the stylesheet
	 * decides, which is also how a theme keeps the upper hand.
	 */
	public static function style_attribute(): string {
		$declarations = array();

		$background = trim( (string) Options::get( 'button_bg_color' ) );
		$foreground = trim( (string) Options::get( 'button_text_color' ) );

		if ( '' !== $background && preg_match( '/^#[0-9a-f]{3,8}$/i', $background ) ) {
			$declarations[] = '--moksa-line-button-bg:' . $background;
		}

		if ( '' !== $foreground && preg_match( '/^#[0-9a-f]{3,8}$/i', $foreground ) ) {
			$declarations[] = '--moksa-line-button-fg:' . $foreground;
		}

		if ( empty( $declarations ) ) {
			return '';
		}

		return ' style="' . esc_attr( implode( ';', $declarations ) ) . '"';
	}

	/**
	 * The class that positions a login button, from the placement settings.
	 *
	 * @return string A class attribute value, always with a leading space when
	 *                it is not empty.
	 */
	public static function align_class(): string {
		$align = (string) Options::get( 'button_align' );

		return in_array( $align, array( 'center', 'full' ), true ) ? ' moksa-line-button--' . $align : '';
	}

	/**
	 * [moksa_line_add_friend] -- a link to the official account.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function add_friend( $atts ): string {
		$atts = shortcode_atts(
			array(
				'label'    => __( 'Add us on LINE', 'moksa-line' ),
				'basic_id' => (string) Options::get( 'bot_basic_id' ),
				'class'    => '',
			),
			(array) $atts,
			'moksa_line_add_friend'
		);

		$basic_id = ltrim( trim( (string) $atts['basic_id'] ), '@' );

		if ( '' === $basic_id ) {
			return current_user_can( 'manage_options' )
				? '<p class="moksa-line-notice">' . esc_html__( 'Set the official account basic ID in the LINE settings first.', 'moksa-line' ) . '</p>'
				: '';
		}

		wp_enqueue_style( 'moksa-line-front' );

		// The line:// scheme was retired; https://line.me/R/ti/p/ is the
		// supported form and works in and out of the app.
		$url = 'https://line.me/R/ti/p/' . rawurlencode( '@' . $basic_id );

		return sprintf(
			'<a class="moksa-line-button moksa-line-button--friend %1$s" href="%2$s" target="_blank" rel="noopener nofollow">%3$s%4$s</a>',
			esc_attr( $atts['class'] ),
			esc_url( $url ),
			$this->logo(),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * [moksa_line_profile] -- the signed-in visitor's LINE identity.
	 *
	 * @param array $atts Shortcode attributes.
	 */
	public function profile( $atts ): string {
		if ( ! is_user_logged_in() ) {
			return '';
		}

		$atts = shortcode_atts(
			array( 'show_avatar' => 'yes' ),
			(array) $atts,
			'moksa_line_profile'
		);

		$record = Users::by_wp_id( get_current_user_id() );

		if ( ! $record ) {
			return '';
		}

		wp_enqueue_style( 'moksa-line-front' );

		$avatar = '';

		if ( 'yes' === $atts['show_avatar'] && '' !== (string) $record->picture_url ) {
			$avatar = sprintf(
				'<img class="moksa-line-profile__avatar" src="%s" alt="" width="48" height="48" loading="lazy" />',
				esc_url( (string) $record->picture_url )
			);
		}

		return sprintf(
			'<div class="moksa-line-profile">%s<span class="moksa-line-profile__name">%s</span></div>',
			$avatar,
			esc_html( (string) $record->display_name )
		);
	}

	/**
	 * Link and unlink controls on the WordPress profile screen.
	 *
	 * @param \WP_User $user User being edited.
	 */
	public function profile_section( $user ): void {
		$record   = Users::by_wp_id( (int) $user->ID );
		$is_self  = get_current_user_id() === (int) $user->ID;
		?>
		<h2><?php esc_html_e( 'LINE account', 'moksa-line' ); ?></h2>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><?php esc_html_e( 'Status', 'moksa-line' ); ?></th>
				<td>
					<?php if ( $record && '' !== (string) $record->line_user_id ) : ?>
						<p>
							<?php
							printf(
								/* translators: %s: LINE display name. */
								esc_html__( 'Linked to %s.', 'moksa-line' ),
								'<strong>' . esc_html( (string) $record->display_name ) . '</strong>'
							);
							?>
						</p>
						<button type="button" class="button" data-moksa-line-unlink="<?php echo esc_attr( (string) $user->ID ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'moksa_line_link' ) ); ?>">
							<?php esc_html_e( 'Unlink LINE account', 'moksa-line' ); ?>
						</button>
					<?php elseif ( $is_self ) : ?>
						<a class="button button-primary" href="
						<?php
						echo esc_url(
							add_query_arg(
								array(
									'action'   => 'moksa_line_start',
									'link'     => 1,
									'_wpnonce' => wp_create_nonce( 'moksa_line_link' ),
								),
								admin_url( 'admin-ajax.php' )
							)
						);
						?>
						"><?php esc_html_e( 'Link my LINE account', 'moksa-line' ); ?></a>
					<?php else : ?>
						<p><?php esc_html_e( 'No LINE account is linked. Only this user can link their own.', 'moksa-line' ); ?></p>
					<?php endif; ?>
				</td>
			</tr>
		</table>
		<?php
	}

	/**
	 * The LINE mark, inlined so it renders before any stylesheet loads.
	 */
	private function logo(): string {
		return '<svg class="moksa-line-button__logo" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true" focusable="false">'
			. '<path fill="currentColor" d="M12 2C6.48 2 2 5.73 2 10.31c0 4.1 3.55 7.54 8.35 8.19.33.07.77.22.88.5.1.26.07.66.03.92l-.14.85c-.04.25-.2.99.87.54 1.07-.45 5.75-3.39 7.84-5.8C21.28 13.86 22 12.19 22 10.31 22 5.73 17.52 2 12 2zM8.2 13.1H6.09a.28.28 0 0 1-.28-.28V8.6a.28.28 0 0 1 .28-.28h.53c.15 0 .28.13.28.28v3.44H8.2c.16 0 .28.12.28.28v.5c0 .15-.12.28-.28.28zm1.6-.28c0 .15-.13.28-.28.28h-.53a.28.28 0 0 1-.28-.28V8.6c0-.15.13-.28.28-.28h.53c.15 0 .28.13.28.28v4.22zm4.62 0c0 .15-.12.28-.28.28h-.53a.28.28 0 0 1-.22-.11l-1.93-2.6v2.43c0 .15-.13.28-.28.28h-.53a.28.28 0 0 1-.28-.28V8.6c0-.15.13-.28.28-.28h.55c.09 0 .17.04.22.11l1.9 2.57V8.6c0-.15.13-.28.29-.28h.53c.15 0 .28.13.28.28v4.22zm3.5-3.44c0 .16-.12.28-.28.28h-1.5v.58h1.5c.16 0 .28.13.28.28v.5c0 .16-.12.29-.28.29h-1.5v.57h1.5c.16 0 .28.13.28.28v.5c0 .16-.12.29-.28.29h-2.3a.28.28 0 0 1-.28-.28V8.6c0-.15.12-.28.28-.28h2.3c.16 0 .28.13.28.28v.5z"/>'
			. '</svg>';
	}
}
