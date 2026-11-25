<?php

namespace ProcessWire;

/**
 * @var array $entries
 * @var string $filterStatus
 * @var string $filterTemplate
 * @var array $templates Registered templates
 * @var string[] $usedTemplates Distinct template values from email_log
 * @var PaginatedArray $pager
 * @var int $total
 * @var Page $adminPage
 * @var Config $config
 * @var WireDateTime $datetime
 */

?>
<div class="uk-flex uk-flex-middle uk-margin" style="gap:8px;">
	<form id="email-log-filter" method="get" action="<?= $adminPage->url ?>" class="uk-flex uk-flex-middle" style="gap:8px;">
		<select name="status" class="uk-select" style="width:auto;" onchange="this.form.submit()">
			<option value=""><?= __('All statuses') ?></option>
			<option value="sent"<?= $filterStatus === 'sent' ? ' selected' : '' ?>><?= __('Sent') ?></option>
			<option value="failed"<?= $filterStatus === 'failed' ? ' selected' : '' ?>><?= __('Failed') ?></option>
		</select>

		<select name="template" class="uk-select" style="width:auto;" onchange="this.form.submit()">
			<option value=""><?= __('All templates') ?></option>
			<?php foreach ($usedTemplates as $tplKey): ?>
				<?php
				$value = $tplKey === '' ? '_none' : $tplKey;
				$label = isset($templates[$tplKey])
					? $templates[$tplKey]['label']
					: ($tplKey === '' ? __('(no template)') : $tplKey);
				?>
				<option value="<?= sanitizer()->entities($value) ?>"<?= $filterTemplate === $value ? ' selected' : '' ?>>
					<?= sanitizer()->entities($label) ?>
				</option>
			<?php endforeach; ?>
		</select>

		<?php if ($filterStatus || $filterTemplate): ?>
			<a href="<?= $adminPage->url ?>" class="uk-text-muted uk-margin-small-left">
				<span uk-icon="icon: close; ratio: 0.8"></span> <?= __('Reset') ?>
			</a>
		<?php endif; ?>
	</form>

	<div class="uk-margin-auto-left">
		<a href="<?= $adminPage->url ?>preview/" class="ui-button ui-state-default">
			<i class="fa fa-eye"></i> <?= __('Template Preview') ?>
		</a>

		<?php if (user()->isSuperuser() && count($entries)): ?>
			<?php
			/** Delete respects the active filter; label reflects the scope. */
			$deleteLabel = ($filterStatus || $filterTemplate) ? __('Delete filtered logs') : __('Delete all logs');
			$deleteConfirm = ($filterStatus || $filterTemplate)
				? __('Delete all email log entries matching the current filter?')
				: __('Delete all email log entries?');
			?>
			<form method="post" action="<?= $adminPage->url ?>delete/" class="uk-display-inline"
				onsubmit="if(window.ProcessWire&&ProcessWire.confirm){ProcessWire.confirm('<?= sanitizer()->entities($deleteConfirm) ?>',()=>this.submit());return false;}return confirm('<?= sanitizer()->entities($deleteConfirm) ?>');">
				<input type="hidden" name="submit_delete" value="1">
				<input type="hidden" name="status" value="<?= sanitizer()->entities($filterStatus) ?>">
				<input type="hidden" name="template" value="<?= sanitizer()->entities($filterTemplate) ?>">
				<input type="hidden" name="<?= session()->CSRF->getTokenName() ?>" value="<?= session()->CSRF->getTokenValue() ?>">
				<button type="submit" class="ui-button ui-state-default">
					<i class="fa fa-trash"></i> <?= sanitizer()->entities($deleteLabel) ?>
				</button>
			</form>
		<?php endif; ?>
	</div>
</div>

<?php if (!count($entries)): ?>

	<div class="uk-card uk-card-default uk-margin">
		<div class="uk-card-body uk-text-lead uk-text-muted uk-text-center">
			<?= __('No emails found.') ?>
		</div>
	</div>

<?php else: ?>

	<?php
	/** @var MarkupAdminDataTable $table */
	$table = modules()->get('MarkupAdminDataTable');
	$table->setEncodeEntities(false);
	$table->setSortable(false);
	$table->addClass('uk-table-middle');
	$table->headerRow([
		__('Date'),
		__('Recipient'),
		__('Subject'),
		__('Template'),
		__('Status'),
	]);

	foreach ($entries as $entry) {
		$date = $datetime->formatDate((int) $entry['created'], $config->dateFormat);
		$detailUrl = $adminPage->url . 'detail/?id=' . (int) $entry['id'];

		$statusColor = $entry['status'] === 'sent' ? '#2ecc71' : '#e74c3c';
		$statusLabel = $entry['status'] === 'sent' ? __('Sent') : __('Failed');
		$badge = '<span style="display:inline-block;padding:2px 8px;border-radius:3px;'
			. 'font-size:.82em;background:' . $statusColor . ';color:#fff;white-space:nowrap;">'
			. $statusLabel . '</span>';

		$table->row([
			'<span style="white-space:nowrap;">' . $date . '</span>',
			'<a href="' . $detailUrl . '">' . sanitizer()->entities($entry['to_email']) . '</a>',
			sanitizer()->entities($entry['subject']),
			'<span style="white-space:nowrap;">' . sanitizer()->entities($templates[$entry['template']]['label'] ?? ($entry['template'] ?: '–')) . '</span>',
			$badge,
		]);
	}

	echo $table->render();
	?>

	<?php
	$queryString = '';
	$params = [];
	if ($filterStatus) $params['status'] = $filterStatus;
	if ($filterTemplate) $params['template'] = $filterTemplate;
	if ($params) $queryString = '?' . http_build_query($params);
	?>

	<?= $pager->renderPager([
		'baseUrl' => $adminPage->url,
		'queryString' => $queryString
	]) ?>

	<p class="uk-text-muted uk-text-small uk-margin-small-top">
		<?= sprintf(__('%d emails'), $total) ?>
	</p>

<?php endif; ?>
