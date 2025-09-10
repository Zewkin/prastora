<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();
$doc = $app->getDocument();
$tplPath = $this->baseurl . '/templates/' . $this->template;

$wam = $doc->getWebAssetManager();
$wam->registerAndUseStyle('tpl.prastora.main', 'assets/css/main.css');
$wam->registerAndUseStyle('tpl.prastora.page.constitution', 'assets/css/constitution.css');
$wam->registerAndUseStyle('tpl.prastora.page.faq', 'assets/css/faq.css');
$wam->registerAndUseStyle('tpl.prastora.page.about', 'assets/css/about-us.css');
$wam->registerAndUseStyle('tpl.prastora.page.results', 'assets/css/results.css');
$wam->registerAndUseScript('tpl.prastora.main', 'assets/js/main.js', [], ['defer' => true]);
$wam->registerAndUseScript('tpl.prastora.page.constitution', 'assets/js/constitution.js', [], ['defer' => true]);
$wam->registerAndUseScript('tpl.prastora.page.faq', 'assets/js/faq.js', [], ['defer' => true]);
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
	<head>
		<jdoc:include type="metas" />
		<jdoc:include type="styles" />
		<jdoc:include type="scripts" />
	</head>
	<body>
		<header>
			<jdoc:include type="modules" name="header" style="none" />
			<jdoc:include type="modules" name="menu" style="none" />
		</header>
		<main>
			<section>
				<jdoc:include type="modules" name="hero" style="none" />
			</section>
			<section>
				<jdoc:include type="modules" name="home-section-a" style="none" />
				<jdoc:include type="modules" name="home-section-b" style="none" />
				<jdoc:include type="modules" name="home-section-c" style="none" />
			</section>
			<div class="container">
				<aside>
					<jdoc:include type="modules" name="sidebar" style="none" />
				</aside>
				<section class="content">
					<jdoc:include type="message" />
					<jdoc:include type="component" />
				</section>
			</div>
		</main>
		<footer>
			<jdoc:include type="modules" name="footer" style="none" />
			<jdoc:include type="modules" name="footer-bottom" style="none" />
		</footer>
	</body>
</html>
