# Resend

Resend brings reliable email delivery, audience lists, templates, broadcasts, webhooks and delivery diagnostics into ProcessWire.

![Resend](assets/Resend.png)

It is made for sites that need transactional email, marketing sends or delivery visibility without leaving the ProcessWire admin: contact forms, booking flows, onboarding emails, newsletters, product updates, notifications and operational alerts.

**Author:** Maxim Semenov  
**Website:** [smnv.org](https://smnv.org)  
**Email:** [maxim@smnv.org](mailto:maxim@smnv.org)

If this project helps your work, consider supporting future development: [GitHub Sponsors](https://github.com/sponsors/mxmsmnv) or [smnv.org/sponsor](https://smnv.org/sponsor/).

## What Resend Does

- Sends ProcessWire email through the Resend API as a drop-in WireMail provider.
- Supports transactional sends, batch sends, scheduling, cancellation, idempotency keys and tags.
- Manages domains, DNS records, verification and open/click tracking.
- Helps with DNS setup when SPF or MX records already exist.
- Manages contacts, segments, contact properties and topics.
- Creates, edits, publishes, duplicates and sends templates.
- Creates, edits, sends and schedules broadcasts.
- Receives and verifies Resend webhooks at `/resend-webhook/`.
- Stores webhook events locally for inspection and automation.
- Lists sent email, received email, attachments, API logs and delivery signals.
- Includes safe simulator test sends using `resend.dev` recipients.
- Stores API keys and webhook secrets encrypted in a dedicated settings table.
- Includes bundled admin language files for English, French, German and Spanish.

## Admin Area

Resend adds a Setup > Resend admin section where site editors can:

- monitor domain readiness and recent sending activity;
- inspect sent and received email;
- create, verify and delete sending domains;
- use the DNS Assistant for SPF, MX, TXT and CNAME setup guidance;
- manage contacts, segments, properties and topics;
- create templates and broadcasts;
- configure webhooks and inspect stored events;
- review API request logs;
- send safe test emails;
- open module settings from the shared admin navigation.

## Localization

Bundled ProcessWire language CSV files live in `languages/`: English, French, German and Spanish. Import them from ProcessWire's language editor when a multilingual admin needs localized Resend screens.

## Website Integration

For normal site email, keep using ProcessWire's mail API:

```php
$mail = wireMail();
$mail->to('person@example.com')
	->from('Site <hello@example.com>')
	->subject('Thanks for your request')
	->bodyHTML('<p>We received your request.</p>')
	->send();
```

For Resend-specific behavior, use the module extras:

```php
$mail = wireMail();
$mail->to('person@example.com')
	->subject('Welcome')
	->bodyHTML('<p>Hello</p>');

$mail->scheduledAt('in 1 hour');
$mail->addTag('source', 'website');
$mail->idempotencyKey('welcome-' . $page->id);
$mail->send();
```

For direct API access, use resource methods:

```php
$resend = wire('modules')->get('Resend');

$resend->domains()->all();
$resend->contacts()->create(['email' => 'person@example.com']);
$resend->broadcasts()->send($id, 'tomorrow 9am');
```

Modules that only list mailers named `WireMail*` can select `WireMail Resend`. It uses the same encrypted settings and delivery behavior as the main `Resend` module.

## Webhooks

Resend includes a built-in webhook receiver at:

```text
https://your-site.com/resend-webhook/
```

Store the webhook signing secret in module settings, then handle verified events from site code:

```php
$wire->addHookAfter('ResendWebhooks::processEvent', function($event) {
	$type = $event->arguments(0);
	$payload = $event->arguments(1);

	if($type === 'email.bounced') {
		// Update local state, notify an admin, or pause a workflow.
	}
});
```

Events are stored locally and deduplicated by Svix message id.

## CLI

Resend includes a CLI for deployment checks, scripted sends, imports and diagnostics:

```bash
php site/modules/Resend/bin/resend help
php bin/resend status
php bin/resend send --to=user@example.com --subject=Hello --html-file=./mail.html
php bin/resend domains:list --json
php bin/resend broadcasts:send <id> --schedule="tomorrow 9am"
```

The ProcessWire root is auto-detected. Use `PW_PATH=/path/to/pw-root` when running from another directory.

## Installation

1. Copy the `Resend` folder into `/site/modules/`.
2. In ProcessWire Admin, refresh modules.
3. Install `Resend`.
4. Enter your Resend API key and default sender in the module settings.
5. Open Setup > Resend.
6. Add or verify a sending domain before sending real email.

`ProcessResend`, `ResendWebhooks` and `WireMailResend` install automatically.

## Requirements

- ProcessWire 3.0.184+
- PHP 8.1+
- A Resend account
- A verified sending domain for production email

## Documentation

See [DOCUMENTATION.md](DOCUMENTATION.md) for setup, configuration, API reference and admin workflows.

See [AGENTS.md](AGENTS.md) for AI-agent usage guidance and Olivia-ready safety notes.

See [CHANGELOG.md](CHANGELOG.md) for release notes.

## Author

Maxim Semenov  
[smnv.org](https://smnv.org)  
[maxim@smnv.org](mailto:maxim@smnv.org)

## License

MIT
