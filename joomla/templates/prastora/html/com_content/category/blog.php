<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Content\Site\Helper\RouteHelper as ContentHelperRoute;
use Joomla\CMS\Categories\Categories;
use Joomla\Helper\TagsHelper;

$app     = Factory::getApplication();
$template = $app->getTemplate();
$tplUri  = rtrim(Uri::root(true), '/') . '/templates/' . $template;

$items = $this->items ?? [];

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
    $tagsHelper = new TagsHelper();
    $items = array_values(array_filter($items, function ($it) use ($tagsHelper, $filterTag) {
        $tags = $tagsHelper->getItemTags('com_content.article', (int) $it->id) ?: [];
        foreach ($tags as $tag) {
            if (mb_strtolower($tag->title) === mb_strtolower($filterTag) || $tag->alias === $filterTag) {
                return true;
            }
        }
        return false;
    }));
}
?>
<div class="projects_page container">
	<div class="projects_wrapper">
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

		<div class="projects_cards news_cards">
			<?php foreach ($items as $item) :
				$images = json_decode($item->images ?? '{}');
				$introImage = $images->image_intro ?? '';
				$link = Route::_(ContentHelperRoute::getArticleRoute($item->slug, (int) $item->catid, (string) $item->language));
			?>
			<div class="project_card">
				<div class="project_card_top">
					<?php if ($introImage) : ?>
						<a href="<?php echo $link; ?>"><img src="<?php echo htmlspecialchars($introImage, ENT_QUOTES, 'UTF-8'); ?>" alt="" /></a>
					<?php else : ?>
						<a href="<?php echo $link; ?>"><img src="<?php echo $tplUri; ?>/assets/images/news-img.png" alt="" /></a>
					<?php endif; ?>
					<div class="project_card_desc">
						<div class="project_card_desc_items">
							<div class="project_hashtag"><p><?php echo htmlspecialchars($this->category->title ?? 'Навіны', ENT_QUOTES, 'UTF-8'); ?></p></div>
						</div>
						<div class="news_date">
							<span><?php echo JHtml::_('date', $item->publish_up ?? $item->created, Text::_('DATE_FORMAT_LC3')); ?></span>
						</div>
						<div class="project_card_desc_text">
							<p><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?></a></p>
							<span><a href="<?php echo $link; ?>"><?php echo strip_tags($item->introtext); ?></a></span>
						</div>
					</div>
				</div>
				<div class="project_card_btm"><img src="<?php echo $tplUri; ?>/assets/images/Union.png" alt="" /></div>
				<a href="<?php echo $link; ?>" class="card_link"><img src="<?php echo $tplUri; ?>/assets/icons/link-f.svg" alt="" /></a>
			</div>
			<?php endforeach; ?>
		</div>
	</div>

	<div class="cards_pagination">
		<?php
			$pagesTotal = (int) ($this->pagination->pagesTotal ?? 1);
			$current    = (int) ($this->pagination->pagesCurrent ?? 1);
			$limit      = (int) ($this->pagination->limit ?? 9);

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
