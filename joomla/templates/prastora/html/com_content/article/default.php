<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

$app     = Factory::getApplication();
$template = $app->getTemplate();
$tplUri  = rtrim(Uri::root(true), '/') . '/templates/' . $template;
$item = $this->item;
// Если материал относится к Конституции (по alias или категории), используем альтернативный макет
if (isset($item->alias) && strtolower((string) $item->alias) === 'constitution') {
    echo $this->loadTemplate('constitution');
    return;
}
if (isset($item->catid)) {
    $categoryTitle = (string) ($item->category_title ?? '');
    if (stripos($categoryTitle, 'Канстытуц') !== false || stripos($categoryTitle, 'Конституц') !== false) {
        echo $this->loadTemplate('constitution');
        return;
    }
}
$alias = isset($item->alias) ? strtolower((string) $item->alias) : '';
$specialLayouts = [
    'about-us' => 'about',
    'faq' => 'faq',
    'privacy-policy' => 'privacy',
];

if ($alias && isset($specialLayouts[$alias])) {
    echo $this->loadTemplate($specialLayouts[$alias]);
    return;
}
$images = json_decode($item->images ?? '{}');
?>
<div class="projects_page container colored_bg">
	<div class="projects_wrapper blank_page">
		<div class="news_content">
			<div class="news_title">
				<h2><?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?></h2>
				<div class="news_descriptions">
					<?php if (!empty($item->category_title)) : ?>
					<div class="news_description_hash"><span><?php echo htmlspecialchars($item->category_title, ENT_QUOTES, 'UTF-8'); ?></span></div>
					<div class="dot"></div>
					<?php endif; ?>
					<p><?php echo htmlspecialchars($item->author ?? $item->created_by_alias ?? '', ENT_QUOTES, 'UTF-8'); ?></p>
					<div class="dot"></div>
					<p><?php echo JHtml::_('date', $item->publish_up ?? $item->created, Text::_('DATE_FORMAT_LC3')); ?></p>
				</div>
			</div>

			<div class="news_text">
				<?php echo $item->text; ?>
			</div>

			<?php
				$experts = [];
				try {
					$fields = FieldsHelper::getFields('com_content.article', $item, true);
					foreach ($fields as $field) {
						if ($field->name === 'experts' && !empty($field->rawvalue)) {
							$rows = json_decode(is_string($field->rawvalue) ? $field->rawvalue : json_encode($field->rawvalue), true) ?: [];
							foreach ($rows as $row) {
								$experts[] = [
									'name' => $row['name'] ?? '',
									'bio'  => $row['bio'] ?? '',
									'photo'=> $row['photo'] ?? ''
								];
							}
						}
					}
				} catch (\Throwable $e) {}
			?>

			<?php if (!empty($experts)) : ?>
				<div class="news_author">
					<h3><?php echo count($experts) > 1 ? 'Эксперты:' : 'Эксперт:'; ?></h3>
					<?php foreach ($experts as $expert) : ?>
						<div class="news_author_cont">
							<?php if (!empty($expert['photo'])) : ?>
								<img src="<?php echo htmlspecialchars($expert['photo'], ENT_QUOTES, 'UTF-8'); ?>" alt="" />
							<?php endif; ?>
							<div>
								<?php if (!empty($expert['name'])) : ?>
									<p><?php echo htmlspecialchars($expert['name'], ENT_QUOTES, 'UTF-8'); ?></p>
								<?php endif; ?>
								<?php if (!empty($expert['bio'])) : ?>
									<span><?php echo htmlspecialchars($expert['bio'], ENT_QUOTES, 'UTF-8'); ?></span>
								<?php endif; ?>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>
</div>
