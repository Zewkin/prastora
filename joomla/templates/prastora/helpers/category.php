<?php
/**
 * Category-related helpers.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Factory;

if (!function_exists('prastoraIsProjectsCategory')) {
	/**
	 * Returns true if the category belongs to the "projects" tree.
	 * The detection is based on ancestor aliases containing "project" (rooted cats),
	 * dynamically discovered root ids, and provided alias/path hints.
	 */
	function prastoraIsProjectsCategory(?int $categoryId, ?string $categoryAlias = '', ?string $categoryPath = ''): bool
	{
		static $cache = [];
		static $rootIds = null;

		$id = (int) $categoryId;
		if ($id > 0 && array_key_exists($id, $cache)) {
			return $cache[$id];
		}

		$isProjectsAlias = static function (string $alias): bool {
			$normalized = strtolower(trim($alias));

			return $normalized !== '' && str_contains($normalized, 'project');
		};

		// Build root ids list once (top-level categories whose alias contains "project")
		if ($rootIds === null) {
			$rootIds = [];

			try {
				$db = Factory::getDbo();
				$query = $db->getQuery(true)
					->select($db->quoteName('id'))
					->from($db->quoteName('#__categories'))
					->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
					->where($db->quoteName('parent_id') . ' = 1')
					->where($db->quoteName('published') . ' >= 0')
					->where($db->quoteName('alias') . ' LIKE ' . $db->quote('%project%'));
				$db->setQuery($query);
				$rootIds = array_map('intval', $db->loadColumn() ?: []);
			} catch (\Throwable $exception) {
				$rootIds = [];
			}
		}

		// Alias hint
		if ($categoryAlias && $isProjectsAlias((string) $categoryAlias)) {
			if ($id > 0) {
				$cache[$id] = true;
			}

			return true;
		}

		// Path hint
		if ($categoryPath) {
			foreach (explode('/', $categoryPath) as $segment) {
				if ($isProjectsAlias((string) $segment)) {
					if ($id > 0) {
						$cache[$id] = true;
					}

					return true;
				}
			}
		}

		// Walk ancestors via Categories API
		try {
			$categoriesTree = Categories::getInstance('Content');
			$node = $categoriesTree ? $categoriesTree->get($id) : null;

			while ($node) {
				$nodeId    = (int) ($node->id ?? 0);
				$nodeAlias = (string) ($node->alias ?? '');

				if (($nodeId > 0 && in_array($nodeId, $rootIds, true)) || $isProjectsAlias($nodeAlias)) {
					if ($id > 0) {
						$cache[$id] = true;
					}

					return true;
				}

				$node = $node->getParent();
			}
		} catch (\Throwable $exception) {
			// Ignore failure, fall through to false
		}

		if ($id > 0) {
			$cache[$id] = false;
		}

		return false;
	}
}
