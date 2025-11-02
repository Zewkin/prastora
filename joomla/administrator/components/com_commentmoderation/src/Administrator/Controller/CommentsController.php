<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

namespace Joomla\Component\Commentmoderation\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\Utilities\ArrayHelper;

class CommentsController extends AdminController
{
    protected $text_prefix = 'COM_COMMENTMODERATION_COMMENTS';

    public function getModel($name = 'Comments', $prefix = 'Administrator', $config = array('ignore_request' => true))
    {
        return parent::getModel($name, $prefix, $config);
    }

    public function approve(): void
    {
        $this->checkToken();

        $this->changeStatus('approve');
    }

    public function reject(): void
    {
        $this->checkToken();

        $this->changeStatus('reject');
    }

    public function delete()
    {
        $this->checkToken();

        $pks = (array) $this->input->post->get('cid', [], 'array');
        $pks = ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            $this->setMessage(Text::_('COM_COMMENTMODERATION_COMMENTS_NO_ITEM_SELECTED'), 'warning');
            $this->setRedirect($this->getRedirectToListRoute());

            return;
        }

        $model = $this->getModel();
        $model->delete($pks);
        $count = $model->getDeletedCount();

        $this->setMessage(Text::plural('COM_COMMENTMODERATION_COMMENTS_N_ITEMS_DELETED', $count), $count ? 'message' : 'warning');
        $this->setRedirect($this->getRedirectToListRoute());
    }

    protected function changeStatus(string $task): void
    {
        $pks = (array) $this->input->post->get('cid', [], 'array');
        $pks = ArrayHelper::toInteger($pks);

        if (empty($pks)) {
            $this->setMessage(Text::_('COM_COMMENTMODERATION_COMMENTS_NO_ITEM_SELECTED'), 'warning');
            $this->setRedirect($this->getRedirectToListRoute());

            return;
        }

        $model = $this->getModel();

        if ($task === 'approve') {
            $count = $model->approve($pks);
            $messageKey = 'COM_COMMENTMODERATION_COMMENTS_N_ITEMS_APPROVED';
        } elseif ($task === 'reject') {
            $count = $model->reject($pks);
            $messageKey = 'COM_COMMENTMODERATION_COMMENTS_N_ITEMS_REJECTED';
        } else {
            $count = 0;
            $messageKey = 'COM_COMMENTMODERATION_COMMENTS_NO_ITEM_SELECTED';
        }

        $this->setMessage(Text::plural($messageKey, $count), $count ? 'message' : 'warning');
        $this->setRedirect($this->getRedirectToListRoute());
    }

    protected function getRedirectToListRoute(): string
    {
        return Route::_('index.php?option=com_commentmoderation&view=comments', false);
    }
}

