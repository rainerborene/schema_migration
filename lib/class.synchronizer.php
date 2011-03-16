<?php

	require_once(TOOLKIT . '/class.sectionmanager.php');

	class Synchronizer {

		public static function removeMissingFields($fields, &$fieldManager){
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
					$section_guid = $xpath->query('/section/@guid')->item(0)->value;
					$sections = Symphony::Database()->fetchCol('guid', "SELECT * FROM `tbl_sections`");
					
					if (in_array($section_guid, $sections)) $editing = true;
					
					// Meta data
					$meta = array();
					$meta_nodes = $xpath->query('/section/meta/*');
					foreach($meta_nodes as $node) $meta[$node->tagName] = $node->textContent;
					
					if ($editing){
						Symphony::Database()->update($meta, 'tbl_sections', "guid = {$section_guid}");
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
					
					self::removeMissingFields($fields, $fieldManager);
					
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
				// Load XML
				$data = @file_get_contents($path);
				
				// Create DOMDocument instance
				$xml = new DOMDocument();
				$xml->preserveWhiteSpace = false;
				$xml->formatOutput = true;
				$xml->loadXML($data);
				
				// XPath for querying nodes
				$xpath = new DOMXPath($xml);
				
				// Update page entries
				$db_pages = Symphony::Database()->fetchCol('guid', "SELECT guid FROM `tbl_pages`");
				$pages = $xpath->query('/pages/entries/entry');

				if ($pages->length){
					foreach($pages as $page){
						$guid = $page->lastChild->textContent;
					
						$fields = array();
					
						foreach($page->childNodes as $node){
							if ($node->tagName == 'guid') continue;
							$fields[$node->tagName] = $node->textContent;
						}

						if (in_array($guid, $db_pages)){
							Symphony::Database()->update($fields, 'tbl_pages', "guid = {$guid}");
						} else {
							Symphony::Database()->insert($fields, 'tbl_pages');
						}
					}
				}

				// Update page type entries
				$db_types = Symphony::Database()->fetch("SELECT * FROM `tbl_pages_types`");
				$types = $xpath->query('/pages/types/type');

				$page_guids = array();
				$entries = array();

				if($types->length){
					foreach($types as $type){
						$page_guids[] = $type->firstChild->textContent;
					}
				}
				
				$imploded_guids = implode(',', $page_guids);
				$page_ids = Symphony::Database()->fetch("SELECT id, guid FROM `tbl_pages` WHERE guid IN ({$imploded_guids})", "guid");

				if(is_array($page_ids) && !empty($page_ids)){
					foreach($page_ids as $guid => $id){
						if (in_array($guid, $page_guids)){
							$entries[] = array(
								'page_id' => $id,
								'type' => $xpath->query("/pages/types/type[page_guid/text() = '{$guid}']/type")->item(0)->textContent
							);
						}
					}
				}
				
				$ids = implode(',', array_values($page_ids));
				Symphony::Database()->delete('tbl_pages_types', "`page_id` IN ({$ids})");

				if(is_array($entries) && !empty($entries)){
					foreach ($entries as $entry){
						Symphony::Database()->insert($entry, 'tbl_pages_types');
					}
				}
			}
		}

	}
