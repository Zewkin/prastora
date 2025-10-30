<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

$item = $this->item;
$app = Factory::getApplication();

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
	$paragraphs = preg_split('#\r?\n\s*\r?\n#', $text) ?: [];
	$result = [];
	foreach ($paragraphs as $paragraph) {
		$paragraph = trim($paragraph);
		if ($paragraph === '') {
			continue;
		}
		$result[] = $paragraph;
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

$updatedFallback = 'Дата апошняга абнаўлення: 16 ліпеня 2025';
$introFallback = [
	'Мы ў «Прасторы» ставімся да вашай прыватнасці з павагай і адказнасцю.',
	'Гэтая палітыка тлумачыць, якія даныя мы збіраем, як іх выкарыстоўваем, і якія правы маеце вы як карыстальнік платформы.',
];

$sectionsFallback = [
	[
		'title' => '1. Мінімум дадзеных — максімум павагі',
		'body' => [
			'Вы можаце карыстацца платформай без рэгістрацыі і ідэнтыфікацыі. Мы не патрабуем вашых асабістых дадзеных для ўдзелу ў абмеркаваннях або галасаваннях.',
		],
	],
	[
		'title' => '2. E-mail — толькі па жаданні',
		'body' => [
			'Вы можаце дадаткова пакінуць свой e-mail, каб атрымліваць апавяшчэнні пра адказы на вашы каментары. Гэта неабавязкова, і e-mail не бачны іншым карыстальнікам.',
			'Мы не выкарыстоўваем ваш адрас электроннай пошты ў маркетынгавых мэтах і не перадаем яго трэцім асобам.',
			'Мы можам збіраць ананімную статыстыку пра выкарыстанне сайта (напрыклад, колькасць наведванняў, клікі), каб палепшыць працу платформы. Гэта не ўключае персанальных дадзеных.',
		],
	],
	[
		'title' => '4. Файлы cookie',
		'body' => [
			'Платформа можа выкарыстоўваць тэхнічныя cookie-файлы для правільнай працы асобных функцый (напрыклад, захаванне мовы інтэрфейсу). Вы можаце абмежаваць іх праз налады свайго браўзера.',
		],
	],
];

$updatedLine = $prepareString($getRaw($fieldMap, 'privacy_updated'), $updatedFallback);
$introParagraphs = $prepareParagraphs($getRaw($fieldMap, 'privacy_intro'), $introFallback);
$sectionsRaw = $prepareRepeatable($getRaw($fieldMap, 'privacy_sections'), $sectionsFallback);

$sections = [];
foreach ($sectionsRaw as $entry) {
	$title = $prepareString($entry['title'] ?? '', '');
	$body = $entry['body'] ?? '';
	if (!is_array($body)) {
		$body = $prepareParagraphs($body, []);
	} else {
		$tmp = [];
		foreach ($body as $paragraph) {
			$paragraph = trim((string) $paragraph);
			if ($paragraph === '') {
				continue;
			}
			$tmp[] = $paragraph;
		}
		$body = $tmp;
	}
	if ($title === '' && empty($body)) {
		continue;
	}
	$sections[] = [
		'title' => $title,
		'body' => $body,
	];
}

if (empty($sections)) {
	$sections = $sectionsFallback;
}

?>
<div class="colored_bg">
	<div class="colored_w container">
		<div class="policy_container">
			<div class="page_title">
				<h2><?php echo htmlspecialchars($item->title ?? 'Палітыка прыватнасці', ENT_QUOTES, 'UTF-8'); ?></h2>
				<?php if ($updatedLine !== '') : ?>
					<p><?php echo htmlspecialchars($updatedLine, ENT_QUOTES, 'UTF-8'); ?></p>
				<?php endif; ?>
			</div>

			<div class="policy_content">
				<?php foreach ($introParagraphs as $paragraph) : ?>
					<p><?php echo nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')); ?></p>
				<?php endforeach; ?>
				<?php foreach ($sections as $section) : ?>
					<?php if (!empty($section['title'])) : ?>
						<h4><?php echo htmlspecialchars($section['title'], ENT_QUOTES, 'UTF-8'); ?></h4>
					<?php endif; ?>
					<?php foreach ($section['body'] as $paragraph) : ?>
						<p><?php echo nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')); ?></p>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</div>
		</div>
	</div>
</div>
