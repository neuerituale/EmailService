<?php

namespace ProcessWire;

final class ProcessEmailLogTest extends TestCase
{
	private Page $adminPage;

	protected function setUp(): void
	{
		parent::setUp();
		$this->adminPage = $this->useAdminPage();
		$this->registerFixtures();
		if ($this->service()->hasLanguageSupport()) {
			$this->wire()->user->setQuietly('language', $this->wire()->languages->getDefault());
		}
	}

	private function templates(): array
	{
		return $this->service()->getRegisteredTemplates();
	}

	private function currentLanguageId(): int
	{
		return $this->service()->hasLanguageSupport() ? $this->wire()->user->language->id : 0;
	}

	/* ***** Preview state *** */

	public function testPreviewStateDefaults(): void
	{
		$state = $this->process()->getPreviewState($this->templates());

		$this->assertSame([
			'template' => '',
			'format' => 'html',
			'size' => 'desktop',
			'language' => $this->currentLanguageId(),
		], $state);
	}

	public function testPreviewStateReadsValidValues(): void
	{
		$language = $this->otherLanguage();
		$this->setGet(['template' => 'basic', 'format' => 'text', 'size' => 'mobile', 'language' => (string) $language->id]);

		$this->assertSame([
			'template' => 'basic',
			'format' => 'text',
			'size' => 'mobile',
			'language' => $language->id,
		], $this->process()->getPreviewState($this->templates()));
	}

	public function testPreviewStateRejectsInvalidValues(): void
	{
		$invalid = [
			['template' => 'not-registered'],
			['template' => '<script>'],
			['format' => 'pdf'],
			['format' => 'HTML '],
			['size' => 'huge'],
			['size' => '100%'],
			['language' => '999999'],
			['language' => '1'],
			['language' => 'de'],
			['language' => '-5'],
			['language' => '1029abc'],
			['language' => ''],
		];

		foreach ($invalid as $get) {
			$this->setGet($get);
			$state = $this->process()->getPreviewState($this->templates());
			$this->assertSame('', $state['template'], json_encode($get));
			$this->assertSame('html', $state['format'], json_encode($get));
			$this->assertSame('desktop', $state['size'], json_encode($get));
			$this->assertSame($this->currentLanguageId(), $state['language'], json_encode($get));
		}
	}

	public function testPreviewStateUsesCurrentUserLanguageByDefault(): void
	{
		$language = $this->otherLanguage();
		$this->wire()->user->setQuietly('language', $language);

		$this->assertSame($language->id, $this->process()->getPreviewState($this->templates())['language']);
	}

	/* ***** URLs *** */

	public function testPreviewUrlKeepsAllSettings(): void
	{
		$url = $this->process()->getPreviewUrl(['template' => 'shop/order & more', 'format' => 'text', 'size' => 'tablet', 'language' => 1029]);

		$this->assertSame($this->adminPage->url . 'preview/?template=shop%2Forder+%26+more&format=text&size=tablet&language=1029', $url);
	}

	public function testPreviewUrlOmitsEmptyValues(): void
	{
		$url = $this->process()->getPreviewUrl(['template' => 'basic', 'format' => 'html', 'size' => 'desktop', 'language' => 0]);

		$this->assertSame($this->adminPage->url . 'preview/?template=basic&format=html&size=desktop', $url);
	}

	public function testIframeUrlHasNoSize(): void
	{
		$url = $this->process()->getIframeUrl(['template' => 'basic', 'format' => 'text', 'size' => 'mobile', 'language' => 1029]);

		$this->assertSame($this->adminPage->url . 'iframe/?template=basic&format=text&language=1029', $url);
	}

	/* ***** Iframe *** */

	public function testIframeRendersHtml(): void
	{
		$this->setGet(['template' => 'basic']);
		$html = $this->process()->renderIframe();

		$this->assertStringContainsString('<h1>Hello Ada</h1>', $html);
		$this->assertStringContainsString('WRAPPER-FOOTER', $html);
	}

	public function testIframeRendersTextEscaped(): void
	{
		$this->service()->registerTemplate('basic', 'Basic', ['name' => '<script>alert(1)</script>']);
		$this->setGet(['template' => 'basic', 'format' => 'text']);

		$html = $this->process()->renderIframe();

		$this->assertStringContainsString('<meta charset="utf-8">', $html);
		$this->assertStringContainsString('Text for &lt;script&gt;alert(1)&lt;/script&gt;', $html);
		$this->assertStringNotContainsString('<script>', $html);
	}

	public function testIframeTextFallsBackToTextFromHtml(): void
	{
		$this->setGet(['template' => 'no-text', 'format' => 'text']);
		$html = $this->process()->renderIframe();

		$this->assertStringContainsString("Only HTML\n\nFirst paragraph", $html);
		$this->assertStringContainsString('Second &lt;b&gt; paragraph', $html);
	}

	public function testIframeRendersInLanguage(): void
	{
		$language = $this->otherLanguage();
		$user = $this->wire()->user;

		$this->setGet(['template' => 'language', 'language' => (string) $language->id]);
		$this->assertStringContainsString("LANG:$language->name", $this->process()->renderIframe());
		$this->assertStringContainsString("<title>Lang $language->name</title>", $this->process()->renderIframe(), 'Sample data closure runs in the preview language.');

		$this->setGet(['template' => 'language', 'language' => (string) $language->id, 'format' => 'text']);
		$this->assertStringContainsString("TEXT-LANG:$language->name", $this->process()->renderIframe());

		$this->assertSame('default', $user->language->name);
	}

	public function testIframeWithoutTemplateOrUnknownTemplate(): void
	{
		$this->assertSame('', $this->process()->renderIframe());

		$this->setGet(['template' => 'not-registered']);
		$this->assertSame('', $this->process()->renderIframe());
	}

	public function testIframeRendersLoggedEmail(): void
	{
		$id = $this->insertLogRow(['body_html' => '<p>Logged body</p>']);

		$this->setGet(['id' => (string) $id]);
		$this->assertSame('<p>Logged body</p>', $this->process()->renderIframe());

		$this->setGet(['id' => '999999999']);
		$this->assertSame('', $this->process()->renderIframe());
	}

	/* ***** Preview page *** */

	public function testPreviewPageWithoutSelection(): void
	{
		$out = $this->process()->executePreview();

		$this->assertStringContainsString('email-preview-template', $out);
		$this->assertStringNotContainsString('<iframe', $out);
		$this->assertActive($out, 'size', 'desktop');
		$this->assertActive($out, 'format', 'html');
		if ($this->currentLanguageId()) $this->assertActive($out, 'language', (string) $this->currentLanguageId());
	}

	public function testPreviewPageWithoutRegisteredTemplates(): void
	{
		$this->service()->registeredTemplates = [];
		$out = $this->process()->executePreview();

		$this->assertStringContainsString('EmailService::registerTemplates', $out);
		$this->assertStringNotContainsString('<iframe', $out);
	}

	public function testPreviewPageKeepsSettingsInTemplateLinks(): void
	{
		$language = $this->otherLanguage();
		$this->setGet(['template' => 'basic', 'format' => 'text', 'size' => 'tablet', 'language' => (string) $language->id]);

		$out = $this->process()->executePreview();

		// every template link carries format, size and language
		$base = $this->adminPage->url . 'preview/?template=';
		foreach (['basic', 'no-text', 'language', 'missing-file'] as $name) {
			$this->assertStringContainsString(
				'href="' . $base . $name . '&amp;format=text&amp;size=tablet&amp;language=' . $language->id . '"',
				$out,
				$name
			);
		}

		// iframe uses the state
		$this->assertStringContainsString('src="' . $this->adminPage->url . 'iframe/?template=basic&amp;format=text&amp;language=' . $language->id . '"', $out);
		$this->assertMatchesRegularExpression('~<div id="email-preview-frame"[^>]*style="width:770px;~', $out);

		// active states
		$this->assertActive($out, 'size', 'tablet');
		$this->assertActive($out, 'format', 'text');
		$this->assertActive($out, 'language', (string) $language->id);
		$this->assertMatchesRegularExpression('~<li class="uk-active">\s*<a href="[^"]*template=basic&amp;[^"]*" class="email-preview-template" aria-current="page"~', $out);

		// initial JS state
		$this->assertStringContainsString('{"format":"text","size":"tablet","language":' . $language->id . '}', $out);
	}

	public function testPreviewPageListsAllLanguages(): void
	{
		$this->otherLanguage();
		$out = $this->process()->executePreview();

		foreach ($this->service()->getLanguages() as $id => $language) {
			$this->assertStringContainsString('data-preview="language" data-value="' . $id . '"', $out);
		}
	}

	public function testPreviewPageTextHint(): void
	{
		$this->setGet(['template' => 'no-text', 'format' => 'text']);
		$out = $this->process()->executePreview();
		$this->assertMatchesRegularExpression('~<div id="email-preview-text-hint"[^>]*>~', $out);
		$this->assertDoesNotMatchRegularExpression('~<div id="email-preview-text-hint"[^>]*hidden~', $out);
		$this->assertStringContainsString('var hasTextTemplate = false;', $out);

		$this->setGet(['template' => 'basic', 'format' => 'text']);
		$out = $this->process()->executePreview();
		$this->assertMatchesRegularExpression('~<div id="email-preview-text-hint"[^>]*hidden~', $out);
		$this->assertStringContainsString('var hasTextTemplate = true;', $out);

		// html format: hint hidden, even without text template
		$this->setGet(['template' => 'no-text', 'format' => 'html']);
		$this->assertMatchesRegularExpression('~<div id="email-preview-text-hint"[^>]*hidden~', $this->process()->executePreview());
	}

	public function testPreviewPageWithUnrenderableTemplate(): void
	{
		$this->setGet(['template' => 'missing-file']);
		$out = $this->process()->executePreview();

		$this->assertStringContainsString('uk-alert-warning', $out);
		$this->assertStringNotContainsString('<iframe', $out);
	}

	public function testPreviewPageEscapesLabels(): void
	{
		$this->service()->registerTemplate('basic', '<img src=x onerror=alert(1)>', []);
		$out = $this->process()->executePreview();

		$this->assertStringContainsString('&lt;img src=x onerror=alert(1)&gt;', $out);
		$this->assertStringNotContainsString('<img src=x', $out);
	}

	public function testPreviewPageLanguageSwitchOnlyWithSeveralLanguages(): void
	{
		$out = $this->process()->executePreview();
		if (count($this->service()->getLanguages()) > 1) {
			$this->assertStringContainsString('data-preview="language"', $out);
		} else {
			$this->assertStringNotContainsString('data-preview="language"', $out);
		}
	}

	public function testPreviewPageShowsTemplateCount(): void
	{
		$this->assertMatchesRegularExpression('~<span class="[^"]*\buk-badge\b[^"]*">4</span>~', $this->process()->executePreview());
	}

	public function testPreviewBoxTakesDeviceWidth(): void
	{
		foreach (ProcessEmailLog::previewSizes as $size => $width) {
			$this->setGet(['template' => 'basic', 'size' => $size]);
			$out = $this->process()->executePreview();

			// the box gets the device width, the iframe fills the box
			$this->assertMatchesRegularExpression('~<div id="email-preview-frame"[^>]*style="width:' . preg_quote($width, '~') . ';max-width:100%;~', $out, $size);
			$this->assertMatchesRegularExpression('~<iframe id="email-preview-iframe"[^>]*style="display:block;width:100%;~s', $out, $size);
		}
	}

	/**
	 * Exactly one control of a group is active, and it is the given one.
	 */
	private function assertActive(string $out, string $control, string $value): void
	{
		$pattern = '~<li class="uk-active">\s*<a href="#" data-preview="' . $control . '" data-value="([^"]*)"~';
		$this->assertSame(1, preg_match_all($pattern, $out, $m), "Exactly one active $control");
		$this->assertSame($value, $m[1][0], "Active $control");
	}

	/* ***** Warnings *** */

	public function testDependencyWarningsOnlyForSuperusers(): void
	{
		$this->setProperty($this->service(), 'inlinerSource', false);
		$notices = $this->wire()->notices;
		// identical notices are not added twice, so start without any
		$notices->removeAll();
		// get the module as superuser, guests may not load it
		$process = $this->process();

		$this->loginAsGuest();
		$before = count($notices);
		$this->callMethod($process, 'showDependencyWarnings');
		$this->assertCount($before, $notices);

		$this->loginAsSuperuser();
		$this->callMethod($process, 'showDependencyWarnings');
		$this->assertCount($before + 1, $notices);
		$this->assertInstanceOf(NoticeWarning::class, $notices->last());
		$this->assertStringContainsString('pelago/emogrifier', $notices->last()->text);
	}

	/* ***** Page title *** */

	public function testTranslatePageTitle(): void
	{
		$language = $this->otherLanguage();
		$process = $this->process();
		$expected = $this->service()->withLanguage($language, fn() => __('Email Log', $this->wire()->config->paths->root . 'site/modules/EmailService/ProcessEmailLog.module'));
		if ($expected === 'Email Log') $this->markTestSkipped('No translation of "Email Log" imported.');

		$page = $this->adminPage;
		$page->of(false);
		$page->setLanguageValue($language, 'title', '');
		$page->save('title', ['quiet' => true]);

		$this->assertGreaterThanOrEqual(1, $process->translatePageTitle());
		$this->assertSame($expected, $this->freshTitle($language));

		// existing titles are not overwritten
		$this->assertSame(0, $process->translatePageTitle());

		$page = $this->wire()->pages->getFresh($page->id);
		$page->of(false);
		$page->setLanguageValue($language, 'title', 'Custom');
		$page->save('title', ['quiet' => true]);
		$this->assertSame(0, $process->translatePageTitle());
		$this->assertSame('Custom', $this->freshTitle($language));

		// the transaction is rolled back, the cached page must not keep the test values
		$this->wire()->pages->uncache($page);
		$this->wire()->pages->uncache($this->adminPage);
	}

	private function freshTitle(Language $language): string
	{
		$page = $this->wire()->pages->getFresh($this->adminPage->id);
		$page->of(false);
		return (string) $page->getLanguageValue($language, 'title');
	}
}
