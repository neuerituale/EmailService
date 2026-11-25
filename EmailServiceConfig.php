<?php

namespace ProcessWire;

/**
 * Configuration of EmailService.
 *
 * ProcessWire finds this class by its name (<ModuleName>Config in <ModuleName>Config.php),
 * applies getDefaults() to the module and populates the values into the fields of
 * getInputfields() itself.
 */
class EmailServiceConfig extends ModuleConfig
{
	/** Default values of all settings */
	const defaults = [
		'templatePath' => 'site/templates/email/',
		'wrapperFile' => 'wrapper.php',
		'cssFile' => 'wrapper.css',
		'defaultFrom' => '',
		'defaultFromName' => '',
		'retainDays' => 90,
	];

	/**
	 * @return array
	 */
	public function getDefaults(): array
	{
		return self::defaults;
	}

	/**
	 * Build the module configuration form.
	 *
	 * @return InputfieldWrapper
	 * @throws WireException
	 * @throws WirePermissionException
	 */
	public function getInputfields(): InputfieldWrapper
	{
		$modules = $this->wire('modules');
		/** @var EmailService $service */
		$service = $modules->get('EmailService');
		$inputfields = parent::getInputfields();

		foreach ($service->getDependencyWarnings() as $warning) {
			$this->warning($warning);
		}

		$templatePath = $service->getTemplatePath();

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'templatePath';
		$f->label = $this->_('Template path');
		$f->description = $this->_('Directory of the email templates, relative to the ProcessWire root, e.g. site/templates/email/');
		$f->notes = is_dir($templatePath)
			? sprintf($this->_('Resolved: %s'), $templatePath)
			: sprintf($this->_('Directory %s does not exist.'), $templatePath);
		$f->columnWidth = 50;
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'wrapperFile';
		$f->label = $this->_('Wrapper file');
		$f->description = $this->_('File name of the HTML wrapper');
		$f->notes = $this->_('Leave empty to send templates without wrapper.');
		$f->columnWidth = 25;
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'cssFile';
		$f->label = $this->_('CSS file');
		$f->description = $this->_('File name for CSS inlining');
		$f->notes = $this->_('Leave empty to disable CSS inlining.');
		$f->columnWidth = 25;
		$inputfields->add($f);

		/** @var InputfieldEmail $f */
		$f = $modules->get('InputfieldEmail');
		$f->name = 'defaultFrom';
		$f->label = $this->_('Default sender (email)');
		$f->description = $this->_('Falls back to $config->adminEmail if empty');
		$f->columnWidth = 50;
		$inputfields->add($f);

		/** @var InputfieldText $f */
		$f = $modules->get('InputfieldText');
		$f->name = 'defaultFromName';
		$f->label = $this->_('Sender name');
		$f->columnWidth = 50;
		$inputfields->add($f);

		/** @var InputfieldInteger $f */
		$f = $modules->get('InputfieldInteger');
		$f->name = 'retainDays';
		$f->label = $this->_('Log retention (days)');
		$f->description = $this->_('Email log entries older than this number of days are deleted daily. 0 = unlimited.');
		$f->notes = $modules->isInstalled('ProcessCronJobs')
			? $this->_('Runs via ProcessCronJobs.')
			: ($modules->isInstalled('LazyCron')
				? $this->_('Runs via LazyCron.')
				: $this->_('Neither ProcessCronJobs nor LazyCron is installed, old entries are not deleted automatically.'));
		$f->min = 0;
		$f->columnWidth = 50;
		$inputfields->add($f);

		/** @var InputfieldMarkup $f */
		$f = $modules->get('InputfieldMarkup');
		$f->name = 'inlinerStatus';
		$f->label = $this->_('CSS inlining');
		// long paths wrap instead of overflowing the column
		$f->value = '<p style="overflow-wrap:anywhere;">' . $this->wire('sanitizer')->entities($service->getInlinerStatus()) . '</p>';
		$f->columnWidth = 50;
		$inputfields->add($f);

		// the settings are always open, without collapse toggle
		foreach (array_merge(array_keys(self::defaults), ['inlinerStatus']) as $name) {
			$field = $inputfields->getChildByName($name);
			if ($field) $field->collapsed = Inputfield::collapsedNever;
		}

		return $inputfields;
	}
}
