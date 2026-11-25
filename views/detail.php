<?php

namespace ProcessWire;

/**
 * @var array $entry
 * @var Page $adminPage
 * @var Config $config
 * @var WireDateTime $datetime
 */

$date = $datetime->formatDate((int) $entry['created'], $config->dateFormat);
$statusColor = $entry['status'] === 'sent' ? '#2ecc71' : '#e74c3c';
$statusLabel = $entry['status'] === 'sent'
		? __('Sent')
		: __('Failed')
;
$iframeUrl = $adminPage->url . 'iframe/?id=' . (int) $entry['id'];

?>
<div style="display:grid;grid-template-columns:340px 1fr;gap:20px;align-items:start;">

	<div>
		<table class="uk-table uk-table-divider uk-table-small">
			<tbody>
				<tr>
					<th><?= __('Date') ?></th>
					<td><?= $date ?></td>
				</tr>
				<tr>
					<th><?= __('Recipient') ?></th>
					<td><?= sanitizer()->entities($entry['to_email']) ?></td>
				</tr>
				<tr>
					<th><?= __('From') ?></th>
					<td><?= sanitizer()->entities($entry['from_email']) ?></td>
				</tr>
				<tr>
					<th><?= __('Subject') ?></th>
					<td><?= sanitizer()->entities($entry['subject']) ?></td>
				</tr>
				<tr>
					<th><?= __('Template') ?></th>
					<td><?= sanitizer()->entities($entry['template']) ?></td>
				</tr>
				<tr>
					<th><?= __('Status') ?></th>
					<td>
						<span style="display:inline-block;padding:2px 8px;border-radius:3px;font-size:.82em;background:<?= $statusColor ?>;color:#fff;">
							<?= $statusLabel ?>
						</span>
					</td>
				</tr>
			</tbody>
		</table>

		<form method="post" action="<?= $adminPage->url ?>resend/" style="display:inline;">
			<input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
			<button type="submit" name="submit_resend" value="1"
			        class="ui-button ui-state-default"
			        onclick="return confirm('<?= __('Resend this email?') ?>')">
				<i class="fa fa-repeat"></i> <?= __('Resend') ?>
			</button>
		</form>
	</div>

	<div>
		<iframe id="email-detail-iframe"
		        src="<?= $iframeUrl ?>"
		        style="width:100%;border:1px solid #ddd;border-radius:4px;background:#fff;"
		        scrolling="no"
		        onload="this.style.height=this.contentDocument.documentElement.scrollHeight+'px'"></iframe>
	</div>

</div>
