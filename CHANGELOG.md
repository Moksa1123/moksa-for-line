# Changelog

## 1.0.0

Initial release.

LINE Login, a Messaging API bot, Flex Messages, tabbed rich menus, a
customer-service inbox, AI replies and LINE Pay, for WordPress and WooCommerce.

### Replacing Moksa LINE Login

This plugin supersedes **Moksa LINE Login** (1.4.0 and earlier), which was not
published to the plugin directory. It is a new plugin rather than an update:
the folder, main file and text domain are all different, so WordPress treats
them as unrelated and both can be installed at once.

They do, however, share the `moksa_line_` option and table prefix, and that is
deliberate. Installing this on a site that ran the old plugin imports
everything on first load:

- Channel IDs, secrets and every other setting carry over. Credentials that
  were stored as plain text are re-stored encrypted.
- Auto-reply rules move from the column the old plugin created
  (`reply_content`) to the one its code actually read (`reply_data`), which is
  why some rules may appear to work for the first time.
- Quick replies, which the old plugin stored as a single keyword and message,
  are folded into the LINE quick-reply format.
- LINE users and their WordPress account bindings are preserved, and a
  conversation is opened for each so the inbox is not empty on first use.
- The n8n-specific forwarding URL becomes the general webhook forwarding URL.

Deactivate and delete Moksa LINE Login after confirming this plugin works.
Leaving both active means two webhook handlers competing for the same events.

The import runs once, is safe to repeat, and does not double-encrypt
credentials or duplicate conversations if it does.

### What the old plugin got wrong

Worth stating, because it explains what may look different:

- Its installer created four database tables while its code wrote to nine.
  Rich menus, webhook logs and Flex templates were writing into tables that did
  not exist, and the failures were never surfaced. All fourteen tables are now
  created and versioned.
- Schema changes depended on `register_activation_hook`, which it called from a
  constructor where it does not reliably fire.
- Its OAuth flow passed the session key through the OpenID Connect `nonce`
  parameter, so replay protection was never actually in use; it never verified
  the ID token, taking identity from an unauthenticated profile read; and the
  post-login redirect was not validated. Login now uses separate state and
  nonce values, PKCE, a verified ID token, and a validated redirect.
- Channel secrets and access tokens sat in `wp_options` as plain text. They are
  now encrypted at rest with AES-256-GCM keyed off the site's WordPress salts.
- Its webhook had no idempotency, so LINE's retries could send a customer the
  same reply twice. Events are now deduplicated by `webhookEventId`.
- Keyword rules were returned in whatever order the database chose, so which
  rule won depended on insertion order. Rules now rank by match strength and an
  explicit priority.

### WooCommerce order notifications

Carried over in full from the plugin this replaces, with the data model kept
compatible so existing templates and history survive:

- Notification templates remain a custom post type with the same meta keys, so
  templates already written keep working, including those whose statuses were
  stored with the `wc-` prefix.
- Trigger conditions on payment method, shipping method and order total.
- Tracking numbers are read from ECPay, RY Tools, Advanced Shipment Tracking
  and WooCommerce Shipment Tracking, along with convenience-store pickup
  details.
- A shipping notification waits for the tracking number to appear, retrying on
  a schedule, and sends without it once the budget is spent rather than never
  arriving at all.
- Delivery history records what was sent to whom about which order, and rows
  from the old table are imported.

Three faults in that system were fixed while porting:

- Every successful send was recorded in the history as failed, because the code
  checked the push response for a key the LINE API does not return.
- The class meant to stop an order being notified twice about the same status
  was never called from anywhere, so it never did.
- Values were spliced into the template's JSON unescaped, so a customer whose
  name contained a quotation mark silently received nothing.

### Not carried over

The imagemap builder and the WooCommerce product carousel generator from the
old plugin are not reimplemented. The imagemap table is preserved, so nothing
is lost if they return.

### Notes

- The Messaging API cannot read past chats. The inbox only contains messages
  received after this plugin was installed and the webhook switched on.
- Answering an inbound message within a minute is free; anything sent
  afterwards is a push message and is billed by LINE.
- Uninstalling leaves data in place unless `MOKSA_LINE_REMOVE_DATA` is defined
  as `true` in `wp-config.php`.
