# Changelog

## 2.0.0

A rewrite. The plugin keeps its slug, its settings and its data, but the code
underneath is new. Upgrading in place is supported and no reconfiguration is
needed; the schema and settings migrate on first load.

### Fixed (things that were silently broken in 1.x)

- **Missing tables.** The installer created four tables while the code wrote to
  nine. Rich menus, webhook logs and Flex templates were writing into tables
  that did not exist, and the failures were never surfaced. All fourteen tables
  are now created and versioned.
- **Column mismatches.** Auto-replies were saved to `reply_data` and read from
  `reply_content`; quick replies were saved with `name`/`items` into a table
  with `keyword`/`reply_message`. Existing rows are migrated rather than lost.
- **Upgrades depended on the activation hook.** `register_activation_hook` was
  called from a constructor, where it does not reliably fire. Schema changes now
  apply on load whenever the stored version is behind.
- **Rich menu images.** Out-of-bounds tap areas and wrong-sized images produced
  an opaque 400 from LINE. Both are checked locally with a message naming the
  actual problem.

### Security

- **OAuth state and nonce are now separate.** 1.x passed the transient key
  through the OIDC `nonce` parameter, so replay protection was never actually
  in use.
- **The ID token is verified.** Identity comes from the `sub` claim of a token
  LINE has verified, plus local issuer, audience, expiry and nonce checks,
  rather than from an unauthenticated profile read.
- **PKCE (S256)** is used on the authorization request.
- **Open redirect closed.** The post-login destination is passed through
  `wp_validate_redirect`.
- **Credentials are encrypted at rest** with AES-256-GCM keyed off the site's
  WordPress salts, instead of being stored as plain text in `wp_options`.
- **Stateless channel access tokens** (15 minutes, unlimited issuance) replace a
  stored long-lived token as the default.
- **Webhook replies cannot be duplicated.** `webhookEventId` carries a UNIQUE
  index, and events are claimed with a conditional UPDATE, so LINE's retries and
  an overlapping cron drain cannot both answer the customer.
- **Account takeover paths removed.** Matching an existing account by email is
  opt-in and off by default; a LINE identity already bound to another user is
  refused; email is never synced onto an existing account; the new-user role
  cannot be administrator.
- Credentials are redacted from logs.

### Added

- **Customer-service inbox.** Conversations and messages are recorded, unread
  counts tracked, and agents can reply from wp-admin. Replying takes the
  conversation over so the bot cannot talk across an agent.
- **Conversation flows.** Multi-step scenarios with per-step validation, choices
  as quick replies, a working cancel path, expiry, stored submissions and
  optional email notification.
- **AI replies** through AI Engine's chatbot API, with a stable per-user
  conversation id so context carries between messages. Guarded by a daily cap, a
  length limit and a hand-off keyword.
- **Tabbed rich menus** built the way LINE intends, with one menu and alias per
  tab and `richmenuswitch` actions between them.
- **LINE Pay** (Online API v3): a WooCommerce gateway with refunds and voids,
  standalone payment links deliverable as a Flex bubble, in-app payment URLs for
  customers already inside LINE, and an hourly sweep that recovers payments the
  customer never returned from.
- **Flex editor** with local structural validation, LINE's own validation
  endpoint, live preview and a test send.
- **Broadcast** to everyone or to recorded friends, with a typed confirmation
  and remaining quota shown.
- **Logs screen** showing webhook deliveries and plugin errors, so failures are
  diagnosable without SSH.
- LIFF profile and chat shortcodes, both verifying the ID token server-side.
- A setup checklist that reports what is still missing and why it matters.

### Changed

- Namespaced (`Moksa\Line`) with a PSR-4 autoloader and module bootstrap.
- Keyword rules rank by match strength and an explicit priority, rather than
  whichever row the database returned first.
- The webhook forwarding URL is generic rather than n8n-specific; the old
  option migrates automatically.

### Notes

- The Messaging API cannot read past chats. The inbox only contains messages
  received after this plugin was installed and the webhook switched on.
- Uninstalling leaves data in place unless `MOKSA_LINE_REMOVE_DATA` is defined
  as `true` in `wp-config.php`.

## 1.4.0 and earlier

See the git history.
