<?php
/**
 * Helper script to align multilingual content structure.
 */

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;
use Joomla\Registry\Registry;

define('_JEXEC', 1);

$rootPath = dirname(__DIR__);

if (file_exists($rootPath . '/defines.php')) {
	require_once $rootPath . '/defines.php';
}

if (!defined('_JDEFINES')) {
	define('JPATH_BASE', $rootPath);
	require_once JPATH_BASE . '/includes/defines.php';
}

require_once dirname(__DIR__) . '/includes/framework.php';

const LANG_BE = 'be-BY';
const LANG_RU = 'ru-RU';
const LANG_EN = 'en-GB';

/**
 * Helper to run a simple update query.
 */
function updateLanguage(DatabaseDriver $db, string $table, string $field, array $ids, string $language): void
{
	if (empty($ids)) {
		return;
	}

	$query = $db->getQuery(true)
		->update($db->quoteName($table))
		->set($db->quoteName('language') . ' = ' . $db->quote($language))
		->where($db->quoteName($field) . ' IN (' . implode(',', array_map('intval', $ids)) . ')');

	$db->setQuery($query);
	$db->execute();
}

/**
 * Helper to update language by matching against a set of category ids.
 */
function updateContentLanguage(DatabaseDriver $db, array $catIds, string $language): void
{
	if (empty($catIds)) {
		return;
	}

	$query = $db->getQuery(true)
		->update($db->quoteName('#__content'))
		->set($db->quoteName('language') . ' = ' . $db->quote($language))
		->where($db->quoteName('catid') . ' IN (' . implode(',', array_map('intval', $catIds)) . ')');

	$db->setQuery($query);
	$db->execute();
}

/**
 * Ensure a menu item exists (or update an existing one) using Joomla API.
 */
function ensureMenuItem(array $data, DatabaseDriver $db): int
{
	Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/tables');

	/** @var \Joomla\CMS\Table\Menu $table */
	$table = Table::getInstance('Menu', 'JTable', []);

	$existingId = (int) $db->setQuery(
		$db->getQuery(true)
			->select($db->quoteName('id'))
			->from($db->quoteName('#__menu'))
			->where($db->quoteName('alias') . ' = ' . $db->quote($data['alias']))
			->where($db->quoteName('language') . ' = ' . $db->quote($data['language']))
			->where($db->quoteName('menutype') . ' = ' . $db->quote($data['menutype']))
	)->loadResult();

	if ($existingId) {
		$table->load($existingId);

		foreach ($data as $key => $value) {
			$table->$key = $value;
		}
	} else {
		$table->setLocation((int) ($data['parent_id'] ?? 1), 'last-child');

		if (!$table->bind($data)) {
			throw new RuntimeException('Unable to bind menu data: ' . $table->getError());
		}
	}

	if (!$table->check()) {
		throw new RuntimeException('Menu check failed: ' . $table->getError());
	}

	if (!$table->store()) {
		throw new RuntimeException('Menu store failed: ' . $table->getError());
	}

	$table->rebuildPath($table->id);

	return (int) $table->id;
}

/**
 * Update module settings.
 */
function updateModule(DatabaseDriver $db, int $moduleId, callable $updater): void
{
	Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_modules/tables');

	/** @var \Joomla\CMS\Table\Module $module */
	$module = Table::getInstance('Module', 'JTable', []);

	if (!$module->load($moduleId)) {
		throw new RuntimeException("Unable to load module #{$moduleId}");
	}

	$updater($module);

	if (!$module->check()) {
		throw new RuntimeException('Module check failed: ' . $module->getError());
	}

	if (!$module->store()) {
		throw new RuntimeException('Module store failed: ' . $module->getError());
	}
}

$container = Factory::getContainer();
$container->alias('session', 'session.cli')
	->alias('JSession', 'session.cli')
	->alias(\Joomla\CMS\Session\Session::class, 'session.cli')
	->alias(\Joomla\Session\Session::class, 'session.cli')
	->alias(\Joomla\Session\SessionInterface::class, 'session.cli');

if (!isset($_SERVER['HTTP_HOST'])) {
	$_SERVER['HTTP_HOST'] = 'localhost';
}

if (!isset($_SERVER['REQUEST_SCHEME'])) {
	$_SERVER['REQUEST_SCHEME'] = 'http';
}
$app       = $container->get(AdministratorApplication::class);
Factory::$application = $app;
$db = $container->get(DatabaseDriver::class);

$db->transactionStart();

try {
	updateLanguage($db, '#__categories', 'id', [8, 10, 11, 12], LANG_BE);
	updateLanguage($db, '#__categories', 'id', [13, 14, 15, 16, 21], LANG_RU);
	updateLanguage($db, '#__categories', 'id', [17, 18, 19, 20], LANG_EN);

	updateContentLanguage($db, [8, 10, 11, 12], LANG_BE);
	updateContentLanguage($db, [13, 14, 15, 16, 21], LANG_RU);
	updateContentLanguage($db, [17, 18, 19, 20], LANG_EN);

	Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_menus/tables');

	$menuUpdates = [
		101 => [LANG_BE, 1],
		102 => [LANG_BE, 0],
		103 => [LANG_BE, 0],
		104 => [LANG_BE, 0],
		105 => [LANG_BE, 0],
		106 => [LANG_BE, 0],
		107 => [LANG_BE, 0],
		108 => [LANG_BE, 0],
		110 => [LANG_RU, 1],
		111 => [LANG_RU, 0],
		112 => [LANG_RU, 0],
		113 => [LANG_RU, 0],
		114 => [LANG_RU, 0],
	];

	foreach ($menuUpdates as $menuId => [$language, $isHome]) {
		/** @var \Joomla\CMS\Table\Menu $menu */
		$menu = Table::getInstance('Menu', 'JTable', []);

		if (!$menu->load($menuId)) {
			continue;
		}

		$menu->language = $language;
		$menu->menutype = 'mainmenu';
		$menu->home     = (int) $isHome;
		$menu->check();
		$menu->store();
		$menu->rebuildPath($menu->id);
	}

	$componentId = (int) $db->setQuery(
		$db->getQuery(true)
			->select($db->quoteName('extension_id'))
			->from($db->quoteName('#__extensions'))
			->where($db->quoteName('element') . ' = ' . $db->quote('com_content'))
			->where($db->quoteName('client_id') . ' = 1')
	)->loadResult();

	if (!$componentId) {
		throw new RuntimeException('Unable to detect com_content component id');
	}

	$db->setQuery(
		$db->getQuery(true)
			->update($db->quoteName('#__menu'))
			->set($db->quoteName('home') . ' = 0')
			->where($db->quoteName('language') . ' = ' . $db->quote(LANG_EN))
	);
	$db->execute();

	$menuParamsArticle = '{"show_title":"","link_titles":"","show_intro":"","info_block_position":"","info_block_show_title":"","show_category":"","link_category":"","show_parent_category":"","link_parent_category":"","show_author":"","link_author":"","show_create_date":"","show_modify_date":"","show_publish_date":"","show_item_navigation":"","show_hits":"","show_tags":"","show_noauth":"","urls_position":"","menu-anchor_title":"","menu-anchor_css":"","menu_icon_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1,"page_title":"","show_page_heading":"","page_heading":"","pageclass_sfx":"","menu-meta_description":"","robots":""}';

	$menuDefinitions = [
		[
			'title'        => 'Home',
			'note'         => 'EN',
			'alias'        => 'home-en',
			'menutype'     => 'mainmenu',
			'link'         => 'index.php?option=com_content&view=featured',
			'type'         => 'component',
			'published'    => 1,
			'parent_id'    => 1,
			'access'       => 1,
			'language'     => LANG_EN,
			'home'         => 1,
			'component_id' => $componentId,
			'params'       => $menuParamsArticle,
			'template_style_id' => 0,
			'client_id'    => 0,
			'browserNav'   => 0,
			'img'          => '',
			'metakey'      => '',
			'metadesc'     => '',
			'metadata'     => '{}',
		],
		[
			'title'        => 'Constitution',
			'note'         => 'EN',
			'alias'        => 'constitution-en',
			'menutype'     => 'mainmenu',
			'link'         => 'index.php?option=com_content&view=article&id=44',
			'type'         => 'component',
			'published'    => 1,
			'parent_id'    => 1,
			'access'       => 1,
			'language'     => LANG_EN,
			'home'         => 0,
			'component_id' => $componentId,
			'params'       => $menuParamsArticle,
			'template_style_id' => 0,
			'client_id'    => 0,
			'browserNav'   => 0,
			'img'          => '',
			'metakey'      => '',
			'metadesc'     => '',
			'metadata'     => '{}',
		],
		[
			'title'        => 'Projects',
			'note'         => 'EN',
			'alias'        => 'projects-en',
			'menutype'     => 'mainmenu',
			'link'         => 'index.php?option=com_content&view=category&id=19',
			'type'         => 'component',
			'published'    => 1,
			'parent_id'    => 1,
			'access'       => 1,
			'language'     => LANG_EN,
			'home'         => 0,
			'component_id' => $componentId,
			'params'       => '{"num_leading_articles":"0","num_intro_articles":"0","num_links":"0","show_subcategory_content":"0","show_empty_categories":"0","show_category_header":"0","page_title":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","page_heading":"","show_page_heading":"","pageclass_sfx":"","menu-anchor_title":"","menu-anchor_css":"","menu_icon_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}',
			'template_style_id' => 0,
			'client_id'    => 0,
			'browserNav'   => 0,
			'img'          => '',
			'metakey'      => '',
			'metadesc'     => '',
			'metadata'     => '{}',
		],
		[
			'title'        => 'Documents',
			'note'         => 'EN',
			'alias'        => 'documents-en',
			'menutype'     => 'mainmenu',
			'link'         => 'index.php?option=com_content&view=category&id=20',
			'type'         => 'component',
			'published'    => 1,
			'parent_id'    => 1,
			'access'       => 1,
			'language'     => LANG_EN,
			'home'         => 0,
			'component_id' => $componentId,
			'params'       => '{"num_leading_articles":"0","num_intro_articles":"0","num_links":"0","show_subcategory_content":"0","show_empty_categories":"0","show_category_header":"0","page_title":"","menu-meta_description":"","menu-meta_keywords":"","robots":"","page_heading":"","show_page_heading":"","pageclass_sfx":"","menu-anchor_title":"","menu-anchor_css":"","menu_icon_css":"","menu_image":"","menu_image_css":"","menu_text":1,"menu_show":1}',
			'template_style_id' => 0,
			'client_id'    => 0,
			'browserNav'   => 0,
			'img'          => '',
			'metakey'      => '',
			'metadesc'     => '',
			'metadata'     => '{}',
		],
	];

	foreach ($menuDefinitions as $definition) {
		ensureMenuItem($definition, $db);
	}

	$menuTable = Table::getInstance('Menu', 'JTable', []);
	$menuTable->rebuild(1);

	updateModule($db, 1, function ($module) {
		$params = new Registry($module->params);
		$params->set('menutype', 'mainmenu');
		$params->set('startLevel', '0');
		$params->set('endLevel', '0');
		$params->set('showAllChildren', '1');

		$module->title     = 'Main Menu BE';
		$module->position  = 'menu';
		$module->language  = LANG_BE;
		$module->published = 1;
		$module->showtitle = 0;
		$module->params    = $params->toString();
		$module->client_id = 0;
	});

	updateModule($db, 110, function ($module) {
		$params = new Registry($module->params);
		$params->set('menutype', 'mainmenu');
		$params->set('startLevel', '0');
		$params->set('endLevel', '0');
		$params->set('showAllChildren', '1');
		$params->set('base', '');

		$module->title     = 'Main Menu RU';
		$module->position  = 'menu';
		$module->language  = LANG_RU;
		$module->published = 1;
		$module->showtitle = 0;
		$module->params    = $params->toString();
		$module->client_id = 0;
	});

	Table::addIncludePath(JPATH_ADMINISTRATOR . '/components/com_modules/tables');
	/** @var \Joomla\CMS\Table\Module $enModule */
	$enModule = Table::getInstance('Module', 'JTable', []);

	$moduleParams = new Registry();
	$moduleParams->set('menutype', 'mainmenu');
	$moduleParams->set('startLevel', '0');
	$moduleParams->set('endLevel', '0');
	$moduleParams->set('showAllChildren', '1');

	$ordering = (int) $db->setQuery(
		$db->getQuery(true)
			->select('COALESCE(MAX(' . $db->quoteName('ordering') . '), 0) + 1')
			->from($db->quoteName('#__modules'))
			->where($db->quoteName('position') . ' = ' . $db->quote('menu'))
	)->loadResult();

	$enModuleData = [
		'title'      => 'Main Menu EN',
		'module'     => 'mod_menu',
		'position'   => 'menu',
		'language'   => LANG_EN,
		'published'  => 1,
		'showtitle'  => 0,
		'access'     => 1,
		'ordering'   => $ordering ?: 1,
		'client_id'  => 0,
		'params'     => $moduleParams->toString(),
	];

	if (!$enModule->bind($enModuleData)) {
		throw new RuntimeException('Unable to bind EN module: ' . $enModule->getError());
	}

	if (!$enModule->check()) {
		throw new RuntimeException('EN module check failed: ' . $enModule->getError());
	}

	if (!$enModule->store()) {
		throw new RuntimeException('EN module store failed: ' . $enModule->getError());
	}

	$db->setQuery(
		$db->getQuery(true)
			->insert($db->quoteName('#__modules_menu'))
			->columns([$db->quoteName('moduleid'), $db->quoteName('menuid')])
			->values((int) $enModule->id . ', 0')
	);
	$db->execute();

	$orderQuery = $db->getQuery(true)
		->select($db->quoteName('id'))
		->from($db->quoteName('#__modules'))
		->where($db->quoteName('position') . ' = ' . $db->quote('menu'))
		->order($db->quoteName('id'));
	$moduleIds = $db->setQuery($orderQuery)->loadColumn();

	foreach ($moduleIds as $index => $moduleId) {
		$db->setQuery(
			$db->getQuery(true)
				->update($db->quoteName('#__modules'))
				->set($db->quoteName('ordering') . ' = ' . ($index + 1))
				->where($db->quoteName('id') . ' = ' . (int) $moduleId)
		)->execute();
	}

	$params = new Registry();
	$params->set('detect_browser', 0);
	$params->set('automatic_change', 1);
	$params->set('item_associations', 1);
	$params->set('alternate_meta', 1);
	$params->set('xdefault', 1);
	$params->set('xdefault_language', LANG_BE);
	$params->set('remove_default_prefix', 0);
	$params->set('lang_cookie', 0);

	$db->setQuery(
		$db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('enabled') . ' = 1')
			->set($db->quoteName('params') . ' = ' . $db->quote($params->toString()))
			->where($db->quoteName('element') . ' = ' . $db->quote('languagefilter'))
			->where($db->quoteName('folder') . ' = ' . $db->quote('system'))
	)->execute();

	$languageParams = new Registry();
	$languageParams->set('administrator', 'en-GB');
	$languageParams->set('site', LANG_BE);

	$db->setQuery(
		$db->getQuery(true)
			->update($db->quoteName('#__extensions'))
			->set($db->quoteName('params') . ' = ' . $db->quote($languageParams->toString()))
			->where($db->quoteName('element') . ' = ' . $db->quote('com_languages'))
	)->execute();

	$db->transactionCommit();
	echo "Multilingual setup completed\n";
} catch (Throwable $e) {
	$db->transactionRollback();
	fwrite(STDERR, 'Error: ' . $e->getMessage() . PHP_EOL);
	exit(1);
}

