<?php
namespace MarkedUp;

class Text
{

	public static $chars = array('>', '|', "\t", '*', '-');

	public function normalize($text)
	{
		$text = str_replace(array("\r\n", "\r"), "\n", $text);
		$text = str_replace('    ', "\t", $text);

		// We could do this, but some ASCII art or code blocks might need multiple whitespace lines ;)
		//$text = preg_replace("~\n\n\n+~", "\n\n", $text);

		$text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

		// We need to allow blockquote characters at the start of the line
		$text = preg_replace_callback('~^(&gt;)+~m', function($m)
		{
			return str_replace('&gt;', '>', $m[0]);
		}, $text);

		return $text;
	}

	public function parse($text, $validate = FALSE)
	{
		// Handle HR lines first since they look like lists and other stuff
		$text = preg_replace('~\n([\-*] *){3,}\n~', "\n<hr>\n", $text);

		// Lets get this party stated
		$groups = explode("\n\n", trim($text, "\n"));

		// Remove empty items
		$groups = array_filter($groups);

		// Only parse if more than one item :)
		if(isset($groups[1]))
		{
			// We need to rejoin same-content items that are separated by \n\n
			foreach($groups as $i => $group)
			{

				$groups[$i] = $group = trim($group, "\n");

				if($i === 0) continue;

				$x = 1;
				while(empty($groups[$i - $x]))
				{
					$x++;
				}

				// Look for special characters (or a sequince of 2 digits which should be a list)
				if(
					($group{0} === $groups[$i - $x]{0} AND in_array($group{0}, self::$chars))
					OR
					(ctype_digit($group{0}) AND ctype_digit($groups[$i - $x]{0}))
				)
				{
					$groups[$i - $x] .= "\n\n" . $group;
					unset($groups[$i]);
				}
			}

			$groups = array_values($groups);
		}

		// Remove starting char
		foreach($groups as $i => $group)
		{
			$char = $group{0};

			// Lists are tricky because of digits
			if($char === '*' OR $char === '-' OR (ctype_digit($char) AND strpos($group, "\n") !== FALSE))
			{
				$group = $this->listing($group);
			}
			else
			{
				if(in_array($char, self::$chars))
				{
					if($char !== '#')
					{
						$group = ltrim(str_replace("\n". $char, "\n", $group), $char);
					}
				}

				// Do not process code/pre blocks!
				if($char === "\t")
				{
					$groups[$i] = $this->pre($group);
					continue;
				}

				if($char === '>')
				{
					$group = $this->quote($group);
				}
				else if($char === '|')
				{
					$group = $this->table($group);
				}
				else if($char === '#')
				{
					$group = $this->heading($group);
				}
				else
				{
					$group = $this->paragraph($group);
				}
			}

			$group = $this->inline($group);

			$groups[$i] = $group;
		}

		$groups = join("\n\n", $groups);

		return $validate ? $this->validate($groups) : $groups;
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

		$dom = new DomDocument();

		if($dom->loadHTML($html))
		{
			return preg_replace('~<(?:!DOCTYPE|/?(?:html|body))[^>]*>\s*~i', '', $dom->saveHTML());
		}
	}

	/**
	 * Convert HTML headings
	 *
	 * @param string $group
	 * @return string
	 */
	public function heading($group)
	{
		$header = ltrim($group, "#");
		$h = min(6, strlen($group) - strlen($header));
		return "<h$h>" . rtrim($header, '# ') . "</h$h>";
	}

	/**
	 * Highlight code in preformated blocks
	 *
	 * @param string $group
	 * @return string
	 */
	public function pre($group)
	{
		// Mark strings
		$regex = '((&#039;)|(&quot;))(?:[^\\\\1]|\\\.)*?\1';
		$group = preg_replace("~$regex~", '<span class="string">$0</span>', $group);

		// Mark comments
		$regex = '(/\*.*?\*/)|((#(?!\w+;)|(-- )|(//))[^\n]+)';
		$group = preg_replace("~$regex~s", '<span class="comment">$0</span>', $group);

		// Party on
		return '<pre>' . $group . '</pre>';
	}

	/**
	 * This method is called recursively to deal with each list item and it's children ($m[3])
	 *
	 * @param string $group
	 * @return string
	 */
	public function listing($group)
	{
		// Find this item (and it's children)
		preg_match_all('~ *((?:\d+\.)|[\*\-]) *([^\n]+)((?:\n\t[^\n]+)*)~', $group, $matches, PREG_SET_ORDER);

		if( ! $matches)
		{
			print "What is missing?\n";
			die($group);
		}

		$output = '';
		$tag = '';
		foreach ($matches as $m)
		{
			if( ! $tag)
			{
				$tag = ($m[1] === '*' OR $m[1] === '-') ? 'ul' : 'ol';
			}

			$item = $m[2];

			if($m[3])
			{
				$item .= $this->listing(str_replace("\n\t", "\n", $m[3]));
			}

			$output .= "<li>". $item. "</li>";
		}

		return "<$tag>$output</$tag>";
	}

	/**
	 * Quotes are blocklevel elements and can contain many nested children
	 *
	 * @param string $group
	 * @return string
	 */
	public function quote($group)
	{
		$group = $this->parse($group);

		return '<blockquote>' . $group . '</blockquote>';
	}

	/**
	 * Markup table elements
	 *
	 * @param string $text
	 * @return string
	 */
	public function table($text)
	{
		list($header, $line, $table) = explode("\n", $text, 3) + array('', '', '');

		$header = preg_replace('~\s*\|\s*~', '</th><th>', trim(rtrim($header, ' |')));

		$block = "\n\n<table><thead><tr><th>". $header . '</th></tr></thead><tbody>';

		$lines = explode("\n", $table);
		$lines = array_filter($lines);

		foreach($lines as $line)
		{
			$line = preg_replace('~\s*\|\s*~', '</td><td>', trim(rtrim($line, ' |')));
			
			if($line == '</td><td>') continue; // blank line?

			$block .= '<tr><td>' . $line . "</td></tr>\n";
		}

		$block .= "</tbody></table>\n\n";

		return $block;
	}

	/**
	 * All other text is considered a paragraph
	 *
	 * @param string $text
	 * @return string
	 */
	public function paragraph($text)
	{
		if($text == '<hr>') return $text;
		return '<p>' . $text . '</p>';
	}


	/**
	 * Inline styles like strike, bold, italic, underline, code, links, and images.
	 *
	 * @param string $text
	 * @return string
	 */
	public function inline($text)
	{
		// Lines that end in two spaces require a BR
		$text = str_replace("  \n", "<br>\n", $text);

		//$text = preg_replace('~ \*\*((?:(?!\*\*).)+)\*\* ~', ' <b>$1</b> ', $text);

		// Italic, code, strike, and underline
		//$regex = '(?:^| )([*_`\-])((?:(?!\1).)+)\1(?: |$)';
		//$regex = '(?: |^)([*_`\-])((?:(?!\1).)+)\1(?: )';
		$regex = '(?:^|\W)([*_`\-])((?:(?!\1).)+)\1(?:$|\W)';
		preg_match_all("~$regex~m", $text, $matches, PREG_SET_ORDER);

		foreach($matches as $set)
		{
			if($set[1] == '`') $tag = 'code';
			elseif($set[1] == '*') $tag = 'b';
			//elseif($set[1] == '/') $tag = 'i';
			elseif($set[1] == '-') $tag = 'strike';
			else $tag = 'u';

			$text = str_replace($set[0], $set[0]{0} . "<$tag>{$set[2]}</$tag>" . substr($set[0], -1), $text);
		}

		// Links and Images
		$regex = '(!)*\[([^\]]+)\]\(([^\)]+?)(?: &quot;([\w\s]+)&quot;)*\)';
		preg_match_all("~$regex~", $text, $matches, PREG_SET_ORDER);

		foreach($matches as $set)
		{
			$title = isset($set[4]) ? " title=\"{$set[4]}\"" : '';
			if($set[1])
			{
				$text = str_replace($set[0], "<img src=\"{$set[3]}\"$title alt=\"{$set[2]}\"/>", $text);
			}
			else
			{
				$text = str_replace($set[0], "<a href=\"{$set[3]}\"$title>{$set[2]}</a>", $text);
			}
		}

		return $text;
	}
}