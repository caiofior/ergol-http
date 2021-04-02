<?php
/**
 * #  ergol-http
 * 
 * Gemini capsule server through http.
 * 
 * https://codeberg.org/adele.work/ergol-http
 * 
 * Version 0.4
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

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require(__DIR__.'/config.php');

if(isset($_GET['q']))
	$q = $_GET['q'];
else
	$q = $_SERVER['REQUEST_URI'];
// Loading config...
$conf_filename = @realpath(ERGOL_JSON);

$conf_json = file_get_contents($conf_filename);
if($conf_json===false)
	die("Unable to open file ".$conf_filename."\n");

$conf_json = preg_replace('/[\x00-\x1F\x80-\xFF]/', '',$conf_json);

$conf = json_decode($conf_json);
if($conf===null)
	die("Unable to parse ".$conf_filename." : ".json_last_error_msg()."\n");

foreach($conf->capsules as $hostname => $capsule)
{
	if(empty($conf->capsules->$hostname->redirect))
	{
		$conf->capsules->$hostname->folder = str_replace("{here}",dirname($conf_filename),$capsule->folder);
	}
	else
	{
		unset($conf->capsules->$hostname->folder);
	}
}

if(strpos($_SERVER['HTTP_HOST'],':')!==false)
	$capsule = strtolower(substr($_SERVER['HTTP_HOST'], 0, strpos($_SERVER['HTTP_HOST'],':')));
else
	$capsule = strtolower($_SERVER['HTTP_HOST']);

$response = false;
$response_code = 0;
$body = false;

if($response === false && !isset($conf->capsules->$capsule))
{
	$response = "HTTP/1.1 400 BAD REQUEST";
	$response_code = 0;
}

if($response === false && strpos(str_replace("\\",'/',rawurldecode($q)),'/..')!==false)
{
	$response = "HTTP/1.1 400 BAD REQUEST";
	$response_code = 0;
}

if(!empty($conf->capsules->$capsule->redirect))
{
	// redirect to another capsule
	$response = "Location: ".str_replace('gemini://','http://',$conf->capsules->$capsule->redirect.$q);
	$response_code = 302;
}
elseif($response === false)
{
	// search requested file
	$filename = $conf->capsules->$capsule->folder.rawurldecode($q);
	$lang = $conf->capsules->$capsule->lang;
	if(!empty($conf->capsules->$capsule->lang_regex))
	{
		// search lang code in requested path (ex: file.fr.gmi)
		preg_match($conf->capsules->$capsule->lang_regex, rawurldecode($q), $matches);
		if(isset($matches[1]))
			$lang = strtolower($matches[1]);
	}
	// search favicon
	$favicon = @file_get_contents($conf->capsules->$capsule->folder.'/favicon.txt');
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
			$body=gmi2html($capsule, $body, $lang,
				'gemini://'.$capsule.($conf->port==1965?'':(':'.$conf->port)).$q,
				$favicon);
		}
	}
	
	if($response === false && is_dir($filename))
	{
		// if path is a directory name redirect into it
		if(substr($filename,-1)!='/')
		{
			$response = "Location: ".$q."/";
			$response_code = 302;
		}
		else
		{
			$mime = "text/html";
			if(file_exists($filename.'/index.gmi'))
			{
				// open default file index.gmi
				$response = "OK";
				$filename = $filename.'/index.gmi';
				$body = file_get_contents($filename);
				$body = gmi2html($capsule, $body, $lang,
					'gemini://'.$capsule.($conf->port==1965?'':(':'.$conf->port)).$q,
					$favicon);
			}
			elseif(is_array($conf->capsules->$capsule->auto_index_ext))
			{
				// build auto index
				$response = "OK";
				$body = "# ".$capsule." ".basename($filename)."\r\n";
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
					if(is_dir($filename.'/'.$entry) &&
						!in_array('/', $conf->capsules->$capsule->auto_index_ext))
					{
						// folder ext "/" not in auto_index conf
						continue;
					}
					if(is_file($filename.'/'.$entry) &&
						!in_array(substr($entry,strrpos($entry,'.')), $conf->capsules->$capsule->auto_index_ext))
					{
						// ext not in auto_index conf
						continue;
					}						$link_name = $entry;
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
				$body = gmi2html($capsule, $body, $lang,
					'gemini://'.$capsule.($conf->port==1965?'':(':'.$conf->port)).$q,
					$favicon);
			}
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
	$title='';
	$lines=array();
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
				$lines[] = "<h1>".htmlentities(substr($line,1))."</h1>";
				break;
			case "##":
				$lines[] = "<h2>".htmlentities(substr($line,2))."</h2>";
				break;
			case "###":
				$lines[] = "<h3>".htmlentities(substr($line,3))."</h3>";
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
				$lines[] = '<a href="'.str_replace('gemini://'.$capsule,$_SERVER["REQUEST_SCHEME"].'://'.$capsule, $link[0]).'">'.htmlentities(empty($link[1])?rawurldecode($link[0]):$link[1])."</a>";
				if(strpos($link[0], '://')===false &&		 // relative image
				   in_array(strtolower(substr($link[0],-4)),array('.jpg','.png','.gif','jpeg','webp')) )
					$lines[] = ' 🖼️ <div class="inline-img"><img src="'.$link[0].'" alt="'.htmlentities(empty($link[1])?rawurldecode($link[0]):$link[1]).'" /></div>';
				$lines[]='</p>';
				break;
			default:
				$lines[] = "<p>".htmlentities($line)."</p>";
				break;
		}
	}
	$html = '<!DOCTYPE html>
	<html xmlns="http://www.w3.org/1999/xhtml" lang="'.$lang.'">
	<head>
	  <meta charset="utf-8">
	  <meta name="viewport" content="width=device-width, initial-scale=1.0">
	  <title>'.htmlentities($title.' | '.$urlgem).'</title>
	  <link rel="alternate" href="'.$urlgem.'" type="text/gemini" title="Gemini protocol">
	  <style media="screen">
	  '.@file_get_contents(__DIR__.'/style.css').'
	  </style>
	</head>
	<body>
	<div class="main" role="article">
	'.implode("\n",$lines).'
	</div>
	<div class="gemini" role="banner">
	<span>'.$favicon.'</span>
	<a href="'.$urlgem.'" title="Gemini address">'.htmlentities($urlgem).'</a>
	</div>
	</body>
	</html>';
	return $html;
}
