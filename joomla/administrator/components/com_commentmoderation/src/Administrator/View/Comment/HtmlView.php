<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

namespace Joomla\Component\Commentmoderation\Administrator\View\Comment;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;
    protected $state;

    public function display($tpl = null)
    {
        $this->form  = $this->get('Form');
        $this->item  = $this->get('Item');
        $this->state = $this->get('State');

        if (count($errors = $this->get('Errors'))) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        return parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $isNew = (int) ($this->item->id ?? 0) === 0;

        $title = $isNew
            ? Text::_('COM_COMMENTMODERATION_EDIT_COMMENT')
            : Text::_('COM_COMMENTMODERATION_EDIT_COMMENT');

        ToolbarHelper::title($title, 'comments');
        ToolbarHelper::apply('comment.apply');
        ToolbarHelper::save('comment.save');
        ToolbarHelper::cancel('comment.cancel');
    }
}


