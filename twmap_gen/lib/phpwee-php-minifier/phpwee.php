<?
namespace PHPWee;
require_once("src/CssMin/CssMin.php");
require_once("src/HtmlMin/HtmlMin.php");
require_once("src/JsMin/JsMin.php");
 	

// Open-source (BSD) PHP inline minifier functions for HTML, XHTML, HTML5, CSS 1-3 and Javascript.   
// BSD Licensed  - https://github.com/searchturbine/phpwee-php-minifier/blob/master/LICENSE
// 
// Usage
//	$output = 	 \PHPWee\Minify::html($any_html);
//  $output =     \PHPWee\Minify::css($any_css);
//  $output =     \PHPWee\Minify::js($any_js);





class Minify{
	
	public static function html($html){
		return HtmlMin::minify($html);
	}
	
	public static function css($css){
		return CssMin::minify($css);
	}
	
	public static function js($js){
		return JsMin::minify($js);
	}
	
}
	
