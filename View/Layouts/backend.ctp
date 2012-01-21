<?php echo $this->Html->docType('xhtml-strict'); ?>
<html xmlns="http://www.w3.org/1999/xhtml">

<head>
<title><?php echo $title_for_layout?></title>
<?php 
  echo $this->Html->charset('UTF-8')."\n";
  echo $this->Html->meta('icon')."\n";
  echo $this->Html->css('backend')."\n";
  echo $this->Html->script('phtagr');
  echo $scripts_for_layout; 
?>

</head>

<body>
<div id="page">

<div id="header"><div class="sub">
<?php echo $this->Html->link(__('Gallery', true), '/'); ?>
</div></div>

<div id="main">

<div id="sidebar">
<div class="box">
<h1>Menu</h1>
<?php echo $this->Menu->menu('main'); ?>
</div>
</div>
<div id="content">
<?php echo $content_for_layout?>
</div>
</div><!-- main -->

<div id="footer"><div class="sub">
<p>&copy; 2006-2011 by <?php echo $this->Html->link("Gallery phTagr", 'http://www.phtagr.org'); ?></p>
</div></div>
</body>
</html>