<?php namespace ProcessWire;

require_once __DIR__ . '/resend.partials.php';
$sanitizer = wire()->sanitizer;
$section = $received ? 'received' : 'emails';
$label = $received ? 'Received' : 'Sent';
$counterpartLabel = $received ? 'Senders' : 'Recipients';
$statusCounts = [];
$counterparts = [];
$problem = 0;
$attachmentTotal = 0;
$withAttachments = 0;
$ccBccTotal = 0;
$replyToTotal = 0;
$latest = '';

foreach($emails as $e) {
	$created = isset($e['created_at']) ? strtotime((string) $e['created_at']) : false;
	if($created !== false && (!strlen($latest) || $created > strtotime($latest))) $latest = (string) $e['created_at'];
	if($received) {
		$attachments = isset($e['attachments']) && is_array($e['attachments']) ? $e['attachments'] : [];
		$attachmentTotal += count($attachments);
		if(count($attachments)) $withAttachments++;
		$ccBccTotal += count((array) ($e['cc'] ?? [])) + count((array) ($e['bcc'] ?? []));
		$replyToTotal += count((array) ($e['reply_to'] ?? []));
	} else {
		$status = (string) ($e['last_event'] ?? ($e['status'] ?? ''));
		if(!strlen($status)) $status = 'unknown';
		if(!isset($statusCounts[$status])) $statusCounts[$status] = 0;
		$statusCounts[$status]++;
		if(in_array($status, ['bounced', 'failed', 'complained', 'delivery_delayed', 'suppressed'], true)) $problem++;
	}
	$people = $received ? [$e['from'] ?? ''] : (array) ($e['to'] ?? []);
	foreach($people as $person) {
		$person = trim((string) $person);
		if(strlen($person)) $counterparts[$person] = true;
	}
}
arsort($statusCounts);
?>
<?= resendNav($adminUrl, $section) ?>

<div class="uk-grid-small uk-flex-bottom uk-margin-medium-bottom" uk-grid>
	<div class="uk-width-expand@m">
		<p class="uk-text-muted uk-margin-remove"><?= $received ? 'Inbound messages received on configured Resend domains appear here. Scan sender, routing, attachments and arrival time, then open a message to inspect the sandboxed content preview.' : 'Sent messages are listed with their latest delivery event. Scan status, recipient and subject, then open a message to inspect payload, preview content, attachments or scheduled actions.' ?></p>
	</div>
	<div class="uk-width-auto@m">
		<div class="uk-grid-medium uk-child-width-auto uk-flex-right@m" uk-grid>
			<div><a class="uk-button <?= $received ? 'uk-button-default' : 'uk-button-primary' ?>" style="text-decoration: none;" href="<?= $adminUrl ?>emails/"><?= resendE('Sent') ?></a></div>
			<div><a class="uk-button <?= $received ? 'uk-button-primary' : 'uk-button-default' ?>" style="text-decoration: none;" href="<?= $adminUrl ?>received/"><?= resendE('Received') ?></a></div>
		</div>
	</div>
</div>

<?= resendSummaryPanel([
	['label' => 'This Page', 'value' => count($emails), 'note' => $label . ' messages loaded.'],
	['label' => $counterpartLabel, 'value' => count($counterparts), 'note' => 'Unique ' . strtolower($counterpartLabel) . ' on this page.'],
	['label' => $received ? 'Attachments' : 'Needs Attention', 'value' => $received ? $attachmentTotal : $problem, 'note' => $received ? $withAttachments . ' messages include files.' : 'Bounces, delays, failures or complaints.'],
	['label' => $received ? 'Routing' : 'Latest', 'value' => $received ? ($ccBccTotal + $replyToTotal) : (strlen($latest) ? $process->formatDate($latest) : 'None'), 'note' => $received ? 'CC, BCC and reply-to values.' : ($hasMore ? 'More sent messages available below.' : 'Newest sent message on this page.')],
]) ?>

<?php if(!$received && count($statusCounts)): ?>
<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
	<h3><?= resendE('Status Mix') ?></h3>
	<div class="uk-grid-small uk-child-width-1-2@m" uk-grid="match: > div > .uk-card">
		<?php foreach($statusCounts as $status => $count): ?>
		<?php $pct = count($emails) ? (int) round($count / count($emails) * 100) : 0; ?>
		<div>
			<div class="uk-flex uk-flex-between uk-flex-middle uk-margin-small-bottom">
				<span><?= $process->badge($status) ?></span>
				<span class="uk-text-meta"><?= (int) $count ?> (<?= $pct ?>%)</span>
			</div>
			<progress class="uk-progress" value="<?= (int) $count ?>" max="<?= count($emails) ?>"></progress>
		</div>
		<?php endforeach; ?>
	</div>
</div>
<?php elseif($received && count($emails)): ?>
<?= resendSummaryPanel([
	['label' => 'Messages With Files', 'value' => $withAttachments, 'note' => 'Inbound messages with attachments.'],
	['label' => 'CC/BCC Entries', 'value' => $ccBccTotal, 'note' => 'Additional routed recipients.'],
	['label' => 'Reply-To Entries', 'value' => $replyToTotal, 'note' => 'Reply routing values.'],
]) ?>
<?php endif; ?>

<?php if(!count($emails)): ?>
	<div class="uk-card uk-card-default uk-card-body uk-margin-medium-bottom">
		<h3><?= $received ? 'No Received Emails' : 'No Sent Emails' ?></h3>
		<p><?= $received ? 'No inbound messages were returned for this page. Confirm inbound routing and MX records in Resend if you expected mail here.' : 'No sent messages were returned for this page. Send a test email to confirm the integration and populate this list.' ?></p>
		<?php if(!$received): ?><p><a class="uk-button uk-button-primary" style="text-decoration: none;" href="<?= $adminUrl ?>test/"><?= resendE('Send Test') ?></a></p><?php endif; ?>
	</div>
<?php elseif($received): ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr>
			<th><?= resendE('Message') ?></th>
			<th><?= resendE('From') ?></th>
			<th><?= resendE('To') ?></th>
			<th><?= resendE('Routing') ?></th>
			<th><?= resendE('Attachments') ?></th>
			<th><?= resendE('Date') ?></th>
			<th><?= resendE('Open') ?></th>
		</tr>
		<?php foreach($emails as $e): ?>
		<?php
			$id = (string) ($e['id'] ?? '');
			$from = (string) ($e['from'] ?? '');
			$to = implode(', ', (array) ($e['to'] ?? []));
			$cc = count((array) ($e['cc'] ?? []));
			$bcc = count((array) ($e['bcc'] ?? []));
			$replyTo = count((array) ($e['reply_to'] ?? []));
			$messageId = (string) ($e['message_id'] ?? '');
			$attachments = isset($e['attachments']) && is_array($e['attachments']) ? $e['attachments'] : [];
			$subject = (string) ($e['subject'] ?? '');
			if(!strlen($subject)) $subject = '(no subject)';
		?>
		<tr>
			<td>
				<a href="<?= $adminUrl ?>email/?id=<?= rawurlencode($id) ?>&received=1"><?= $sanitizer->entities($subject) ?></a>
				<div class="uk-text-meta uk-text-monospace"><?= $sanitizer->entities(strlen($messageId) ? $messageId : $id) ?></div>
			</td>
			<td><?= strlen($from) ? $sanitizer->entities($from) : '<span class="uk-text-muted">' . resendE('Not returned') . '</span>' ?></td>
			<td><?= strlen($to) ? $sanitizer->entities($to) : '<span class="uk-text-muted">' . resendE('Not returned') . '</span>' ?></td>
			<td>
				<span class="uk-text-meta">CC <?= $cc ?></span><br>
				<span class="uk-text-meta">BCC <?= $bcc ?></span><br>
				<span class="uk-text-meta">Reply-To <?= $replyTo ?></span>
			</td>
			<td>
				<?php if(count($attachments)): ?>
					<span class="uk-label"><?= count($attachments) ?></span>
					<?php if(isset($attachments[0]['filename'])): ?><div class="uk-text-meta"><?= $sanitizer->entities($attachments[0]['filename']) ?></div><?php endif; ?>
				<?php else: ?>
					<span class="uk-text-muted"><?= resendE('None') ?></span>
				<?php endif; ?>
			</td>
			<td><?= $process->formatDate($e['created_at'] ?? '') ?></td>
			<td><a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>email/?id=<?= rawurlencode($id) ?>&received=1"><?= resendE('View') ?></a></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php else: ?>
	<div class="uk-overflow-auto uk-margin-medium-bottom">
	<table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small">
		<tr>
			<th><?= resendE('Status') ?></th>
			<th><?= resendE('Message') ?></th>
			<th><?= resendE('Recipient') ?></th>
			<th><?= resendE('Sender') ?></th>
			<th><?= resendE('Date') ?></th>
			<th><?= resendE('Open') ?></th>
		</tr>
		<?php foreach($emails as $e): ?>
		<?php
			$id = (string) ($e['id'] ?? '');
			$status = (string) ($e['last_event'] ?? ($e['status'] ?? ''));
			if(!strlen($status)) $status = 'unknown';
			$from = (string) ($e['from'] ?? '');
			$to = implode(', ', (array) ($e['to'] ?? []));
			$subject = (string) ($e['subject'] ?? '');
			if(!strlen($subject)) $subject = '(no subject)';
		?>
		<tr>
			<td><?= $process->badge($status) ?></td>
			<td>
				<a href="<?= $adminUrl ?>email/?id=<?= rawurlencode($id) ?>&received=0"><?= $sanitizer->entities($subject) ?></a>
				<div class="uk-text-meta uk-text-monospace"><?= $sanitizer->entities($id) ?></div>
			</td>
			<td><?= $sanitizer->entities($to) ?></td>
			<td><?= strlen($from) ? $sanitizer->entities($from) : '<span class="uk-text-muted">' . resendE('Not returned') . '</span>' ?></td>
			<td><?= $process->formatDate($e['created_at'] ?? '') ?></td>
			<td><a class="uk-button uk-button-default uk-button-small" style="text-decoration: none;" href="<?= $adminUrl ?>email/?id=<?= rawurlencode($id) ?>&received=0"><?= resendE('View') ?></a></td>
		</tr>
		<?php endforeach; ?>
	</table>
	</div>
<?php endif; ?>

<?= resendPagination($adminUrl . $section . '/', $emails, $hasMore) ?>
