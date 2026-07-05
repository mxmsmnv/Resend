<?php namespace Smnv\Resend;

/**
 * Cli
 *
 * Command-line interface for the Resend module. Instantiated by bin/resend
 * after bootstrapping ProcessWire. Contains no HTTP logic of its own; every
 * command delegates to the resource classes via the module.
 *
 * Usage: php bin/resend <command> [arguments] [--options]
 * Run "php bin/resend help" for the command list.
 */
class Cli {

	/** @var object Resend module instance (duck-typed to allow testing) */
	protected $resend;

	/** @var resource */
	protected $stdout;

	/** @var resource */
	protected $stderr;

	/** @var bool Output raw JSON instead of tables */
	protected $json = false;

	/**
	 * @param object $resend Resend module
	 * @param resource|null $stdout
	 * @param resource|null $stderr
	 */
	public function __construct($resend, $stdout = null, $stderr = null) {
		$this->resend = $resend;
		$this->stdout = $stdout ?: STDOUT;
		$this->stderr = $stderr ?: STDERR;
	}

	/**
	 * Run the CLI.
	 *
	 * @param array $argv
	 * @return int Exit code
	 */
	public function run(array $argv) {
		array_shift($argv); // script name
		list($command, $args, $opts) = $this->parse($argv);
		$this->json = isset($opts['json']);

		if(!strlen($command) || $command === 'help' || isset($opts['help'])) {
			$this->help();
			return 0;
		}

		$method = 'cmd' . str_replace(' ', '', ucwords(str_replace([':', '-'], ' ', $command)));
		if(!method_exists($this, $method)) {
			$this->err("Unknown command: $command (run \"help\")");
			return 1;
		}

		try {
			return (int) $this->$method($args, $opts);
		} catch(\Exception $e) {
			$this->err('Error: ' . $e->getMessage());
			return 1;
		}
	}

	/**
	 * Parse argv into command, positional args and --options.
	 *
	 * @param array $argv
	 * @return array [command, args, opts]
	 */
	protected function parse(array $argv) {
		$command = '';
		$args = [];
		$opts = [];
		foreach($argv as $i => $arg) {
			if(strpos($arg, '--') === 0) {
				$pair = explode('=', substr($arg, 2), 2);
				$opts[$pair[0]] = isset($pair[1]) ? $pair[1] : true;
			} elseif($i === 0) {
				$command = $arg;
			} else {
				$args[] = $arg;
			}
		}
		return [$command, $args, $opts];
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: general
	 * ------------------------------------------------------------------
	 */

	protected function help() {
		$this->out(trim("
Resend CLI — ProcessWire module for the Resend email platform

Usage: php bin/resend <command> [arguments] [--options]

General:
  help                                   Show this help
  status                                 Config summary and domain status
  send --to= --subject= [--html=|--text=|--html-file=]
       [--from=] [--schedule=] [--tag=name:value] [--idempotency=]

Emails:
  emails:list [--limit=25] [--after=]    List sent emails
  emails:get <id>                        Show one email
  emails:cancel <id>                     Cancel a scheduled email
  emails:reschedule <id> <when>          Reschedule a scheduled email
  received:list [--limit=25]             List received emails

Domains:
  domains:list                           List domains
  domains:get <id>                       Domain with DNS records
  domains:create <name> [--region=]      Add a domain
  domains:verify <id>                    Trigger verification
  domains:delete <id>                    Delete a domain

Contacts and segments:
  contacts:list [--segment=] [--limit=]  List contacts
  contacts:get <idOrEmail>               Show a contact
  contacts:create <email> [--first=] [--last=] [--segment=]
  contacts:import <file.csv> [--segment=] [--dry-run]
                                         CSV columns: email,first_name,last_name
  contacts:unsubscribe <idOrEmail>
  contacts:resubscribe <idOrEmail>
  contacts:delete <idOrEmail>
  segments:list
  segments:create <name>
  segments:delete <id>

Contact properties:
  properties:list
  properties:create <key> [--type=string|number|boolean|date] [--fallback=]
  properties:delete <id>

Topics:
  topics:list
  topics:create <name> [--desc=] [--default=opt-in|opt-out]
  topics:delete <id>

Templates:
  templates:list [--limit=]
  templates:publish <idOrAlias>
  templates:duplicate <idOrAlias>
  templates:delete <idOrAlias>

Broadcasts:
  broadcasts:list [--limit=]
  broadcasts:create --name= --segment= --from= --subject= --html-file=
  broadcasts:send <id> [--schedule=]
  broadcasts:delete <id>

Webhooks:
  webhooks:list
  webhooks:create <url> --events=email.delivered,email.bounced
  webhooks:delete <id>

Logs:
  logs:list [--limit=]

Module config:
  config:list                            Show module settings (secrets masked)
  config:set <name> <value>              Update a setting (secrets stored encrypted)

Options:
  --json                                 Output raw JSON
"));
	}

	protected function cmdStatus(array $args, array $opts) {
		$configured = strlen((string) $this->resend->apiKey) > 0;
		if(!$configured) {
			$this->err('API key is not configured.');
			return 1;
		}
		if($this->resend->apiKeyPermission() === 'sending_access') {
			$this->out('API key: configured (sending access only)');
			$this->out('Default from: ' . ($this->resend->fromEmail ?: '(not set)'));
			$this->out('Test mode: ' . ((int) $this->resend->disableSending ? 'ON (sending disabled)' : 'off'));
			$this->out('Management commands are unavailable with this key; "send" works. Use a full access key to unlock them.');
			return 0;
		}
		$result = $this->resend->domains()->all();
		if($this->failed($result)) return 1;
		$domains = isset($result['data']) ? $result['data'] : [];
		if($this->json) return $this->outJson($domains);
		$this->out('API key: configured');
		$this->out('Default from: ' . ($this->resend->fromEmail ?: '(not set)'));
		$this->out('Test mode: ' . ((int) $this->resend->disableSending ? 'ON (sending disabled)' : 'off'));
		$this->table($domains, ['name' => 'Domain', 'status' => 'Status', 'region' => 'Region']);
		return 0;
	}

	protected function cmdSend(array $args, array $opts) {
		$to = isset($opts['to']) ? $opts['to'] : '';
		$subject = isset($opts['subject']) ? $opts['subject'] : '';
		if(!strlen($to) || !strlen($subject)) {
			$this->err('Required: --to= and --subject=');
			return 1;
		}
		$m = $this->resend;
		foreach(explode(',', $to) as $address) $m->to(trim($address));
		$m->subject($subject);
		if(isset($opts['from'])) $m->from($opts['from']);
		if(isset($opts['html-file'])) {
			if(!is_file($opts['html-file'])) {
				$this->err('File not found: ' . $opts['html-file']);
				return 1;
			}
			$m->bodyHTML(file_get_contents($opts['html-file']));
		} elseif(isset($opts['html'])) {
			$m->bodyHTML($opts['html']);
		} elseif(isset($opts['text'])) {
			$m->body($opts['text']);
		} else {
			$this->err('Required: --html=, --html-file= or --text=');
			return 1;
		}
		if(isset($opts['schedule'])) $m->scheduledAt($opts['schedule']);
		if(isset($opts['idempotency'])) $m->idempotencyKey($opts['idempotency']);
		if(isset($opts['tag'])) {
			$pair = explode(':', $opts['tag'], 2);
			$m->addTag($pair[0], isset($pair[1]) ? $pair[1] : '1');
		}
		$count = $m->send();
		if(!$count) {
			$this->err('Send failed. Check the resend log.');
			return 1;
		}
		$this->out('Sent to ' . $count . ' recipient(s). Id: ' . $m->getLastEmailId());
		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: emails
	 * ------------------------------------------------------------------
	 */

	protected function cmdEmailsList(array $args, array $opts) {
		return $this->listCommand($this->resend->emails()->all($this->pagination($opts)), [
			'id' => 'ID', 'subject' => 'Subject', 'last_event' => 'Status', 'created_at' => 'Date',
		]);
	}

	protected function cmdEmailsGet(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->emails()->get($args[0]);
		if($this->failed($result)) return 1;
		return $this->outJson($result);
	}

	protected function cmdEmailsCancel(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->emails()->cancel($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Cancelled: ' . $args[0]);
		return 0;
	}

	protected function cmdEmailsReschedule(array $args, array $opts) {
		if(count($args) < 2) {
			$this->err('Usage: emails:reschedule <id> <when>');
			return 1;
		}
		$result = $this->resend->emails()->update($args[0], $args[1]);
		if($this->failed($result)) return 1;
		$this->out('Rescheduled: ' . $args[0] . ' to ' . $args[1]);
		return 0;
	}

	protected function cmdReceivedList(array $args, array $opts) {
		return $this->listCommand($this->resend->emails()->receivedAll($this->pagination($opts)), [
			'id' => 'ID', 'from' => 'From', 'subject' => 'Subject', 'created_at' => 'Date',
		]);
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: domains
	 * ------------------------------------------------------------------
	 */

	protected function cmdDomainsList(array $args, array $opts) {
		return $this->listCommand($this->resend->domains()->all(), [
			'id' => 'ID', 'name' => 'Domain', 'status' => 'Status', 'region' => 'Region',
		]);
	}

	protected function cmdDomainsGet(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->domains()->get($args[0]);
		if($this->failed($result)) return 1;
		if($this->json) return $this->outJson($result);
		$this->out('Domain: ' . (isset($result['name']) ? $result['name'] : '') . ' (' . (isset($result['status']) ? $result['status'] : '') . ')');
		$records = isset($result['records']) ? $result['records'] : [];
		$this->table($records, ['type' => 'Type', 'name' => 'Name', 'value' => 'Value', 'status' => 'Status']);
		return 0;
	}

	protected function cmdDomainsCreate(array $args, array $opts) {
		if(!$this->requireArg($args, 'name')) return 1;
		$options = [];
		if(isset($opts['region'])) $options['region'] = $opts['region'];
		$result = $this->resend->domains()->create($args[0], $options);
		if($this->failed($result)) return 1;
		$this->out('Created: ' . $args[0] . '. Add the DNS records, then run domains:verify ' . (isset($result['id']) ? $result['id'] : ''));
		return 0;
	}

	protected function cmdDomainsVerify(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->domains()->verify($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Verification triggered: ' . $args[0]);
		return 0;
	}

	protected function cmdDomainsDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->domains()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: contacts and segments
	 * ------------------------------------------------------------------
	 */

	protected function cmdContactsList(array $args, array $opts) {
		$params = $this->pagination($opts);
		$result = isset($opts['segment'])
			? $this->resend->segments()->contacts($opts['segment'], $params)
			: $this->resend->contacts()->all($params);
		return $this->listCommand($result, [
			'id' => 'ID', 'email' => 'Email', 'first_name' => 'First', 'last_name' => 'Last', 'unsubscribed' => 'Unsub',
		]);
	}

	protected function cmdContactsGet(array $args, array $opts) {
		if(!$this->requireArg($args, 'idOrEmail')) return 1;
		$result = $this->resend->contacts()->get($args[0]);
		if($this->failed($result)) return 1;
		return $this->outJson($result);
	}

	protected function cmdContactsCreate(array $args, array $opts) {
		if(!$this->requireArg($args, 'email')) return 1;
		$data = ['email' => $args[0]];
		if(isset($opts['first'])) $data['first_name'] = $opts['first'];
		if(isset($opts['last'])) $data['last_name'] = $opts['last'];
		$result = $this->resend->contacts()->create($data);
		if($this->failed($result)) return 1;
		if(isset($opts['segment']) && isset($result['id'])) {
			$this->resend->contacts()->addToSegment($result['id'], $opts['segment']);
		}
		$this->out('Created: ' . $args[0] . ' (' . (isset($result['id']) ? $result['id'] : '') . ')');
		return 0;
	}

	protected function cmdContactsImport(array $args, array $opts) {
		if(!$this->requireArg($args, 'file')) return 1;
		$file = $args[0];
		if(!is_file($file)) {
			$this->err("File not found: $file");
			return 1;
		}
		$dryRun = isset($opts['dry-run']);
		$segmentId = isset($opts['segment']) ? (string) $opts['segment'] : '';
		$created = 0;
		$failed = 0;
		$line = 0;
		$fh = fopen($file, 'r');
		while(($row = fgetcsv($fh, 0, ',', '"', '\\')) !== false) {
			$line++;
			if(!is_array($row) || !count($row)) continue;
			$email = trim((string) $row[0]);
			if(!strlen($email)) continue;
			if($line === 1 && strpos($email, '@') === false) continue; // header row
			if(strpos($email, '@') === false) {
				$failed++;
				$this->err("Line $line: invalid email: $email");
				continue;
			}
			$data = ['email' => $email];
			if(isset($row[1]) && strlen(trim((string) $row[1]))) $data['first_name'] = trim((string) $row[1]);
			if(isset($row[2]) && strlen(trim((string) $row[2]))) $data['last_name'] = trim((string) $row[2]);
			if($dryRun) {
				$created++;
				continue;
			}
			$result = $this->resend->contacts()->create($data);
			if($this->resend->isError($result)) {
				$failed++;
				$this->err("Line $line: " . $result['error']['message']);
			} else {
				$created++;
				if(strlen($segmentId) && isset($result['id'])) {
					$this->resend->contacts()->addToSegment($result['id'], $segmentId);
					usleep(250000);
				}
			}
			usleep(250000); // stay under the API rate limit
		}
		fclose($fh);
		$this->out(($dryRun ? '[dry-run] ' : '') . "Imported: $created, failed: $failed");
		return $failed ? 1 : 0;
	}

	protected function cmdContactsUnsubscribe(array $args, array $opts) {
		return $this->contactSubscription($args, true);
	}

	protected function cmdContactsResubscribe(array $args, array $opts) {
		return $this->contactSubscription($args, false);
	}

	protected function contactSubscription(array $args, $unsubscribed) {
		if(!$this->requireArg($args, 'idOrEmail')) return 1;
		$result = $this->resend->contacts()->update($args[0], ['unsubscribed' => $unsubscribed]);
		if($this->failed($result)) return 1;
		$this->out(($unsubscribed ? 'Unsubscribed: ' : 'Resubscribed: ') . $args[0]);
		return 0;
	}

	protected function cmdContactsDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'idOrEmail')) return 1;
		$result = $this->resend->contacts()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	protected function cmdSegmentsList(array $args, array $opts) {
		return $this->listCommand($this->resend->segments()->all(), [
			'id' => 'ID', 'name' => 'Name', 'created_at' => 'Created',
		]);
	}

	protected function cmdSegmentsCreate(array $args, array $opts) {
		if(!$this->requireArg($args, 'name')) return 1;
		$result = $this->resend->segments()->create($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Created: ' . $args[0] . ' (' . (isset($result['id']) ? $result['id'] : '') . ')');
		return 0;
	}

	protected function cmdSegmentsDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->segments()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: contact properties
	 * ------------------------------------------------------------------
	 */

	protected function cmdPropertiesList(array $args, array $opts) {
		return $this->listCommand($this->resend->contactProperties()->all(), [
			'id' => 'ID', 'key' => 'Key', 'type' => 'Type', 'fallback_value' => 'Fallback',
		]);
	}

	protected function cmdPropertiesCreate(array $args, array $opts) {
		if(!$this->requireArg($args, 'key')) return 1;
		$type = isset($opts['type']) ? $opts['type'] : 'string';
		if(!in_array($type, ContactProperties::TYPES)) {
			$this->err('Invalid type. Valid: ' . implode(', ', ContactProperties::TYPES));
			return 1;
		}
		$data = ['key' => $args[0], 'type' => $type];
		if(isset($opts['fallback'])) $data['fallback_value'] = $opts['fallback'];
		$result = $this->resend->contactProperties()->create($data);
		if($this->failed($result)) return 1;
		$this->out('Created: ' . $args[0] . ' (' . (isset($result['id']) ? $result['id'] : '') . ')');
		return 0;
	}

	protected function cmdPropertiesDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->contactProperties()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: topics
	 * ------------------------------------------------------------------
	 */

	protected function cmdTopicsList(array $args, array $opts) {
		return $this->listCommand($this->resend->topics()->all(), [
			'id' => 'ID', 'name' => 'Name', 'default_subscription' => 'Default',
		]);
	}

	protected function cmdTopicsCreate(array $args, array $opts) {
		if(!$this->requireArg($args, 'name')) return 1;
		$data = [
			'name' => $args[0],
			'default_subscription' => (isset($opts['default']) && $opts['default'] === 'opt-out') ? 'opt-out' : 'opt-in',
		];
		if(isset($opts['desc'])) $data['description'] = $opts['desc'];
		$result = $this->resend->topics()->create($data);
		if($this->failed($result)) return 1;
		$this->out('Created: ' . $args[0] . ' (' . (isset($result['id']) ? $result['id'] : '') . ')');
		return 0;
	}

	protected function cmdTopicsDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->topics()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: templates
	 * ------------------------------------------------------------------
	 */

	protected function cmdTemplatesList(array $args, array $opts) {
		return $this->listCommand($this->resend->templates()->all($this->pagination($opts)), [
			'id' => 'ID', 'name' => 'Name', 'alias' => 'Alias', 'status' => 'Status',
		]);
	}

	protected function cmdTemplatesPublish(array $args, array $opts) {
		if(!$this->requireArg($args, 'idOrAlias')) return 1;
		$result = $this->resend->templates()->publish($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Published: ' . $args[0]);
		return 0;
	}

	protected function cmdTemplatesDuplicate(array $args, array $opts) {
		if(!$this->requireArg($args, 'idOrAlias')) return 1;
		$result = $this->resend->templates()->duplicate($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Duplicated: ' . $args[0] . ' -> ' . (isset($result['id']) ? $result['id'] : ''));
		return 0;
	}

	protected function cmdTemplatesDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'idOrAlias')) return 1;
		$result = $this->resend->templates()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: broadcasts
	 * ------------------------------------------------------------------
	 */

	protected function cmdBroadcastsList(array $args, array $opts) {
		return $this->listCommand($this->resend->broadcasts()->all($this->pagination($opts)), [
			'id' => 'ID', 'name' => 'Name', 'subject' => 'Subject', 'status' => 'Status',
		]);
	}

	protected function cmdBroadcastsCreate(array $args, array $opts) {
		foreach(['name', 'segment', 'from', 'subject', 'html-file'] as $required) {
			if(!isset($opts[$required])) {
				$this->err('Required: --name= --segment= --from= --subject= --html-file=');
				return 1;
			}
		}
		if(!is_file($opts['html-file'])) {
			$this->err('File not found: ' . $opts['html-file']);
			return 1;
		}
		$result = $this->resend->broadcasts()->create([
			'name' => $opts['name'],
			'segment_id' => $opts['segment'],
			'from' => $opts['from'],
			'subject' => $opts['subject'],
			'html' => file_get_contents($opts['html-file']),
		]);
		if($this->failed($result)) return 1;
		$this->out('Created draft: ' . (isset($result['id']) ? $result['id'] : ''));
		return 0;
	}

	protected function cmdBroadcastsSend(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->broadcasts()->send($args[0], isset($opts['schedule']) ? $opts['schedule'] : '');
		if($this->failed($result)) return 1;
		$this->out(isset($opts['schedule']) ? 'Scheduled: ' . $args[0] : 'Sending: ' . $args[0]);
		return 0;
	}

	protected function cmdBroadcastsDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->broadcasts()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: webhooks and logs
	 * ------------------------------------------------------------------
	 */

	protected function cmdWebhooksList(array $args, array $opts) {
		return $this->listCommand($this->resend->webhooks()->all(), [
			'id' => 'ID', 'endpoint_url' => 'Endpoint', 'status' => 'Status',
		]);
	}

	protected function cmdWebhooksCreate(array $args, array $opts) {
		if(!$this->requireArg($args, 'url')) return 1;
		if(!isset($opts['events'])) {
			$this->err('Required: --events=email.delivered,email.bounced');
			return 1;
		}
		$events = array_values(array_intersect(
			array_map('trim', explode(',', $opts['events'])),
			Webhooks::EVENT_TYPES
		));
		if(!count($events)) {
			$this->err('No valid events. Valid: ' . implode(', ', Webhooks::EVENT_TYPES));
			return 1;
		}
		$result = $this->resend->webhooks()->create($args[0], $events);
		if($this->failed($result)) return 1;
		$this->out('Created: ' . (isset($result['id']) ? $result['id'] : ''));
		if(isset($result['signing_secret'])) {
			$this->out('Signing secret (shown once): ' . $result['signing_secret']);
		}
		return 0;
	}

	protected function cmdWebhooksDelete(array $args, array $opts) {
		if(!$this->requireArg($args, 'id')) return 1;
		$result = $this->resend->webhooks()->delete($args[0]);
		if($this->failed($result)) return 1;
		$this->out('Deleted: ' . $args[0]);
		return 0;
	}

	protected function cmdLogsList(array $args, array $opts) {
		return $this->listCommand($this->resend->logs()->all($this->pagination($opts)), [
			'id' => 'ID', 'method' => 'Method', 'endpoint' => 'Path', 'response_status' => 'Status', 'created_at' => 'Date',
		]);
	}

	/*
	 * ------------------------------------------------------------------
	 * Commands: module config
	 * ------------------------------------------------------------------
	 */

	protected function cmdConfigList(array $args, array $opts) {
		$rows = [];
		foreach(array_keys($this->configDefaults()) as $name) {
			$value = $this->resend->$name;
			if(in_array($name, $this->encryptedSettings())) {
				$value = strlen((string) $value) ? '(set, stored encrypted)' : '(not set)';
			}
			$rows[] = ['name' => $name, 'value' => (string) $value];
		}
		if($this->json) return $this->outJson($rows);
		$this->table($rows, ['name' => 'Setting', 'value' => 'Value']);
		return 0;
	}

	protected function cmdConfigSet(array $args, array $opts) {
		if(count($args) < 2) {
			$this->err('Usage: config:set <name> <value>');
			return 1;
		}
		list($name, $value) = $args;
		$written = $this->resend->saveSettings([$name => $value]);
		if(!count($written)) {
			$valid = implode(', ', array_keys($this->configDefaults()));
			$this->err("Unknown setting: $name" . ($valid ? " (valid: $valid)" : ''));
			return 1;
		}
		$this->out('Set ' . $name . (in_array($name, $this->encryptedSettings()) ? ' (stored encrypted)' : ' = ' . $value));
		return 0;
	}

	/**
	 * @return array
	 */
	protected function configDefaults() {
		$class = get_class($this->resend);
		return defined("$class::CONFIG_DEFAULTS") ? constant("$class::CONFIG_DEFAULTS") : [];
	}

	/**
	 * @return array
	 */
	protected function encryptedSettings() {
		$class = get_class($this->resend);
		return defined("$class::ENCRYPTED_SETTINGS") ? constant("$class::ENCRYPTED_SETTINGS") : [];
	}

	/*
	 * ------------------------------------------------------------------
	 * Helpers
	 * ------------------------------------------------------------------
	 */

	/**
	 * Standard list command: error check, JSON or table output.
	 *
	 * @param array $result API result
	 * @param array $columns key => header
	 * @return int
	 */
	protected function listCommand($result, array $columns) {
		if($this->failed($result)) return 1;
		$data = isset($result['data']) ? $result['data'] : [];
		if($this->json) return $this->outJson($data);
		$this->table($data, $columns);
		return 0;
	}

	/**
	 * @param array $opts
	 * @return array Pagination params
	 */
	protected function pagination(array $opts) {
		$params = ['limit' => isset($opts['limit']) ? max(1, (int) $opts['limit']) : 25];
		if(isset($opts['after'])) $params['after'] = $opts['after'];
		if(isset($opts['before'])) $params['before'] = $opts['before'];
		return $params;
	}

	/**
	 * If the result is an error, print it and return true.
	 *
	 * @param array $result
	 * @return bool
	 */
	protected function failed($result) {
		if($this->resend->isError($result)) {
			$e = $result['error'];
			$this->err("API error: {$e['name']} — {$e['message']} ({$e['statusCode']})");
			return true;
		}
		return false;
	}

	/**
	 * @param array $args
	 * @param string $name For the usage message
	 * @return bool
	 */
	protected function requireArg(array $args, $name) {
		if(!count($args) || !strlen($args[0])) {
			$this->err("Required argument: <$name>");
			return false;
		}
		return true;
	}

	/**
	 * Render rows as an aligned text table.
	 *
	 * @param array $rows
	 * @param array $columns key => header
	 */
	protected function table(array $rows, array $columns) {
		if(!count($rows)) {
			$this->out('(empty)');
			return;
		}
		$widths = [];
		foreach($columns as $key => $header) $widths[$key] = strlen($header);
		$cells = [];
		foreach($rows as $row) {
			$line = [];
			foreach($columns as $key => $header) {
				$value = isset($row[$key]) ? $row[$key] : '';
				if(is_bool($value)) $value = $value ? 'yes' : 'no';
				if(is_array($value)) $value = $this->json($value);
				$value = (string) $value;
				if(strlen($value) > 48) $value = substr($value, 0, 45) . '...';
				$line[$key] = $value;
				$widths[$key] = max($widths[$key], strlen($value));
			}
			$cells[] = $line;
		}
		$header = '';
		foreach($columns as $key => $label) $header .= str_pad($label, $widths[$key] + 2);
		$this->out(rtrim($header));
		$this->out(str_repeat('-', array_sum($widths) + count($widths) * 2 - 2));
		foreach($cells as $line) {
			$row = '';
			foreach($columns as $key => $label) $row .= str_pad($line[$key], $widths[$key] + 2);
			$this->out(rtrim($row));
		}
	}

	protected function out($text) {
		fwrite($this->stdout, $text . "\n");
	}

	protected function err($text) {
		fwrite($this->stderr, $text . "\n");
	}

	/**
	 * @param mixed $data
	 * @return int
	 */
	protected function outJson($data) {
		$this->out($this->json($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		return 0;
	}

	/**
	 * @param mixed $data
	 * @param int $flags
	 * @return string
	 */
	protected function json($data, $flags = 0) {
		$json = json_encode($data, $flags | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json === false ? '[unencodable]' : $json;
	}
}
