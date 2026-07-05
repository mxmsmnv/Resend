# AGENTS.md

Instructions for AI coding agents working on this repository.

## Project Overview

Resend is a ProcessWire 3.x email, audience and delivery-operations module integrating the Resend email platform (resend.com):

- `Resend.module.php` — core module. Extends `WireMail` for drop-in sending, exposes the resource-based API client, holds module configuration. Class `ProcessWire\Resend`.
- `ProcessResend.module.php` — admin UI (Setup > Resend). Thin controllers only: parse input, validate CSRF, call resources, redirect, render views. Class `ProcessWire\ProcessResend`.
- `ResendWebhooks.module.php` — autoload webhook receiver (URL hook at `/resend-webhook/`, signature verification, `resend_events` storage, hookable `processEvent()`). Class `ProcessWire\ResendWebhooks`.
- `src/` — namespace `Smnv\Resend`. `Client.php` (HTTP transport), `Resource.php` (base), one class per API resource (`Emails`, `Domains`, `Segments`, `Contacts`, `ContactProperties`, `Topics`, `Templates`, `Broadcasts`, `Webhooks`, `Logs`), `Stats.php` (pure aggregation for dashboard charts), and `Cli.php` (command dispatcher).
- `bin/resend` — CLI entry point. Bootstraps ProcessWire, delegates everything to `Smnv\Resend\Cli`.
- `views/` — one view per admin screen, named `resend.<screen>.php`. Shared helpers in `resend.partials.php`.
- Admin UI markup uses native AdminThemeUikit/UIkit classes directly; no module CSS is injected.

Target: PHP 8.1+, ProcessWire 3.0.184+.

## Agent Use: Olivia-Ready Guidance

This file follows the intent of Olivia's agent-readable documentation standard: `AGENTS.md` is behavioral guidance, not proof of current site state. Before acting in a ProcessWire site, confirm the module is installed, configured and allowed by the current project policy. If this file conflicts with live site state, approved project memory, a Blueprint, or an explicit user decision, surface the conflict and follow the stricter or newer source.

Use this module when a site needs:

- transactional email through ProcessWire's normal `$mail` / `wireMail()` flow;
- Resend API access for domains, emails, received emails, contacts, segments, contact properties, topics, templates, broadcasts, webhooks, events and logs;
- a ProcessWire admin screen for delivery operations under Setup > Resend;
- a built-in webhook receiver at `/resend-webhook/` with verified, stored events;
- safe test sends and delivery diagnostics.

Do not recommend this module when the project only needs local SMTP with no Resend account, requires a different email provider, needs a drag-and-drop newsletter editor inside ProcessWire, or needs automatic DNS provider changes. The DNS Assistant checks public DNS and suggests safe values; it does not edit DNS at the provider.

## How Agents Should Use This Module In A Website

1. Confirm site state first: module installed, API key configured, sender domain verified, `resend-admin` permission assigned, and sending-only vs full-access API key constraints understood.
2. Prefer the standard ProcessWire mail API for ordinary site emails. This keeps forms, password resets, booking confirmations and notifications provider-agnostic:

```php
$mail = wireMail();
$mail->to('person@example.com')
	->from('Site <hello@example.com>')
	->subject('Thanks for your request')
	->bodyHTML('<p>We received your request.</p>')
	->send();
```

3. Use the `Resend` module object only when a site needs Resend-specific capabilities:

```php
/** @var ProcessWire\Resend $resend */
$resend = wire('modules')->get('Resend');
$result = $resend->domains()->all();
if($resend->isError($result)) {
	wire('log')->save('resend', $resend->getLastError()['message'] ?? 'Resend error');
}
```

4. For scheduled sends, tags, idempotency and templates, use the fluent `WireMail` extras before `send()`:

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

5. For marketing/audience features, use Contacts, Segments, Topics, Templates and Broadcasts resources. Respect consent and unsubscribe requirements. Broadcast HTML should include `{{{RESEND_UNSUBSCRIBE_URL}}}` when appropriate.
6. For inbound or lifecycle automation, prefer `ResendWebhooks::processEvent` hooks in `site/ready.php` over polling when possible.
7. Use the CLI for inspection, deployment checks, dry-run imports and scripted operations only after the ProcessWire root is known (`PW_PATH=/path/to/site php bin/resend ...`).

## Public Calls Agents May Use

Use resource methods through `$resend->resource()->method()`. Do not invent flat methods on `Resend`.

Core module:

```php
$resend->emails();
$resend->domains();
$resend->segments();
$resend->contacts();
$resend->contactProperties();
$resend->topics();
$resend->templates();
$resend->broadcasts();
$resend->webhooks();
$resend->logs();
$resend->isError($result);
$resend->getLastError();
$resend->getLastResponse();
$resend->verifyWebhookSignature($payload, $headers);
$resend->apiKeyPermission();
$resend->refreshApiKeyPermission();
```

WireMail extras:

```php
$mail->scheduledAt($when);
$mail->addTag($name, $value);
$mail->idempotencyKey($key);
$mail->useTemplate($idOrAlias, $variables);
$mail->topicId($topicId);
$mail->getLastEmailId();
```

Resource calls:

```php
$resend->emails()->send($payload);
$resend->emails()->sendBatch($emails);
$resend->emails()->all($params);
$resend->emails()->get($id);
$resend->emails()->update($id, $scheduledAt);
$resend->emails()->cancel($id);
$resend->emails()->attachments($emailId);
$resend->emails()->attachment($emailId, $attachmentId);
$resend->emails()->receivedAll($params);
$resend->emails()->received($id);
$resend->emails()->receivedAttachments($emailId);
$resend->emails()->receivedAttachment($emailId, $attachmentId);

$resend->domains()->create($name, $options);
$resend->domains()->all($params);
$resend->domains()->get($id);
$resend->domains()->update($id, $data);
$resend->domains()->verify($id);
$resend->domains()->delete($id);

$resend->segments()->create($name);
$resend->segments()->all($params);
$resend->segments()->get($id);
$resend->segments()->delete($id);
$resend->segments()->contacts($segmentId, $params);

$resend->contacts()->create($data);
$resend->contacts()->all($params);
$resend->contacts()->get($idOrEmail);
$resend->contacts()->update($idOrEmail, $data);
$resend->contacts()->delete($idOrEmail);
$resend->contacts()->addToSegment($idOrEmail, $segmentId);
$resend->contacts()->removeFromSegment($idOrEmail, $segmentId);
$resend->contacts()->segments($idOrEmail, $params);
$resend->contacts()->topics($idOrEmail, $params);
$resend->contacts()->updateTopics($idOrEmail, $topics);

$resend->contactProperties()->create($data);
$resend->contactProperties()->all($params);
$resend->contactProperties()->get($id);
$resend->contactProperties()->update($id, $data);
$resend->contactProperties()->delete($id);

$resend->topics()->create($data);
$resend->topics()->all($params);
$resend->topics()->get($id);
$resend->topics()->update($id, $data);
$resend->topics()->delete($id);

$resend->templates()->create($data);
$resend->templates()->all($params);
$resend->templates()->get($idOrAlias);
$resend->templates()->update($idOrAlias, $data);
$resend->templates()->duplicate($idOrAlias);
$resend->templates()->publish($idOrAlias);
$resend->templates()->delete($idOrAlias);

$resend->broadcasts()->create($data);
$resend->broadcasts()->all($params);
$resend->broadcasts()->get($id);
$resend->broadcasts()->update($id, $data);
$resend->broadcasts()->send($id, $scheduledAt);
$resend->broadcasts()->delete($id);

$resend->webhooks()->create($endpointUrl, $events);
$resend->webhooks()->all($params);
$resend->webhooks()->get($id);
$resend->webhooks()->update($id, $data);
$resend->webhooks()->delete($id);

$resend->logs()->all($params);
$resend->logs()->get($id);
```

For full signatures, examples and edge cases, use `DOCUMENTATION.md` until a dedicated `API.md` exists. Treat `DOCUMENTATION.md` and source code as stronger than README for exact API usage.

## Safe, Risky And Approval-Gated Operations

Safe without special approval:

- inspect configuration, status, domains, logs, events and list endpoints;
- explain setup requirements, API key permission status and DNS readiness;
- send test email to `*@resend.dev` simulator recipients;
- add non-destructive UI or documentation improvements inside this module.

Requires explicit user approval in a real site:

- send real emails or broadcasts;
- import contacts or change topic subscriptions;
- create, verify or delete domains;
- change sender defaults, redirect rules, global BCC, webhook secrets or retry settings;
- create public webhooks or expose `/resend-webhook/` on production;
- publish templates or schedule broadcasts.

High-risk operations:

- deleting domains, contacts, segments, topics, templates, broadcasts, webhooks or local webhook event tables;
- changing webhook verification, crypto, secret storage, logging or HTML preview behavior;
- editing DNS records at a provider outside this module;
- changing unsubscribe behavior or consent-related audience workflows.

Forbidden by default:

- store API keys, webhook signing secrets or one-time tokens in plaintext;
- render stored secrets back into forms, CLI output or logs;
- echo received email HTML directly into the admin DOM;
- bypass CSRF for admin mutations;
- call `WireHttp` or `curl` outside `src/Client.php`;
- reintroduce API Keys management screens or dead API key code unless explicitly requested and reviewed.

## Common Website Patterns

Contact form:

- Use `wireMail()` for the notification.
- Strip or validate user input before building subject/from/reply-to values.
- Do not put user-supplied HTML into the email without sanitizing it.
- Use `addTag('source', 'contact-form')` if reporting needs source grouping.

Account or booking notification:

- Use `wireMail()` and optionally `idempotencyKey()` to avoid duplicate sends.
- Use `scheduledAt()` only for intentional delayed delivery.
- Keep transactional content separate from Broadcasts.

Newsletter or campaign:

- Model the audience with Segments, Contacts, Topics and Contact Properties.
- Use Templates for reusable bodies and Broadcasts for segment sends.
- Include unsubscribe merge tags where required.
- Do not send or schedule without user approval and a verified sender domain.

Webhook-driven automation:

- Configure one webhook endpoint pointing to `/resend-webhook/`.
- Store the signing secret in module settings.
- React through `ResendWebhooks::processEvent`.
- Keep event handlers idempotent because delivery systems may retry.

Domain setup:

- Create or inspect the domain in Setup > Resend > Domains.
- Use the domain detail DNS Assistant to interpret public TXT/MX/CNAME records.
- If an SPF record already exists, merge into one SPF TXT record; do not add a second SPF record at the same host.
- If MX records already exist, keep existing mail routing and add Resend's MX record with the shown priority when needed.

## Commands

```
# Lint everything (must pass before any delivery)
for f in *.php src/*.php views/*.php tests/*.php bin/resend; do php -l "$f"; done

# Run the test suite (plain PHP, no ProcessWire needed; must pass before any delivery)
php tests/run.php

# CLI (requires a ProcessWire installation)
php bin/resend help
PW_PATH=/path/to/pw-root php bin/resend status
```

There is no build step, no Composer, no external dependencies. Do not add any.

## Architecture Rules

1. All HTTP logic lives in `src/Client.php`. Resource classes contain only endpoint paths and payload shaping. Never call `WireHttp` or `curl` anywhere else.
2. Every Resend endpoint gets a method on the matching resource class. Access pattern: `$resend->domains()->all()`, never flat methods on the module.
3. `ProcessResend` methods stay thin: input, CSRF, resource call, notice, redirect, render. No business logic in Process methods or views.
4. Views receive data via `wireRenderFile()` variables. Views never call the API.
5. `Cli.php` commands delegate to resources; no HTTP logic in the CLI.
6. Errors are normalized arrays: `['error' => ['name', 'message', 'statusCode']]`. Check with `$resend->isError($result)`. Do not throw exceptions from resource methods.

## ProcessWire Conventions (Critical)

- NEVER add `declare(strict_types=1)` — it breaks the ProcessWire FileCompiler.
- Module versions are integers in `getModuleInfo()` (e.g., `120` for 1.2.0). Bump on every delivered change.
- Hookable methods use the `___` prefix (`___send()`, `___execute()`).
- CSRF: every mutating admin action is POST and validated with `$session->CSRF->hasValidToken()` before any side effect.
- Module files use namespace `ProcessWire`; `src/` files use `Smnv\Resend` and are loaded via `require_once` at the top of `Resend.module.php`.
- Indentation: tabs in PHP files.

## Project Conventions

- View files use the `resend.` prefix (`resend.domains.php`). No underscore prefixes anywhere.
- Icons are inline SVG paths rendered by `resendIcon()` in `views/resend.partials.php`, using `currentColor`. No icon fonts, no CDN, no new icon sources. Form hints come from `resendNote()`.
- English only in code, comments, and documentation. No emoji anywhere.
- Documentation set: `README.md` (concise overview), `DOCUMENTATION.md` (full technical reference), `CHANGELOG.md` (Keep a Changelog format). Update all three when behavior changes.
- Admin UI uses native AdminThemeUikit/UIkit classes directly. Do not add an external CSS framework or reintroduce a module stylesheet unless the user explicitly requests a new styling layer.
- The Resend API requires a `User-Agent` header on every request (403 without it). Never remove it from `Client`.
- List endpoints use cursor pagination (`limit`, `after`, `before`); list responses contain `data` and `has_more`.

## Security Requirements (Do Not Regress)

1. Email HTML previews render only inside a sandboxed iframe: `sandbox=""`, `referrerpolicy="no-referrer"`, entity-encoded `srcdoc`. Received email HTML is attacker-controlled; it must never be echoed into the admin DOM.
2. Strip CR/LF from every single-line value entering an email payload or HTTP header (`sanitizeLine()` in the module, `sanitizeHeaders()` in Client).
3. Strip newlines from log messages (log injection).
4. Entity-encode every value rendered in views (`$sanitizer->entities()`); urlencode ids in URLs (`rawurlencode` via `Resource::seg()`).
5. Webhook verification (`Webhooks::verifySignature()`): constant-time comparison (`hash_equals`), 5-minute timestamp tolerance, strict base64 secret decoding, reject empty payloads. Do not weaken any of these.
6. Secrets (API key tokens, webhook signing secrets) are displayed once at creation and never persisted or logged.
7. The API key config field stays masked, with `autocomplete="new-password"`, and the stored key is never rendered back into the form.
8. Settings live in the dedicated `resend_settings` table; secrets (`Resend::ENCRYPTED_SETTINGS`: API key, webhook signing secret) are stored encrypted via `Smnv\Resend\Crypto` (sodium secretbox, OpenSSL AES-256-GCM fallback) with a key derived from the site salt (`$config->tableSalt` -> `$config->userAuthSalt`). Do not move settings back into the modules table, do not store secrets in plaintext, and never render stored secrets back into forms or CLI output.

## Testing

Plain-PHP test suite in `tests/` (no PHPUnit, no dependencies, no ProcessWire bootstrap): `php tests/run.php`. It covers `Crypto`, `Stats`, `Cli` (mocked module, memory streams), `Client` retries (stubbed `WireHttp`) and `Webhooks::verifySignature()`. Add a `tests/test-<name>.php` file for anything standalone-testable; use the shared `check($label, $ok)` helper.

Verification workflow:

1. `php -l` on every PHP file.
2. `php tests/run.php` — all checks must pass.
3. `grep -rn` for residuals after renames or refactors.
4. Live verification against a real Resend API key happens outside this repository (PW bootstrap of a site with the module symlinked).

## Release Checklist

1. Bump the integer version in all four `getModuleInfo()` blocks (Resend, ProcessResend, ResendWebhooks, WireMailResend) and the `Client::USER_AGENT` version.
2. Add a dated entry to `CHANGELOG.md`.
3. Update `README.md` / `DOCUMENTATION.md` if behavior or API changed.
4. Lint all PHP files and run `php tests/run.php`.
5. Grep for leftover old names or debug output.
6. Commit, tag `vX.Y.Z`, push to https://github.com/mxmsmnv/Resend.
