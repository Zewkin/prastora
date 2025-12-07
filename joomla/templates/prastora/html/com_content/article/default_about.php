<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

$item = $this->item;
$app = Factory::getApplication();
$template = $app->getTemplate();
$tplUri  = rtrim(Uri::root(true), '/') . '/templates/' . $template;

$rawFields = [];
try {
	$rawFields = FieldsHelper::getFields('com_content.article', $item, true) ?: [];
} catch (\Throwable $e) {
}

$fieldMap = [];
foreach ($rawFields as $field) {
	$name = (string) ($field->name ?? '');
	if ($name === '') {
		continue;
	}
	$fieldMap[$name] = $field;
}

$getRaw = static function (array $map, string $name) {
	if (!isset($map[$name])) {
		return null;
	}
	$field = $map[$name];
	if (isset($field->rawvalue)) {
		return $field->rawvalue;
	}
	if (isset($field->value)) {
		return $field->value;
	}
	return null;
};

$prepareString = static function ($value, string $default = '') {
	if ($value === null) {
		return $default;
	}
	if (is_array($value)) {
		$value = implode("\n", $value);
	}
	$value = trim((string) $value);
	return $value !== '' ? $value : $default;
};

$prepareParagraphs = static function ($value, array $default = []) use ($prepareString) {
	$text = $prepareString($value, '');
	if ($text === '') {
		return $default;
	}
	$chunks = preg_split('#\r?\n\s*\r?\n#', $text) ?: [];
	$result = [];
	foreach ($chunks as $chunk) {
		$chunk = trim($chunk);
		if ($chunk === '') {
			continue;
		}
		$result[] = $chunk;
	}
	return $result ?: $default;
};

$prepareRepeatable = static function ($value, array $default = []) {
	if ($value === null || $value === '') {
		return $default;
	}
	if (is_array($value)) {
		return $value ?: $default;
	}
	if (is_string($value)) {
		$decoded = json_decode($value, true);
		if (is_array($decoded)) {
			return $decoded ?: $default;
		}
	}
	return $default;
};

$resolveMedia = static function ($value, string $default = '') {
	$path = $value !== null ? trim((string) $value) : '';
	if ($path === '') {
		$path = $default;
	}
	if ($path === '') {
		return '';
	}
	$clean = HTMLHelper::cleanImageURL($path);
	$url = $clean->url ?? $path;
	if ($url !== '') {
		if (strpos($url, 'http://') === 0 || strpos($url, 'https://') === 0 || strpos($url, '//') === 0) {
			return $url;
		}
		return rtrim(Uri::root(true), '/') . '/' . ltrim($url, '/');
	}
	return $default;
};

$heroFallbackParagraphs = [
	Text::_('TPL_PRASTORA_ABOUT_HERO_PARAGRAPH1'),
	Text::_('TPL_PRASTORA_ABOUT_HERO_PARAGRAPH2'),
];

$hero = [
	'title' => $prepareString($getRaw($fieldMap, 'about_hero_title'), Text::_('TPL_PRASTORA_ABOUT_HERO_TITLE')),
	'paragraphs' => $prepareParagraphs($getRaw($fieldMap, 'about_hero_paragraphs'), $heroFallbackParagraphs),
	'cta_text' => $prepareString($getRaw($fieldMap, 'about_hero_cta_text'), Text::_('TPL_PRASTORA_ABOUT_CTA_SUPPORT')),
	'cta_link' => $prepareString($getRaw($fieldMap, 'about_hero_cta_link'), '#'),
	'image' => $resolveMedia($getRaw($fieldMap, 'about_hero_image'), $tplUri . '/assets/images/about-img.png'),
	'image_mobile' => null,
];

$hero['image_mobile'] = $resolveMedia($getRaw($fieldMap, 'about_hero_image_mobile'), $hero['image']);

$cardsFallback = [
	['number' => '01', 'title' => Text::_('TPL_PRASTORA_ABOUT_CARD1_TITLE'), 'text' => Text::_('TPL_PRASTORA_ABOUT_CARD1_TEXT')],
	['number' => '02', 'title' => Text::_('TPL_PRASTORA_ABOUT_CARD2_TITLE'), 'text' => Text::_('TPL_PRASTORA_ABOUT_CARD2_TEXT')],
	['number' => '03', 'title' => Text::_('TPL_PRASTORA_ABOUT_CARD3_TITLE'), 'text' => Text::_('TPL_PRASTORA_ABOUT_CARD3_TEXT')],
];

$cards = $prepareRepeatable($getRaw($fieldMap, 'about_cards'), $cardsFallback);

$quote = [
	'avatar' => $resolveMedia($getRaw($fieldMap, 'about_quote_avatar'), $tplUri . '/assets/images/about-avatar.png'),
	'icon' => $resolveMedia($getRaw($fieldMap, 'about_quote_icon'), $tplUri . '/assets/icons/quote-icon.svg'),
	'paragraphs' => $prepareParagraphs($getRaw($fieldMap, 'about_quote_text'), [
		Text::_('TPL_PRASTORA_ABOUT_QUOTE_LINE1'),
		Text::_('TPL_PRASTORA_ABOUT_QUOTE_LINE2'),
		Text::_('TPL_PRASTORA_ABOUT_QUOTE_LINE3'),
		Text::_('TPL_PRASTORA_ABOUT_QUOTE_LINE4'),
	]),
];

$motto = [
	'title' => $prepareString($getRaw($fieldMap, 'about_motto_title'), Text::_('TPL_PRASTORA_ABOUT_MOTTO_TITLE')),
	'paragraphs' => $prepareParagraphs($getRaw($fieldMap, 'about_motto_paragraphs'), [
		Text::_('TPL_PRASTORA_ABOUT_MOTTO_P1'),
		Text::_('TPL_PRASTORA_ABOUT_MOTTO_P2'),
	]),
	'highlight_icon' => $resolveMedia($getRaw($fieldMap, 'about_motto_highlight_icon'), $tplUri . '/assets/icons/quote-icon-c.svg'),
	'highlight_text' => $prepareString($getRaw($fieldMap, 'about_motto_highlight'), Text::_('TPL_PRASTORA_ABOUT_MOTTO_HIGHLIGHT')),
];

$teamFallback = [];
for ($i = 0; $i < 8; $i++) {
	$teamFallback[] = [
		'photo' => $tplUri . '/assets/images/kfc-founder.png',
		'name' => Text::_('TPL_PRASTORA_ABOUT_TEAM_NAME'),
		'role' => Text::_('TPL_PRASTORA_ABOUT_TEAM_ROLE'),
	];
}

$team = $prepareRepeatable($getRaw($fieldMap, 'about_team'), $teamFallback);

foreach ($team as &$member) {
	$member['photo'] = $resolveMedia($member['photo'] ?? '', $tplUri . '/assets/images/kfc-founder.png');
	$member['name'] = $prepareString($member['name'] ?? '', '');
	$member['role'] = $prepareString($member['role'] ?? '', '');
}
unset($member);

$partners = $prepareRepeatable($getRaw($fieldMap, 'about_partners'), [
	['logo' => $tplUri . '/assets/images/partner-1.png', 'link' => '#'],
	['logo' => $tplUri . '/assets/images/partner-2.png', 'link' => '#'],
	['logo' => $tplUri . '/assets/images/partner-1.png', 'link' => '#'],
	['logo' => $tplUri . '/assets/images/partner-2.png', 'link' => '#'],
]);

foreach ($partners as &$partner) {
	$partner['logo'] = $resolveMedia($partner['logo'] ?? '', '');
	$partner['link'] = $prepareString($partner['link'] ?? '', '#');
}
unset($partner);

?>
<div class="container about_header">
	<div class="about_header_desc">
		<div class="about_top">
			<?php if (!empty($hero['image_mobile'])) : ?>
				<img class="mob_img" src="<?php echo htmlspecialchars($hero['image_mobile'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
			<?php endif; ?>
			<h1><?php echo nl2br(htmlspecialchars($hero['title'], ENT_QUOTES, 'UTF-8')); ?></h1>
		</div>
		<div class="about_top_btm">
			<?php foreach ($hero['paragraphs'] as $paragraph) : ?>
				<p><?php echo nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')); ?></p>
			<?php endforeach; ?>
			<?php if (!empty($hero['cta_text'])) : ?>
				<a href="<?php echo htmlspecialchars($hero['cta_link'] ?: '#', ENT_QUOTES, 'UTF-8'); ?>"><button class="main_btn"><?php echo htmlspecialchars($hero['cta_text'], ENT_QUOTES, 'UTF-8'); ?></button></a>
			<?php endif; ?>
		</div>
	</div>
	<?php if (!empty($hero['image'])) : ?>
		<img class="desc_img" src="<?php echo htmlspecialchars($hero['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
	<?php endif; ?>
</div>

<div class="who_us container">
	<div class="block_title">
		<h2><?php echo Text::_('TPL_PRASTORA_ABOUT_SECTION_STORY'); ?></h2>
	</div>
	<div class="whous_cards">
		<?php foreach ($cards as $card) :
			$number = htmlspecialchars($prepareString($card['number'] ?? '', ''), ENT_QUOTES, 'UTF-8');
			$title = htmlspecialchars($prepareString($card['title'] ?? '', ''), ENT_QUOTES, 'UTF-8');
			$text = htmlspecialchars($prepareString($card['text'] ?? '', ''), ENT_QUOTES, 'UTF-8');
		?>
		<div class="whous_card">
			<img src="<?php echo $tplUri; ?>/assets/images/whowe-bg-line.svg" alt="" />
			<?php if ($number !== '') : ?><h4><?php echo $number; ?></h4><?php endif; ?>
			<div class="whous_card_desc">
				<?php if ($title !== '') : ?><p><?php echo nl2br($title); ?></p><?php endif; ?>
				<?php if ($text !== '') : ?><span><?php echo nl2br($text); ?></span><?php endif; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
</div>

<div class="quote_block container">
	<?php if (!empty($quote['avatar'])) : ?>
	<div class="quote_block_author">
		<img src="<?php echo htmlspecialchars($quote['avatar'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
	</div>
	<?php endif; ?>
	<div class="quote_block_text">
		<?php if (!empty($quote['icon'])) : ?><img src="<?php echo htmlspecialchars($quote['icon'], ENT_QUOTES, 'UTF-8'); ?>" alt="" /><?php endif; ?>
		<?php foreach ($quote['paragraphs'] as $paragraph) : ?>
			<p><?php echo nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')); ?></p>
		<?php endforeach; ?>
	</div>
</div>

<div class="about_moto container">
	<h2><?php echo nl2br(htmlspecialchars($motto['title'], ENT_QUOTES, 'UTF-8')); ?></h2>
	<div class="about_moto_desc">
		<?php foreach ($motto['paragraphs'] as $paragraph) : ?>
			<p><?php echo nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')); ?></p>
		<?php endforeach; ?>
		<?php if (!empty($motto['highlight_text'])) : ?>
		<div>
			<?php if (!empty($motto['highlight_icon'])) : ?><img src="<?php echo htmlspecialchars($motto['highlight_icon'], ENT_QUOTES, 'UTF-8'); ?>" alt="" /><?php endif; ?>
			<span><?php echo nl2br(htmlspecialchars($motto['highlight_text'], ENT_QUOTES, 'UTF-8')); ?></span>
		</div>
		<?php endif; ?>
	</div>
</div>

<?php /* command_workers section temporarily disabled */ if (false && !empty($team)) : ?>
<div class="command_workers">
	<?php foreach ($team as $member) :
		$photo = $member['photo'] ?? '';
		$name = $prepareString($member['name'] ?? '', '');
		$role = $prepareString($member['role'] ?? '', '');
	?>
	<div class="command_worker">
		<?php if ($photo !== '') : ?><img src="<?php echo htmlspecialchars($photo, ENT_QUOTES, 'UTF-8'); ?>" alt="" /><?php endif; ?>
		<div>
			<?php if ($name !== '') : ?><p><?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?></p><?php endif; ?>
			<?php if ($role !== '') : ?><span><?php echo nl2br(htmlspecialchars($role, ENT_QUOTES, 'UTF-8')); ?></span><?php endif; ?>
		</div>
	</div>
	<?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!empty($partners)) : ?>
<section class="partners_block container">
	<div class="block_title"><h2><?php echo Text::_('TPL_PRASTORA_ABOUT_SECTION_PARTNERS'); ?></h2></div>
	<div class="partners_list">
		<?php foreach ($partners as $partner) :
			if (empty($partner['logo'])) {
				continue;
			}
			$link = $partner['link'] ?? '#';
		?>
		<a href="<?php echo htmlspecialchars($link ?: '#', ENT_QUOTES, 'UTF-8'); ?>">
			<img src="<?php echo htmlspecialchars($partner['logo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
		</a>
		<?php endforeach; ?>
	</div>
</section>
<?php endif; ?>
