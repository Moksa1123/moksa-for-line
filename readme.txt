=== Moksa LINE Suite ===
Contributors: moksa
Tags: line, line login, line pay, chatbot, woocommerce
Requires at least: 6.2
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

LINE Login, a Messaging API bot, Flex Messages, tabbed rich menus, a customer-service inbox, AI replies and LINE Pay.

== Description ==

Connects a WordPress site to the LINE platform.

* **LINE Login** — OAuth 2.0 and OpenID Connect with PKCE. Identity comes from a verified ID token, not an unauthenticated profile read. Visitors can register automatically or link LINE to an existing account from their profile.
* **Bot** — keyword rules matched by exact text, prefix, substring, regular expression or catch-all, ranked by match strength and priority. Replies can be text, a Flex template, a quick reply set, a sticker, an image or raw message JSON.
* **Conversation flows** — multi-step scenarios that collect answers for bookings, enquiries or sign-ups, with per-step validation, choices as quick reply buttons, a cancel path, stored submissions and optional email notification.
* **AI replies** — answers whatever the rules did not, using the AI Engine plugin's chatbot and its knowledge base. Protected by a daily cap, a length limit and a hand-off keyword that passes the conversation to a person.
* **Customer-service inbox** — conversations captured from the webhook, with unread counts, bot/human/closed status, and replies sent from wp-admin.
* **Rich menus** — including tabbed menus, built from rich menu aliases so tabs switch instantly inside LINE.
* **Flex messages** — an editor with live preview, structural validation, LINE's own validation endpoint and a test send.
* **LINE Pay** — a WooCommerce gateway with full and partial refunds, plus standalone payment links you can send straight into a chat.
* **LIFF** — profile and chat pages that run inside LINE, with every request's ID token verified against LINE before it is trusted.
* **Broadcast** — to every follower or to the friends this site has recorded, with a typed confirmation and the remaining monthly quota shown.

= Two things to know first =

The Messaging API provides no way to read past conversations, so the inbox starts from the moment the webhook is switched on. And answering an inbound message within a minute is free, while anything sent afterwards — inbox replies, broadcasts, receipts — is a push message and is billed by LINE.

= External services =

This plugin connects your site to the LINE platform, which is operated by LY Corporation. It cannot work without doing so. Each service below is listed with what is sent, and when.

**LINE Messaging API** — `api.line.me` and `api-data.line.me`
Used to send and receive messages, issue channel access tokens, read the profile of someone who messages your bot, and create rich menus. Sent: your channel id and secret (to obtain a token), the LINE user id of the recipient, and the message content you or the bot compose. Called whenever a message is sent, a webhook event is processed, or a rich menu is published.

**LINE Login** — `access.line.me` and `api.line.me`
Used to sign visitors in and to verify the resulting ID token. Sent: your channel id and secret, and the authorization code returned by LINE. Received: the visitor's LINE user id, display name, profile picture URL, and email address when your channel is approved for it. Called when a visitor uses a LINE login button.

**LIFF SDK** — `static.line-scdn.net`
A JavaScript file loaded in the visitor's browser on pages using the LIFF shortcodes. It must be served from LINE's own CDN; a bundled copy is not supported. Loaded only on pages containing `[moksa_liff_profile]` or `[moksa_line_chat]`.

**LINE Pay** — `api-pay.line.me`, or `sandbox-api-pay.line.me` in sandbox mode
Only contacted when LINE Pay is enabled. Sent: your LINE Pay channel id, a signature derived from your channel secret, the order reference, the amount and currency, and the names and quantities of the items being purchased. Called when a payment is reserved, confirmed, refunded, voided or queried.

Terms of use: https://terms.line.me/LINE_Developers_Agreement — Privacy policy: https://line.me/en/terms/policy/

**Optional, off unless you configure it:**

*Webhook forwarding* — if you set a forwarding URL in the settings, every webhook delivery from LINE is relayed to that URL unchanged, including the message content and the sender's LINE user id. The destination is entirely your choice; nothing is sent anywhere until you enter one.

*AI replies* — if you enable AI replies, the visitor's message text and a pseudonymous conversation id are passed to the AI Engine plugin, which sends them onward to whichever AI provider you have configured in that plugin. This plugin does not contact any AI provider directly. Review AI Engine's own disclosures for where that data goes.

= Privacy =

The plugin stores LINE user ids, display names, profile picture URLs and, where the channel is approved for it, email addresses. Messages exchanged with the bot are stored so the inbox can show them, and are deleted after the retention period set in the settings. Credentials are encrypted at rest using keys derived from the site's WordPress salts.

The conversation id given to an AI provider is a salted hash of the LINE user id, so the provider can keep context per person without ever receiving the LINE user id itself.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` and activate it.
2. Create a LINE Login channel and a Messaging API channel in the LINE Developers Console, both under the same provider.
3. Go to **LINE > Settings** and paste the Channel IDs and secrets. Copy the Callback URL and Webhook URL shown there into the LINE Console.
4. In the Console, enable "Use webhook" and press Verify.
5. The Dashboard checklist shows anything still outstanding.

== Frequently Asked Questions ==

= Can it show conversations from before I installed it? =

No. LINE does not provide an endpoint for reading chat history. Everything the inbox shows arrived by webhook after the plugin was set up.

= Do I need the LINE Login and Messaging API channels under the same provider? =

Yes, if you want the same person to be recognised in both. LINE user ids are issued per provider, so channels under different providers report different ids for the same human.

= Which LINE Pay API version does this use? =

Online API v3. Its signature construction is publicly documented and widely used in production. v4 exists, but its string-to-sign is not published in the same detail.

= Does uninstalling delete my data? =

No, unless you define `MOKSA_LINE_REMOVE_DATA` as `true` in `wp-config.php`. Payment records and conversation history are the kind of thing a shop may need to keep.

= Why can I not select administrator as the role for new accounts? =

Because a self-service login flow that can mint administrators is a way to lose a site.

= I already use the Moksa LINE Login plugin. What happens to my data? =

It is imported automatically. The two plugins share the same option and table prefix, so activating this one carries across your channel settings, LINE users, WordPress account bindings and reply rules. Credentials that were stored as plain text are re-stored encrypted, and reply rules are moved to the column the code actually reads, so some may work for the first time.

Deactivate and delete the older plugin once you have confirmed this one works. Leaving both active means two webhook handlers competing for the same events.

= Do I have to reconfigure anything after switching? =

No. The callback and webhook URLs are unchanged, so the settings already registered in the LINE Developers Console keep working.

== Upgrade Notice ==

= 1.0.0 =
First release. If you previously ran the Moksa LINE Login plugin, this imports its settings and data automatically on activation; deactivate that plugin afterwards so the two are not both answering the same webhook.

== Changelog ==

= 1.0.0 =

Initial release.

* LINE Login using OAuth 2.0 and OpenID Connect with PKCE, with identity taken from a verified ID token.
* Messaging API bot: keyword rules ranked by match strength and priority, replying with text, Flex, quick replies, stickers, images or raw JSON.
* Conversation flows that collect answers over several messages, with validation, a cancel path, stored submissions and optional email notification.
* AI replies through the AI Engine plugin, with a daily cap, a length limit and a hand-off keyword.
* Customer-service inbox with unread counts, bot/human/closed status and replies sent from wp-admin.
* Rich menus, including tabbed menus built from rich menu aliases.
* Flex message editor with live preview, local validation and LINE's own validation endpoint.
* LINE Pay: a WooCommerce gateway with refunds and voids, plus standalone payment links.
* WooCommerce order notifications: templates with conditions on payment method, shipping method and order total; tracking numbers read from ECPay, RY Tools, Advanced Shipment Tracking and WooCommerce Shipment Tracking; a wait-and-retry so shipping notices carry the tracking number; and a delivery history of what was sent to whom.
* Account linking from My Account, and a configurable login button.
* LIFF profile and chat shortcodes, with every request's ID token verified server-side.
* Broadcasting, a logs screen, and a setup checklist.

Replacing Moksa LINE Login:

* This is a separate plugin, not an update. Both can be installed at once, but only one should be active, or two webhook handlers will compete for the same events.
* It shares the moksa_line_ option and table prefix, so the older plugin's settings, LINE users, account bindings and reply rules are imported on first load.
* Credentials that were stored as plain text are re-stored encrypted.
* Auto-reply rules are moved to the column the code actually reads, so some rules may work for the first time.
* The import runs once, is safe to repeat, and neither double-encrypts credentials nor duplicates conversations.
