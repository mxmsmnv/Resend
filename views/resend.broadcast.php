<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'broadcast/?id=' . rawurlencode($id);
?>
<?= resendNav($adminUrl, 'broadcasts') ?>

<?php if(!count($broadcast)): ?>
	<?= resendEmpty('Broadcast not found.') ?>
<?php else: ?>
<?php
$status = (string) ($broadcast['status'] ?? 'unknown');
if(!strlen($status)) $status = 'unknown';
$segmentId = (string) ($broadcast['segment_id'] ?? '');
?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove"><?= resendE('Edit the draft campaign content, audience and sender before sending or scheduling it.') ?></p>
	</div>
	<div class="uk-width-auto@m">
		<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>broadcasts/"><?= resendE('Back to Broadcasts') ?></a>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Broadcast', 'value' => $sanitizer->entities($broadcast['name'] ?? 'Untitled'), 'note' => '<span class="uk-text-monospace">' . $sanitizer->entities($broadcast['id'] ?? '') . '</span>'],
	['label' => 'Status', 'value' => $process->badge($status), 'note' => $status === 'draft' ? 'Editable draft.' : 'Sent or scheduled campaigns may be locked by Resend.'],
	['label' => 'Audience', 'value' => strlen($segmentId) ? 'Segment' : 'Missing', 'note' => strlen($segmentId) ? '<span class="uk-text-monospace">' . $sanitizer->entities($segmentId) . '</span>' : 'Choose a segment before sending.'],
	['label' => 'Created', 'value' => $process->formatDate($broadcast['created_at'] ?? ''), 'note' => 'Last saved in Resend.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-2-3@l">
			<h3 class="uk-margin-remove-top"><?= resendE('Edit Broadcast') ?></h3>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="update">
				<div class="uk-grid-medium" uk-grid>
					<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('Name') ?></label><input class="uk-input" type="text" name="name" value="<?= $sanitizer->entities($broadcast['name'] ?? '') ?>" required></div>
					<div class="uk-width-1-2@m">
						<label class="uk-form-label"><?= resendE('Segment') ?></label>
						<select class="uk-select" name="segment_id" required>
							<?php foreach($segments as $s): ?>
							<option value="<?= $sanitizer->entities($s['id']) ?>" <?= $s['id'] === $segmentId ? 'selected' : '' ?>><?= $sanitizer->entities($s['name'] ?? '') ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('From') ?></label><input class="uk-input" type="text" name="from" value="<?= $sanitizer->entities($broadcast['from'] ?? '') ?>" placeholder="Name &lt;news@example.com&gt;" required></div>
					<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('Subject') ?></label><input class="uk-input" type="text" name="subject" value="<?= $sanitizer->entities($broadcast['subject'] ?? '') ?>" required></div>
				</div>
				<div class="uk-margin">
					<label class="uk-form-label"><?= resendE('HTML Body') ?></label>
					<textarea class="uk-textarea" name="html" rows="14" required><?= $sanitizer->entities($broadcast['html'] ?? '') ?></textarea>
				</div>
				<p class="uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Save Draft') ?></button></p>
			</form>
		</div>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top"><?= resendE('Send') ?></h3>
			<?php if($status === 'draft'): ?>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>" onsubmit="return confirm('Send this broadcast?')">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="send">
				<label class="uk-form-label"><?= resendE('Schedule') ?></label>
				<input class="uk-input" type="text" name="scheduled_at" placeholder="now or in 1 hour">
				<p class="uk-text-meta"><?= resendE('Leave empty to send immediately, or enter a natural time like') ?> <code><?= resendE('tomorrow 9am') ?></code>.</p>
				<p class="uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Send Broadcast') ?></button></p>
			</form>
			<?php else: ?>
				<p class="uk-text-muted"><?= resendE('This broadcast is no longer a draft, so send controls may be unavailable.') ?></p>
			<?php endif; ?>
			<hr>
			<?= resendActionForm($actionUrl, ['action' => 'delete'], 'Delete Broadcast', $csrf, 'danger', 'Delete this broadcast?') ?>
			<?= resendNote("Include <code>{{{RESEND_UNSUBSCRIBE_URL}}}</code> in campaign emails so recipients can unsubscribe.") ?>
		</div>
	</div>
</div>

<?php endif; ?>
