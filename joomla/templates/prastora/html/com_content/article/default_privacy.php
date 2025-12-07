<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
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

$updatedFallback = Text::_('TPL_PRASTORA_PRIVACY_LAST_UPDATED');
$introFallback = [
	Text::_('TPL_PRASTORA_PRIVACY_INTRO1'),
	Text::_('TPL_PRASTORA_PRIVACY_INTRO2'),
];

$sectionsFallback = [
	[
		'title' => Text::_('TPL_PRASTORA_PRIVACY_ITEM1_TITLE'),
		'body' => [
			Text::_('TPL_PRASTORA_PRIVACY_ITEM1_TEXT'),
		],
	],
	[
		'title' => Text::_('TPL_PRASTORA_PRIVACY_ITEM2_TITLE'),
		'body' => [
			Text::_('TPL_PRASTORA_PRIVACY_ITEM2_TEXT1'),
			Text::_('TPL_PRASTORA_PRIVACY_ITEM2_TEXT2'),
			Text::_('TPL_PRASTORA_PRIVACY_ITEM3_TEXT'),
		],
	],
	[
		'title' => Text::_('TPL_PRASTORA_PRIVACY_ITEM4_TITLE'),
		'body' => [
			Text::_('TPL_PRASTORA_PRIVACY_ITEM4_TEXT'),
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
				<h2><?php echo htmlspecialchars($item->title ?? Text::_('TPL_PRASTORA_PRIVACY_FALLBACK_TITLE'), ENT_QUOTES, 'UTF-8'); ?></h2>
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
