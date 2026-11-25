# EmailService

ProcessWire-Modul für templated E-Mails mit CSS-Inlining, Versand-Log und Admin-Oberfläche.

## Features

- E-Mail-Templates als PHP-Dateien mit voller ProcessWire-API, standardmäßig in `/site/templates/email/`
- Gemeinsamer Wrapper (Layout) und eine CSS-Datei, die automatisch inline gesetzt wird ([Emogrifier](https://github.com/MyIntervals/emogrifier))
- Optionale Plain-Text-Templates, sonst wird der Text aus dem HTML erzeugt
- Log aller versendeten E-Mails, auch solcher, die andere Module direkt über `WireMail` verschicken
- Admin-Seite **Setup > Email Log** (`ProcessEmailLog`): Liste mit Filtern, Detailansicht, erneut senden, löschen, Template-Vorschau für Phone/Tablet/Desktop
- Automatisches Aufräumen alter Log-Einträge über ProcessCronJobs oder LazyCron

## Voraussetzungen

- PHP >= 8.0
- ProcessWire >= 3.0
- `pelago/emogrifier` (Composer), empfohlen. Ohne Emogrifier funktioniert das Modul ebenfalls, das CSS wird dann als `<style>`-Block in den `<head>` eingebettet statt inline gesetzt. Viele Mail-Clients ignorieren `<style>`-Blöcke teilweise.

## Installation

### Variante A: per Composer im Projekt

```bash
composer require neuerituale/emailservice
```

`composer/installers` legt das Modul nach `site/modules/EmailService/`. Emogrifier landet im `vendor/` des Projekts und wird über dessen Autoloader geladen.

### Variante B: Modulordner kopieren

1. Ordner nach `site/modules/EmailService/` kopieren
2. Emogrifier an einer der folgenden Stellen installieren, z. B. direkt im Modul:
   ```bash
   cd site/modules/EmailService && composer install --no-dev
   ```
   oder im Projekt-Root:
   ```bash
   composer require pelago/emogrifier
   ```

### Danach

Im Admin unter **Modules > Refresh** das Modul **Email Service** installieren. `ProcessEmailLog` wird automatisch mitinstalliert und beim Deinstallieren wieder entfernt. Bei der Installation erscheinen Hinweise, falls Emogrifier oder das Template-Verzeichnis fehlen.

### Wo nach Emogrifier gesucht wird

Ist die Klasse `Pelago\Emogrifier\CssInliner` noch nicht geladen, sucht das Modul in dieser Reihenfolge nach einer `autoload.php` und lädt die erste, die die Klasse bereitstellt:

1. Pfad aus der Modul-Konfiguration **Composer-Autoload-Datei** (absolut oder relativ zum Root)
2. `site/modules/EmailService/vendor/autoload.php`: `composer install` im Modulordner
3. `site/vendor/autoload.php`
4. `vendor/autoload.php` im ProcessWire-Root. Diesen lädt ProcessWire meist schon selbst über `index.php`.
5. `../vendor/autoload.php`, falls der Docroot ein Unterordner des Composer-Projekts ist (z. B. `projekt/public/`) und dort eine `composer.json` liegt

Welche Datei verwendet wurde, steht in der Modul-Konfiguration unter dem Feld **Composer-Autoload-Datei**.

## Konfiguration

**Modules > Configure > EmailService**

| Option | Standard | Beschreibung |
|--------|----------|--------------|
| Template-Pfad | `email/` | Verzeichnis der E-Mail-Templates, relativ zu `/site/templates/`. Unterordner sind möglich (`mail/de/`). |
| Wrapper-Datei | `wrapper.php` | Layout für alle E-Mails. Leer = kein Wrapper. |
| CSS-Datei | `wrapper.css` | CSS, das inline gesetzt wird. Leer = kein CSS. |
| Standard-Absender | – | Fallback: `$config->adminEmail` |
| Absender-Name | – | Name des Absenders |
| Log-Aufbewahrung | `90` | Einträge älter als X Tage werden täglich gelöscht, `0` = unbegrenzt |
| Composer-Autoload-Datei | – | Optionaler Pfad zu einer `vendor/autoload.php`, sonst automatische Suche (s. o.) |

Das Konfigurationsfeld zeigt den aufgelösten Template-Pfad an und warnt, wenn das Verzeichnis fehlt.

## Templates

Fertige Beispiele liegen in [`examples/email/`](examples/email/). Für den Einstieg einfach nach `/site/templates/email/` kopieren:

```bash
cp -R site/modules/EmailService/examples/email site/templates/email
```

```
site/templates/email/
├── wrapper.php              Layout (Header, Footer)
├── wrapper.css              wird inline gesetzt
├── welcome.php              Template "welcome" (HTML)
├── welcome.txt.php          Template "welcome" (Text, optional)
└── password-reset.php       Template "password-reset"
```

- Der Template-Name entspricht dem Dateinamen ohne `.php`. Unterordner werden mit `/` angegeben: Für `'template' => 'shop/order'` wird `email/shop/order.php` geladen.
- Templates sind normale ProcessWire-`TemplateFile`s. `$pages`, `$config`, `$sanitizer`, `$user` usw. stehen zur Verfügung, dazu alle Werte aus `data`.
- `$subject` wird von `send()` automatisch an Template und Wrapper übergeben.
- Liegt neben `name.php` eine `name.txt.php`, wird sie als Text-Teil verwendet. Sonst erzeugt das Modul den Text-Teil aus dem HTML.

### Content-Template

```php
<?php namespace ProcessWire;

/**
 * @var string $userName
 * @var string $loginUrl
 */
?>
<h1>Willkommen, <?= $sanitizer->entities($userName) ?>!</h1>

<p><a class="button" href="<?= $sanitizer->entities($loginUrl) ?>">Jetzt anmelden</a></p>
```

### Wrapper

Der Wrapper erhält alle Template-Daten und zusätzlich `$content` mit dem gerenderten Content-Template:

```php
<?php namespace ProcessWire;

/**
 * @var string $content Gerenderter Template-Inhalt
 * @var string $subject E-Mail-Betreff
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

Das vollständige Beispiel mit Preheader und tabellenbasiertem Layout steht in [`examples/email/wrapper.php`](examples/email/wrapper.php).

## Verwendung

### E-Mail senden

```php
/** @var EmailService $emailService */
$emailService = $modules->get('EmailService');

$sent = $emailService->send([
	'to' => 'empfaenger@example.com',
	'subject' => 'Willkommen!',
	'template' => 'welcome',
	'data' => [
		'userName' => 'Max Mustermann',
		'loginUrl' => $pages->get('/login/')->httpUrl,
		'user' => $userPage, // Objekte sind möglich
	],
]);
```

`send()` gibt `false` zurück, wenn `to` oder `template` fehlen, die Template-Datei nicht existiert oder der Versand fehlschlägt. Fehlende Templates werden im Log `email-service` vermerkt.

### send()-Optionen

| Option | Typ | Beschreibung |
|--------|-----|--------------|
| `to` | string | Empfänger (erforderlich) |
| `template` | string | Template-Name ohne `.php` (erforderlich) |
| `subject` | string | Betreff, steht im Template als `$subject` zur Verfügung |
| `data` | array | Daten für Template und Wrapper |
| `from` | string | Absender, Fallback: Konfiguration bzw. `$config->adminEmail` |
| `fromName` | string | Absender-Name |
| `cc` | string | CC-Empfänger |
| `bcc` | string | BCC-Empfänger |

### Nur rendern (ohne Versand)

```php
$html = $emailService->render('password-reset', [
	'resetLink' => 'https://example.com/reset/?token=abc',
	'code' => '123456',
]);

$text = $emailService->renderText('password-reset', [...]); // '' wenn keine .txt.php existiert
```

Das ist praktisch, wenn ein anderes Modul den Versand selbst übernimmt und nur das Layout von EmailService nutzen soll.

## Templates für die Vorschau registrieren

Damit Templates unter **Setup > Email Log > Vorschau** erscheinen, werden sie mit Beispieldaten registriert. Das geht in `/site/ready.php` oder in der `ready()`-Methode eines eigenen Moduls:

```php
$wire->addHookAfter('EmailService::registerTemplates', function(HookEvent $event) {
	/** @var EmailService $service */
	$service = $event->object;

	$service->registerTemplate('welcome', 'Willkommens-E-Mail', [
		'subject' => 'Willkommen!',
		'userName' => 'Max Mustermann',
		'loginUrl' => 'https://example.com/login/',
	]);
});
```

Weitere Beispiele stehen in [`examples/ready.php`](examples/ready.php).

## Log

Jede E-Mail wird in der Tabelle `email_log` gespeichert, mit Empfänger, Absender, Betreff, Template, HTML, Text, Status (`sent`/`failed`) und Zeitpunkt.

- **Über `send()`:** Diese E-Mails werden mit Template-Namen geloggt.
- **Direkt über `WireMail`:** Mails von anderen Modulen, z. B. ProcessForgotPassword, werden per Hook ebenfalls geloggt, aber ohne Template-Namen. Über den Hook `EmailService::detectTemplate` lässt sich einer zuordnen:

  ```php
  $wire->addHookAfter('EmailService::detectTemplate', function(HookEvent $event) {
  	/** @var WireMail $mail */
  	$mail = $event->arguments(0);
  	if (str_contains((string) $mail->subject, 'Passwort')) {
  		$event->return = 'password-reset';
  	}
  });
  ```

- **Aufräumen:** Einträge älter als **Log-Aufbewahrung** Tage werden täglich gelöscht. Ist ProcessCronJobs installiert, läuft das als Job `EmailServiceCleanup`, sonst über den Core-`LazyCron`. Ist keins von beiden installiert, wird nichts automatisch gelöscht. Darauf weist auch die Modul-Konfiguration hin.
- **Fehler:** Fehler und Hinweise stehen unter **Setup > Logs > email-service**.

### Admin-Seite (ProcessEmailLog)

**Setup > Email Log** benötigt die Permission `email-log-view`.

- Liste aller E-Mails, filterbar nach Status und Template, mit Pagination
- Detailansicht mit HTML-Vorschau und **Erneut senden**
- Löschen aller bzw. der gefilterten Einträge (nur Superuser)
- Vorschau registrierter Templates mit Beispieldaten in Phone-, Tablet- und Desktop-Breite, als HTML und als Text

## Namen

| Was | Name |
|-----|------|
| Module | `EmailService`, `ProcessEmailLog` |
| DB-Tabelle | `email_log` (`EmailService::logTable`) |
| Log-Datei | `email-service` (`EmailService::logName`) |
| Cron-Job | `EmailServiceCleanup` (`EmailService::cleanupJob`) |
| Permission | `email-log-view` |
| Admin-Seite | `setup/email-log` |
| Hooks | `EmailService::registerTemplates`, `EmailService::detectTemplate` |

## Upgrade

Beim Aktualisieren der Moduldateien führt ProcessWire `___upgrade()` automatisch aus (Modules > Refresh):

- **< 2.1.0:** `ProcessEmailLog` wird nachinstalliert, falls es fehlt.
- **< 2.2.0:** Ein bisheriger root-relativer Template-Pfad wie `/site/templates/email/` wird auf `email/` umgestellt. Der alte Wert würde aber auch ohne Umstellung weiterhin aufgelöst.
- **2.2.0:** Die fest eingebaute Zuordnung der ProcessForgotPassword-Mail (`ProcessUserPage::passwordResetSubject`) entfällt. Wer sie braucht, nutzt den Hook `EmailService::detectTemplate` (s. o.).
