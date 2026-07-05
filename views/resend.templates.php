<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'templates/';
$drafts = 0;
$published = 0;
foreach($templates as $t) {
	$status = (string) ($t['status'] ?? '');
	if($status === 'published') $published++;
	elseif(strlen($status)) $drafts++;
}
?>
<?= resendNav($adminUrl, 'templates') ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove">Create reusable email bodies, save drafts, then publish the version that should be used by template sends.</p>
	</div>
	<div class="uk-width-auto@m">
		<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>broadcasts/">Broadcasts</a>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Templates', 'value' => count($templates), 'note' => 'Reusable email bodies.'],
	['label' => 'Published', 'value' => $published, 'note' => 'Available for sends.'],
	['label' => 'Drafts', 'value' => $drafts, 'note' => 'Need review or publish.'],
	['label' => 'Variables', 'value' => '{{{NAME}}}', 'note' => 'Merge tag syntax.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top">Create Template</h3>
			<p class="uk-text-muted">Start a reusable draft with metadata and HTML. Open it after creation to keep editing and publish when ready.</p>
			<?= resendNote("Send with <code>\$mail->useTemplate('idOrAlias', ['VAR' => 'value'])</code>.") ?>
		</div>
		<div class="uk-width-2-3@l">
	<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
		<?= resendCsrf($csrf) ?>
		<input type="hidden" name="action" value="create">
		<div class="uk-grid-medium" uk-grid>
			<div class="uk-width-1-2@m"><label class="uk-form-label">Name</label><input class="uk-input" type="text" name="name" required></div>
			<div class="uk-width-1-2@m"><label class="uk-form-label">Alias</label><input class="uk-input" type="text" name="alias" placeholder="welcome-email"></div>
			<div class="uk-width-1-2@m"><label class="uk-form-label">Subject</label><input class="uk-input" type="text" name="subject"></div>
			<div class="uk-width-1-2@m"><label class="uk-form-label">From</label><input class="uk-input" type="text" name="from" placeholder="Name &lt;me@example.com&gt;"></div>
		</div>
		<div class="uk-margin">
			<label class="uk-form-label">HTML Body</label>
			<textarea class="uk-textarea" name="html" rows="6" required></textarea>
		</div>
		<p class="uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary">Create Draft</button></p>
	</form>
		</div>
	</div>
</div>

<?php if(!count($templates)): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top">No Templates Yet</h3>
				<p class="uk-margin-remove-bottom">Create a draft and publish it before using template sends.</p>
			</div>
			<div class="uk-width-auto@m">
				<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>properties/">Contact Properties</a>
			</div>
		</div>
	</div>
<?php else: ?>
<div class="uk-overflow-auto uk-margin-medium-bottom">
<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
	<tr><th>Name</th><th>Alias</th><th>Status</th><th>ID</th><th>Updated</th><th>Actions</th></tr>
	<?php foreach($templates as $t): ?>
	<tr>
		<td><a href="<?= $adminUrl ?>template/?id=<?= rawurlencode($t['id']) ?>"><?= $sanitizer->entities($t['name'] ?? '') ?></a></td>
		<td class="uk-text-monospace"><?= $sanitizer->entities($t['alias'] ?? '') ?></td>
		<td><?= $process->badge($t['status'] ?? '') ?></td>
		<td class="uk-text-monospace"><?= $sanitizer->entities($t['id'] ?? '') ?></td>
		<td><?= $process->formatDate($t['updated_at'] ?? ($t['created_at'] ?? '')) ?></td>
		<td>
			<?php if(($t['status'] ?? '') !== 'published'): ?>
				<?= resendActionForm($actionUrl, ['action' => 'publish', 'id' => $t['id']], 'Publish', $csrf) ?>
			<?php endif; ?>
			<?= resendActionForm($actionUrl, ['action' => 'duplicate', 'id' => $t['id']], 'Duplicate', $csrf, 'secondary') ?>
			<?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $t['id']], 'Delete', $csrf, 'danger', 'Delete this template?') ?>
		</td>
	</tr>
	<?php endforeach; ?>
</table>
</div>
<?php endif; ?>

<?= resendPagination($adminUrl . 'templates/', $templates, $hasMore) ?>
