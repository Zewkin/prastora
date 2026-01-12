<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Categories\Categories;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Log\Log;
use Joomla\Component\Content\Site\Helper\RouteHelper;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use Joomla\Database\ParameterType;
use Joomla\Database\DatabaseDriver;

$languageHelperPath = dirname(__DIR__, 3) . '/helpers/language.php';
if (is_file($languageHelperPath)) {
	require_once $languageHelperPath;
}

$app = Factory::getApplication();
$doc = $app->getDocument();
$template = $app->getTemplate();
$tplUri = rtrim(Uri::root(true), '/') . '/templates/' . $template;
$langTag = strtolower((string) $app->getLanguage()->getTag());
$logoSrc = $tplUri . '/assets/' . ((strpos($langTag, 'en') === 0) ? 'logo-en.svg' : 'logo.svg');
$newsPath = (strpos($langTag, 'ru') === 0) ? '/news-ru' : '/news';
$aboutPath = '/about-us';
$faqPath = '/faq';

if (!function_exists('prastoraResolveMediaUrl')) {
	function prastoraResolveMediaUrl($value)
	{
		$value = $value !== null ? trim((string) $value) : '';
		if ($value === '') {
			return '';
		}
		$clean = HTMLHelper::cleanImageURL($value);
		$url = $clean->url ?? $value;
		if ($url === '') {
			return '';
		}
		if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 || strpos($url, '//') === 0) {
			return $url;
		}
		if ($url[0] === '/') {
			return rtrim(Uri::root(true), '/') . $url;
		}
		return rtrim(Uri::root(true), '/') . '/' . ltrim($url, '/');
	}
}

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

if (!function_exists('prastoraCollectRunsText')) {
	function prastoraCollectRunsText($value): string
	{
		if (is_array($value)) {
			if (isset($value[0]) && is_array($value[0]) && array_key_exists('text', $value[0])) {
				$text = '';
				foreach ($value as $run) {
					if (is_array($run) && isset($run['text'])) {
						$text .= (string) $run['text'];
					}
				}
				return $text;
			}
			if (array_key_exists('simpleText', $value)) {
				return is_scalar($value['simpleText']) ? (string) $value['simpleText'] : '';
			}
			if (array_key_exists('text', $value)) {
				return prastoraCollectRunsText($value['text']);
			}
		}
		return is_scalar($value) ? (string) $value : '';
	}
}

if (!function_exists('prastoraExtractJsonObject')) {
	function prastoraExtractJsonObject(string $source, string $needle): ?string
	{
		$pos = strpos($source, $needle);
		if ($pos === false) {
			return null;
		}
		$start = strpos($source, '{', $pos);
		if ($start === false) {
			return null;
		}
		$length = strlen($source);
		$depth = 0;
		$inString = false;
		$escape = false;
		for ($i = $start; $i < $length; $i++) {
			$char = $source[$i];
			if ($inString) {
				if ($escape) {
					$escape = false;
					continue;
				}
				if ($char === '\\') {
					$escape = true;
					continue;
				}
				if ($char === '"') {
					$inString = false;
				}
				continue;
			}
			if ($char === '"') {
				$inString = true;
				continue;
			}
			if ($char === '{') {
				$depth++;
				continue;
			}
			if ($char === '}') {
				$depth--;
				if ($depth === 0) {
					return substr($source, $start, $i - $start + 1);
				}
				continue;
			}
		}
		return null;
	}
}

if (!function_exists('prastoraFetchChannelVideoFromVideosTab')) {
	function prastoraFetchChannelVideoFromVideosTab(string $channelId): array
	{
		$channelId = trim($channelId);
		if ($channelId === '') {
			return [];
		}
		$url = 'https://www.youtube.com/channel/' . rawurlencode($channelId) . '/videos?view=0&sort=dd&live_view=0';
		$context = stream_context_create([
			'http' => [
				'header' => "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/119.0.0.0 Safari/537.36\r\nAccept-Language: en-US,en;q=0.9\r\n",
				'timeout' => 10,
			],
		]);
		$html = @file_get_contents($url, false, $context);
		if (!is_string($html) || $html === '') {
			return [];
		}
		$json = prastoraExtractJsonObject($html, 'ytInitialData');
		if ($json === null) {
			return [];
		}
		$data = json_decode($json, true);
		if (!is_array($data)) {
			return [];
		}
		$queue = [$data];
		while (!empty($queue)) {
			$node = array_shift($queue);
			if (!is_array($node)) {
				continue;
			}
			if (isset($node['videoRenderer']) && is_array($node['videoRenderer'])) {
				$renderer = $node['videoRenderer'];
				if (!empty($renderer['isShortsContent'])) {
					// Explicitly marked as Shorts, skip.
					continue;
				}
				if (!empty($renderer['thumbnailOverlays'])) {
					$skip = false;
					foreach ($renderer['thumbnailOverlays'] as $overlay) {
						if (!is_array($overlay)) {
							continue;
						}
						$status = $overlay['thumbnailOverlayTimeStatusRenderer']['style'] ?? '';
						if (is_string($status) && strtoupper($status) === 'SHORTS') {
							$skip = true;
							break;
						}
					}
					if ($skip) {
						continue;
					}
				}
				if (!empty($renderer['badges'])) {
					$skip = false;
					foreach ($renderer['badges'] as $badge) {
						if (!is_array($badge)) {
							continue;
						}
						$badgeRenderer = $badge['metadataBadgeRenderer'] ?? null;
						if (!$badgeRenderer) {
							continue;
						}
						$label = prastoraCollectRunsText($badgeRenderer['label'] ?? '');
						if ($label !== '' && stripos($label, 'short') !== false) {
							$skip = true;
							break;
						}
					}
					if ($skip) {
						continue;
					}
				}
				$navigation = $renderer['navigationEndpoint']['watchEndpoint'] ?? [];
				$videoId = isset($renderer['videoId']) ? trim((string) $renderer['videoId']) : '';
				if ($videoId === '' && isset($navigation['videoId'])) {
					$videoId = trim((string) $navigation['videoId']);
				}
				if ($videoId === '') {
					continue;
				}
				if (!empty($navigation['watchEndpointSupportedOnesieConfig']['html5PlaybackOnesieConfig']['commonConfig']['url']) && strpos((string) $navigation['watchEndpointSupportedOnesieConfig']['html5PlaybackOnesieConfig']['commonConfig']['url'], 'shorts') !== false) {
					continue;
				}
				$titleRuns = $renderer['title']['runs'] ?? [];
				$title = trim(prastoraCollectRunsText($titleRuns));
				$description = '';
				if (!empty($renderer['descriptionSnippet']['runs'])) {
					$description = trim(prastoraCollectRunsText($renderer['descriptionSnippet']['runs']));
				}
				$thumbnails = $renderer['thumbnail']['thumbnails'] ?? [];
				$thumbnail = '';
				if (!empty($thumbnails)) {
					$lastThumb = end($thumbnails);
					if (is_array($lastThumb) && !empty($lastThumb['url'])) {
						$thumbnail = (string) $lastThumb['url'];
					}
				}
				return [
					'videoId' => $videoId,
					'title' => $title,
					'description' => $description,
					'thumbnail' => $thumbnail,
					'url' => 'https://www.youtube.com/watch?v=' . rawurlencode($videoId),
					'channelUrl' => 'https://www.youtube.com/channel/' . rawurlencode($channelId),
				];
			}
			foreach ($node as $child) {
				if (is_array($child)) {
					$queue[] = $child;
				}
			}
		}
		return [];
	}
}

if (!function_exists('prastoraFetchLatestVideo')) {
	function prastoraFetchLatestVideo(string $channelId, int $cacheMinutes = 60): array
	{
		static $runtimeCache = [];
		$channelId = trim($channelId);
		if ($channelId === '') {
			return [];
		}
		$cacheMinutes = max(5, $cacheMinutes);
		$cacheKey = $channelId;
		$now = time();
		if (isset($runtimeCache[$cacheKey]) && $runtimeCache[$cacheKey]['expires'] > $now) {
			return $runtimeCache[$cacheKey]['data'];
		}
		try {
			$feedUrl = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
			$rawFeed = @file_get_contents($feedUrl);
			if ($rawFeed === false || $rawFeed === '') {
				Log::add('Video feed error: unable to download feed for channel ' . $channelId, Log::WARNING, 'tpl_prastora_video');
				return [];
			}
		$xml = @simplexml_load_string($rawFeed);
		if (!$xml) {
			Log::add('Video feed parse error for channel ' . $channelId, Log::WARNING, 'tpl_prastora_video');
			return [];
		}
		$xml->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
		$xml->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
		$entries = [];
		if (isset($xml->entry)) {
			foreach ($xml->entry as $candidateEntry) {
				$entries[] = $candidateEntry;
			}
		}
		$entry = null;
		if (!empty($entries)) {
			foreach ($entries as $candidate) {
				$alternateHref = '';
				if (isset($candidate->link)) {
					foreach ($candidate->link as $linkNode) {
						$attrs = $linkNode->attributes();
						if ($attrs && (string) $attrs['rel'] === 'alternate') {
							$alternateHref = (string) $attrs['href'];
							break;
						}
					}
				}
				if ($alternateHref !== '' && strpos($alternateHref, '/shorts/') !== false) {
					continue;
				}
				$mediaCandidate = $candidate->children('media', true);
				$groupCandidate = $mediaCandidate ? $mediaCandidate->group : null;
				$durationSeconds = null;
				if ($groupCandidate) {
					$ytDurationNode = $groupCandidate->children('yt', true);
					if ($ytDurationNode && isset($ytDurationNode->duration)) {
						$durationAttributes = $ytDurationNode->duration->attributes();
						if ($durationAttributes && isset($durationAttributes['seconds'])) {
							$durationSeconds = (int) $durationAttributes['seconds'];
						}
					}
					if ($durationSeconds === null && isset($groupCandidate->content)) {
						foreach ($groupCandidate->content as $contentNode) {
							$contentAttributes = $contentNode->attributes();
							if ($contentAttributes && isset($contentAttributes['duration'])) {
								$durationSeconds = (int) $contentAttributes['duration'];
								break;
							}
						}
					}
				}
				if ($durationSeconds !== null && $durationSeconds > 0 && $durationSeconds <= 61) {
					continue;
				}
				$entry = $candidate;
				break;
			}
		}
		if (!$entry) {
			$pageVideo = prastoraFetchChannelVideoFromVideosTab($channelId);
			if (!empty($pageVideo)) {
				$data = [
					'videoId' => (string) ($pageVideo['videoId'] ?? ''),
					'title' => (string) ($pageVideo['title'] ?? ''),
					'description' => (string) ($pageVideo['description'] ?? ''),
					'thumbnail' => (string) ($pageVideo['thumbnail'] ?? ''),
					'url' => (string) ($pageVideo['url'] ?? ''),
					'channelUrl' => (string) ($pageVideo['channelUrl'] ?? ''),
				];
				$runtimeCache[$cacheKey] = [
					'expires' => $now + ($cacheMinutes * 60),
					'data' => $data,
				];
				return $data;
			}
			return [];
		}
		$yt = $entry->children('yt', true);
		$media = $entry->children('media', true);
		$group = $media ? $media->group : null;

			$videoId = isset($yt->videoId) ? (string) $yt->videoId : '';
			if ($videoId === '') {
				return [];
			}

			$title = (string) ($entry->title ?? '');
			$description = '';
			if ($group && isset($group->description)) {
				$description = (string) $group->description;
			} elseif (isset($entry->summary)) {
				$description = (string) $entry->summary;
			}

			$thumbnail = '';
			if ($group && isset($group->thumbnail)) {
				$thumbAttributes = $group->thumbnail[0]->attributes();
				if ($thumbAttributes && isset($thumbAttributes['url'])) {
					$thumbnail = (string) $thumbAttributes['url'];
				}
			}

			$videoUrl = '';
			if (isset($entry->link)) {
				foreach ($entry->link as $link) {
					$attrs = $link->attributes();
					if ($attrs && (string) $attrs['rel'] === 'alternate') {
						$videoUrl = (string) $attrs['href'];
						break;
					}
				}
			}
			if ($videoUrl === '') {
				$videoUrl = 'https://www.youtube.com/watch?v=' . $videoId;
			}

			$channelUrl = '';
			if (isset($entry->author) && isset($entry->author->uri)) {
				$channelUrl = (string) $entry->author->uri;
			}

			$data = [
				'videoId' => $videoId,
				'title' => $title,
				'description' => $description,
				'thumbnail' => $thumbnail,
				'url' => $videoUrl,
				'channelUrl' => $channelUrl,
			];
			$runtimeCache[$cacheKey] = [
				'expires' => $now + ($cacheMinutes * 60),
				'data' => $data,
			];
			return $data;
		} catch (\Throwable $e) {
			Log::add('Video feed exception: ' . $e->getMessage(), Log::WARNING, 'tpl_prastora_video');
			return [];
		}
	}
}

if (!function_exists('prastoraResolveLanguageValue')) {
	function prastoraResolveLanguageValue(array $map, string $languageTag, string $defaultKey)
	{
		if (isset($map[$languageTag])) {
			return $map[$languageTag];
		}

		return $map[$defaultKey] ?? reset($map);
	}
}

if (!function_exists('prastoraFindCategoryIdByAlias')) {
	function prastoraFindCategoryIdByAlias(DatabaseDriver $db, string $alias): int
	{
		$alias = trim((string) $alias);

		if ($alias === '') {
			return 0;
		}

		return (int) $db->setQuery(
			$db->getQuery(true)
				->select($db->quoteName('id'))
				->from($db->quoteName('#__categories'))
				->where($db->quoteName('extension') . ' = ' . $db->quote('com_content'))
				->where($db->quoteName('alias') . ' = ' . $db->quote($alias))
				->setLimit(1)
		)->loadResult();
	}
}

// Action формы поиска: ищем пункт меню com_finder
$menu = $app->getMenu();
$languageObject = $app->getLanguage();
$currentLanguageTag = $languageObject ? $languageObject->getTag() : 'be-BY';

$projectsMenuAliasMap = [
	'be-BY' => 'projects',
	'ru-RU' => 'men-projects-ru',
	'en-GB' => 'projects-en',
];
$projectsCategoryAliasMap = [
	'be-BY' => 'projects',
	'ru-RU' => 'projects-ru',
	'en-GB' => 'projects-en',
];
$newsMenuAliasMap = [
	'be-BY' => 'news',
	'ru-RU' => 'men-news-ru',
	'en-GB' => 'news-en',
];
$newsCategoryAliasMap = [
	'be-BY' => 'news',
	'ru-RU' => 'news-ru',
	'en-GB' => 'news-en',
];
$constitutionMenuAliasMap = [
	'be-BY' => 'constitution',
	'ru-RU' => 'men-const-ru',
	'en-GB' => 'constitution-en',
];
$projectsMenuAlias = prastoraResolveLanguageValue($projectsMenuAliasMap, $currentLanguageTag, 'be-BY');
$newsMenuAlias = prastoraResolveLanguageValue($newsMenuAliasMap, $currentLanguageTag, 'be-BY');
$constitutionMenuAlias = prastoraResolveLanguageValue($constitutionMenuAliasMap, $currentLanguageTag, 'be-BY');
$projectsCategoryAlias = prastoraResolveLanguageValue($projectsCategoryAliasMap, $currentLanguageTag, 'be-BY');
$newsCategoryAlias = prastoraResolveLanguageValue($newsCategoryAliasMap, $currentLanguageTag, 'be-BY');

$searchMenuItem = $menu ? $menu->getItems('link', 'index.php?option=com_finder&view=search', true) : null;
$searchAction = $searchMenuItem
	? Route::_('index.php?option=com_finder&view=search&Itemid=' . (int) $searchMenuItem->id)
	: (rtrim(Uri::base(true), '/') . '/index.php?option=com_finder&view=search');

// Projects category/link
$projectsMenuItem = null;
if ($menu) {
	$projectsMenuItem = $menu->getItems('alias', $projectsMenuAlias, true);
	if (!$projectsMenuItem && $projectsMenuAlias !== 'projects') {
		$projectsMenuItem = $menu->getItems('alias', 'projects', true);
	}
}

$db = Factory::getContainer()->get('DatabaseDriver');
$projectsCategoryId = prastoraFindCategoryIdByAlias($db, $projectsCategoryAlias);

$projectsIntroFallback = Text::_('TPL_PRASTORA_PROJECTS_INTRO_FALLBACK');

if (!$projectsCategoryId) {
    $projectsCategoryId = (int) $db->setQuery(
        $db->getQuery(true)
            ->select($db->quoteName('catid'))
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('alias') . ' = ' . $db->quote('constitution'))
            ->setLimit(1)
    )->loadResult();
}

$newsMenuItem = null;
if ($menu) {
	$newsMenuItem = $menu->getItems('alias', $newsMenuAlias, true);
	if (!$newsMenuItem && $newsMenuAlias !== 'news') {
		$newsMenuItem = $menu->getItems('alias', 'news', true);
	}
}
$newsCategoryId = 0;

if ($newsMenuItem) {
    if (!empty($newsMenuItem->query['id'])) {
        $newsCategoryId = (int) $newsMenuItem->query['id'];
    } elseif (!empty($newsMenuItem->link)) {
        $linkQuery = [];
        $queryString = parse_url($newsMenuItem->link, PHP_URL_QUERY);
        if (!empty($queryString)) {
            parse_str($queryString, $linkQuery);
            if (!empty($linkQuery['id'])) {
                $newsCategoryId = (int) $linkQuery['id'];
            }
        }
    }
}

if (!$newsCategoryId) {
    $newsCategoryId = prastoraFindCategoryIdByAlias($db, $newsCategoryAlias);
    if (!$newsCategoryId && $newsCategoryAlias === 'news-en') {
        $newsCategoryId = 0;
    }
}

$newsCategoryIds = [];
if ($newsCategoryId) {
    $newsCategoryIds[] = $newsCategoryId;
    $categoriesApi = Categories::getInstance('Content');
    if ($categoriesApi) {
        $newsCategory = $categoriesApi->get($newsCategoryId);
        if ($newsCategory) {
            foreach ($newsCategory->getChildren(true) as $child) {
                $newsCategoryIds[] = (int) $child->id;
            }
        }
    }
    $newsCategoryIds = array_values(array_unique(array_map('intval', $newsCategoryIds)));
}

$projectsLink = $projectsMenuItem
    ? Route::_($projectsMenuItem->link . '&Itemid=' . (int) $projectsMenuItem->id)
    : ($projectsCategoryId ? Route::_('index.php?option=com_content&view=category&id=' . $projectsCategoryId . '&layout=projects') : '#');
$newsLink = $newsMenuItem
    ? Route::_($newsMenuItem->link . '&Itemid=' . (int) $newsMenuItem->id)
    : ($newsCategoryId ? Route::_('index.php?option=com_content&view=category&id=' . $newsCategoryId) : '#');
$constitutionMenuItem = null;
if ($menu) {
	$constitutionMenuItem = $menu->getItems('alias', $constitutionMenuAlias, true);
	if (!$constitutionMenuItem && $constitutionMenuAlias !== 'constitution') {
		$constitutionMenuItem = $menu->getItems('alias', 'constitution', true);
	}
}
$constitutionLink = $constitutionMenuItem
    ? Route::_($constitutionMenuItem->link . '&Itemid=' . (int) $constitutionMenuItem->id)
    : Route::_('index.php?option=com_content&view=article&id=16');

$projectsItems = [];
if ($projectsCategoryId) {
    $query = $db->getQuery(true)
        ->select([
            'a.id',
            'a.title',
            'a.alias',
            'a.introtext',
            'a.images',
            'a.catid',
            'a.publish_up',
            'a.created',
        ])
        ->from($db->quoteName('#__content', 'a'))
        ->where($db->quoteName('a.state') . ' = 1')
        ->where($db->quoteName('a.catid') . ' = :catid')
        ->order('COALESCE(a.publish_up, a.created) DESC')
        ->setLimit(6)
        ->bind(':catid', $projectsCategoryId, ParameterType::INTEGER);
    $db->setQuery($query);
    $projectsItems = $db->loadObjectList() ?: [];
}

// Подключаем Swiper (CSS/JS)
$doc->addStyleSheet('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css');
$doc->addScript('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js');
?>
<div class="header_wrapper">
	<header class="main_header container">
		<nav>
			<div class="nav_wrapper">
				<div class="nav_left">
					<a href="/" class="logo"><img src="<?php echo $logoSrc; ?>" alt="" /></a>
					<div class="nav_menu">
						<?php $modsMenu = ModuleHelper::getModules('menu'); if (!empty($modsMenu)) { foreach ($modsMenu as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
								<a href="<?php echo htmlspecialchars($constitutionLink, ENT_QUOTES, 'UTF-8'); ?>"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?></p></a>
								<a href="<?php echo htmlspecialchars($projectsLink, ENT_QUOTES, 'UTF-8'); ?>"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?></p></a>
								<a href="<?php echo htmlspecialchars($newsPath, ENT_QUOTES, 'UTF-8'); ?>"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?></p></a>
								<a href="<?php echo htmlspecialchars($aboutPath, ENT_QUOTES, 'UTF-8'); ?>"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?></p></a>
								<a href="<?php echo htmlspecialchars($faqPath, ENT_QUOTES, 'UTF-8'); ?>"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?></p></a>
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
					<a href="https://buy.stripe.com/bJeaEX0vy2HDetk9vH33W00" target="_blank" class="nav_btn">Donate</a>
					<?php prastoraRenderLanguageSwitcher('home-desktop'); ?>
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
							<a href="<?php echo htmlspecialchars($constitutionLink, ENT_QUOTES, 'UTF-8'); ?>"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_CONSTITUTION'); ?></p></a>
							<a href="<?php echo htmlspecialchars($projectsLink, ENT_QUOTES, 'UTF-8'); ?>"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_PROJECTS'); ?></p></a>
							<a href="/news"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_NEWS'); ?></p></a>
							<a href="/about-us"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_ABOUT'); ?></p></a>
							<a href="/faq"><p data-label="<?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?>"><?php echo Text::_('TPL_PRASTORA_NAV_FAQ'); ?></p></a>
						<?php } ?>
					</div>
				<div class="mob_menu_actions">
					<?php prastoraRenderLanguageSwitcher('home-mobile'); ?>
					<a href="https://buy.stripe.com/bJeaEX0vy2HDetk9vH33W00" target="_blank" class="nav_btn"><?php echo Text::_("TPL_PRASTORA_ACTION_DONATE"); ?></a>
				</div>
			</div>
		</div>

		<?php $modsHero = ModuleHelper::getModules('hero'); if (!empty($modsHero)) { foreach ($modsHero as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
		<div class="header_content">
			<div class="header_main_title">
				<h1><?php echo Text::_('TPL_PRASTORA_HERO_TITLE'); ?></h1>
			</div>
			<div class="header_sec_content">
				<p><?php echo Text::_('TPL_PRASTORA_HERO_LEAD'); ?></p>
				<div class="header_links">
					<a href="<?php echo htmlspecialchars($constitutionLink, ENT_QUOTES, 'UTF-8'); ?>"><div></div><span><?php echo Text::_('TPL_PRASTORA_HERO_CTA_CONSTITUTION'); ?></span></a>
					<a href="<?php echo htmlspecialchars($projectsLink, ENT_QUOTES, 'UTF-8'); ?>"><div></div><span><?php echo Text::_('TPL_PRASTORA_HERO_CTA_PROJECTS'); ?></span></a>
				</div>
				<h5><?php echo Text::_('TPL_PRASTORA_HERO_NOTICE'); ?></h5>
			</div>
		</div>
		<?php } ?>
		<div class="header_btm">
			<p><?php echo Text::_('TPL_PRASTORA_HERO_NOTICE'); ?></p>
			<div class="header_socials">
				<a href="https://www.facebook.com/groups/ConstitutionBelarus" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" /></a>
				<a href="https://t.me/konstituciya_bel" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /></a>
				<a href="https://www.youtube.com/@PRA_NAS" target="_blank"><img src="<?php echo $tplUri; ?>/assets/icons/yt.svg" alt="" /></a>
			</div>
		</div>
	</header>
</div>

<?php $modsA = ModuleHelper::getModules('home-section-a'); if (!empty($modsA)) { foreach ($modsA as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
<section class="moto_block container">
	<img src="<?php echo $tplUri; ?>/assets/images/moto-img.png" alt="" />
		<div class="moto_block_desc">
			<img src="<?php echo $tplUri; ?>/assets/icons/logo-icon.svg" alt="" />
			<p><?php echo Text::_('TPL_PRASTORA_MOTO_INTRO'); ?></p>
			<p><?php echo Text::_('TPL_PRASTORA_MOTO_PARAGRAPH_ONE'); ?></p>
			<p><?php echo Text::_('TPL_PRASTORA_MOTO_PARAGRAPH_TWO'); ?></p>
		</div>
</section>
<?php } ?>

<?php
$modsB = ModuleHelper::getModules('home-section-b');
if (!empty($modsB)) {
	foreach ($modsB as $mod) {
		echo ModuleHelper::renderModule($mod, ['style' => 'none']);
	}
} else {
	$howSteps = [
		[
			'icon' => 'howt-1.svg',
			'title' => Text::_('TPL_PRASTORA_HOW_STEP2_TITLE'),
			'body' => Text::_('TPL_PRASTORA_HOW_STEP2_MORE'),
			'extras' => [],
		],
		[
			'icon' => 'howt-2.svg',
			'title' => Text::_('TPL_PRASTORA_HOW_STEP3_TITLE'),
			'body' => Text::_('TPL_PRASTORA_HOW_STEP3_MORE'),
			'extras' => [Text::_('TPL_PRASTORA_HOW_STEP4_MORE')],
		],
		[
			'icon' => 'howt-3.svg',
			'title' => Text::_('TPL_PRASTORA_HOW_STEP5_TITLE'),
			'body' => Text::_('TPL_PRASTORA_HOW_STEP5_MORE'),
			'extras' => [Text::_('TPL_PRASTORA_HOW_STEP5_NOTE')],
		],
	];
?>
<div class="how_it_works">
	<div class="container">
			<div class="how_it_work_title">
				<h2><?php echo Text::_('TPL_PRASTORA_HOW_TITLE'); ?></h2>
			<div class="how_it_work_descs">
				<p><?php echo Text::_('TPL_PRASTORA_HOW_STEP1'); ?></p>
				<div>
					<img src="<?php echo $tplUri; ?>/assets/icons/Vector.svg" alt="" />
					<p><?php echo Text::_('TPL_PRASTORA_HOW_STEP1_MORE'); ?></p>
				</div>
			</div>
		</div>
		<div class="howtw_cards">
			<?php foreach ($howSteps as $step) : ?>
			<div class="howtw_card">
				<div class="howtw_card_img">
					<img src="<?php echo $tplUri; ?>/assets/icons/<?php echo htmlspecialchars($step['icon'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
				</div>
				<p><?php echo $step['title']; ?></p>
				<span><?php echo $step['body']; ?></span>
				<?php foreach ($step['extras'] as $extra) : ?>
				<br />
				<br />
				<span><?php echo $extra; ?></span>
				<?php endforeach; ?>
			</div>
			<?php endforeach; ?>
		</div>

		<div class="swiper howt_car">
			<div class="swiper-wrapper">
				<?php foreach ($howSteps as $step) : ?>
				<div class="swiper-slide">
					<div class="howtw_card">
						<div class="howtw_card_img">
							<img src="<?php echo $tplUri; ?>/assets/icons/<?php echo htmlspecialchars($step['icon'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
						</div>
						<p><?php echo $step['title']; ?></p>
						<span><?php echo $step['body']; ?></span>
						<?php foreach ($step['extras'] as $extra) : ?>
						<br />
						<br />
						<span><?php echo $extra; ?></span>
						<?php endforeach; ?>
					</div>
				</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="swiper_pagination howt_car_pag"></div>
	</div>

	<div class="howt_btm">
		<div class="howt_btm_desc">
			<h4><?php echo Text::_('TPL_PRASTORA_SECURITY_NOTICE_TITLE'); ?></h4>
			<p><?php echo Text::_('TPL_PRASTORA_SECURITY_NOTICE_LINE1'); ?></p>
			<p><?php echo Text::_('TPL_PRASTORA_SECURITY_NOTICE_LINE2'); ?></p>
			<p><?php echo Text::_('TPL_PRASTORA_SECURITY_NOTICE_LINE3'); ?></p>
		</div>
	</div>
</div>
<?php } ?>

<?php $modsC = ModuleHelper::getModules('home-section-c'); if (!empty($modsC)) { foreach ($modsC as $mod) { echo ModuleHelper::renderModule($mod, ['style' => 'none']); } } else { ?>
<section class="last_projects container">
	<div class="block_title"><h2><?php echo Text::_('TPL_PRASTORA_PROJECTS_SECTION_TITLE'); ?></h2></div>
	<?php if (!empty($projectsItems)) : ?>
	<div class="last_project_cards">
		<?php foreach (array_slice($projectsItems, 0, 2) as $idx => $project) :
			$link = Route::_(RouteHelper::getArticleRoute($project->id . ':' . $project->alias, (int) $project->catid));
			$customIntro = prastoraGetIntroFieldText($project);
			$rawIntro = $customIntro !== '' ? $customIntro : trim(strip_tags($project->introtext));
			if ($rawIntro === '') {
				$rawIntro = $projectsIntroFallback;
			}
			$summary = HTMLHelper::_('string.truncate', $rawIntro, 180, true, false);
		?>
		<div class="last_project_card">
			<img class="last_project_card_bg" src="<?php echo $tplUri; ?>/assets/images/project-bg.svg" alt="" />
			<img src="<?php echo $tplUri; ?>/assets/icons/project-icon.svg" alt="" />
			<div class="last_project_card_descs">
				<div>
					<p><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($project->title, ENT_QUOTES, 'UTF-8'); ?></a></p>
					<span><?php echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></span>
				</div>
				<a href="<?php echo $link; ?>"><img src="<?php echo $tplUri; ?>/assets/icons/link.svg" alt="" /></a>
			</div>
			<div class="last_project_card_slice"></div>
		</div>
		<?php endforeach; ?>
	</div>
	<div class="swiper last_project_car">
		<div class="swiper-wrapper">
		<?php foreach ($projectsItems as $project) :
			$link = Route::_(RouteHelper::getArticleRoute($project->id . ':' . $project->alias, (int) $project->catid));
			$customIntro = prastoraGetIntroFieldText($project);
			$rawIntro = $customIntro !== '' ? $customIntro : trim(strip_tags($project->introtext));
			if ($rawIntro === '') {
				$rawIntro = $projectsIntroFallback;
			}
			$summary = HTMLHelper::_('string.truncate', $rawIntro, 180, true, false);
			?>
			<div class="swiper-slide">
				<div class="last_project_card">
					<img class="last_project_card_bg" src="<?php echo $tplUri; ?>/assets/images/project-bg.svg" alt="" />
					<img src="<?php echo $tplUri; ?>/assets/icons/project-icon.svg" alt="" />
					<div class="last_project_card_descs">
						<div>
							<p><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($project->title, ENT_QUOTES, 'UTF-8'); ?></a></p>
							<span><?php echo htmlspecialchars($summary, ENT_QUOTES, 'UTF-8'); ?></span>
						</div>
						<a href="<?php echo $link; ?>"><img src="<?php echo $tplUri; ?>/assets/icons/link.svg" alt="" /></a>
					</div>
					<div class="last_project_card_slice"></div>
				</div>
			</div>
			<?php endforeach; ?>
		</div>
	</div>
	<div class="swiper_pagination last_project_car_pag"></div>
	<?php else : ?>
	<p class="no-projects"><?php echo Text::_('TPL_PRASTORA_PROJECTS_EMPTY'); ?></p>
	<?php endif; ?>
	<div class="corner_btn">
		<a href="<?php echo htmlspecialchars($projectsLink, ENT_QUOTES, 'UTF-8'); ?>"><button class="line_btn"><?php echo Text::_('TPL_PRASTORA_PROJECTS_MORE'); ?> <img src="<?php echo $tplUri; ?>/assets/icons/arrow-top-right.svg" alt="" /></button></a>
	</div>
</section>
<?php } ?>

<?php if ($currentLanguageTag !== 'en-GB') : ?>
<section class="news_block container">
	<div class="block_title"><h2><?php echo Text::_('TPL_PRASTORA_NEWS_SECTION_TITLE'); ?></h2></div>
	<div class="news_cards">
		<?php
			$db = Joomla\CMS\Factory::getDbo();
			$nowSql = Factory::getDate()->toSql();
			$nullDate = $db->getNullDate();
			$query = $db->getQuery(true)
				->select(['a.id','a.title','a.alias','a.introtext','a.catid','a.publish_up','a.created','a.images'])
				->from('#__content AS a')
				->where('a.state = 1')
				// Exclude future-dated or expired articles so the links do not 404.
				->where('(a.publish_up IS NULL OR a.publish_up = ' . $db->quote($nullDate) . ' OR a.publish_up <= ' . $db->quote($nowSql) . ')')
				->where('(a.publish_down IS NULL OR a.publish_down = ' . $db->quote($nullDate) . ' OR a.publish_down >= ' . $db->quote($nowSql) . ')')
				->order('COALESCE(a.publish_up, a.created) DESC')
				->setLimit(3);
			if (!empty($newsCategoryIds)) {
				$query->where('a.catid IN (' . implode(',', $newsCategoryIds) . ')');
			} elseif ($projectsCategoryId) {
				$query->where('a.catid != ' . (int) $projectsCategoryId);
			}
			$db->setQuery($query);
		$rows = $db->loadObjectList() ?: [];
		$newsItems = [];
		foreach ($rows as $row) {
			$images = json_decode($row->images ?: '{}');
			$link  = Joomla\CMS\Router\Route::_(Joomla\Component\Content\Site\Helper\RouteHelper::getArticleRoute($row->id . ':' . $row->alias, (int)$row->catid));
			// Определяем фон карточки из intro image или плейсхолдер
			$bg = $tplUri . '/assets/images/news-img.png';
			if (!empty($images->image_intro)) {
				$introPath = $images->image_intro;
				if (strpos($introPath, 'http') === 0 || strpos($introPath, '//') === 0 || strpos($introPath, '/') === 0) {
					$bg = $introPath;
				} else {
					$bg = rtrim(Joomla\CMS\Uri\Uri::root(true), '/') . '/' . ltrim($introPath, '/');
				}
			}
		$customNewsIntro = prastoraGetIntroFieldText($row);
		$newsIntro = $customNewsIntro !== '' ? $customNewsIntro : trim(strip_tags($row->introtext));
		$newsSummary = $newsIntro !== '' ? HTMLHelper::_('string.truncate', $newsIntro, 180, true, false) : '';
			$newsItems[] = [
				'bg' => htmlspecialchars($bg, ENT_QUOTES, 'UTF-8'),
				'link' => $link,
				'title' => htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'),
				'date' => htmlspecialchars(JHtml::_('date', $row->publish_up ?: $row->created, 'Y-m-d'), ENT_QUOTES, 'UTF-8'),
				'summary' => $newsSummary !== '' ? htmlspecialchars($newsSummary, ENT_QUOTES, 'UTF-8') : '',
			];
		}
	?>
	<?php foreach ($newsItems as $item) : ?>
		<div class="news_card" style="background-image:url('<?php echo $item['bg']; ?>'); background-size:cover; background-position:center;">
			<div class="news_card_desc">
				<h4><a href="<?php echo $item['link']; ?>"><?php echo $item['title']; ?></a></h4>
				<p><?php echo $item['date']; ?></p>
				<?php if ($item['summary'] !== '') : ?>
				<span><a href="<?php echo $item['link']; ?>"><?php echo $item['summary']; ?></a></span>
				<?php endif; ?>
			</div>
			<a href="<?php echo $item['link']; ?>" class="card_link"><img src="<?php echo $tplUri; ?>/assets/icons/link-red.svg" alt="" /></a>
		</div>
	<?php endforeach; ?>
	</div>
	<?php if (!empty($newsItems)) : ?>
	<div class="swiper news_cards_car">
		<div class="swiper-wrapper">
			<?php foreach ($newsItems as $item) : ?>
				<div class="swiper-slide">
					<div class="news_card" style="background-image:url('<?php echo $item['bg']; ?>'); background-size:cover; background-position:center;">
						<div class="news_card_desc">
							<h4><a href="<?php echo $item['link']; ?>"><?php echo $item['title']; ?></a></h4>
							<p><?php echo $item['date']; ?></p>
							<?php if ($item['summary'] !== '') : ?>
							<span><a href="<?php echo $item['link']; ?>"><?php echo $item['summary']; ?></a></span>
							<?php endif; ?>
						</div>
						<a href="<?php echo $item['link']; ?>" class="card_link"><img src="<?php echo $tplUri; ?>/assets/icons/link-red.svg" alt="" /></a>
					</div>
				</div>
			<?php endforeach; ?>
		</div>
	</div>
	<?php endif; ?>
	<div class="swiper_pagination news_cards_car_pag"></div>
	<div class="corner_btn">
		<a href="<?php echo htmlspecialchars($newsLink, ENT_QUOTES, 'UTF-8'); ?>"><button class="line_btn"><?php echo Text::_('TPL_PRASTORA_NEWS_ALL'); ?> <img src="<?php echo $tplUri; ?>/assets/icons/arrow-top-right.svg" alt="" /></button></a>
	</div>
</section>
<?php endif; ?>
<?php
	$templateParams = $app->getTemplate(true)->params;
	$videoChannelId = trim((string) $templateParams->get('video_channel_id', ''));
	$videoCacheMinutes = (int) $templateParams->get('video_cache_minutes', 60);
	$videoData = [];
	if ($videoChannelId !== '') {
		$videoData = prastoraFetchLatestVideo($videoChannelId, $videoCacheMinutes);
	}

	$fallbackVideoId = trim((string) $templateParams->get('video_fallback_video_id', ''));
	if (empty($videoData) && $fallbackVideoId !== '') {
		$videoData = [
			'videoId' => $fallbackVideoId,
			'title' => '',
			'description' => '',
			'thumbnail' => '',
			'url' => 'https://www.youtube.com/watch?v=' . $fallbackVideoId,
			'channelUrl' => '',
		];
	}

	$overrideTitle = trim((string) $templateParams->get('video_fallback_title', ''));
	$overrideDescription = trim((string) $templateParams->get('video_fallback_description', ''));
	if ($overrideTitle !== '') {
		$videoData['title'] = $overrideTitle;
	}
	if ($overrideDescription !== '') {
		$videoData['description'] = $overrideDescription;
	}

$defaultTitle = Text::_('TPL_PRASTORA_VIDEO_FALLBACK_TITLE');
$defaultDescription = Text::_('TPL_PRASTORA_VIDEO_FALLBACK_DESC');

	$videoId = trim((string) ($videoData['videoId'] ?? ''));
	$videoTitle = trim((string) ($videoData['title'] ?? ''));
	if ($videoTitle === '') {
		$videoTitle = $defaultTitle;
	}
	$videoDescription = trim((string) ($videoData['description'] ?? ''));
	if ($videoDescription === '') {
		$videoDescription = $defaultDescription;
	}
	$videoDescription = strip_tags($videoDescription);
	$videoDescriptionShort = HTMLHelper::_('string.truncate', $videoDescription, 220, true, false);

	$channelUrlParam = trim((string) $templateParams->get('video_channel_url', ''));
	$channelUrl = $channelUrlParam !== '' ? $channelUrlParam : trim((string) ($videoData['channelUrl'] ?? ''));
	if ($channelUrl === '' && $videoChannelId !== '') {
		$channelUrl = 'https://www.youtube.com/channel/' . $videoChannelId;
	}
	if ($channelUrl === '') {
		$channelUrl = '#';
	}

	$videoUrl = trim((string) ($videoData['url'] ?? ''));
	if ($videoUrl === '' && $videoId !== '') {
		$videoUrl = 'https://www.youtube.com/watch?v=' . $videoId;
	}
	if ($channelUrl === '#' && $videoUrl !== '') {
		$channelUrl = $videoUrl;
	}

	$videoThumbnail = trim((string) ($videoData['thumbnail'] ?? ''));
	$fallbackThumbnailParam = $templateParams->get('video_fallback_thumbnail');
	if (!empty($fallbackThumbnailParam)) {
		$videoThumbnail = prastoraResolveMediaUrl($fallbackThumbnailParam);
	}
	if ($videoThumbnail === '' && $videoId !== '') {
		$videoThumbnail = 'https://i.ytimg.com/vi/' . rawurlencode($videoId) . '/hqdefault.jpg';
	}

	$embedUrl = $videoId !== '' ? 'https://www.youtube.com/embed/' . rawurlencode($videoId) . '?autoplay=1&rel=0' : '';
	$overlayStyle = $videoThumbnail !== '' ? ' style="background-image:url(' . htmlspecialchars($videoThumbnail, ENT_QUOTES, 'UTF-8') . ');"' : '';
	$hasPlayableVideo = $embedUrl !== '';
$playButtonLabel = Text::sprintf('TPL_PRASTORA_VIDEO_PLAY_LABEL', $videoTitle);
?>

<div class="video_block">
	<div class="video_block_wrapper">
		<div class="video_player"<?php echo $hasPlayableVideo ? ' data-video-player data-video-playing="0" data-video-embed="' . htmlspecialchars($embedUrl, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
			<div class="video_frame_holder" data-video-frame></div>
			<div class="video_player_overlay" data-video-overlay<?php echo $overlayStyle; ?>>
				<?php if ($hasPlayableVideo) : ?>
				<button type="button" class="video_play_btn" data-video-play aria-label="<?php echo htmlspecialchars($playButtonLabel, ENT_QUOTES, 'UTF-8'); ?>">
					<img src="<?php echo $tplUri; ?>/assets/icons/play.svg" alt="" />
				</button>
				<?php else : ?>
				<span class="video_play_btn" aria-hidden="true">
					<img src="<?php echo $tplUri; ?>/assets/icons/play.svg" alt="" />
				</span>
				<?php endif; ?>
			</div>
		</div>
		<div class="video_block_desc">
			<div>
				<p><?php echo htmlspecialchars($videoTitle, ENT_QUOTES, 'UTF-8'); ?></p>
				<span><?php echo nl2br(htmlspecialchars($videoDescriptionShort, ENT_QUOTES, 'UTF-8')); ?></span>
			</div>
			<a class="line_btn" href="<?php echo htmlspecialchars($channelUrl, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $channelUrl !== '#' ? ' target="_blank" rel="noopener"' : ''; ?>>YouTube <img src="<?php echo $tplUri; ?>/assets/icons/arrow-top-right.svg" alt="" /></a>
		</div>
		<div class="video_copyright"><p><?php echo Text::_('TPL_PRASTORA_VIDEO_NOTE'); ?></p></div>
	</div>
</div>

<section class="partners_block container">
	<div class="block_title"><h2><?php echo Text::_('TPL_PRASTORA_PARTNERS_SECTION_TITLE'); ?></h2></div>
	<div class="partners_list">
		<a href="https://tsikhanouskaya.org" target="_blank"><img src="<?php echo $tplUri; ?>/assets/images/logo-tsikhanouskaya.svg" alt="Офіс Святланы Ціханоўскай" /></a>
		<a href="https://pr-belarus.org" target="_blank"><img src="<?php echo $tplUri; ?>/assets/images/Logo_PR_eng.svg" alt="Personnel Reserve" /></a>
	</div>
</section>

<footer>
	<div class="footer_wrapper container">
		<div class="footer_main">
			<div class="footer_main_left">
				<a href="/" class="footer_logo">
					<img src="<?php echo $tplUri; ?>/assets/icons/project-icon.svg" alt="" />
				</a>
				<div class="footer_email">
					<h4><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACT_PROMPT'); ?></h4>
					<p><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACT_TEXT'); ?></p>
					<a href="mailto:hello@prastora.info"><span>hello@prastora.info</span></a>
				</div>
				<div class="footer_contacts">
					<h4><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACTS_LABEL'); ?></h4>
					<a href="mailto:hello@prastora.info">
						<img src="<?php echo $tplUri; ?>/assets/icons/mail.svg" alt="" />
						<p>hello@prastora.info</p>
					</a>
					<a href="https://t.me/konstituciya_bel" target="_blank">
						<img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" />
						<p><?php echo Text::_('TPL_PRASTORA_FOOTER_TELEGRAM_LATIN'); ?></p>
					</a>
					<a href="https://www.facebook.com/groups/ConstitutionBelarus" target="_blank">
						<img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" />
						<p><?php echo Text::_('TPL_PRASTORA_FOOTER_CONTACT_FACEBOOK'); ?></p>
					</a>
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

<script>
	new Swiper('.howt_car', { slidesPerView: 1, spaceBetween: 10, pagination: { el: '.howt_car_pag', clickable: true } });
	new Swiper('.last_project_car', { slidesPerView: 1, spaceBetween: 10, pagination: { el: '.last_project_car_pag', clickable: true } });
	new Swiper('.news_cards_car', { slidesPerView: 1, spaceBetween: 10, pagination: { el: '.news_cards_car_pag', clickable: true } });
</script>
