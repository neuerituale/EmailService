<?php

namespace ProcessWire;

/**
 * Email log overview: toolbar with filters and actions, list of emails.
 * Same look as the template preview.
 *
 * @var array $entries
 * @var string $filterStatus
 * @var string $filterTemplate
 * @var array $templates Registered templates
 * @var string[] $usedTemplates Distinct template values from email_log
 * @var Language[] $languages Languages indexed by id, empty without LanguageSupport
 * @var PaginatedArray $pager
 * @var int $total
 * @var ProcessEmailLog $process
 * @var Page $adminPage
 * @var Config $config
 * @var WireDateTime $datetime
 * @var Sanitizer $sanitizer
 */

$isFiltered = $filterStatus !== '' || $filterTemplate !== '';
$showLanguage = count($languages) > 1;

$filterUrl = function(array $params) use ($adminPage, $filterStatus, $filterTemplate): string {
	$query = http_build_query(array_filter(array_merge(['status' => $filterStatus, 'template' => $filterTemplate], $params)));
	return $adminPage->url . ($query !== '' ? "?$query" : '');
};

$statuses = [
	'' => __('All'),
	'sent' => __('Sent'),
	'failed' => __('Failed'),
];

?>
<div class="email-ui">

	<div class="email-bar email-toolbar uk-flex uk-flex-middle uk-flex-wrap uk-margin-small-bottom">

		<ul class="uk-subnav uk-subnav-pill uk-margin-remove-vertical" aria-label="<?= __('Status') ?>">
			<?php foreach ($statuses as $status => $label): ?>
				<li<?= $filterStatus === $status ? ' class="uk-active"' : '' ?>>
					<a href="<?= $sanitizer->entities($filterUrl(['status' => $status])) ?>"><?= $label ?></a>
				</li>
			<?php endforeach; ?>
		</ul>

		<form id="email-log-filter" method="get" action="<?= $adminPage->url ?>" class="uk-flex uk-flex-middle">
			<?php if ($filterStatus !== ''): ?>
				<input type="hidden" name="status" value="<?= $sanitizer->entities($filterStatus) ?>">
			<?php endif; ?>
			<span class="uk-text-muted uk-margin-small-right" uk-icon="icon: file-edit; ratio: .9" uk-tooltip="<?= __('Template') ?>"></span>
			<select name="template" class="uk-select uk-form-small uk-form-width-medium" aria-label="<?= __('Template') ?>" onchange="this.form.submit()">
				<option value=""><?= __('All templates') ?></option>
				<?php foreach ($usedTemplates as $tplKey):
					$value = $tplKey === '' ? '_none' : $tplKey;
					$label = isset($templates[$tplKey])
						? $templates[$tplKey]['label']
						: ($tplKey === '' ? __('(no template)') : $tplKey);
				?>
					<option value="<?= $sanitizer->entities($value) ?>"<?= $filterTemplate === $value ? ' selected' : '' ?>>
						<?= $sanitizer->entities($label) ?>
					</option>
				<?php endforeach; ?>
			</select>
			<?php if ($isFiltered): ?>
				<a href="<?= $adminPage->url ?>" class="email-reset uk-margin-small-left" uk-icon="icon: close; ratio: .8"
				   uk-tooltip="<?= __('Reset') ?>" aria-label="<?= __('Reset') ?>"></a>
			<?php endif; ?>
		</form>

		<div class="email-actions uk-flex uk-flex-middle uk-margin-auto-left">
			<a href="<?= $adminPage->url ?>preview/" class="uk-button uk-button-default uk-button-small">
				<span uk-icon="icon: eye; ratio: .8"></span>
				<?= __('Template Preview') ?>
			</a>

			<?php if (user()->isSuperuser() && count($entries)):
				/** Delete respects the active filter; label reflects the scope. */
				$deleteLabel = $isFiltered
					? __('Delete filtered logs')
					: __('Delete all logs');
				$deleteConfirm = $isFiltered
					? __('Delete all email log entries matching the current filter?')
					: __('Delete all email log entries?');
				$deleteConfirm = html_entity_decode($deleteConfirm, ENT_QUOTES, 'UTF-8');
			?>
				<form method="post" action="<?= $adminPage->url ?>delete/" class="uk-display-inline"
				      data-confirm="<?= $sanitizer->entities($deleteConfirm) ?>">
					<input type="hidden" name="submit_delete" value="1">
					<input type="hidden" name="status" value="<?= $sanitizer->entities($filterStatus) ?>">
					<input type="hidden" name="template" value="<?= $sanitizer->entities($filterTemplate) ?>">
					<?= session()->CSRF->renderInput() ?>
					<button type="submit" class="email-delete uk-button uk-button-default uk-button-small">
						<span uk-icon="icon: trash; ratio: .8"></span>
						<?= $sanitizer->entities($deleteLabel) ?>
					</button>
				</form>
			<?php endif; ?>
		</div>

	</div>

	<?php if (!count($entries)): ?>

		<div class="uk-card uk-card-default uk-card-body">
			<div class="uk-placeholder uk-text-center uk-text-muted uk-margin-remove">
				<?= __('No emails found.') ?>
			</div>
		</div>

	<?php else: ?>

		<div class="uk-card uk-card-default uk-overflow-auto">
			<table class="email-log-table uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small uk-margin-remove">
				<thead>
					<tr>
						<th><?= __('Date') ?></th>
						<th><?= __('Recipient') ?></th>
						<th><?= __('Subject') ?></th>
						<th><?= __('Template') ?></th>
						<?php if ($showLanguage): ?>
							<th><?= __('Language') ?></th>
						<?php endif; ?>
						<th class="uk-text-right"><?= __('Status') ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ($entries as $entry):
						$detailUrl = $adminPage->url . 'detail/?id=' . (int) $entry['id'];
						$isSent = $entry['status'] === 'sent';
						$templateLabel = $templates[$entry['template']]['label'] ?? ($entry['template'] ?: '–');
					?>
						<tr>
							<td class="uk-text-nowrap uk-text-muted"><?= $sanitizer->entities($datetime->formatDate((int) $entry['created'], $config->dateFormat)) ?></td>
							<td class="uk-text-nowrap"><a href="<?= $detailUrl ?>"><?= $sanitizer->entities($entry['to_email']) ?></a></td>
							<td><a href="<?= $detailUrl ?>"><?= $sanitizer->entities($entry['subject']) ?></a></td>
							<td class="uk-text-nowrap"><?= $sanitizer->entities($templateLabel) ?></td>
							<?php if ($showLanguage): ?>
								<td class="uk-text-nowrap"><?= $sanitizer->entities($process->getLanguageLabel((string) ($entry['language'] ?? ''))) ?: '–' ?></td>
							<?php endif; ?>
							<td class="uk-text-right">
								<span class="email-status uk-label <?= $isSent ? 'uk-label-success' : 'uk-label-danger' ?>">
									<?= $isSent ? __('Sent') : __('Failed') ?>
								</span>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>

		<div class="uk-flex uk-flex-middle uk-flex-between uk-flex-wrap uk-margin-small-top" style="gap:8px;">
			<span class="uk-text-muted uk-text-small"><?= sprintf(__('%d emails'), $total) ?></span>
			<?php $query = http_build_query(array_filter(['status' => $filterStatus, 'template' => $filterTemplate])); ?>
			<?= $pager->renderPager([
				'baseUrl' => $adminPage->url,
				'queryString' => $query !== '' ? "?$query" : '',
			]) ?>
		</div>

	<?php endif; ?>

</div>
