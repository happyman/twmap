# phpwee-php-minifier


PHPWee is an inline PHP minifier for web resources.  It lets you minify HTML4, HTML5, XHTML, CSS and JavaScript files in a single line of code.

The best bit is its free and open source under the BSD license.




## Installing PHPWee

Installing PHPWee should only take a minute. Here are you 3 choices:

- Download from SearchTurbine.com - http://searchturbine.com/downloads/community/phpwee.zip)
- Install a Composer package form Packagist.com - https://packagist.org/packages/searchturbine/phpwee-php-minifier
- Hit the 'Download Zip' button on GitHub.


You can include all the necessary classes by simply including the phpwee-php-minifier/phpwee.php file.

```php
require_once ("phpwee-php-minifier/phpwee.php");
```



##  Using PHPWee

How to compress HTML:
```php
$minified_html = PHPWee\Minify::html($any_html);
```

This automatically compresses all inline scripts and stylesheets within the HTML document.   Apply a 'data-no-min' attribute to a script or style to exclude it from minification.

How to compress CSS:
```php
$minified_css = PHPWee\Minify::css($any_css);
```


How to compress JS:
```php
$minified_js = PHPWee\Minify::html($any_js);
```



```php

require_once ("phpwee-php-minifier/phpwee.php");

$html = file_get_contents("http://en.wikipedia.org/wiki/Minification_%28programming%29");
$minified_html = PHPWee\Minify::html($html);
// a 9.38% performance boost - in 3 lines of code!!

```

There are working examples in the /examples folder of the package.




## PHPWee Performance


- The compression ratio are typically 5-20% for HTML
- The compression ratio are typically 5-30% for CSS
- The compression ratio are typically 5-30% for JavaScript

Even highly optimized sites such as wikipedia.com, github.com and W3Schools.com can reduce the size of their HTML payload using PHPWee html compression. 


![Performance Graph ](http://searchturbine.com/assets/phpwee-performace.png)




## Who Makes PHPWee ?

The PHPWee HTML compression technology is totally new.  It actually parses HTML documents and removes unneeded spaces and verbosity without changing meaning in any way.  The output is generally at least as W3C compliant as the input.  


The Css compressor is built upon the CssMin package by Joe Scylla <joe.scylla@gmail.com>.  The JavaScript compressor is built upon  Douglas Crockford's JSMin.

## PHPWee Purpose


PHPWee is built and maintained by http://searchturbine.com as part of their 20% codeshare policy.  They can be contacted at community@seacrhturbine.com


PHPWee was designed as a pre-CDN optimizer for the searchturbine.com website - which is hosted on Github pages.  It currently offers about 22% compression site-wide - and is used in the deployment process.

This technology would is ideally placed to be used in deployment scripts for websites, and would also fair wall as part of a CDN architecture.

 
## PHPWee Improvements

- We are relatively new to Composer - and welcome input on package improvements.
- Removing default values from html tags should reduce HTML file size
- Allowing HTML fragments rather than whole documents would be ideal.


We "eat our own dog-food".    We will be using this package to optimize http://searchturbine.com - and will publish all results, insights and improvements.


Open-source (BSD) PHP inline minifier functions for HTML, XHTML, HTML5, CSS 1-3 and Javascript.   
