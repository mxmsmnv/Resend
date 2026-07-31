<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'template/?id=' . rawurlencode($id);
?>
<?= resendNav($adminUrl, 'templates') ?>
<?= resendIntro('template') ?>

<?php if(!count($template)): ?>
	<?= resendEmpty('Template not found.') ?>
<?php else: ?>

<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
<dl class="uk-description-list uk-description-list-divider uk-margin-remove-bottom">
	<dt><?= resendE('ID') ?></dt><dd class="uk-text-monospace"><?= $sanitizer->entities($template['id'] ?? '') ?></dd>
	<dt><?= resendE('Status') ?></dt><dd><?= $process->badge($template['status'] ?? '') ?></dd>
	<dt><?= resendE('Created') ?></dt><dd><?= $process->formatDate($template['created_at'] ?? '') ?></dd>
</dl>
</div>

<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
	<h3><?= resendE('Edit Template') ?></h3>
	<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
		<?= resendCsrf($csrf) ?>
		<input type="hidden" name="action" value="update">
		<div class="uk-grid-small" uk-grid>
			<div class="uk-width-1-3@m"><label class="uk-form-label"><?= resendE('Name') ?></label><input class="uk-input" type="text" name="name" value="<?= $sanitizer->entities($template['name'] ?? '') ?>" required></div>
			<div class="uk-width-1-3@m"><label class="uk-form-label"><?= resendE('Alias') ?></label><input class="uk-input" type="text" name="alias" value="<?= $sanitizer->entities($template['alias'] ?? '') ?>"></div>
			<div class="uk-width-1-3@m"><label class="uk-form-label"><?= resendE('Subject') ?></label><input class="uk-input" type="text" name="subject" value="<?= $sanitizer->entities($template['subject'] ?? '') ?>"></div>
		</div>
		<div class="uk-margin">
			<label class="uk-form-label"><?= resendE('HTML Body') ?></label>
			<textarea class="uk-textarea" name="html" rows="12" required><?= $sanitizer->entities($template['html'] ?? '') ?></textarea>
		</div>
		<div class="uk-margin">
			<button type="submit" class="uk-button uk-button-primary"><?= resendE('Save') ?></button>
			<?= resendActionForm($actionUrl, ['action' => 'publish'], 'Publish', $csrf, 'secondary') ?>
		</div>
	</form>
	<?= resendNote("Saving creates a new draft version; only published versions can be sent. Variables use the <code>{{{NAME}}}</code> syntax; reserved names: FIRST_NAME, LAST_NAME, EMAIL, UNSUBSCRIBE_URL.") ?>
	<?= resendNote("Review rendered output in Resend or with a test send before publishing changes used by production workflows.") ?>
</div>

<?php endif; ?>
