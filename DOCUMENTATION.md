# Resend Documentation

Full technical reference for the Resend and ProcessResend modules.

## Architecture

- `Resend.module.php` — core module. Extends `WireMail`, exposes the resource-based API client and WireMail sending. Not autoloaded, not singular: every `$mail->new()` creates a fresh instance.
- `WireMailResend.module.php` — compatibility alias for modules that only discover mail providers whose class name starts with `WireMail` (for example Subscribe and Access). It inherits Resend sending behavior and shares the same encrypted settings table.
- `ProcessResend.module.php` — admin UI. Thin controllers rendering views from the `views/` directory. Requires the `resend-admin` permission and creates a `Setup > Resend` admin page.
- `ResendWebhooks.module.php` — autoload webhook receiver at `/resend-webhook/`. Verifies signatures, stores events in the `resend_events` table, fires the hookable `processEvent()`.
- `src/` — `Smnv\Resend` namespace. `Client` (HTTP transport: auth, JSON, error normalization), `Resource` base class, one class per API resource (`Emails`, `Domains`, `Segments`, `Contacts`, `ContactProperties`, `Topics`, `Templates`, `Broadcasts`, `Webhooks`, `Logs`), and `Stats` (pure aggregation helpers for the dashboard charts).
- `views/` — one view per screen (`resend.<screen>.php`), shared helpers in `resend.partials.php`.
- Admin UI markup uses native AdminThemeUikit/UIkit classes directly; no module CSS is injected.
- `bin/resend` — CLI entry point; command logic in `src/Cli.php`.

All API calls go through `Smnv\Resend\Client::request($method, $endpoint, $data, $headers)` using `WireHttp`. Every request sends the required `User-Agent` header (Resend rejects requests without one with a 403).

## Configuration

| Option | Description |
|---|---|
| `apiKey` | Resend API key (`re_...`), required; stored encrypted |
| `webhookSigningSecret` | Optional webhook signing secret (`whsec_...`), stored encrypted; used by `verifyWebhookSignature()` when no secret is passed |
| `fromEmail` | Default sender address, must belong to a verified domain |
| `fromName` | Default sender name |
| `replyToEmail` | Default reply-to address |
| `defaultTopicId` | Topic applied to all sends unless overridden |
| `defaultTags` | `name:value` pairs (comma separated) attached to every send; per-send tags with the same name take precedence |
| `globalBcc` | Blind copy address(es) for every send, comma separated; skipped while the redirect is active |
| `redirectEmail` | Development: deliver every email to this address instead of the real recipients; originals recorded in `X-Original-To`/`Cc`/`Bcc` headers |
| `httpRetries` | Automatic retries (0-5) on rate limiting (429) and network errors, exponential backoff |
| `httpTimeout` | HTTP timeout in seconds (default 30) |
| `logSends` | Log successful sends to the `resend` log |
| `disableSending` | Test mode: payloads are logged, nothing is sent |

Settings are stored in a dedicated `resend_settings` database table (name/value rows), not in the ProcessWire modules table. The API key is encrypted at rest — libsodium XSalsa20-Poly1305, or AES-256-GCM via OpenSSL when sodium is unavailable — with a key derived from the site salt in config.php (`$config->tableSalt`, falling back to `$config->userAuthSalt`). The salt never touches the database, so a database dump alone does not expose the key. If neither salt is configured, or if no supported crypto backend is available, secrets are not persisted and the module logs an error rather than storing them in plaintext. Consequences: the key must be re-entered if the site salt changes or when the database is moved to a site with a different config.php; the config screen never displays the stored key (leave the field blank to keep it). Pre-1.8 configuration is migrated automatically on first load.

The key's permission level is detected automatically with a cached probe request (1 hour, keyed by a hash of the key). With a sending-only key (`sending_access`) the admin UI locks all management sections — only the dashboard and Test Send remain available — and `bin/resend status` reports the restriction. WireMail sending is unaffected. Use a full access key to unlock the entire admin UI and CLI.

Swapping the key re-detects immediately. If you change the permission of the existing key in the Resend dashboard instead, use the "Re-check permission" button on the dashboard (or `$resend->refreshApiKeyPermission()`); a downgrade is also picked up automatically when a management call returns `restricted_api_key`.

## Sending Email (WireMail)

```php
$m = $mail->new(['module' => 'Resend']);
$m->to('user@example.com', 'User Name')
  ->from('sender@example.com', 'Sender')
  ->subject('Hello')
  ->body('Plain text version')
  ->bodyHTML('<p>HTML version</p>')
  ->header('cc', 'copy@example.com')
  ->header('bcc', 'hidden@example.com')
  ->attachment('/path/to/file.pdf')
  ->send(); // returns number of accepted recipients
```

For modules with a mailer selector that only shows installed `WireMail*`
providers, choose `WireMail Resend` (class name `WireMailResend`). This is an
alias module, so it sends through the same Resend client, API key, defaults,
logging, redirect mode and safety checks as `Resend`.

`send()` returns `0` on failure; the reason is written to the `resend` log and to admin notices.

### Resend Extras

| Method | Description |
|---|---|
| `scheduledAt($when)` | Schedule the send. Natural language (`'in 1 hour'`) or ISO 8601 |
| `addTag($name, $value)` | Attach a tag (ASCII letters, digits, `_`, `-`; max 256 chars) |
| `idempotencyKey($key)` | Prevent duplicate sends on retries (24h window, max 256 chars) |
| `useTemplate($idOrAlias, $variables)` | Send a published Resend template. Excludes html/text body |
| `topicId($id)` | Subscription-aware sending: contacts opted out of the topic are skipped |
| `getLastEmailId()` | Resend email id after a successful send |

Template note: when a template is used, `from`, `subject`, and `reply_to` in the payload take precedence over the template defaults. Reserved variable names: `FIRST_NAME`, `LAST_NAME`, `EMAIL`, `UNSUBSCRIBE_URL`.

Extras apply to the next `send()` only: after a completed send they reset to defaults. If the send fails they are kept, so a retry reuses the same idempotency key.

## API Client Reference

Resources are accessed via the module: `$resend->emails()`, `domains()`, `segments()`, `contacts()`, `contactProperties()`, `topics()`, `templates()`, `broadcasts()`, `webhooks()`, `logs()`. The raw transport is available via `$resend->client()`.

All methods return decoded arrays. Errors return `['error' => ['name', 'message', 'statusCode']]`. Check with `$resend->isError($result)`; the last error is available via `getLastError()`, the last raw response via `getLastResponse()`.

List endpoints accept cursor pagination params: `['limit' => 25, 'after' => $id]` or `['before' => $id]`. List responses contain `data` and `has_more`.

### Emails

```php
$resend->emails()->send(array $payload, $idempotencyKey = '');
$resend->emails()->sendBatch(array $emails, $idempotencyKey = ''); // up to 100; attachments and scheduled_at not supported by the batch API
$resend->emails()->get($id);
$resend->emails()->all($params);
$resend->emails()->update($id, $scheduledAt); // reschedule
$resend->emails()->cancel($id);            // cancel scheduled
$resend->emails()->attachments($emailId);
$resend->emails()->attachment($emailId, $attachmentId);
$resend->emails()->receivedAll($params);
$resend->emails()->received($id);
$resend->emails()->receivedAttachments($emailId);
$resend->emails()->receivedAttachment($emailId, $attachmentId);
```

### Domains

```php
$resend->domains()->create($name, ['region' => 'eu-west-1']);
$resend->domains()->all();
$resend->domains()->get($id); // includes DNS records
$resend->domains()->update($id, ['open_tracking' => true, 'click_tracking' => true]);
$resend->domains()->verify($id);
$resend->domains()->delete($id);
```

### Segments and Contacts

```php
$resend->segments()->create($name);
$resend->segments()->all();
$resend->segments()->get($id);
$resend->segments()->delete($id);
$resend->segments()->contacts($segmentId, $params);

$resend->contacts()->create(['email' => 'a@b.com', 'first_name' => 'A', 'properties' => [...]]);
$resend->contacts()->get($idOrEmail);
$resend->contacts()->update($idOrEmail, ['unsubscribed' => true]);
$resend->contacts()->delete($idOrEmail);
$resend->contacts()->all($params);
$resend->contacts()->addToSegment($idOrEmail, $segmentId);
$resend->contacts()->removeFromSegment($idOrEmail, $segmentId);
$resend->contacts()->segments($idOrEmail);
$resend->contacts()->topics($idOrEmail);
$resend->contacts()->updateTopics($idOrEmail, [['id' => $topicId, 'subscription' => 'opt-out']]);
```

### Contact Properties

```php
$resend->contactProperties()->create(['key' => 'plan', 'type' => 'string', 'fallback_value' => 'free']);
$resend->contactProperties()->all();
$resend->contactProperties()->get($id);
$resend->contactProperties()->update($id, $data);
$resend->contactProperties()->delete($id);
```

### Topics

```php
$resend->topics()->create(['name' => 'Product news', 'default_subscription' => 'opt-in']);
$resend->topics()->all();
$resend->topics()->get($id);
$resend->topics()->update($id, $data);
$resend->topics()->delete($id);
```

### Templates

```php
$resend->templates()->create(['name' => 'Welcome', 'alias' => 'welcome', 'html' => '...', 'variables' => [...]]);
$resend->templates()->all($params);
$resend->templates()->get($idOrAlias);
$resend->templates()->update($idOrAlias, $data);
$resend->templates()->delete($idOrAlias);
$resend->templates()->duplicate($idOrAlias);
$resend->templates()->publish($idOrAlias); // only published templates can be sent
```

### Broadcasts

```php
$resend->broadcasts()->create([
	'name' => 'July newsletter',
	'segment_id' => $segmentId,
	'from' => 'News <news@example.com>',
	'subject' => 'News',
	'html' => '<p>...</p><a href="{{{RESEND_UNSUBSCRIBE_URL}}}">Unsubscribe</a>',
]);
$resend->broadcasts()->all($params);
$resend->broadcasts()->get($id);
$resend->broadcasts()->update($id, $data);
$resend->broadcasts()->send($id);              // send now
$resend->broadcasts()->send($id, 'in 1 hour'); // schedule
$resend->broadcasts()->delete($id);
```

Broadcasts created via the API can only be edited and sent via the API (not the Resend visual editor), and vice versa.

### Webhooks

```php
$resend->webhooks()->create('https://example.com/hook/', ['email.delivered', 'email.bounced']);
$resend->webhooks()->all();
$resend->webhooks()->get($id);
$resend->webhooks()->update($id, ['status' => 'disabled']);
$resend->webhooks()->delete($id);
```

### Built-in Webhook Receiver

The `ResendWebhooks` autoload module provides a ready endpoint at `/resend-webhook/`:

1. Create a webhook in Resend (admin Webhooks screen or CLI) pointing to `https://your-site.com/resend-webhook/`.
2. Store its signing secret in the module config (`webhookSigningSecret`). Until a secret is configured, the URL responds with the regular 404.
3. Events are signature-verified, stored in the `resend_events` table (deduplicated by Svix message id, pruned after 90 days) and listed on the admin Events screen.

React to events from site code (e.g. in `site/ready.php`):

```php
$wire->addHookAfter('ResendWebhooks::processEvent', function($e) {
	$type = $e->arguments(0);  // 'email.bounced', 'contact.updated', ...
	$event = $e->arguments(1); // full decoded payload
	if($type === 'email.bounced') {
		// ...
	}
});
```

`processEvent` fires only on the first delivery of an event; Svix retries with the same message id are stored-deduplicated and not re-dispatched.

Custom receiving endpoint (alternative to the built-in one):

```php
$resend = $modules->get('Resend');
$payload = file_get_contents('php://input');
$headers = getallheaders();
// Uses the webhookSigningSecret module setting; or pass the secret as a third argument
if(!$resend->verifyWebhookSignature($payload, $headers)) {
	http_response_code(401);
	exit;
}
$event = json_decode($payload, true);
// $event['type'] => 'email.delivered', $event['data'] => payload
http_response_code(200);
exit;
```

Signature verification implements the Svix scheme: HMAC-SHA256 over `id.timestamp.body` with a 5-minute timestamp tolerance and constant-time comparison.

### Logs

```php
$resend->logs()->all($params);
$resend->logs()->get($id);
```

## Admin UI (ProcessResend)

Setup > Resend. Requires the `resend-admin` permission (created on install).
The admin navigation is grouped by workflow. Top-level groups stay visible, and the active group shows its related screens in a secondary subnav row while preserving the direct URLs below.

| Screen | URL segment | Actions |
|---|---|---|
| Dashboard | `/` | Domain readiness, delivery health, next actions, 14-day sending activity chart, status breakdown and recent emails |
| Sent Emails | `emails/` | Summary cards, status mix and paginated sent email list |
| Received | `received/` | Inbound summary, routing details, attachment counts and paginated received email list |
| Email detail | `email/?id=` | HTML preview, attachments, cancel or reschedule scheduled emails |
| Domains | `domains/` | Readiness overview, create, verify, delete and tracking status |
| Domain detail | `domain/?id=` | DNS readiness, DNS Assistant for SPF/MX/CNAME guidance, records, tracking controls, delete and next-step guidance |
| Contacts | `contacts/` | Summary cards, create, delete, unsubscribe/resubscribe and filter by segment |
| Contact detail | `contact/?id=` | Identity, global subscription, segment membership, topic subscriptions and properties |
| Segments | `segments/` | Audience overview, create, delete and jump to filtered contacts or broadcasts |
| Contact Properties | `properties/` | Summary, type guidance, create (key, type, fallback value) and delete |
| Topics | `topics/` | Create, delete |
| Templates | `templates/` | Create draft, publish, duplicate, delete |
| Template detail | `template/?id=` | Edit name, alias, subject and HTML; publish |
| Broadcasts | `broadcasts/`, `broadcast/` | Create and edit drafts, send now or schedule, delete |
| Webhooks | `webhooks/` | Create, enable/disable, delete |
| Events | `events/` | Locally stored webhook events (works with any key permission) |
| Logs | `logs/` | API request logs |
| Settings | module config | Resend API key, sender defaults, delivery options and webhook signing secret |
| Test Send | `test/` | Preflight sender check, send or schedule a test email |

All mutating actions are POST with CSRF validation (`hasValidToken()`).

### Admin Localization

Bundled ProcessWire language CSV files are available in `languages/` for English, French, German and Spanish. Import the matching CSV from ProcessWire's language editor to localize the Resend admin screens and module configuration labels.

## PocketWire integration

The opt-in PocketWire provider is deliberately narrower than the administration
and PHP APIs. A current logged-in actor with `resend-admin` may read one local
status DTO containing the module version and explicit non-exposure flags. The
provider does not inspect or reveal configuration, credentials, sender or
webhook readiness, delivery records, contacts, recipients, or message content;
it performs no external request and exposes no mutation or send action.

## CLI

`bin/resend` bootstraps ProcessWire (walks up from the script, then from the current working directory, to find `index.php` + `wire/`; the cwd pass covers symlinked module directories; override with the `PW_PATH` environment variable) and dispatches to `Smnv\Resend\Cli`.

```
php bin/resend <command> [arguments] [--options]
```

| Command | Description |
|---|---|
| `status` | Config summary and domain status |
| `send --to= --subject= --html=/--text=/--html-file= [--from=] [--schedule=] [--tag=name:value] [--idempotency=]` | Send an email |
| `emails:list`, `emails:get <id>`, `emails:cancel <id>`, `emails:reschedule <id> <when>` | Sent emails |
| `received:list` | Received emails |
| `domains:list`, `domains:get <id>`, `domains:create <name> [--region=]`, `domains:verify <id>`, `domains:delete <id>` | Domains |
| `contacts:list [--segment=]`, `contacts:get`, `contacts:create <email> [--first=] [--last=] [--segment=]`, `contacts:import <file.csv> [--segment=] [--dry-run]`, `contacts:unsubscribe`, `contacts:resubscribe`, `contacts:delete` | Contacts |
| `segments:list`, `segments:create <name>`, `segments:delete <id>` | Segments |
| `properties:list`, `properties:create <key> [--type=] [--fallback=]`, `properties:delete <id>` | Contact properties |
| `topics:list`, `topics:create <name> [--desc=] [--default=]`, `topics:delete <id>` | Topics |
| `templates:list`, `templates:publish`, `templates:duplicate`, `templates:delete` | Templates |
| `broadcasts:list`, `broadcasts:create --name= --segment= --from= --subject= --html-file=`, `broadcasts:send <id> [--schedule=]`, `broadcasts:delete <id>` | Broadcasts |
| `webhooks:list`, `webhooks:create <url> --events=a,b`, `webhooks:delete <id>` | Webhooks |
| `logs:list` | API request logs |
| `config:list`, `config:set <name> <value>` | Module settings (secrets masked in output, stored encrypted) |

List commands accept `--limit=`, `--after=`, `--before=` (cursor pagination). Add `--json` to any command for raw JSON output. Exit code is 0 on success and 1 on any error, so commands are safe to chain in shell scripts and cron jobs. Secrets (API key tokens, webhook signing secrets) are printed once at creation and never stored.

## Security

- All mutating admin actions are POST requests validated with `hasValidToken()` (CSRF).
- Email HTML previews render inside a fully sandboxed iframe (`sandbox=""`, `referrerpolicy="no-referrer"`, entity-encoded `srcdoc`). Received email HTML is untrusted input and never touches the admin DOM directly.
- CR/LF characters are stripped from all single-line values entering the payload (subject, address names, custom headers, attachment filenames) and from outgoing HTTP headers, preventing header injection.
- Log messages are newline-stripped to prevent log injection.
- Webhook signature verification (Svix scheme) uses constant-time comparison, rejects timestamps older than 5 minutes (replay protection), non-numeric timestamps, empty payloads and malformed secrets.
- All values rendered in views are entity-encoded; ids in URLs are encoded with `rawurlencode()`.
- The API key is stored encrypted in the `resend_settings` table (libsodium XSalsa20-Poly1305 or OpenSSL AES-256-GCM); the encryption key derives from the site salt in config.php, which is never stored in the database. If no site salt or crypto backend is available, secrets are not persisted rather than being stored in plaintext.
- The API key config field is masked, never re-populated with the stored key, and excluded from browser autofill.

## Rate Limits

Resend enforces 5 requests per second per team by default (HTTP 429 above that). Set `httpRetries` (0-5) to retry 429 responses and network errors automatically with exponential backoff (0.5s, 1s, 2s, ...); other error responses are never retried. For bulk sending prefer `sendBatch()` — up to 100 emails in one request.

## Logging

Sends and errors are written to the `resend` log (Setup > Logs). In test mode (`disableSending`) the logged payload replaces attachment contents with their byte size and truncates html/text bodies to 1024 characters.
