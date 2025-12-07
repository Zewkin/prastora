<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\CMS\Captcha\Captcha;
use Joomla\Registry\Registry;
use Joomla\Component\Fields\Administrator\Helper\FieldsHelper;

$app     = Factory::getApplication();
$template = $app->getTemplate();
$tplUri  = rtrim(Uri::root(true), '/') . '/templates/' . $template;

$commentsPlugin = PluginHelper::getPlugin('ajax', 'comments');
$commentsParams = $commentsPlugin ? new Registry($commentsPlugin->params) : new Registry();
$commentsModerationMode = (string) $commentsParams->get('moderation_mode', 'premoderation');
$captchaEnabled = (bool) $commentsParams->get('captcha_enabled', 0);
$captchaMode = (string) $commentsParams->get('captcha_mode', 'joomla');
$captchaPlugin = (string) $commentsParams->get('captcha_plugin', '');
$recaptchaSiteKey = trim((string) $commentsParams->get('recaptcha_site_key', ''));
$recaptchaBadge = (string) $commentsParams->get('recaptcha_badge', 'bottomright');
$recaptchaLocale = trim((string) $commentsParams->get('recaptcha_locale', ''));
$useInvisibleRecaptcha = $captchaEnabled && $captchaMode === 'recaptcha_invisible' && $recaptchaSiteKey !== '';
$captchaInstance = null;

if ($captchaEnabled && !$useInvisibleRecaptcha && $captchaPlugin !== '') {
    try {
        PluginHelper::importPlugin('captcha', $captchaPlugin);
        $captchaInstance = Captcha::getInstance($captchaPlugin, ['namespace' => 'ajaxcomments']);
    } catch (\RuntimeException $exception) {
        $captchaInstance = null;
    }
}

$captchaActive = $captchaEnabled && ($useInvisibleRecaptcha || $captchaInstance);
$captchaModeAttribute = !$captchaActive ? 'none' : ($useInvisibleRecaptcha ? 'recaptcha_invisible' : 'joomla');

$item = $this->item ?? null;
if (!$item || !\is_object($item)) {
    echo '<div class="container"><div class="colored_w"><p>Article not found.</p></div></div>';
    return;
}

$customIntroHtml = null;
$downloadPdfUrl = '';

$extractPdfPath = static function ($value) use (&$extractPdfPath): string {
    if (\is_array($value)) {
        if (isset($value['file']) && is_scalar($value['file'])) {
            return trim((string) $value['file']);
        }

        foreach ($value as $subValue) {
            $candidate = $extractPdfPath($subValue);
            if ($candidate !== '') {
                return $candidate;
            }
        }

        return '';
    }

    if (\is_object($value)) {
        return $extractPdfPath(get_object_vars($value));
    }

    if (\is_string($value)) {
        $stringValue = trim($value);

        if ($stringValue === '') {
            return '';
        }

        $decoded = json_decode($stringValue, true);

        if (json_last_error() === JSON_ERROR_NONE) {
            return $extractPdfPath($decoded);
        }

        return $stringValue;
    }

    if (is_scalar($value)) {
        return trim((string) $value);
    }

    return '';
};

try {
    $fields = FieldsHelper::getFields('com_content.article', $item, true);
    foreach ($fields as $field) {
        $fieldName = (string) ($field->name ?? '');

        if ($fieldName === 'intro' && isset($field->rawvalue)) {
            $value = is_scalar($field->rawvalue) ? (string) $field->rawvalue : '';
            if (trim(strip_tags($value)) !== '') {
                $customIntroHtml = (string) $field->rawvalue;
            }
        }

        if (($fieldName === 'constitution_pdf' || $fieldName === 'constitution-pdf') && isset($field->rawvalue)) {
            $mediaValue = $extractPdfPath($field->rawvalue);

            if ($mediaValue !== '') {
                $isAbsolute = preg_match('#^(?:https?:)?//#i', $mediaValue);
                $downloadPdfUrl = $isAbsolute
                    ? $mediaValue
                    : rtrim(Uri::root(), '/') . '/' . ltrim($mediaValue, '/');
            }
        }
    }
} catch (\Throwable $exception) {
    $customIntroHtml = null;
}

$rawHtml = (string) (($item->text ?? '') !== '' ? $item->text : ($item->fulltext ?? ''));
$introHeaderHtml = $customIntroHtml !== null ? trim((string) $customIntroHtml) : trim((string) ($item->introtext ?? ''));
if ($introHeaderHtml !== '') {
    $normalizedIntro = preg_replace('/\s+/u', ' ', trim(strip_tags($introHeaderHtml)));
    $normalizedFull  = preg_replace('/\s+/u', ' ', trim(strip_tags($rawHtml)));
    if ($normalizedIntro !== '' && $normalizedIntro === $normalizedFull) {
        $introHeaderHtml = '';
    }
}

$normalizeHeading = static function (string $html, string $fallback): string {
    $value = $html !== '' ? $html : htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    $value = preg_replace('#^(?:\s*<br\s*/?>)+#i', '', $value);
    $value = preg_replace('#(?:<br\s*/?>\s*)+$#i', '', $value);
    if (trim(strip_tags($value)) === '') {
        $value = htmlspecialchars($fallback, ENT_QUOTES, 'UTF-8');
    }
    return $value;
};

$makeId = static function (string $text, array &$used, string $prefix): string {
    if (!function_exists('mb_strtolower')) {
        $text = strtolower($text);
    } else {
        $text = mb_strtolower($text, 'UTF-8');
    }
    $text = preg_replace('/[^\\p{L}\\p{Nd}]+/u', '-', $text);
    $text = trim($text, '-');
    if ($text === '') {
        $text = $prefix;
    } else {
        $text = $prefix . '-' . $text;
    }

    $candidate = $text;
    $suffix = 2;
    while (in_array($candidate, $used, true)) {
        $candidate = $text . '-' . $suffix++;
    }

    $used[] = $candidate;

    return $candidate;
};

$sections = [];
$prefaceHtml = '';

$usedSectionIds = [];
$usedArticleIds = [];
$currentSectionIndex = null;
$currentArticleIndex = null;

$pattern = '#(<h[24][^>]*>.*?</h[24]>)#isu';
$tokens = preg_split($pattern, $rawHtml, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

foreach ($tokens as $token) {
    $trimmedToken = trim($token);

    if ($trimmedToken === '') {
        continue;
    }

    if (preg_match('#^<h2[^>]*>.*</h2>$#isu', $trimmedToken)) {
        preg_match('#<h2[^>]*>(.*?)</h2>#isu', $trimmedToken, $hm);
        $titleInner = trim($hm[1] ?? '');
        $titleText  = trim(strip_tags($titleInner));
        $sectionId  = $makeId($titleText !== '' ? $titleText : 'section', $usedSectionIds, 'chapter');

        $sections[] = [
            'id' => $sectionId,
            'title' => $titleText,
            'titleHtml' => $titleInner,
            'introHtml' => '',
            'articles' => [],
        ];

        $currentSectionIndex = count($sections) - 1;
        $currentArticleIndex = null;
        continue;
    }

    if (preg_match('#^<h4[^>]*>.*</h4>$#isu', $trimmedToken)) {
        preg_match('#<h4[^>]*>(.*?)</h4>#isu', $trimmedToken, $hm4);
        $articleInner = trim($hm4[1] ?? '');
        $articleText  = trim(strip_tags($articleInner));

        if ($currentSectionIndex === null) {
            $sectionId = $makeId($articleText !== '' ? $articleText : 'section', $usedSectionIds, 'chapter');
            $sections[] = [
                'id' => $sectionId,
                'title' => $articleText,
                'titleHtml' => $articleInner,
                'introHtml' => '',
                'articles' => [],
            ];
            $currentSectionIndex = count($sections) - 1;
        }

        $articleId = $makeId($articleText !== '' ? $articleText : 'article', $usedArticleIds, 'article');
        $sections[$currentSectionIndex]['articles'][] = [
            'id' => $articleId,
            'title' => $articleText,
            'titleHtml' => $articleInner,
            'html' => '',
        ];
        $currentArticleIndex = count($sections[$currentSectionIndex]['articles']) - 1;
        continue;
    }

    if ($currentSectionIndex !== null) {
        if ($currentArticleIndex !== null) {
            $sections[$currentSectionIndex]['articles'][$currentArticleIndex]['html'] .= $token;
        } else {
            $sections[$currentSectionIndex]['introHtml'] .= $token;
        }
    } else {
        $prefaceHtml .= $token;
    }
}

$prefaceHtml = trim($prefaceHtml);

if ($prefaceHtml !== '' && empty($sections)) {
    $sectionId = $makeId('preface', $usedSectionIds, 'chapter');
    $articleId = $makeId('preface', $usedArticleIds, 'article');
    $fallbackTitle = trim(strip_tags($prefaceHtml));

    $sections[] = [
        'id' => $sectionId,
        'title' => $fallbackTitle,
        'titleHtml' => $fallbackTitle,
        'introHtml' => '',
        'articles' => [
            [
                'id' => $articleId,
                'title' => $fallbackTitle,
                'titleHtml' => $fallbackTitle,
                'html' => $prefaceHtml,
            ],
        ],
    ];
    $prefaceHtml = '';
}

foreach ($sections as $secIdx => $section) {
    $sections[$secIdx]['introHtml'] = trim($section['introHtml']);

    if (empty($section['articles'])) {
        $content = $sections[$secIdx]['introHtml'];
        if ($content !== '') {
            $articleId = $makeId($section['title'] !== '' ? $section['title'] : 'article', $usedArticleIds, 'article');
            $sections[$secIdx]['articles'][] = [
                'id' => $articleId,
                'title' => $section['title'],
                'titleHtml' => $section['titleHtml'],
                'html' => $content,
            ];
            $sections[$secIdx]['introHtml'] = '';
        }
    }

    foreach ($sections[$secIdx]['articles'] as $aIdx => $article) {
        $sections[$secIdx]['articles'][$aIdx]['html'] = trim($article['html']);
        if ($sections[$secIdx]['articles'][$aIdx]['title'] === '' && $article['titleHtml'] !== '') {
            $sections[$secIdx]['articles'][$aIdx]['title'] = trim(strip_tags($article['titleHtml']));
        }
    }
}

if (empty($sections)) {
    $sectionId = $makeId('article', $usedSectionIds, 'chapter');
    $articleId = $makeId('article', $usedArticleIds, 'article');
    $fallbackTitle = trim(strip_tags($rawHtml));

    $sections[] = [
        'id' => $sectionId,
        'title' => $fallbackTitle,
        'titleHtml' => $fallbackTitle,
        'introHtml' => '',
        'articles' => [
            [
                'id' => $articleId,
                'title' => $fallbackTitle,
                'titleHtml' => $fallbackTitle,
                'html' => $rawHtml,
            ],
        ],
    ];
}
?>
<div class="about_header container cons_header">
    <div class="cons_header_wra">
        <div class="about_header_desc">
            <div class="cons_top">
                <h1><?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="cons_btm">
                <?php if ($introHeaderHtml !== '') : ?>
                    <?php echo $introHeaderHtml; ?>
                <?php endif; ?>
                <div class="header_actions">
                  <a href="#"><button class="main_btn"><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_BTN_LEARN_MORE'); ?></button></a>
                  <?php if ($downloadPdfUrl !== '') : ?>
                  <a href="<?php echo htmlspecialchars($downloadPdfUrl, ENT_QUOTES, 'UTF-8'); ?>" class="main_btn line_btn" target="_blank" rel="noopener" download>
                      <?php echo Text::_('TPL_PRASTORA_CONSTITUTION_BTN_DOWNLOAD'); ?>
                  </a>
                  <?php endif; ?>
                </div>
                <!-- <div class="cons_header_desc">
                    <p><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_SECURITY_TEXT'); ?></p>
                </div> -->
            </div>
        </div>
        <img class="cons_img" src="<?php echo $tplUri; ?>/assets/images/cons-img.png" alt="" />
    </div>
    <!-- <div class="cons_header_desc">
        <p><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_SECURITY_TEXT'); ?></p>
    </div> -->
</div>

<div class="cons_articles container">
    <div class="articles_list_block">
        <div class="articles_list_block_top">
            <div class="nav_search">
                <img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
                <input id="tocSearch" type="text" placeholder="<?php echo Text::_('TPL_PRASTORA_CONSTITUTION_TOC_SEARCH'); ?>" />
            </div>
            <!-- <div class="info"><img src="<?php echo $tplUri; ?>/assets/icons/info.svg" alt="" /></div> -->
        </div>
        <div class="articles_list">
            <h3><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_TOC_TITLE'); ?></h3>
            <?php foreach ($sections as $secIdx => $sec) : ?>
            <div class="articles_list_item<?php echo $secIdx === 0 ? ' articles_list_item_active' : ''; ?>" data-section-id="<?php echo htmlspecialchars($sec['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <div class="articles_list_item_title" data-target="<?php echo htmlspecialchars($sec['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <p><?php echo htmlspecialchars($sec['title'] !== '' ? $sec['title'] : Text::_('JGLOBAL_ARTICLES'), ENT_QUOTES, 'UTF-8'); ?></p>
                    <img src="<?php echo $tplUri; ?>/assets/icons/article-icon.svg" alt="" />
                </div>
                <?php if (!empty($sec['articles'])) : ?>
                <div class="articles_list_item_desc">
                  <?php foreach ($sec['articles'] as $article) : ?>
                    <?php if (trim($article['title']) !== '') : ?>
                    <p data-target="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <?php endif; ?>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="article_block" id="constitution-content">
        <?php if ($prefaceHtml !== '' && !empty($sections)) : ?>
        <div class="article_block_preface" id="constitution-preface" data-role="article-content" data-section-id="constitution-preface">
            <?php echo $prefaceHtml; ?>
        </div>
        <?php endif; ?>
        <?php foreach ($sections as $sec) : ?>
        <?php foreach ($sec['articles'] as $articleIndex => $article) : ?>
        <?php
        $articleHeading = $normalizeHeading($article['titleHtml'], $article['title']);
        $articleHeadingText = trim(strip_tags($articleHeading));
        ?>
        <div class="article_block_item" id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>" data-section-id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>" data-section-title="<?php echo htmlspecialchars($articleHeadingText, ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($articleIndex === 0) : ?>
            <div class="article_chapter_title" id="<?php echo htmlspecialchars($sec['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <?php $chapterHeading = $normalizeHeading($sec['titleHtml'], $sec['title']); ?>
                <h2><?php echo $chapterHeading; ?></h2>
                <?php if (!empty($sec['introHtml'])) : ?>
                <div class="article_chapter_intro"><?php echo $sec['introHtml']; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="article_block_item_title">
                <h4><?php echo $articleHeading; ?></h4>
                <div class="article_reactions" data-article-id="<?php echo (int) $item->id; ?>" data-section-id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>" data-section-title="<?php echo htmlspecialchars($articleHeadingText, ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="article_reaction" data-role="reaction-like"><img src="<?php echo $tplUri; ?>/assets/icons/like.svg" alt="" /><p data-role="count-like">0</p></div>
                    <div class="article_reaction" data-role="reaction-dislike"><img src="<?php echo $tplUri; ?>/assets/icons/dislike.svg" alt="" /><p data-role="count-dislike">0</p></div>
                    <div class="article_reaction" data-role="open-comments"><img src="<?php echo $tplUri; ?>/assets/icons/comment.svg" alt="" /><p>0</p></div>
                    <div class="article_reaction article_commenting" data-role="comment-open"><p><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_COMMENT_BUTTON'); ?></p></div>
                </div>
            </div>
            <div class="article_block_item_content" data-role="article-content">
                <?php echo $article['html']; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="article_block_info">
            <img src="<?php echo $tplUri; ?>/assets/icons/arc-info.svg" alt="" />
            <p><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_INFO_TEXT'); ?></p>
        </div>
    </div>

    <div class="comments_block" data-comments="1" data-article-id="<?php echo (int) $item->id; ?>" data-like-icon="<?php echo $tplUri; ?>/assets/icons/like.svg" data-dislike-icon="<?php echo $tplUri; ?>/assets/icons/dislike.svg" data-reply-icon="<?php echo $tplUri; ?>/assets/icons/comment.svg" data-moderation-mode="<?php echo htmlspecialchars($commentsModerationMode, ENT_QUOTES, 'UTF-8'); ?>" data-captcha-enabled="<?php echo $captchaActive ? '1' : '0'; ?>" data-captcha-mode="<?php echo htmlspecialchars($captchaModeAttribute, ENT_QUOTES, 'UTF-8'); ?>" data-recaptcha-sitekey="<?php echo htmlspecialchars($useInvisibleRecaptcha ? $recaptchaSiteKey : '', ENT_QUOTES, 'UTF-8'); ?>" data-recaptcha-badge="<?php echo htmlspecialchars($useInvisibleRecaptcha ? $recaptchaBadge : '', ENT_QUOTES, 'UTF-8'); ?>" data-recaptcha-locale="<?php echo htmlspecialchars($useInvisibleRecaptcha ? $recaptchaLocale : '', ENT_QUOTES, 'UTF-8'); ?>">
        <div class="comments_list">
            <div class="comment_list_title" data-role="list-header">
                <div class="comment_list_title_inner">
                    <svg class="comment_list_title_icon" aria-hidden="true" viewBox="0 0 16.9121 15" focusable="false">
                        <path d="M8.18359 0C3.66406 0 0 3.35742 0 7.5C0 11.6426 3.66406 15 8.18359 15C9.35352 15 10.4668 14.7754 11.4727 14.3701C11.5996 14.3193 11.7344 14.29 11.8691 14.29L15.9121 14.29C16.6035 14.29 17.0859 13.6045 16.8535 12.9531L15.9766 10.502C15.8926 10.2686 15.9023 10.0146 15.9824 9.78125C16.2324 9.06152 16.3691 8.29492 16.3691 7.5C16.3691 3.35742 12.7051 0 8.18359 0Z" />
                    </svg>
                    <span data-role="list-title"><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_COMMENTS_ALL'); ?></span>
                </div>
                <button type="button" class="comment_back_btn" data-role="back-to-all" hidden><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_COMMENTS_BACK'); ?></button>
            </div>
            <div class="comments_container" data-role="comments-list">
                <div class="comments_empty" data-role="comments-empty"><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_COMMENTS_EMPTY'); ?></div>
            </div>
        </div>
        <form class="auth_comment" data-role="comment-form" data-moderation-mode="<?php echo htmlspecialchars($commentsModerationMode, ENT_QUOTES, 'UTF-8'); ?>" data-captcha-enabled="<?php echo $captchaActive ? '1' : '0'; ?>" novalidate>
            <div class="comment_form_state" data-role="reply-context" hidden>
                <span data-role="reply-label"><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_REPLY_LABEL'); ?></span>
                <button type="button" class="comment_reply_clear" data-role="reply-clear"><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_REPLY_CLEAR'); ?></button>
            </div>
            <div class="comment_form_notice" data-role="form-notice" hidden></div>
            <div class="comment_form_error" data-role="form-error" hidden></div>
            <input type="text" name="author_name" placeholder="<?php echo Text::_('TPL_PRASTORA_CONSTITUTION_AUTHOR_PLACEHOLDER'); ?>" maxlength="255" />
            <input type="email" name="author_email" placeholder="<?php echo Text::_('TPL_PRASTORA_CONSTITUTION_EMAIL_PLACEHOLDER'); ?>" maxlength="255" />
            <textarea name="body" rows="4" placeholder="<?php echo Text::_('TPL_PRASTORA_CONSTITUTION_BODY_PLACEHOLDER'); ?>" maxlength="5000" required></textarea>
            <?php if ($useInvisibleRecaptcha) : ?>
            <?php
            $recaptchaPrivacyLink = '<a href="https://policies.google.com/privacy" target="_blank" rel="noopener noreferrer">' . Text::_('TPL_PRASTORA_RECAPTCHA_PRIVACY') . '</a>';
            $recaptchaTermsLink = '<a href="https://policies.google.com/terms" target="_blank" rel="noopener noreferrer">' . Text::_('TPL_PRASTORA_RECAPTCHA_TERMS') . '</a>';
            $recaptchaNoticeText = Text::sprintf('TPL_PRASTORA_RECAPTCHA_NOTICE', $recaptchaPrivacyLink, $recaptchaTermsLink);
            ?>
            <input type="hidden" name="g-recaptcha-response" value="" data-role="recaptcha-response" />
            <div class="comment_form_captcha comment_form_captcha--invisible" data-role="captcha-wrapper">
                <div class="g-recaptcha" data-role="recaptcha-placeholder"></div>
                <noscript>
                    <span><?php echo $recaptchaNoticeText; ?></span>
                </noscript>
            </div>
            <p class="comment_form_notice comment_form_notice--static comment_form_notice--recaptcha">
                <?php echo $recaptchaNoticeText; ?>
            </p>
            <?php elseif ($captchaInstance) : ?>
            <div class="comment_form_captcha" data-role="captcha-wrapper">
                <?php echo $captchaInstance->display('ajaxcomments-comment-' . (int) $item->id, 'ajaxcomments-captcha-' . (int) $item->id); ?>
            </div>
            <?php endif; ?>
            <div class="send_btn">
                <button class="main_btn" type="submit"><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_COMMENT_BUTTON'); ?></button>
            </div>
        </form>
    </div>
</div>

<div class="fixed_nav" data-role="mobile-fixed-nav">
    <div class="fixed_nav_left">
        <div class="fixed_nav_item" data-role="toggle-contents"><div><img src="<?php echo $tplUri; ?>/assets/icons/f-nav-1.svg" alt="" /></div><p><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_FIXED_TOC'); ?></p></div>
        <!-- <div class="fixed_nav_item"><div><img src="<?php echo $tplUri; ?>/assets/icons/f-nav-2.svg" alt="" /></div><p><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_FIXED_HELP'); ?></p></div> -->
    </div>
    <div class="fixed_nav_item" data-role="toggle-comments"><div><img src="<?php echo $tplUri; ?>/assets/icons/f-nav-3.svg" alt="" /></div><p><?php echo Text::_('TPL_PRASTORA_CONSTITUTION_FIXED_COMMENTS'); ?></p></div>
</div>

<div class="mobile_overlay" data-role="mobile-overlay" hidden>
    <div class="mobile_overlay_inner">
        <div class="mobile_overlay_content" data-role="mobile-overlay-content"></div>
    </div>
</div>
