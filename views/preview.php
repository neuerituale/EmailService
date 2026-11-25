<?php

namespace ProcessWire;

/**
 * @var array $templates Registered templates
 * @var string $selectedTemplate
 * @var string $previewHtml
 * @var Page $adminPage
 * @var Config $config
 */

?>
<?php if (!count($templates)): ?>

	<div class="uk-card uk-card-default uk-margin">
		<div class="uk-card-body uk-text-muted uk-text-center">
			<?= __('No templates registered. Use the EmailService::registerTemplates hook to register templates with sample data.') ?>
		</div>
	</div>

<?php else: ?>

	<div style="display:grid;grid-template-columns:220px 1fr;gap:20px;">

		<nav>

			<ul class="uk-iconnav uk-margin-small-bottom">
				<li><a href="#" class="email-preview-size" data-width="377px" title="Mobile" uk-icon="icon: phone" uk-tooltip="<?= __('Phone'); ?>"></a></li>
				<li><a href="#" class="email-preview-size" data-width="770px" title="Tablet" uk-icon="icon: tablet" uk-tooltip="<?= __('Tablet'); ?>"></a></li>
				<li><a href="#" class="email-preview-size uk-active" data-width="100%" title="Desktop" uk-icon="icon: desktop" uk-tooltip="<?= __('Desktop'); ?>"></a></li>
				<li class="uk-margin-auto-left"></li>
				<li><a href="#" class="email-preview-format uk-active" data-format="html" uk-icon="icon: code" uk-tooltip="<?= __('HTML'); ?>"></a></li>
				<li><a href="#" class="email-preview-format" data-format="text" uk-icon="icon: file-text" uk-tooltip="<?= __('Text'); ?>"></a></li>
			</ul>

			<hr>

			<!-- <p class="uk-text-small uk-text-muted uk-text-italic"><?= __('Templates'); ?></p> -->

			<ul class="uk-nav uk-nav-default">
				<?php foreach ($templates as $key => $tplInfo):
					$url = $adminPage->url . 'preview/?template=' . urlencode($key);
					$active = $selectedTemplate === $key;
				?>
					<li<?= $active ? ' class="uk-active"' : '' ?>>
						<a href="<?= $url ?>"<?= $active ? ' style="font-weight:600;"' : '' ?>>
							<?= sanitizer()->entities($tplInfo['label']) ?>
						</a>
					</li>
				<?php endforeach; ?>
			</ul>
		</nav>

		<div style="display:flex;justify-content:center;">
			<?php if ($selectedTemplate && $previewHtml): ?>

				<?php $iframeUrl = $adminPage->url . 'iframe/?template=' . urlencode($selectedTemplate); ?>

				<iframe id="email-preview-iframe"
				        src="<?= $iframeUrl ?>"
				        style="width:100%;border:1px solid #ddd;border-radius:4px;background:#fff;transition:width .2s ease;"
				        scrolling="no"
				        onload="this.style.height=this.contentDocument.documentElement.scrollHeight+'px'"></iframe>

			<?php elseif ($selectedTemplate): ?>

				<div class="uk-alert uk-alert-warning" style="width:100%;">
					<?= __('Could not render template. Check that the template file exists and sample data is valid.') ?>
				</div>

			<?php else: ?>

				<div class="uk-text-muted uk-text-center" style="width:100%;padding-top:200px;">
					<?= __('Select a template to preview.') ?>
				</div>

			<?php endif; ?>
		</div>

	</div>

	<script>
	document.querySelectorAll('.email-preview-size').forEach(function(link) {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			var iframe = document.getElementById('email-preview-iframe');
			if (!iframe) return;
			iframe.style.width = this.dataset.width;
			iframe.contentDocument.documentElement.offsetHeight;
			iframe.style.height = iframe.contentDocument.documentElement.scrollHeight + 'px';
			document.querySelectorAll('.email-preview-size').forEach(function(a) {
				a.classList.remove('uk-active');
			});
			this.classList.add('uk-active');
		});
	});

	document.querySelectorAll('.email-preview-format').forEach(function(link) {
		link.addEventListener('click', function(e) {
			e.preventDefault();
			var iframe = document.getElementById('email-preview-iframe');
			if (!iframe) return;
			var url = new URL(iframe.src);
			url.searchParams.set('format', this.dataset.format);
			iframe.src = url.toString();
			document.querySelectorAll('.email-preview-format').forEach(function(a) {
				a.classList.remove('uk-active');
			});
			this.classList.add('uk-active');
		});
	});
	</script>

<?php endif; ?>
