<?php
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

defined('_JEXEC') or die;

require_once __DIR__ . '/helpers/language.php';

$app      = Factory::getApplication();
$app->getLanguage()->load('tpl_prastora', JPATH_SITE, null, true, true);
prastoraRegisterScriptTranslations();
$template = $app->getTemplate();
$tplUri   = rtrim(Uri::root(true), '/') . '/templates/' . $template;
$langTag = strtolower((string) $app->getLanguage()->getTag());
$logoSrc = $tplUri . '/assets/' . ((strpos($langTag, 'en') === 0) ? 'logo-en.svg' : 'logo.svg');
$menu     = $app->getMenu();
$langTag = strtolower((string) $app->getLanguage()->getTag());
$newsPath = (strpos($langTag, 'ru') === 0) ? '/news-ru' : '/news';
$aboutPath = '/about-us';
$faqPath = '/faq';

$searchMenuItem = $menu ? $menu->getItems('link', 'index.php?option=com_finder&view=search', true) : null;
$searchAction = $searchMenuItem
    ? Route::_('index.php?option=com_finder&view=search&Itemid=' . (int) $searchMenuItem->id)
    : (rtrim(Uri::base(true), '/') . '/index.php?option=com_finder&view=search');
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>">
	<head>
		<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no" />
		<jdoc:include type="metas" />
		<jdoc:include type="styles" />
		<jdoc:include type="scripts" />
	</head>
	<body>
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
					<?php prastoraRenderLanguageSwitcher('component-desktop'); ?>
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
					<?php prastoraRenderLanguageSwitcher('component-mobile'); ?>
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
							<a href="https://t.me/konstituciya_bel" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /><p><?php echo Text::_('TPL_PRASTORA_FOOTER_TELEGRAM_CYRILLIC'); ?></p></a>
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

		<script src="<?php echo $tplUri; ?>/assets/js/main.js"></script>
	</body>
</html>
