<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentHelperRoute;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Helper\TagsHelper;

$app     = Factory::getApplication();
$template = $app->getTemplate();
$tplUri  = rtrim(Uri::root(true), '/') . '/templates/' . $template;

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

// Child categories of current
$catsApi = Categories::getInstance('Content');
$parent  = $catsApi->get($this->category->parent_id ?: 1);
$siblings = $parent ? $parent->getChildren() : [];
// filter out Uncategorised and hidden
$siblings = array_values(array_filter($siblings, function($sib){
    $alias = property_exists($sib, 'alias') ? $sib->alias : '';
    $published = property_exists($sib, 'published') ? (int)$sib->published : 1;
    return $published === 1 && $alias !== 'uncategorised';
}));

// Apply category filter
if ($filterCat > 0) {
    $items = array_values(array_filter($items, function ($it) use ($filterCat) {
        return (int) $it->catid === (int) $filterCat;
    }));
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
$totalItems = (is_object($pagination) && isset($pagination->total)) ? (int) $pagination->total : count($items);
$limitStart = (is_object($pagination) && isset($pagination->limitstart)) ? (int) $pagination->limitstart : max(0, $input->getInt('start', 0));

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

$items = array_slice($items, 0, $limit);

if (is_object($pagination)) {
    $pagination->limit       = $limit;
    $pagination->pagesTotal  = $pagesTotal;
    $pagination->pagesCurrent = $current;
}
?>
<div class="projects_page container">
	<div class="projects_wrapper project_main">
		<div class="projects_nav">
			<div class="projects_nav_menu">
				<?php
					$base = Uri::getInstance();
					$baseQuery = $base->getQuery(true);
					unset($baseQuery['start']);
					$basePath = $base->getPath();
					$sep = empty($baseQuery) ? '?' : ('?' . http_build_query($baseQuery) . '&');
					$allActive = $filterCat === 0 ? ' projects_nav_menu_item_active' : '';
					echo '<div class="projects_nav_menu_item' . $allActive . '"><a href="' . Route::_($basePath . $sep . 'type=0') . '"><p>Усё</p></a></div>';
					if (empty($siblings)) {
						$active = ($filterCat === (int) $this->category->id) ? ' projects_nav_menu_item_active' : '';
						echo '<div class="projects_nav_menu_item' . $active . '"><a href="' . Route::_($basePath . $sep . 'type=' . (int) $this->category->id) . '"><p>' . htmlspecialchars($this->category->title, ENT_QUOTES, 'UTF-8') . '</p></a></div>';
					} else {
						foreach ($siblings as $sib) {
							$active = ($filterCat === (int) $sib->id) ? ' projects_nav_menu_item_active' : '';
							echo '<div class="projects_nav_menu_item' . $active . '"><a href="' . Route::_($basePath . $sep . 'type=' . (int) $sib->id) . '"><p>' . htmlspecialchars($sib->title, ENT_QUOTES, 'UTF-8') . '</p></a></div>';
						}
					}
				?>
			</div>
			<div class="projects_nav_action"><img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-1.svg" alt="" /></div>
			<div class="projects_nav_action"><img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-2.svg" alt="" /></div>
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
				$rawIntro = trim(strip_tags($item->introtext));
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
				echo '<a href="' . $nextLink . '" class="next">' . Text::_('Далей') . '</a>';
			}
		?>
	</div>
</div>
