<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
?>
<?= resendNav($adminUrl, 'logs') ?>
<?= resendIntro('logs') ?>

<div class="uk-overflow-auto uk-margin-medium-bottom">
<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
	<tr><th><?= resendE('Method') ?></th><th><?= resendE('Path') ?></th><th><?= resendE('Status') ?></th><th><?= resendE('Date') ?></th><th><?= resendE('ID') ?></th></tr>
	<?php foreach($logs as $l): ?>
	<?php $status = $l['response_status'] ?? ($l['status_code'] ?? ($l['status'] ?? '')); ?>
	<tr>
		<td><?= $sanitizer->entities($l['method'] ?? '') ?></td>
		<td class="uk-text-monospace"><?= $sanitizer->entities($l['path'] ?? ($l['endpoint'] ?? '')) ?></td>
		<td><?= $sanitizer->entities((string) $status) ?></td>
		<td><?= $process->formatDate($l['created_at'] ?? '') ?></td>
		<td class="uk-text-monospace"><?= $sanitizer->entities($l['id'] ?? '') ?></td>
	</tr>
	<?php endforeach; ?>
	<?php if(!count($logs)): ?><?= resendEmptyRow(5, 'No API logs were returned for this page.') ?><?php endif; ?>
</table>
</div>

<?= resendPagination($adminUrl . 'logs/', $logs, $hasMore) ?>
