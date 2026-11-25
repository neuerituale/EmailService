# EmailService

ProcessWire-Modul für templated E-Mails mit CSS-Inlining, Versand-Log und Admin-Oberfläche.

## Features

- E-Mail-Templates als PHP-Dateien mit voller ProcessWire-API, standardmäßig in `/site/templates/email/`
- Gemeinsamer Wrapper (Layout) und eine CSS-Datei, die automatisch inline gesetzt wird ([Emogrifier](https://github.com/MyIntervals/emogrifier))
- Optionale Plain-Text-Templates, sonst wird der Text aus dem HTML erzeugt
- Mehrsprachig: Templates werden in einer wählbaren Sprache gerendert (`__()`, mehrsprachige Felder)
- Log aller versendeten E-Mails, auch solcher, die andere Module direkt über `WireMail` verschicken
- Admin-Seite **Setup > Email Log** (`ProcessEmailLog`): Liste mit Filtern, Detailansicht, erneut senden, löschen, Template-Vorschau für Phone/Tablet/Desktop, HTML/Text und alle Sprachen
- Automatisches Aufräumen alter Log-Einträge über ProcessCronJobs oder LazyCron

## Voraussetzungen

- PHP >= 8.0
- ProcessWire >= 3.0
- `pelago/emogrifier` (Composer), empfohlen. Ohne Emogrifier funktioniert das Modul ebenfalls, das CSS wird dann als `<style>`-Block in den `<head>` eingebettet statt inline gesetzt. Viele Mail-Clients ignorieren `<style>`-Blöcke teilweise.

## Installation

### Variante A: per Composer im Projekt (empfohlen)

Das Modul ist auf Packagist verfügbar: [nr/emailservice](https://packagist.org/packages/nr/emailservice). Im Projekt-Root ausführen:

```bash
composer require nr/emailservice
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

### Wo Emogrifier geladen wird

1. Ist die Klasse `Pelago\Emogrifier\CssInliner` schon geladen, wird sie verwendet. Das ist der Fall, wenn Emogrifier per Composer im Projekt installiert ist: ProcessWire lädt `vendor/autoload.php` im Root selbst über `index.php`.
2. Sonst lädt das Modul `site/modules/EmailService/vendor/autoload.php` (`composer install` im Modulordner).

Welche Quelle verwendet wird, zeigt die Modul-Konfiguration unter **CSS-Inlining**. Fehlt Emogrifier, erscheint eine Warnung bei der Installation, in der Modul-Konfiguration und (für Superuser) auf den Seiten unter **Setup > E-Mail Logs**.

## Konfiguration

**Modules > Configure > EmailService**

| Option | Standard | Beschreibung |
|--------|----------|--------------|
| Template-Pfad | `site/templates/email/` | Verzeichnis der E-Mail-Templates, relativ zum ProcessWire-Root. Ein führender `/` ist erlaubt, `..` wird entfernt. So können die Templates auch außerhalb von `/site/templates/` liegen, z. B. `site/mail/`. |
| Wrapper-Datei | `wrapper.php` | Layout für alle E-Mails. Leer = kein Wrapper. |
| CSS-Datei | `wrapper.css` | CSS, das inline gesetzt wird. Leer = kein CSS. |
| Standard-Absender | – | Fallback: `$config->adminEmail` |
| Absender-Name | – | Name des Absenders |
| Log-Aufbewahrung | `90` | Einträge älter als X Tage werden täglich gelöscht, `0` = unbegrenzt |

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

- Der Template-Name entspricht dem Dateinamen ohne `.php`. Unterordner werden mit `/` angegeben: Für `'template' => 'shop/order'` wird `site/templates/email/shop/order.php` geladen.
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
| `cc` | string\|array | CC-Empfänger, kommagetrennt oder als Array. Ungültige Adressen werden verworfen. |
| `bcc` | string\|array | BCC-Empfänger, wie `cc` |
| `language` | Language\|int\|string | Sprache (Objekt, ID oder Name), in der gerendert wird. Standard: aktuelle Benutzersprache |

`cc`/`bcc` nutzen `$mail->cc()`/`bcc()`, wenn das Mail-Modul sie anbietet (z. B. WireMailSmtp), sonst den jeweiligen Header.

### Nur rendern (ohne Versand)

```php
$html = $emailService->render('password-reset', [
	'resetLink' => 'https://example.com/reset/?token=abc',
	'code' => '123456',
]);

$text = $emailService->renderText('password-reset', [...]); // '' wenn keine .txt.php existiert
```

Das ist praktisch, wenn ein anderes Modul den Versand selbst übernimmt und nur das Layout von EmailService nutzen soll.

## Mehrsprachigkeit

Mit installiertem **LanguageSupport** rendern `render()`, `renderText()`, `renderTextBody()` und `send()` in der übergebenen Sprache. Währenddessen wird die Benutzersprache umgeschaltet und danach wiederhergestellt, auch bei Exceptions. Dadurch liefern `__()`, `_x()` und mehrsprachige Felder in Templates und Wrapper die richtige Sprache.

```php
$emailService->send([
	'to' => $recipient->email,
	'template' => 'welcome',
	'language' => $recipient->language, // Language, ID oder Name ('de')
	'subject' => '…',
]);
```

Texte außerhalb der Templates, z. B. den Betreff, übersetzt `withLanguage()` in derselben Sprache:

```php
$emailService->withLanguage($recipient->language, fn() => $emailService->send([
	'to' => $recipient->email,
	'template' => 'welcome',
	'subject' => __('Welcome!'),
]));
```

- Eine unbekannte Sprache wird geloggt. `send()` gibt dann `false` zurück, `render()` einen leeren String. `resolveLanguage()` und `withLanguage()` werfen eine `WireException`.
- Ohne LanguageSupport wird `language` ignoriert.
- Templates werden wie alle Site-Dateien unter **Setup > Languages > Sprache > Site-Dateien** übersetzt. Die Beispiel-Templates verwenden englische Quelltexte. Deutsche Übersetzungen dafür liegen in [`examples/languages/de.csv`](examples/languages/de.csv).
- `__()` gibt Übersetzungen HTML-kodiert zurück. In `.txt.php`-Templates deshalb `html_entity_decode()` verwenden, siehe [`examples/email/welcome.txt.php`](examples/email/welcome.txt.php).

### Übersetzungen des Moduls

Die Admin-Oberfläche ist übersetzt ([`languages/de.csv`](languages/de.csv)). Import: **Modules > EmailService**, in der Info-Tabelle unter **Languages** auf „install translations“ klicken, oder per API:

```php
$languages->importTranslationsFile('de', $config->paths->EmailService . 'languages/de.csv');
```

Den übersetzten Seitentitel („E-Mail Logs“) setzt `ProcessEmailLog` bei Installation und Upgrade für jede Sprache, die noch keinen Titel hat. Nachträglich: `$modules->get('ProcessEmailLog')->translatePageTitle()`.

## Templates für die Vorschau registrieren

Damit Templates unter **Setup > Email Log > Vorschau** erscheinen, werden sie mit Beispieldaten registriert. Das geht in `/site/ready.php` oder in der `ready()`-Methode eines eigenen Moduls:

```php
$wire->addHookAfter('EmailService::registerTemplates', function(HookEvent $event) {
	/** @var EmailService $service */
	$service = $event->object;

	$service->registerTemplate('welcome', __('Welcome email'), [
		'userName' => 'Max Mustermann',
		'loginUrl' => 'https://example.com/login/',
	]);

	// Closure: wird in der Vorschausprache aufgerufen, __() liefert also die passende Übersetzung
	$service->registerTemplate('password-reset', __('Password reset'), fn() => [
		'subject' => __('Reset your password'),
		'code' => '123456',
	]);
});
```

In der Vorschau lassen sich Größe (Phone/Tablet/Desktop), Format (HTML/Text) und Sprache umschalten. Die Einstellungen stehen in der URL und bleiben beim Wechsel des Templates erhalten. So lassen sich alle Varianten schnell nacheinander prüfen. Hat ein Template keine `.txt.php`, zeigt die Text-Ansicht den aus dem HTML erzeugten Text, also genau das, was `send()` verschickt.

Weitere Beispiele stehen in [`examples/ready.php`](examples/ready.php).

## Log

Jede E-Mail wird in der Tabelle `email_log` gespeichert, mit Empfänger, Absender, Betreff, Template, Sprache, HTML, Text, Status (`sent`/`failed`) und Zeitpunkt.

- **Über `send()`:** Diese E-Mails werden mit Template-Namen und dem Namen der Sprache geloggt, in der sie gerendert wurden (Option `language`, sonst die aktuelle Sprache). **Erneut senden** übernimmt die Sprache des ursprünglichen Eintrags.
- **Direkt über `WireMail`:** Mails von anderen Modulen, z. B. ProcessForgotPassword, werden per Hook ebenfalls geloggt, aber ohne Template-Namen und ohne Sprache. Über den Hook `EmailService::detectTemplate` lässt sich einer zuordnen:

  ```php
  $wire->addHookAfter('EmailService::detectTemplate', function(HookEvent $event) {
  	/** @var WireMail $mail */
  	$mail = $event->arguments(0);
  	if (str_contains((string) $mail->subject, 'Passwort')) {
  		$event->return = 'password-reset';
  	}
  });
  ```

- **Aufräumen:** Einträge älter als **Log-Aufbewahrung** Tage werden täglich gelöscht. Ist ProcessCronJobs installiert, läuft das als Job `EmailServiceCleanup`, sonst über den Core-`LazyCron` (Hook `LazyCron::everyDay`). LazyCron muss dafür installiert sein und wird durch Seitenaufrufe ausgelöst, auf Seiten ohne Besucher läuft das Aufräumen also verzögert. Ist keins von beiden installiert, wird nichts automatisch gelöscht. Darauf weist auch die Modul-Konfiguration hin.
- **Fehler:** Fehler und Hinweise stehen unter **Setup > Logs > email-service**.

### Admin-Seite (ProcessEmailLog)

**Setup > Email Log** benötigt die Permission `email-log-view`.

- Liste aller E-Mails, filterbar nach Status und Template, mit Pagination und, bei mehreren Sprachen, einer Spalte für die Sprache
- Detailansicht mit allen Daten inkl. Sprache, der E-Mail als HTML oder Text in Phone-, Tablet- und Desktop-Breite und **Erneut senden**
- Löschen einzelner Einträge in der Detailansicht sowie aller bzw. der gefilterten Einträge in der Liste (nur Superuser)
- Vorschau registrierter Templates mit Beispieldaten in Phone-, Tablet- und Desktop-Breite, als HTML und als Text, in allen Sprachen

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

## Tests

Integrationstests mit PHPUnit. Sie booten die ProcessWire-Installation, in der das Modul liegt (oder die aus `PW_ROOT`). Voraussetzung: EmailService ist installiert, die Tabelle `email_log` ist InnoDB.

```bash
cd site/modules/EmailService
composer install
vendor/bin/phpunit
```

- Jeder Test läuft in einer Datenbank-Transaktion, die danach zurückgerollt wird.
- `WireMail::send` wird abgefangen. Es wird **keine** E-Mail verschickt.
- Einträge ins Log `email-service` werden abgefangen, die Log-Datei bleibt unverändert.
- Templates kommen aus `tests/fixtures/templates/email/` (Template-Pfad relativ zum Root), nicht aus `/site/templates/email/`.
- Sprach-Tests laufen nur mit mindestens zwei Sprachen, Übersetzungs-Tests nur nach dem Import von `languages/de.csv`. Sonst werden sie übersprungen.
- Die Reihenfolge ist zufällig (`executionOrder="random"`), damit Abhängigkeiten zwischen Tests auffallen.

## Upgrade

Beim Aktualisieren der Moduldateien führt ProcessWire `___upgrade()` automatisch aus (Modules > Refresh):

- **< 2.1.0:** `ProcessEmailLog` wird nachinstalliert, falls es fehlt.
- **2.6.0:** Die Option **Composer-Autoload-Datei** entfällt, das Upgrade entfernt den gespeicherten Wert. Emogrifier wird nur noch aus dem Autoloader des Projekts oder aus dem `vendor/` des Moduls geladen, nicht mehr aus `site/vendor/` oder einem übergeordneten Verzeichnis.
- **2.5.0:** Der Template-Pfad ist jetzt relativ zum ProcessWire-Root statt zu `/site/templates/`. Das Upgrade stellt einen gespeicherten Wert um, z. B. `email/` → `site/templates/email/`. Die Felder der Modul-Konfiguration sind immer geöffnet.
- **< 2.2.0:** Ein bisheriger root-relativer Template-Pfad wie `/site/templates/email/` wird auf `email/` umgestellt. Der alte Wert würde aber auch ohne Umstellung weiterhin aufgelöst.
- **2.3.0 / ProcessEmailLog 1.1.0:** Mehrsprachigkeit. `registerTemplate()` akzeptiert eine Closure als Beispieldaten. Die Seite „Email Log“ bekommt übersetzte Titel. Rückwärtskompatibel: Ohne `language` wird wie bisher in der aktuellen Sprache gerendert.
- **2.4.0 / ProcessEmailLog 1.2.0:** Die Log-Tabelle bekommt die Spalte `language`. Bestehende Einträge behalten eine leere Sprache. Übersicht und Detailansicht haben das Layout der Vorschau. Hinweis: `ALTER TABLE` beendet in MySQL eine laufende Transaktion, das Upgrade daher nicht innerhalb einer Transaktion auslösen.
- **2.2.0:** Die fest eingebaute Zuordnung der ProcessForgotPassword-Mail (`ProcessUserPage::passwordResetSubject`) entfällt. Wer sie braucht, nutzt den Hook `EmailService::detectTemplate` (s. o.).
