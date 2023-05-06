<?php
/**
 * #  ergol-http
 * 
 * Gemini capsule server through http.
 * 
 * https://codeberg.org/adele.work/ergol-http
 * 
 * Version 0.4.1
 * 
 * ## Copyright 2021 Adële
 * 
 * Permission is hereby granted, free of charge, to any person obtaining a 
 * copy of this software and associated documentation files (the 
 * "Software"), to deal in the Software without restriction, including 
 * without limitation the rights to use, copy, modify, merge, publish, 
 * distribute, sublicense, and/or sell copies of the Software, and to 
 * permit persons to whom the Software is furnished to do so, subject to 
 * the following conditions:
 * 
 * The above copyright notice and this permission notice shall be included 
 * in all copies or substantial portions of the Software.
 * 
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS 
 * OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF 
 * MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. 
 * IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY 
 * CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN ACTION OF CONTRACT, 
 * TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE 
 * SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
 * 
 */
if (!function_exists('str_starts_with'))
{
    function str_starts_with($haystack, $needle)
    {
        return (string)$needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
    }
}
if (!function_exists('str_ends_with'))
{
    function str_ends_with($haystack, $needle)
    {
        return $needle !== '' && substr($haystack, -strlen($needle)) === (string)$needle;
    }
}
if (!function_exists('str_contains'))
{
    function str_contains($haystack, $needle)
    {
        return $needle !== '' && mb_strpos($haystack, $needle) !== false;
    }
}
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require(__DIR__.'/config.php');

$response = false;
$response_code = 0;
$body = false;

if(isset($_GET['qx']))
{
	$response = "OK";
	$body = "# You are following a Gemini link to another server

You can't access all the Geminispace with this proxy. If you want to follow this link, you have to install ans use a Gemini client.

You asked to follow :
```gemini-url
".urldecode($_GET['qx'])."
```";
	$mime="text/html";
	$body=gmi2html($capsule, $body, 'en', $_GET['qx'], '');
}

if(isset($_GET['q']))
	$q = $_GET['q'];
else
	$q = 'index.gmi';

if($response === false)
{
	// search requested file
	$filename = GEMINI_PATH.rawurldecode($q);
	$lang = GEMINI_LANG;
	// search favicon
	$favicon = @file_get_contents(GEMINI_PATH.'/favicon.txt');
	$favicon = mb_substr(trim($favicon),0,1);
}

if($response === false && $q==='/favicon.ico' && !empty($favicon))
{
	// generate favicon
	$image = new Imagick();
	$draw = new ImagickDraw();
	$pixel = new ImagickPixel( 'white' );
	$image->newImage(128, 128, $pixel);
	$draw->setFont('TwitterColorEmoji-SVGinOT.ttf');
	$draw->setFontSize( 120 );
	$draw->setFillColor('#999');
	$image->annotateImage($draw, 3, 107, 0, $favicon);
	$image->annotateImage($draw, 4, 106, 0, $favicon);
	$image->annotateImage($draw, 5, 107, 0, $favicon);
	$image->annotateImage($draw, 2, 108, 0, $favicon);
	$draw->setFillColor('#666');
	$image->annotateImage($draw, 6, 108, 0, $favicon);
	$image->annotateImage($draw, 3, 109, 0, $favicon);
	$image->annotateImage($draw, 4, 110, 0, $favicon);
	$image->annotateImage($draw, 5, 109, 0, $favicon);
	$draw->setFillColor('#333');
	$image->annotateImage($draw, 4, 108, 0, $favicon);
	$image->setImageFormat('png');
	header('Content-type: image/png');
	echo $image;
	exit;
}

if($response === false && file_exists($filename))
{
	if($response === false && is_file($filename))
	{
		$mime = mime_content_type($filename);
		if($mime == "text/plain")
		{
			if(substr($q,-4)=='.gmi')
				$mime = "text/gemini";
			elseif(substr($q,-3)=='.md')
				$mime = "text/markdown";
			elseif(substr($q,-4)=='.html')
				$mime = "text/html";
		}
		$response = "OK";
		$body = file_get_contents($filename);
		if($mime=="text/gemini")
		{
			$mime="text/html";
			$body=gmi2html('', $body, $lang,
				'gemini://'.$q,
				$favicon);
		}
	}
	
	if($response === false && is_dir($filename))
	{
			$mime = "text/html";
			if(file_exists($filename.'/index.gmi'))
			{
				// open default file index.gmi
				$response = "OK";
				$filename = $filename.'/index.gmi';
				$body = file_get_contents($filename);
				$body = gmi2html('', $body, $lang,
					'gemini://'.$q,
					$favicon);
			} else {
			
				// build auto index
				$response = "OK";
				$body = "# ".basename($filename)."\r\n";
				$body .= "=> ../ [..]\r\n";
				// three blocks
				$items_dir=array(); // sub directories
				$items_gmi=array(); // gmi file chronogically desc
				$items_oth=array(); // other files
				$d = dir($filename);
				while (false !== ($entry = $d->read()))
				{
					if(substr($entry,0,1)=='.')
					{
						// dir itself
						continue;
					}
                                        $link_name = $entry;
					if(substr($entry,-4)=='.gmi')
					{
						// build feed for subscriptions for .gmi files,
						// adding date YYYY-MM-DD in link if not file name
						// see specs gemini://gemini.circumlunar.space/docs/companion/subscription.gmi
						$entry_name = str_replace('_',' ',substr($entry,0,-4));
						if(!preg_match("/^[0-9]{4}-(0[1-9]|1[0-2])-(0[1-9]|[1-2][0-9]|3[0-1])\s$/",substr($entry_name,0,11)))
							$link_name = date("Y-m-d", filemtime($filename.'/'.$entry))." ".$entry_name;
						else
							$link_name = " ".$entry_name;
						$items_gmi[$link_name." ".$entry] = "=> ".rawurlencode($entry)." ".$link_name;
					}
					elseif(is_dir($filename.'/'.$entry))
					{
						// sub directory
						$link_name = "[".$entry."]";
						$items_dir[$entry] = "=> ".rawurlencode($entry)."/ ".$link_name;
					}
					else
					{
						// other file ext
						$items_oth[$entry] = "=> ".rawurlencode($entry)." ".$link_name;
					}
				}
				$d->close();
				ksort($items_dir);
				krsort($items_gmi);
				ksort($items_oth);
				if(count($items_dir)>0)
					$body .= implode("\r\n", $items_dir)."\r\n";
				if(count($items_gmi)>0)
					$body .= implode("\r\n", $items_gmi)."\r\n";
				if(count($items_oth)>0)
					$body .= implode("\r\n", $items_oth)."\r\n";
				$body = gmi2html('', $body, $lang,
					'gemini://'.$q,
					$favicon);
                        }

	}
}

if($response === false)
{
	$response = "HTTP/1.1 404 NOT FOUND";
	$response_code = 0;
}

if($response != "OK")
{
	header($response, true, $response_code);
	exit;
}

header("Content-Type: ".$mime, true);
header("Content-Length: ".strlen($body), true);
echo $body;
exit;


function gmi2html($capsule, $body, $lang, $urlgem, $favicon)
{
	global $whitelist_domains;
	if(isset($_SERVER['REQUEST_SCHEME'])) {
		$scheme = $_SERVER['REQUEST_SCHEME'];
	}
	else if(isset($_SERVER['HTTPS']) && !empty($_SERVER['HTTPS'])) {
		$scheme = 'https';
	}
	else {
		$scheme = 'http';
	}
	$title='';
	$lines=array();
	$tocs=array();
	$lev1=0;
	$lev2=0;
	$lev3=0;
	$pre=false;
	$glines = explode("\n", $body);
	foreach($glines as $line)
	{
		if($pre && substr(trim($line, "\r\n"),0,3)!='```')
		{
			$lines[] = str_replace(array('&','<','>','"',"'"), array('&amp;','&lt;','&gt;','&quot;','&#39;'), $line);
			continue;
		}
		$line=trim($line, "\r\n");
		$prefix = explode(' ',substr($line,0,3),2);
		$prefix=$prefix[0];
		// if no space before titles
		if(substr($line,0,1)=='#')
			$prefix='#';
		if(substr($line,0,2)=='##')
			$prefix='##';
		if(substr($line,0,3)=='###')
			$prefix='###';
		if($prefix=="```")
		{
			if($pre)
				$lines[]='</pre>';
			else
				$lines[]='<pre title="'.htmlentities(substr($line,3)).'">';
			$pre=!$pre;
			continue;
		}
		if($prefix=="#" && empty($title))
			$title = trim(substr($line,2));
		switch($prefix)
		{
			case "#":
				$lev1++;
				$lev2=0;
				$lev3=0;
				$levid = $lev1;
				$lines[] = '<h1 id="'.$levid.'">'.trim(htmlentities(substr($line,1))).'</h1>';
				$tocs[] = '<li class="l1"><a href="#'.$levid.'">'.trim(htmlentities(substr($line,1))).'</a></li>';
				break;
			case "##":
				$lev2++;
				$lev3=0;
				$levid = $lev1.'-'.$lev2;
				$lines[] = '<h2 id="'.$levid.'">'.trim(htmlentities(substr($line,2))).'</h2>';
				$tocs[] = '<li class="l2"><a href="#'.$levid.'">'.trim(htmlentities(substr($line,2))).'</a></li>';
				break;
			case "###":
				$lev3++;
				$levid = $lev1.'-'.$lev2.'-'.$lev3;
				$lines[] = '<h3 id="'.$levid.'">'.trim(htmlentities(substr($line,3))).'</h3>';
				$tocs[] = '<li class="l3"><a href="#'.$levid.'">'.trim(htmlentities(substr($line,3))).'</a></li>';
				break;
			case ">":
				$lines[] = "<blockquote>".htmlentities(substr($line,2))."</blockquote>";
				break;
			case "*":
				$lines[] = "<li>".htmlentities(substr($line,2))."</li>";
				break;
			case "=>":
				$lines[]='<p>';
				$link = explode(' ', substr($line,3), 2);
				if(!str_starts_with($link[0], 'http'))
                                {
					$link_domain = parse_url($link[0], PHP_URL_HOST);
                                        $urlPrefix = $scheme.'://'.$_SERVER['SERVER_NAME'];
                                        $urlPrefix .= preg_replace('/\/index\.php\?.*/','',($_SERVER['REQUEST_URI']??''));
                                        $link_href = $urlPrefix.'/index.php?q=';
					if (
	    				($_REQUEST['q']??'') != '' &&
	    				!str_ends_with(($_REQUEST['q']??''),'.gmi')
					)
                                        {
					    $link_href .= urlencode(($_REQUEST['q']??'').'/');
                                        } elseif (str_contains(($_REQUEST['q']??''),'/')) {
                                            $link_href .= urlencode(preg_replace('/\/(?!.*\/).*/','/',($_REQUEST['q']??'')));
                                        }
					$link_href .= urlencode($link[0]);
					$lines[] = '<a href="'.$link_href.'">'.htmlentities(empty($link[1])?rawurldecode($link[0]):$link[1])."</a>";
				}
				else
                                {
					$lines[] = '<a href="'.$link[0].'">'.htmlentities(empty($link[1])?rawurldecode($link[0]):$link[1])."</a>";
				}
				if(strpos($link[0], '://')===false &&		 // relative image
				   in_array(strtolower(substr($link[0],-4)),array('.jpg','.png','.gif','jpeg','webp')) )
					$lines[] = ' 🖼️ <div class="inline-img"><img src="'.$link_href.'" alt="'.htmlentities(empty($link[1])?rawurldecode($link[0]):$link[1]).'" /></div>';
				$lines[]='</p>';
				break;
			default:
				$lines[] = "<p>".htmlentities($line)."</p>";
				break;
		}
	}
	$style = file_get_contents(__DIR__.'/style.css');
	ob_start();
	include "template.php";
	$html = ob_get_contents();
	ob_end_clean();
	return $html;
}
