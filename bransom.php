<?php

require_once realpath(dirname(__FILE__) . '/php/Bootstrap.class.php');
Bootstrap::initConfig(dirname(__FILE__) . '/config/config.ini');
Bootstrap::import('nl.bransom.persistency.meta.MetaData');

$metaData = MetaData::getInstance();
$appNames = $metaData->getAppNames();

?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
	<head>
		<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
		<title>REST applications</title>
	</head>
	<body>
		<h3>REST applications</h3>
		<table>
			<?php foreach ($appNames as $appName) { ?>
				<tr><td colspan="2"><a href="REST/<?php echo $appName; ?>"><b><?php echo $appName; ?></b></a></td></tr>
				<tr>
					<td>&nbsp;</td>
					<td>schema = <?php echo $metaData->getSchema($appName)->getName(); ?></td>
				</tr>
				<tr>
					<td>&nbsp;</td>
					<td>namespace = <a href="<?php echo $metaData->getNamespaceUri($appName); ?>"><?php echo $metaData->getNamespaceUri($appName); ?></a></td>
				</tr>
				<tr><td colspan="2">&nbsp;</td></tr>
			<?php } ?>
		</table>
	</body>
</html>
