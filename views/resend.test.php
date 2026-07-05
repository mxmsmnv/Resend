<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$settingsUrl = wire()->config->urls->admin . 'module/edit?name=Resend';
$fromLabel = strlen((string) $fromEmail)
	? (strlen((string) $fromName) ? $fromName . ' <' . $fromEmail . '>' : $fromEmail)
	: '';
$scenario = wire()->input->get->text('scenario');
$simulators = [
	'delivered' => ['Delivered', 'delivered@resend.dev', 'Normal inbox delivery'],
	'bounced' => ['Bounced', 'bounced@resend.dev', 'Recipient rejects the email'],
	'complained' => ['Complaint', 'complained@resend.dev', 'Recipient reports the email'],
];
if(!isset($simulators[$scenario])) $scenario = 'delivered';
$selectedSimulator = $simulators[$scenario];
?>
<?= resendNav($adminUrl, 'test', $restricted) ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove">Send a safe simulator email after changing settings, sender defaults, retry behavior or template code. Simulator recipients generate known outcomes without contacting a real person.</p>
	</div>
	<div class="uk-width-auto@m">
		<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>emails/">View Sent Emails</a>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Default From', 'value' => $fromConfigured ? $process->badge('active') : $process->badge('missing'), 'note' => $fromConfigured ? $sanitizer->entities($fromLabel) : 'Required before sending tests.'],
	['label' => 'API Permission', 'value' => $restricted ? $process->badge('sending') : $process->badge('full access'), 'note' => 'Test sending works with either key type.'],
	['label' => 'Simulators', 'value' => 'resend.dev', 'note' => 'Use simulator recipients for safe outcomes.'],
]) ?>

<?php if(!$fromConfigured): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top">Default From Email Required</h3>
				<p class="uk-margin-remove-bottom">Set <strong>Default From Email</strong> in Resend settings first, using an address on a verified Resend domain.</p>
			</div>
			<div class="uk-width-auto@m">
				<a class="uk-button uk-button-primary" style="text-decoration: none;" href="<?= $settingsUrl ?>">Open Settings</a>
			</div>
		</div>
	</div>
<?php endif; ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-flex-middle uk-margin-small-bottom" uk-grid>
		<div class="uk-width-expand@m">
			<h3 class="uk-margin-remove">Send a Test Email</h3>
			<p class="uk-text-muted uk-margin-small-top uk-margin-remove-bottom">Select the delivery result you want to simulate. The safe resend.dev recipient is filled in automatically.</p>
		</div>
		<div class="uk-width-auto@m">
			<div class="uk-text-meta">Recipient: <span class="uk-text-monospace"><?= $sanitizer->entities($selectedSimulator[1]) ?></span></div>
		</div>
	</div>

	<div class="uk-margin">
		<div class="uk-grid-small uk-child-width-1-3@m" uk-grid>
			<?php foreach($simulators as $key => $sim): ?>
			<div>
				<a class="uk-display-block uk-padding-small <?= $key === $scenario ? 'uk-background-primary uk-light' : 'uk-background-muted' ?>" style="text-decoration: none;" href="<?= $adminUrl ?>test/?scenario=<?= rawurlencode($key) ?>">
					<strong><?= $sanitizer->entities($sim[0]) ?></strong>
					<span class="uk-display-block uk-text-small"><?= $sanitizer->entities($sim[2]) ?></span>
				</a>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<form class="uk-form-stacked" method="post" action="<?= $adminUrl ?>test/">
		<?= resendCsrf($csrf) ?>
		<input type="hidden" name="action" value="send">
		<div class="uk-grid-medium" uk-grid>
			<div class="uk-width-1-2@m"><label class="uk-form-label">To</label><input class="uk-input" type="email" name="to" value="<?= $sanitizer->entities($selectedSimulator[1]) ?>" required <?= $fromConfigured ? '' : 'disabled' ?>></div>
			<div class="uk-width-1-2@m"><label class="uk-form-label">Subject</label><input class="uk-input" type="text" name="subject" value="Test from ProcessWire via Resend" required <?= $fromConfigured ? '' : 'disabled' ?>></div>
			<div class="uk-width-1-2@m"><label class="uk-form-label">Schedule</label><input class="uk-input" type="text" name="scheduled_at" placeholder="Leave empty, or use in 1 min" <?= $fromConfigured ? '' : 'disabled' ?>></div>
		</div>
		<div class="uk-margin">
			<label class="uk-form-label">HTML Body</label>
			<textarea class="uk-textarea" name="body" rows="7" <?= $fromConfigured ? '' : 'disabled' ?>>&lt;p&gt;It works.&lt;/p&gt;</textarea>
		</div>
		<div class="uk-margin-remove-bottom">
			<div class="uk-grid-medium uk-child-width-auto uk-flex-middle" uk-grid>
				<div><button type="submit" class="uk-button uk-button-primary" <?= $fromConfigured ? '' : 'disabled' ?>>Send Test</button></div>
				<?php if(!$fromConfigured): ?><div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $settingsUrl ?>">Set Default From</a></div><?php endif; ?>
			</div>
		</div>
	</form>
	<?= resendNote("Simulator addresses are safe: <code>delivered@resend.dev</code>, <code>bounced@resend.dev</code> and <code>complained@resend.dev</code>.") ?>
</div>
