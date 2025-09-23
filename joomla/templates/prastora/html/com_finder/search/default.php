<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\HTML\HTMLHelper;

$app      = Factory::getApplication();
$template = $app->getTemplate();
$tplUri   = rtrim(Uri::root(true), '/') . '/templates/' . $template;

$queryObj = isset($this->query) ? $this->query : null;
$qStr     = is_object($queryObj) && property_exists($queryObj, 'input') ? (string) $queryObj->input : (string) $app->input->getString('q', '');
$results  = $this->results ?? [];
$total    = (int) ($this->total ?? count($results));

$pluralize = function (int $n): string {
	$mod10 = $n % 10;
	$mod100 = $n % 100;
	if ($mod10 === 1 && $mod100 !== 11) return 'вынік';
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) return 'вынікі';
	return 'вынікаў';
};
?>
<div class="colored_w results_page container">
	<div class="results_page_con">
		<div class="results_top">
			<h2>
				<?php if ($qStr !== '') : ?>
					Ваш пошук <span>“<?php echo htmlspecialchars($qStr, ENT_QUOTES, 'UTF-8'); ?>”</span> даў <?php echo (int) $total . ' ' . $pluralize((int) $total); ?>.
				<?php else : ?>
					<?php echo Text::_('Search'); ?>
				<?php endif; ?>
			</h2>
			<div class="results_actions">
				<div><img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-1.svg" alt="" /></div>
				<div><img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-2.svg" alt="" /></div>
			</div>
		</div>
		<div class="results_items_list">
			<?php if (!empty($results)) : ?>
				<?php foreach ($results as $r) :
					$title = $r->title ?? '';
					$link  = $r->route ?? ($r->url ?? '#');
					$snippet = isset($r->summary) ? strip_tags($r->summary) : '';
					$category = '';
					if (isset($r->section) && $r->section) { $category = (string) $r->section; }
					elseif (isset($r->category) && $r->category) { $category = (string) $r->category; }
					if ($category === '') { $category = 'Навіны'; }
					$date = '';
					if (!empty($r->start_date)) {
						try { $date = HTMLHelper::_('date', $r->start_date, Text::_('DATE_FORMAT_LC3')); } catch (\Throwable $e) { $date = ''; }
					}
				?>
				<div class="results_item">
					<div class="project_card_desc_items">
						<div class="project_hashtag"><p><?php echo htmlspecialchars($category, ENT_QUOTES, 'UTF-8'); ?></p></div>
						<?php if ($date) : ?>
							<span><?php echo $date; ?></span>
						<?php endif; ?>
					</div>
					<div class="results_item_desc">
						<p><a href="<?php echo Route::_($link); ?>"><?php echo htmlspecialchars($title, ENT_QUOTES, 'UTF-8'); ?></a></p>
						<span><a href="<?php echo Route::_($link); ?>"><?php echo htmlspecialchars($snippet, ENT_QUOTES, 'UTF-8'); ?></a></span>
					</div>
					<a href="<?php echo Route::_($link); ?>" class="card_link"><img src="<?php echo $tplUri; ?>/assets/icons/link.svg" alt="" /></a>
				</div>
				<?php endforeach; ?>
			<?php elseif ($qStr !== '') : ?>
				<p><?php echo Text::_('COM_FINDER_SEARCH_NO_RESULTS_HEADING'); ?></p>
			<?php endif; ?>
		</div>
	</div>
</div>
<?php if (!empty($this->pagination) && (int) ($this->pagination->pagesTotal ?? 0) > 1) : ?>
	<div class="cards_pagination">
		<?php
			$pagesTotal = (int) $this->pagination->pagesTotal;
			$current    = (int) $this->pagination->pagesCurrent;
			$limit      = (int) $this->pagination->limit;
			$uri = Uri::getInstance();
			$query = $uri->getQuery(true);
			unset($query['start']);
			$base = $uri->getPath();
			if (!empty($query)) { $base .= '?' . http_build_query($query); }
			$sep = (strpos($base, '?') === false) ? '?' : '&';
			for ($i = 1; $i <= $pagesTotal; $i++) {
				$start = ($i - 1) * $limit;
				$link  = Route::_($base . $sep . 'start=' . $start);
				$tag = ($i === $current) ? 'p' : 'a';
				echo '<' . $tag . ' href="' . ($tag === 'a' ? $link : '#') . '">' . $i . '</' . $tag . '>';
			}
			if ($current < $pagesTotal) {
				$nextStart = $current * $limit;
				$nextLink  = Route::_($base . $sep . 'start=' . $nextStart);
				echo '<a href="' . $nextLink . '">' . Text::_('Далей') . '</a>';
			}
		?>
	</div>
<?php endif; ?>


