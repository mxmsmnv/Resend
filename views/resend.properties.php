<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$actionUrl = $adminUrl . 'properties/';
$typeCounts = [];
$withFallback = 0;

foreach($types as $type) $typeCounts[$type] = 0;
foreach($properties as $p) {
	$type = (string) ($p['type'] ?? 'string');
	if(!isset($typeCounts[$type])) $typeCounts[$type] = 0;
	$typeCounts[$type]++;
	if(array_key_exists('fallback_value', $p) && strlen((string) ($p['fallback_value'] ?? ''))) $withFallback++;
}
?>
<?= resendNav($adminUrl, 'properties') ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove">Contact properties define reusable fields for templates and audience personalization. The property key becomes the template variable name, while fallback values keep templates readable when a contact has no value set.</p>
	</div>
	<div class="uk-width-auto@m">
		<div class="uk-grid-medium uk-child-width-auto uk-flex-right@m" uk-grid>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>contacts/">Contacts</a></div>
			<div><a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>templates/">Templates</a></div>
		</div>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'Properties', 'value' => count($properties), 'note' => 'Custom fields defined.'],
	['label' => 'Fallbacks', 'value' => $withFallback, 'note' => 'Properties with defaults.'],
	['label' => 'Types', 'value' => count(array_filter($typeCounts)), 'note' => 'Types currently in use.'],
	['label' => 'Template Use', 'value' => 'Variables', 'note' => 'Keys are available in templates.'],
]) ?>

<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
	<div class="uk-grid-medium uk-grid-divider" uk-grid>
		<div class="uk-width-1-3@l">
			<h3 class="uk-margin-remove-top">Create Property</h3>
			<form class="uk-form-stacked" method="post" action="<?= $actionUrl ?>">
				<?= resendCsrf($csrf) ?>
				<input type="hidden" name="action" value="create">
				<div class="uk-margin-small-bottom">
					<label class="uk-form-label">Key</label>
					<input class="uk-input" type="text" name="key" placeholder="plan" required>
				</div>
				<div class="uk-margin-small-bottom">
					<label class="uk-form-label">Type</label>
					<select class="uk-select" name="type">
						<?php foreach($types as $type): ?>
						<option value="<?= $type ?>"><?= $type ?></option>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="uk-margin-small-bottom">
					<label class="uk-form-label">Fallback Value</label>
					<input class="uk-input" type="text" name="fallback_value" placeholder="optional">
				</div>
				<p class="uk-margin-remove-bottom"><button type="submit" class="uk-button uk-button-primary">Create Property</button></p>
			</form>
		</div>
		<div class="uk-width-2-3@l">
			<h3 class="uk-margin-remove-top">Type Guide</h3>
			<div class="uk-grid-medium uk-child-width-1-2@m" uk-grid>
				<div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">String</p><p class="uk-margin-small-top">Names, plans, cities and labels.</p></div>
				<div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">Number</p><p class="uk-margin-small-top">Counts, scores or numeric tiers.</p></div>
				<div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">Boolean</p><p class="uk-margin-small-top">Feature flags and yes/no states.</p></div>
				<div><p class="uk-text-meta uk-text-uppercase uk-margin-remove">Date</p><p class="uk-margin-small-top">Renewals, bookings and lifecycle dates.</p></div>
			</div>
			<?= resendNote("Choose stable keys such as <code>plan</code> or <code>check_in_date</code>. Renaming a key later means updating templates that reference it.") ?>
		</div>
	</div>
</div>

<?php if(!count($properties)): ?>
	<div class="uk-card uk-card-default uk-card-body uk-card-small uk-margin-medium-bottom">
		<div class="uk-grid-medium uk-flex-middle" uk-grid>
			<div class="uk-width-expand@m">
				<h3 class="uk-margin-remove-top">No Contact Properties Yet</h3>
				<p class="uk-margin-remove-bottom">Create fields before using them in templates. Start with values you personalize often, such as plan, city or booking dates.</p>
			</div>
			<div class="uk-width-auto@m">
				<a class="uk-button uk-button-default" style="text-decoration: none;" href="<?= $adminUrl ?>templates/">Open Templates</a>
			</div>
		</div>
	</div>
<?php else: ?>
	<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
		<h3>Type Mix</h3>
		<div class="uk-grid-small uk-child-width-1-2@m" uk-grid="match: > div > .uk-card">
			<?php foreach($typeCounts as $type => $count): ?>
			<?php $pct = count($properties) ? (int) round($count / count($properties) * 100) : 0; ?>
			<div>
				<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
					<span class="uk-label"><?= $sanitizer->entities($type) ?></span>
					<span class="uk-text-meta"><?= (int) $count ?> (<?= $pct ?>%)</span>
				</div>
				<progress class="uk-progress" value="<?= (int) $count ?>" max="<?= count($properties) ?>"></progress>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr><th>Property</th><th>Type</th><th>Fallback</th><th>Template Variable</th><th>ID</th><th>Actions</th></tr>
		<?php foreach($properties as $p): ?>
		<?php
			$key = (string) ($p['key'] ?? '');
			$fallback = $p['fallback_value'] ?? '';
			$fallbackText = is_scalar($fallback) ? (string) $fallback : resendJson($fallback);
		?>
		<tr>
			<td class="uk-text-monospace"><?= $sanitizer->entities($key) ?></td>
			<td><span class="uk-label"><?= $sanitizer->entities($p['type'] ?? '') ?></span></td>
			<td><?= strlen($fallbackText) ? $sanitizer->entities($fallbackText) : '<span class="uk-text-muted">None</span>' ?></td>
			<td class="uk-text-monospace">{{<?= $sanitizer->entities($key) ?>}}</td>
			<td class="uk-text-monospace"><?= $sanitizer->entities($p['id'] ?? '') ?></td>
			<td><?= resendActionForm($actionUrl, ['action' => 'delete', 'id' => $p['id']], 'Delete', $csrf, 'danger', 'Delete this contact property? Templates using this key may need updates.') ?></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php endif; ?>
