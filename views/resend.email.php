<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$status = $email['last_event'] ?? ($email['status'] ?? '');
$actionUrl = $adminUrl . 'email/?id=' . rawurlencode($id) . '&received=' . (int) $received;
?>
<?= resendNav($adminUrl, $received ? 'received' : 'emails') ?>
<?= resendIntro('email') ?>

<?php if(!count($email)): ?>
	<?= resendEmpty('Email not found.') ?>
<?php else: ?>

<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
<dl class="uk-description-list uk-description-list-divider uk-margin-remove-bottom">
	<dt>ID</dt><dd class="uk-text-monospace"><?= $sanitizer->entities($email['id'] ?? '') ?></dd>
	<dt>From</dt><dd><?= $sanitizer->entities($email['from'] ?? '') ?></dd>
	<dt>To</dt><dd><?= $sanitizer->entities(implode(', ', (array) ($email['to'] ?? []))) ?></dd>
	<?php if(!empty($email['cc'])): ?><dt>Cc</dt><dd><?= $sanitizer->entities(implode(', ', (array) $email['cc'])) ?></dd><?php endif; ?>
	<?php if(!empty($email['bcc'])): ?><dt>Bcc</dt><dd><?= $sanitizer->entities(implode(', ', (array) $email['bcc'])) ?></dd><?php endif; ?>
	<?php if(!empty($email['reply_to'])): ?><dt>Reply-To</dt><dd><?= $sanitizer->entities(implode(', ', (array) $email['reply_to'])) ?></dd><?php endif; ?>
	<dt>Subject</dt><dd><?= $sanitizer->entities($email['subject'] ?? '') ?></dd>
	<dt>Status</dt><dd><?= $process->badge($status) ?></dd>
	<dt>Created</dt><dd><?= $process->formatDate($email['created_at'] ?? '') ?></dd>
	<?php if(!empty($email['scheduled_at'])): ?><dt>Scheduled At</dt><dd><?= $process->formatDate($email['scheduled_at']) ?></dd><?php endif; ?>
</dl>
</div>

<?php if(!$received && $status === 'scheduled'): ?>
	<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
		<h3>Scheduled Email</h3>
		<div class="uk-grid-small uk-flex-bottom" uk-grid>
			<div class="uk-width-1-3@m">
			<form id="scheduled-resend-form" class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="reschedule">
				<label class="uk-form-label" for="scheduled_at">Reschedule (natural language or ISO 8601)</label>
				<input class="uk-input" type="text" name="scheduled_at" id="scheduled_at" placeholder="in 1 hour">
			</form>
			</div>
			<div class="uk-width-auto@m"><button form="scheduled-resend-form" type="submit" class="uk-button uk-button-primary">Reschedule</button></div>
			<div class="uk-width-auto@m"><?= resendActionForm($actionUrl, ['action' => 'cancel'], 'Cancel Send', $csrf, 'danger', 'Cancel this scheduled email?') ?></div>
		</div>
	</div>
<?php endif; ?>

<?php if(count($attachments)): ?>
	<h2>Attachments</h2>
	<?= resendNote("Attachment metadata is listed here for inspection. Downloading or rendering attachment content should be handled outside this view if you need a deeper forensic review.") ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th>Filename</th><th>Content Type</th><th>ID</th></tr>
		<?php foreach($attachments as $a): ?>
		<tr>
			<td><?= $sanitizer->entities($a['filename'] ?? '') ?></td>
			<td><?= $sanitizer->entities($a['content_type'] ?? '') ?></td>
			<td class="uk-text-monospace"><?= $sanitizer->entities($a['id'] ?? '') ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php endif; ?>

<?php if(!empty($email['html'])): ?>
	<h2>HTML Preview</h2>
	<?= resendNote("This preview is isolated in a sandboxed iframe. Treat received email HTML as untrusted even when the sender appears familiar.") ?>
	<?php /* Sandboxed iframe: no scripts, no forms, no same-origin access.
	        Email HTML is untrusted input (especially for received emails). */ ?>
	<iframe class="uk-width-1-1" height="520" sandbox="" referrerpolicy="no-referrer" srcdoc="<?= $sanitizer->entities($email['html']) ?>"></iframe>
<?php elseif(!empty($email['text'])): ?>
	<h2>Text</h2>
	<pre><?= $sanitizer->entities($email['text']) ?></pre>
<?php endif; ?>

<?php endif; ?>
