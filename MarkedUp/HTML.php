<?php

namespace MarkedUp;

class HTML
{
	public $tags = array(
		'a', 'b', 'i', 'em', 'u', 'strike', 'strong', 'code',
		'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
		'table', 'thead', 'tbody', 'tr', 'td', 'th',
		'blockquote', 'pre', 'p',
		'ul', 'ol', 'li'
	);

	public $remove = array(
		'script', 'title', 'style', 'meta'
	);
	
	public function innerXML($node)
	{
		$doc = $node->ownerDocument;
		$frag = $doc->createDocumentFragment();
		foreach ($node->childNodes as $child)
		{
			$frag->appendChild($child->cloneNode(TRUE));
		}
		return $doc->saveXML($frag);
	}

	/**
	 * Validate the DOM structure to make sure we aren't producing invalid markup
	 * 
	 * @param string $html
	 * @return string
	 */
	public function validate($html)
	{
		libxml_use_internal_errors(true) AND libxml_clear_errors();

		$dom = new \DomDocument();
		//$dom->preserveWhiteSpace = false;
		$dom->registerNodeClass('DOMElement', '\MarkedUp\JSLikeHTMLElement');

		if($dom->loadHTML($html))
		{
			// Script tags are a problem for our parser, so we will just remove them
			$xpath = new \DOMXPath($dom);
			$list = $xpath->query("//script");

			for($j=0; $j<$list->length; ++$j)
			{
				$node = $list->item($j);
				if ($node == null) continue;
				$node->parentNode->removeChild($node);
			}

			// Pre blocks often use \n instead of <br> which is a problem
			foreach($dom->getElementsByTagName("pre") as $node)
			{
				/*
				if(strpos($node->nodeValue, '<br') === FALSE)
				{
					$children = $node->childNodes;
					$node->nodeValue = "";
					foreach($node->childNodes as $child)
					{
						$node->appendChild($doc->importNode($child, true));
					}
				}
				*/

				if(strpos($node->nodeValue, '<br') === FALSE)
				{

					//die($node->nodeValue . "\n-\n" . $node->textContent);
					//$node->nodeValue = nl2br($node->nodeValue);
				}
				

				if(strpos($node->textContent, '<br') === FALSE)
				{
					//die($node->nodeValue . "\n-\n" . $node->textContent);
					//$node->textContent = nl2br($node->textContent);
					//$node->nodeValue = nl2br($node->textContent);
					$node->innerHTML = nl2br($node->innerHTML);
				}
			}

			return preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $dom->saveHTML());
		}
	}

	public function parse($html)
	{
		$html = $this->validate($html);

		//$html = preg_replace("~\n\n+~", "\n\n", $html);
		//$html = preg_replace("~\n\n+([^ \t])~", "\n$1", $html);
		//$html = str_replace("\n\n", '', $html);

		// Remove all remaining unneeded whitespace
		//$html = preg_replace("~^\s*~m", '', $html);
		$html = str_replace("\n", '', $html);

		$tags = array_flip($this->tags);
		$remove = array_flip($this->remove);

		// First remove single element tags
		$single = array(
			'<hr>' => "\n\n---",
			'<br>' => "\n",
			'<br />' => "\n"
		);

		$html = str_replace(array_keys($single), array_values($single), $html);

		// Fix images
		$self = $this;
		$html = preg_replace_callback('~<img ([^>]*)>~i', function($m) use(&$self)
		{
			$attributes = $self->break_attr($m[1]);

			if(isset($attributes['src']))
			{
				$alt = isset($attributes['alt']) ? $attributes['alt'] : 'image';
				$title = isset($attributes['title']) ? $attributes['title'] : '';

				return '![' . $alt . ']('. $attributes['src'] . ($title ? " \"$title\"" : ''). ')';
			}
		}, $html);


		$output = '';
		while(preg_match_all('~<(\w+)([^>]*)>((?:(?!<).)*)</\1[^<>]*>~s', $html, $matches, PREG_SET_ORDER))
		{
			foreach($matches as $match)
			{
				$output = '';

				list($match, $tag, $attributes, $text) = $match;

				// We don't support this tag, just remove it
				if( ! isset($tags[$tag]))
				{
					//print "Missing: $tag-\n\n";
					if( ! isset($remove[$tag]))
					{
						$output .= $text;
					}

					$output = htmlspecialchars($output);

					$html = str_replace($match, $output, $html);
					continue;
				}

				$attributes = $this->break_attr($attributes);

				if($tag == 'a')
				{
					if(isset($attributes['href']))
					{
						$output .= '[' . $text . ']('. $attributes['href'] . ')';
					}
					else
					{
						$output .= '[' . $text . '](#)';
					}
				}
				elseif($tag == 'img')
				{
					//die($match);
					if(isset($attributes['href']))
					{
						$output .= '[' . $text . ']('. $attributes['href'] . ')';
					}
					else
					{
						$output .= '[' . $text . '](#)';
					}
				}
				elseif($tag === 'b')
				{
					$output .= '**' . $text . '**';
				}
				elseif($tag === 'i')
				{
					$output .= '*' . $text . '*';
				}
				elseif($tag === 'u')
				{
					$output .= '_' . $text . '_';
				}
				elseif($tag === 'strike')
				{
					$output .= '-' . $text . '-';
				}
				elseif($tag === 'pre')
				{
					$output .= "\n\n\t" . str_replace("\n", "\n\t", trim($text, "\n"));
				}
				elseif($tag === 'blockquote')
				{
					$output .= "\n\n>" . str_replace("\n", "\n>", trim($text, "\n"));
				}
				elseif($tag === 'code')
				{
					$output .= '`' . $text . '`';
				}
				elseif($tag === 'p')
				{
					$output .= "\n\n". $text;
				}
				elseif($tag === 'tbody' OR $tag === 'tr')
				{
					$output .= "\n" . $text;
				}
				elseif($tag === 'thead')
				{
					$output .= "\n" . $text . "\n|" . str_repeat('-', strlen($text));
				}
				elseif($tag === 'table')
				{
					//$output .= "\n". str_replace("\n", "\n|", $text);
					$output .= "\n\n" . str_replace("\n\n", "\n", $text);
				}
				elseif($tag === 'td')
				{
					$output .= '| ' . trim($text) . ' ';
				}
				elseif($tag === 'th')
				{
					$output .= '| ' . trim($text) . ' ';
				}
				elseif($tag === 'li')
				{
					// We remove any nested lists leading linebreaks, then we indent them
					$output .= "\n- ". str_replace(array("\n\n", "\n"), array("", "\n\t"), trim($text));
				}
				elseif($tag === 'ul')
				{
					$output .= "\n\n" . $text;
				}
				elseif($tag === 'ol')
				{
					// If this is an ordered list we need to use numbers instead
					$counter = 1;
					$text = preg_replace_callback("~^\-~m", function($m) use(&$counter)
					{
						return $counter++ . '.';
					}, $text);

					$output .= "\n\n" . $text;
				}
				elseif($tag === 'h1')
				{
					$output .= "\n\n# " . $text;
				}
				elseif($tag === 'h2')
				{
					$output .= "\n\n## " . $text;
				}
				elseif($tag === 'h3')
				{
					$output .= "\n\n### " . $text;
				}
				elseif($tag === 'h4')
				{
					$output .= "\n\n#### " . $text;
				}
				elseif($tag === 'h5')
				{
					$output .= "\n\n###### " . $text;
				}
				elseif($tag === 'h6')
				{
					$output .= "\n\n####### " . $text;
				}
				else
				{
					$output .= '[' . $tag . ']' . $text . '[/' . $tag . ']';
				}


				//print $match . "\n-\n" . $output . "\n---\n";
				$html = str_replace($match, $output, $html);
			}
		}

		$html = htmlspecialchars_decode($html);
		$html = preg_replace("~\n\n\n+~", "\n\n", $html);
		$html = trim($html);

		//return $output;
		return $html;
	}


	public function break_attr($string)
	{
		$attributes = array();
		if(preg_match_all('~(\w+)=([\'"])((?:(?!\2).)*)\2~', $string, $matches, PREG_SET_ORDER))
		{
			//die(var_dump($matches));
			foreach($matches as $m)
			{
				$attributes[$m[1]] = $m[3];
			}
		}

		return $attributes;
	}
}


/**
* JavaScript-like HTML DOM Element
*
* This class extends PHP's DOMElement to allow
* users to get and set the innerHTML property of
* HTML elements in the same way it's done in 
* JavaScript.
*
* @author Keyvan Minoukadeh - http://www.keyvan.net - keyvan@keyvan.net
* @see http://fivefilters.org (the project this was written for)
*/
class JSLikeHTMLElement extends \DOMElement
{
	/**
	* Used for setting innerHTML like it's done in JavaScript:
	* @code
	* $div->innerHTML = '<h2>Chapter 2</h2><p>The story begins...</p>';
	* @endcode
	*/
	public function __set($name, $value) {
		if ($name == 'innerHTML') {
			// first, empty the element
			for ($x=$this->childNodes->length-1; $x>=0; $x--) {
				$this->removeChild($this->childNodes->item($x));
			}
			// $value holds our new inner HTML
			if ($value != '') {
				$f = $this->ownerDocument->createDocumentFragment();
				// appendXML() expects well-formed markup (XHTML)
				$result = @$f->appendXML($value); // @ to suppress PHP warnings
				if ($result) {
					if ($f->hasChildNodes()) $this->appendChild($f);
				} else {
					// $value is probably ill-formed
					$f = new \DOMDocument();
					$value = mb_convert_encoding($value, 'HTML-ENTITIES', 'UTF-8');
					// Using <htmlfragment> will generate a warning, but so will bad HTML
					// (and by this point, bad HTML is what we've got).
					// We use it (and suppress the warning) because an HTML fragment will 
					// be wrapped around <html><body> tags which we don't really want to keep.
					// Note: despite the warning, if loadHTML succeeds it will return true.
					$result = @$f->loadHTML('<htmlfragment>'.$value.'</htmlfragment>');
					if ($result) {
						$import = $f->getElementsByTagName('htmlfragment')->item(0);
						foreach ($import->childNodes as $child) {
							$importedNode = $this->ownerDocument->importNode($child, true);
							$this->appendChild($importedNode);
						}
					} else {
						// oh well, we tried, we really did. :(
						// this element is now empty
					}
				}
			}
		} else {
			$trace = debug_backtrace();
			trigger_error('Undefined property via __set(): '.$name.' in '.$trace[0]['file'].' on line '.$trace[0]['line'], E_USER_NOTICE);
		}
	}

	/**
	* Used for getting innerHTML like it's done in JavaScript:
	* @code
	* $string = $div->innerHTML;
	* @endcode
	*/	
	public function __get($name)
	{
		if ($name == 'innerHTML') {
			$inner = '';
			foreach ($this->childNodes as $child) {
				$inner .= $this->ownerDocument->saveXML($child);
			}
			return $inner;
		}

		$trace = debug_backtrace();
		trigger_error('Undefined property via __get(): '.$name.' in '.$trace[0]['file'].' on line '.$trace[0]['line'], E_USER_NOTICE);
		return null;
	}

	public function __toString()
	{
		return '['.$this->tagName.']';
	}
}
