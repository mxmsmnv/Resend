<?php namespace ProcessWire;

if(!function_exists(__NAMESPACE__ . '\resendNav')) {

	/**
	 * Render section navigation.
	 *
	 * @param string $adminUrl Process page base URL
	 * @param string $active Active section key
	 * @param bool $restricted Sending-only API key: lock management sections
	 * @return string
	 */
	function resendNav($adminUrl, $active, $restricted = false) {
		$groups = [
			[
				'label' => 'Dashboard',
				'primary' => '',
				'items' => ['' => 'Dashboard'],
			],
			[
				'label' => 'Messages',
				'primary' => 'emails',
				'items' => ['emails' => 'Sent', 'received' => 'Received'],
			],
			[
				'label' => 'Domains',
				'primary' => 'domains',
				'items' => ['domains' => 'Domains'],
			],
			[
				'label' => 'Audience',
				'primary' => 'contacts',
				'items' => ['contacts' => 'Contacts', 'segments' => 'Segments', 'properties' => 'Properties', 'topics' => 'Topics'],
			],
			[
				'label' => 'Content',
				'primary' => 'templates',
				'items' => ['templates' => 'Templates', 'broadcasts' => 'Broadcasts'],
			],
			[
				'label' => 'Diagnostics',
				'primary' => 'events',
				'items' => ['events' => 'Events', 'webhooks' => 'Webhooks', 'logs' => 'Logs'],
			],
			[
				'label' => 'Test Send',
				'primary' => 'test',
				'items' => ['test' => 'Test Send'],
			],
		];
		// Sections usable with a sending-only key ('events' is local data)
		$sendingOnly = ['', 'events', 'test'];
		$sanitizer = wire()->sanitizer;
		$activeGroup = null;
		$out = "<div class='resend-admin-nav uk-margin-medium-bottom uk-flex uk-flex-top'><div class='uk-width-expand'><ul class='uk-subnav uk-subnav-pill'>\n";
		foreach($groups as $index => $group) {
			$groupActive = false;
			foreach($group['items'] as $segment => $label) {
				if($segment === $active) $groupActive = true;
			}
			if($groupActive) $activeGroup = $index;
			$primary = $group['primary'];
			$primaryAllowed = !$restricted || in_array($primary, $sendingOnly);
			$class = $groupActive ? " class='uk-active'" : '';
			if(!$primaryAllowed) {
				$out .= "<li class='uk-disabled'><span title='Requires a full access API key'>" . $sanitizer->entities($group['label']) . "</span></li>\n";
				continue;
			}
			$url = $sanitizer->entities($adminUrl . ($primary ? "$primary/" : ''));
			$out .= "<li$class><a href='$url'>" . $sanitizer->entities($group['label']) . "</a>";
			$out .= "</li>\n";
		}
		$out .= "</ul>";
		if($activeGroup !== null && count($groups[$activeGroup]['items']) > 1) {
			$out .= "<div class='uk-margin-small-top'><ul class='uk-subnav uk-subnav-pill uk-margin-remove-bottom'>\n";
			foreach($groups[$activeGroup]['items'] as $segment => $label) {
				$allowed = !$restricted || in_array($segment, $sendingOnly);
				$itemClass = $segment === $active ? " class='uk-active'" : '';
				if(!$allowed) {
					$out .= "<li class='uk-disabled'><span title='Requires a full access API key'>" . $sanitizer->entities($label) . "</span></li>\n";
					continue;
				}
				$itemUrl = $sanitizer->entities($adminUrl . ($segment ? "$segment/" : ''));
				$out .= "<li$itemClass><a href='$itemUrl'>" . $sanitizer->entities($label) . "</a></li>\n";
			}
			$out .= "</ul></div>";
		}
		$settingsUrl = $sanitizer->entities(wire()->config->urls->admin . 'module/edit?name=Resend');
		$out .= "</div><div class='uk-width-auto'><a class='uk-link-muted uk-display-inline-flex uk-flex-middle' href='$settingsUrl' title='Resend settings' aria-label='Resend settings'>" . resendIcon('settings') . "</a></div></div>\n";
		return $out;
	}

	/**
	 * Render a hidden CSRF input.
	 *
	 * @param SessionCSRF $csrf
	 * @return string
	 */
	function resendCsrf($csrf) {
		$sanitizer = wire()->sanitizer;
		$name = $sanitizer->entities($csrf->getTokenName());
		$value = $sanitizer->entities($csrf->getTokenValue());
		return "<input type='hidden' name='$name' value='$value'>";
	}

	/**
	 * Render an inline action form (single button POST).
	 *
	 * @param string $url
	 * @param array $fields Hidden field name => value
	 * @param string $label Button label
	 * @param SessionCSRF $csrf
	 * @param string $class Extra button class
	 * @param string $confirm Optional confirm message
	 * @return string
	 */
	function resendActionForm($url, array $fields, $label, $csrf, $class = '', $confirm = '') {
		$sanitizer = wire()->sanitizer;
		$confirm = str_replace(["\\", "'", "\r", "\n"], ["\\\\", "\\'", ' ', ' '], (string) $confirm);
		$onsubmit = strlen($confirm) ? " onsubmit=\"return confirm('" . $sanitizer->entities($confirm) . "')\"" : '';
		$url = $sanitizer->entities($url);
		$class = (string) $class;
		$buttonClass = 'uk-button uk-button-small uk-button-primary';
		if(strpos($class, 'danger') !== false) {
			$buttonClass = 'uk-button uk-button-small uk-button-danger';
		} elseif(strpos($class, 'secondary') !== false) {
			$buttonClass = 'uk-button uk-button-small uk-button-default';
		}
		$out = "<form class='uk-display-inline-block uk-margin-right' method='post' action='$url'$onsubmit>";
		$out .= resendCsrf($csrf);
		foreach($fields as $name => $value) {
			$name = $sanitizer->entities($name);
			$value = $sanitizer->entities($value);
			$out .= "<input type='hidden' name='$name' value='$value'>";
		}
		$out .= "<button type='submit' class='$buttonClass'>" . $sanitizer->entities($label) . "</button>";
		$out .= "</form>";
		return $out;
	}

	/**
	 * Render cursor pagination links.
	 *
	 * @param string $url Base URL
	 * @param array $items Current page items (must contain 'id')
	 * @param bool $hasMore
	 * @return string
	 */
	function resendPagination($url, array $items, $hasMore) {
		if(!$hasMore || !count($items)) return '';
		$last = end($items);
		if(!isset($last['id'])) return '';
		$sanitizer = wire()->sanitizer;
		$after = rawurlencode($last['id']);
		$url = $sanitizer->entities($url);
		return "<p class='uk-margin-medium-top'><a class='uk-button uk-button-default' href='{$url}?after={$after}'>Next page</a></p>";
	}

	/**
	 * Render a compact horizontal summary panel.
	 *
	 * Values are trusted view markup; dynamic pieces must be encoded by callers.
	 *
	 * @param array $items
	 * @return string
	 */
	function resendSummaryPanel(array $items) {
		if(!count($items)) return '';
		$out = "<div class='uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom'>";
		$out .= "<div class='uk-grid-small uk-grid-divider uk-child-width-1-" . min(4, max(1, count($items))) . "@l uk-child-width-1-2@s' uk-grid>";
		foreach($items as $item) {
			$label = isset($item['label']) ? (string) $item['label'] : '';
			$value = isset($item['value']) ? (string) $item['value'] : '';
			$note = isset($item['note']) ? (string) $item['note'] : '';
			$extra = isset($item['extra']) ? (string) $item['extra'] : '';
			$out .= "<div>";
			$out .= "<p class='uk-text-meta uk-text-uppercase uk-margin-remove'>$label</p>";
			$out .= "<div class='uk-text-large uk-margin-small-top'>$value</div>";
			if(strlen($extra)) $out .= $extra;
			if(strlen($note)) $out .= "<p class='uk-text-muted uk-margin-remove'>$note</p>";
			$out .= "</div>";
		}
		$out .= "</div></div>";
		return $out;
	}

	/**
	 * Inline settings SVG, matching the neighboring Ichiban admin nav.
	 *
	 * @param string $name Icon key
	 * @return string
	 */
	function resendIcon($name) {
		if($name === 'settings') {
			return '<svg width="32" height="32" aria-hidden="true" fill="none" stroke-width="1.5" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">'
				. '<path d="M10.343 3.94c.09-.542.56-.94 1.11-.94h1.093c.55 0 1.02.398 1.11.94l.149.894c.07.424.384.764.78.93.398.164.855.142 1.205-.108l.737-.527a1.125 1.125 0 0 1 1.45.12l.773.774c.39.389.44 1.002.12 1.45l-.527.737c-.25.35-.272.806-.107 1.204.165.397.505.71.93.78l.893.15c.543.09.94.559.94 1.109v1.094c0 .55-.397 1.02-.94 1.11l-.894.149c-.424.07-.764.383-.929.78-.165.398-.143.854.107 1.204l.527.738c.32.447.269 1.06-.12 1.45l-.774.773a1.125 1.125 0 0 1-1.449.12l-.738-.527c-.35-.25-.806-.272-1.203-.107-.398.165-.71.505-.781.929l-.149.894c-.09.542-.56.94-1.11.94h-1.094c-.55 0-1.019-.398-1.11-.94l-.148-.894c-.071-.424-.384-.764-.781-.93-.398-.164-.854-.142-1.204.108l-.738.527c-.447.32-1.06.269-1.45-.12l-.773-.774a1.125 1.125 0 0 1-.12-1.45l.527-.737c.25-.35.272-.806.108-1.204-.165-.397-.506-.71-.93-.78l-.894-.15c-.542-.09-.94-.56-.94-1.109v-1.094c0-.55.398-1.02.94-1.11l.894-.149c.424-.07.765-.383.93-.78.165-.398.143-.854-.108-1.204l-.526-.738a1.125 1.125 0 0 1 .12-1.45l.773-.773a1.125 1.125 0 0 1 1.45-.12l.737.527c.35.25.807.272 1.204.107.397-.165.71-.505.78-.929l.15-.894Z" stroke-linecap="round" stroke-linejoin="round"></path>'
				. '<path d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" stroke-linecap="round" stroke-linejoin="round"></path>'
				. '</svg>';
		}
		return '';
	}

	/**
	 * Render the screen intro: icon plus a short description of the section.
	 *
	 * @param string $screen Screen key
	 * @return string
	 */
	function resendIntro($screen) {
		$intros = [
			'dashboard' => 'Start here when checking the integration. The cards summarize domain readiness and recent sending, while the charts use the 100 most recent emails to show whether volume and delivery states look normal.',
			'emails' => 'Sent messages are listed with their latest delivery event, creation date and recipient summary. Open a row to inspect the payload, attachments, preview content, or cancel and reschedule messages that are still scheduled.',
			'received' => 'Inbound messages received on configured Resend domains appear here. Use this screen to confirm that inbound routing is working, review sender and subject details, and inspect any attached files from the email detail page.',
			'email' => 'This detail view collects the full message envelope, delivery state, schedule information, attachments and content preview in one place. HTML is always shown in a sandboxed preview so untrusted message content stays isolated from the admin UI.',
			'domains' => 'Domains control whether Resend can send mail for your site. Add the sending domain or subdomain here, choose the region before creation, then open the domain to copy DNS records and trigger verification.',
			'domain' => 'Use this page while setting up or auditing a sending domain. The DNS table shows the records that need to exist, and the tracking controls let you adjust open and click tracking for this domain.',
			'contacts' => 'Contacts make up the audience used by broadcasts, segments and topic subscriptions. Filter by segment when working with a subset, add individual contacts, or open a profile to manage names, segment membership and subscription state.',
			'segments' => 'Segments are named groups used as broadcast audiences. Create small operational groups for targeted sends, then open a segment to review its contacts from the Contacts screen. Deleting a segment does not delete contacts.',
			'properties' => 'Contact properties define reusable fields such as plan, city or account type. The property key doubles as a template variable name, and fallback values keep templates readable when a contact has no value set.',
			'topics' => 'Topics represent subscription preferences for different kinds of mail. Attach a topic id to sends so Resend can skip contacts who opted out of that topic while leaving other communication streams available.',
			'templates' => 'Templates are reusable email bodies stored in Resend. Create a draft here, edit the HTML and metadata, then publish the template before sending it from WireMail or the API client.',
			'broadcasts' => 'Broadcasts are one-off campaigns sent to a segment. Create a draft with subject, sender and HTML, then send immediately or schedule it for later once the content and audience are ready.',
			'webhooks' => 'Webhooks notify external endpoints about email, contact and domain activity. Choose only the events your endpoint handles, then store the one-time signing secret wherever requests will be verified.',
			'events' => 'This local log shows webhook deliveries received by the built-in /resend-webhook/ endpoint. Events are stored only after signature verification and deduplicated by Svix message id, so repeated retries do not create duplicate rows.',
			'contact' => 'A contact profile combines identity, subscription state, segment membership and topic preferences. Use it when you need to update names, move a person between audiences, or manually adjust opt-in and opt-out state.',
			'template' => 'Edit the draft content and metadata for one template. Saving creates or updates a draft version; publish after reviewing the HTML so the latest version can be used for sends.',
			'logs' => 'API logs help diagnose failed or unexpected requests. Review method, path, status and timestamp together when checking whether a problem is coming from the module, the API key permissions, or the Resend API response.',
			'test' => 'Use a test send after changing configuration, sender defaults, retry settings or template behavior. The Resend simulator addresses let you generate delivered, bounced or complained outcomes without affecting a real recipient.',
		];
		if(!isset($intros[$screen])) return '';
		return "<p class='uk-text-muted uk-margin-medium-bottom'>{$intros[$screen]}</p>";
	}

	/**
	 * Render an informational note (for forms and screens). Text is trusted
	 * static markup from views, not user input.
	 *
	 * @param string $html
	 * @return string
	 */
	function resendNote($html) {
		return "<p class='uk-text-meta uk-margin-small-top'>$html</p>";
	}

	/**
	 * Render an empty table row.
	 *
	 * @param int $cols
	 * @param string $message
	 * @return string
	 */
	function resendEmptyRow($cols, $message) {
		$sanitizer = wire()->sanitizer;
		return "<tr><td class='uk-text-center uk-text-muted' colspan='" . (int) $cols . "'>" . $sanitizer->entities($message) . "</td></tr>";
	}

	/**
	 * Render a standalone empty/not-found panel.
	 *
	 * @param string $message
	 * @return string
	 */
	function resendEmpty($message) {
		$sanitizer = wire()->sanitizer;
		return "<div class='uk-alert-warning' uk-alert><p>" . $sanitizer->entities($message) . "</p></div>";
	}

	/**
	 * JSON for display-only values in views.
	 *
	 * @param mixed $value
	 * @return string
	 */
	function resendJson($value) {
		$json = json_encode($value, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json === false ? '[unencodable]' : $json;
	}
}
