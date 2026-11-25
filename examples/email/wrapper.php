<?php

namespace ProcessWire;

/**
 * Wrapper for all emails. Copy to /site/templates/email/wrapper.php.
 *
 * Receives all template data plus:
 *
 * @var string $content Rendered content template
 * @var string $subject Email subject (set automatically by EmailService::send())
 * @var string|null $preheader Optional preview text shown in the inbox list
 * @var Config $config
 */

$siteName = $config->httpHost;
$homeUrl = $config->urls->httpRoot;

?>
<!DOCTYPE html>
<html lang="de">
<head>
	<meta charset="utf-8">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="x-apple-disable-message-reformatting">
	<title><?= $sanitizer->entities($subject ?? '') ?></title>
</head>
<body>
	<?php if (!empty($preheader)): ?>
		<div class="preheader"><?= $sanitizer->entities($preheader) ?></div>
	<?php endif; ?>

	<table role="presentation" class="outer" width="100%" cellpadding="0" cellspacing="0">
		<tr>
			<td align="center">
				<table role="presentation" class="container" width="600" cellpadding="0" cellspacing="0">
					<tr>
						<td class="header">
							<a href="<?= $homeUrl ?>"><?= $sanitizer->entities($siteName) ?></a>
						</td>
					</tr>
					<tr>
						<td class="content">
							<?= $content ?>
						</td>
					</tr>
					<tr>
						<td class="footer">
							&copy; <?= date('Y') ?> <?= $sanitizer->entities($siteName) ?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>
</body>
</html>
