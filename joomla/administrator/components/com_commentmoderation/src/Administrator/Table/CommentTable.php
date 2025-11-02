<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

namespace Joomla\Component\Commentmoderation\Administrator\Table;

\defined('_JEXEC') or die;

use Joomla\CMS\Language\Text;
use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class CommentTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__constitution_comments', 'id', $db);
    }

    public function check()
    {
        if (trim((string) $this->body) === '') {
            $this->setError(Text::_('COM_COMMENTMODERATION_FIELD_BODY'));

            return false;
        }

        if (!empty($this->author_email) && !filter_var($this->author_email, FILTER_VALIDATE_EMAIL)) {
            $this->setError(Text::_('COM_COMMENTMODERATION_FIELD_AUTHOR_EMAIL'));

            return false;
        }

        return parent::check();
    }
}


