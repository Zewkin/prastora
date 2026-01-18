<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Database\ParameterType;

require_once __DIR__ . '/helpers/language.php';
require_once __DIR__ . '/helpers/category.php';

$app = Factory::getApplication();
$language = $app->getLanguage();
$language->load('tpl_prastora', JPATH_SITE, null, true, true);
prastoraRegisterScriptTranslations();
$doc = $app->getDocument();
$tplUri = rtrim(Uri::root(true), '/') . '/templates/' . $this->template;
$langTag = strtolower((string) $language->getTag());
$logoSrc = $tplUri . '/assets/' . ((strpos($langTag, 'en') === 0) ? 'logo-en.svg' : 'logo.svg');
$tplPath = __DIR__;
$baseHref = rtrim(Uri::root(true), '/') . '/';
$newsPath = (strpos($langTag, 'ru') === 0) ? '/news-ru' : '/news';
$aboutPath = '/about-us';
$faqPath = '/faq';

// Базовые ассеты подключаем всегда
$mainCssOptions = [];
$mainCssPath = $tplPath . '/assets/css/main.css';
if (is_file($mainCssPath)) {
	$mainCssOptions['version'] = (string) filemtime($mainCssPath);
}
$doc->addStyleSheet($tplUri . '/assets/css/main.css', $mainCssOptions);

$mainJsOptions = [];
$mainJsPath = $tplPath . '/assets/js/main.js';
if (is_file($mainJsPath)) {
	$mainJsOptions['version'] = (string) filemtime($mainJsPath);
}
$doc->addScript($tplUri . '/assets/js/main.js', $mainJsOptions);

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
	'constitution-en' => [
		'css' => ['about-us.css', 'constitution.css'],
		'js'  => ['constitution.js'],
	],
	'constitution-ru' => [
		'css' => ['about-us.css', 'constitution.css'],
		'js'  => ['constitution.js'],
	],
	'faq' => [
		'css' => ['faq.css'],
		'js'  => ['faq.js'],
	],
	'faq-en' => [
		'css' => ['faq.css'],
		'js'  => ['faq.js'],
	],
	'faq-ru' => [
		'css' => ['faq.css'],
		'js'  => ['faq.js'],
	],
	'about-us' => [
		'css' => ['about-us.css'],
	],
	'about-us-en' => [
		'css' => ['about-us.css'],
	],
	'about-us-ru' => [
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

if ($option === 'com_content' && $view === 'article') {
	$articleIdInput = $app->input->getString('id', '');
	$articleId = (int) $app->input->getInt('id');
	if ($articleId <= 0 && $articleIdInput !== '') {
		[$articleIdPart] = explode(':', $articleIdInput, 2);
		$articleId = (int) $articleIdPart;
	}

	if ($articleId > 0) {
		try {
			$db = Factory::getDbo();
			$query = $db->getQuery(true)
				->select([
					$db->quoteName('a.alias', 'article_alias'),
					$db->quoteName('a.catid'),
					$db->quoteName('c.alias', 'category_alias'),
					$db->quoteName('c.title', 'category_title'),
					$db->quoteName('c.path', 'category_path'),
				])
				->from($db->quoteName('#__content', 'a'))
				->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON ' . $db->quoteName('c.id') . ' = ' . $db->quoteName('a.catid'))
				->where($db->quoteName('a.id') . ' = :articleId')
				->bind(':articleId', $articleId, ParameterType::INTEGER)
				->setLimit(1);
			$db->setQuery($query);
			$articleMeta = $db->loadObject();
			if ($articleMeta) {
				$articleAlias = strtolower((string) ($articleMeta->article_alias ?? ''));
				$categoryAlias = strtolower((string) ($articleMeta->category_alias ?? ''));
				$categoryTitle = (string) ($articleMeta->category_title ?? '');
				$categoryId = (int) ($articleMeta->catid ?? 0);
				$categoryPath = (string) ($articleMeta->category_path ?? '');

				$loadConstitutionAssets = false;

				if (in_array($articleAlias, ['constitution', 'constitution-en', 'constitution-ru'], true)) {
					$loadConstitutionAssets = true;
				}

				if (!$loadConstitutionAssets && in_array($categoryAlias, ['constitution', 'constitution-en', 'constitution-ru'], true)) {
					$loadConstitutionAssets = true;
				}

				if (
					!$loadConstitutionAssets
					&& function_exists('prastoraIsProjectsCategory')
					&& prastoraIsProjectsCategory($categoryId, $categoryAlias, $categoryPath)
				) {
					$loadConstitutionAssets = true;
				}

				if (
					!$loadConstitutionAssets
					&& (
						stripos($categoryTitle, 'Канстытуц') !== false
						|| stripos($categoryTitle, 'Конституц') !== false
						|| stripos($categoryTitle, 'Constitution') !== false
					)
				) {
					$loadConstitutionAssets = true;
				}

				if ($loadConstitutionAssets) {
					$pageKeys[] = 'constitution';
				}
			}
		} catch (\RuntimeException $exception) {
			// Ignore DB lookup failure, assets will fallback to defaults
		}
	}
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
	$options = [];
	$path = $tplPath . '/assets/css/' . $cssFile;
	if (is_file($path)) {
		$options['version'] = (string) filemtime($path);
	}
    $doc->addStyleSheet($tplUri . '/assets/css/' . $cssFile, $options);
}

foreach ($jsToLoad as $jsFile) {
	$options = [];
	$path = $tplPath . '/assets/js/' . $jsFile;
	if (is_file($path)) {
		$options['version'] = (string) filemtime($path);
	}
    $doc->addScript($tplUri . '/assets/js/' . $jsFile, $options);
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
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<base href="<?php echo htmlspecialchars($baseHref, ENT_QUOTES, 'UTF-8'); ?>" />
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
					<a href="/" class="logo"><img src="<?php echo $logoSrc; ?>" alt="" /></a>
					<div class="nav_menu">
						<?php $modsMenu = ModuleHelper::getModules('menu'); if (!empty($modsMenu)) { foreach ($modsMenu as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
							<a href="/constitution"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?></p></a>
							<a href="/projects"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?></p></a>
							<a href="/news"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?></p></a>
							<a href="/about-us"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?></p></a>
							<a href="/faq"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?></p></a>
						<?php } ?>
					</div>
				</div>
				<div class="nav_right">
					<form class="nav_search" action="<?php echo $searchAction; ?>" method="get">
						<input type="hidden" name="option" value="com_finder" />
						<input type="hidden" name="view" value="search" />
						<img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
						<input type="text" name="q" placeholder="<?php echo Text::_('TPL_PRASTORA_SEARCH_PLACEHOLDER'); ?>" />
					</form>
					<a href="https://buy.stripe.com/bJeaEX0vy2HDetk9vH33W00" target="_blank" class="nav_btn"><?php echo Text::_("TPL_PRASTORA_ACTION_DONATE"); ?></a>
					<?php prastoraRenderLanguageSwitcher('desktop'); ?>
				</div>
				<img onclick="toggleMenu()" class="mob_menu_btn" src="<?php echo $tplUri; ?>/assets/icons/mob-menu.svg" alt="" />
			</div>
		</nav>

		<div class="mob_menu">
			<div class="mob_menu_content">
				<div class="mob_menu_header">
					<a href="/" class="logo mob_menu_logo">
						<img src="<?php echo $logoSrc; ?>" alt="" />
					</a>
					<img onclick="toggleMenu()" class="mob_exit_btn" src="<?php echo $tplUri; ?>/assets/icons/exit.svg" alt="" />
				</div>
				<form class="nav_search" action="<?php echo $searchAction; ?>" method="get">
					<input type="hidden" name="option" value="com_finder" />
					<input type="hidden" name="view" value="search" />
					<img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
					<input type="text" name="q" placeholder="<?php echo Text::_('TPL_PRASTORA_SEARCH_PLACEHOLDER'); ?>" />
				</form>
				<div class="nav_menu">
					<?php $modsMenu = ModuleHelper::getModules('menu'); if (!empty($modsMenu)) { foreach ($modsMenu as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
						<a href="/constitution"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?></p></a>
						<a href="/projects"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?></p></a>
						<a href="/news"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?></p></a>
						<a href="/about-us"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?></p></a>
						<a href="/faq"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?></p></a>
					<?php } ?>
				</div>
				<div class="mob_menu_actions">
					<?php prastoraRenderLanguageSwitcher('mobile'); ?>
					<a href="https://buy.stripe.com/bJeaEX0vy2HDetk9vH33W00" target="_blank" class="nav_btn"><?php echo Text::_("TPL_PRASTORA_ACTION_DONATE"); ?></a>
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
							<h4><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACT_PROMPT'); ?></h4>
							<p><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACT_TEXT'); ?></p>
							<a href="mailto:hello@prastora.info"><span>hello@prastora.info</span></a>
						</div>
						<div class="footer_contacts">
							<h4><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACTS_LABEL'); ?></h4>
							<a href="mailto:hello@prastora.info"><img src="<?php echo $tplUri; ?>/assets/icons/mail.svg" alt="" /><p>hello@prastora.info</p></a>
							<a href="https://t.me/konstituciya_bel" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /><p><?php echo Text::_('TPL_PRASTORA_FOOTER_TELEGRAM_LATIN'); ?></p></a>
							<a href="https://www.facebook.com/groups/ConstitutionBelarus" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" /><p><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACT_FACEBOOK'); ?></p></a>
						</div>
					</div>
					<div class="header_socials">
						<a href="https://www.facebook.com/groups/ConstitutionBelarus" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" /></a>
						<a href="https://t.me/konstituciya_bel" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /></a>
						<a href="https://www.youtube.com/@PRA_NAS" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/yt.svg" alt="" /></a>
					</div>
				</div>
				<div class="footer_btm">
					<p><?php echo Text::_('TPL_PRASTORA_FOOTER_COPYRIGHT'); ?></p>
					<a href="/privacy-policy"><p><?php echo Text::_('TPL_PRASTORA_FOOTER_PRIVACY'); ?></p></a>
				</div>
			</div>
		</footer>
<?php endif; ?>
	</body>
</html>
