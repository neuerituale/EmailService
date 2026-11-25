<?php

namespace ProcessWire;

/**
 * Language in the email log, log overview and detail page.
 */
final class LogViewTest extends TestCase
{
	private Page $adminPage;

	protected function setUp(): void
	{
		parent::setUp();
		$this->adminPage = $this->useAdminPage();
		$this->registerFixtures();
	}

	private function languageLabel(Language $language): string
	{
		return (string) $language->get('title') ?: $language->name;
	}

	/* ***** Language in the log *** */

	public function testLogTableHasLanguageColumn(): void
	{
		$stmt = $this->wire()->database->prepare("SHOW COLUMNS FROM `" . EmailService::logTable . "` LIKE 'language'");
		$stmt->execute();
		$column = $stmt->fetch(\PDO::FETCH_ASSOC);
		$stmt->closeCursor();

		$this->assertIsArray($column, 'The log table has a language column (added by the 2.4.0 upgrade).');
		$this->assertStringStartsWith('varchar', strtolower($column['Type']));
	}

	public function testSendLogsGivenLanguage(): void
	{
		$language = $this->otherLanguage();

		$this->assertTrue($this->service()->send(['to' => 'lang@phpunit.test', 'template' => 'basic', 'language' => $language]));

		$this->assertSame($language->name, $this->logRows()[0]['language']);
	}

	public function testSendLogsCurrentUserLanguage(): void
	{
		$this->assertTrue($this->service()->send(['to' => 'lang@phpunit.test', 'template' => 'basic']));

		$expected = $this->service()->hasLanguageSupport() ? $this->defaultLanguage()->name : '';
		$this->assertSame($expected, $this->logRows()[0]['language']);
	}

	public function testFailedSendLogsLanguage(): void
	{
		$language = $this->otherLanguage();
		$this->mailResult = 0;

		$this->assertFalse($this->service()->send(['to' => 'lang@phpunit.test', 'template' => 'basic', 'language' => $language->id]));

		$row = $this->logRows()[0];
		$this->assertSame('failed', $row['status']);
		$this->assertSame($language->name, $row['language']);
	}

	public function testResendKeepsLanguage(): void
	{
		$id = $this->insertLogRow(['to_email' => 'resend@phpunit.test', 'language' => 'stored-language']);
		$entry = $this->callMethod($this->process(), 'getEntry', [$id]);

		$this->assertTrue($this->service()->resend($entry));

		$rows = $this->logRows('resend@phpunit.test');
		$this->assertCount(2, $rows);
		$this->assertSame('stored-language', $rows[1]['language']);
	}

	public function testDirectWireMailLogsNoLanguage(): void
	{
		$this->wire()->mail->new()->to('direct@phpunit.test')->subject('Direct')->body('Plain')->send();

		$this->assertSame('', $this->logRows()[0]['language'], 'The language of a mail sent directly via WireMail is unknown.');
	}

	public function testGetLanguageLabel(): void
	{
		$process = $this->process();

		$this->assertSame('', $process->getLanguageLabel(''));
		$this->assertSame('deleted-language', $process->getLanguageLabel('deleted-language'), 'Unknown languages show their name.');

		foreach ($this->service()->getLanguages() as $language) {
			$this->assertSame($this->languageLabel($language), $process->getLanguageLabel($language->name));
		}
	}

	/* ***** Detail *** */

	public function testDetailStateDefaults(): void
	{
		$this->assertSame(['format' => 'html', 'size' => 'desktop'], $this->process()->getDetailState());
	}

	public function testDetailStateReadsValidAndRejectsInvalidValues(): void
	{
		$this->setGet(['format' => 'text', 'size' => 'mobile']);
		$this->assertSame(['format' => 'text', 'size' => 'mobile'], $this->process()->getDetailState());

		foreach ([['pdf', 'huge'], ['TEXT', 'Tablet'], ['', ''], ['../text', 'mobile<']] as [$format, $size]) {
			$this->setGet(['format' => $format, 'size' => $size]);
			$this->assertSame(['format' => 'html', 'size' => 'desktop'], $this->process()->getDetailState(), "$format / $size");
		}
	}

	public function testDetailPage(): void
	{
		$language = $this->otherLanguage();
		$id = $this->insertLogRow([
			'subject' => 'Subject <b>bold</b>',
			'template' => 'basic',
			'language' => $language->name,
			'status' => 'failed',
		]);
		$this->setGet(['id' => (string) $id, 'format' => 'text', 'size' => 'tablet']);

		$out = $this->process()->executeDetail();

		$this->assertStringContainsString('<dd>' . htmlspecialchars($this->languageLabel($language)) . '</dd>', $out);
		$this->assertStringContainsString('Basic <span class="uk-text-muted">(basic)</span>', $out);
		$this->assertStringContainsString('Subject &lt;b&gt;bold&lt;/b&gt;', $out);
		$this->assertStringNotContainsString('<b>bold</b>', $out);
		$this->assertMatchesRegularExpression('~class="email-status uk-label uk-label-danger[^"]*">\s*Failed~', $out);

		// iframe and box use the view settings
		$this->assertStringContainsString('src="' . $this->adminPage->url . 'iframe/?id=' . $id . '&amp;format=text"', $out);
		$this->assertMatchesRegularExpression('~<div id="email-preview-frame"[^>]*style="width:770px;max-width:100%;~', $out);
		$this->assertStringContainsString('data-state="{&quot;format&quot;:&quot;text&quot;,&quot;size&quot;:&quot;tablet&quot;}"', $out);
		$this->assertMatchesRegularExpression('~<li class="uk-active">\s*<a href="#" data-preview="format" data-value="text"~', $out);
		$this->assertMatchesRegularExpression('~<li class="uk-active">\s*<a href="#" data-preview="size" data-value="tablet"~', $out);

		// the language of a logged email can't be switched
		$this->assertStringNotContainsString('data-preview="language"', $out);
	}

	public function testDetailPageWithoutLanguageAndTemplate(): void
	{
		$id = $this->insertLogRow(['template' => '', 'language' => '']);
		$this->setGet(['id' => (string) $id]);

		$out = $this->process()->executeDetail();

		$this->assertStringContainsString('<dd><span class="uk-text-muted">–</span></dd>', $out);
		$this->assertStringContainsString('<dd><span class="uk-text-muted">(no template)</span></dd>', $out);
		$this->assertMatchesRegularExpression('~class="email-status uk-label uk-label-success[^"]*">\s*Sent~', $out);
		$this->assertStringContainsString('src="' . $this->adminPage->url . 'iframe/?id=' . $id . '&amp;format=html"', $out);
	}

	public function testDetailPageWithUnregisteredTemplate(): void
	{
		$id = $this->insertLogRow(['template' => 'not-registered']);
		$this->setGet(['id' => (string) $id]);

		$this->assertStringContainsString('<dd>not-registered</dd>', $this->process()->executeDetail());
	}

	/* ***** Overview *** */

	public function testOverviewListsEntriesWithLanguage(): void
	{
		$language = $this->otherLanguage();
		$this->insertLogRow(['subject' => 'Row <i>one</i>', 'template' => 'phpunit-view', 'language' => $language->name]);
		$this->insertLogRow(['subject' => 'Row two', 'template' => 'phpunit-view', 'language' => '', 'status' => 'failed']);
		$this->setGet(['template' => 'phpunit-view']);

		$out = $this->process()->execute();

		$this->assertSame(4, substr_count($out, 'detail/?id='), 'Recipient and subject of both rows link to the detail page.');
		$this->assertStringContainsString('Row &lt;i&gt;one&lt;/i&gt;', $out);
		$this->assertStringNotContainsString('<i>one</i>', $out);
		$this->assertStringContainsString('<th>Language</th>', $out);
		$this->assertStringContainsString('<td class="uk-text-nowrap">' . htmlspecialchars($this->languageLabel($language)) . '</td>', $out);
		$this->assertStringContainsString('<td class="uk-text-nowrap">–</td>', $out);
		$this->assertMatchesRegularExpression('~uk-label-danger">\s*Failed~', $out);
		$this->assertMatchesRegularExpression('~uk-label-success">\s*Sent~', $out);
	}

	public function testOverviewStatusFilterKeepsTemplateFilter(): void
	{
		$this->insertLogRow(['template' => 'phpunit-view', 'status' => 'failed']);
		$this->setGet(['template' => 'phpunit-view', 'status' => 'failed']);

		$out = $this->process()->execute();
		$base = $this->adminPage->url;

		$this->assertMatchesRegularExpression('~<li class="uk-active">\s*<a href="' . preg_quote($base . '?status=failed&amp;template=phpunit-view', '~') . '">Failed</a>~', $out);
		$this->assertStringContainsString('<a href="' . $base . '?template=phpunit-view">All</a>', $out);
		$this->assertStringContainsString('<a href="' . $base . '?status=sent&amp;template=phpunit-view">Sent</a>', $out);
		// the template form keeps the status
		$this->assertStringContainsString('<input type="hidden" name="status" value="failed">', $out);
		$this->assertStringContainsString('Delete filtered logs', $out);
	}

	public function testOverviewWithoutEntries(): void
	{
		$this->setGet(['template' => 'phpunit-nothing']);

		$out = $this->process()->execute();

		$this->assertStringContainsString('No emails found.', $out);
		$this->assertStringNotContainsString('<table', $out);
		$this->assertStringNotContainsString('submit_delete', $out, 'No delete button without entries.');
	}

	public function testOverviewDeleteFormHasTokenAndEncodedConfirm(): void
	{
		$this->insertLogRow(['template' => 'phpunit-view']);
		$out = $this->process()->execute();

		$csrf = $this->wire()->session->CSRF;
		$this->assertMatchesRegularExpression(
			'~<input type=.hidden. name=.' . preg_quote($csrf->getTokenName(), '~') . '. value=.' . preg_quote($csrf->getTokenValue(), '~') . '.~',
			$out
		);
		$this->assertStringContainsString('data-confirm="Delete all email log entries?"', $out);
		$this->assertStringNotContainsString('onsubmit=', $out);
	}
}
