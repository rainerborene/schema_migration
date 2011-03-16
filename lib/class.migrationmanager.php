<?php

	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');

	class MigrationManager {

		private static $_initialized;

		static $sectionManager;
		static $fieldManager;

		private function __construct(){}

		public static function initialize(){
			if (is_null(self::$_initialized)){
				$engine =& Symphony::Engine();
				self::$sectionManager = new SectionManager($engine);
				self::$fieldManager = new FieldManager($engine);
				self::$_initialized = true;
			}
		}

		public static function getPagesTypes($page_guid){
			$result = array();
			
			$fields = $_POST['fields'];
			$current_types = preg_split('/\s*,\s*/', $fields['type'], -1, PREG_SPLIT_NO_EMPTY);
			$current_types = @array_map('trim', $current_types);

			$types = Symphony::Database()->fetch('SELECT * FROM `tbl_pages_types`');
			
			if (!empty($current_types)){
				$result[] = array(
					'page_guid' => $page_guid,
					'type' => $current_types
				);
			}
			
			if (is_array($types) && !empty($types)){
				foreach($types as $type){
					$guid = Symphony::Database()->fetchVar('guid', 0, "SELECT guid `tbl_pages` WHERE id = {$type['page_id']}");

					$result[] = array(
						'page_guid' => $guid,
						'type' => $type['type']
					);
				}
			}
			
			return $result;
		}

		public static function migratePages(){
			$pages = Symphony::Database()->fetch("SELECT * FROM `tbl_pages`");

			// Creates DOMDocument object
			$xml = new DOMDocument('1.0', 'UTF-8');
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;

			// Root node
			$root = $xml->createElement('pages');

			// Page entries
			$entries = $xml->createElement('entries');
			if (is_array($pages) && !empty($pages)){
				foreach($pages as $page){
					// Ensures that pages has a guid
					if (!$page['guid']){
						$page['guid'] = uniqid();
						Symphony::Database()->update(array('guid' => $page['guid']), 'tbl_pages', "id = {$page['id']}");
					}

					$entry = $xml->createElement('entry');

					foreach($page as $column => $value){
						if ($column == 'id') continue;
						$data = $xml->createElement($column, $value);
						$entry->appendChild($data);
					}

					$entries->appendChild($entry);
				}
			}

			// Page types
			$types = MigrationManager::getPagesTypes($page['guid']);
			$pages_types = $xml->createElement('types');

			if (is_array($types) && !empty($types)){
				foreach($types as $page_type){
					$type = $xml->createElement('type');

					foreach($page_type as $column => $value){
						if ($column == 'type') $value = implode(', ', $value);
						$data = $xml->createElement($column, $value);
						$type->appendChild($data);
					}

					$pages_types->appendChild($type);
				}
			}

			$root->appendChild($entries);
			$root->appendChild($pages_types);
			$xml->appendChild($root);

			$location = WORKSPACE . "/pages/_pages.xml";
			$output = $xml->saveXML();
			
			General::writeFile($location, $output, Symphony::Configuration()->get('write_mode', 'file'));
		}
		
		public static function migrateSection($section_id){
			self::$sectionManager->flush();
			$section = self::$sectionManager->fetch($section_id);
			
			// Ensures that section has a guid value
			if (!$section->get('guid')){
				$section->set('guid', uniqid());
				$section->commit();
			}

			$meta = $section->get();
			$fields = array();
			$field_objects = $section->fetchFields();

			if (is_array($field_objects) && !empty($field_objects)){
				foreach($field_objects as $f){
					// Ensures that fields has guid values
					if (!$f->get('guid')){
						$f->set('guid', uniqid());
						self::$fieldManager->edit($f->get('id'), array('guid' => $f->get('guid')));
					}

					$fields[] = $f->get();
				}
			}
			
			// Creates DOMDocument object
			$xml = new DOMDocument('1.0', 'UTF-8');
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;
			
			// Section element
			$section = $xml->createElement('section');
			$section->setAttribute('guid', $meta['guid']);

			// Section meta data
			$section_meta = $xml->createElement('meta');
			
			foreach($meta as $key => $value){
				if ($key == 'id') continue;
				$element = $xml->createElement($key, $value);
				$section_meta->appendChild($element);
			}
			
			// Section fields
			$section_fields = $xml->createElement('fields');

			foreach($fields as $f){
				$field = $xml->createElement('entry');

				foreach($f as $key => $value){
					if ($key == 'id' || $key == 'field_id') continue;
					$data = $xml->createElement($key, $value);
					$field->appendChild($data);
				}

				$section_fields->appendChild($field);
			}

			$section->appendChild($section_meta);
			$section->appendChild($section_fields);
			$xml->appendChild($section);

			// Saves output in an external file
			$location = WORKSPACE . "/sections/{$meta['handle']}.xml";
			$output = $xml->saveXML();

			General::writeFile($location, $output, Symphony::Configuration()->get('write_mode', 'file'));
		}

		public static function cleanup(){
			$sections = Symphony::Database()->fetchCol('handle', "SELECT * FROM `tbl_sections`");

			$files = General::listStructure(WORKSPACE . '/sections', array(), false);
			$exclude = array();

			if (is_array($files['filelist']) && !empty($files['filelist'])){
				foreach($files['filelist'] as $filename){
					$section = str_replace('.xml', '', $filename);
					if (!in_array($section, $sections)) $exclude[] = $section;
				}
			}

			if (!empty($exclude)){
				foreach($exclude as $filename){
					General::deleteFile(WORKSPACE . "/sections/{$filename}.xml");
				}
			}
		}

	}
