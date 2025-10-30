<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Router\Route;

$app = Factory::getApplication();
$doc = $app->getDocument();
$tplUri = rtrim(Uri::root(true), '/') . '/templates/' . $this->template;

// Базовые ассеты подключаем всегда
$doc->addStyleSheet($tplUri . '/assets/css/main.css');
$doc->addScript($tplUri . '/assets/js/main.js');

$cssToLoad = [];
$jsToLoad  = [];

$option = $app->input->getCmd('option', '');
$view   = $app->input->getCmd('view', '');
$layout = $app->input->getCmd('layout', '');


$assetMap = [
	'constitution' => [
		'css' => ['about-us.css', 'constitution.css'],
		'js'  => ['constitution.js'],
	],
	'faq' => [
		'css' => ['faq.css'],
		'js'  => ['faq.js'],
	],
	'about-us' => [
		'css' => ['about-us.css'],
	],
	'privacy-policy' => [],
];

$menu = $app->getMenu();
$active = $menu ? $menu->getActive() : null;
$pageKeys = [];

if ($active && !empty($active->alias)) {
	$pageKeys[] = (string) $active->alias;
}

if ($option === 'com_content' && $view === 'article' && $layout === 'constitution') {
	$pageKeys[] = 'constitution';
}

if ($option === 'com_finder') {
	$cssToLoad[] = 'results.css';
}

foreach (array_unique($pageKeys) as $key) {
	if (!isset($assetMap[$key])) {
		continue;
	}
	$bundle = $assetMap[$key];
	if (!empty($bundle['css'])) {
		$cssToLoad = array_merge($cssToLoad, (array) $bundle['css']);
	}
	if (!empty($bundle['js'])) {
		$jsToLoad = array_merge($jsToLoad, (array) $bundle['js']);
	}
}

$cssToLoad = array_values(array_unique($cssToLoad));
$jsToLoad  = array_values(array_unique($jsToLoad));

foreach ($cssToLoad as $cssFile) {
    $doc->addStyleSheet($tplUri . '/assets/css/' . $cssFile);
}

foreach ($jsToLoad as $jsFile) {
    $doc->addScript($tplUri . '/assets/js/' . $jsFile);
}

$isHome = $active && !empty($active->home);

$bodyClasses = [];
if ($active && !empty($active->alias)) {
	$bodyClasses[] = 'page-' . preg_replace('#[^a-z0-9\-]+#i', '-', (string) $active->alias);
}
if (in_array('privacy-policy', $pageKeys, true)) {
	$bodyClasses[] = 'colored_bg';
}
$bodyClasses = array_values(array_unique(array_filter($bodyClasses)));

// Вычисляем action для формы поиска: пункт меню на com_finder, иначе прямой URL
$searchMenuItem = $menu ? $menu->getItems('link', 'index.php?option=com_finder&view=search', true) : null;
$searchAction = $searchMenuItem
	? Route::_('index.php?option=com_finder&view=search&Itemid=' . (int) $searchMenuItem->id)
	: (rtrim(Uri::base(true), '/') . '/index.php?option=com_finder&view=search');

if ($option === 'com_finder') {
	$isHome = false;
}
?>

<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0" />
		<jdoc:include type="metas" />
		<jdoc:include type="styles" />
		<jdoc:include type="scripts" />
	</head>
	<body<?php echo empty($bodyClasses) ? '' : ' class="' . htmlspecialchars(implode(' ', $bodyClasses), ENT_QUOTES, 'UTF-8') . '"'; ?>>

<?php if ($isHome): ?>
		<jdoc:include type="message" />
		<jdoc:include type="component" />
<?php else: ?>

		<nav class="nav_sec">
			<div class="nav_wrapper container">
				<div class="nav_left">
					<a href="/" class="logo"><img src="<?php echo $tplUri; ?>/assets/logo.svg" alt="" /></a>
					<div class="nav_menu">
						<?php $modsMenu = ModuleHelper::getModules('menu'); if (!empty($modsMenu)) { foreach ($modsMenu as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
							<a href="/constitution"><p data-label="Канстытуцыя">Канстытуцыя</p></a>
							<a href="/projects"><p data-label="Праекты">Праекты</p></a>
							<a href="/news"><p data-label="Навіны">Навіны</p></a>
							<a href="/about-us"><p data-label="Пра нас">Пра нас</p></a>
							<a href="/faq"><p data-label="FAQ">FAQ</p></a>
						<?php } ?>
					</div>
				</div>
				<div class="nav_right">
					<form class="nav_search" action="<?php echo $searchAction; ?>" method="get">
						<input type="hidden" name="option" value="com_finder" />
						<input type="hidden" name="view" value="search" />
						<img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
						<input type="text" name="q" placeholder="Пошук..." />
					</form>
					<button class="nav_btn">Donate</button>
					<button class="nav_btn lang_toggler">BEL</button>
				</div>
				<img onclick="toggleMenu()" class="mob_menu_btn" src="<?php echo $tplUri; ?>/assets/icons/mob-menu.svg" alt="" />
			</div>
		</nav>

		<div class="mob_menu">
			<div class="mob_exit">
				<img onclick="toggleMenu()" src="<?php echo $tplUri; ?>/assets/icons/exit.svg" alt="" />
			</div>
			<div class="mob_menu_content">
				<div class="nav_menu">
					<?php $modsMenu = ModuleHelper::getModules('menu'); if (!empty($modsMenu)) { foreach ($modsMenu as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
						<a href="/constitution"><p data-label="Канстытуцыя">Канстытуцыя</p></a>
						<a href="/projects"><p data-label="Праекты">Праекты</p></a>
						<a href="/news"><p data-label="Навіны">Навіны</p></a>
						<a href="/about-us"><p data-label="Пра нас">Пра нас</p></a>
						<a href="/faq"><p data-label="FAQ">FAQ</p></a>
					<?php } ?>
				</div>
				<form class="nav_search" action="<?php echo $searchAction; ?>" method="get">
					<input type="hidden" name="option" value="com_finder" />
					<input type="hidden" name="view" value="search" />
					<img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
					<input type="text" name="q" placeholder="Пошук..." />
				</form>
				<div class="mob_menu_actions">
					<button class="nav_btn">Donate</button>
					<button class="nav_btn lang_toggler">BEL</button>
				</div>
			</div>
		</div>

		<jdoc:include type="message" />
		<jdoc:include type="component" />

		<footer>
			<div class="footer_wrapper container">
				<div class="footer_main">
					<div class="footer_main_left">
						<a href="/" class="footer_logo"><img src="<?php echo $tplUri; ?>/assets/icons/project-icon.svg" alt="" /></a>
						<div class="footer_email">
							<h4>Ёсць пытанні або думкі?</h4>
							<p>Напішыце нам – мы заўсёды гатовыя <br />выслухаць і адказаць.</p>
							<a href="mailto:hello@prastora.online"><span>hello@prastora.online</span></a>
						</div>
						<div class="footer_contacts">
							<h4>Кантакты:</h4>
							<a href="mailto:hello@prastora.online"><img src="<?php echo $tplUri; ?>/assets/icons/mail.svg" alt="" /><p>hello@prastora.online</p></a>
							<a href="https://t.me/konstituciya_bel"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /><p>@konstituciya_bel</p></a>
							<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" /><p>Канстытуцыя Рэспублікі Беларусь</p></a>
						</div>
					</div>
					<div class="header_socials">
						<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" /></a>
						<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /></a>
						<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/yt.svg" alt="" /></a>
					</div>
				</div>
				<div class="footer_btm">
					<p>© 2021, Светлана Тихановская</p>
					<a href="/privacy-policy"><p>Privacy Policy</p></a>
				</div>
			</div>
		</footer>
<?php endif; ?>
	</body>
</html>
