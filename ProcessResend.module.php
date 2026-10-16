<?php namespace ProcessWire;

/**
 * ProcessResend
 *
 * Admin UI for the Resend module: dashboard, sent and received emails,
 * domains, contacts, segments, topics, templates, broadcasts, webhooks,
 * logs and a test send screen.
 *
 * Copyright (c) 2026 Maxim Semenov <maxim@smnv.org>
 * License: MIT
 */

class ProcessResend extends Process implements Module {

	public static function getModuleInfo() {
		return [
			'title' => 'Resend Admin',
			'summary' => 'Admin interface for the Resend email platform.',
			'version' => 103,
			'author' => 'Maxim Semenov',
			'icon' => 'envelope-o',
			'requires' => ['Resend'],
			'permission' => 'resend-admin',
			'permissions' => [
				'resend-admin' => 'Access the Resend admin interface',
			],
			'page' => [
				'name' => 'resend',
				'parent' => 'setup',
				'title' => 'Resend',
			],
		];
	}

	/** @var Resend */
	protected $resend;

	public function init() {
		parent::init();
		$this->resend = $this->wire()->modules->get('Resend');
	}

	/*
	 * ------------------------------------------------------------------
	 * Rendering
	 * ------------------------------------------------------------------
	 */

	/**
	 * Module directory path as ProcessWire sees it.
	 *
	 * Not __DIR__: that resolves symlinks to the real filesystem path, which
	 * falls outside the allowed wireRenderFile() paths when the module
	 * directory is symlinked into site/modules.
	 *
	 * @return string
	 */
	protected function modulePath() {
		$path = $this->wire()->config->paths->get($this->className());
		return $path ? $path : __DIR__ . '/';
	}

	/**
	 * Render a view file from the views directory.
	 *
	 * @param string $view View name without prefix/extension, e.g. 'dashboard'
	 * @param array $vars Variables extracted into view scope
	 * @return string
	 */
	protected function render($view, array $vars = []) {
		$vars['process'] = $this;
		$vars['resend'] = $this->resend;
		$vars['adminUrl'] = $this->wire()->page->url;
		$vars['csrf'] = $this->wire()->session->CSRF;
		$file = $this->modulePath() . 'views/resend.' . $view . '.php';
		if(!is_file($file)) return "View not found: $view";
		return wireRenderFile($file, $vars);
	}

	/**
	 * Build a URL within this process page.
	 *
	 * @param string $segment
	 * @param array $params
	 * @return string
	 */
	public function url($segment = '', array $params = []) {
		$url = $this->wire()->page->url . ($segment ? trim($segment, '/') . '/' : '');
		if(count($params)) $url .= '?' . http_build_query($params);
		return $url;
	}

	/**
	 * Check API result; on error, show a notice and return false.
	 *
	 * @param array $result
	 * @return bool True if OK
	 */
	protected function ok($result) {
		if($this->resend->isError($result)) {
			$e = $result['error'];
			$this->error("Resend API: {$e['name']} — {$e['message']} ({$e['statusCode']})");
			// Key was downgraded on the Resend side: drop the stale cached
			// permission so restricted mode kicks in on the next request
			if($e['name'] === 'restricted_api_key') $this->resend->refreshApiKeyPermission();
			return false;
		}
		return true;
	}

	/**
	 * Extract list data from a Resend list response.
	 *
	 * @param array $result
	 * @return array
	 */
	public function listData($result) {
		if($this->resend->isError($result)) return [];
		if(isset($result['data']) && is_array($result['data'])) return $result['data'];
		return [];
	}

	/**
	 * Validate CSRF for POST actions.
	 */
	protected function requireValidToken() {
		if(!$this->wire()->session->CSRF->hasValidToken()) {
			throw new WireException('CSRF token validation failed');
		}
	}

	/**
	 * Whether the configured key is restricted to sending only.
	 *
	 * @return bool
	 */
	protected function restricted() {
		return $this->resend->apiKeyPermission() === 'sending_access';
	}

	/**
	 * Redirect to the dashboard when the key cannot use management endpoints.
	 */
	protected function requireFullAccess() {
		if(!$this->restricted()) return;
		$this->warning($this->_('This screen requires a full access API key. The configured key is restricted to sending emails.'));
		$this->wire()->session->redirect($this->url());
	}

	/**
	 * Common cursor pagination params from query string.
	 *
	 * @return array
	 */
	protected function paginationParams() {
		$input = $this->wire()->input;
		$params = ['limit' => 25];
		$after = $input->get->text('after');
		$before = $input->get->text('before');
		if(strlen($after)) $params['after'] = $after;
		if(strlen($before)) $params['before'] = $before;
		return $params;
	}

	/*
	 * ------------------------------------------------------------------
	 * Screens
	 * ------------------------------------------------------------------
	 */

	public function ___execute() {
		$this->headline($this->_('Resend Dashboard'));

		if($this->wire()->input->post->text('action') === 'recheck') {
			$this->requireValidToken();
			$permission = $this->resend->refreshApiKeyPermission();
			if($permission === 'full_access') {
				$this->message($this->_('API key permission re-checked: full access. All sections unlocked.'));
			} elseif($permission === 'sending_access') {
				$this->message($this->_('API key permission re-checked: the key is still sending-only.'));
			} else {
				$this->warning($this->_('Could not determine the key permission (invalid key or network error).'));
			}
			$this->wire()->session->redirect($this->url());
		}

		if(!strlen($this->resend->apiKey)) {
			$this->warning($this->_('API key is not configured. Configure the Resend module first.'));
			return $this->render('dashboard', [
				'configured' => false,
				'restricted' => false,
				'domains' => [],
				'emails' => [],
				'statsDays' => [],
				'statsSignalDays' => [],
				'statsStatuses' => [],
				'statsTotal' => 0,
			]);
		}
		if($this->restricted()) {
			// Sending-only key: management endpoints return 401, skip them
			return $this->render('dashboard', [
				'configured' => true,
				'restricted' => true,
				'domains' => [],
				'emails' => [],
				'statsDays' => [],
				'statsSignalDays' => [],
				'statsStatuses' => [],
				'statsTotal' => 0,
			]);
		}
		$domains = $this->resend->domains()->all();
		// One list call feeds both the recent emails table and the charts
		$emails = $this->resend->emails()->all(['limit' => 100]);
		$this->ok($domains);
		$emailData = $this->listData($emails);
		return $this->render('dashboard', [
			'configured' => true,
			'restricted' => false,
			'domains' => $this->listData($domains),
			'emails' => array_slice($emailData, 0, 10),
			'statsDays' => \Smnv\Resend\Stats::perDay($emailData),
			'statsSignalDays' => \Smnv\Resend\Stats::perDaySignals($emailData),
			'statsStatuses' => \Smnv\Resend\Stats::byStatus($emailData),
			'statsTotal' => count($emailData),
		]);
	}

	public function ___executeEmails() {
		$this->requireFullAccess();
		$this->headline($this->_('Sent Emails'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->emails()->all($this->paginationParams());
		$this->ok($result);
		return $this->render('emails', [
			'emails' => $this->listData($result),
			'hasMore' => isset($result['has_more']) ? (bool) $result['has_more'] : false,
			'received' => false,
		]);
	}

	public function ___executeReceived() {
		$this->requireFullAccess();
		$this->headline($this->_('Received Emails'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->emails()->receivedAll($this->paginationParams());
		$this->ok($result);
		return $this->render('emails', [
			'emails' => $this->listData($result),
			'hasMore' => isset($result['has_more']) ? (bool) $result['has_more'] : false,
			'received' => true,
		]);
	}

	public function ___executeEmail() {
		$this->requireFullAccess();
		$input = $this->wire()->input;
		$id = $input->get->text('id');
		$received = (bool) $input->get->int('received');
		if(!strlen($id)) $this->wire()->session->redirect($this->url('emails'));

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'cancel') {
				$result = $this->resend->emails()->cancel($id);
				if($this->ok($result)) $this->message($this->_('Scheduled email cancelled.'));
			} elseif($action === 'reschedule') {
				$when = $input->post->text('scheduled_at');
				$result = $this->resend->emails()->update($id, $when);
				if($this->ok($result)) $this->message($this->_('Email rescheduled.'));
			}
			$this->wire()->session->redirect($this->url('email', ['id' => $id, 'received' => (int) $received]));
		}

		$this->headline($this->_('Email'));
		$this->breadcrumb('../', $this->_('Resend'));
		$this->breadcrumb('../emails/', $this->_('Sent Emails'));
		$email = $received ? $this->resend->emails()->received($id) : $this->resend->emails()->get($id);
		$this->ok($email);
		$attachments = $received
			? $this->resend->emails()->receivedAttachments($id)
			: $this->resend->emails()->attachments($id);
		return $this->render('email', [
			'email' => $this->resend->isError($email) ? [] : $email,
			'attachments' => $this->listData($attachments),
			'received' => $received,
			'id' => $id,
		]);
	}

	public function ___executeDomains() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			$id = $input->post->text('id');
			if($action === 'create') {
				$name = $input->post->text('name');
				$data = [];
				$region = $input->post->text('region');
				if(strlen($region)) $data['region'] = $region;
				$result = $this->resend->domains()->create($name, $data);
				if($this->ok($result)) $this->message(sprintf($this->_('Domain %s created. Add the DNS records, then verify.'), $name));
			} elseif($action === 'verify' && strlen($id)) {
				$result = $this->resend->domains()->verify($id);
				if($this->ok($result)) $this->message($this->_('Verification triggered.'));
			} elseif($action === 'delete' && strlen($id)) {
				$result = $this->resend->domains()->delete($id);
				if($this->ok($result)) $this->message($this->_('Domain deleted.'));
			} elseif($action === 'tracking' && strlen($id)) {
				$result = $this->resend->domains()->update($id, [
					'open_tracking' => (bool) $input->post->int('open_tracking'),
					'click_tracking' => (bool) $input->post->int('click_tracking'),
				]);
				if($this->ok($result)) $this->message($this->_('Tracking settings updated.'));
			}
			$this->wire()->session->redirect($this->url('domains'));
		}

		$this->headline($this->_('Domains'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->domains()->all();
		$this->ok($result);
		return $this->render('domains', ['domains' => $this->listData($result)]);
	}

	public function ___executeDomain() {
		$this->requireFullAccess();
		$input = $this->wire()->input;
		$id = $input->get->text('id');
		if(!strlen($id)) $this->wire()->session->redirect($this->url('domains'));
		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'delete') {
				$result = $this->resend->domains()->delete($id);
				if($this->ok($result)) {
					$this->message($this->_('Domain deleted.'));
					$this->wire()->session->redirect($this->url('domains'));
				}
			}
			$this->wire()->session->redirect($this->url('domain', ['id' => $id]));
		}
		$this->headline($this->_('Domain'));
		$this->breadcrumb('../', $this->_('Resend'));
		$this->breadcrumb('../domains/', $this->_('Domains'));
		$domain = $this->resend->domains()->get($id);
		$this->ok($domain);
		return $this->render('domain', [
			'domain' => $this->resend->isError($domain) ? [] : $domain,
		]);
	}

	public function ___executeContacts() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'create') {
				$data = ['email' => $input->post->email('email')];
				$firstName = $input->post->text('first_name');
				$lastName = $input->post->text('last_name');
				if(strlen($firstName)) $data['first_name'] = $firstName;
				if(strlen($lastName)) $data['last_name'] = $lastName;
				$result = $this->resend->contacts()->create($data);
				if($this->ok($result)) {
					$segmentId = $input->post->text('segment_id');
					if(strlen($segmentId) && isset($result['id'])) {
						$this->resend->contacts()->addToSegment($result['id'], $segmentId);
					}
					$this->message($this->_('Contact created.'));
				}
			} elseif($action === 'delete') {
				$result = $this->resend->contacts()->delete($input->post->text('id'));
				if($this->ok($result)) $this->message($this->_('Contact deleted.'));
			} elseif($action === 'unsubscribe') {
				$result = $this->resend->contacts()->update($input->post->text('id'), ['unsubscribed' => true]);
				if($this->ok($result)) $this->message($this->_('Contact unsubscribed.'));
			} elseif($action === 'resubscribe') {
				$result = $this->resend->contacts()->update($input->post->text('id'), ['unsubscribed' => false]);
				if($this->ok($result)) $this->message($this->_('Contact resubscribed.'));
			}
			$this->wire()->session->redirect($this->url('contacts', array_filter([
				'segment' => $input->get->text('segment'),
			])));
		}

		$this->headline($this->_('Contacts'));
		$this->breadcrumb('../', $this->_('Resend'));
		$segmentId = $input->get->text('segment');
		$params = $this->paginationParams();
		$result = strlen($segmentId)
			? $this->resend->segments()->contacts($segmentId, $params)
			: $this->resend->contacts()->all($params);
		$this->ok($result);
		$segments = $this->resend->segments()->all();
		return $this->render('contacts', [
			'contacts' => $this->listData($result),
			'hasMore' => isset($result['has_more']) ? (bool) $result['has_more'] : false,
			'segments' => $this->listData($segments),
			'segmentId' => $segmentId,
		]);
	}

	public function ___executeSegments() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'create') {
				$result = $this->resend->segments()->create($input->post->text('name'));
				if($this->ok($result)) $this->message($this->_('Segment created.'));
			} elseif($action === 'delete') {
				$result = $this->resend->segments()->delete($input->post->text('id'));
				if($this->ok($result)) $this->message($this->_('Segment deleted.'));
			}
			$this->wire()->session->redirect($this->url('segments'));
		}

		$this->headline($this->_('Segments'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->segments()->all();
		$this->ok($result);
		return $this->render('segments', ['segments' => $this->listData($result)]);
	}

	public function ___executeProperties() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'create') {
				$type = $input->post->text('type');
				$data = [
					'key' => $input->post->text('key'),
					'type' => in_array($type, $this->propertyTypes()) ? $type : 'string',
				];
				$fallback = $input->post->text('fallback_value');
				if(strlen($fallback)) $data['fallback_value'] = $fallback;
				$result = $this->resend->contactProperties()->create($data);
				if($this->ok($result)) $this->message($this->_('Contact property created.'));
			} elseif($action === 'delete') {
				$result = $this->resend->contactProperties()->delete($input->post->text('id'));
				if($this->ok($result)) $this->message($this->_('Contact property deleted.'));
			}
			$this->wire()->session->redirect($this->url('properties'));
		}

		$this->headline($this->_('Contact Properties'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->contactProperties()->all();
		$this->ok($result);
		return $this->render('properties', [
			'properties' => $this->listData($result),
			'types' => $this->propertyTypes(),
		]);
	}

	public function ___executeTopics() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'create') {
				$data = [
					'name' => $input->post->text('name'),
					'default_subscription' => $input->post->text('default_subscription') === 'opt-out' ? 'opt-out' : 'opt-in',
				];
				$description = $input->post->text('description');
				if(strlen($description)) $data['description'] = $description;
				$result = $this->resend->topics()->create($data);
				if($this->ok($result)) $this->message($this->_('Topic created.'));
			} elseif($action === 'delete') {
				$result = $this->resend->topics()->delete($input->post->text('id'));
				if($this->ok($result)) $this->message($this->_('Topic deleted.'));
			}
			$this->wire()->session->redirect($this->url('topics'));
		}

		$this->headline($this->_('Topics'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->topics()->all();
		$this->ok($result);
		return $this->render('topics', ['topics' => $this->listData($result)]);
	}

	public function ___executeTemplates() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			$id = $input->post->text('id');
			if($action === 'create') {
				// Raw value: the textarea() sanitizer strips tags, destroying the HTML body
				$html = $input->post('html');
				$data = [
					'name' => $input->post->text('name'),
					'html' => is_string($html) ? $html : '',
				];
				foreach(['alias', 'subject', 'from'] as $key) {
					$value = $input->post->text($key);
					if(strlen($value)) $data[$key] = $value;
				}
				$result = $this->resend->templates()->create($data);
				if($this->ok($result)) $this->message($this->_('Template created as a draft. Publish it to make it sendable.'));
			} elseif($action === 'publish' && strlen($id)) {
				$result = $this->resend->templates()->publish($id);
				if($this->ok($result)) $this->message($this->_('Template published.'));
			} elseif($action === 'duplicate' && strlen($id)) {
				$result = $this->resend->templates()->duplicate($id);
				if($this->ok($result)) $this->message($this->_('Template duplicated.'));
			} elseif($action === 'delete' && strlen($id)) {
				$result = $this->resend->templates()->delete($id);
				if($this->ok($result)) $this->message($this->_('Template deleted.'));
			}
			$this->wire()->session->redirect($this->url('templates'));
		}

		$this->headline($this->_('Templates'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->templates()->all($this->paginationParams());
		$this->ok($result);
		return $this->render('templates', [
			'templates' => $this->listData($result),
			'hasMore' => isset($result['has_more']) ? (bool) $result['has_more'] : false,
		]);
	}

	public function ___executeBroadcasts() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			$id = $input->post->text('id');
			if($action === 'create') {
				// Raw value: the textarea() sanitizer strips tags, destroying the HTML body
				$html = $input->post('html');
				$data = [
					'name' => $input->post->text('name'),
					'segment_id' => $input->post->text('segment_id'),
					'from' => $input->post->text('from'),
					'subject' => $input->post->text('subject'),
					'html' => is_string($html) ? $html : '',
				];
				$result = $this->resend->broadcasts()->create($data);
				if($this->ok($result)) {
					$this->message($this->_('Broadcast created as draft.'));
					if(isset($result['id']) && strlen((string) $result['id'])) {
						$this->wire()->session->redirect($this->url('broadcast', ['id' => (string) $result['id']]));
					}
				}
			} elseif($action === 'send' && strlen($id)) {
				$scheduledAt = $input->post->text('scheduled_at');
				$result = $this->resend->broadcasts()->send($id, $scheduledAt);
				if($this->ok($result)) {
					$this->message(strlen($scheduledAt) ? $this->_('Broadcast scheduled.') : $this->_('Broadcast is being sent.'));
				}
			} elseif($action === 'delete' && strlen($id)) {
				$result = $this->resend->broadcasts()->delete($id);
				if($this->ok($result)) $this->message($this->_('Broadcast deleted.'));
			}
			$this->wire()->session->redirect($this->url('broadcasts'));
		}

		$this->headline($this->_('Broadcasts'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->broadcasts()->all($this->paginationParams());
		$this->ok($result);
		$segments = $this->resend->segments()->all();
		return $this->render('broadcasts', [
			'broadcasts' => $this->listData($result),
			'segments' => $this->listData($segments),
			'hasMore' => isset($result['has_more']) ? (bool) $result['has_more'] : false,
		]);
	}

	public function ___executeBroadcast() {
		$this->requireFullAccess();
		$input = $this->wire()->input;
		$id = $input->get->text('id');
		if(!strlen($id)) $this->wire()->session->redirect($this->url('broadcasts'));

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'update') {
				// Raw value: the textarea() sanitizer strips tags, destroying the HTML body
				$html = $input->post('html');
				$data = [
					'name' => $input->post->text('name'),
					'segment_id' => $input->post->text('segment_id'),
					'from' => $input->post->text('from'),
					'subject' => $input->post->text('subject'),
					'html' => is_string($html) ? $html : '',
				];
				$result = $this->resend->broadcasts()->update($id, $data);
				if($this->ok($result)) $this->message($this->_('Broadcast draft updated.'));
			} elseif($action === 'send') {
				$scheduledAt = $input->post->text('scheduled_at');
				$result = $this->resend->broadcasts()->send($id, $scheduledAt);
				if($this->ok($result)) {
					$this->message(strlen($scheduledAt) ? $this->_('Broadcast scheduled.') : $this->_('Broadcast is being sent.'));
				}
			} elseif($action === 'delete') {
				$result = $this->resend->broadcasts()->delete($id);
				if($this->ok($result)) {
					$this->message($this->_('Broadcast deleted.'));
					$this->wire()->session->redirect($this->url('broadcasts'));
				}
			}
			$this->wire()->session->redirect($this->url('broadcast', ['id' => $id]));
		}

		$this->headline($this->_('Broadcast'));
		$this->breadcrumb('../', $this->_('Resend'));
		$this->breadcrumb('../broadcasts/', $this->_('Broadcasts'));
		$broadcast = $this->resend->broadcasts()->get($id);
		$this->ok($broadcast);
		return $this->render('broadcast', [
			'broadcast' => $this->resend->isError($broadcast) ? [] : $broadcast,
			'segments' => $this->listData($this->resend->segments()->all()),
			'id' => $id,
		]);
	}

	public function ___executeWebhooks() {
		$this->requireFullAccess();
		$input = $this->wire()->input;

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			$id = $input->post->text('id');
			if($action === 'create') {
				$events = $input->post->array('events');
				$events = array_values(array_intersect($events, $this->webhookEventTypes()));
				$result = $this->resend->webhooks()->create($input->post->url('endpoint_url'), $events);
				if($this->ok($result)) $this->message($this->_('Webhook created.'));
			} elseif($action === 'delete' && strlen($id)) {
				$result = $this->resend->webhooks()->delete($id);
				if($this->ok($result)) $this->message($this->_('Webhook deleted.'));
			} elseif($action === 'toggle' && strlen($id)) {
				$result = $this->resend->webhooks()->update($id, [
					'status' => $input->post->text('status') === 'disabled' ? 'enabled' : 'disabled',
				]);
				if($this->ok($result)) $this->message($this->_('Webhook status updated.'));
			}
			$this->wire()->session->redirect($this->url('webhooks'));
		}

		$this->headline($this->_('Webhooks'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->webhooks()->all();
		$this->ok($result);
		return $this->render('webhooks', [
			'webhooks' => $this->listData($result),
			'eventTypes' => $this->webhookEventTypes(),
		]);
	}

	public function ___executeEvents() {
		// Local data from ResendWebhooks; available regardless of key permission
		$this->headline($this->_('Webhook Events'));
		$this->breadcrumb('../', $this->_('Resend'));
		$modules = $this->wire()->modules;
		$events = [];
		$receiverInstalled = $modules->isInstalled('ResendWebhooks');
		$beforeId = $this->wire()->input->get->int('before');
		if($receiverInstalled) {
			/** @var ResendWebhooks $webhooks */
			$webhooks = $modules->get('ResendWebhooks');
			$events = $webhooks->latestEvents(25, $beforeId);
		}
		return $this->render('events', [
			'events' => $events,
			'receiverInstalled' => $receiverInstalled,
			'secretConfigured' => strlen((string) $this->resend->webhookSigningSecret) > 0,
			'hasMore' => count($events) === 25,
		]);
	}

	public function ___executeContact() {
		$this->requireFullAccess();
		$input = $this->wire()->input;
		$id = $input->get->text('id');
		if(!strlen($id)) $this->wire()->session->redirect($this->url('contacts'));

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'update') {
				$result = $this->resend->contacts()->update($id, [
					'first_name' => $input->post->text('first_name'),
					'last_name' => $input->post->text('last_name'),
				]);
				if($this->ok($result)) $this->message($this->_('Contact updated.'));
			} elseif($action === 'addsegment') {
				$result = $this->resend->contacts()->addToSegment($id, $input->post->text('segment_id'));
				if($this->ok($result)) $this->message($this->_('Added to segment.'));
			} elseif($action === 'removesegment') {
				$result = $this->resend->contacts()->removeFromSegment($id, $input->post->text('segment_id'));
				if($this->ok($result)) $this->message($this->_('Removed from segment.'));
			} elseif($action === 'topic') {
				$subscription = $input->post->text('subscription') === 'opt-out' ? 'opt-out' : 'opt-in';
				$result = $this->resend->contacts()->updateTopics($id, [[
					'id' => $input->post->text('topic_id'),
					'subscription' => $subscription,
				]]);
				if($this->ok($result)) $this->message($this->_('Topic subscription updated.'));
			} elseif($action === 'unsubscribe' || $action === 'resubscribe') {
				$result = $this->resend->contacts()->update($id, ['unsubscribed' => $action === 'unsubscribe']);
				if($this->ok($result)) $this->message($this->_('Subscription updated.'));
			}
			$this->wire()->session->redirect($this->url('contact', ['id' => $id]));
		}

		$this->headline($this->_('Contact'));
		$this->breadcrumb('../', $this->_('Resend'));
		$this->breadcrumb('../contacts/', $this->_('Contacts'));
		$contact = $this->resend->contacts()->get($id);
		$this->ok($contact);
		return $this->render('contact', [
			'contact' => $this->resend->isError($contact) ? [] : $contact,
			'memberSegments' => $this->listData($this->resend->contacts()->segments($id)),
			'topics' => $this->listData($this->resend->contacts()->topics($id)),
			'segments' => $this->listData($this->resend->segments()->all()),
			'id' => $id,
		]);
	}

	public function ___executeTemplate() {
		$this->requireFullAccess();
		$input = $this->wire()->input;
		$id = $input->get->text('id');
		if(!strlen($id)) $this->wire()->session->redirect($this->url('templates'));

		if($input->post->text('action')) {
			$this->requireValidToken();
			$action = $input->post->text('action');
			if($action === 'update') {
				// Raw value: the textarea() sanitizer strips tags, destroying the HTML body
				$html = $input->post('html');
				$data = [
					'name' => $input->post->text('name'),
					'subject' => $input->post->text('subject'),
					'html' => is_string($html) ? $html : '',
				];
				$alias = $input->post->text('alias');
				if(strlen($alias)) $data['alias'] = $alias;
				$result = $this->resend->templates()->update($id, $data);
				if($this->ok($result)) $this->message($this->_('Template updated. Publish to make the changes sendable.'));
			} elseif($action === 'publish') {
				$result = $this->resend->templates()->publish($id);
				if($this->ok($result)) $this->message($this->_('Template published.'));
			}
			$this->wire()->session->redirect($this->url('template', ['id' => $id]));
		}

		$this->headline($this->_('Template'));
		$this->breadcrumb('../', $this->_('Resend'));
		$this->breadcrumb('../templates/', $this->_('Templates'));
		$template = $this->resend->templates()->get($id);
		$this->ok($template);
		return $this->render('template', [
			'template' => $this->resend->isError($template) ? [] : $template,
			'id' => $id,
		]);
	}

	public function ___executeLogs() {
		$this->requireFullAccess();
		$this->headline($this->_('API Logs'));
		$this->breadcrumb('../', $this->_('Resend'));
		$result = $this->resend->logs()->all($this->paginationParams());
		$this->ok($result);
		return $this->render('logs', [
			'logs' => $this->listData($result),
			'hasMore' => isset($result['has_more']) ? (bool) $result['has_more'] : false,
		]);
	}

	public function ___executeTest() {
		$input = $this->wire()->input;
		$sent = false;
		$fromConfigured = strlen((string) $this->resend->fromEmail) > 0;

		if($input->post->text('action') === 'send') {
			$this->requireValidToken();
			if(!$fromConfigured) {
				$this->warning($this->_('Set a Default From Email in Resend settings before sending a test email.'));
				$this->wire()->session->redirect($this->url('test'));
			}
			$to = $input->post->email('to');
			// Raw values: text() truncates at 255 chars, textarea() strips tags from the HTML body
			$subject = $this->wire()->sanitizer->text($input->post('subject'), ['maxLength' => 998, 'stripTags' => false]);
			$body = $input->post('body');
			if(!is_string($body)) $body = '';
			$scheduledAt = $input->post->text('scheduled_at');
			if(strlen($to) && strlen($subject)) {
				/** @var Resend $m */
				$m = $this->wire()->modules->get('Resend');
				$m->to($to)->subject($subject)->bodyHTML($body ?: '<p>Test email from ProcessWire via Resend.</p>');
				if(strlen($scheduledAt)) $m->scheduledAt($scheduledAt);
				$m->addTag('source', 'processwire-test');
				$count = $m->send();
				if($count) {
					$this->message(sprintf($this->_('Test email sent. Resend id: %s'), $m->getLastEmailId()));
					$sent = true;
				} else {
					$this->error($this->_('Send failed. Check the resend log.'));
				}
			} else {
				$this->error($this->_('Recipient and subject are required.'));
			}
		}

		$this->headline($this->_('Test Send'));
		$this->breadcrumb('../', $this->_('Resend'));
		return $this->render('test', [
			'sent' => $sent,
			'restricted' => $this->restricted(),
			'fromConfigured' => $fromConfigured,
			'fromEmail' => $this->resend->fromEmail,
			'fromName' => $this->resend->fromName,
		]);
	}

	/**
	 * Supported webhook event types.
	 *
	 * @return array
	 */
	public function webhookEventTypes() {
		return \Smnv\Resend\Webhooks::EVENT_TYPES;
	}

	/**
	 * Supported contact property types.
	 *
	 * @return array
	 */
	public function propertyTypes() {
		return \Smnv\Resend\ContactProperties::TYPES;
	}

	/**
	 * Inspect public DNS records for Resend domain setup guidance.
	 *
	 * @param string $domainName
	 * @param array $records
	 * @return array
	 */
	public function domainDnsAudit($domainName, array $records) {
		if(!function_exists('dns_get_record')) return [];
		$out = [];
		foreach($records as $record) {
			$type = strtoupper((string) ($record['type'] ?? ''));
			$name = (string) ($record['name'] ?? '');
			$value = (string) ($record['value'] ?? '');
			$host = $this->domainDnsHost($domainName, $name);
			$item = [
				'type' => $type,
				'name' => $name,
				'host' => $host,
				'value' => $value,
				'priority' => isset($record['priority']) ? (string) $record['priority'] : '',
				'status' => 'missing',
				'message' => 'Add this record at your DNS provider.',
				'current' => [],
				'suggested' => '',
			];

			if($type === 'TXT') {
				$item = $this->auditTxtRecord($item);
			} elseif($type === 'MX') {
				$item = $this->auditMxRecord($item);
			} elseif($type === 'CNAME') {
				$item = $this->auditCnameRecord($item);
			}
			$out[] = $item;
		}
		return $out;
	}

	/**
	 * @param string $domainName
	 * @param string $recordName
	 * @return string
	 */
	protected function domainDnsHost($domainName, $recordName) {
		$domainName = trim((string) $domainName, ". \t\r\n");
		$recordName = trim((string) $recordName, ". \t\r\n");
		if(!strlen($recordName) || $recordName === '@') return $domainName;
		if(strtolower($recordName) === strtolower($domainName)) return $domainName;
		if(substr(strtolower($recordName), -strlen('.' . strtolower($domainName))) === '.' . strtolower($domainName)) return $recordName;
		return $recordName . '.' . $domainName;
	}

	/**
	 * @param array $item
	 * @return array
	 */
	protected function auditTxtRecord(array $item) {
		$records = @dns_get_record($item['host'], DNS_TXT);
		$records = is_array($records) ? $records : [];
		$txts = [];
		foreach($records as $record) {
			if(isset($record['txt'])) $txts[] = (string) $record['txt'];
		}
		$item['current'] = $txts;
		$required = trim($item['value']);
		if(stripos($required, 'v=spf1') === 0) {
			$existingSpf = '';
			foreach($txts as $txt) {
				if(stripos(trim($txt), 'v=spf1') === 0) {
					$existingSpf = trim($txt);
					break;
				}
			}
			if(!strlen($existingSpf)) {
				$item['message'] = 'Add this SPF TXT record at this host.';
			} elseif($this->dnsValueEquals($existingSpf, $required)) {
				$item['status'] = 'found';
				$item['message'] = 'SPF is already ready for Resend.';
			} else {
				$item['status'] = 'merge';
				$item['message'] = 'Existing SPF found. Replace it with one combined SPF record so your current mail setup and Resend both work.';
				$item['suggested'] = $this->mergeSpf($existingSpf, $required);
			}
			return $item;
		}
		foreach($txts as $txt) {
			if($this->dnsValueEquals($txt, $required)) {
				$item['status'] = 'found';
				$item['message'] = 'This TXT record is already present.';
				return $item;
			}
		}
		$item['message'] = count($txts) ? 'TXT records exist here, but the Resend value is not present yet.' : 'Add this TXT record.';
		return $item;
	}

	/**
	 * @param array $item
	 * @return array
	 */
	protected function auditMxRecord(array $item) {
		$records = @dns_get_record($item['host'], DNS_MX);
		$records = is_array($records) ? $records : [];
		$item['current'] = $records;
		$requiredTarget = $this->normalizeDnsValue($item['value']);
		$requiredPriority = strlen($item['priority']) ? (int) $item['priority'] : null;
		foreach($records as $record) {
			$target = $this->normalizeDnsValue((string) ($record['target'] ?? ''));
			$priority = isset($record['pri']) ? (int) $record['pri'] : null;
			if($target === $requiredTarget && ($requiredPriority === null || $priority === $requiredPriority)) {
				$item['status'] = 'found';
				$item['message'] = 'This MX record is already present.';
				return $item;
			}
		}
		if(count($records)) {
			$item['status'] = 'coexist';
			$item['message'] = 'Existing MX records found. Keep them and add the Resend MX record too; the priority keeps routing predictable.';
		} else {
			$item['message'] = 'Add this MX record.';
		}
		return $item;
	}

	/**
	 * @param array $item
	 * @return array
	 */
	protected function auditCnameRecord(array $item) {
		$records = @dns_get_record($item['host'], DNS_CNAME);
		$records = is_array($records) ? $records : [];
		$item['current'] = $records;
		$required = $this->normalizeDnsValue($item['value']);
		foreach($records as $record) {
			$target = $this->normalizeDnsValue((string) ($record['target'] ?? ''));
			if($target === $required) {
				$item['status'] = 'found';
				$item['message'] = 'This CNAME record is already present.';
				return $item;
			}
		}
		$item['message'] = count($records) ? 'A CNAME exists here, but it points somewhere else. Update it to the Resend value.' : 'Add this CNAME record.';
		return $item;
	}

	/**
	 * @param string $a
	 * @param string $b
	 * @return bool
	 */
	protected function dnsValueEquals($a, $b) {
		return $this->normalizeDnsValue($a) === $this->normalizeDnsValue($b);
	}

	/**
	 * @param string $value
	 * @return string
	 */
	protected function normalizeDnsValue($value) {
		return strtolower(trim((string) $value, ". \t\r\n\""));
	}

	/**
	 * Merge two SPF records into one record.
	 *
	 * @param string $existing
	 * @param string $required
	 * @return string
	 */
	protected function mergeSpf($existing, $required) {
		$existingParts = preg_split('/\s+/', trim($existing));
		$requiredParts = preg_split('/\s+/', trim($required));
		$all = ['v=spf1'];
		$qualifier = '~all';
		foreach(array_merge($existingParts, $requiredParts) as $part) {
			$part = trim((string) $part);
			if(!strlen($part) || strtolower($part) === 'v=spf1') continue;
			if(preg_match('/^[~\\-\\+?]all$/', $part)) {
				if(in_array($part[0], ['-', '~', '?', '+'], true)) $qualifier = $part;
				continue;
			}
			if(!in_array($part, $all, true)) $all[] = $part;
		}
		$all[] = $qualifier;
		return implode(' ', $all);
	}

	/**
	 * Format an ISO timestamp for display.
	 *
	 * @param string $iso
	 * @return string
	 */
	public function formatDate($iso) {
		if(!strlen((string) $iso)) return '';
		$ts = strtotime($iso);
		if($ts === false) return $this->wire()->sanitizer->entities($iso);
		return date('Y-m-d H:i', $ts);
	}

	/**
	 * Severity type (success|warning|danger|neutral) for a status string.
	 *
	 * @param string $status
	 * @return string
	 */
	public function statusType($status) {
		$map = [
			'delivered' => 'success', 'sent' => 'success', 'verified' => 'success',
			'active' => 'success', 'enabled' => 'success', 'published' => 'success',
			'opened' => 'success', 'clicked' => 'success',
			'queued' => 'neutral', 'scheduled' => 'neutral', 'draft' => 'neutral',
			'pending' => 'warning', 'not_started' => 'warning', 'delivery_delayed' => 'warning',
			'bounced' => 'danger', 'failed' => 'danger', 'complained' => 'danger',
			'canceled' => 'danger', 'cancelled' => 'danger', 'disabled' => 'danger',
			'temporary_failure' => 'danger', 'suppressed' => 'danger',
		];
		$status = (string) $status;
		return isset($map[$status]) ? $map[$status] : 'neutral';
	}

	/**
	 * Render a status badge.
	 *
	 * @param string $status
	 * @return string
	 */
	public function badge($status) {
		$type = $this->statusType($status);
		$label = $this->wire()->sanitizer->entities($this->_((string) $status));
		$class = 'uk-label';
		if($type === 'success') $class .= ' uk-label-success';
		elseif($type === 'warning') $class .= ' uk-label-warning';
		elseif($type === 'danger') $class .= ' uk-label-danger';
		return "<span class='$class'>$label</span>";
	}
}
