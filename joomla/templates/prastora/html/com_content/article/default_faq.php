<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
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
		'question' => 'Як я магу ўдзельнічаць у абмеркаванні?',
		'answer' => [
			'Проста выдзеліце частку тэксту і націсніце на іконку каментарыя з “+”, каб пакінуць каментар або прапанову.',
		],
	],
	[
		'question' => 'Ці трэба рэгістравацца, каб пакінуць каментар?',
		'answer' => [
			'Не. Вы можаце ўдзельнічаць ананімна. Калі хочаце — можна пакінуць email для апавяшчэнняў, але гэта неабавязкова.',
		],
	],
	[
		'question' => 'Мой каментар не адлюстроўваецца — што рабіць?',
		'answer' => [
			'Калі каментар яшчэ не з’явіўся, магчыма, яго правярае мадэратар. Дайце нам крыху часу — і ён з’явіцца на старонцы.',
		],
	],
	[
		'question' => 'Ці магу я прапаноўваць свае тэмы або ідэі?',
		'answer' => [
			'Так, вы можаце пакінуць прапанову да любога раздзела, альбо напісаць нам, калі тэма патрабуе асобнай дыскусіі.',
		],
	],
	[
		'question' => 'Як вы забяспечваеце бяспеку ўдзельнікаў?',
		'answer' => [
			'Мы не патрабуем рэгістрацыі або персанальных дадзеных. Усе каментарыі мадэруюцца, каб прадухіліць пераслед і хейт-спіч.',
		],
	],
	[
		'question' => 'Я магу даведацца, што здарылася з маім каментаром?',
		'answer' => [
			'Так, калі вы пакінулі email, мы паведамім пра статус каментара. Без email вы можаце сачыць за адказамі на старонцы.',
		],
	],
	[
		'question' => 'Ці сапраўды мой каментар можа нешта змяніць?',
		'answer' => [
			'Так, кожны каментар мае значэнне. Усе прапановы збіраюцца, аналізуюцца і перадаюцца экспертам, якія дапрацоўваюць дакументы.',
			'Мы фіксуем падтрымкі, частату і аргументацыю. Найбольш важныя ідэі ўключаем у новыя версіі і перад толькі шырокай аўдыторыі.',
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
		'answer' => $paragraphs ?: ['Адказ падрыхтоўваецца.'],
	];
}

if (empty($faqItems)) {
	$faqItems = $itemsFallback;
}

$footerText = $prepareString(
	$getRaw($fieldMap, 'faq_footer_text'),
	'Калі вы не знайшлі патрэбнай інфармацыі – напішыце нам. Мы заўсёды на сувязі і гатовыя дапамагчы.'
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
