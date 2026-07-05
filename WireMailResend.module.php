<?php namespace ProcessWire;

require_once __DIR__ . '/Resend.module.php';

/**
 * WireMailResend
 *
 * Compatibility alias for modules that only discover WireMail providers whose
 * class name starts with "WireMail".
 *
 * Settings, sending and API behavior are inherited from Resend.
 */
class WireMailResend extends Resend {

	public static function getModuleInfo() {
		return [
			'title' => 'WireMail Resend',
			'summary' => 'Compatibility alias for selecting Resend in modules that list WireMail providers by class name.',
			'version' => 100,
			'author' => 'Maxim Semenov',
			'href' => 'https://github.com/mxmsmnv/Resend',
			'icon' => 'envelope-o',
			'singular' => false,
			'autoload' => false,
			'requires' => [
				'Resend',
				'ProcessWire>=3.0.184',
				'PHP>=8.1',
			],
		];
	}

	public function ___install() {
		// Resend owns the shared settings table.
	}

	public function ___uninstall() {
		// Keep Resend settings when only the compatibility alias is removed.
	}

	public function ___upgrade($fromVersion, $toVersion) {
		// Resend handles database setup and migrations.
	}
}
