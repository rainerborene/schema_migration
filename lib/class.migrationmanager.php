<?php

	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');

	class MigrationManager implements Singleton {

		protected static $_instance = null;

		public $sectionManager;
		public $fieldManager;

		private function __construct(){
			$this->_engine =& Symphony::Engine();
			$this->sectionManager = new SectionManager($this->_engine);
			$this->fieldManager = new FieldManager($this->_engine);
		}

		public static function instance(){
			if (!(self::$_instance instanceof MigrationManager)){
				self::$_instance = new MigrationManager;
			}

			return self::$_instance;
		}

		private function __getPagesTypes(){
			$result = array();
			$callback = Administration::instance()->getPageCallback();
			
			$fields = $_POST['fields'];
			$current_types = preg_split('/\s*,\s*/', $fields['type'], -1, PREG_SPLIT_NO_EMPTY);
			$current_types = @array_map('trim', $current_types);

			$types = Symphony::Database()->fetch('SELECT * FROM `tbl_pages_types`');
			
			$result[] = array(
				'page_id' => $callback['context'][1],
				'type' => $current_types
			);
			
			if (is_array($types) && !empty($types)){
				foreach($types as $type){
					$result[] = array(
						'page_id' => $type['page_id'],
						'type' => $type['type']
					);
				}
			}
			
			return $result;
		}

		public function migratePages(){
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
					$entry = $xml->createElement('entry');
				
					foreach($page as $column => $value){
						$data = $xml->createElement($column, $value);
						$entry->appendChild($data);
					}

					$entries->appendChild($entry);
				}
			}

			// Page types
			$types = $this->__getPagesTypes();
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
		
		public function migrateSection($section_id){
			$this->sectionManager->flush();
			$section = $this->sectionManager->fetch($section_id);
			
			$meta = $section->get();
			$fields = array();
			$field_objects = $section->fetchFields();

			foreach($field_objects as $f) $fields[] = $f->get();
			
			// Creates DOMDocument object
			$xml = new DOMDocument('1.0', 'UTF-8');
			$xml->preserveWhiteSpace = false;
			$xml->formatOutput = true;
			
			// Section element
			$section = $xml->createElement('section');
			$section->setAttribute('id', $meta['id']);

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

		public function cleanup(){
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
