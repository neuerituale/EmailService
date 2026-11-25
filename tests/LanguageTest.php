<?php

namespace ProcessWire;

final class LanguageTest extends TestCase
{
	protected function setUp(): void
	{
		parent::setUp();
		if (!$this->service()->hasLanguageSupport()) $this->markTestSkipped('Requires LanguageSupport.');
		// tests start in the default language
		$this->wire()->user->setQuietly('language', $this->wire()->languages->getDefault());
	}

	public function testGetLanguagesIsIndexedById(): void
	{
		$languages = $this->service()->getLanguages();

		$this->assertNotEmpty($languages);
		foreach ($languages as $id => $language) {
			$this->assertInstanceOf(Language::class, $language);
			$this->assertSame($language->id, $id);
		}
		$this->assertSame(count($this->wire()->languages), count($languages));
	}

	public function testResolveLanguageWithoutValueReturnsNull(): void
	{
		foreach ([null, '', 0] as $value) {
			$this->assertNull($this->service()->resolveLanguage($value));
		}
	}

	public function testResolveLanguageByObjectIdNumericStringAndName(): void
	{
		$language = $this->otherLanguage();
		$service = $this->service();

		$this->assertSame($language->id, $service->resolveLanguage($language)->id);
		$this->assertSame($language->id, $service->resolveLanguage($language->id)->id);
		$this->assertSame($language->id, $service->resolveLanguage((string) $language->id)->id);
		$this->assertSame($language->id, $service->resolveLanguage($language->name)->id);
		$this->assertSame($this->defaultLanguage()->id, $service->resolveLanguage('default')->id);
	}

	public function testResolveUnknownLanguageThrows(): void
	{
		foreach ([999999, '999999', 'no-such-language', '../de', -1, new Language()] as $value) {
			try {
				$this->service()->resolveLanguage($value);
				$this->fail('No exception for ' . (is_object($value) ? 'new Language' : var_export($value, true)));
			} catch (WireException $e) {
				$this->assertStringContainsString('Unknown language', $e->getMessage());
			}
		}
	}

	public function testResolveLanguageDoesNotAcceptOtherPages(): void
	{
		// the home page exists but is not a language
		$this->expectException(WireException::class);
		$this->service()->resolveLanguage(1);
	}

	public function testWithLanguageSwitchesAndRestores(): void
	{
		$language = $this->otherLanguage();
		$user = $this->wire()->user;
		$before = $user->language;

		$inside = $this->service()->withLanguage($language, fn() => $user->language->id);

		$this->assertSame($language->id, $inside);
		$this->assertSame($before->id, $user->language->id);
		$this->assertFalse($user->isChanged('language'), 'Switching the language must not mark the user as changed.');
	}

	public function testWithLanguageRestoresAfterException(): void
	{
		$language = $this->otherLanguage();
		$user = $this->wire()->user;
		$before = $user->language->id;

		try {
			$this->service()->withLanguage($language, function() {
				throw new \RuntimeException('boom');
			});
			$this->fail('Exception was swallowed.');
		} catch (\RuntimeException $e) {
			$this->assertSame('boom', $e->getMessage());
		}

		$this->assertSame($before, $user->language->id);
	}

	public function testWithLanguageCanBeNested(): void
	{
		$other = $this->otherLanguage();
		$default = $this->defaultLanguage();
		$service = $this->service();
		$user = $this->wire()->user;

		$result = $service->withLanguage($other, function() use ($service, $default, $other, $user) {
			$inner = $service->withLanguage($default, fn() => $user->language->id);
			return [$inner, $user->language->id];
		});

		$this->assertSame([$default->id, $other->id], $result);
		$this->assertSame($default->id, $user->language->id);
	}

	public function testWithLanguageNullKeepsCurrentLanguage(): void
	{
		$user = $this->wire()->user;
		$before = $user->language->id;
		$this->assertSame($before, $this->service()->withLanguage(null, fn() => $user->language->id));
	}

	public function testWithLanguageSameLanguage(): void
	{
		$user = $this->wire()->user;
		$current = $user->language;
		$this->assertSame('ok', $this->service()->withLanguage($current, fn() => 'ok'));
		$this->assertSame($current->id, $user->language->id);
	}

	public function testWithUnknownLanguageThrowsBeforeCallingCallback(): void
	{
		$called = false;
		try {
			$this->service()->withLanguage('no-such-language', function() use (&$called) {
				$called = true;
			});
			$this->fail('No exception thrown.');
		} catch (WireException) {
		}
		$this->assertFalse($called);
	}

	public function testRenderInLanguage(): void
	{
		$language = $this->otherLanguage();
		$service = $this->service();
		$service->wrapperFile = '';
		$user = $this->wire()->user;
		$before = $user->language->id;

		$this->assertSame("<p>LANG:$language->name</p>", trim($service->render('language', [], $language)));
		$this->assertSame("<p>LANG:$language->name</p>", trim($service->render('language', [], $language->name)));
		$this->assertSame("<p>LANG:default</p>", trim($service->render('language')));
		$this->assertSame($before, $user->language->id);
	}

	public function testRenderTextInLanguage(): void
	{
		$language = $this->otherLanguage();
		$this->assertSame("TEXT-LANG:$language->name", $this->service()->renderText('language', [], $language));
		$this->assertSame("TEXT-LANG:$language->name", $this->service()->renderTextBody('language', [], $language));
	}

	public function testRenderTextBodyFallbackInLanguage(): void
	{
		$language = $this->otherLanguage();
		$this->service()->wrapperFile = '';
		// translated.php has no text template, so the text is generated from the translated HTML
		$this->assertSame(
			'TRANSLATED:' . $this->expectedEmailLogTranslation($language),
			$this->service()->renderTextBody('translated', [], $language)
		);
	}

	public function testRenderWithUnknownLanguageReturnsEmptyStringAndLogs(): void
	{
		$service = $this->service();

		$this->assertSame('', $service->render('basic', [], 'no-such-language'));
		$this->assertSame('', $service->renderText('basic', [], 'no-such-language'));
		$this->assertSame('', $service->renderTextBody('basic', [], 'no-such-language'));

		$this->assertNotEmpty($this->logLines);
		foreach ($this->logLines as $line) {
			$this->assertStringContainsString('Unknown language: no-such-language', $line);
		}
	}

	public function testRenderTranslatesStrings(): void
	{
		$language = $this->otherLanguage();
		$service = $this->service();
		$service->wrapperFile = '';

		$this->assertSame('<p>TRANSLATED:Email Log</p>', trim($service->render('translated', [], $this->defaultLanguage())));
		$this->assertSame(
			'<p>TRANSLATED:' . $this->expectedEmailLogTranslation($language) . '</p>',
			trim($service->render('translated', [], $language))
		);
	}

	public function testGetSampleDataCallsClosureInLanguage(): void
	{
		$language = $this->otherLanguage();
		$service = $this->service();
		$service->registerTemplate('closure', 'Closure', fn() => ['lang' => $this->wire()->user->language->name]);

		$this->assertSame(['lang' => $language->name], $service->getSampleData('closure', $language));
		$this->assertSame(['lang' => 'default'], $service->getSampleData('closure'));
	}

	public function testGetSampleDataWithUnknownLanguage(): void
	{
		$service = $this->service();
		$service->registerTemplate('closure', 'Closure', fn() => ['a' => 1]);
		$service->registerTemplate('array', 'Array', ['a' => 1]);

		$this->assertSame([], $service->getSampleData('closure', 'no-such-language'));
		$this->assertCount(1, $this->logLines);
		// array data does not depend on the language
		$this->assertSame(['a' => 1], $service->getSampleData('array', 'no-such-language'));
	}

	/**
	 * The fixture template uses the textdomain of ProcessEmailLog.module.
	 */
	private function expectedEmailLogTranslation(Language $language): string
	{
		$translated = $this->service()->withLanguage($language, fn() => __('Email Log', $this->wire()->config->paths->root . 'site/modules/EmailService/ProcessEmailLog.module'));
		if ($translated === 'Email Log') {
			$this->markTestSkipped("No translation of 'Email Log' in language $language->name. Import languages/de.csv first.");
		}
		return $translated;
	}
}
