<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\CMS\Session\Session;
use Joomla\Database\ParameterType;

class PlgAjaxReactions extends CMSPlugin
{
    public function onAjaxReactions()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        // Require token only for state-changing requests
        if (strtoupper($app->input->getMethod()) !== 'GET') {
            if (!Session::checkToken('request')) {
                return $this->json(['ok' => false, 'error' => 'invalid_token']);
            }
        }

        $task = $input->getCmd('task', 'get');
        $articleId = $input->getInt('article_id', 0);
        $sectionId = trim($input->getString('section_id', ''));
        $db = Factory::getContainer()->get('DatabaseDriver');
        $this->ensureTable($db);
        $this->ensureLogTable($db);

        if ($task === 'vote') {
            $type = $input->getCmd('type', 'like');
            if (!$articleId || $sectionId === '' || !in_array($type, ['like', 'dislike'], true)) {
                return $this->json(['ok' => false, 'error' => 'bad_params']);
            }
            $clientToken = $this->normalizeClientToken($input->getString('client_token', ''));
            if ($clientToken === '') {
                return $this->json(['ok' => false, 'error' => 'missing_token']);
            }
            if ($this->hasTokenVote($db, $articleId, $sectionId, $clientToken)) {
                return $this->json(['ok' => false, 'error' => 'already_voted']);
            }
            $column = $type === 'like' ? 'likes' : 'dislikes';
            $aidParam = (int) $articleId;
            $sidParam = (string) $sectionId;
            $update = $db->getQuery(true)
                ->update($db->quoteName('#__constitution_reactions'))
                ->set($db->quoteName($column) . ' = ' . $db->quoteName($column) . ' + 1')
                ->where($db->quoteName('article_id') . ' = :aid')
                ->where($db->quoteName('section_id') . ' = :sid')
                ->bind(':aid', $aidParam, ParameterType::INTEGER)
                ->bind(':sid', $sidParam, ParameterType::STRING);

            $db->setQuery($update)->execute();

            if ($db->getAffectedRows() === 0) {
                $likeInit = $type === 'like' ? 1 : 0;
                $dislikeInit = $type === 'dislike' ? 1 : 0;
                $insert = $db->getQuery(true)
                    ->insert($db->quoteName('#__constitution_reactions'))
                    ->columns([
                        $db->quoteName('article_id'),
                        $db->quoteName('section_id'),
                        $db->quoteName('likes'),
                        $db->quoteName('dislikes'),
                    ])
                    ->values(':aid,:sid,:likes,:dislikes')
                    ->bind(':aid', $aidParam, ParameterType::INTEGER)
                    ->bind(':sid', $sidParam, ParameterType::STRING)
                    ->bind(':likes', $likeInit, ParameterType::INTEGER)
                    ->bind(':dislikes', $dislikeInit, ParameterType::INTEGER);

                try {
                    $db->setQuery($insert)->execute();
                } catch (\RuntimeException $e) {
                    // If the row appeared between UPDATE and INSERT, bump the counter now.
                    $db->setQuery($update)->execute();
                }
            }
            $row = $this->fetchRow($db, $articleId, $sectionId);
            $this->storeVoteLog($db, $articleId, $sectionId, $clientToken, $type);
            return $this->json(['ok' => true, 'likes' => (int) ($row->likes ?? 0), 'dislikes' => (int) ($row->dislikes ?? 0)]);
        }

        // default: get
        if (!$articleId) {
            return $this->json(['ok' => false, 'error' => 'bad_params']);
        }
        $sectionsParam = trim($input->getString('sections', ''));
        $aidParam = (int) $articleId;
        $rows = [];
        if ($sectionsParam !== '') {
            $sections = array_filter(array_map('trim', explode(',', $sectionsParam)));
            if (!empty($sections)) {
                $quotedSections = array_map([$db, 'quote'], $sections);
                $query = $db->getQuery(true)
                    ->select(['section_id', 'likes', 'dislikes'])
                    ->from($db->quoteName('#__constitution_reactions'))
                    ->where($db->quoteName('article_id') . ' = :aid')
                    ->where($db->quoteName('section_id') . ' IN (' . implode(',', $quotedSections) . ')')
                    ->bind(':aid', $aidParam, ParameterType::INTEGER);
                $rows = $db->setQuery($query)->loadAssocList('section_id') ?: [];
            }
        } else {
            $query = $db->getQuery(true)
                ->select(['section_id', 'likes', 'dislikes'])
                ->from($db->quoteName('#__constitution_reactions'))
                ->where($db->quoteName('article_id') . ' = :aid')
                ->bind(':aid', $aidParam, ParameterType::INTEGER);
            $rows = $db->setQuery($query)->loadAssocList('section_id') ?: [];
        }
        return $this->json(['ok' => true, 'rows' => $rows]);
    }

    private function ensureTable($db): void
    {
        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__constitution_reactions') . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            section_id VARCHAR(255) NOT NULL,
            likes INT NOT NULL DEFAULT 0,
            dislikes INT NOT NULL DEFAULT 0,
            UNIQUE KEY uniq_article_section (article_id, section_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')->execute();
    }

    private function ensureLogTable($db): void
    {
        $db->setQuery('CREATE TABLE IF NOT EXISTS ' . $db->quoteName('#__constitution_reaction_logs') . ' (
            id INT AUTO_INCREMENT PRIMARY KEY,
            article_id INT NOT NULL,
            section_id VARCHAR(255) NOT NULL,
            client_token VARCHAR(128) NOT NULL,
            vote_type ENUM(\'like\', \'dislike\') NOT NULL,
            created DATETIME NOT NULL,
            UNIQUE KEY uniq_vote (article_id, section_id, client_token)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;')->execute();

        try {
            $columns = $db->getTableColumns('#__constitution_reaction_logs', true);
            if (isset($columns['ip_hash']) && !isset($columns['client_token'])) {
                $db->setQuery('ALTER TABLE ' . $db->quoteName('#__constitution_reaction_logs')
                    . ' CHANGE ' . $db->quoteName('ip_hash') . ' ' . $db->quoteName('client_token') . ' VARCHAR(128) NOT NULL')->execute();
            }
        } catch (\Throwable $e) {
            // Ignore migration errors
        }
    }

    private function hasTokenVote($db, int $articleId, string $sectionId, string $clientToken): bool
    {
        $aidParam = (int) $articleId;
        $sidParam = (string) $sectionId;
        $query = $db->getQuery(true)
            ->select('COUNT(*)')
            ->from($db->quoteName('#__constitution_reaction_logs'))
            ->where($db->quoteName('article_id') . ' = :aid')
            ->where($db->quoteName('section_id') . ' = :sid')
            ->where($db->quoteName('client_token') . ' = :token')
            ->bind(':aid', $aidParam, ParameterType::INTEGER)
            ->bind(':sid', $sidParam, ParameterType::STRING)
            ->bind(':token', $clientToken, ParameterType::STRING);
        $count = (int) $db->setQuery($query)->loadResult();
        return $count > 0;
    }

    private function storeVoteLog($db, int $articleId, string $sectionId, string $clientToken, string $type): void
    {
        $aidParam = (int) $articleId;
        $sidParam = (string) $sectionId;
        $typeParam = $type === 'dislike' ? 'dislike' : 'like';
        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__constitution_reaction_logs'))
            ->columns([
                $db->quoteName('article_id'),
                $db->quoteName('section_id'),
                $db->quoteName('client_token'),
                $db->quoteName('vote_type'),
                $db->quoteName('created'),
            ])
            ->values(':aid,:sid,:token,:type,NOW()')
            ->bind(':aid', $aidParam, ParameterType::INTEGER)
            ->bind(':sid', $sidParam, ParameterType::STRING)
            ->bind(':token', $clientToken, ParameterType::STRING)
            ->bind(':type', $typeParam, ParameterType::STRING);
        try {
            $db->setQuery($query)->execute();
        } catch (\RuntimeException $e) {
            // Уникальный ключ не даёт повторно записать тот же токен — можно игнорировать.
        }
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

    private function fetchRow($db, int $articleId, string $sectionId)
    {
        $aidParam = (int) $articleId;
        $sidParam = (string) $sectionId;
        $query = $db->getQuery(true)
            ->select(['likes', 'dislikes'])
            ->from($db->quoteName('#__constitution_reactions'))
            ->where($db->quoteName('article_id') . ' = :aid')
            ->where($db->quoteName('section_id') . ' = :sid')
            ->bind(':aid', $aidParam, ParameterType::INTEGER)
            ->bind(':sid', $sidParam, ParameterType::STRING);
        return $db->setQuery($query)->loadObject();
    }

    private function json($data)
    {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        Factory::getApplication()->close();
    }
}
