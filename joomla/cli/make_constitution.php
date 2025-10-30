<?php
// CLI script to create Constitution article and a menu item /constitution
define('_JEXEC', 1);
define('JPATH_ROOT', __DIR__ . '/..');

require JPATH_ROOT . '/includes/app.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Table\Table;

try {
    $app = Factory::getApplication('site');
    $db  = Factory::getContainer()->get('DatabaseDriver');

    // 1) Ensure article exists (by alias)
    $alias = 'constitution';
    $db->setQuery($db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__content'))
        ->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
        ->setLimit(1)
    );
    $articleId = (int) $db->loadResult();

    if (!$articleId) {
        /** @var Joomla\Component\Content\Administrator\Table\ArticleTable $table */
        $table = Table::getInstance('Article', 'Joomla\\Component\\Content\\Administrator\\Table\\');
        $data = [
            'title' => 'Канстытуцыя Новай Беларусі',
            'alias' => $alias,
            'introtext' => '',
            'fulltext' => '<h4>Артыкул 1. Рэспубліка Беларусь</h4><p>1. Рэспубліка Беларусь — унітарная дэмакратычная сацыяльная прававая дзяржава.</p>',
            'state' => 1,
            'catid' => 2, // Uncategorised
            'language' => '*',
            'access' => 1,
        ];
        if (!$table->save($data)) {
            throw new RuntimeException('Failed to save article: ' . $table->getError());
        }
        $articleId = (int) $table->id;
    }

    // 2) Ensure menu item exists at /constitution
    $menutype = 'mainmenu';
    $db->setQuery($db->getQuery(true)
        ->select('id')
        ->from($db->quoteName('#__menu'))
        ->where($db->quoteName('menutype') . ' = ' . $db->quote($menutype))
        ->where($db->quoteName('alias') . ' = ' . $db->quote('constitution'))
        ->setLimit(1)
    );
    $menuId = (int) $db->loadResult();

    if (!$menuId) {
        /** @var Joomla\CMS\Table\Menu $menu */
        $menu = Table::getInstance('Menu', 'Joomla\\CMS\\Table\\');
        $link = 'index.php?option=com_content&view=article&id=' . $articleId;
        $data = [
            'menutype' => $menutype,
            'title' => 'Канстытуцыя',
            'alias' => 'constitution',
            'path' => 'constitution',
            'link' => $link,
            'type' => 'component',
            'published' => 1,
            'parent_id' => 1, // root
            'component_id' => (int) $db->setQuery('SELECT extension_id FROM #__extensions WHERE element="com_content" AND type="component"')->loadResult(),
            'access' => 1,
            'language' => '*',
            'client_id' => 0,
        ];
        if (!$menu->setLocation((int) $data['parent_id'], 'last-child')) {
            throw new RuntimeException('Failed to set menu location');
        }
        if (!$menu->bind($data) || !$menu->check() || !$menu->store()) {
            throw new RuntimeException('Failed to create menu: ' . $menu->getError());
        }
        $menu->rebuildPath((int) $menu->id);
    }

    echo "OK\n";
} catch (Throwable $e) {
    fwrite(STDERR, 'Error: ' . $e->getMessage() . "\n");
    exit(1);
}




