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
		$cases = [
			'email/' => 'email/',
			'email' => 'email/',
			'/site/templates/email/' => 'email/',
			'site/templates/email/' => 'site/templates/email/',
			'mail/de' => 'mail/de/',
			'' => '',
			'/' => '',
			'  email  ' => 'email/',
			'../../etc' => 'etc/',
			'./a/../b' => 'a/b/',
			'a\\b' => 'a/b/',
			'a//b///' => 'a/b/',
		];
		foreach ($cases as $input => $expected) {
			$this->assertSame($expected, $this->callMethod($this->service(), 'normalizeTemplatePath', [(string) $input]), "Input: '$input'");
		}
	}

	public function testGetTemplatePath(): void
	{
		$this->assertSame($this->fixturesPath() . 'email/', $this->service()->getTemplatePath());

		$this->service()->templatePath = '';
		$this->assertSame($this->fixturesPath(), $this->service()->getTemplatePath());
	}

	/* ***** Dependencies *** */

	public function testAutoloadCandidates(): void
	{
		$config = $this->wire()->config;
		$service = $this->service();
		$moduleDir = dirname(__DIR__);

		$candidates = $service->getAutoloadCandidates();
		$this->assertSame($moduleDir . '/vendor/autoload.php', $candidates[0]);
		$this->assertContains($config->paths->site . 'vendor/autoload.php', $candidates);
		$this->assertContains($config->paths->root . 'vendor/autoload.php', $candidates);
		$this->assertSame(array_values(array_unique($candidates)), $candidates);

		$service->autoloadFile = 'custom/vendor/autoload.php';
		$this->assertSame($config->paths->root . 'custom/vendor/autoload.php', $service->getAutoloadCandidates()[0]);

		$service->autoloadFile = '/custom/vendor/autoload.php';
		$this->assertSame($config->paths->root . 'custom/vendor/autoload.php', $service->getAutoloadCandidates()[0], 'A missing absolute path is treated as relative to the root.');

		$service->autoloadFile = $moduleDir . '/vendor/autoload.php';
		$this->assertSame($moduleDir . '/vendor/autoload.php', $service->getAutoloadCandidates()[0]);
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

	public function testDependencyWarningForMissingConfiguredAutoloadFile(): void
	{
		$service = $this->service();
		$service->autoloadFile = 'no/such/autoload.php';

		$warnings = $service->getDependencyWarnings();

		$this->assertNotEmpty($warnings);
		$this->assertStringContainsString('no/such/autoload.php', $warnings[0]);
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
		$this->assertNotNull($inputfields->getChildByName('autoloadFile'));
		$found = false;
		foreach ($notices as $i => $notice) {
			if ($i < $before) continue;
			if ($notice instanceof NoticeWarning && str_contains($notice->text, 'pelago/emogrifier')) $found = true;
		}
		$this->assertTrue($found, 'A warning notice about the missing inliner is shown.');
	}

	public function testModuleVersions(): void
	{
		$this->assertSame('2.3.0', EmailService::getModuleInfo()['version']);
		$this->assertSame('1.1.0', ProcessEmailLog::getModuleInfo()['version']);
	}
}
