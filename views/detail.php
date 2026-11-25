<?php

namespace ProcessWire;

/**
 * Detail of a logged email: data on the left, the email with a toolbar
 * (format, device) on the right. Same layout as the template preview.
 *
 * @var array $entry Row of the email log
 * @var array{format: string, size: string} $state
 * @var array<string, string> $sizes Preview widths by size name
 * @var array $templates Registered templates
 * @var string $iframeUrl
 * @var ProcessEmailLog $process
 * @var Page $adminPage
 * @var Config $config
 * @var WireDateTime $datetime
 * @var Sanitizer $sanitizer
 */

$isSent = $entry['status'] === 'sent';
$templateName = (string) $entry['template'];
$templateLabel = $templates[$templateName]['label'] ?? '';
$languageLabel = $process->getLanguageLabel((string) ($entry['language'] ?? ''));
// confirm texts for ProcessWire.confirm (ProcessEmailLog.js), __() returns them HTML-encoded
$resendConfirm = html_entity_decode(__('Resend this email?'), ENT_QUOTES, 'UTF-8');
$deleteConfirm = html_entity_decode(__('Delete this email log entry?'), ENT_QUOTES, 'UTF-8');

$fields = [
	__('Date') => $sanitizer->entities($datetime->formatDate((int) $entry['created'], $config->dateFormat)),
	__('Recipient') => $sanitizer->entities($entry['to_email']),
	__('From') => $sanitizer->entities($entry['from_email']),
	__('Subject') => $sanitizer->entities($entry['subject']),
	__('Template') => $templateName === ''
		? '<span class="uk-text-muted">' . __('(no template)') . '</span>'
		: ($templateLabel !== ''
			? $sanitizer->entities($templateLabel) . ' <span class="uk-text-muted">(' . $sanitizer->entities($templateName) . ')</span>'
			: $sanitizer->entities($templateName)),
	__('Language') => $languageLabel !== ''
		? $sanitizer->entities($languageLabel)
		: '<span class="uk-text-muted">–</span>',
];

// the language of a logged email can't be switched
$languages = [];

?>
<div id="email-preview" class="email-ui uk-grid-medium" uk-grid
     data-email-preview
     data-state="<?= $sanitizer->entities(json_encode($state)) ?>"
     data-sizes="<?= $sanitizer->entities(json_encode($sizes)) ?>">

	<div class="uk-width-1-1 uk-width-1-3@m uk-width-1-4@xl">
		<div class="email-bar uk-flex uk-flex-middle uk-margin-small-bottom">
			<h3 class="email-bar-label uk-margin-remove"><?= __('Details') ?></h3>
			<span class="email-status uk-label <?= $isSent ? 'uk-label-success' : 'uk-label-danger' ?> uk-margin-auto-left">
				<?= $isSent ? __('Sent') : __('Failed') ?>
			</span>
		</div>

		<div class="uk-card uk-card-default uk-card-small uk-card-body">
			<dl class="email-meta uk-description-list uk-description-list-divider uk-margin-remove">
				<?php foreach ($fields as $label => $value): ?>
					<dt><?= $label ?></dt>
					<dd><?= $value ?></dd>
				<?php endforeach; ?>
			</dl>
		</div>

		<div class="email-actions uk-flex uk-flex-middle uk-flex-wrap uk-margin-small-top">
			<form method="post" action="<?= $adminPage->url ?>resend/" data-confirm="<?= $sanitizer->entities($resendConfirm) ?>">
				<input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
				<?= session()->CSRF->renderInput() ?>
				<button type="submit" name="submit_resend" value="1"
				        class="uk-button uk-button-default uk-button-small">
					<span uk-icon="icon: refresh; ratio: .8"></span>
					<?= __('Resend') ?>
				</button>
			</form>

			<?php if (user()->isSuperuser()): ?>
				<form method="post" action="<?= $adminPage->url ?>delete-entry/" data-confirm="<?= $sanitizer->entities($deleteConfirm) ?>">
					<input type="hidden" name="id" value="<?= (int) $entry['id'] ?>">
					<?= session()->CSRF->renderInput() ?>
					<button type="submit" name="submit_delete_entry" value="1"
					        class="email-delete uk-button uk-button-default uk-button-small">
						<span uk-icon="icon: trash; ratio: .8"></span>
						<?= __('Delete') ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
	</div>

	<div class="uk-width-expand">

		<div class="email-bar email-toolbar uk-flex uk-flex-middle uk-flex-wrap uk-margin-small-bottom">
			<?php include __DIR__ . '/_toolbar.php'; ?>
		</div>

		<div class="uk-flex uk-flex-center">
			<div id="email-preview-frame" class="uk-card uk-card-default"
			     style="width:<?= $sanitizer->entities($sizes[$state['size']]) ?>;max-width:100%;overflow:hidden;transition:width .2s ease;">
				<iframe id="email-preview-iframe"
				        src="<?= $sanitizer->entities($iframeUrl) ?>"
				        title="<?= $sanitizer->entities($entry['subject']) ?>"
				        style="display:block;width:100%;height:600px;border:0;background:#fff;"
				        scrolling="no"></iframe>
			</div>
		</div>

	</div>

</div>
