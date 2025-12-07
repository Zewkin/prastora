<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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

$itemsFallback = [
	[
		'question' => Text::_('TPL_PRASTORA_FAQ_Q1'),
		'answer' => [
			Text::_('TPL_PRASTORA_FAQ_A1'),
		],
	],
	[
		'question' => Text::_('TPL_PRASTORA_FAQ_Q2'),
		'answer' => [
			Text::_('TPL_PRASTORA_FAQ_A2'),
		],
	],
	[
		'question' => Text::_('TPL_PRASTORA_FAQ_Q3'),
		'answer' => [
			Text::_('TPL_PRASTORA_FAQ_A3'),
		],
	],
	[
		'question' => Text::_('TPL_PRASTORA_FAQ_Q4'),
		'answer' => [
			Text::_('TPL_PRASTORA_FAQ_A4'),
		],
	],
	[
		'question' => Text::_('TPL_PRASTORA_FAQ_Q5'),
		'answer' => [
			Text::_('TPL_PRASTORA_FAQ_A5'),
		],
	],
	[
		'question' => Text::_('TPL_PRASTORA_FAQ_Q6'),
		'answer' => [
			Text::_('TPL_PRASTORA_FAQ_A6'),
		],
	],
	[
		'question' => Text::_('TPL_PRASTORA_FAQ_Q7'),
		'answer' => [
			Text::_('TPL_PRASTORA_FAQ_A7'),
		],
	],
];

$itemsRaw = $prepareRepeatable($getRaw($fieldMap, 'faq_items'), $itemsFallback);
$faqItems = [];

foreach ($itemsRaw as $entry) {
	$question = $prepareString($entry['question'] ?? '', '');
	if ($question === '') {
		continue;
	}
	$paragraphs = $entry['answer'] ?? '';
	if (!is_array($paragraphs)) {
		$paragraphs = $prepareParagraphs($paragraphs, []);
	} else {
		$tmp = [];
		foreach ($paragraphs as $paragraph) {
			$paragraph = trim((string) $paragraph);
			if ($paragraph === '') {
				continue;
			}
			$tmp[] = $paragraph;
		}
		$paragraphs = $tmp;
	}
	$faqItems[] = [
		'question' => $question,
		'answer' => $paragraphs ?: [Text::_('TPL_PRASTORA_FAQ_DEFAULT_ANSWER')],
	];
}

if (empty($faqItems)) {
	$faqItems = $itemsFallback;
}

$footerText = $prepareString(
	$getRaw($fieldMap, 'faq_footer_text'),
	Text::_('TPL_PRASTORA_FAQ_FOOTER')
);

?>
<div class="faq_wrapper container">
	<div class="faq_list">
		<?php foreach ($faqItems as $index => $faq) :
			$id = 'faq-item-' . ($index + 1);
		?>
		<div class="faq_item" data-faq-item>
			<div class="faq_item_title" role="button" tabindex="0" aria-expanded="false" aria-controls="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
				<p><?php echo htmlspecialchars($faq['question'], ENT_QUOTES, 'UTF-8'); ?></p>
				<div class="faq_item_icon">
					<img src="<?php echo $tplUri; ?>/assets/icons/plus.svg" alt="" />
				</div>
			</div>
			<div class="faq_item_desciprition" id="<?php echo htmlspecialchars($id, ENT_QUOTES, 'UTF-8'); ?>">
				<?php foreach ($faq['answer'] as $paragraph) : ?>
					<p><?php echo nl2br(htmlspecialchars($paragraph, ENT_QUOTES, 'UTF-8')); ?></p>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
	<div class="faq_btm">
		<p><?php echo nl2br(htmlspecialchars($footerText, ENT_QUOTES, 'UTF-8')); ?></p>
	</div>
</div>
