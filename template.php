<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml" lang="<?php echo $lang; ?>">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1.0">
		<title><?php echo $title; ?></title>
		<link rel="alternate" href="<?php echo $urlgem; ?>" type="text/gemini" title="Gemini protocol">
		<style media="screen">
			<?php echo $style; ?>
		</style>
	</head>
	<body id="top">
		<?php if(count($tocs)>1)
		{
		?>
		<div class="toc" role="navigation">
			<span class="icon">⚓</span>
			<ul>
				<?php echo implode("\n",$tocs); ?>
			</ul>
		</div>
		<?php
		}
		?>
		<label class="control" for="check-smaller">🔍 -</label>
		<input type="radio" name="check-size" id="check-smaller" class="control check-smaller" />
		<input type="radio" name="check-size" id="check-small" class="control check-small" />
		<input type="radio" name="check-size" id="check-normal" class="control check-normal" checked="checked" />
		<input type="radio" name="check-size" id="check-big" class="control check-big" />
		<input type="radio" name="check-size" id="check-bigger" class="control check-bigger" />
		<label class="control" for="check-bigger">+</label>
		<div class="main" role="article">
			<?php echo implode("\n",$lines); ?>
		</div>
		<div class="topanchor"><a href="#top">🔝</a></div>
		<div class="gemini" role="banner">
			<span><?php echo $favicon; ?></span>
			<a href="<?php echo $urlgem; ?>" title="Gemini address"><?php echo htmlentities($urlgem); ?></a>
		</div>
	</body>
</html>
