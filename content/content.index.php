<?php

	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/schema_migration/lib/class.migrationmanager.php');
	require_once(EXTENSIONS . '/schema_migration/lib/class.synchronizer.php');

	class contentExtensionSchema_migrationIndex extends AdministrationPage {

		public function __construct(&$parent){
			parent::__construct($parent);
			$this->setPageType('form');
			$this->setTitle(__('%1$s &ndash; %2$s', array(__('Symphony'), __('Migrations'))));
		}

		public function canAccessPage(){
			return Administration::instance()->Author->isDeveloper();
		}

		public function view(){
			// Heading
			$this->appendSubheading(__('Migrations'));

			// Report fieldset
			$group = new XMLElement('fieldset');
			$group->setAttribute('class', 'settings');
			$group->appendChild(new XMLElement('legend', __('Report')));

			$label = Widget::Label();
			$label->appendChild(new XMLElement('p', __('Hit the migrate button to update sections and pages.')));

			$group->appendChild($label);

			$this->Form->appendChild($group);

			// Action button
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(Widget::Input('action[sync]', __('Migrate'), 'submit', $attr));
			$this->Form->appendChild($div);
		}
	
		public function action(){
			if (@array_key_exists('sync', $_POST['action'])){
				Synchronizer::updatePages();
				Synchronizer::updateSections();
			}
		}
		
	}
