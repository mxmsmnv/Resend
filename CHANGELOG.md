# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.0.0] - 2026-07-04

### Added

- First public release of the Resend ProcessWire module.
- Added `Resend` as a WireMail provider and resource-based Resend API client.
- Added `WireMailResend` compatibility alias for modules that discover `WireMail*` providers.
- Added ProcessWire admin UI under Setup > Resend.
- Added sent email, received email, domain, contact, segment, contact property, topic, template, broadcast, webhook, event, log and test-send screens.
- Added dashboard delivery charts, domain readiness summaries and compact UIkit-based admin layouts.
- Added DNS Assistant guidance for SPF, MX, TXT and CNAME records on domain detail pages.
- Added built-in webhook receiver at `/resend-webhook/` with Svix signature verification and local event storage.
- Added CLI commands for status checks, sending, domains, contacts, segments, properties, topics, templates, broadcasts, webhooks, logs and configuration.
- Added encrypted settings storage for API keys and webhook signing secrets.
- Added dependency-free tests for crypto, client retries, CLI behavior, stats and webhook signature verification.
- Added Olivia-ready `AGENTS.md` guidance and Vox-style `README.md` with the Resend doodle image.
