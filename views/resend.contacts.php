<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'contacts/' . (strlen($segmentId) ? '?segment=' . rawurlencode($segmentId) : '');
$active = 0;
$unsubscribed = 0;
$named = 0;
$selectedSegmentName = '';

foreach($segments as $s) {
	if(($s['id'] ?? '') === $segmentId) $selectedSegmentName = (string) ($s['name'] ?? '');
}
foreach($contacts as $c) {
	if(!empty($c['unsubscribed'])) $unsubscribed++;
	else $active++;
	$name = trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? ''));
	if(strlen($name)) $named++;
}
?>
<?= resendNav($adminUrl, 'contacts') ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove"><?= resendE('Contacts make up the audience used by broadcasts, segments and topic subscriptions. Filter by segment, add a person, or open a profile to manage membership and subscription state.') ?></p>
	</div>
	<div class="uk-width-auto@m">
		<div class="uk-grid-medium uk-child-width-auto uk-flex-right@m" uk-grid>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>segments/"><?= resendE('Segments') ?></a></div>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>topics/"><?= resendE('Topics') ?></a></div>
		</div>
	</div>
</div>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-small uk-grid-divider uk-child-width-1-4@l uk-child-width-1-2@s" uk-grid>
		<div>
			<p class="uk-text-meta uk-text-uppercase uk-margin-remove"><?= resendE('This Page') ?></p>
			<div class="uk-text-large uk-margin-small-top"><?= count($contacts) ?></div>
			<p class="uk-text-muted uk-margin-remove"><?= strlen($segmentId) ? 'Filtered by segment.' : 'Contacts loaded.' ?></p>
		</div>
		<div>
			<p class="uk-text-meta uk-text-uppercase uk-margin-remove"><?= resendE('Active') ?></p>
			<div class="uk-text-large uk-margin-small-top"><?= $active ?></div>
			<p class="uk-text-muted uk-margin-remove"><?= resendE('Available for broadcasts.') ?></p>
		</div>
		<div>
			<p class="uk-text-meta uk-text-uppercase uk-margin-remove"><?= resendE('Unsubscribed') ?></p>
			<div class="uk-text-large uk-margin-small-top"><?= $unsubscribed ?></div>
			<p class="uk-text-muted uk-margin-remove"><?= resendE('Excluded from broadcasts.') ?></p>
		</div>
		<div>
			<p class="uk-text-meta uk-text-uppercase uk-margin-remove"><?= resendE('Named') ?></p>
			<div class="uk-text-large uk-margin-small-top"><?= $named ?></div>
			<p class="uk-text-muted uk-margin-remove"><?= resendE('Have first or last name.') ?></p>
		</div>
	</div>
</div>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top"><?= resendE('View') ?></h3>
			<form class="uk-form-stacked" method="get" action="<?= $adminUrl ?>contacts/">
				<label class="uk-form-label"><?= resendE('Segment') ?></label>
				<select class="uk-select" name="segment" onchange="this.form.submit()">
					<option value=""><?= resendE('All contacts') ?></option>
					<?php foreach($segments as $s): ?>
					<option value="<?= $sanitizer->entities($s['id']) ?>" <?= $s['id'] === $segmentId ? 'selected' : '' ?>><?= $sanitizer->entities($s['name'] ?? '') ?></option>
					<?php endforeach; ?>
				</select>
			</form>
			<?php if(strlen($segmentId)): ?>
				<p class="uk-text-meta">Showing <?= $sanitizer->entities(strlen($selectedSegmentName) ? $selectedSegmentName : $segmentId) ?>.</p>
				<p class="uk-margin-remove-bottom"><a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>contacts/"><?= resendE('Clear Filter') ?></a></p>
			<?php else: ?>
				<p class="uk-text-meta uk-margin-remove-bottom"><?= resendE('Choose a segment to focus this page on one audience.') ?></p>
			<?php endif; ?>
		</div>
		<div class="uk-width-2-3@l">
			<h3 class="uk-margin-remove-top"><?= resendE('Add Contact') ?></h3>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="create">
				<div class="uk-grid-medium uk-flex-bottom" uk-grid>
					<div class="uk-width-1-2@m"><label class="uk-form-label"><?= resendE('Email') ?></label><input class="uk-input" type="email" name="email" required></div>
					<div class="uk-width-1-4@m"><label class="uk-form-label"><?= resendE('First Name') ?></label><input class="uk-input" type="text" name="first_name"></div>
					<div class="uk-width-1-4@m"><label class="uk-form-label"><?= resendE('Last Name') ?></label><input class="uk-input" type="text" name="last_name"></div>
					<div class="uk-width-1-2@m">
						<label class="uk-form-label"><?= resendE('Segment') ?></label>
						<select class="uk-select" name="segment_id">
							<option value=""><?= resendE('None') ?></option>
							<?php foreach($segments as $s): ?>
							<option value="<?= $sanitizer->entities($s['id']) ?>" <?= $s['id'] === $segmentId ? 'selected' : '' ?>><?= $sanitizer->entities($s['name'] ?? '') ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<div class="uk-width-auto@m"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Add Contact') ?></button></div>
				</div>
			</form>
			<?= resendNote("Segment membership is optional; a contact can belong to several segments. Unsubscribed contacts are excluded from broadcasts automatically.") ?>
		</div>
	</div>
</div>

<?php if(!count($contacts)): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top"><?= resendE('No Contacts Found') ?></h3>
				<p class="uk-margin-remove-bottom"><?= strlen($segmentId) ? 'No contacts match this segment. Clear the filter or add a contact to this audience.' : 'Add a contact manually above, or use the CLI import command for larger lists.' ?></p>
			</div>
			<div class="uk-width-auto@m">
				<div class="uk-grid-medium uk-child-width-auto uk-flex-right@m" uk-grid>
					<?php if(strlen($segmentId)): ?><div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>contacts/"><?= resendE('Clear Filter') ?></a></div><?php endif; ?>
					<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>segments/"><?= resendE('Manage Segments') ?></a></div>
				</div>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th><?= resendE('Contact') ?></th><th><?= resendE('Name') ?></th><th><?= resendE('Subscription') ?></th><th><?= resendE('Created') ?></th><th><?= resendE('Actions') ?></th></tr>
		<?php foreach($contacts as $c): ?>
		<?php
			$id = (string) ($c['id'] ?? '');
			$email = (string) ($c['email'] ?? '');
			$name = trim((string) ($c['first_name'] ?? '') . ' ' . (string) ($c['last_name'] ?? ''));
			$unsub = !empty($c['unsubscribed']);
		?>
		<tr>
			<td>
				<a href="<?= $adminUrl ?>contact/?id=<?= rawurlencode($id) ?>"><?= $sanitizer->entities($email) ?></a>
				<div class="uk-text-meta uk-text-monospace"><?= $sanitizer->entities($id) ?></div>
			</td>
			<td><?= strlen($name) ? $sanitizer->entities($name) : '<span class="uk-text-muted">' . resendE('No name') . '</span>' ?></td>
			<td><?= $unsub ? $process->badge('unsubscribed') : $process->badge('active') ?></td>
			<td><?= $process->formatDate($c['created_at'] ?? '') ?></td>
			<td>
				<a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>contact/?id=<?= rawurlencode($id) ?>"><?= resendE('Profile') ?></a>
				<?php if($unsub): ?>
					<?= resendActionForm($actionUrl, ['action' => 'resubscribe', 'id' => $id], 'Resubscribe', $csrf, 'secondary') ?>
				<?php else: ?>
					<?= resendActionForm($actionUrl, ['action' => 'unsubscribe', 'id' => $id], 'Unsubscribe', $csrf, 'secondary') ?>
				<?php endif; ?>
				<?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $id], 'Delete', $csrf, 'danger', 'Delete this contact?') ?>
			</td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php endif; ?>

<?= resendPagination($adminUrl . 'contacts/', $contacts, $hasMore) ?>
