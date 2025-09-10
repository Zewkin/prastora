<?php
defined('_JEXEC') or die;

$code = $this->error->getCode();
$message = $this->error->getMessage();
?><!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
	<head>
		<jdoc:include type="metas" />
		<title><?php echo htmlspecialchars($code . ' - ' . $message, ENT_QUOTES, 'UTF-8'); ?></title>
	</head>
	<body>
		<h1><?php echo (int) $code; ?></h1>
		<p><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
		<p><a href="/">На главную</a></p>
	</body>
</html>
