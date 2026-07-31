<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'topics/';
?>
<?= resendNav($adminUrl, 'topics') ?>
<?= resendIntro('topics') ?>

<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
	<h3><?= resendE('Create Topic') ?></h3>
	<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
		<?= resendCsrf($csrf) ?>
		<input type="hidden" name="action" value="create">
		<div class="uk-grid-small uk-flex-bottom" uk-grid>
			<div class="uk-width-1-4@m"><label class="uk-form-label"><?= resendE('Name') ?></label><input class="uk-input" type="text" name="name" required></div>
			<div class="uk-width-1-3@m"><label class="uk-form-label"><?= resendE('Description') ?></label><input class="uk-input" type="text" name="description"></div>
			<div class="uk-width-1-4@m">
				<label class="uk-form-label"><?= resendE('Default Subscription') ?></label>
				<select class="uk-select" name="default_subscription">
					<option value="opt-in"><?= resendE('opt-in') ?></option>
					<option value="opt-out"><?= resendE('opt-out') ?></option>
				</select>
			</div>
			<div class="uk-width-auto@m"><button type="submit" class="uk-button uk-button-primary"><?= resendE('Create') ?></button></div>
		</div>
	</form>
	<?= resendNote("Default subscription defines the starting state: <code>opt-in</code> topics require contacts to subscribe explicitly, <code>opt-out</code> topics include everyone until they unsubscribe.") ?>
</div>

<div class="uk-overflow-auto uk-margin-medium-bottom">
<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
	<tr><th><?= resendE('Name') ?></th><th><?= resendE('Description') ?></th><th><?= resendE('Default') ?></th><th><?= resendE('ID') ?></th><th><?= resendE('Actions') ?></th></tr>
	<?php foreach($topics as $t): ?>
	<tr>
		<td><?= $sanitizer->entities($t['name'] ?? '') ?></td>
		<td><?= $sanitizer->entities($t['description'] ?? '') ?></td>
		<td><?= $sanitizer->entities($t['default_subscription'] ?? '') ?></td>
		<td class="uk-text-monospace"><?= $sanitizer->entities($t['id'] ?? '') ?></td>
		<td><?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $t['id']], 'Delete', $csrf, 'danger', 'Delete this topic?') ?></td>
	</tr>
	<?php endforeach; ?>
	<?php if(!count($topics)): ?><?= resendEmptyRow(5, 'No topics yet. Create topics to manage subscription preferences by mail type.') ?><?php endif; ?>
</table>
</div>
