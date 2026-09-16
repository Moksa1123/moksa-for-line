# Moksa for LINE

LINE Login, a Messaging API bot, Flex Messages, tabbed rich menus, a
customer-service inbox, AI replies and LINE Pay, for WordPress and WooCommerce.

Version 1.0.0. Requires WordPress 6.2 and PHP 7.4.

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
| **Order notifications** | Templates with conditions on payment method, shipping method and order total. Tracking numbers are read from ECPay, RY Tools, AST and WooCommerce Shipment Tracking, and a shipping notice waits for one to appear before going out. Every send is recorded. |

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
[mofoline_login label="Log in with LINE" redirect="/account/"]
[mofoline_add_friend]
[mofoline_profile]
[mofoline_liff_profile]
[mofoline_chat]
```

### Optional constants

```php
// wp-config.php
define( 'MOFOLINE_ENCRYPTION_KEY', '...' ); // Key for credentials at rest; defaults to the WP salts.
define( 'MOFOLINE_REMOVE_DATA', true );     // Delete all plugin data on uninstall. Off by default.
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
add_filter( 'mofoline_compose_reply', function ( $messages, $text, $event, $line_user_id ) {
	if ( null !== $messages ) {
		return $messages; // Something earlier already answered.
	}

	if ( 'order status' === strtolower( trim( $text ) ) ) {
		return array( Mofoline\Api\MessagingClient::text( my_lookup_order( $line_user_id ) ) );
	}

	return $messages;
}, 25, 4 ); // Between flow triggers (20) and keyword rules (30).
```

Other useful hooks: `mofoline_logged_in`, `mofoline_user_registered`,
`mofoline_inbound_message`, `mofoline_postback`, `mofoline_flow_completed`,
`mofoline_payment_completed`, `mofoline_ai_answer`, `mofoline_modules`.

## Development

```bash
# Syntax check everything
find src views -name '*.php' -exec php -l {} \;
node --check assets/js/admin.js

# Exercise the pure logic outside WordPress: credential encryption, webhook
# signature verification, the LINE Pay v3 string-to-sign, and the Flex validator.
php tests/logic-check.php
```

`tests/logic-check.php` stubs just enough of WordPress to load the relevant
classes, so it runs anywhere PHP does. It needs the `openssl` and `mbstring`
extensions; without them the encryption checks fail even though the plugin
itself degrades gracefully.

The rest run inside WordPress, against a site you do not mind changing. None
of them send a LINE message, so none of them cost message quota.

```bash
# Against a throwaway WordPress install: rebuild a 1.4.0-shaped database and
# assert the migrator carries everything across without loss or double-encryption
wp eval-file tests/migration-check.php

# Needs WooCommerce. Exercises the order notification pipeline: placeholders,
# JSON safety, rule evaluation, template selection, dispatch, history,
# the duplicate guard, the tracking wait and the retry budget. Also asserts no
# placeholder value carries HTML entities, which is how order totals once
# reached customers as "&#78;&#84;&#36;1,000".
wp eval-file tests/notify-check.php

# Drives a conversation flow from its trigger to its stored submission:
# every transition, what is stored under which key, a refused answer that does
# not lose the customer's place, and the cancel word. This exists because every
# answer was once read as a validation failure, so no flow reached its second
# question and nothing in the admin showed it.
wp eval-file tests/flow-check.php

# Cuts an imagemap into the five widths LINE fetches and checks each one is
# exactly that wide, is a JPEG, keeps its aspect ratio and is under LINE's
# 1 MB limit, and that the base URL carries no file extension.
wp eval-file tests/imagemap-check.php

# Stubs Moksa for WooCommerce's two entry points under the exact namespaces it
# uses, so the invoice, tracking and transaction numbers can be verified here
# without installing it.
wp eval-file tests/moksafowo-bridge-check.php
```

Each of those five refuses to run when `wp_get_environment_type()` says
`production`, because they write to the database and WordPress defaults to
`production` when `WP_ENVIRONMENT_TYPE` is unset. Set that constant on your
test site, or define `MOFOLINE_ALLOW_DESTRUCTIVE_TESTS` in `wp-config.php`,
and they run. The guard exists because one of them was once run against a site
holding real data.

```bash

# Regenerate the translation template after changing any user-facing string
php bin/make-pot.php

# Build dist/moksa-for-line.zip, ready to upload
bash bin/build.sh
```

`bin/make-pot.php` walks the source with PHP's tokenizer rather than a regular
expression, so a string containing brackets or a nested call cannot throw the
extraction off. It picks up `translators:` comments and skips anything that is
not a plain literal, which is what keeps concatenated strings out of the
catalogue.

`bin/build.sh` refuses to build when the plugin header version and the
readme's `Stable tag` disagree, syntax-checks the staged copy rather than the
working tree, and always names the zip's root folder `moksa-for-line` regardless of
what the checkout directory is called.

### Translations

The plugin ships no translation files. Once it is listed on wordpress.org,
translations come from translate.wordpress.org and WordPress installs them
by itself. `languages/moksa-for-line.pot` is the current template and
`languages/moksa-for-line-zh_TW.po` the Traditional Chinese translation kept
here as the source to import there; neither is part of the release package.

## Licence

GPL v2 or later.
