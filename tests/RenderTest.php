<?php

namespace ProcessWire;

final class RenderTest extends TestCase
{
	public function testRenderWrapsContentAndPassesData(): void
	{
		$html = $this->service()->render('basic', ['name' => 'Ada <b>', 'subject' => 'Hi & bye']);

		$this->assertStringContainsString('<h1>Hello Ada &lt;b&gt;</h1>', $html);
		$this->assertStringContainsString('WRAPPER-FOOTER', $html);
		$this->assertStringContainsString('<title>Hi &amp; bye</title>', $html);
	}

	public function testRenderInlinesCss(): void
	{
		if (!$this->service()->hasInliner()) $this->markTestSkipped('pelago/emogrifier is not installed.');

		$html = $this->service()->render('basic', ['name' => 'Ada']);

		$this->assertMatchesRegularExpression('~<div class="wrap" style="color: #123456;?">~', $html);
		$this->assertStringNotContainsString('<style>', $html);
	}

	public function testRenderEmbedsCssWithoutInliner(): void
	{
		$this->setProperty($this->service(), 'inlinerSource', false);

		$html = $this->service()->render('basic', ['name' => 'Ada']);

		$this->assertMatchesRegularExpression('~<style>\s*\.wrap \{ color: #123456; \}\s*</style>\s*</head>~', $html);
		$this->assertCount(1, $this->logLines);
		$this->assertStringContainsString('emogrifier not found', $this->logLines[0]);

		// warning is logged only once per request
		$this->service()->render('basic', ['name' => 'Ada']);
		$this->assertCount(1, $this->logLines);
	}

	public function testRenderWithoutWrapper(): void
	{
		$this->service()->wrapperFile = '';
		$html = $this->service()->render('basic', ['name' => 'Ada']);

		$this->assertStringStartsWith('<h1>Hello Ada</h1>', $html);
		$this->assertStringNotContainsString('WRAPPER-FOOTER', $html);
	}

	public function testRenderWithMissingWrapperFileReturnsContent(): void
	{
		$this->service()->wrapperFile = 'does-not-exist.php';
		$html = $this->service()->render('basic', ['name' => 'Ada']);

		$this->assertStringStartsWith('<h1>Hello Ada</h1>', $html);
	}

	public function testRenderWithoutCssFile(): void
	{
		$this->service()->cssFile = '';
		$html = $this->service()->render('basic', ['name' => 'Ada']);

		$this->assertStringContainsString('<div class="wrap">', $html);
		$this->assertStringNotContainsString('#123456', $html);
	}

	public function testRenderMissingTemplateReturnsEmptyStringAndLogs(): void
	{
		$this->assertSame('', $this->service()->render('does-not-exist'));
		$this->assertCount(1, $this->logLines);
		$this->assertStringContainsString('Template not found', $this->logLines[0]);
		$this->assertStringContainsString('does-not-exist.php', $this->logLines[0]);
	}

	public function testRenderEmptyTemplateName(): void
	{
		$this->assertSame('', $this->service()->render(''));
		$this->assertSame('', $this->service()->renderText(''));
		$this->assertFalse($this->service()->hasTemplate(''));
	}

	public function testRenderTemplateInSubdirectory(): void
	{
		$this->service()->wrapperFile = '';
		$this->assertSame('<p>NESTED</p>', trim($this->service()->render('sub/nested')));
	}

	public function testTemplateNamesCannotLeaveTheTemplateDirectory(): void
	{
		// fixtures/templates/outside.php exists one level above the email directory
		$this->assertFileExists($this->fixturesPath() . 'outside.php');

		foreach (['../outside', '../../templates/outside', '/../outside', '..\\outside', './../outside'] as $name) {
			$this->assertSame('', $this->service()->render($name), $name);
			$this->assertFalse($this->service()->hasTemplate($name), $name);
		}
	}

	public function testRenderPropagatesTemplateExceptions(): void
	{
		$this->expectException(WireException::class);
		$this->expectExceptionMessage('Fixture template failure');
		$this->service()->render('throws');
	}

	public function testRenderTextIsTrimmed(): void
	{
		$this->assertSame('Text for Ada', $this->service()->renderText('basic', ['name' => 'Ada']));
	}

	public function testRenderTextWithoutTextTemplateIsEmpty(): void
	{
		$this->assertSame('', $this->service()->renderText('no-text'));
		$this->assertSame('', $this->service()->renderText('does-not-exist'));
		$this->assertSame([], $this->logLines, 'A missing text template is not an error.');
	}

	public function testRenderTextBodyUsesTextTemplate(): void
	{
		$this->assertSame('Text for Ada', $this->service()->renderTextBody('basic', ['name' => 'Ada']));
	}

	public function testRenderTextBodyFallsBackToTextFromHtml(): void
	{
		$text = $this->service()->renderTextBody('no-text');

		$this->assertSame("Only HTML\n\nFirst paragraph\n\nSecond <b> paragraph\n\nWRAPPER-FOOTER", $text);
	}

	public function testRenderTextBodyOfMissingTemplateIsEmpty(): void
	{
		$this->assertSame('', $this->service()->renderTextBody('does-not-exist'));
	}

	public function testHasTemplateAndHasTextTemplate(): void
	{
		$service = $this->service();
		$this->assertTrue($service->hasTemplate('basic'));
		$this->assertTrue($service->hasTextTemplate('basic'));
		$this->assertTrue($service->hasTemplate('no-text'));
		$this->assertFalse($service->hasTextTemplate('no-text'));
		$this->assertFalse($service->hasTemplate('does-not-exist'));
		$this->assertFalse($service->hasTextTemplate('does-not-exist'));
	}

	public function testHtmlToText(): void
	{
		$html = '<html><head><title>T</title><style>p{color:red}</style></head><body>'
			. '<script>alert(1)</script><h1>Head</h1><p>A<br>B<br/>C</p>'
			. '<table><tr><td>Cell 1</td></tr><tr><td>Cell 2</td></tr></table>'
			. '<p>Fish &amp; Chips &quot;x&quot; &#039;y&#039;</p>   <p>  spaced    out  </p>'
			. '<p></p><p></p><p></p><p>End</p></body></html>';

		$text = $this->callMethod($this->service(), 'htmlToText', [$html]);

		$this->assertSame("Head\n\nA\nB\nC\n\nCell 1\nCell 2\nFish & Chips \"x\" 'y'\n\nspaced out\n\nEnd", $text);
	}

	public function testHtmlToTextOfEmptyString(): void
	{
		$this->assertSame('', $this->callMethod($this->service(), 'htmlToText', ['']));
	}
}
