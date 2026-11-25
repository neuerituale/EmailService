<?php

namespace ProcessWire;

/**
 * Template preview: template list on the left, preview with a toolbar
 * (format, language, device) on the right.
 *
 * @var array $templates Registered templates
 * @var Language[] $languages Languages indexed by id, empty without LanguageSupport
 * @var array{template: string, format: string, size: string, language: int} $state
 * @var array<string, string> $sizes Preview widths by size name
 * @var bool $canRender Whether the selected template renders
 * @var bool $hasTextTemplate Whether the selected template has a .txt.php version
 * @var ProcessEmailLog $process
 * @var Page $adminPage
 * @var Sanitizer $sanitizer
 */

?>
<?php if (!count($templates)): ?>

	<div class="uk-card uk-card-default uk-margin">
		<div class="uk-card-body uk-text-muted uk-text-center">
			<?= __('No templates registered. Use the EmailService::registerTemplates hook to register templates with sample data.') ?>
		</div>
	</div>

<?php else: ?>

	<div id="email-preview" class="email-ui uk-grid-medium" uk-grid
	     data-email-preview
	     data-state="<?= $sanitizer->entities(json_encode(['format' => $state['format'], 'size' => $state['size'], 'language' => $state['language']])) ?>"
	     data-sizes="<?= $sanitizer->entities(json_encode($sizes)) ?>"
	     data-has-text="<?= $hasTextTemplate ? '1' : '0' ?>">

		<div class="uk-width-1-1 uk-width-1-4@m uk-width-1-5@l">
			<div class="email-bar uk-flex uk-flex-middle uk-margin-small-bottom">
				<h3 class="email-bar-label uk-margin-remove"><?= __('Templates') ?></h3>
				<span class="email-bar-count uk-badge uk-margin-small-left"><?= count($templates) ?></span>
			</div>
			<div class="uk-card uk-card-default uk-card-small uk-card-body">
				<ul class="uk-nav uk-nav-default">
					<?php foreach ($templates as $key => $tplInfo):
						$active = $state['template'] === $key;
						$url = $process->getPreviewUrl(array_merge($state, ['template' => $key]));
					?>
						<li<?= $active ? ' class="uk-active"' : '' ?>>
							<a href="<?= $sanitizer->entities($url) ?>" class="email-preview-template"<?= $active ? ' aria-current="page"' : '' ?>>
								<?= $sanitizer->entities($tplInfo['label']) ?>
							</a>
						</li>
					<?php endforeach; ?>
				</ul>
			</div>
		</div>

		<div class="uk-width-expand">

			<div class="email-bar email-toolbar uk-flex uk-flex-middle uk-flex-wrap uk-margin-small-bottom">

				<?php include __DIR__ . '/_toolbar.php'; ?>

			</div>

			<?php if ($state['template'] !== '' && $canRender): ?>

				<div id="email-preview-text-hint" class="uk-alert uk-alert-primary uk-text-small uk-padding-small uk-margin-small-bottom"<?= $hasTextTemplate || $state['format'] !== 'text' ? ' hidden' : '' ?>>
					<?= __('This template has no text version (.txt.php). The text part is generated from the HTML.') ?>
				</div>

				<?php /* the box takes the width of the selected device */ ?>
				<div class="uk-flex uk-flex-center">
					<div id="email-preview-frame" class="uk-card uk-card-default"
					     style="width:<?= $sanitizer->entities($sizes[$state['size']]) ?>;max-width:100%;overflow:hidden;transition:width .2s ease;">
						<iframe id="email-preview-iframe"
						        src="<?= $sanitizer->entities($process->getIframeUrl($state)) ?>"
						        title="<?= $sanitizer->entities($templates[$state['template']]['label']) ?>"
						        style="display:block;width:100%;height:600px;border:0;background:#fff;"
						        scrolling="no"></iframe>
					</div>
				</div>

			<?php elseif ($state['template'] !== ''): ?>

				<div class="uk-alert uk-alert-warning uk-margin-remove">
					<?= __('Could not render template. Check that the template file exists and sample data is valid.') ?>
				</div>

			<?php else: ?>

				<div class="uk-card uk-card-default uk-card-body">
					<div class="uk-placeholder uk-text-center uk-text-muted uk-margin-remove">
						<?= __('Select a template to preview.') ?>
					</div>
				</div>

			<?php endif; ?>

		</div>

	</div>

<?php endif; ?>
