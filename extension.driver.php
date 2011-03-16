<?php

	require_once(EXTENSIONS . '/schema_migration/lib/class.migrationmanager.php');
	require_once(EXTENSIONS . '/schema_migration/lib/class.synchronizer.php');

	class extension_schema_migration extends Extension {

		public function __construct(array $args){
			$this->_Parent =& $args['parent'];
			MigrationManager::initialize();
		}

		public function __call($name, $args){
			$methods = array('migratePages', 'cbSectionPreDelete', 'cbSectionPostCreateEdit', 'cleanupSections');

			if (in_array($name, $methods) && Symphony::Configuration()->get('enabled', 'migrations') !== 'yes'){
				return false;
			}
			
			return call_user_func_array($name, $args);
		}

		public function about(){
			return array(
				'name' => 'Schema Migration',
				'version' => '0.1',
				'release-date' => '2011-01-16',
				'author' => array(
					'name' => 'Rainer Borene',
					'website' => 'http://rainerborene.com',
					'email' => 'rainerborene@gmail.com'),
				'description' => 'Track actions performed on sections and pages in a convenient way.'
			);
		}

		public function getSubscribedDelegates(){
			return array(
				array(
					'page' => '/backend/',
					'delegate' => 'AdminPagePreGenerate',
					'callback' => 'cleanupSections'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'AddCustomPreferenceFieldsets',
					'callback' => 'appendPreferences'
				),
				array(
					'page' => '/system/preferences/',
					'delegate' => 'Save',
					'callback' => 'savePreferences'
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'SectionPostCreate',
					'callback' => 'cbSectionPostCreateEdit'
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'SectionPostEdit',
					'callback' => 'cbSectionPostCreateEdit'
				),
				array(
					'page' => '/blueprints/sections/',
					'delegate' => 'SectionPreDelete',
					'callback' => 'cbSectionPreDelete'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePostCreate',
					'callback' => 'migratePages'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePostEdit',
					'callback' => 'migratePages'
				),
				array(
					'page' => '/blueprints/pages/',
					'delegate' => 'PagePreDelete',
					'callback' => 'migratePages'
				),
			);
		}
		
		public function fetchNavigation(){
			return array(
				array(
					'location' => __('System'),
					'limit' => 'developer',
					'name' => __('Migrations')
				)
			);
		}


		public function appendPreferences($context){
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Schema Migration')));			
			
			$label = Widget::Label();
			$input = Widget::Input('settings[migrations][enabled]', 'yes', 'checkbox');
			
			if (Symphony::Configuration()->get('enabled', 'migrations') == 'yes')
				$input->setAttribute('checked', 'checked');
			
			$label->setValue($input->generate() . ' ' . __('Enable tracking of section and page changes'));
			
			$group->appendChild($label);
			$group->appendChild(new XMLElement('p', __('Migrations are the easiest way to keep content type changes synchronized with the production environment through XML documents.'), array('class' => 'help')));
			
			$context['wrapper']->appendChild($group);
		}
		
		public function savePreferences($context){
			if (!is_array($context['settings'])){
				$context['settings'] = array('migrations' => array('enabled' => 'no'));
			}
			elseif (!isset($context['settings']['migrations'])){
				$context['settings']['migrations'] = array('enabled' => 'no');
			}
		}

		public function cleanupSections($context){
			$callback = Administration::instance()->getPageCallback();

			if ($callback['driver'] === 'blueprintssections'){
				MigrationManager::cleanup();
			}
		}

		public function cbSectionPostCreateEdit($context){
			MigrationManager::migrateSection($context['section_id']);
		}

		public function cbSectionPreDelete($context){
			$section_ids = implode(',', $context['section_ids']);
			$sections = Symphony::Database()->fetchCol('handle', "SELECT * FROM `tbl_sections` WHERE id IN ({$section_ids})");

			foreach($sections as $section){
				General::deleteFile(WORKSPACE . "/sections/{$section}.xml");
			}
		}

		public function migratePages($context){
			MigrationManager::migratePages();
		}

		public function install(){
			@General::realiseDirectory(WORKSPACE . '/sections');
			@General::realiseDirectory(WORKSPACE . '/pages');

			Symphony::Database()->query("ALTER TABLE `tbl_sections` ADD `guid` varchar(13) NOT NULL AFTER `navigation_group`");
			Symphony::Database()->query("ALTER TABLE `tbl_fields` ADD `guid` varchar(13) NOT NULL AFTER `show_column`");
			Symphony::Database()->query("ALTER TABLE `tbl_pages` ADD `guid` varchar(13) NOT NULL AFTER `sortorder`");

			return true;
		}

		public function uninstall(){
			Symphony::Database()->query("ALTER TABLE `tbl_sections` DROP `guid`");
			Symphony::Database()->query("ALTER TABLE `tbl_fields` DROP `guid`");
			Symphony::Database()->query("ALTER TABLE `tbl_pages` DROP `guid`");
			
			return true;
		}

	}

	function debug($var) {
		header('Content-Type:text/plain; charset=utf-8');
		var_dump($var);
		exit;
	}
