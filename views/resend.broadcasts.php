<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'broadcasts/';
$drafts = 0;
$sent = 0;
foreach($broadcasts as $b) {
	$status = (string) ($b['status'] ?? '');
	if($status === 'draft') $drafts++;
	elseif(strlen($status)) $sent++;
}
?>
<?= resendNav($adminUrl, 'broadcasts') ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove"><?= resendE('Create one-off campaigns for a segment, save them as drafts, then edit, send now or schedule for later.') ?></p>
	</div>
	<div class="uk-width-auto@m">
		<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>segments/"><?= resendE('Segments') ?></a>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Broadcasts', 'value' => count($broadcasts), 'note' => 'Campaigns loaded on this page.'],
	['label' => 'Drafts', 'value' => $drafts, 'note' => 'Editable before sending.'],
	['label' => 'Sent/Scheduled', 'value' => $sent, 'note' => 'Already moved past draft.'],
	['label' => 'Segments', 'value' => count($segments), 'note' => 'Available audiences.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top"><?= resendE('Create Broadcast') ?></h3>
			<p class="uk-text-muted"><?= resendE('Start with name, audience, sender and subject. After creation, open the draft to refine the HTML before sending.') ?></p>
			<?= resendNote("Include <code>{{{RESEND_UNSUBSCRIBE_URL}}}</code> in campaign emails.") ?>
		</div>
		<div class="uk-width-2-3@l">
	<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
		<?= resendCsrf($csrf) ?>
		<input type="hidden" name="action" value="create">
		<div class="uk-grid-medium" uk-grid>
			<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('Name') ?></label><input class="uk-input" type="text" name="name" required></div>
			<div class="uk-width-1-2@m">
				<label class="uk-form-label"><?= resendE('Segment') ?></label>
				<select class="uk-select" name="segment_id" required>
					<?php foreach($segments as $s): ?>
					<option value="<?= $sanitizer->entities($s['id']) ?>"><?= $sanitizer->entities($s['name'] ?? '') ?></option>
					<?php endforeach; ?>
				</select>
			</div>
			<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('From') ?></label><input class="uk-input" type="text" name="from" placeholder="Name &lt;news@example.com&gt;" required></div>
			<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('Subject') ?></label><input class="uk-input" type="text" name="subject" required></div>
		</div>
		<div class="uk-margin">
			<label class="uk-form-label"><?= resendE('HTML Body') ?></label>
			<textarea class="uk-textarea" name="html" rows="6" required></textarea>
		</div>
		<p class="uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Create Draft') ?></button></p>
	</form>
		</div>
	</div>
</div>

<?php if(!count($broadcasts)): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top"><?= resendE('No Broadcasts Yet') ?></h3>
				<p class="uk-margin-remove-bottom"><?= resendE('Create a draft after you have at least one segment ready. Drafts can be edited before sending.') ?></p>
			</div>
			<div class="uk-width-auto@m">
				<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>segments/"><?= resendE('Manage Segments') ?></a>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
	<tr><th><?= resendE('Name') ?></th><th><?= resendE('Subject') ?></th><th><?= resendE('Status') ?></th><th><?= resendE('Created') ?></th><th><?= resendE('Actions') ?></th></tr>
	<?php foreach($broadcasts as $b): ?>
	<?php $id = (string) ($b['id'] ?? ''); ?>
	<tr>
		<td>
			<a href="<?= $adminUrl ?>broadcast/?id=<?= rawurlencode($id) ?>"><?= $sanitizer->entities($b['name'] ?? 'Untitled') ?></a>
			<div class="uk-text-meta uk-text-monospace"><?= $sanitizer->entities($id) ?></div>
		</td>
		<td><?= $sanitizer->entities($b['subject'] ?? '') ?></td>
		<td><?= $process->badge($b['status'] ?? '') ?></td>
		<td><?= $process->formatDate($b['created_at'] ?? '') ?></td>
		<td>
			<a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>broadcast/?id=<?= rawurlencode($id) ?>"><?= resendE('Edit') ?></a>
			<?php if(($b['status'] ?? '') === 'draft'): ?>
				<form class="uk-display-inline-block uk-margin-right" method="post" action="<?= $actionUrl ?>" onsubmit="return confirm('Send this broadcast?')">
					<?= resendCsrf($csrf) ?>
					<input type="hidden" name="action" value="send">
					<input type="hidden" name="id" value="<?= $sanitizer->entities($id) ?>">
					<input class="uk-input uk-form-small uk-form-width-small" type="text" name="scheduled_at" placeholder="now or in 1 hour">
					<button type="submit" class="uk-button uk-button-small uk-button-primary"><?= resendE('Send') ?></button>
				</form>
			<?php endif; ?>
			<?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $id], 'Delete', $csrf, 'danger', 'Delete this broadcast?') ?>
		</td>
	</tr>
	<?php endforeach; ?>
</table>
	</div>
<?php endif; ?>

<?= resendPagination($adminUrl . 'broadcasts/', $broadcasts, $hasMore) ?>
