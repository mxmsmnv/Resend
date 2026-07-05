<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'segments/';
$segmentCount = count($segments);
$recent = 0;
$now = time();
$latest = '';

foreach($segments as $s) {
	$created = isset($s['created_at']) ? strtotime((string) $s['created_at']) : false;
	if($created !== false) {
		if($created >= $now - (30 * 86400)) $recent++;
		if(!strlen($latest) || $created > strtotime($latest)) $latest = (string) $s['created_at'];
	}
}
?>
<?= resendNav($adminUrl, 'segments') ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove">Segments are named audiences for contacts and broadcasts. Create operational groups, then open a segment to review and manage its members from the Contacts screen.</p>
	</div>
	<div class="uk-width-auto@m">
		<div class="uk-grid-medium uk-child-width-auto uk-flex-right@m" uk-grid>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>contacts/">Contacts</a></div>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>broadcasts/">Broadcasts</a></div>
		</div>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Segments', 'value' => $segmentCount, 'note' => 'Audience groups in Resend.'],
	['label' => 'Recent', 'value' => $recent, 'note' => 'Created in the last 30 days.'],
	['label' => 'Latest', 'value' => strlen($latest) ? $process->formatDate($latest) : '-', 'note' => 'Most recently created segment.'],
	['label' => 'Membership', 'value' => 'Contacts', 'note' => 'Open a segment to manage members.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top">Create Segment</h3>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="create">
				<label class="uk-form-label">Name</label>
				<input class="uk-input" type="text" name="name" placeholder="Newsletter subscribers" required>
				<p class="uk-margin-small-top uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary">Create Segment</button></p>
			</form>
			<?= resendNote("Deleting a segment does not delete its contacts. Use clear operational names so broadcast audiences are easy to choose later.") ?>
		</div>
		<div class="uk-width-2-3@l">
			<h3 class="uk-margin-remove-top">Audience Workflow</h3>
			<div class="uk-grid-medium uk-child-width-1-3@m" uk-grid>
				<div>
					<p class="uk-text-meta uk-text-uppercase uk-margin-remove">1. Create</p>
					<p class="uk-margin-small-top">Add a segment for each audience you plan to target.</p>
				</div>
				<div>
					<p class="uk-text-meta uk-text-uppercase uk-margin-remove">2. Populate</p>
					<p class="uk-margin-small-top">Open the segment in Contacts and add or import members.</p>
				</div>
				<div>
					<p class="uk-text-meta uk-text-uppercase uk-margin-remove">3. Send</p>
					<p class="uk-margin-small-top">Choose the segment when drafting a broadcast.</p>
				</div>
			</div>
		</div>
	</div>
</div>

<?php if(!$segmentCount): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top">No Segments Yet</h3>
				<p class="uk-margin-remove-bottom">Create a segment before drafting a broadcast audience. Contacts can be added to one or more segments later.</p>
			</div>
			<div class="uk-width-auto@m">
				<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>contacts/">Open Contacts</a>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th>Segment</th><th>ID</th><th>Created</th><th>Use</th><th>Actions</th></tr>
		<?php foreach($segments as $s): ?>
		<?php
			$id = (string) ($s['id'] ?? '');
			$name = (string) ($s['name'] ?? '');
		?>
		<tr>
			<td>
				<a href="<?= $adminUrl ?>contacts/?segment=<?= rawurlencode($id) ?>"><?= $sanitizer->entities($name) ?></a>
				<div class="uk-text-meta">Browse members in Contacts</div>
			</td>
			<td class="uk-text-monospace"><?= $sanitizer->entities($id) ?></td>
			<td><?= $process->formatDate($s['created_at'] ?? '') ?></td>
			<td>
				<a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>contacts/?segment=<?= rawurlencode($id) ?>">Contacts</a>
				<a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>broadcasts/">Broadcasts</a>
			</td>
			<td><?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $id], 'Delete', $csrf, 'danger', 'Delete this segment? Contacts inside it will not be deleted.') ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php endif; ?>
