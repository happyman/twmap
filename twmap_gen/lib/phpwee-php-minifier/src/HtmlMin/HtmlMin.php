<?php
namespace PHPWee;


/*

PHPWee PHP Minifier Package - http://searchturbine.com/php/phpwee

Copyright (c) 2015, SearchTurbine - Enterprise Search for Everyone
http://searchturbine.com/

All rights reserved. 

Redistribution and use in source and binary forms, with or without
modification, are permitted provided that the following conditions are met:

Redistributions of source code must retain the above copyright notice, this
list of conditions and the following disclaimer.

Redistributions in binary form must reproduce the above copyright notice,
this list of conditions and the following disclaimer in the documentation
and/or other materials provided with the distribution.

THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.


*/
	// -- Class Name : HtmlMin
	// -- Purpose : PHP class to minify html code.
	// -- Usage:  echo PHPWee\Minify::html($myhtml);
	// -- notes:  aply data-no-min to a style or script node to exempt it
	// -- HTML 4, XHTML, and HTML 5 compliant
	
	class HtmlMin{
			// -- Function Name : minify - Params : $html, $js = true, $css = true
		public static function minify($html, $js = true, $css = true){
			$doc = new \DOMDocument();
			$doc->preserveWhiteSpace = false;
			@$doc->loadHTML($html);
			$xpath = new \DOMXPath($doc);
			foreach ($xpath->query('//comment()') as $comment) {
				$val= $comment->nodeValue;
				if( strpos($val,'[')!==0){
					$comment->parentNode->removeChild($comment);
				}
			}
			$doc->normalizeDocument();
			$textnodes = $xpath->query('//text()');
			$skip = ["style","pre","code","script","textarea"];
			foreach($textnodes as $t){
				$xp = $t->getNodePath();
				$doskip = false;
				foreach($skip as $pattern){
					if(strpos($xp,"/$pattern")!==false){
						$doskip = true;
						break;
					}
				}
				if($doskip){
					continue;
				}
				$t->nodeValue = preg_replace("/\s{2,}/", " ", $t->nodeValue);
			}
			$doc->normalizeDocument();
			$divnodes = $xpath->query('//div|//p|//nav|//footer|//article|//script|//hr|//br');
			foreach($divnodes as $d){
				$candidates = [];
				if(count($d->childNodes)){
					$candidates[] = $d->firstChild;
					$candidates[] = $d->lastChild;
					$candidates[] = $d->previousSibling;
					$candidates[] = $d->nextSibling;
				}
				foreach($candidates as $c){
					if($c==null){
						continue;
					}
					if($c->nodeType==3){
						$c->nodeValue = trim($c->nodeValue);
					}
				}
			}
			$doc->normalizeDocument();
			if($js){
				$scriptnodes = $xpath->query('//script');
				foreach($scriptnodes as $d){
					if($d->hasAttribute("type") && strtolower($d->getAttribute("type"))!=='text/javascript' ){
						continue;
					}
					if($d->hasAttribute("data-no-min")){
						continue;
					}
					if(trim($d->nodeValue)==""){
						continue;
					}
					$d->nodeValue =  JSMin::minify( $d->nodeValue);
				}
			}
			if($css){
				$cssnodes = $xpath->query('//style');
				foreach($cssnodes as $d){
					if($d->hasAttribute("data-no-min")){
						continue;
					}
					if(trim($d->nodeValue)==""){
						continue;
					}
					$d->nodeValue = CssMin::minify( $d->nodeValue);
				}
			}
			return ($doc->saveHTML());
		}
	}