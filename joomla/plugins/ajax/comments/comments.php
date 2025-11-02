<?php
defined('_JEXEC') or die;

use Joomla\CMS\Captcha\Captcha;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filter\InputFilter;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\CMS\Plugin\PluginHelper;

class PlgAjaxComments extends CMSPlugin
{
    private const STATUS_PENDING = 0;
    private const STATUS_APPROVED = 1;
    private const STATUS_REJECTED = 2;

    private function isPremoderationEnabled(): bool
    {
        $componentParams = ComponentHelper::getParams('com_commentmoderation');
        if ($componentParams !== null) {
            $value = $componentParams->get('premoderation_enabled', null);
            if ($value !== null && $value !== '') {
                return (bool) $value;
            }
        }

        return (string) $this->params->get('moderation_mode', 'premoderation') === 'premoderation';
    }

    private function isCaptchaRequired(): bool
    {
        return (bool) $this->params->get('captcha_enabled', 0);
    }

    private function getCaptchaPlugin(): string
    {
        return (string) $this->params->get('captcha_plugin', '');
    }

    private function canModerate(): bool
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();

        if (!$user || $user->guest) {
            return false;
        }

        return $user->authorise('core.manage', 'com_content') || $user->authorise('core.edit', 'com_content');
    }

    public function onAjaxComments()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $method = strtoupper($input->getMethod());

        if ($method !== 'GET') {
            if (!Session::checkToken('request')) {
                return $this->json(['ok' => false, 'error' => 'invalid_token']);
            }
        }

        $task = $input->getCmd('task', $method === 'POST' ? 'create' : 'list');
        $db   = Factory::getContainer()->get('DatabaseDriver');

        $this->ensureTables($db);

        if ($task === 'list') {
            return $this->handleList($input, $db);
        }

        if ($task === 'create') {
            return $this->handleCreate($input, $db);
        }

        if ($task === 'react') {
            return $this->handleReact($input, $db);
        }

        if ($task === 'moderation_list') {
            return $this->handleModerationList($input, $db);
        }

        if ($task === 'moderation_setstatus') {
            return $this->handleModerationSetStatus($input, $db);
        }

        if ($task === 'moderation_delete') {
            return $this->handleModerationDelete($input, $db);
        }

        return $this->json(['ok' => false, 'error' => 'unknown_task']);
    }

    private function handleList($input, DatabaseInterface $db)
    {
        $articleId = $input->getInt('article_id', 0);
        if ($articleId <= 0) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }

        $clientToken = $this->normalizeClientToken($input->getString('client_token', ''));

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('article_id'),
                $db->quoteName('section_id'),
                $db->quoteName('parent_id'),
                $db->quoteName('client_token'),
                $db->quoteName('author_name'),
                $db->quoteName('author_email'),
                $db->quoteName('body'),
                $db->quoteName('anchor_start'),
                $db->quoteName('anchor_end'),
                $db->quoteName('anchor_exact'),
                $db->quoteName('anchor_prefix'),
                $db->quoteName('anchor_suffix'),
                $db->quoteName('status'),
                $db->quoteName('created'),
            ])
            ->from($db->quoteName('#__constitution_comments'))
            ->where($db->quoteName('article_id') . ' = :aid')
            ->where($db->quoteName('status') . ' = 1')
            ->order($db->quoteName('created') . ' ASC')
            ->bind(':aid', $articleId, ParameterType::INTEGER);

        $rows = $db->setQuery($query)->loadAssocList() ?: [];
        $commentIds = array_column($rows, 'id');

        $reactionTotals = $commentIds ? $this->fetchCommentReactionTotals($db, $commentIds) : [];
        $userReactions  = ($clientToken !== '' && $commentIds)
            ? $this->fetchUserCommentReactions($db, $commentIds, $clientToken)
            : [];

        $comments = [];
        foreach ($rows as $row) {
            $comments[$row['id']] = $this->formatCommentRow(
                $row,
                $reactionTotals[$row['id']] ?? ['likes' => 0, 'dislikes' => 0],
                $userReactions[$row['id']] ?? null,
                $clientToken
            );
        }

        $tree = [];
        foreach ($comments as $id => &$comment) {
            $parentId = (int) $comment['parent_id'];
            if ($parentId > 0 && isset($comments[$parentId])) {
                $comments[$parentId]['replies'][] =& $comment;
            } else {
                $tree[] =& $comment;
            }
        }

        return $this->json(['ok' => true, 'comments' => $tree]);
    }

    private function handleCreate($input, DatabaseInterface $db)
    {
        $articleId = $input->getInt('article_id', 0);
        if ($articleId <= 0) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }

        $clientToken = $this->normalizeClientToken($input->getString('client_token', ''));
        if ($clientToken === '') {
            return $this->json(['ok' => false, 'error' => 'missing_token']);
        }

        $sectionId = trim($input->getString('section_id', ''));
        if ($sectionId === '') {
            $sectionId = null;
        }

        $parentId = $input->getInt('parent_id', 0);
        if ($parentId <= 0) {
            $parentId = null;
        } else {
            $parentRow = $this->loadCommentRow($db, $parentId);
            if (!$parentRow || (int) $parentRow['article_id'] !== $articleId || (int) $parentRow['status'] !== 1) {
                return $this->json(['ok' => false, 'error' => 'parent_not_found']);
            }
        }

        $filter = InputFilter::getInstance();
        $authorName = trim((string) $filter->clean($input->getString('author_name', ''), 'STRING'));
        $authorEmail = trim((string) $input->getString('author_email', ''));
        if ($authorEmail !== '' && !filter_var($authorEmail, FILTER_VALIDATE_EMAIL)) {
            return $this->json(['ok' => false, 'error' => 'bad_email']);
        }
        if ($authorEmail !== '') {
            $authorEmail = strtolower($authorEmail);
        } else {
            $authorEmail = null;
        }

        $bodyRaw = trim((string) $input->getString('body', ''));
        $body = trim((string) $filter->clean($bodyRaw, 'STRING'));
        if ($body === '') {
            return $this->json(['ok' => false, 'error' => 'empty_body']);
        }
        if (mb_strlen($body, 'UTF-8') > 5000) {
            return $this->json(['ok' => false, 'error' => 'body_too_long']);
        }

        if ($this->isCaptchaRequired()) {
            $captchaError = $this->validateCaptcha($input);
            if ($captchaError !== null) {
                return $this->json($captchaError);
            }
        }

        $anchorStart = $this->filterNullableInt($input->getString('anchor_start', ''));
        $anchorEnd   = $this->filterNullableInt($input->getString('anchor_end', ''));
        $anchorExact = trim($input->getString('anchor_exact', ''));
        $anchorPrefix = trim($input->getString('anchor_prefix', ''));
        $anchorSuffix = trim($input->getString('anchor_suffix', ''));

        $hasAnchor = $anchorStart !== null && $anchorEnd !== null && $anchorExact !== '';
        if ($hasAnchor) {
            if ($anchorStart < 0 || $anchorEnd <= $anchorStart) {
                return $this->json(['ok' => false, 'error' => 'bad_anchor']);
            }
            if (mb_strlen($anchorExact, 'UTF-8') > 2000) {
                $anchorExact = mb_substr($anchorExact, 0, 2000, 'UTF-8');
            }
            if (mb_strlen($anchorPrefix, 'UTF-8') > 255) {
                $anchorPrefix = mb_substr($anchorPrefix, 0, 255, 'UTF-8');
            }
            if (mb_strlen($anchorSuffix, 'UTF-8') > 255) {
                $anchorSuffix = mb_substr($anchorSuffix, 0, 255, 'UTF-8');
            }
        } else {
            $anchorStart = null;
            $anchorEnd = null;
            $anchorExact = null;
            $anchorPrefix = null;
            $anchorSuffix = null;
        }

        $now = Factory::getDate()->toSql();

        $status = $this->isPremoderationEnabled() ? self::STATUS_PENDING : self::STATUS_APPROVED;

        $record = (object) [
            'article_id' => $articleId,
            'section_id' => $sectionId,
            'parent_id' => $parentId,
            'client_token' => $clientToken,
            'author_name' => $authorName !== '' ? $authorName : null,
            'author_email' => $authorEmail,
            'body' => $body,
            'anchor_start' => $anchorStart,
            'anchor_end' => $anchorEnd,
            'anchor_exact' => $anchorExact,
            'anchor_prefix' => $anchorPrefix !== '' ? $anchorPrefix : null,
            'anchor_suffix' => $anchorSuffix !== '' ? $anchorSuffix : null,
            'status' => $status,
            'created' => $now,
        ];

        $db->insertObject('#__constitution_comments', $record);
        $newId = (int) $db->insertid();
        $row = $this->loadCommentRow($db, $newId);

        if ($status !== self::STATUS_APPROVED) {
            return $this->json(['ok' => true, 'pending' => true]);
        }

        $commentData = $this->formatCommentRow(
            $row,
            ['likes' => 0, 'dislikes' => 0],
            null,
            $clientToken
        );

        return $this->json(['ok' => true, 'comment' => $commentData]);
    }

    private function handleReact($input, DatabaseInterface $db)
    {
        $commentId = $input->getInt('comment_id', 0);
        if ($commentId <= 0) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }

        $clientToken = $this->normalizeClientToken($input->getString('client_token', ''));
        if ($clientToken === '') {
            return $this->json(['ok' => false, 'error' => 'missing_token']);
        }

        $type = $input->getCmd('type', 'like');
        if (!in_array($type, ['like', 'dislike'], true)) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }

        $commentRow = $this->loadCommentRow($db, $commentId);
        if (!$commentRow || (int) $commentRow['status'] !== 1) {
            return $this->json(['ok' => false, 'error' => 'comment_not_found']);
        }

        if ($this->hasCommentVote($db, $commentId, $clientToken)) {
            return $this->json(['ok' => false, 'error' => 'already_voted']);
        }

        $column = $type === 'like' ? 'likes' : 'dislikes';
        $cidParam = (int) $commentId;

        $update = $db->getQuery(true)
            ->update($db->quoteName('#__constitution_comment_reactions'))
            ->set($db->quoteName($column) . ' = ' . $db->quoteName($column) . ' + 1')
            ->where($db->quoteName('comment_id') . ' = :cid')
            ->bind(':cid', $cidParam, ParameterType::INTEGER);
        $db->setQuery($update)->execute();

        if ($db->getAffectedRows() === 0) {
            $likeInit = $type === 'like' ? 1 : 0;
            $dislikeInit = $type === 'dislike' ? 1 : 0;
            $insert = $db->getQuery(true)
                ->insert($db->quoteName('#__constitution_comment_reactions'))
                ->columns([
                    $db->quoteName('comment_id'),
                    $db->quoteName('likes'),
                    $db->quoteName('dislikes'),
                ])
                ->values(':cid,:likes,:dislikes')
                ->bind(':cid', $cidParam, ParameterType::INTEGER)
                ->bind(':likes', $likeInit, ParameterType::INTEGER)
                ->bind(':dislikes', $dislikeInit, ParameterType::INTEGER);

            try {
                $db->setQuery($insert)->execute();
            } catch (\RuntimeException $e) {
                $db->setQuery($update)->execute();
            }
        }

        $now = Factory::getDate()->toSql();
        $log = $db->getQuery(true)
            ->insert($db->quoteName('#__constitution_comment_reaction_logs'))
            ->columns([
                $db->quoteName('comment_id'),
                $db->quoteName('client_token'),
                $db->quoteName('vote_type'),
                $db->quoteName('created'),
            ])
            ->values(':cid,:token,:type,:created')
            ->bind(':cid', $cidParam, ParameterType::INTEGER)
            ->bind(':token', $clientToken, ParameterType::STRING)
            ->bind(':type', $type, ParameterType::STRING)
            ->bind(':created', $now, ParameterType::STRING);

        try {
            $db->setQuery($log)->execute();
        } catch (\RuntimeException $e) {
            // ignore duplicate
        }

        $totals = $this->fetchCommentReactionTotals($db, [$commentId]);
        $entry = $totals[$commentId] ?? ['likes' => 0, 'dislikes' => 0];

        return $this->json([
            'ok' => true,
            'likes' => (int) $entry['likes'],
            'dislikes' => (int) $entry['dislikes'],
        ]);
    }

    private function ensureTables(DatabaseInterface $db): void
    {
        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__constitution_comments') . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            section_id VARCHAR(255) DEFAULT NULL,
            parent_id INT DEFAULT NULL,
            client_token VARCHAR(64) NOT NULL,
            author_name VARCHAR(255) DEFAULT NULL,
            author_email VARCHAR(255) DEFAULT NULL,
            body TEXT NOT NULL,
            anchor_start INT DEFAULT NULL,
            anchor_end INT DEFAULT NULL,
            anchor_exact TEXT DEFAULT NULL,
            anchor_prefix VARCHAR(255) DEFAULT NULL,
            anchor_suffix VARCHAR(255) DEFAULT NULL,
            status TINYINT NOT NULL DEFAULT 1,
            created DATETIME NOT NULL,
            modified DATETIME DEFAULT NULL,
            INDEX idx_article (article_id),
            INDEX idx_article_section (article_id, section_id),
            INDEX idx_parent (parent_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')->execute();

        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__constitution_comment_reactions') . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            likes INT NOT NULL DEFAULT 0,
            dislikes INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_comment (comment_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')->execute();

        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__constitution_comment_reaction_logs') . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            comment_id INT NOT NULL,
            client_token VARCHAR(64) NOT NULL,
            vote_type ENUM(\'like\', \'dislike\') NOT NULL,
            created DATETIME NOT NULL,
            UNIQUE KEY uniq_vote (comment_id, client_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')->execute();
    }

    private function filterNullableInt($value): ?int
    {
        if ($value === '' || $value === null) {
            return null;
        }
        if (!is_numeric($value)) {
            return null;
        }
        return (int) $value;
    }

    private function validateCaptcha($input): ?array
    {
        $pluginName = $this->getCaptchaPlugin();
        if ($pluginName === '') {
            return ['ok' => false, 'error' => 'captcha_not_configured'];
        }

        if (!PluginHelper::isEnabled('captcha', $pluginName)) {
            return ['ok' => false, 'error' => 'captcha_not_configured'];
        }

        PluginHelper::importPlugin('captcha', $pluginName);

        try {
            $captcha = Captcha::getInstance($pluginName, ['namespace' => 'ajaxcomments']);
        } catch (\RuntimeException $exception) {
            return ['ok' => false, 'error' => 'captcha_not_configured'];
        }

        if (!$captcha) {
            return ['ok' => false, 'error' => 'captcha_not_configured'];
        }

        try {
            $valid = $captcha->checkAnswer(null);
        } catch (\Throwable $e) {
            return ['ok' => false, 'error' => 'captcha_failed'];
        }

        if (!$valid) {
            return ['ok' => false, 'error' => 'captcha_failed'];
        }

        return null;
    }

    private function loadCommentRow(DatabaseInterface $db, int $commentId): ?array
    {
        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__constitution_comments'))
            ->where($db->quoteName('id') . ' = :cid')
            ->bind(':cid', $commentId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadAssoc();
        return $row ?: null;
    }

    private function fetchCommentReactionTotals(DatabaseInterface $db, array $commentIds): array
    {
        if (empty($commentIds)) {
            return [];
        }
        $commentIds = array_values(array_unique(array_map('intval', $commentIds)));
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('comment_id'),
                $db->quoteName('likes'),
                $db->quoteName('dislikes'),
            ])
            ->from($db->quoteName('#__constitution_comment_reactions'))
            ->where($db->quoteName('comment_id') . ' IN (' . implode(',', $commentIds) . ')');
        return $db->setQuery($query)->loadAssocList('comment_id') ?: [];
    }

    private function fetchUserCommentReactions(DatabaseInterface $db, array $commentIds, string $clientToken): array
    {
        if ($clientToken === '' || empty($commentIds)) {
            return [];
        }
        $commentIds = array_values(array_unique(array_map('intval', $commentIds)));
        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('comment_id'),
                $db->quoteName('vote_type'),
            ])
            ->from($db->quoteName('#__constitution_comment_reaction_logs'))
            ->where($db->quoteName('comment_id') . ' IN (' . implode(',', $commentIds) . ')')
            ->where($db->quoteName('client_token') . ' = :token')
            ->bind(':token', $clientToken, ParameterType::STRING);
        return $db->setQuery($query)->loadAssocList('comment_id') ?: [];
    }

    private function hasCommentVote(DatabaseInterface $db, int $commentId, string $clientToken): bool
    {
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__constitution_comment_reaction_logs'))
            ->where($db->quoteName('comment_id') . ' = :cid')
            ->where($db->quoteName('client_token') . ' = :token')
            ->bind(':cid', $commentId, ParameterType::INTEGER)
            ->bind(':token', $clientToken, ParameterType::STRING);
        $count = (int) $db->setQuery($query)->loadResult();
        return $count > 0;
    }

    private function handleModerationList($input, DatabaseInterface $db)
    {
        if (!$this->canModerate()) {
            return $this->json(['ok' => false, 'error' => 'forbidden']);
        }

        $statusFilter = $input->getCmd('status', 'pending');
        $articleId = $input->getInt('article_id', 0);

        $map = [
            'pending' => self::STATUS_PENDING,
            'approved' => self::STATUS_APPROVED,
            'rejected' => self::STATUS_REJECTED,
            'all' => null,
        ];

        $status = array_key_exists($statusFilter, $map) ? $map[$statusFilter] : null;

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('article_id'),
                $db->quoteName('section_id'),
                $db->quoteName('parent_id'),
                $db->quoteName('client_token'),
                $db->quoteName('author_name'),
                $db->quoteName('author_email'),
                $db->quoteName('body'),
                $db->quoteName('anchor_start'),
                $db->quoteName('anchor_end'),
                $db->quoteName('anchor_exact'),
                $db->quoteName('anchor_prefix'),
                $db->quoteName('anchor_suffix'),
                $db->quoteName('status'),
                $db->quoteName('created'),
            ])
            ->from($db->quoteName('#__constitution_comments'))
            ->order([$db->quoteName('status') . ' ASC', $db->quoteName('created') . ' DESC']);

        if ($articleId > 0) {
            $query->where($db->quoteName('article_id') . ' = :aid')->bind(':aid', $articleId, ParameterType::INTEGER);
        }

        if ($status !== null) {
            $query->where($db->quoteName('status') . ' = :status')->bind(':status', $status, ParameterType::INTEGER);
        }

        $rows = $db->setQuery($query)->loadAssocList() ?: [];

        return $this->json(['ok' => true, 'comments' => $rows]);
    }

    private function handleModerationSetStatus($input, DatabaseInterface $db)
    {
        if (!$this->canModerate()) {
            return $this->json(['ok' => false, 'error' => 'forbidden']);
        }

        $commentId = $input->getInt('comment_id', 0);
        if ($commentId <= 0) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }

        $statusCmd = $input->getCmd('status', 'approved');
        $map = [
            'pending' => self::STATUS_PENDING,
            'approved' => self::STATUS_APPROVED,
            'rejected' => self::STATUS_REJECTED,
        ];

        if (!isset($map[$statusCmd])) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }

        $statusValue = $map[$statusCmd];

        $query = $db->getQuery(true)
            ->update($db->quoteName('#__constitution_comments'))
            ->set($db->quoteName('status') . ' = :status')
            ->where($db->quoteName('id') . ' = :cid')
            ->bind(':status', $statusValue, ParameterType::INTEGER)
            ->bind(':cid', $commentId, ParameterType::INTEGER);

        $db->setQuery($query)->execute();

        $row = $this->loadCommentRow($db, $commentId);

        if (!$row) {
            return $this->json(['ok' => false, 'error' => 'comment_not_found']);
        }

        return $this->json(['ok' => true, 'comment' => $row]);
    }

    private function handleModerationDelete($input, DatabaseInterface $db)
    {
        if (!$this->canModerate()) {
            return $this->json(['ok' => false, 'error' => 'forbidden']);
        }

        $commentId = $input->getInt('comment_id', 0);
        if ($commentId <= 0) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }

        $this->deleteCommentCascade($db, $commentId);

        return $this->json(['ok' => true]);
    }

    private function deleteCommentCascade(DatabaseInterface $db, int $commentId): void
    {
        $commentId = (int) $commentId;

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
                ->where($db->quoteName('comment_id') . ' = :cid')
                ->bind(':cid', $commentId, ParameterType::INTEGER)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__constitution_comment_reactions'))
                ->where($db->quoteName('comment_id') . ' = :cid')
                ->bind(':cid', $commentId, ParameterType::INTEGER)
        )->execute();

        $db->setQuery(
            $db->getQuery(true)
                ->delete($db->quoteName('#__constitution_comments'))
                ->where($db->quoteName('id') . ' = :cid')
                ->bind(':cid', $commentId, ParameterType::INTEGER)
        )->execute();
    }

    private function formatCommentRow(array $row, array $totals, ?array $userReaction, string $clientToken): array
    {
        $bodyHtml = nl2br(htmlspecialchars($row['body'], ENT_QUOTES, 'UTF-8'));
        $anchor = null;
        if ($row['anchor_start'] !== null && $row['anchor_end'] !== null && $row['anchor_exact'] !== null) {
            $anchor = [
                'start' => (int) $row['anchor_start'],
                'end' => (int) $row['anchor_end'],
                'exact' => $row['anchor_exact'],
                'prefix' => $row['anchor_prefix'] ?? '',
                'suffix' => $row['anchor_suffix'] ?? '',
            ];
        }

        return [
            'id' => (int) $row['id'],
            'article_id' => (int) $row['article_id'],
            'section_id' => $row['section_id'] ? (string) $row['section_id'] : null,
            'parent_id' => $row['parent_id'] !== null ? (int) $row['parent_id'] : null,
            'author_name' => $row['author_name'] ?: 'Ананімны Карыстальнік',
            'author_email' => $row['author_email'] ?: null,
            'body' => $row['body'],
            'body_html' => $bodyHtml,
            'created' => $row['created'],
            'status' => isset($row['status']) ? (int) $row['status'] : self::STATUS_APPROVED,
            'likes' => (int) ($totals['likes'] ?? 0),
            'dislikes' => (int) ($totals['dislikes'] ?? 0),
            'user_reaction' => $userReaction['vote_type'] ?? null,
            'own' => ($clientToken !== '' && $row['client_token'] === $clientToken),
            'anchor' => $anchor,
            'replies' => [],
        ];
    }

    private function normalizeClientToken(string $token): string
    {
        $token = trim($token);
        if ($token === '') {
            return '';
        }
        if (strlen($token) > 128) {
            $token = substr($token, 0, 128);
        }
        if (!preg_match('/^[A-Za-z0-9_\-]{8,128}$/', $token)) {
            return '';
        }
        return $token;
    }

    private function json($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        Factory::getApplication()->close();
    }
}
