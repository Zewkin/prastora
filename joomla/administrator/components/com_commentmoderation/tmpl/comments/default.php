<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

$statuses = [
    0 => Text::_('COM_COMMENTMODERATION_STATUS_PENDING'),
    1 => Text::_('COM_COMMENTMODERATION_STATUS_PUBLISHED'),
    2 => Text::_('COM_COMMENTMODERATION_STATUS_REJECTED'),
];

$statusClasses = [
    0 => 'badge bg-warning text-dark',
    1 => 'badge bg-success',
    2 => 'badge bg-secondary',
];

$listOrder = $this->state->get('list.ordering', 'c.created');
$listDirn  = $this->state->get('list.direction', 'DESC');
?>

<form action="<?php echo Route::_('index.php?option=com_commentmoderation&view=comments'); ?>" method="post" id="adminForm" name="adminForm">
    <div class="container-fluid">
        <div class="row mb-3">
            <div class="col-lg-12">
                <?php if ($this->premoderationEnabled) : ?>
                    <div class="alert alert-warning">
                        <span class="icon-exclamation-circle" aria-hidden="true"></span>
                        <?php echo Text::_('COM_COMMENTMODERATION_PREMODERATION_ENABLED'); ?>
                    </div>
                <?php else : ?>
                    <div class="alert alert-info">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <?php echo Text::_('COM_COMMENTMODERATION_PREMODERATION_DISABLED'); ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (empty($this->items)) : ?>
            <div class="alert alert-info">
                <?php echo Text::_('COM_COMMENTMODERATION_EMPTY'); ?>
            </div>
        <?php else : ?>
            <div class="table-responsive">
                <table class="table table-striped table-hover" id="commentsList">
                    <thead>
                        <tr>
                            <th width="1%" class="text-center">
                                <?php echo HTMLHelper::_('grid.checkall'); ?>
                            </th>
                            <th scope="col" class="w-5">
                                <?php echo Text::_('COM_COMMENTMODERATION_TABLE_HEADER_ID'); ?>
                            </th>
                            <th scope="col">
                                <?php echo Text::_('COM_COMMENTMODERATION_TABLE_HEADER_ARTICLE'); ?>
                            </th>
                            <th scope="col">
                                <?php echo Text::_('COM_COMMENTMODERATION_TABLE_HEADER_AUTHOR'); ?>
                            </th>
                            <th scope="col" class="w-15">
                                <?php echo Text::_('COM_COMMENTMODERATION_TABLE_HEADER_STATUS'); ?>
                            </th>
                            <th scope="col" class="w-15">
                                <?php echo HTMLHelper::_('grid.sort', 'COM_COMMENTMODERATION_TABLE_HEADER_CREATED', 'c.created', $listDirn, $listOrder); ?>
                            </th>
                            <th scope="col">
                                <?php echo Text::_('COM_COMMENTMODERATION_TABLE_HEADER_SNIPPET'); ?>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($this->items as $i => $item) : ?>
                            <?php
                            $status = (int) $item->status;
                            $statusText = $statuses[$status] ?? Text::_('COM_COMMENTMODERATION_STATUS_UNKNOWN');
                            $statusClass = $statusClasses[$status] ?? 'badge bg-secondary';
                            $articleLink = $item->article_id
                                ? Route::_('index.php?option=com_content&task=article.edit&id=' . (int) $item->article_id)
                                : null;
                            $editLink = Route::_('index.php?option=com_commentmoderation&task=comment.edit&id=' . (int) $item->id);
                            ?>
                            <tr>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>
                                <td>
                                    <a href="<?php echo $editLink; ?>">
                                        <?php echo (int) $item->id; ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($articleLink) : ?>
                                        <a href="<?php echo $articleLink; ?>">
                                            <?php echo $this->escape($item->article_title ?: Text::_('COM_COMMENTMODERATION_UNKNOWN_ARTICLE')); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo Text::_('COM_COMMENTMODERATION_UNKNOWN_ARTICLE'); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="<?php echo $editLink; ?>">
                                        <?php echo $this->escape($item->author_name ?: Text::_('COM_COMMENTMODERATION_UNKNOWN_AUTHOR')); ?>
                                    </a>
                                    <?php if (!empty($item->author_email)) : ?>
                                        <div class="text-muted small">
                                            <?php echo $this->escape($item->author_email); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?php echo $statusClass; ?>">
                                        <?php echo $statusText; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php echo HTMLHelper::_('date', $item->created, Text::_('DATE_FORMAT_LC2')); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape(HTMLHelper::_('string.truncate', (string) $item->body, 160, true)); ?>
                                    <?php if (!empty($item->anchor_exact)) : ?>
                                        <div class="text-muted small">
                                            <?php echo Text::sprintf('COM_COMMENTMODERATION_ANCHOR_CONTEXT', $this->escape($item->anchor_exact)); ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php echo $this->pagination->getListFooter(); ?>
        <?php endif; ?>
    </div>

    <input type="hidden" name="task" value="" />
    <input type="hidden" name="boxchecked" value="0" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
