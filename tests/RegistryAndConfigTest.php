<?php

namespace ProcessWire;

final class RegistryAndConfigTest extends TestCase
{
	/* ***** Registry *** */

	public function testRegisterTemplate(): void
	{
		$service = $this->service();
		$service->registerTemplate('basic', 'Basic', ['a' => 1]);
		$service->registerTemplate('basic', 'Basic again', ['b' => 2]);

		$templates = $service->getRegisteredTemplates();
		$this->assertSame(['basic'], array_keys($templates));
		$this->assertSame(['name' => 'basic', 'label' => 'Basic again', 'sampleData' => ['b' => 2]], $templates['basic']);
	}

	public function testRegisterTemplatesHookRunsOnce(): void
	{
		$service = $this->service();
		$this->setProperty($service, 'templatesRegistered', false);

		$calls = 0;
		$this->addHook('EmailService::registerTemplates', function(HookEvent $event) use (&$calls) {
			$calls++;
			$event->object->registerTemplate('hooked', 'Hooked', []);
		});

		$this->assertArrayHasKey('hooked', $service->getRegisteredTemplates());
		$this->assertArrayHasKey('hooked', $service->getRegisteredTemplates());
		$this->assertSame(1, $calls);
	}

	public function testGetSampleData(): void
	{
		$service = $this->service();
		$service->registerTemplate('array', 'Array', ['a' => 1]);
		$service->registerTemplate('closure', 'Closure', fn() => ['b' => 2]);

		$this->assertSame(['a' => 1], $service->getSampleData('array'));
		$this->assertSame(['b' => 2], $service->getSampleData('closure'));
		$this->assertSame([], $service->getSampleData('not-registered'));
		$this->assertSame([], $this->logLines);
	}

	public function testGetSampleDataClosureIsCalledOnEveryRequest(): void
	{
		$service = $this->service();
		$n = 0;
		$service->registerTemplate('counter', 'Counter', function() use (&$n) {
			return ['n' => ++$n];
		});

		$this->assertSame(['n' => 1], $service->getSampleData('counter'));
		$this->assertSame(['n' => 2], $service->getSampleData('counter'));
	}

	public function testGetSampleDataWithInvalidClosureResult(): void
	{
		$service = $this->service();
		$service->registerTemplate('string', 'String', fn() => 'not an array');
		$service->registerTemplate('null', 'Null', fn() => null);

		$this->assertSame([], $service->getSampleData('string'));
		$this->assertSame([], $service->getSampleData('null'));
		$this->assertCount(2, $this->logLines);
		$this->assertStringContainsString("'string' must return an array, got string", $this->logLines[0]);
		$this->assertStringContainsString("'null' must return an array, got null", $this->logLines[1]);
	}

	public function testGetSampleDataWithThrowingClosure(): void
	{
		$service = $this->service();
		$service->registerTemplate('throws', 'Throws', function() {
			throw new \LogicException('sample failure');
		});

		$this->assertSame([], $service->getSampleData('throws'));
		$this->assertStringContainsString('sample failure', $this->logLines[0]);
	}

	public function testRegisterTemplateRejectsStringCallables(): void
	{
		// a string such as 'phpinfo' must never be called as sample data
		$this->expectException(\TypeError::class);
		$this->service()->registerTemplate('x', 'X', 'phpinfo');
	}

	/* ***** Template path *** */

	public function testNormalizeTemplatePath(): void
	{
		$root = $this->wire()->config->paths->root;
		$cases = [
			'site/templates/email/' => 'site/templates/email/',
			'site/templates/email' => 'site/templates/email/',
			'/site/templates/email/' => 'site/templates/email/',
			'mail/de' => 'mail/de/',
			'' => '',
			'/' => '',
			'  site/mail  ' => 'site/mail/',
			'../../etc' => 'etc/',
			'/../../etc/passwd' => 'etc/passwd/',
			'./a/../b' => 'a/b/',
			'a\\b' => 'a/b/',
			'a//b///' => 'a/b/',
			// absolute path inside the root
			$root . 'site/templates/email' => 'site/templates/email/',
		];
		foreach ($cases as $input => $expected) {
			$this->assertSame($expected, $this->callMethod($this->service(), 'normalizeTemplatePath', [(string) $input]), "Input: '$input'");
		}
	}

	public function testGetTemplatePathIsRelativeToRoot(): void
	{
		$service = $this->service();
		$root = $this->wire()->config->paths->root;

		$this->assertSame($this->fixturesPath() . 'email/', $service->getTemplatePath());

		$service->templatePath = 'site/templates/email/';
		$this->assertSame($root . 'site/templates/email/', $service->getTemplatePath());

		$service->templatePath = '/site/mail/';
		$this->assertSame($root . 'site/mail/', $service->getTemplatePath(), 'A leading slash means the root.');

		$service->templatePath = '../../outside/';
		$this->assertSame($root . 'outside/', $service->getTemplatePath(), 'The path can not leave the root.');

		$service->templatePath = '';
		$this->assertSame($root, $service->getTemplatePath());
	}

	public function testDefaultTemplatePath(): void
	{
		$this->assertSame('site/templates/email/', $this->service()->getDefaults()['templatePath']);
	}

	public function testTemplateNamesCanNotLeaveTheTemplatePath(): void
	{
		$service = $this->service();
		$this->assertTrue($service->hasTemplate('basic'));
		$this->assertTrue($service->hasTemplate('sub/nested'));
		$this->assertFalse($service->hasTemplate('../outside'), 'outside.php lies one level above the template path.');
		$this->assertTrue($service->hasTemplate('/basic'));
	}

	public function testMigrateTemplatePath(): void
	{
		$config = $this->wire()->config;
		$templates = substr($config->paths->templates, strlen($config->paths->root)); // fixtures, relative to the root
		$templatesUrl = $config->urls->templates;
		$cases = [
			// < 2.5.0: relative to /site/templates/
			'email/' => $templates . 'email/',
			'email' => $templates . 'email/',
			'mail/de/' => $templates . 'mail/de/',
			'' => $templates,
			'../../etc' => $templates . 'etc/',
			// < 2.2.0: root-relative with the templates URL
			$templatesUrl . 'email/' => $templates . 'email/',
		];
		foreach ($cases as $input => $expected) {
			$this->assertSame($expected, $this->callMethod($this->service(), 'migrateTemplatePath', [(string) $input]), "Input: '$input'");
		}
	}

	public function testMigratedPathResolvesToTheSameDirectory(): void
	{
		$service = $this->service();
		$config = $this->wire()->config;

		foreach (['email/', 'email', 'sub', $config->urls->templates . 'email/'] as $old) {
			$expected = $config->paths->templates . $this->callMethod($service, 'normalizeRelativePath', [str_replace($config->urls->templates, '', $old)]);
			$service->templatePath = $this->callMethod($service, 'migrateTemplatePath', [$old]);
			$this->assertSame($expected, $service->getTemplatePath(), "Old value: '$old'");
		}
	}

	/* ***** Dependencies *** */

	public function testAutoloadFileIsTheModuleVendor(): void
	{
		$this->assertSame(dirname(__DIR__) . '/vendor/autoload.php', $this->service()->getAutoloadFile());
		$this->assertArrayNotHasKey('autoloadFile', $this->service()->getDefaults(), 'The autoload file is not configurable.');
	}

	public function testInlinerStatusWhenMissingNamesTheModuleVendor(): void
	{
		$service = $this->service();
		$this->setProperty($service, 'inlinerSource', false);

		$this->assertStringContainsString(dirname(__DIR__) . '/vendor/autoload.php', $service->getInlinerStatus());
	}

	public function testNoDependencyWarningsWhenInstalled(): void
	{
		if (!$this->service()->hasInliner()) $this->markTestSkipped('pelago/emogrifier is not installed.');
		$this->assertSame([], $this->service()->getDependencyWarnings());
	}

	public function testDependencyWarningWhenInlinerIsMissing(): void
	{
		$service = $this->service();
		$this->setProperty($service, 'inlinerSource', false);

		$warnings = $service->getDependencyWarnings();

		$this->assertCount(1, $warnings);
		$this->assertStringContainsString('pelago/emogrifier', $warnings[0]);
		$this->assertStringContainsString('composer install --no-dev', $warnings[0]);
		$this->assertStringContainsString(dirname(__DIR__), $warnings[0]);
		$this->assertStringContainsString('composer require pelago/emogrifier', $warnings[0]);
		$this->assertFalse($service->hasInliner());
		$this->assertStringContainsString('not found', $service->getInlinerStatus());
	}

	public function testInlinerStatusWhenAvailable(): void
	{
		$service = $this->service();
		$this->setProperty($service, 'inlinerSource', null);
		if (!$service->hasInliner()) $this->markTestSkipped('pelago/emogrifier is not installed.');

		$this->assertStringContainsString('is available', $service->getInlinerStatus());
	}

	public function testModuleConfigShowsDependencyWarnings(): void
	{
		$service = $this->service();
		$this->setProperty($service, 'inlinerSource', false);
		$notices = $this->wire()->notices;
		// identical notices are not added twice, so start without any
		$notices->removeAll();
		$before = 0;

		$inputfields = $service->getModuleConfigInputfields(new InputfieldWrapper());

		$this->assertNotNull($inputfields->getChildByName('templatePath'));
		$this->assertNull($inputfields->getChildByName('autoloadFile'), 'The autoload file is not configurable.');
		$status = $inputfields->getChildByName('inlinerStatus');
		$this->assertInstanceOf(InputfieldMarkup::class, $status);
		$this->assertStringContainsString('pelago/emogrifier not found', $status->value);
		foreach (array_merge(array_keys($service->getDefaults()), ['inlinerStatus']) as $name) {
			$field = $inputfields->getChildByName($name);
			$this->assertNotNull($field, $name);
			$this->assertSame(Inputfield::collapsedNever, $field->collapsed, "$name can not be collapsed");
		}
		$found = false;
		foreach ($notices as $i => $notice) {
			if ($i < $before) continue;
			if ($notice instanceof NoticeWarning && str_contains($notice->text, 'pelago/emogrifier')) $found = true;
		}
		$this->assertTrue($found, 'A warning notice about the missing inliner is shown.');
	}

	public function testModuleVersions(): void
	{
		$this->assertSame('2.6.0', EmailService::getModuleInfo()['version']);
		$this->assertSame('1.2.0', ProcessEmailLog::getModuleInfo()['version']);
	}
}
