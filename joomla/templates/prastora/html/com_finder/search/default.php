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
$input    = $app->input;

$queryObj = isset($this->query) ? $this->query : null;
$qStr     = is_object($queryObj) && property_exists($queryObj, 'input') ? (string) $queryObj->input : (string) $app->input->getString('q', '');
$results  = $this->results ?? [];
$total    = (int) ($this->total ?? count($results));

$fromDate = $input->getString('from', '');
$toDate   = $input->getString('to', '');
$sanitizeDate = static function (string $value): string {
	$trimmed = trim($value);
	return preg_match('/^\d{4}-\d{2}-\d{2}$/', $trimmed) ? $trimmed : '';
};
$fromDate = $sanitizeDate($fromDate);
$toDate   = $sanitizeDate($toDate);

if (!empty($results) && ($fromDate !== '' || $toDate !== '')) {
	$fromTs = $fromDate !== '' ? strtotime($fromDate . ' 00:00:00') : null;
	$toTs   = $toDate !== '' ? strtotime($toDate . ' 23:59:59') : null;

	$results = array_values(array_filter($results, static function ($item) use ($fromTs, $toTs) {
		if (!is_object($item)) {
			return false;
		}

		$dateValue = '';
		foreach (['start_date', 'publish_start_date', 'publish_up', 'created', 'date'] as $prop) {
			if (!empty($item->$prop)) {
				$dateValue = (string) $item->$prop;
				break;
			}
		}

		if ($dateValue === '') {
			return false;
		}

		$timestamp = strtotime($dateValue);
		if ($timestamp === false) {
			return false;
		}

		if ($fromTs && $timestamp < $fromTs) {
			return false;
		}

		if ($toTs && $timestamp > $toTs) {
			return false;
		}

		return true;
	}));

	$total = count($results);
}

$pluralize = function (int $n): string {
	$mod10 = $n % 10;
	$mod100 = $n % 100;
	if ($mod10 === 1 && $mod100 !== 11) {
		return Text::_('TPL_PRASTORA_SEARCH_RESULT_ONE');
	}
	if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
		return Text::_('TPL_PRASTORA_SEARCH_RESULT_FEW');
	}
	return Text::_('TPL_PRASTORA_SEARCH_RESULT_MANY');
};
?>
<div class="colored_w results_page container">
	<div class="results_page_con">
		<div class="results_top">
			<h2>
				<?php if ($qStr !== '') :
					$highlightedQuery = '<span>“' . htmlspecialchars($qStr, ENT_QUOTES, 'UTF-8') . '”</span>';
					$countDisplay = '<span>' . (int) $total . ' ' . htmlspecialchars($pluralize((int) $total), ENT_QUOTES, 'UTF-8') . '</span>';
					echo Text::sprintf('TPL_PRASTORA_SEARCH_RESULT_SUMMARY', $highlightedQuery, $countDisplay);
				else : ?>
					<?php echo Text::_('Search'); ?>
				<?php endif; ?>
			</h2>
			<div class="results_actions">
				<!-- <div class="results_action_button"><img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-1.svg" alt="" /></div> -->
				<div class="results_action_group">
					<div
						class="results_action_button results_action_button_filter"
						data-date-filter-toggle
						role="button"
						tabindex="0"
						aria-haspopup="dialog"
						aria-expanded="false"
						aria-controls="results-date-filter"
					>
						<img src="<?php echo $tplUri; ?>/assets/icons/nav-ac-2.svg" alt="" />
					</div>
					<div
						class="projects_datepicker"
						id="results-date-filter"
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
					if ($category === '') { $category = Text::_('TPL_PRASTORA_NAV_NEWS'); }
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
				echo '<a href="' . $nextLink . '">' . Text::_('TPL_PRASTORA_PAGINATION_NEXT') . '</a>';
			}
		?>
	</div>
<?php endif; ?>


