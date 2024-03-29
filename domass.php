<?php

/////////////////////////////////////////////////////
// DOMass version 0.01 x 10^-100000 (alpha)        //
// by Eric <ericpp@bigfoot.com>                    //
//                                                 //
// Usage:                                          //
//   php domass.php oldcode.php > newcode.php      //
/////////////////////////////////////////////////////

function get_tokens($php_string, $add_php_tags=false) {
	if($add_php_tags) {
		if(substr($php_string,0,2) != '<?')
			$php_string = '<?php ' . $php_string;
		if(substr($php_string,-2) != '?>')
			$php_string = $php_string . '?>';
	}

	$tokens = token_get_all($php_string);

	if($add_php_tags) {
		array_shift($tokens); array_pop($tokens);
	}

	return $tokens;
}

function token_text($token) {
	return is_array($token) ? $token[1] : $token;
}

function match_token($search, $tokens, $offset=0) {
	// possible match...
	if($search[0][0] == $tokens[$offset][0]) {
		$x = 0;
		$y = $offset;
		$matched_tokens = NULL;
		$variables = array();

		$matched = true;

		while(isset($search[$x]) && isset($tokens[$y]) && $matched) {
			if($tokens[$y][0] == T_WHITESPACE) {
				$matched_tokens[$y] = $tokens[$y];
				$y++;
			}
			else if($search[$x] == $tokens[$y] || ($search[$x][0] == T_VARIABLE && $tokens[$y][0] == T_VARIABLE) || $search[$x][1] == "_SOME_PARAMETER_") {
				if($search[$x][0] == T_VARIABLE && $tokens[$y][0] == T_VARIABLE) {
					$opened = 0;
					$v = count($variables);
					for($yy = $y; $yy < count($tokens); $yy++) {
						if($tokens[$yy] == "[")
							$opened++;
						elseif($tokens[$yy] == "]")
							$opened--;

						if($opened == 0 && $tokens[$y] != $tokens[$yy] && $tokens[$yy][0] != T_WHITESPACE && $tokens[$yy] != "[" && $tokens[$yy] != "]") {
							$y = $yy-1;
							break;
						}

						$matched_tokens[$yy] = $tokens[$yy];
						$variables[$v][$yy] = $tokens[$yy];
					}
				}
				else if($search[$x][1] == "_SOME_PARAMETER_") {
					$opened = 0;
					$v = count($variables);
					for($yy = $y; $yy < count($tokens); $yy++) {
						if(($tokens[$yy] == "," || $tokens[$yy] == ")" || $tokens[$yy] == "]") && $opened == 0) {
							$y = $yy-1;
							break;
						}
						elseif($tokens[$yy] == "(" || $tokens[$yy] == "[") {
							$opened++;
						}
						elseif($tokens[$yy] == ")" || $tokens[$yy] == "]") {
							$opened--;
						}
						
						$matched_tokens[$yy] = $tokens[$yy];
						$variables[$v][$yy] = $tokens[$yy];
					}
				}
				else {
					$matched_tokens[$y] = $tokens[$y];
				}

				$x++; $y++;
			}
			else {
//print "==============================\n";
//print_r($matched_tokens);
//print_r($search[$x]);
//print_r($tokens[$y]);
				$matched = false;
				unset($matched_tokens);
				unset($variables);
			}
		}

		if(!isset($search[$x]) && $matched) {
			return array($matched_tokens, $variables);
		}
	}

	return false;
}

function replace_tokens($search_tokens, $replace_tokens, $tokens) {
	$newtokens = array();

	// search through the tokenized file for the tokens
	for($i = 0; $i < count($tokens); $i++) {
		$match = NULL;
		$matchidx = -1;

		for($j = 0; ($j < count($search_tokens) && !$match); $j++) {
			$match = match_token($search_tokens[$j], $tokens, $i);
			$matchidx = $j;
		}

		if($match) {
			list($matched_tokens, $parms) = $match;
/*
print "=================\n";
print "tokens: "; print_r($matched_tokens);
print "search: "; print_r($search_tokens[$matchidx]);
print "replace: "; print_r($replace_tokens[$matchidx]);
print "parameter: "; print_r($parms);
*/
			for($k = 0; $k < count($replace_tokens[$matchidx]); $k++) {
				if(preg_match("|_VARIABLE_(\d+)_|", token_text($replace_tokens[$matchidx][$k]), $pnum)) {
					$pnum = $pnum[1]-1;
					if(is_array($parms[$pnum])) {
						$variable = array_values($parms[$pnum]);
						if(count($variable) > 1) {
							$variable = replace_tokens($search_tokens, $replace_tokens, $variable);
						}
					}
					$newtokens = array_merge($newtokens, $variable);
				}
				else {
					$newtokens[] = $replace_tokens[$matchidx][$k];
				}
			}
			$i += count($matched_tokens)-1;
		}
		else {
			$newtokens[] = $tokens[$i];
		}
	}

	return $newtokens;	
}

function replace_calls($calls, $file) {
	$tokens = get_tokens($file);

	// get an array of tokens to search for
	for($i = 0; $i < count($calls); $i++) {
		$searches[$i] = get_tokens(str_replace('$$','_SOME_PARAMETER_', $calls[$i][1]), true);
		$replacements[$i] = get_tokens(preg_replace('|\$(\d+)|','_VARIABLE_\1_', $calls[$i][2]), true);
	}

	// search through the tokenized file for the tokens
	$asdf = replace_tokens($searches, $replacements, $tokens);

	$text = "";

	foreach($asdf as $a) {
		$text .= token_text($a);
	}

	return $text;
}

$calls =
	array(
		array('$doc', 'domxml_new_doc($$)', 'new DOMDocument($1)'),
		array('$doc', 'domxml_new_xmldoc($$)', 'new DOMDocument($1)'),
		array('$doc', 'xmldoc($$)', 'DOMDocument::loadXML($1)'),
		array('$doc', 'domxml_open_mem($$)', 'DOMDocument::loadXML($1)'),
		array('$doc', 'xmldocfile($$)', 'DOMDocument::load($1)'),
		array('$doc', 'domxml_open_file($$)', 'DOMDocument::load($1)'),

	// DOMDocument
		array('$node', '$doc->add_root($$)', '$1->appendChild($1->createElement($2))'),
		array('$node', '$doc->append_child($$)', '$1->appendChild($2)'),
		array('$attr', '$doc->create_attribute($$,$$)', '$1->createAttribute($2,$3)'),
		array('$cdata', '$doc->create_cdata_section($$)', '$1->createCDATASection($2)'),
		array('$comment', '$doc->create_comment($$)', '$1->createComment($2)'),
		array('$node', '$doc->create_element_ns($$,$$)', '$1->createElementNS($2,$3)'),
		array('$node', '$doc->create_element_ns($$,$$,$$)', '$1->createElementNS($2,$3,$4)'),
		array('$node', '$doc->create_element($$)', '$1->createElement($2)'),
		array('$entity', '$doc->create_entity_reference($$)', '$1->createEntityReference($2)'),
		array('$pi', '$doc->create_processing_instruction($$)', '$1->createProcessingInstruction($2)'),
		array('$text', '$doc->create_text_node($$)', '$1->createTextNode($2)'),
		array('$doctype', '$doc->doctype()', '$1->doctype'),
		array('$nodelist', '$doc->get_elements_by_tagname($$)', '$1->getElementsByTagName($2)'),
		array('$nodelist', '$doc->get_element_by_id($$)', '$1->getElementById($2)'),

		array(NULL, '$doc->dump_mem()', '$1->saveXML()'),
		array(NULL, '$doc->dump_mem($$)', '$1->saveXML(/* $2 */)'),
		array(NULL, '$obj->dump_mem($$,$$)', '$1->saveXML(/* $2, $3 */)'),
		array(NULL, '$doc->html_dump_mem()', '$1->saveHTML()'),
		array(NULL, '$doc->free()', '$1 = NULL'),

	// DOMElement
		array(NULL, '$node->tagname()', '$1->tagName'),
		array(NULL, '$node->get_attribute($$)', '$1->getAttribute($2)'),
		array(NULL, '$node->set_attribute($$,$$)', '$1->setAttribute($2,$3)'),
		array(NULL, '$node->remove_attribute($$)', '$1->removeAttribute($2)'),
		array('$attr', '$node->get_attribute_node($$)', '$1->getAttributeNode($2)'),
		array('$attr', '$node->set_attribute_node($$)', '$1->setAttributeNode($2)'),
		array(NULL, '$node->has_attribute($$)', '$1->hasAttribute($2)'),

	// DOMNode
		array('$node', '$node->append_child($$)', '$1->appendChild($2)'),
		array('$node', '$node->append_sibling($$)', '$1->parentNode->appendChild($2)'),
		array(NULL, '$node->node_name()', '$1->nodeName'),
		array(NULL, '$node->set_name($$)', '$1->nodeName = $2'),
		array(NULL, '$node->node_value()', '$1->nodeValue'),
		array(NULL, '$node->node_type()', '$1->nodeType'),
		array(NULL, '$node->type', '$1->nodeType'),
		array('$node', '$node->last_child()', '$1->lastChild'),
		array('$node', '$node->first_child()', '$1->firstChild'),
		array('$nodelist', '$node->children()', '$1->childNodes'),
		array('$nodelist', '$node->child_nodes()', '$1->childNodes'),
		array('$node', '$node->previous_sibling()', '$1->previousSibling'),
		array('$node', '$node->next_sibling()', '$1->nextSibling'),
		array('$node', '$node->parent()', '$1->parentNode'),
		array('$node', '$node->parent_node()', '$1->parentNode'),
		array('$doc', '$node->owner_document()', '$1->ownerDocument'),
		array('$node', '$node->insert_before($$,$$)', '$1->insertBefore($2,$3)'),
		array('$node', '$node->remove_child($$)', '$1->removeChild($2)'),
		array(NULL, '$node->has_child_nodes()', '$1->hasChildNodes()'),
		array(NULL, '$node->has_attributes()', '$1->hasAttributes()'),
		array('$nodelist', '$node->attributes()', '$1->attributes'),
		array('$node', '$node->unlink_node()', '$1->parentNode->removeChild($1)'),
		array('$node', '$node->replace_node($$)', '$1->parentNode->replaceChild($2,$1)'),
		array(NULL, '$node->set_content($$)', '$1->textContent = $2'),
		array(NULL, '$node->get_content()', '$1->textContent'),
		array(NULL, '$node->dump_node($$)', '$1->ownerDocument->saveXML($2)'),

	// this might not be correct
		array(NULL, '$node->is_blank_node()', '($obj->nodeType == XML_TEXT_NODE && $obj->textContent == ""), E_USER_ERROR)'),

		array('$node', '$node->new_child($$)', '$1->appendChild($1->ownerDocument->createElement($2))'),
		array('$node', '$node->root()', '$1->documentElement'),
		array('$node', '$node->document_element()', '$1->documentElement'),

	// DOMAttribute
		array(NULL, '$attr->name()', '$1->name'),
		array(NULL, '$attr->value()', '$1->value'),
		array(NULL, '$attr->set_value($$)', '$1->value = $2'),

	// DOMDocumentType
		array('$nodeset', '$doctype->entities()', '$1->entities'),
		array(NULL, '$doctype->internal_subset()', '$1->internalSubset'),
		array(NULL, '$doctype->name()', '$1->name'),
		array('$nodeset', '$doctype->notations()', '$1->notations'),
		array(NULL, '$doctype->public_id()', '$1->publicId'),
		array(NULL, '$doctype->system_id()', '$1->systemId'),

	// DOMProcessingInstruction
		array(NULL, '$pi->data()', '$1->data'),
		array(NULL, '$pi->target()', '$1->target'),

		//array('$node', '$node->appendChild($node->clone_node())', '$1->appendChild($1->ownerDocument->isSameNode($2->ownerDocument)?$2->cloneNode():$1->ownerDocument->importNode($2))'),
		//array('$node', '$node->appendChild($node->clone_node($$))', '$1->appendChild($1->ownerDocument->isSameNode($2->ownerDocument)?$2->cloneNode($3):$1->ownerDocument->importNode($2,$3))'),
	//	array('$obj->$func($obj->clone_node(),$$)', '$1->$2(($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode():$1->ownerDocument->importNode($3)),$4)'),
	//	array('$obj->$func($obj->clone_node($$),$$)', '$1->$2(($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode($4):$1->ownerDocument->importNode($3,$4)),$5)'),
	//	array('$obj->$func($obj->clone_node())', '$1->$2($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode():$1->ownerDocument->importNode($3))'),
	//	array('$obj->$func($obj->clone_node($$))', '$1->$2($1->ownerDocument->isSameNode($3->ownerDocument)?$3->cloneNode($4):$1->ownerDocument->importNode($3,$4))'),
		array('$node', '$obj->clone_node()', '$1->cloneNode()'),
		array('$node', '$obj->clone_node($$)', '$1->cloneNode($2)'),


		array(NULL, 'xpath_init()', '//xpath_init()'),
		array('$xpath', 'xpath_new_context($$)', 'new DOMXPath($1)'),
		array('$xpathresult', 'xpath_eval($$,$$)', '$1->evaluate($2)'),
		array('$xpathresult', 'xpath_eval($$,$$,$$)', '$1->evaluate($2,$3)'),
		array(NULL, 'count($xpathresult->nodeset)', '$1->length'),
		array(NULL, '$xpathresult->nodeset[$$]', '$1->item($2)'),
		array(NULL, 'foreach($xpathresult->nodeset', 'foreach($1'),
		//array('$nodelist', '$xpathresult->nodeset', '$1'),

		//array(NULL, 'count($nodelist)', '$1->length'),
		//array(NULL, '$nodelist[$$]', '$1->item($2)')
	);

$contents = file_get_contents($_SERVER["argv"][1]);
echo replace_calls($calls, $contents);

exit;

?>

