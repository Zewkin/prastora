<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

namespace Joomla\Component\Commentmoderation\Administrator\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ListModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Utilities\ArrayHelper;

class CommentsModel extends ListModel
{
    public const STATUS_PENDING = 0;
    public const STATUS_APPROVED = 1;
    public const STATUS_REJECTED = 2;

    protected $filter_fields = [
        'id', 'c.id',
        'article_id', 'c.article_id',
        'created', 'c.created',
        'status', 'c.status',
    ];

    protected $lastDeleteCount = 0;

    protected function populateState($ordering = 'c.created', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true);

        $query->select([
                $db->quoteName('c.id'),
                $db->quoteName('c.article_id'),
                $db->quoteName('c.author_name'),
                $db->quoteName('c.author_email'),
                $db->quoteName('c.body'),
                $db->quoteName('c.status'),
                $db->quoteName('c.created'),
                $db->quoteName('c.modified'),
                $db->quoteName('c.section_id'),
                $db->quoteName('c.parent_id'),
                $db->quoteName('c.anchor_start'),
                $db->quoteName('c.anchor_end'),
                $db->quoteName('c.anchor_exact'),
                $db->quoteName('c.anchor_prefix'),
                $db->quoteName('c.anchor_suffix'),
                $db->quoteName('a.title', 'article_title')
            ])
            ->from($db->quoteName('#__constitution_comments', 'c'))
            ->leftJoin(
                $db->quoteName('#__content', 'a') . ' ON ' . $db->quoteName('a.id') . ' = ' . $db->quoteName('c.article_id')
            );

        $orderCol = $this->state->get('list.ordering', 'c.created');
        $orderDir = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol) . ' ' . $db->escape($orderDir));

        return $query;
    }

    public function approve(array $pks): int
    {
        return $this->setStatus($pks, self::STATUS_APPROVED);
    }

    public function reject(array $pks): int
    {
        return $this->setStatus($pks, self::STATUS_REJECTED);
    }

    public function setPending(array $pks): int
    {
        return $this->setStatus($pks, self::STATUS_PENDING);
    }

    public function delete(&$pks)
    {
        $pks = ArrayHelper::toInteger((array) $pks);

        $this->lastDeleteCount = 0;

        if (empty($pks)) {
            return true;
        }

        $db = $this->getDatabase();
        $deleted = 0;

        foreach ($pks as $pk) {
            if ($this->deleteCommentCascade($db, (int) $pk)) {
                $deleted++;
            }
        }

        $this->lastDeleteCount = $deleted;

        return true;
    }

    public function getDeletedCount(): int
    {
        return $this->lastDeleteCount;
    }

    protected function setStatus(array $pks, int $status): int
    {
        $pks = ArrayHelper::toInteger(array_filter($pks));

        if (empty($pks)) {
            return 0;
        }

        $db = $this->getDatabase();
        $now = Factory::getDate()->toSql();

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__constitution_comments'))
            ->set($db->quoteName('status') . ' = :status')
            ->set($db->quoteName('modified') . ' = :modified')
            ->where($db->quoteName('id') . ' IN (' . implode(',', $pks) . ')')
            ->bind(':status', $status, ParameterType::INTEGER)
            ->bind(':modified', $now, ParameterType::STRING);

        $db->setQuery($query)->execute();

        return (int) $db->getAffectedRows();
    }

    protected function deleteCommentCascade(DatabaseInterface $db, int $commentId): bool
    {
        $commentId = (int) $commentId;

        if ($commentId <= 0) {
            return false;
        }

        $childIds = $db->setQuery(
            $db->getQuery(true)
                ->select($db->quoteName('id'))
                ->from($db->quoteName('#__constitution_comments'))
                ->where($db->quoteName('parent_id') . ' = :cid')
                ->bind(':cid', $commentId, ParameterType::INTEGER)
        )->loadColumn();

        foreach ($childIds as $childId) {
            $this->deleteCommentCascade($db, (int) $childId);
        }

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__constitution_comment_reaction_logs'))
                ->where($db->quoteName('comment_id') . ' = :cid_logs')
                ->bind(':cid_logs', $commentId, ParameterType::INTEGER)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__constitution_comment_reactions'))
                ->where($db->quoteName('comment_id') . ' = :cid_react')
                ->bind(':cid_react', $commentId, ParameterType::INTEGER)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__constitution_comments'))
                ->where($db->quoteName('id') . ' = :cid_comment')
                ->bind(':cid_comment', $commentId, ParameterType::INTEGER)
        )->execute();

        return true;
    }
}

