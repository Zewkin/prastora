<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentHelperRoute;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Helper\TagsHelper;

$app     = Factory::getApplication();
$template = $app->getTemplate();
$tplUri  = rtrim(Uri::root(true), '/') . '/templates/' . $template;

if (!function_exists('prastoraGetIntroFieldText')) {
	function prastoraGetIntroFieldText(?object $item): string
	{
		static $cache = [];
		if (!$item) {
			return '';
		}
		$id = isset($item->id) ? (int) $item->id : 0;
		if ($id > 0 && array_key_exists($id, $cache)) {
			return $cache[$id];
		}
		$value = '';
		try {
			$fields = FieldsHelper::getFields('com_content.article', $item, true);
			foreach ($fields as $field) {
				if ($field->name === 'intro' && isset($field->rawvalue)) {
					$candidate = is_scalar($field->rawvalue) ? (string) $field->rawvalue : '';
					$candidate = trim(strip_tags($candidate));
					if ($candidate !== '') {
						$value = $candidate;
					}
					break;
				}
			}
		} catch (\Throwable $exception) {
			$value = '';
		}
		if ($id > 0) {
			$cache[$id] = $value;
		}
		return $value;
	}
}

$items = $this->items ?? [];
$tagsHelper = new TagsHelper();
$tagsCache = [];
$getItemTags = function (int $itemId) use (&$tagsCache, $tagsHelper) {
    if (!array_key_exists($itemId, $tagsCache)) {
        $tagsCache[$itemId] = $tagsHelper->getItemTags('com_content.article', $itemId) ?: [];
    }

    return $tagsCache[$itemId];
};

// Filters
$input     = $app->input;
$filterCat = $input->getInt('type', 0);
$fromDate  = $input->getString('from', '');
$toDate    = $input->getString('to', '');
$filterTag = trim($input->getString('tag', ''));

// Get subcategories of current category for filter menu (direct SQL to avoid nested set issues)
$langTag = $app->getLanguage()->getTag();
$db = Factory::getDbo();

// Prefer children prepared by the view (already filtered by Joomla)
$subcategories = [];
if (!empty($this->children[(int) $this->category->id])) {
	foreach ($this->children[(int) $this->category->id] as $child) {
		$subcategories[] = (object) [
			'id' => (int) $child->id,
			'title' => (string) $child->title,
			'alias' => (string) $child->alias,
		];
	}
}

// Try to fetch direct children via the Categories API to respect language and access
if (empty($subcategories)) {
	try {
		$categoriesTree = Categories::getInstance('Content', ['access' => true, 'published' => 1, 'language_filter' => true]);
		$currentCategory = $categoriesTree ? $categoriesTree->get((int) $this->category->id) : null;
		if ($currentCategory) {
			$children = $currentCategory->getChildren(true);
			foreach ($children as $child) {
				if ((int) ($child->published ?? 0) !== 1) {
					continue;
				}
				$childLanguage = (string) ($child->language ?? '*');
				if ($childLanguage !== '*' && strcasecmp($childLanguage, $langTag) !== 0) {
					continue;
				}
				$subcategories[] = (object) [
					'id' => (int) $child->id,
					'title' => (string) $child->title,
					'alias' => (string) $child->alias,
				];
			}
		}
	} catch (\Throwable $exception) {
		$subcategories = [];
	}
}

// Fallback to direct SQL if API did not return children (e.g. cache miss)
if (empty($subcategories)) {
	$query = $db->getQuery(true)
		->select('id, title, alias')
		->from($db->quoteName('#__categories'))
		->where('parent_id = ' . (int) $this->category->id)
		->where('published = 1')
		->where('extension = ' . $db->quote('com_content'))
		->where('alias != ' . $db->quote('uncategorised'))
		->where('(language = ' . $db->quote('*') . ' OR language = ' . $db->quote($langTag) . ')')
		->order('lft ASC');
	$db->setQuery($query);
	$subcategories = $db->loadObjectList() ?: [];
}

// Last-resort fallback: ignore language filter so renamed/translated categories still show
if (empty($subcategories)) {
	$query = $db->getQuery(true)
		->select('id, title, alias')
		->from($db->quoteName('#__categories'))
		->where('parent_id = ' . (int) $this->category->id)
		->where('published = 1')
		->where('extension = ' . $db->quote('com_content'))
		->where('alias != ' . $db->quote('uncategorised'))
		->order('lft ASC');
	$db->setQuery($query);
	$subcategories = $db->loadObjectList() ?: [];
}

// Path-based fallback (direct children by nested set path)
if (empty($subcategories) && !empty($this->category->path)) {
	$pathPrefix = rtrim((string) $this->category->path, '/') . '/';
	$targetLevel = isset($this->category->level) ? ((int) $this->category->level + 1) : 0;
	$query = $db->getQuery(true)
		->select('id, title, alias')
		->from($db->quoteName('#__categories'))
		->where($db->quoteName('path') . ' LIKE ' . $db->quote($pathPrefix . '%'))
		->where('published = 1')
		->where('extension = ' . $db->quote('com_content'))
		->where('alias != ' . $db->quote('uncategorised'));
	if ($targetLevel > 0) {
		$query->where($db->quoteName('level') . ' = ' . $targetLevel);
	}
	$db->setQuery($query);
	$subcategories = $db->loadObjectList() ?: [];
}

// Collect all subcategory IDs for "All" filter
$subcatIds = array_map(function($s) { return (int) $s->id; }, $subcategories);

// Apply category filter - load from subcategory if selected
if ($filterCat > 0 && in_array($filterCat, $subcatIds, true)) {
    // Load articles from the selected subcategory
    $query = $db->getQuery(true)
        ->select('a.*, c.title AS category_title, c.alias AS category_alias')
        ->from($db->quoteName('#__content', 'a'))
        ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
        ->where('a.catid = ' . (int) $filterCat)
        ->where('a.state = 1')
        ->where('a.access = 1')
        ->order('a.publish_up DESC, a.created DESC');
    $db->setQuery($query);
    $items = $db->loadObjectList() ?: [];
    // Create slug field for routing
    foreach ($items as $item) {
        if (!isset($item->slug) && isset($item->id) && isset($item->alias)) {
            $item->slug = $item->id . ':' . $item->alias;
        }
    }
} elseif ($filterCat > 0) {
    // Filter within current category items
    $items = array_values(array_filter($items, function ($it) use ($filterCat) {
        return (int) $it->catid === (int) $filterCat;
    }));
} else {
    // "All" - show articles from current category AND all subcategories
    if (!empty($subcatIds)) {
        $allCategoryIds = array_merge([(int) $this->category->id], $subcatIds);
        $query = $db->getQuery(true)
            ->select('a.*, c.title AS category_title, c.alias AS category_alias')
            ->from($db->quoteName('#__content', 'a'))
            ->join('LEFT', $db->quoteName('#__categories', 'c') . ' ON c.id = a.catid')
            ->where('a.catid IN (' . implode(',', $allCategoryIds) . ')')
            ->where('a.state = 1')
            ->where('a.access = 1')
            ->order('a.publish_up DESC, a.created DESC');
        $db->setQuery($query);
        $items = $db->loadObjectList() ?: [];
        // Create slug field for routing
        foreach ($items as $item) {
            if (!isset($item->slug) && isset($item->id) && isset($item->alias)) {
                $item->slug = $item->id . ':' . $item->alias;
            }
        }
    }
    // If no subcategories, $items already contains current category articles from Joomla
}

// Apply date filter
if ($fromDate || $toDate) {
    $fromTs = $fromDate ? strtotime($fromDate) : null;
    $toTs   = $toDate ? strtotime($toDate . ' 23:59:59') : null;
    $items = array_values(array_filter($items, function ($it) use ($fromTs, $toTs) {
        $t = strtotime($it->publish_up ?: $it->created);
        if ($fromTs && $t < $fromTs) return false;
        if ($toTs && $t > $toTs) return false;
        return true;
    }));
}

// Apply tag filter (by title or alias)
if ($filterTag !== '') {
    $normalizedTag = mb_strtolower($filterTag);
    $items = array_values(array_filter($items, function ($it) use ($getItemTags, $normalizedTag, $filterTag) {
        $tags = $getItemTags((int) $it->id);
        foreach ($tags as $tag) {
            if (mb_strtolower((string) $tag->title) === $normalizedTag || (string) $tag->alias === $filterTag) {
                return true;
            }
        }

        return false;
    }));
}

$pagination = $this->pagination ?? null;
$limit      = 9;
$totalItems = count($items);
$limitStart = max(0, $input->getInt('start', 0));

if ($limitStart >= $totalItems && $totalItems > 0) {
	$limitStart = max(0, (int) (floor(($totalItems - 1) / $limit) * $limit));
}

if ($totalItems > 0) {
    $pagesTotal = (int) ceil($totalItems / $limit);
    $current    = (int) floor($limitStart / $limit) + 1;
    if ($current < 1) {
        $current = 1;
    } elseif ($current > $pagesTotal) {
        $current = $pagesTotal;
    }
} else {
    $pagesTotal = 1;
    $current    = 1;
}

$items = array_slice($items, $limitStart, $limit);

if (is_object($pagination)) {
    $pagination->limit        = $limit;
    $pagination->pagesTotal   = $pagesTotal;
    $pagination->pagesCurrent = $current;
    $pagination->total        = $totalItems;
    $pagination->limitstart   = $limitStart;
}
?>
<div class="projects_page container">
	<div class="projects_wrapper project_main">
		<div class="projects_nav">
			<div class="projects_nav_menu">
				<?php
					$base = Uri::getInstance();
					$baseQuery = $base->getQuery(true);
					unset($baseQuery['start'], $baseQuery['type']);
					$basePath = $base->getPath();
					$sep = empty($baseQuery) ? '?' : ('?' . http_build_query($baseQuery) . '&');
					$allActive = $filterCat === 0 ? ' projects_nav_menu_item_active' : '';
					
					// Determine if this is the projects category
					$categoryAlias = strtolower((string) ($this->category->alias ?? ''));
					$isProjectsCategory = in_array($categoryAlias, ['projects', 'projects-ru', 'projects-en', 'projects-by'], true);
					$allFilterLabel = $isProjectsCategory ? Text::_('TPL_PRASTORA_PROJECTS_FILTER_DRAFT_LAWS') : Text::_('TPL_PRASTORA_PROJECTS_FILTER_ALL');
					
					
					echo '<div class="projects_nav_menu_item' . $allActive . '"><a href="' . Route::_($basePath . $sep . 'type=0') . '"><p>' . htmlspecialchars($allFilterLabel, ENT_QUOTES, 'UTF-8') . '</p></a></div>';
					if (empty($subcategories)) {
						$active = ($filterCat === (int) $this->category->id) ? ' projects_nav_menu_item_active' : '';
						echo '<div class="projects_nav_menu_item' . $active . '"><a href="' . Route::_($basePath . $sep . 'type=' . (int) $this->category->id) . '"><p>' . htmlspecialchars($this->category->title, ENT_QUOTES, 'UTF-8') . '</p></a></div>';
					} else {
						foreach ($subcategories as $sib) {
							$active = ($filterCat === (int) $sib->id) ? ' projects_nav_menu_item_active' : '';
							echo '<div class="projects_nav_menu_item' . $active . '"><a href="' . Route::_($basePath . $sep . 'type=' . (int) $sib->id) . '"><p>' . htmlspecialchars($sib->title, ENT_QUOTES, 'UTF-8') . '</p></a></div>';
						}
					}
				?>
			</div>
			<!-- <div class="projects_nav_action"><img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-1.svg" alt="" /></div> -->
			<div class="projects_nav_action_group">
				<div
					class="projects_nav_action projects_nav_action_filter"
					data-date-filter-toggle
					role="button"
					tabindex="0"
					aria-haspopup="dialog"
					aria-expanded="false"
					aria-controls="projects-date-filter"
				>
					<img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-2.svg" alt="" />
				</div>
				<div
					class="projects_datepicker"
					id="projects-date-filter"
					data-date-filter
					data-initial-from="<?php echo htmlspecialchars($fromDate, ENT_QUOTES, 'UTF-8'); ?>"
					data-initial-to="<?php echo htmlspecialchars($toDate, ENT_QUOTES, 'UTF-8'); ?>"
					hidden
				>
					<div class="projects_datepicker_inner">
						<div class="projects_datepicker_header">
							<button type="button" class="projects_datepicker_nav" data-date-filter-prev aria-label="<?php echo Text::_('TPL_PRASTORA_PROJECTS_DATEPREV'); ?>">
								<span aria-hidden="true">‹</span>
							</button>
							<p class="projects_datepicker_month" data-date-filter-label></p>
							<button type="button" class="projects_datepicker_nav" data-date-filter-next aria-label="<?php echo Text::_('TPL_PRASTORA_PROJECTS_DATENEXT'); ?>">
								<span aria-hidden="true">›</span>
							</button>
						</div>
						<div class="projects_datepicker_weekdays">
							<span>Su</span>
							<span>Mo</span>
							<span>Tu</span>
							<span>We</span>
							<span>Th</span>
							<span>Fr</span>
							<span>Sa</span>
						</div>
						<div class="projects_datepicker_grid" data-date-filter-grid></div>
						<div class="projects_datepicker_footer">
							<div class="projects_datepicker_selection is-empty" data-date-filter-selection data-empty-text="<?php echo htmlspecialchars(Text::_('TPL_PRASTORA_PROJECTS_DATE_EMPTY'), ENT_QUOTES, 'UTF-8'); ?>">
								<span data-date-filter-from><?php echo Text::_('TPL_PRASTORA_PROJECTS_DATE_EMPTY'); ?></span>
								<span data-date-filter-separator></span>
								<span data-date-filter-to></span>
							</div>
							<div class="projects_datepicker_actions">
								<button type="button" class="projects_datepicker_reset" data-date-filter-reset><?php echo Text::_('TPL_PRASTORA_PROJECTS_DATE_RESET'); ?></button>
								<button type="button" class="projects_datepicker_apply" data-date-filter-apply><?php echo Text::_('TPL_PRASTORA_PROJECTS_DATE_APPLY'); ?></button>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<div class="projects_cards">
			<?php foreach ($items as $item) :
				$images = json_decode($item->images ?? '{}');
				$introImage = $images->image_intro ?? '';
				$imageAlt = trim((string) ($images->image_intro_alt ?? ''));
				$imageSrc = '';
				if (!empty($introImage)) {
					$cleanImage = HTMLHelper::cleanImageURL($introImage);
					if (!empty($cleanImage->url)) {
						$introImage = $cleanImage->url;
					}
					if (strpos($introImage, 'http://') === 0 || strpos($introImage, 'https://') === 0 || strpos($introImage, '//') === 0 || strpos($introImage, '/') === 0) {
						$imageSrc = $introImage;
					} else {
						$imageSrc = rtrim(Uri::root(true), '/') . '/' . ltrim($introImage, '/');
					}
				}
				if ($imageSrc === '') {
					$imageSrc = $tplUri . '/assets/images/project-img.png';
				}
				if ($imageAlt === '') {
					$imageAlt = (string) $item->title;
				}
				$link = Route::_(ContentHelperRoute::getArticleRoute($item->slug, (int) $item->catid, (string) $item->language));
				$tags = $getItemTags((int) $item->id);
				$tagLabel = '';
				if (!empty($tags)) {
					$firstTag = reset($tags);
					$firstTagTitle = trim((string) ($firstTag->title ?? ''));
					$tagLabel = $firstTagTitle !== '' ? $firstTagTitle : trim((string) ($firstTag->alias ?? ''));
				} elseif (!empty($item->category_title)) {
					$tagLabel = trim((string) $item->category_title);
				} elseif (!empty($this->category->title)) {
					$tagLabel = trim((string) $this->category->title);
				}
				$publishDate = '';
				if (!empty($item->publish_up) || !empty($item->created)) {
					$publishDate = JHtml::_('date', $item->publish_up ?: $item->created, Text::_('DATE_FORMAT_LC3'));
				}
			$customIntro = prastoraGetIntroFieldText($item);
			$rawIntro = $customIntro !== '' ? $customIntro : trim(strip_tags($item->introtext));
			$summary = $rawIntro !== '' ? HTMLHelper::_('string.truncate', $rawIntro, 180, true, false) : '';
				$readMoreLabel = Text::sprintf('COM_CONTENT_READ_MORE_TITLE', str_replace('%', '%%', (string) $item->title));
			?>
			<div class="project_card">
				<div class="project_card_top">
					<a href="<?php echo $link; ?>"><img src="<?php echo htmlspecialchars($imageSrc, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($imageAlt, ENT_QUOTES, 'UTF-8'); ?>" /></a>
					<div class="project_card_desc">
						<?php if ($tagLabel !== '' || $publishDate !== '') : ?>
						<div class="project_card_desc_items">
							<?php if ($tagLabel !== '') : ?>
							<div class="project_hashtag"><p><?php echo htmlspecialchars($tagLabel, ENT_QUOTES, 'UTF-8'); ?></p></div>
							<?php endif; ?>
							<?php if ($tagLabel !== '' && $publishDate !== '') : ?>
							<div class="dot"></div>
							<?php endif; ?>
							<?php if ($publishDate !== '') : ?>
							<span><?php echo htmlspecialchars($publishDate, ENT_QUOTES, 'UTF-8'); ?></span>
							<?php endif; ?>
						</div>
						<?php endif; ?>
						<div class="project_card_desc_text">
							<p><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?></a></p>
							<?php if ($summary !== '') : ?>
							<span><?php echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></span>
							<?php endif; ?>
						</div>
					</div>
				</div>
				<div class="project_card_btm"><img src="<?php echo $tplUri; ?>/assets/images/Union.png" alt="" /></div>
				<a href="<?php echo $link; ?>" class="card_link" title="<?php echo htmlspecialchars($readMoreLabel, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars($readMoreLabel, ENT_QUOTES, 'UTF-8'); ?>"><img src="<?php echo $tplUri; ?>/assets/icons/link-f.svg" alt="" /></a>
			</div>
			<?php endforeach; ?>
		</div>
		<?php if (empty($items)) : ?>
			<p class="no-projects"><?php echo Text::_('COM_CONTENT_NO_ARTICLES'); ?></p>
		<?php endif; ?>
	</div>

	<div class="cards_pagination">
		<?php
			$uri = Uri::getInstance();
			$query = $uri->getQuery(true);
			unset($query['start']);
			$base = $uri->getPath();
			if (!empty($query)) {
				$base .= '?' . http_build_query($query);
			}
			$sep = (strpos($base, '?') === false) ? '?' : '&';

			for ($i = 1; $i <= $pagesTotal; $i++) {
				$start = ($i - 1) * $limit;
				$link  = Route::_($base . $sep . 'start=' . $start);
				$active = ($i === $current) ? ' style="font-weight:700;"' : '';
				echo '<a href="' . $link . '"' . $active . '>' . $i . '</a>' . "\n";
			}
			if ($current < $pagesTotal) {
				$nextStart = $current * $limit;
				$nextLink  = Route::_($base . $sep . 'start=' . $nextStart);
				echo '<a href="' . $nextLink . '" class="next">' . Text::_('TPL_PRASTORA_PAGINATION_NEXT') . '</a>';
			}
		?>
	</div>
</div>
