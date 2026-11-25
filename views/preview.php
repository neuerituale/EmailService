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
<?php if (!count($templates)): ?>

	<div class="uk-card uk-card-default uk-margin">
		<div class="uk-card-body uk-text-muted uk-text-center">
			<?= __('No templates registered. Use the EmailService::registerTemplates hook to register templates with sample data.') ?>
		</div>
	</div>

<?php else: ?>

	<div id="email-preview" class="uk-grid-medium" uk-grid>

		<div class="uk-width-1-1 uk-width-1-4@m uk-width-1-5@l">
			<div class="email-preview-bar uk-flex uk-flex-middle uk-margin-small-bottom">
				<h3 class="email-preview-label uk-margin-remove"><?= __('Templates') ?></h3>
				<span class="email-preview-count uk-badge uk-margin-small-left"><?= count($templates) ?></span>
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

			<div class="email-preview-bar uk-flex uk-flex-middle uk-flex-wrap uk-margin-small-bottom" style="gap:8px 32px;">

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

	<script>
	(function() {
		var root = document.getElementById('email-preview');
		var iframe = document.getElementById('email-preview-iframe');
		var frame = document.getElementById('email-preview-frame');
		var hint = document.getElementById('email-preview-text-hint');
		var hasTextTemplate = <?= $hasTextTemplate ? 'true' : 'false' ?>;
		var sizes = <?= json_encode($sizes) ?>;
		var state = <?= json_encode([
			'format' => $state['format'],
			'size' => $state['size'],
			'language' => $state['language'],
		]) ?>;

		// height: at least the space that is left in the window (so short emails don't make the
		// page scroll), longer emails make the preview taller, so the page scrolls and not the iframe
		var minHeight = 320;

		function contentHeight() {
			try {
				var doc = iframe.contentDocument;
				if (!doc || !doc.documentElement) return 0;
				// measure without the current height stretching the document
				iframe.style.height = '0px';
				return Math.max(doc.documentElement.scrollHeight, doc.body ? doc.body.scrollHeight : 0);
			} catch (e) {
				return 0;
			}
		}

		// space the page needs below the preview (paddings, footer); measured while the preview
		// is taller than the window, otherwise the sticky footer of the admin theme would distort it
		function spaceBelow() {
			iframe.style.height = (window.innerHeight * 2) + 'px';
			var frameBottom = frame.getBoundingClientRect().bottom + window.scrollY;
			return Math.max(0, document.documentElement.scrollHeight - frameBottom);
		}

		function fitIframe() {
			if (!iframe || !frame) return;
			var top = frame.getBoundingClientRect().top + window.scrollY;
			var available = Math.floor(window.innerHeight - top - spaceBelow());
			iframe.style.height = Math.max(minHeight, available, contentHeight()) + 'px';
		}

		function applyParams(url) {
			url.searchParams.set('format', state.format);
			url.searchParams.set('size', state.size);
			if (state.language) url.searchParams.set('language', String(state.language));
			else url.searchParams.delete('language');
			return url;
		}

		// keep size, format and language in the page URL and in the template links
		function syncUrls() {
			try {
				history.replaceState(null, '', applyParams(new URL(window.location.href)).toString());
			} catch (e) {}
			root.querySelectorAll('.email-preview-template').forEach(function(link) {
				link.href = applyParams(new URL(link.href, window.location.href)).toString();
			});
		}

		function reloadIframe() {
			if (!iframe) return;
			var url = applyParams(new URL(iframe.src, window.location.href));
			url.searchParams.delete('size');
			iframe.src = url.toString();
		}

		function markActive(control, value) {
			root.querySelectorAll('[data-preview="' + control + '"]').forEach(function(el) {
				el.parentElement.classList.toggle('uk-active', el.dataset.value === value);
			});
		}

		function setState(control, value) {
			if (control === 'language') value = parseInt(value, 10) || 0;
			if (state[control] === value) return;
			state[control] = value;
			markActive(control, String(value));

			if (control === 'size') {
				if (frame) {
					frame.style.width = sizes[value];
					// a narrower email gets taller, measure after the width transition
					setTimeout(fitIframe, 250);
				}
			} else {
				if (control === 'format' && hint) {
					hint.hidden = hasTextTemplate || state.format !== 'text';
					// the hint moves the preview down
					fitIframe();
				}
				reloadIframe();
			}
			syncUrls();
		}

		root.addEventListener('click', function(e) {
			var el = e.target.closest('[data-preview]');
			if (!el) return;
			e.preventDefault();
			setState(el.dataset.preview, el.dataset.value);
		});

		if (iframe) {
			iframe.addEventListener('load', fitIframe);
			window.addEventListener('resize', fitIframe);
			// in case the iframe finished loading before this script ran
			fitIframe();
		}

		syncUrls();
	})();
	</script>

<?php endif; ?>
