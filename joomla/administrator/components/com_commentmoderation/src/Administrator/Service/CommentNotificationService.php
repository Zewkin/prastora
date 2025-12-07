<?php
/**
 * @package     Joomla.Administrator
 * @subpackage  com_commentmoderation
 */

namespace Joomla\Component\Commentmoderation\Administrator\Service;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Component\Commentmoderation\Administrator\Model\CommentsModel;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Joomla\Registry\Registry;

/**
 * Handles email notifications for the comment moderation workflow.
 */
class CommentNotificationService
{
    /**
     * Notify when a comment is created.
     */
    public static function onNewComment(
        DatabaseInterface $db,
        array $commentRow,
        ?array $parentRow,
        ?array $articleRow,
        bool $isApproved
    ): void {
        $params = ComponentHelper::getParams('com_commentmoderation');
        self::ensureLanguageLoaded();

        self::notifyAdmin($params, $commentRow, $articleRow);

        if ($isApproved) {
            self::notifyParentOnReply($db, $params, $commentRow, $parentRow, $articleRow);
        }
    }

    /**
     * Notify when a comment transitions to the approved state.
     */
    public static function onCommentApproved(DatabaseInterface $db, array $commentRow): void
    {
        if ((int) ($commentRow['status'] ?? 0) !== CommentsModel::STATUS_APPROVED) {
            return;
        }

        $params = ComponentHelper::getParams('com_commentmoderation');
        self::ensureLanguageLoaded();

        $parentRow = null;
        if (!empty($commentRow['parent_id'])) {
            $parentRow = self::loadComment($db, (int) $commentRow['parent_id']);
        }

        $articleRow = self::loadArticle($db, (int) ($commentRow['article_id'] ?? 0));

        self::notifyParentOnReply($db, $params, $commentRow, $parentRow, $articleRow);
    }

    private static function notifyAdmin(Registry $params, array $commentRow, ?array $articleRow): void
    {
        if (!(int) $params->get('notification_enable_admin', 1)) {
            return;
        }

        $config = Factory::getConfig();
        $adminEmail = trim((string) $params->get('notification_admin_email', ''));
        if ($adminEmail === '') {
            $adminEmail = (string) $config->get('mailfrom');
        }

        if ($adminEmail === '') {
            return;
        }

        $articleTitle = $articleRow['title'] ?? Text::_('COM_COMMENTMODERATION_UNKNOWN_ARTICLE');
        $subject = Text::sprintf('COM_COMMENTMODERATION_EMAIL_SUBJECT_ADMIN', $articleTitle);

        $statusLabel = self::formatStatus((int) ($commentRow['status'] ?? CommentsModel::STATUS_PENDING));
        $author = $commentRow['author_name'] ?: Text::_('COM_COMMENTMODERATION_UNKNOWN_AUTHOR');
        $authorEmail = $commentRow['author_email'] ?? '';

        $adminLink = Uri::root() . 'administrator/index.php?option=com_commentmoderation&task=comment.edit&id=' . (int) $commentRow['id'];
        $articleLink = self::buildArticleLink($commentRow, $articleRow);

        $body  = '<p>' . Text::_('COM_COMMENTMODERATION_EMAIL_ADMIN_HEADING') . '</p>';
        $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_ARTICLE_LABEL') . ':</strong> ' . htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8') . '</p>';
        $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_STATUS_LABEL') . ':</strong> ' . htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8') . '</p>';
        $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_AUTHOR_LABEL') . ':</strong> ' . htmlspecialchars((string) $author, ENT_QUOTES, 'UTF-8') . '</p>';

        if ($authorEmail !== '') {
            $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_EMAIL_LABEL') . ':</strong> ' . htmlspecialchars((string) $authorEmail, ENT_QUOTES, 'UTF-8') . '</p>';
        }

        $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_BODY_LABEL') . ':</strong><br>' . self::renderBodyHtml((string) ($commentRow['body'] ?? '')) . '</p>';

        $body .= '<p><a href="' . htmlspecialchars($adminLink, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_COMMENTMODERATION_EMAIL_LINK_ADMIN') . '</a></p>';

        if ($articleLink !== null) {
            $body .= '<p><a href="' . htmlspecialchars($articleLink, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_COMMENTMODERATION_EMAIL_LINK_ARTICLE') . '</a></p>';
        }

        self::sendMessage($params, $adminEmail, $subject, $body);
    }

    private static function notifyParentOnReply(
        DatabaseInterface $db,
        Registry $params,
        array $commentRow,
        ?array $parentRow,
        ?array $articleRow
    ): void {
        if (!(int) $params->get('notification_enable_user', 1)) {
            return;
        }

        if ((int) ($commentRow['parent_id'] ?? 0) <= 0) {
            return;
        }

        if ((int) ($commentRow['status'] ?? CommentsModel::STATUS_PENDING) !== CommentsModel::STATUS_APPROVED) {
            return;
        }

        if ($parentRow === null) {
            $parentRow = self::loadComment($db, (int) $commentRow['parent_id']);
        }

        if (!$parentRow || empty($parentRow['author_email'])) {
            return;
        }

        if ((int) ($parentRow['status'] ?? CommentsModel::STATUS_APPROVED) !== CommentsModel::STATUS_APPROVED) {
            return;
        }

        $recipient = trim((string) $parentRow['author_email']);
        if ($recipient === '' || !filter_var($recipient, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        if (!empty($commentRow['author_email']) && strtolower((string) $commentRow['author_email']) === strtolower($recipient)) {
            return;
        }

        $articleTitle = $articleRow['title'] ?? Text::_('COM_COMMENTMODERATION_UNKNOWN_ARTICLE');
        $subject = Text::sprintf('COM_COMMENTMODERATION_EMAIL_SUBJECT_REPLY', $articleTitle);

        $articleLink = self::buildArticleLink($commentRow, $articleRow);
        $replyBody   = self::renderBodyHtml((string) ($commentRow['body'] ?? ''));

        $body  = '<p>' . Text::_('COM_COMMENTMODERATION_EMAIL_REPLY_HEADING') . '</p>';
        $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_ARTICLE_LABEL') . ':</strong> ' . htmlspecialchars($articleTitle, ENT_QUOTES, 'UTF-8') . '</p>';
        $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_AUTHOR_LABEL') . ':</strong> '
            . htmlspecialchars((string) ($commentRow['author_name'] ?? Text::_('COM_COMMENTMODERATION_UNKNOWN_AUTHOR')), ENT_QUOTES, 'UTF-8')
            . '</p>';
        $body .= '<p><strong>' . Text::_('COM_COMMENTMODERATION_EMAIL_BODY_LABEL') . ':</strong><br>' . $replyBody . '</p>';

        if ($articleLink !== null) {
            $body .= '<p><a href="' . htmlspecialchars($articleLink, ENT_QUOTES, 'UTF-8') . '">' . Text::_('COM_COMMENTMODERATION_EMAIL_LINK_ARTICLE') . '</a></p>';
        }

        self::sendMessage($params, $recipient, $subject, $body);
    }

    private static function renderBodyHtml(string $text): string
    {
        $safe = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        return nl2br($safe);
    }

    private static function buildArticleLink(?array $commentRow, ?array $articleRow): ?string
    {
        $articleId = (int) ($commentRow['article_id'] ?? 0);
        if ($articleId <= 0) {
            return null;
        }

        return Uri::root() . 'index.php?option=com_content&view=article&id=' . $articleId;
    }

    private static function sendMessage(Registry $params, string $recipient, string $subject, string $body): void
    {
        if ($recipient === '') {
            return;
        }

        $config = Factory::getConfig();
        $fromEmail = trim((string) $params->get('notification_from_email', ''));
        $fromName  = trim((string) $params->get('notification_from_name', ''));

        if ($fromEmail === '') {
            $fromEmail = (string) $config->get('mailfrom');
        }
        if ($fromName === '') {
            $fromName = (string) $config->get('fromname');
        }

        if ($fromEmail === '') {
            return;
        }

        $mailer = Factory::getMailer();

        // Force SMTP to avoid the PHP mail() transport.
        $mailer->useSmtp(
            (bool) $config->get('smtpauth'),
            (string) $config->get('smtphost'),
            (string) $config->get('smtpuser'),
            (string) $config->get('smtppass'),
            (string) $config->get('smtpsecure'),
            (int) $config->get('smtpport', 25)
        );

        $mailer->clearAllRecipients();
        $mailer->clearAttachments();
        $mailer->clearReplyTos();

        $mailer->setSender([$fromEmail, $fromName ?: $fromEmail]);
        $mailer->addRecipient($recipient);
        $mailer->setSubject($subject);
        $mailer->isHtml(true);
        $mailer->Encoding = 'base64';
        $mailer->setBody($body);

        try {
            $mailer->send();
            self::appendToImap($params, $fromEmail, $recipient, $subject, $body);
        } catch (\Throwable $exception) {
            Log::add('Failed to send comment notification: ' . $exception->getMessage(), Log::WARNING, 'mail');
        }
    }

    private static function appendToImap(Registry $params, string $fromEmail, string $recipient, string $subject, string $body): void
    {
        if (!\extension_loaded('imap')) {
            return;
        }

        $host = trim((string) $params->get('notification_imap_host', ''));
        $user = trim((string) $params->get('notification_imap_user', ''));
        $password = (string) $params->get('notification_imap_password', '');

        if ($host === '' || $user === '' || $password === '') {
            return;
        }

        $port = (int) $params->get('notification_imap_port', 993);
        $folder = trim((string) $params->get('notification_imap_folder', 'Sent'));
        $encryption = strtolower((string) $params->get('notification_imap_encryption', 'ssl'));

        $mailbox = sprintf('{%s:%d/imap', $host, $port > 0 ? $port : 993);
        if ($encryption === 'ssl') {
            $mailbox .= '/ssl';
        } elseif ($encryption === 'tls') {
            $mailbox .= '/tls';
        }
        $mailbox .= '}';

        $targetFolder = $mailbox . ($folder !== '' ? $folder : 'Sent');
        $rawMessage = self::buildRawMessage($fromEmail, $recipient, $subject, $body);

        try {
            $stream = @\imap_open($mailbox, $user, $password, 0, 1);
            if ($stream !== false) {
                @\imap_append($stream, $targetFolder, $rawMessage);
                \imap_close($stream);
            }
        } catch (\Throwable $exception) {
            Log::add('Failed to append notification to IMAP: ' . $exception->getMessage(), Log::NOTICE, 'mail');
        }
    }

    private static function buildRawMessage(string $fromEmail, string $recipient, string $subject, string $body): string
    {
        $host = parse_url(Uri::root(), PHP_URL_HOST) ?: 'localhost';
        try {
            $messageId = bin2hex(random_bytes(16)) . '@' . $host;
        } catch (\Throwable $exception) {
            $messageId = uniqid('comment-', true) . '@' . $host;
        }

        $headers = [
            'Date: ' . (new \DateTimeImmutable())->format('r'),
            'From: ' . $fromEmail,
            'To: ' . $recipient,
            'Subject: ' . $subject,
            'Message-ID: <' . $messageId . '>',
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
        ];

        return implode("\r\n", $headers) . "\r\n\r\n" . chunk_split(base64_encode($body)) . "\r\n";
    }

    private static function loadComment(DatabaseInterface $db, int $commentId): ?array
    {
        if ($commentId <= 0) {
            return null;
        }

        $query = $db->getQuery(true)
            ->select('*')
            ->from($db->quoteName('#__constitution_comments'))
            ->where($db->quoteName('id') . ' = :cid')
            ->bind(':cid', $commentId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadAssoc();

        return $row ?: null;
    }

    private static function loadArticle(DatabaseInterface $db, int $articleId): ?array
    {
        if ($articleId <= 0) {
            return null;
        }

        $query = $db->getQuery(true)
            ->select([
                $db->quoteName('id'),
                $db->quoteName('title'),
                $db->quoteName('catid'),
            ])
            ->from($db->quoteName('#__content'))
            ->where($db->quoteName('id') . ' = :aid')
            ->bind(':aid', $articleId, ParameterType::INTEGER);

        $row = $db->setQuery($query)->loadAssoc();

        return $row ?: null;
    }

    private static function ensureLanguageLoaded(): void
    {
        $language = Factory::getApplication()->getLanguage();
        $language->load('com_commentmoderation', JPATH_ADMINISTRATOR, null, false, true);
    }

    private static function formatStatus(int $status): string
    {
        if ($status === CommentsModel::STATUS_APPROVED) {
            return Text::_('COM_COMMENTMODERATION_STATUS_PUBLISHED');
        }

        if ($status === CommentsModel::STATUS_REJECTED) {
            return Text::_('COM_COMMENTMODERATION_STATUS_REJECTED');
        }

        return Text::_('COM_COMMENTMODERATION_STATUS_PENDING');
    }
}

