# ! ~ WARNING ~ !

This is still a work-in-progress. Only about 80% of the system works currently.

# MarkedUp

MarkedUp is an altered version of the [Markdown](http://daringfireball.net/projects/markdown/)
markup language by [John Gruber](http://daringfireball.net/). While MarkedUp adds
a few new things, it also loses a couple of the less-used Markdown features.

Included in this library are HTML-to-Markdown and Markdown-to-HTML converters.

## MarkedUp\Text (Markdown to HTML)

MarkedUp works with the assumption that there is a blank line between each block
of content in your text. In other words, your text should look normal with proper
spacing.

1. [Tables](#tables)
2. [Lists](#lists)
3. [Preformatted Code](#preformatted_code)
4. [Blockquotes](#blockquotes)
5. [Headers](#headers)
6. [Paragraphs](#paragraphs)
7. [Horizontal rule](#horizontal_rule)

### Tables

Tables are constructed by placeing "|" at the beginging of each line of the table.

	| Column One | Column Two | Column Three
	|------------|------------|--------------
	| 2344       | 764        | 3545
	| 1285       | 12         | 92
	| 17         | 764        | 3545
	| 333        | 466        | 100

Each table must include a header, line separater, and body rows.

| Column One | Column Two | Column Three
|------------|------------|--------------
| 2344       | 764        | 3545
| 1285       | 12         | 92
| 17         | 764        | 3545
| 333        | 466        | 100

## MarkedUp\HTML (HTML to Markdown)

...


## About

Why do this again? I wanted a Markdown implementation that supports tables
and code highlighting. I also wanted one that was at least several times faster
than all the current implementations so it could actually be used on a production
server.

Open Source under the [MIT License](http://david.mit-license.org/).

[David Pennington](http://davidpennington.me)
