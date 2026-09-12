<?php
/**
 * The membership card: a QR on the customer's account page, and the page the
 * staff land on when they scan it.
 *
 * The scan target is a plain URL rather than a LIFF link or a deep link into
 * the LINE app, because the person scanning is standing behind a counter with
 * whatever phone they have. A URL opens in every camera app there is.
 *
 * What that URL shows depends entirely on who opens it. To a member of staff
 * it is the customer's card; to anyone else -- including the customer, and
 * including someone who found the code -- it is a sign-in prompt that says
 * nothing about whether the code was even real.
 *
 * @package Moksa\Line
 */

namespace Moksa\Line\Member;

use Moksa\Line\Admin\AdminModule;
use Moksa\Line\Data\Users;
use Moksa\Line\Support\Logger;
use Moksa\Line\Support\Options;
use Moksa\Line\Support\QrCode;

defined( 'ABSPATH' ) || exit;

class MemberModule {

	/** The path a card's URL lives under. */
	const SLUG = 'line-member';

	/** Which option remembers the rewrite rules we flushed for. */
	const FLUSHED = 'moksa_line_member_rewrite';

	public function register(): void {
		if ( ! Options::get( 'member_card' ) ) {
			return;
		}

		add_action( 'init', array( $this, 'add_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'add_query_var' ) );
		add_action( 'template_redirect', array( $this, 'maybe_render' ) );

		add_action( 'wp_ajax_moksa_line_member_reissue', array( $this, 'ajax_reissue' ) );
	}

	// --- Routing ------------------------------------------------------------------

	/**
	 * The two rules: a card, and the counter's lookup form.
	 */
	public function add_rewrite(): void {
		add_rewrite_rule(
			'^' . self::SLUG . '/([0-9A-Za-z]{' . MemberCard::LENGTH . '})/?$',
			'index.php?moksa_line_member=$matches[1]',
			'top'
		);

		// Staff reach this one by typing the address when a camera will not
		// focus, so it has to exist on its own.
		add_rewrite_rule(
			'^' . self::SLUG . '/?$',
			'index.php?moksa_line_member=lookup',
			'top'
		);

		$this->flush_once();
	}

	/**
	 * Rebuild the rules the first time these are registered.
	 *
	 * Same trap as the account endpoint: a rule that is declared but never
	 * flushed is a 404 on every card, until somebody re-saves the permalink
	 * settings for unrelated reasons.
	 */
	private function flush_once(): void {
		if ( self::SLUG === get_option( self::FLUSHED ) ) {
			return;
		}

		flush_rewrite_rules( false );
		update_option( self::FLUSHED, self::SLUG, false );
	}

	/**
	 * @param array $vars Public query vars.
	 * @return array
	 */
	public function add_query_var( $vars ) {
		$vars[] = 'moksa_line_member';

		return $vars;
	}

	/**
	 * Whether the current user may look at a customer's card.
	 *
	 * Shop managers, not administrators: the people scanning cards are the
	 * people working the counter.
	 */
	public static function can_view(): bool {
		return current_user_can( 'edit_shop_orders' )
			|| current_user_can( 'manage_woocommerce' )
			|| current_user_can( 'manage_options' );
	}

	// --- The scanned page ----------------------------------------------------------

	/**
	 * Take over the request when it is a card.
	 */
	public function maybe_render(): void {
		$requested = get_query_var( 'moksa_line_member' );

		if ( ! is_string( $requested ) || '' === $requested ) {
			return;
		}

		// A card URL is a bearer credential in someone's camera roll. Keep it
		// out of search results and out of the referrer of anything it links
		// to, and never let a proxy hold on to the rendered page.
		nocache_headers();
		header( 'X-Robots-Tag: noindex, nofollow, noarchive', true );
		header( 'Referrer-Policy: no-referrer', true );

		if ( ! self::can_view() ) {
			// Deliberately before the code is looked at: an anonymous scanner
			// gets the same page whether or not the code is real, so a page
			// that says "unknown card" can never confirm one.
			$this->render_sign_in();
			exit;
		}

		if ( 'lookup' === $requested ) {
			// A staff member typing a code into a form on their own screen;
			// there is nothing to forge, and normalise() reduces it to the
			// code alphabet before it reaches a query.
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$raw   = isset( $_GET['code'] ) && is_string( $_GET['code'] ) ? wp_unslash( $_GET['code'] ) : '';
			$typed = MemberCard::normalise( (string) $raw );

			if ( '' !== $typed ) {
				$found = MemberCard::user_for_code( $typed );

				if ( $found > 0 ) {
					wp_safe_redirect( MemberCard::url( $typed ) );
					exit;
				}

				$this->render_lookup( __( 'No member has that code. Check it and try again', 'moksa-line' ) );
				exit;
			}

			$this->render_lookup();
			exit;
		}

		$user_id = MemberCard::user_for_code( $requested );

		if ( $user_id <= 0 ) {
			status_header( 404 );
			$this->render_lookup( __( 'That card is not valid. It may have been replaced', 'moksa-line' ) );
			exit;
		}

		// Worth a log line: this is the only record that a card was presented,
		// and "the customer says they showed it" is otherwise unanswerable.
		Logger::info(
			'A membership card was opened at the counter',
			array(
				'member'   => $user_id,
				'staff'    => get_current_user_id(),
			),
			'member'
		);

		$this->render_card( $user_id );
		exit;
	}

	/**
	 * The card, as the counter sees it.
	 *
	 * @param int $user_id Whose card.
	 */
	private function render_card( int $user_id ): void {
		$user   = get_userdata( $user_id );
		$record = Users::by_wp_id( $user_id );

		if ( ! $user ) {
			status_header( 404 );
			$this->render_lookup( __( 'That account no longer exists', 'moksa-line' ) );

			return;
		}

		$name    = trim( $user->display_name ) !== '' ? $user->display_name : $user->user_login;
		$linked  = $record && '' !== (string) $record->line_user_id;
		$picture = $linked ? (string) $record->picture_url : '';

		$this->open( __( 'Member card', 'moksa-line' ) );
		?>
		<main class="moksa-scan moksa-scan--card">
			<header class="moksa-scan__head">
				<span class="moksa-scan__badge"><?php esc_html_e( 'Verified member', 'moksa-line' ); ?></span>
			</header>

			<section class="moksa-scan__who">
				<?php if ( '' !== $picture ) : ?>
					<img class="moksa-scan__avatar" src="<?php echo esc_url( $picture ); ?>" alt="" width="72" height="72" referrerpolicy="no-referrer" />
				<?php else : ?>
					<span class="moksa-scan__avatar moksa-scan__avatar--blank" aria-hidden="true"><?php echo esc_html( mb_substr( $name, 0, 1 ) ); ?></span>
				<?php endif; ?>

				<div class="moksa-scan__identity">
					<h1 class="moksa-scan__name"><?php echo esc_html( $name ); ?></h1>
					<p class="moksa-scan__meta"><?php echo esc_html( $user->user_email ); ?></p>
					<p class="moksa-scan__meta">
						<?php
						printf(
							/* translators: %s: the date the customer registered. */
							esc_html__( 'Joined %s', 'moksa-line' ),
							esc_html(
								mysql2date(
									/* translators: date format for "Member since", see https://www.php.net/manual/datetime.format.php -- translate it to whatever reads naturally in your language, not literally. */
									_x( 'F j, Y', 'member-since date', 'moksa-line' ),
									get_date_from_gmt( $user->user_registered )
								)
							)
						);
						?>
					</p>
				</div>
			</section>

			<?php $this->render_line_state( $record, $linked ); ?>
			<?php $this->render_orders( $user_id ); ?>

			<section class="moksa-scan__block">
				<h2 class="moksa-scan__heading"><?php esc_html_e( 'Member code', 'moksa-line' ); ?></h2>
				<p class="moksa-scan__code"><?php echo esc_html( MemberCard::grouped( MemberCard::code_for( $user_id ) ) ); ?></p>
			</section>

			<nav class="moksa-scan__actions">
				<?php if ( current_user_can( 'edit_shop_orders' ) || current_user_can( 'manage_woocommerce' ) ) : ?>
					<a class="moksa-scan__action" href="<?php echo esc_url( admin_url( 'edit.php?post_type=shop_order&_customer_user=' . $user_id ) ); ?>">
						<?php esc_html_e( 'All orders', 'moksa-line' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( $linked && \Moksa\Line\Inbox\InboxModule::can_manage() ) : ?>
					<a class="moksa-scan__action" href="<?php echo esc_url( add_query_arg(
							array(
								'page' => AdminModule::SLUG . '-inbox',
								// The inbox has no per-user route; its search
								// matches the LINE id exactly.
								's'    => (string) $record->line_user_id,
							),
							admin_url( 'admin.php' )
						) ); ?>">
						<?php esc_html_e( 'Message on LINE', 'moksa-line' ); ?>
					</a>
				<?php endif; ?>

				<?php if ( current_user_can( 'edit_user', $user_id ) ) : ?>
					<a class="moksa-scan__action" href="<?php echo esc_url( get_edit_user_link( $user_id ) ); ?>">
						<?php esc_html_e( 'Edit member', 'moksa-line' ); ?>
					</a>
				<?php endif; ?>

				<a class="moksa-scan__action moksa-scan__action--quiet" href="<?php echo esc_url( home_url( '/' . self::SLUG . '/' ) ); ?>">
					<?php esc_html_e( 'Scan another', 'moksa-line' ); ?>
				</a>
			</nav>
		</main>
		<?php
		$this->close();
	}

	/**
	 * Whether LINE can actually reach this member, said plainly.
	 *
	 * @param object|null $record Row from the LINE users table.
	 * @param bool        $linked Whether an account is linked at all.
	 */
	private function render_line_state( $record, bool $linked ): void {
		?>
		<section class="moksa-scan__block">
			<h2 class="moksa-scan__heading"><?php esc_html_e( 'LINE', 'moksa-line' ); ?></h2>

			<?php if ( ! $linked ) : ?>
				<p class="moksa-scan__state moksa-scan__state--off"><?php esc_html_e( 'No LINE account linked', 'moksa-line' ); ?></p>
			<?php else : ?>
				<p class="moksa-scan__state moksa-scan__state--on">
					<?php
					$display = trim( (string) $record->display_name );

					echo esc_html(
						'' !== $display
							/* translators: %s: the member's LINE display name. */
							? sprintf( __( 'Linked as %s', 'moksa-line' ), $display )
							: __( 'Linked', 'moksa-line' )
					);
					?>
				</p>

				<?php if ( ! (int) $record->is_friend ) : ?>
					<p class="moksa-scan__note moksa-scan__note--warn"><?php esc_html_e( 'Not seen as a friend of the official account, so LINE messages may not arrive', 'moksa-line' ); ?></p>
				<?php endif; ?>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * What this member has bought.
	 *
	 * @param int $user_id Customer id.
	 */
	private function render_orders( int $user_id ): void {
		if ( ! function_exists( 'wc_get_orders' ) ) {
			return;
		}

		$orders = wc_get_orders(
			array(
				'customer_id' => $user_id,
				'limit'       => 3,
				'orderby'     => 'date',
				'order'       => 'DESC',
			)
		);

		$count = function_exists( 'wc_get_customer_order_count' ) ? (int) wc_get_customer_order_count( $user_id ) : 0;
		$spent = function_exists( 'wc_get_customer_total_spent' ) ? wc_get_customer_total_spent( $user_id ) : 0;
		?>
		<section class="moksa-scan__block">
			<h2 class="moksa-scan__heading"><?php esc_html_e( 'Orders', 'moksa-line' ); ?></h2>

			<dl class="moksa-scan__stats">
				<div class="moksa-scan__stat">
					<dt><?php esc_html_e( 'Placed', 'moksa-line' ); ?></dt>
					<dd><?php echo esc_html( number_format_i18n( $count ) ); ?></dd>
				</div>
				<div class="moksa-scan__stat">
					<dt><?php esc_html_e( 'Spent', 'moksa-line' ); ?></dt>
					<dd>
						<?php
						// wc_price returns markup, and the amount inside it is
						// the only part worth showing here.
						echo esc_html( html_entity_decode( wp_strip_all_tags( wc_price( $spent ) ), ENT_QUOTES, 'UTF-8' ) );
						?>
					</dd>
				</div>
			</dl>

			<?php if ( empty( $orders ) ) : ?>
				<p class="moksa-scan__note"><?php esc_html_e( 'No orders yet', 'moksa-line' ); ?></p>
			<?php else : ?>
				<ul class="moksa-scan__orders">
					<?php foreach ( $orders as $order ) : ?>
						<li class="moksa-scan__order">
							<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>">
								<span class="moksa-scan__order-number">#<?php echo esc_html( $order->get_order_number() ); ?></span>
								<span class="moksa-scan__order-status"><?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?></span>
								<span class="moksa-scan__order-total">
									<?php echo esc_html( html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ), ENT_QUOTES, 'UTF-8' ) ); ?>
								</span>
								<span class="moksa-scan__order-date">
									<?php
									// Not the site's date_format: that setting is
									// whatever the shop typed once, and in a
									// Chinese install it renders as "11 9 月,
									// 2026". The format is part of the
									// translation, like every other one here.
									echo esc_html(
										$order->get_date_created()
											? wp_date(
												/* translators: date format for an order in the member card, see https://www.php.net/manual/datetime.format.php -- translate it to whatever reads naturally in your language, not literally. */
												_x( 'F j, Y', 'order date', 'moksa-line' ),
												$order->get_date_created()->getTimestamp()
											)
											: ''
									);
									?>
								</span>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</section>
		<?php
	}

	/**
	 * The page anyone who is not staff gets, whatever they scanned.
	 */
	private function render_sign_in(): void {
		global $wp;

		// The path WordPress matched, not REQUEST_URI: this runs for a rewrite
		// rule, and pasting REQUEST_URI onto home_url() doubles the path on any
		// install that lives in a subdirectory.
		$here = home_url( user_trailingslashit( $wp->request ) );

		$this->open( __( 'Member card', 'moksa-line' ) );
		?>
		<main class="moksa-scan moksa-scan--gate">
			<h1 class="moksa-scan__name"><?php esc_html_e( 'Member card', 'moksa-line' ); ?></h1>
			<p class="moksa-scan__note"><?php esc_html_e( 'Only staff can read this card. Sign in to see it', 'moksa-line' ); ?></p>

			<nav class="moksa-scan__actions">
				<a class="moksa-scan__action" href="<?php echo esc_url( wp_login_url( $here ) ); ?>">
					<?php esc_html_e( 'Staff sign-in', 'moksa-line' ); ?>
				</a>
				<a class="moksa-scan__action moksa-scan__action--quiet" href="<?php echo esc_url( home_url( '/' ) ); ?>">
					<?php esc_html_e( 'Go to the shop', 'moksa-line' ); ?>
				</a>
			</nav>
		</main>
		<?php
		$this->close();
	}

	/**
	 * The counter's fallback: type the code the customer reads out.
	 *
	 * @param string $error Message to show above the form, if any.
	 */
	private function render_lookup( string $error = '' ): void {
		$this->open( __( 'Find a member', 'moksa-line' ) );
		?>
		<main class="moksa-scan moksa-scan--lookup">
			<h1 class="moksa-scan__name"><?php esc_html_e( 'Find a member', 'moksa-line' ); ?></h1>

			<?php if ( '' !== $error ) : ?>
				<p class="moksa-scan__error"><?php echo esc_html( $error ); ?></p>
			<?php endif; ?>

			<form class="moksa-scan__form" method="get" action="<?php echo esc_url( home_url( '/' . self::SLUG . '/' ) ); ?>">
				<label for="moksa-scan-code"><?php esc_html_e( 'Member code', 'moksa-line' ); ?></label>
				<input id="moksa-scan-code" name="code" type="text" inputmode="latin" autocapitalize="characters"
					autocomplete="off" spellcheck="false" required
					placeholder="<?php esc_attr_e( 'ABCDE FGHJK MNPQR STVWX', 'moksa-line' ); ?>" />
				<button type="submit"><?php esc_html_e( 'Look up', 'moksa-line' ); ?></button>
			</form>

			<p class="moksa-scan__note"><?php esc_html_e( 'The code has no I, L or O -- read those as 1, 1 and 0', 'moksa-line' ); ?></p>
		</main>
		<?php
		$this->close();
	}

	// --- Page shell ----------------------------------------------------------------

	/**
	 * A standalone document.
	 *
	 * Not the theme: this page is opened one-handed on a phone at a counter,
	 * and whatever the shop's theme does with headers, menus and cookie banners
	 * is in the way of the one thing the page is for.
	 *
	 * @param string $title Document title.
	 */
	private function open( string $title ): void {
		header( 'Content-Type: text/html; charset=utf-8' );
		?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<meta name="robots" content="noindex, nofollow" />
	<title><?php echo esc_html( $title . ' — ' . get_bloginfo( 'name' ) ); ?></title>
	<?php
	// Enqueued and then printed, rather than written as a tag. This page has no
	// theme and calls no wp_head(), so nothing would otherwise print it -- and a
	// hand-written <link> is one of the things the directory's scan rejects.
	wp_enqueue_style(
		'moksa-line-member',
		MOKSA_LINE_URL . 'assets/css/member.css',
		array(),
		AdminModule::asset_version( 'assets/css/member.css' )
	);
	wp_print_styles( 'moksa-line-member' );
	?>
</head>
<body class="moksa-scan-body">
		<?php
	}

	/**
	 * Close the document.
	 */
	private function close(): void {
		?>
</body>
</html>
		<?php
	}

	// --- The customer's side --------------------------------------------------------

	/**
	 * The card block for the My Account page.
	 *
	 * @param int $user_id Whose card.
	 */
	public static function render_customer_card( int $user_id ): void {
		if ( ! Options::get( 'member_card' ) || $user_id <= 0 ) {
			return;
		}

		$code = MemberCard::code_for( $user_id );

		if ( '' === $code ) {
			return;
		}

		// Quiet zone 4, as the spec asks: the card sits on a page whose
		// background the customer's phone theme decides, and a code with no
		// margin is one a camera has to be lucky to find.
		$svg = QrCode::svg( MemberCard::url( $code ), 6 );

		if ( '' === $svg ) {
			return;
		}
		?>
		<section class="moksa-account__section moksa-account__card">
			<h3 class="moksa-account__subhead"><?php esc_html_e( 'Membership card', 'moksa-line' ); ?></h3>
			<p class="moksa-account__lead"><?php esc_html_e( 'Show this at the counter and we will find you', 'moksa-line' ); ?></p>

			<?php
			// A button, because it does something: the code sits inside a page
			// with the shop's own header and footer around it, and a counter
			// scanner wants it big and surrounded by white. Tapping opens
			// exactly that. Without JavaScript it is an inert button next to a
			// code that is already scannable, which is the right way round.
			?>
			<button type="button" class="moksa-account__qr" data-moksa-qr-zoom
				aria-label="<?php esc_attr_e( 'Show the code larger', 'moksa-line' ); ?>">
				<?php echo $svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- built here, no input in it. ?>
				<span class="moksa-account__qr-hint"><?php esc_html_e( 'Tap to enlarge', 'moksa-line' ); ?></span>
			</button>

			<button type="button" class="moksa-account__code" data-moksa-member-code="<?php echo esc_attr( $code ); ?>"
				title="<?php esc_attr_e( 'Copy your member code', 'moksa-line' ); ?>">
				<?php echo esc_html( MemberCard::grouped( $code ) ); ?>
			</button>

			<p class="moksa-account__hint"><?php esc_html_e( 'If the camera will not read it, read the code out', 'moksa-line' ); ?></p>

			<div class="moksa-account__actions">
				<button type="button" class="moksa-account__quiet"
					data-moksa-line-reissue="<?php echo esc_attr( (string) $user_id ); ?>"
					data-nonce="<?php echo esc_attr( wp_create_nonce( 'moksa_line_member' ) ); ?>">
					<?php esc_html_e( 'Replace this card', 'moksa-line' ); ?>
				</button>
			</div>
		</section>
		<?php
	}

	// --- Ajax -------------------------------------------------------------------------

	/**
	 * Issue the customer a new code, retiring the one they have.
	 */
	public function ajax_reissue(): void {
		check_ajax_referer( 'moksa_line_member', 'nonce' );

		$user_id = get_current_user_id();

		if ( $user_id <= 0 ) {
			wp_send_json_error( array( 'message' => __( 'You are not signed in', 'moksa-line' ) ), 403 );
		}

		MemberCard::issue( $user_id );

		wp_send_json_success(
			array(
				'message' => __( 'The old code has stopped working. This one is yours now', 'moksa-line' ),
			)
		);
	}
}
