<?php

namespace ProcessWire;

use PHPUnit\Framework\TestCase as BaseTestCase;

/**
 * Base class for EmailService integration tests.
 *
 * For every test it:
 * - starts a database transaction and rolls it back afterwards
 * - intercepts WireMail::send, so no email ever leaves the machine
 * - intercepts the email-service log, so the real log file stays untouched
 * - points the template directory to tests/fixtures/templates/email/
 * - restores module settings, user, user language, page and GET input
 */
abstract class TestCase extends BaseTestCase
{
	/** @var WireMail[] Mails passed to WireMail::send during the test */
	protected array $sentMails = [];

	/** @var int Return value of the intercepted WireMail::send (number of recipients) */
	protected int $mailResult = 1;

	/** @var string[] Lines written to the email-service log during the test */
	protected array $logLines = [];

	/** @var string[] Hook ids added by the test */
	private array $hookIds = [];

	private array $savedSettings = [];
	private string $savedTemplatesPath = '';
	private ?User $savedUser = null;
	private ?Page $savedPage = null;
	private mixed $savedLanguage = null;
	private array $savedGet = [];
	private array $savedPost = [];
	private array $savedRegisteredTemplates = [];
	private mixed $savedInlinerSource = null;
	private bool $startedTransaction = false;
	private ?User $testUser = null;
	private mixed $testUserLanguage = null;

	protected function wire(): ProcessWire
	{
		return wire();
	}

	protected function service(): EmailService
	{
		/** @var EmailService $service */
		$service = $this->wire()->modules->get('EmailService');
		return $service;
	}

	protected function process(): ProcessEmailLog
	{
		/** @var ProcessEmailLog $process */
		$process = $this->wire()->modules->get('ProcessEmailLog');
		return $process;
	}

	protected function fixturesPath(): string
	{
		return __DIR__ . '/fixtures/templates/';
	}

	/**
	 * Template path of the fixtures relative to the ProcessWire root (module setting).
	 */
	protected function fixturesTemplatePath(): string
	{
		$root = $this->wire()->config->paths->root;
		$path = $this->fixturesPath() . 'email/';
		$this->assertStringStartsWith($root, $path, 'The fixtures must be inside the ProcessWire root.');
		return substr($path, strlen($root));
	}

	protected function setUp(): void
	{
		parent::setUp();
		$wire = $this->wire();
		$service = $this->service();

		// never send real emails
		$mail = $wire->mail->new();
		$this->assertInstanceOf(WireMail::class, $mail);
		$this->assertTrue(method_exists($mail, '___send'), get_class($mail) . '::send() is not hookable, tests could send real emails.');

		$this->hookIds[] = $wire->addHookBefore('WireMail::send', function(HookEvent $event) {
			$this->sentMails[] = $event->object;
			$event->replace = true;
			$event->return = $this->mailResult;
		}, ['priority' => 1]);

		$this->hookIds[] = $wire->addHookBefore('WireLog::save', function(HookEvent $event) {
			if ($event->arguments(0) !== EmailService::logName) return;
			$this->logLines[] = (string) $event->arguments(1);
			$event->replace = true;
			$event->return = true;
		}, ['priority' => 1]);

		// database
		$database = $wire->database;
		$this->assertSame('INNODB', $this->tableEngine(EmailService::logTable), 'The email log table must support transactions (InnoDB).');
		if (!$database->inTransaction()) {
			$database->beginTransaction();
			$this->startedTransaction = true;
		}

		// module settings
		foreach (array_keys($service->getDefaults()) as $key) {
			$this->savedSettings[$key] = $service->get($key);
		}
		$service->setArray([
			'templatePath' => $this->fixturesTemplatePath(),
			'wrapperFile' => 'wrapper.php',
			'cssFile' => 'wrapper.css',
			'defaultFrom' => 'sender@example.test',
			'defaultFromName' => 'Test Sender',
			'retainDays' => 90,
		]);

		$this->savedTemplatesPath = $wire->config->paths->templates;
		$wire->config->paths->set('templates', $this->fixturesPath());

		$this->savedRegisteredTemplates = $service->registeredTemplates;
		$this->savedInlinerSource = $this->getProperty($service, 'inlinerSource');
		$service->registeredTemplates = [];
		$this->setProperty($service, 'templatesRegistered', true);

		// request state
		$this->savedUser = $wire->user;
		$this->savedLanguage = $wire->user->get('language');
		$this->savedPage = $wire->page instanceof Page ? $wire->page : null;
		$this->savedGet = $wire->input->get->getArray();
		$wire->input->get->removeAll();
		$this->savedPost = $wire->input->post->getArray();
		$wire->input->post->removeAll();

		// admin context: ProcessEmailLog requires the email-log-view permission
		$this->loginAsSuperuser();

		// user objects are cached: remember the language of the test user and
		// start every test in the default language, so tests can't affect each other
		$this->testUser = $wire->user;
		$this->testUserLanguage = $wire->user->get('language');
		if ($service->hasLanguageSupport()) {
			$wire->user->setQuietly('language', $wire->languages->getDefault());
		}
	}

	protected function tearDown(): void
	{
		$wire = $this->wire();
		$service = $this->service();

		$wire->input->get->removeAll();
		foreach ($this->savedGet as $key => $value) $wire->input->get->set($key, $value);
		$wire->input->post->removeAll();
		foreach ($this->savedPost as $key => $value) $wire->input->post->set($key, $value);

		if ($this->testUser) $this->testUser->setQuietly('language', $this->testUserLanguage);
		if ($this->savedUser) $wire->users->setCurrentUser($this->savedUser);
		$wire->user->setQuietly('language', $this->savedLanguage);
		if ($this->savedPage) $wire->wire('page', $this->savedPage);

		$wire->config->paths->set('templates', $this->savedTemplatesPath);
		$service->setArray($this->savedSettings);
		$service->registeredTemplates = $this->savedRegisteredTemplates;
		$this->setProperty($service, 'templatesRegistered', count($this->savedRegisteredTemplates) > 0);
		$this->setProperty($service, 'inlinerSource', $this->savedInlinerSource);

		if ($this->startedTransaction && $wire->database->inTransaction()) {
			$wire->database->rollBack();
		}
		$this->startedTransaction = false;

		foreach ($this->hookIds as $id) $wire->removeHook($id);
		$this->hookIds = [];

		parent::tearDown();
	}

	/* ***** Helpers *** */

	/**
	 * Get the storage engine of a table (WireDatabasePDO::supportsTransaction() reads
	 * the wrong column name for tables, so it is checked here directly).
	 */
	protected function tableEngine(string $table): string
	{
		$stmt = $this->wire()->database->prepare('SHOW TABLE STATUS WHERE `Name` = :name');
		$stmt->execute([':name' => $table]);
		$row = array_change_key_case((array) $stmt->fetch(\PDO::FETCH_ASSOC), CASE_LOWER);
		$stmt->closeCursor();
		return strtoupper((string) ($row['engine'] ?? ''));
	}

	/**
	 * Add a hook that is removed after the test.
	 */
	protected function addHook(string $method, callable $callback, bool $before = false): void
	{
		$this->hookIds[] = $before
			? $this->wire()->addHookBefore($method, $callback)
			: $this->wire()->addHookAfter($method, $callback);
	}

	protected function getProperty(object $object, string $name): mixed
	{
		$property = new \ReflectionProperty($object, $name);
		return $property->getValue($object);
	}

	protected function setProperty(object $object, string $name, mixed $value): void
	{
		$property = new \ReflectionProperty($object, $name);
		$property->setValue($object, $value);
	}

	protected function callMethod(object $object, string $name, array $args = []): mixed
	{
		$method = new \ReflectionMethod($object, $name);
		return $method->invokeArgs($object, $args);
	}

	/**
	 * Get log table rows written during this test (unique recipient domain).
	 *
	 * @return array[]
	 */
	protected function logRows(string $to = ''): array
	{
		$sql = "SELECT * FROM `" . EmailService::logTable . "` WHERE `to_email` LIKE :to ORDER BY `id`";
		$stmt = $this->wire()->database->prepare($sql);
		$stmt->execute([':to' => $to !== '' ? $to : '%@phpunit.test']);
		return $stmt->fetchAll(\PDO::FETCH_ASSOC);
	}

	protected function insertLogRow(array $values): int
	{
		$values = array_merge([
			'to_email' => 'row@phpunit.test',
			'from_email' => 'sender@example.test',
			'subject' => 'Subject',
			'template' => '',
			'language' => '',
			'body_html' => '<p>HTML</p>',
			'body_text' => 'Text',
			'status' => 'sent',
			'created' => time(),
		], $values);

		$database = $this->wire()->database;
		$columns = array_keys($values);
		$sql = "INSERT INTO `" . EmailService::logTable . "` (`" . implode('`, `', $columns) . "`)"
			. " VALUES (:" . implode(', :', $columns) . ")";
		$stmt = $database->prepare($sql);
		foreach ($values as $key => $value) $stmt->bindValue(":$key", $value);
		$stmt->execute();
		return (int) $database->lastInsertId();
	}

	/**
	 * Get a non-default language, or skip the test.
	 */
	protected function otherLanguage(): Language
	{
		$languages = $this->service()->getLanguages();
		foreach ($languages as $language) {
			if (!$language->isDefault()) return $language;
		}
		$this->markTestSkipped('Requires LanguageSupport with at least two languages.');
	}

	protected function defaultLanguage(): Language
	{
		if (!$this->service()->hasLanguageSupport()) $this->markTestSkipped('Requires LanguageSupport.');
		return $this->wire()->languages->getDefault();
	}

	protected function loginAsSuperuser(): void
	{
		$wire = $this->wire();
		$wire->users->setCurrentUser($wire->users->get($wire->config->superUserPageID));
	}

	protected function loginAsGuest(): void
	{
		$wire = $this->wire();
		$wire->users->setCurrentUser($wire->users->getGuestUser());
	}

	/**
	 * Make the email log admin page the current page (used for URLs).
	 */
	protected function useAdminPage(): Page
	{
		$wire = $this->wire();
		$moduleId = $wire->modules->getModuleID('ProcessEmailLog');
		$page = $wire->pages->get("template=admin, process=$moduleId, include=all");
		$this->assertTrue($page->id > 0, 'Admin page of ProcessEmailLog not found.');
		$wire->wire('page', $page);
		return $page;
	}

	protected function setGet(array $values): void
	{
		$get = $this->wire()->input->get;
		$get->removeAll();
		foreach ($values as $key => $value) $get->set($key, $value);
	}

	protected function setPost(array $values): void
	{
		$post = $this->wire()->input->post;
		$post->removeAll();
		foreach ($values as $key => $value) $post->set($key, $value);
	}

	/**
	 * Register the fixture templates.
	 */
	protected function registerFixtures(): void
	{
		$service = $this->service();
		$service->registerTemplate('basic', 'Basic', ['name' => 'Ada', 'subject' => 'Basic subject']);
		$service->registerTemplate('no-text', 'No text', []);
		$service->registerTemplate('language', 'Language', fn() => ['subject' => 'Lang ' . $this->wire()->user->language->name]);
		$service->registerTemplate('missing-file', 'Missing file', []);
	}
}
