<?php

/////////////////////////////////////////////////////
// DOMass version 0.001 x 10^-100000 (alpha)       //
// by Eric <ericpp@bigfoot.com>                    //
//                                                 //
// Usage:                                          //
//   php domass.php oldcode.php > newcode.php      //
//                                                 //
/////////////////////////////////////////////////////

$variables = array();
$relations = array();

function function_def($def) {
	$def = str_replace('(', '\(', $def);
	$def = str_replace(')', '\)', $def);
	$def = str_replace('[', '\[', $def);
	$def = str_replace(']', '\]', $def);
	//$def = preg_replace('/(\$\w+)/s', '(\\\$\S+)', $def);
	$def = str_replace('$$', '\s*(.+?)\s*', $def);

	if(preg_match_all('/(\$\w+)/s', $def, $vars)) {
		$regexp = array();
		foreach($vars[1] as $var) {
			if(is_array($GLOBALS["variables"][$var])) {
				foreach($GLOBALS["variables"][$var] as $vvv) {
					$regexp[] = str_replace('$', '\$', $vvv);
				}
			}
		}

		if(count($regexp) > 0) {
			$regexp = '(' . join('|', $regexp) . ')';
		}
		else {
			$regexp = '(\\\$\S+)';
		}

		$def = str_replace($var, $regexp, $def);

	}

	return $def;
}

function replace_function($retvar, $functiondef, $resultdef, &$file) {
	$def = '/(\$\S+)(\s*=\s*)' . function_def($functiondef) . '/si';

	if(preg_match_all($def, $file, $matches)) {
		for($i = 0; $i < count($matches[0]); $i++) {
			$myresultdef = $resultdef;

			for($j = 3; $j < count($matches); $j++) {
				$myresultdef = str_replace('$'.($j-2), $matches[$j][$i], $myresultdef);
			}

			$file = str_replace($matches[0][$i], $matches[1][$i] . $matches[2][$i] . $myresultdef, $file);

			$GLOBALS["variables"][$retvar][] = $matches[1][$i];

			if(preg_match($def, $functiondef, $fmatches)) {
				for($j = 3; $j < count($matches); $j++) {
					if($matches[$j][$i]{0} == "$") {
						$GLOBALS["variables"][$fmatches[$j]][] = $matches[$j][$i];
					}
				}
			}
		}
	}

	$def = '/' . function_def($functiondef) . '/si';
	if(preg_match_all($def, $file, $matches)) {
		for($i = 0; $i < count($matches[0]); $i++) {
			$myresultdef = $resultdef;

			for($j = 1; $j < count($matches); $j++) {
				$myresultdef = str_replace('$'.$j, $matches[$j][$i], $myresultdef);
			}

			$file = str_replace($matches[0][$i], $myresultdef, $file);

			if(preg_match($def, $functiondef, $fmatches)) {
				for($j = 1; $j < count($matches); $j++) {
					if($matches[$j][$i]{0} == "$") {
						$GLOBALS["variables"][$fmatches[$j]][] = $matches[$j][$i];
					}
				}
			}
		}
	}
}

$file = file_get_contents($_SERVER["argv"][1]);

replace_function('$doc', 'domxml_new_doc($$)', 'new DOMDocument($1)', $file);
replace_function('$doc', 'domxml_new_xmldoc($$)', 'new DOMDocument($1)', $file);
replace_function('$doc', 'xmldoc($$)', 'DOMDocument::loadXML($1)', $file);
replace_function('$doc', 'domxml_open_mem($$)', 'DOMDocument::loadXML($1)', $file);
replace_function('$doc', 'xmldocfile($$)', 'DOMDocument::load($1)', $file);
replace_function('$doc', 'domxml_open_file($$)', 'DOMDocument::load($1)', $file);

// DOM Document
replace_function('$node', '$doc->add_root($$)', '$1->appendChild($1->createElement($2))', $file);
replace_function('$node', '$doc->append_child($$)', '$1->appendChild($2)', $file);
replace_function('$attr', '$doc->create_attribute($$,$$)', '$1->createAttribute($2,$3)', $file);
replace_function('$cdata', '$doc->create_cdata_section($$)', '$1->createCDATASection($2)', $file);
replace_function('$comment', '$doc->create_comment($$)', '$1->createComment($2)', $file);
replace_function('$node', '$doc->create_element_ns($$,$$)', '$1->createElementNS($2,$3)', $file);
replace_function('$node', '$doc->create_element_ns($$,$$,$$)', '$1->createElementNS($2,$3,$4)', $file);
replace_function('$node', '$doc->create_element($$)', '$1->createElement($2)', $file);
replace_function('$entity', '$doc->create_entity_reference($$)', '$1->createEntityReference($2)', $file);
replace_function('$pi', '$doc->create_processing_instruction($$)', '$1->createProcessingInstruction($2)', $file);
replace_function('$text', '$doc->create_text_node($$)', '$1->createTextNode($2)', $file);
replace_function('$doctype', '$doc->doctype()', '$1->doctype', $file);
replace_function('$nodelist', '$doc->get_elements_by_tagname($$)', '$1->getElementsByTagName($2)', $file);
replace_function('$nodelist', '$doc->get_element_by_id($$)', '$1->getElementById($2)', $file);

replace_function(NULL, '$doc->dump_mem()', '$1->saveXML()', $file);
replace_function(NULL, '$doc->dump_mem($$)', '$1->saveXML(/* format */)', $file);
//replace_function('$obj->dump_mem($$,$$)', '$1->saveXML(/* $2, $3 */)', $file);
replace_function(NULL, '$doc->html_dump_mem()', '$1->saveHTML()', $file);
replace_function(NULL, '$doc->free()', '$1 = NULL', $file);

// DomElement
replace_function(NULL, '$node->tagname()', '$1->tagName', $file);
replace_function(NULL, '$node->get_attribute($$)', '$1->getAttribute($2)', $file);
replace_function(NULL, '$node->set_attribute($$,$$)', '$1->setAttribute($2,$3)', $file);
replace_function(NULL, '$node->remove_attribute($$)', '$1->removeAttribute($2)', $file);
replace_function('$attr', '$node->get_attribute_node($$)', '$1->getAttributeNode($2)', $file);
replace_function('$attr', '$node->set_attribute_node($$)', '$1->setAttributeNode($2)', $file);
replace_function(NULL, '$node->has_attribute($$)', '$1->hasAttribute($2)', $file);

// DomNode
replace_function('$node', '$node->append_child($$)', '$1->appendChild($2)', $file);
replace_function('$node', '$node->append_sibling($$)', '$1->parentNode->appendChild($2)', $file);
replace_function(NULL, '$node->node_name()', '$1->nodeName', $file);
replace_function(NULL, '$node->set_name($$)', '$1->nodeName = $2', $file);
replace_function(NULL, '$node->node_value()', '$1->nodeValue', $file);
replace_function(NULL, '$node->node_type()', '$1->nodeType', $file);
replace_function('$node', '$node->last_child()', '$1->lastChild', $file);
replace_function('$node', '$node->first_child()', '$1->firstChild', $file);
replace_function('$nodelist', '$node->children()', '$1->childNodes', $file);
replace_function('$nodelist', '$node->child_nodes()', '$1->childNodes', $file);
replace_function('$node', '$node->previous_sibling()', '$1->previousSibling', $file);
replace_function('$node', '$node->next_sibling()', '$1->nextSibling', $file);
replace_function('$node', '$node->parent()', '$1->parentNode', $file);
replace_function('$node', '$node->parent_node()', '$1->parentNode', $file);
replace_function('$doc', '$node->owner_document()', '$1->ownerDocument', $file);
replace_function('$node', '$node->insert_before($$,$$)', '$1->insertBefore($2,$3)', $file);
replace_function('$node', '$node->remove_child($$)', '$1->removeChild($2)', $file);
replace_function(NULL, '$node->has_child_nodes()', '$1->hasChildNodes()', $file);
replace_function(NULL, '$node->has_attributes()', '$1->hasAttributes()', $file);
replace_function('$nodelist', '$node->attributes()', '$1->attributes', $file);
replace_function('$node', '$node->unlink_node()', '$1->parentNode->removeChild($1)', $file);
replace_function('$node', '$node->replace_node($$)', '$1->parentNode->replaceChild($2,$1)', $file);
replace_function(NULL, '$node->set_content($$)', '$1->textContent = $2', $file);
replace_function(NULL, '$node->get_content()', '$1->textContent', $file);
replace_function(NULL, '$node->dump_node($$)', '$1->ownerDocument->saveXML($2)', $file);

// this might not be correct
replace_function(NULL, '$node->is_blank_node()', '($obj->nodeType == XML_TEXT_NODE && $obj->textContent == ""), E_USER_ERROR)', $file);

replace_function('$node', '$node->new_child($$)', '$1->appendChild($1->ownerDocument->createElement($2))', $file);
replace_function('$node', '$node->root()', '$1->documentElement', $file);
replace_function('$node', '$node->document_element()', '$1->documentElement', $file);

// DOMAttribute
replace_function(NULL, '$attr->name()', '$1->name', $file);
replace_function(NULL, '$attr->value()', '$1->value', $file);
replace_function(NULL, '$attr->set_value($$)', '$1->value = $2', $file);

// DOMDocumentType
replace_function('$nodeset', '$doctype->entities()', '$1->entities', $file);
replace_function(NULL, '$doctype->internal_subset()', '$1->internalSubset', $file);
replace_function(NULL, '$doctype->name()', '$1->name', $file);
replace_function('$nodeset', '$doctype->notations()', '$1->notations', $file);
replace_function(NULL, '$doctype->public_id()', '$1->publicId', $file);
replace_function(NULL, '$doctype->system_id()', '$1->systemId', $file);

// DOMProcessingInstruction
replace_function(NULL, '$pi->data()', '$1->data', $file);
replace_function(NULL, '$pi->target()', '$1->target', $file);

replace_function('$node', '$node->appendChild($node->clone_node())', '$1->appendChild($1->ownerDocument->isSameNode($2->ownerDocument)?$2->cloneNode():$1->ownerDocument->importNode($2))', $file);
replace_function('$node', '$node->appendChild($node->clone_node($$))', '$1->appendChild($1->ownerDocument->isSameNode($2->ownerDocument)?$2->cloneNode($3):$1->ownerDocument->importNode($2,$3))', $file);
//replace_function('$obj->$func($obj->clone_node(),$$)', '$1->$2(($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode():$1->ownerDocument->importNode($3)),$4)', $file);
//replace_function('$obj->$func($obj->clone_node($$),$$)', '$1->$2(($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode($4):$1->ownerDocument->importNode($3,$4)),$5)', $file);
//replace_function('$obj->$func($obj->clone_node())', '$1->$2($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode():$1->ownerDocument->importNode($3))', $file);
//replace_function('$obj->$func($obj->clone_node($$))', '$1->$2($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode($4):$1->ownerDocument->importNode($3,$4))', $file);
//replace_function('$obj->clone_node()', '$1->cloneNode()', $file);
//replace_function('$obj->clone_node($$)', '$1->cloneNode($2)', $file);


replace_function(NULL, 'xpath_init()', '//xpath_init()', $file);
replace_function('$xpath', 'xpath_new_context($$)', 'new DOMXPath($1)', $file);
replace_function('$xpathresult', 'xpath_eval($$,$$)', '$1->evaluate($2)', $file);
replace_function('$xpathresult', 'xpath_eval($$,$$,$$)', '$1->evaluate($2,$3)', $file);
replace_function(NULL, 'count($xpathresult->nodeset)', '$1->length', $file);
replace_function(NULL, '$xpathresult->nodeset[$$]', '$1->item($2)', $file);
replace_function(NULL, 'foreach($xpathresult->nodeset', 'foreach($1', $file);
replace_function('$nodelist', '$xpathresult->nodeset', '$1', $file);

replace_function(NULL, 'count($nodelist)', '$1->length', $file);
replace_function(NULL, '$nodelist[$$]', '$1->item($2)', $file);


//print_r($variables);
echo $file;

exit;

?>

