# Moksa LINE Suite

LINE Login, a Messaging API bot, Flex Messages, tabbed rich menus, a
customer-service inbox, AI replies and LINE Pay, for WordPress and WooCommerce.

Version 2.0.0. Requires WordPress 6.2 and PHP 7.4.

## What it does

| Area | Summary |
|---|---|
| **LINE Login** | OAuth 2.0 + OIDC with PKCE. Identity comes from a verified ID token. Accounts can be created automatically or linked to an existing WordPress user from the profile screen. |
| **Bot** | Keyword rules (exact, prefix, contains, pattern, catch-all) ranked by match strength and priority, replying with text, Flex, quick replies, stickers, images or raw JSON. |
| **Conversation flows** | Multi-step scenarios that collect answers -- bookings, enquiries, sign-ups -- with validation, a cancel path, stored submissions and email notification. |
| **AI** | Answers anything the rules did not, through the AI Engine plugin's chatbot (including its knowledge base), with a daily cap and a hand-off keyword. |
| **Inbox** | Conversations recorded from the webhook, with unread counts, bot/human/closed status and replies sent from wp-admin. |
| **Rich menus** | Including tabbed menus, built from rich menu aliases and `richmenuswitch` actions so tabs change instantly inside LINE. |
| **Flex messages** | An editor with live preview, local structural checks, LINE's own validation endpoint and a test send. |
| **LINE Pay** | Online API v3. A WooCommerce gateway with refunds and voids, plus standalone payment links you can send into a chat. |
| **LIFF** | Profile and chat pages that run inside the LINE in-app browser, with the ID token verified server-side. |
| **Broadcast** | To all followers or to the friends this site has recorded, with a typed confirmation and remaining quota shown. |

## Setup

1. Create a **LINE Login** channel and a **Messaging API** channel in the
   [LINE Developers Console](https://developers.line.biz/), both under the same
   provider. (User ids are per-provider: put them under different providers and
   the same person will look like two different people.)
2. In WordPress, go to **LINE > Settings**.
   - *LINE Login*: paste the Channel ID and secret, and copy the Callback URL
     shown there into the LINE Console.
   - *Messaging API*: paste the Channel ID and secret, and copy the Webhook URL
     into the Console. Turn on "Use webhook", then press Verify.
3. The **Dashboard** checklist reports what is still missing.

### Shortcodes

```
[moksa_line_login label="Log in with LINE" redirect="/account/"]
[moksa_line_add_friend]
[moksa_line_profile]
[moksa_liff_profile]
[moksa_line_chat]
```

### Optional constants

```php
// wp-config.php
define( 'MOKSA_LINE_ENCRYPTION_KEY', '...' ); // Key for credentials at rest; defaults to the WP salts.
define( 'MOKSA_LINE_REMOVE_DATA', true );     // Delete all plugin data on uninstall. Off by default.
```

## Things worth knowing before you rely on it

- **There is no chat history to import.** The Messaging API has no endpoint for
  reading past conversations. The inbox starts from the moment the webhook is
  switched on.
- **Replies are free, pushes are not.** Answering an inbound message within a
  minute uses a reply token and costs nothing. Anything the inbox, broadcast or
  a receipt sends afterwards is a push, and is billed.
- **LINE Pay v3, not v4.** v3's signature construction is documented and in wide
  production use; v4's is not published in the same detail. The client is
  written so a v4 path can be added when that changes.
- **Rich menus are immutable on LINE.** Editing one publishes a replacement and
  moves the alias to it. That is why publishing is a separate, explicit step
  from saving.
- **Changing the WordPress salts** in `wp-config.php` makes stored credentials
  unreadable; they will need re-entering.

## Extending

The bot's reply pipeline is a filter chain, so a site can add its own step:

```php
add_filter( 'moksa_line_compose_reply', function ( $messages, $text, $event, $line_user_id ) {
	if ( null !== $messages ) {
		return $messages; // Something earlier already answered.
	}

	if ( 'order status' === strtolower( trim( $text ) ) ) {
		return array( Moksa\Line\Line\MessagingClient::text( my_lookup_order( $line_user_id ) ) );
	}

	return $messages;
}, 25, 4 ); // Between flow triggers (20) and keyword rules (30).
```

Other useful hooks: `moksa_line_logged_in`, `moksa_line_user_registered`,
`moksa_line_inbound_message`, `moksa_line_postback`, `moksa_line_flow_completed`,
`moksa_line_payment_completed`, `moksa_line_ai_answer`, `moksa_line_modules`.

## Development

```bash
# Syntax check everything
find src views -name '*.php' -exec php -l {} \;
node --check assets/js/admin.js
```

## Licence

GPL v2 or later.
