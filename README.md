# EmailService

ProcessWire module for templated emails with CSS inlining, a send log and an admin interface.

## Features

- Email templates as PHP files with the full ProcessWire API, by default in `/site/templates/email/`
- A shared wrapper (layout) and a CSS file that is inlined automatically ([Emogrifier](https://github.com/MyIntervals/emogrifier))
- Optional plain text templates, otherwise the text part is generated from the HTML
- Multi-language: templates are rendered in a selectable language (`__()`, multi-language fields)
- Log of all sent emails, including those that other modules send directly via `WireMail`
- Admin page **Setup > Email Log** (`ProcessEmailLog`): list with filters, detail view, resend, delete, template preview for phone/tablet/desktop, HTML/text and all languages
- Automatic cleanup of old log entries via ProcessCronJobs or LazyCron

## Requirements

- PHP >= 8.0
- ProcessWire >= 3.0
- `pelago/emogrifier` (Composer), recommended. The module also works without Emogrifier: the CSS is then embedded as a `<style>` block in the `<head>` instead of being inlined. Many mail clients partly ignore `<style>` blocks.

## Installation

### Option A: with Composer in the project (recommended)

The module is available on Packagist: [nr/emailservice](https://packagist.org/packages/nr/emailservice). Run in the project root:

```bash
composer require nr/emailservice
```

`composer/installers` puts the module into `site/modules/EmailService/`. Emogrifier is installed into the project's `vendor/` and loaded by its autoloader.

### Option B: copy the module folder

1. Copy the folder to `site/modules/EmailService/`
2. Install Emogrifier in one of these places, e.g. inside the module:
   ```bash
   cd site/modules/EmailService && composer install --no-dev
   ```
   or in the project root:
   ```bash
   composer require pelago/emogrifier
   ```

### Then

In the admin, go to **Modules > Refresh** and install **Email Service**. `ProcessEmailLog` is installed along with it and removed again on uninstall. During installation, notices appear if Emogrifier or the template directory is missing.

### Where Emogrifier is loaded from

1. If the class `Pelago\Emogrifier\CssInliner` is already loaded, it is used. This is the case when Emogrifier is installed in the project with Composer: ProcessWire itself loads `vendor/autoload.php` in the root via `index.php`.
2. Otherwise the module loads `site/modules/EmailService/vendor/autoload.php` (`composer install` in the module folder).

The module configuration shows the source in use under **CSS inlining**. If Emogrifier is missing, a warning appears during installation, in the module configuration and (for superusers) on the pages under **Setup > Email Log**.

## Configuration

**Modules > Configure > EmailService**

| Option | Default | Description |
|--------|---------|-------------|
| Template path | `site/templates/email/` | Directory of the email templates, relative to the ProcessWire root. A leading `/` is allowed, `..` is removed. This way templates can also live outside `/site/templates/`, e.g. `site/mail/`. |
| Wrapper file | `wrapper.php` | Layout for all emails. Empty = no wrapper. |
| CSS file | `wrapper.css` | CSS that is inlined. Empty = no CSS. |
| Default sender | – | Fallback: `$config->adminEmail` |
| Sender name | – | Name of the sender |
| Log retention | `90` | Entries older than X days are deleted daily, `0` = unlimited |

The configuration shows the resolved template path and warns if the directory is missing. The form is defined in `EmailServiceConfig.php` (ProcessWire's `ModuleConfig` convention).

## Templates

Ready-made examples are in [`examples/email/`](examples/email/). To get started, copy them to `/site/templates/email/`:

```bash
cp -R site/modules/EmailService/examples/email site/templates/email
```

```
site/templates/email/
├── wrapper.php              Layout (header, footer)
├── wrapper.txt.php          Layout for text emails (optional, e.g. signature)
├── wrapper.css              gets inlined
├── welcome.php              Template "welcome" (HTML)
├── welcome.txt.php          Template "welcome" (text, optional)
└── password-reset.php       Template "password-reset"
```

- The template name is the file name without `.php`. Subdirectories are separated with `/`: `'template' => 'shop/order'` loads `site/templates/email/shop/order.php`.
- Templates are regular ProcessWire `TemplateFile`s. `$pages`, `$config`, `$sanitizer`, `$user` etc. are available, plus all values from `data`.
- `send()` passes `$subject` to the template and the wrapper automatically.
- If there is a `name.txt.php` next to `name.php`, it is used as the text part. Otherwise the module generates the text part from the HTML.

### Content template

```php
<?php namespace ProcessWire;

/**
 * @var string $userName
 * @var string $loginUrl
 */
?>
<h1>Welcome, <?= $sanitizer->entities($userName) ?>!</h1>

<p><a class="button" href="<?= $sanitizer->entities($loginUrl) ?>">Sign in now</a></p>
```

### Wrapper

The wrapper receives all template data plus `$content` with the rendered content template:

```php
<?php namespace ProcessWire;

/**
 * @var string $content Rendered template content
 * @var string $subject Email subject
 */
?>
<!DOCTYPE html>
<html>
<head>
	<meta charset="utf-8">
	<title><?= $sanitizer->entities($subject ?? '') ?></title>
</head>
<body>
	<table role="presentation" class="container" width="600">
		<tr><td class="content"><?= $content ?></td></tr>
		<tr><td class="footer">&copy; <?= date('Y') ?></td></tr>
	</table>
</body>
</html>
```

The complete example with preheader and table-based layout is [`examples/email/wrapper.php`](examples/email/wrapper.php).

### Text wrapper

Optionally there is a wrapper for the text part, e.g. for a signature with the sender address. Its name follows the **Wrapper file** setting: `wrapper.php` → `wrapper.txt.php`. It receives the same data as the HTML wrapper, `$content` is the text of the `.txt.php` template.

```php
<?= $content ?>


-- 
Example Ltd.
1 Example Street, 12345 Example City
<?= $config->urls->httpRoot ?>
```

The text wrapper is only used for templates with a `.txt.php` version. Text generated from the HTML already contains the HTML wrapper. Example: [`examples/email/wrapper.txt.php`](examples/email/wrapper.txt.php).

## Usage

### Sending an email

```php
/** @var EmailService $emailService */
$emailService = $modules->get('EmailService');

$sent = $emailService->send([
	'to' => 'recipient@example.com',
	'subject' => 'Welcome!',
	'template' => 'welcome',
	'data' => [
		'userName' => 'Jane Doe',
		'loginUrl' => $pages->get('/login/')->httpUrl,
		'user' => $userPage, // objects are possible
	],
]);
```

`send()` returns `false` if `to` or `template` is missing, the template file does not exist or sending fails. Missing templates are noted in the `email-service` log.

### send() options

| Option | Type | Description |
|--------|------|-------------|
| `to` | string | Recipient (required) |
| `template` | string | Template name without `.php` (required) |
| `subject` | string | Subject, available in the template as `$subject` |
| `data` | array | Data for template and wrapper |
| `from` | string | Sender, fallback: configuration or `$config->adminEmail` |
| `fromName` | string | Sender name |
| `cc` | string\|array | CC recipients, comma-separated or as array. Invalid addresses are dropped. |
| `bcc` | string\|array | BCC recipients, like `cc` |
| `language` | Language\|int\|string | Language (object, ID or name) to render in. Default: current user language |

`cc`/`bcc` use `$mail->cc()`/`bcc()` if the mail module provides them (e.g. WireMailSmtp), otherwise the respective header.

### Render only (without sending)

```php
$html = $emailService->render('password-reset', [
	'resetLink' => 'https://example.com/reset/?token=abc',
	'code' => '123456',
]);

$text = $emailService->renderText('password-reset', [...]); // '' if there is no .txt.php
```

This is useful when another module does the sending itself and only uses the layout of EmailService.

## Multi-language

With **LanguageSupport** installed, `render()`, `renderText()`, `renderTextBody()` and `send()` render in the given language. The user language is switched while rendering and restored afterwards, also on exceptions. This way `__()`, `_x()` and multi-language fields return the right language in templates and wrapper.

```php
$emailService->send([
	'to' => $recipient->email,
	'template' => 'welcome',
	'language' => $recipient->language, // Language, ID or name ('de')
	'subject' => '…',
]);
```

`withLanguage()` translates texts outside the templates, e.g. the subject, in the same language:

```php
$emailService->withLanguage($recipient->language, fn() => $emailService->send([
	'to' => $recipient->email,
	'template' => 'welcome',
	'subject' => __('Welcome!'),
]));
```

- An unknown language is logged. `send()` then returns `false`, `render()` an empty string. `resolveLanguage()` and `withLanguage()` throw a `WireException`.
- Without LanguageSupport, `language` is ignored.
- Templates are translated like all site files under **Setup > Languages > language > Site files**. The example templates use English source strings. German translations for them are in [`examples/languages/de.csv`](examples/languages/de.csv).
- `__()` returns translations HTML-encoded. Use `html_entity_decode()` in `.txt.php` templates, see [`examples/email/welcome.txt.php`](examples/email/welcome.txt.php).

### Module translations

The admin interface is translated ([`languages/de.csv`](languages/de.csv)). To import: **Modules > EmailService**, click "install translations" under **Languages** in the info table, or via API:

```php
$languages->importTranslationsFile('de', $config->paths->EmailService . 'languages/de.csv');
```

`ProcessEmailLog` sets the translated page title ("E-Mail Logs" in German) on installation and upgrade for every language that has no title yet. Afterwards: `$modules->get('ProcessEmailLog')->translatePageTitle()`.

## Registering templates for the preview

To appear under **Setup > Email Log > Preview**, templates are registered with sample data. This can be done in `/site/ready.php` or in the `ready()` method of your own module:

```php
$wire->addHookAfter('EmailService::registerTemplates', function(HookEvent $event) {
	/** @var EmailService $service */
	$service = $event->object;

	$service->registerTemplate('welcome', __('Welcome email'), [
		'userName' => 'Jane Doe',
		'loginUrl' => 'https://example.com/login/',
	]);

	// closure: called in the preview language, so __() returns the matching translation
	$service->registerTemplate('password-reset', __('Password reset'), fn() => [
		'subject' => __('Reset your password'),
		'code' => '123456',
	]);
});
```

In the preview you can switch the size (phone/tablet/desktop), the format (HTML/text) and the language. The settings are kept in the URL and stay when switching templates, so all variants can be checked quickly one after another. If a template has no `.txt.php`, the text view shows the text generated from the HTML, exactly what `send()` sends.

More examples are in [`examples/ready.php`](examples/ready.php).

## Log

Every email is stored in the table `email_log`, with recipient, sender, subject, template, language, HTML, text, status (`sent`/`failed`) and time.

- **Via `send()`:** these emails are logged with the template name and the name of the language they were rendered in (option `language`, otherwise the current language). **Resend** keeps the language of the original entry.
- **Directly via `WireMail`:** mails from other modules, e.g. ProcessForgotPassword, are logged by a hook as well, but without template name and language. The hook `EmailService::detectTemplate` can assign a template:

  ```php
  $wire->addHookAfter('EmailService::detectTemplate', function(HookEvent $event) {
  	/** @var WireMail $mail */
  	$mail = $event->arguments(0);
  	if (str_contains((string) $mail->subject, 'Password')) {
  		$event->return = 'password-reset';
  	}
  });
  ```

- **Cleanup:** entries older than **Log retention** days are deleted daily. If ProcessCronJobs is installed, this runs as job `EmailServiceCleanup`, otherwise via the core `LazyCron` (hook `LazyCron::everyDay`). LazyCron must be installed for that and is triggered by page views, so on sites without visitors the cleanup runs late. If neither is installed, nothing is deleted automatically. The module configuration points this out as well.
- **Errors:** errors and notices are logged under **Setup > Logs > email-service**.

### Admin page (ProcessEmailLog)

**Setup > Email Log** requires the permission `email-log-view`.

- List of all emails, filterable by status and template, with pagination and, with several languages, a language column
- Detail view with all data incl. language, the email as HTML or text in phone, tablet and desktop width, and **Resend**
- Deleting single entries in the detail view and all or the filtered entries in the list (superusers only)
- Preview of registered templates with sample data in phone, tablet and desktop width, as HTML and as text, in all languages

## Names

| What | Name |
|------|------|
| Modules | `EmailService`, `ProcessEmailLog` |
| DB table | `email_log` (`EmailService::logTable`) |
| Log file | `email-service` (`EmailService::logName`) |
| Cron job | `EmailServiceCleanup` (`EmailService::cleanupJob`) |
| Permission | `email-log-view` |
| Admin page | `setup/email-log` |
| Hooks | `EmailService::registerTemplates`, `EmailService::detectTemplate` |

## Tests

Integration tests with PHPUnit. They boot the ProcessWire installation the module is in (or the one in `PW_ROOT`). Requirements: EmailService is installed, the table `email_log` is InnoDB.

```bash
cd site/modules/EmailService
composer install
vendor/bin/phpunit
```

- Every test runs in a database transaction that is rolled back afterwards.
- `WireMail::send` is intercepted. **No** email is sent.
- Entries to the `email-service` log are intercepted, the log file stays unchanged.
- Templates come from `tests/fixtures/templates/email/` (template path relative to the root), not from `/site/templates/email/`.
- Language tests only run with at least two languages, translation tests only after importing `languages/de.csv`. Otherwise they are skipped.
- The order is random (`executionOrder="random"`), so dependencies between tests show up.

## Upgrade

When the module files are updated, ProcessWire runs `___upgrade()` automatically (Modules > Refresh):

- **2.7.0:** Optional text wrapper `wrapper.txt.php` for `.txt.php` templates. Without this file nothing changes.
- **2.6.0:** The option **Composer autoload file** is gone, the upgrade removes the stored value. Emogrifier is only loaded from the project autoloader or from the module's `vendor/`, no longer from `site/vendor/` or a parent directory.
- **2.5.0:** The template path is now relative to the ProcessWire root instead of `/site/templates/`. The upgrade converts a stored value, e.g. `email/` → `site/templates/email/`. The fields of the module configuration are always open.
- **2.4.0 / ProcessEmailLog 1.2.0:** The log table gets the column `language`. Existing entries keep an empty language. Overview and detail view use the layout of the preview. Note: `ALTER TABLE` ends a running transaction in MySQL, so don't trigger the upgrade inside a transaction.
- **2.3.0 / ProcessEmailLog 1.1.0:** Multi-language. `registerTemplate()` accepts a closure as sample data. The "Email Log" page gets translated titles. Backwards compatible: without `language`, rendering happens in the current language as before.
- **2.2.0:** The built-in mapping of the ProcessForgotPassword mail (`ProcessUserPage::passwordResetSubject`) is gone. Use the hook `EmailService::detectTemplate` (see above) if you need it. A root-relative template path like `/site/templates/email/` was converted to `email/`.
- **< 2.1.0:** `ProcessEmailLog` is installed if it is missing.
