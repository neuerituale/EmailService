<?php

namespace ProcessWire;

/**
 * Toolbar controls of the email preview: format, language (if $languages has
 * more than one entry) and device. Included by preview.php and detail.php,
 * handled by ProcessEmailLog.js.
 *
 * @var array{format: string, size: string, language?: int} $state
 * @var Language[] $languages Languages to switch between, empty for no switch
 * @var Sanitizer $sanitizer
 */

$sizeLabels = [
	'mobile' => ['icon' => 'phone', 'label' => __('Phone')],
	'tablet' => ['icon' => 'tablet', 'label' => __('Tablet')],
	'desktop' => ['icon' => 'desktop', 'label' => __('Desktop')],
];
$formatLabels = [
	'html' => ['icon' => 'code', 'label' => __('HTML')],
	'text' => ['icon' => 'file-text', 'label' => __('Text')],
];

?>
			<ul class="uk-subnav uk-subnav-pill uk-margin-remove-vertical" aria-label="<?= __('Format') ?>">
				<?php foreach ($formatLabels as $format => $info): ?>
					<li<?= $state['format'] === $format ? ' class="uk-active"' : '' ?>>
						<a href="#" data-preview="format" data-value="<?= $format ?>">
							<span uk-icon="icon: <?= $info['icon'] ?>; ratio: .8"></span>
							<?= $sanitizer->entities($info['label']) ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>

			<?php if (count($languages) > 1): ?>
				<div class="uk-flex uk-flex-middle">
					<span class="uk-text-muted uk-margin-small-right" uk-icon="icon: world; ratio: .9" uk-tooltip="<?= __('Language') ?>"></span>
					<ul class="uk-subnav uk-subnav-pill uk-margin-remove-vertical" aria-label="<?= __('Language') ?>">
						<?php foreach ($languages as $id => $language):
							$label = (string) $language->get('title') ?: $language->name;
						?>
							<li<?= $state['language'] === $id ? ' class="uk-active"' : '' ?>>
								<a href="#" data-preview="language" data-value="<?= (int) $id ?>"><?= $sanitizer->entities($label) ?></a>
							</li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>

			<ul class="uk-iconnav uk-margin-auto-left" aria-label="<?= __('Device') ?>">
				<?php foreach ($sizeLabels as $size => $info): ?>
					<li<?= $state['size'] === $size ? ' class="uk-active"' : '' ?>>
						<a href="#" data-preview="size" data-value="<?= $size ?>" uk-icon="icon: <?= $info['icon'] ?>"
						   uk-tooltip="<?= $sanitizer->entities($info['label']) ?>" aria-label="<?= $sanitizer->entities($info['label']) ?>"></a>
					</li>
				<?php endforeach; ?>
			</ul>
