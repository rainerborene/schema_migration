<?php

	require_once(TOOLKIT . '/class.sectionmanager.php');

	class Synchronizer {

		public static function __removeMissingFields($fields, &$fieldManager){
			$id_list = array();
			
			if(is_array($fields) && !empty($fields)){
				foreach($fields as $position => $data){
					if(isset($data['id'])) $id_list[] = $data['id'];
				}
			}

			$missing_cfs = Symphony::Database()->fetchCol('id', "SELECT `id` FROM `tbl_fields` WHERE `parent_section` = '$section_id' AND `id` NOT IN ('" . @implode("', '", $id_list) . "')");

			if(is_array($missing_cfs) && !empty($missing_cfs)){
				foreach($missing_cfs as $id){
					$fieldManager->delete($id);
				}
			}
		}

		public static function updateSections(){
			$files = General::listStructure(WORKSPACE . '/sections', array(), false);
			
			$engine =& Symphony::Engine();
			$sectionManager = new SectionManager($engine);
			$fieldManager = new FieldManager($engine);
			
			if (is_array($files['filelist']) && !empty($files['filelist'])){
				foreach($files['filelist'] as $filename){
					$data = @file_get_contents(WORKSPACE . "/sections/{$filename}");
					
					// Create DOMDocument instance
					$xml = new DOMDocument();
					$xml->preserveWhiteSpace = false;
					$xml->formatOutput = true;
					$xml->loadXML($data);
					
					// XPath for querying nodes
					$xpath = new DOMXPath($xml);
					
					$editing = false;
					$section_id = $xpath->query('/section/@id')->item(0)->value;
					$sections = Symphony::Database()->fetchCol('id', "SELECT * FROM `tbl_sections`");
					
					if (in_array($section_id, $sections)) $editing = true;
					
					// Meta data
					$meta = array();
					$meta_nodes = $xpath->query('/section/meta/*');
					foreach($meta_nodes as $node) $meta[$node->tagName] = $node->textContent;
					
					if ($editing){
						$sectionManager->edit($section_id, $meta);
					} else {
						$section_id = $sectionManager->add($meta);
					}
					
					// Fields
					$fields = array();
					$field_nodes = $xpath->query('/section/fields/entry');
					
					foreach($field_nodes as $node){
						$field = array();
						
						foreach($node->childNodes as $childNode){
							$field[$childNode->tagName] = $childNode->textContent;
						}
						
						$fields[] = $field;
					}
					
					self::__removeMissingFields($fields, $fieldManager);
					
					if (is_array($fields) && !empty($fields)){
						foreach($fields as $data){
							$field = $fieldManager->create($data['type']);
							$field->setFromPOST($data);
							$field->commit();
						}
					}
				}
			}
		
		}

		public static function updatePages(){
			$path = WORKSPACE . '/pages/_pages.xml';
			
			if (file_exists($path)){
				$data = @file_get_contents($path);
				
				// Create DOMDocument instance
				$xml = new DOMDocument();
				$xml->preserveWhiteSpace = false;
				$xml->formatOutput = true;
				$xml->loadXML($data);
				
				// XPath for querying nodes
				$xpath = new DOMXPath($xml);
				
				// Update page entries
				$db_pages = Symphony::Database()->fetchCol('id', "SELECT id FROM `tbl_pages`");
				$pages = $xpath->query('/pages/entries/entry');

				if ($pages->length > 0){
					foreach($pages as $page){
						$id = $page->firstChild->textContent;
					
						$fields = array();
					
						foreach($page->childNodes as $node){
							if ($node->tagName == 'id') continue;
							$fields[$node->tagName] = $node->textContent;
						}

						if (in_array($id, $db_pages)){
							Symphony::Database()->update($fields, 'tbl_pages', " id = {$id}");
						} else {
							Symphony::Database()->insert($fields, 'tbl_pages');
						}
					}
				}

				// Update page type entries
				$db_types = Symphony::Database()->fetch("SELECT * FROM `tbl_pages_types`");
				$types = $xpath->query('/pages/types/type');

				$page_ids = array();
				$entries = array();

				if ($types->length > 0){
					foreach($types as $type){
						$page_ids[] = $type->firstChild->textContent;

						$entries[] = array(
							'page_id' => $type->firstChild->textContent,
							'type' => $type->lastChild->textContent
						);
					}
				}
				
				$ids = implode(',', $page_ids);
				Symphony::Database()->delete('tbl_pages_types', "`page_id` IN ({$ids})");

				if(is_array($entries) && !empty($entries)){
					foreach ($entries as $entry){
						Symphony::Database()->insert($entry, 'tbl_pages_types');
					}
				}
			}
		}

	}

	function debug($var) {
		header('Content-Type:text/plain; charset=utf-8');
		var_dump($var);
		exit;
	}

