#PHPWee

Open-source (BSD) PHP inline minifier functions for HTML, XHTML, HTML5, CSS 1-3 and Javascript. http://searchturbine.com/php/phpwee

A new PHP based HTML minifier using Document Object Model Parsing to ensure quality, clean output.

Sudo-Code Algorithm:

- Parse HTML into a DOM (forgiving).
- Remove all comments except those that contain IE proprietary conditional comments.
- Simplify all multiple-space /\s{2,}/ sequences to one space - unless they lie inside a CDATA clock. 
- Remove all spaces directly adjacent inside or outsize all non-CDATA lock level elements.
- Minify all inline CSS (unless data-no-min attribute present)
- Minify all inline JS (unless data-no-min attribute present)


The output is generally at least as W3C compliant as the input - and 5-20% smaller and faster - even after gzip encoding is applied.