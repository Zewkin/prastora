<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

namespace Joomla\Component\Commentmoderation\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Model\AdminModel;

class CommentModel extends AdminModel
{
    protected $text_prefix = 'COM_COMMENTMODERATION_COMMENT';

    public function getTable($type = 'Comment', $prefix = 'Administrator', $config = array())
    {
        return parent::getTable($type, $prefix, $config);
    }

    public function getForm($data = array(), $loadData = true)
    {
        $form = $this->loadForm('com_commentmoderation.comment', 'comment', ['control' => 'jform', 'load_data' => $loadData]);

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    public function save($data)
    {
        $data['modified'] = Factory::getDate()->toSql();

        $nullableIntegerFields = ['parent_id', 'anchor_start', 'anchor_end'];
        foreach ($nullableIntegerFields as $field) {
            if (array_key_exists($field, $data) && ($data[$field] === '' || $data[$field] === null)) {
                $data[$field] = null;
            }
        }

        $nullableTextFields = ['anchor_exact', 'anchor_prefix', 'anchor_suffix'];
        foreach ($nullableTextFields as $field) {
            if (array_key_exists($field, $data) && $data[$field] === '') {
                $data[$field] = null;
            }
        }

        if (parent::save($data)) {
            return true;
        }

        $tableError = $this->getTable()->getError();
        $modelError = $this->getError();
        $message = $tableError ?: $modelError ?: Text::_('COM_COMMENTMODERATION_COMMENT_SAVE_FAILED');

        $this->setError($message);

        return false;
    }

    protected function loadFormData()
    {
        $app = Factory::getApplication();
        $data = $app->getUserState('com_commentmoderation.edit.comment.data', []);

        if (empty($data)) {
            $data = $this->getItem();
        }

        return $data;
    }
}


