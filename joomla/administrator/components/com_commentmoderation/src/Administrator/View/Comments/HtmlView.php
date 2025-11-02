<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

namespace Joomla\Component\Commentmoderation\Administrator\View\Comments;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;
    protected $params;
    protected $premoderationEnabled;

    public function display($tpl = null)
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->params = ComponentHelper::getParams('com_commentmoderation');
        $this->premoderationEnabled = (int) $this->params->get('premoderation_enabled', 1);

        $this->addToolbar();

        return parent::display($tpl);
    }

    protected function addToolbar()
    {
        ToolbarHelper::title(Text::_('COM_COMMENTMODERATION_TITLE'), 'comments');
        ToolbarHelper::custom('comments.approve', 'publish.png', 'publish_f2.png', Text::_('COM_COMMENTMODERATION_TOOLBAR_APPROVE'), true);
        ToolbarHelper::custom('comments.reject', 'unpublish.png', 'unpublish_f2.png', Text::_('COM_COMMENTMODERATION_TOOLBAR_REJECT'), true);
        ToolbarHelper::editList('comment.edit', Text::_('COM_COMMENTMODERATION_TOOLBAR_EDIT'));
        ToolbarHelper::deleteList(Text::_('COM_COMMENTMODERATION_CONFIRM_DELETE'), 'comments.delete');
        ToolbarHelper::divider();
        ToolbarHelper::preferences('com_commentmoderation');
    }
}

