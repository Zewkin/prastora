<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

$app     = Factory::getApplication();
$template = $app->getTemplate();
$tplUri  = rtrim(Uri::root(true), '/') . '/templates/' . $template;

$item = $this->item ?? null;
if (!$item || !\is_object($item)) {
    echo '<div class="container"><div class="colored_w"><p>Article not found.</p></div></div>';
    return;
}
$rawHtml = (string) (($item->text ?? '') !== '' ? $item->text : ($item->fulltext ?? ''));

// Extract chapters (<h4> without class "article-title") and nested articles (<h4 class="article-title">)
$sections = [];
if ($rawHtml !== '') {
    $pattern = '#(<h4[^>]*>.*?</h4>)#isu';
    $chunks = preg_split($pattern, $rawHtml, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);

    $currentChapterIndex = null;
    $currentArticleIndex = null;

    foreach ($chunks as $chunk) {
        $chunk = trim($chunk);
        if ($chunk === '') {
            continue;
        }

        if (stripos($chunk, '<h4') === 0) {
            preg_match('#<h4[^>]*>(.*?)</h4>#isu', $chunk, $hm);
            $titleHtml = trim($hm[1] ?? '');
            $titleText = trim(strip_tags($titleHtml));

            $classAttr = '';
            if (preg_match('#class\s*=\s*"([^"]*)"#i', $chunk, $cm)) {
                $classAttr = strtolower($cm[1]);
            } elseif (preg_match("#class\s*=\s*'([^']*)'#i", $chunk, $cm)) {
                $classAttr = strtolower($cm[1]);
            }

            $base = preg_replace('~[^\p{L}\p{Nd}]+~u', '-', $titleText);
            $base = function_exists('mb_strtolower') ? mb_strtolower($base, 'UTF-8') : strtolower($base);
            $slug = trim($base, '-') ?: ('sec-' . (count($sections) + 1));

            if (strpos($classAttr, 'article-title') !== false) {
                if ($currentChapterIndex === null) {
                    // Skip orphan article titles
                    continue;
                }
                $sections[$currentChapterIndex]['articles'][] = [
                    'id' => 'article-' . ($slug ?: ('item-' . (count($sections[$currentChapterIndex]['articles']) + 1))),
                    'title' => $titleText,
                    'titleHtml' => $titleHtml,
                    'html' => '',
                ];
                $currentArticleIndex = count($sections[$currentChapterIndex]['articles']) - 1;
            } else {
                $chapterAnchor = 'chapter-' . ($slug ?: (string) (count($sections) + 1));
                $sections[] = [
                    'id' => $chapterAnchor,
                    'title' => $titleText,
                    'titleHtml' => $titleHtml,
                    'introHtml' => '',
                    'articles' => [],
                ];
                $currentChapterIndex = count($sections) - 1;
                $currentArticleIndex = null;
            }
        } else {
            if ($currentChapterIndex === null) {
                continue;
            }
            if ($currentArticleIndex !== null) {
                $sections[$currentChapterIndex]['articles'][$currentArticleIndex]['html'] .= $chunk;
            } else {
                $sections[$currentChapterIndex]['introHtml'] .= $chunk;
            }
        }
    }

    // Trim accumulated HTML
    foreach ($sections as $cIdx => $chapter) {
        $sections[$cIdx]['introHtml'] = trim($chapter['introHtml']);
        foreach ($chapter['articles'] as $aIdx => $article) {
            $sections[$cIdx]['articles'][$aIdx]['html'] = trim($article['html']);
        }
    }
}
?>
<div class="about_header container cons_header">
    <div class="cons_header_wra">
        <div class="about_header_desc">
            <div class="cons_top">
                <h1><?php echo htmlspecialchars($item->title, ENT_QUOTES, 'UTF-8'); ?></h1>
            </div>
            <div class="cons_btm">
                <?php if (trim(strip_tags($item->introtext)) !== '') : ?>
                    <?php echo $item->introtext; ?>
                <?php endif; ?>
                <div class="header_actions">
                  <a href="#"><button class="main_btn"><?php echo Text::_('Даведацца Больш'); ?></button></a>
                  <a href="#"><button class="main_btn line_btn"><?php echo Text::_('Спампаваць PDF'); ?></button></a>
                </div>
                <div class="cons_header_desc">
                    <p>Невялікі тэкст пра бяспеку. Прастора – гэта адкрытая платформа, дзе беларусы могуць <br />удзельнічаць у распрацоўцы законаў, рэформаў і Канстытуцыі новай Беларусі.</p>
                </div>
            </div>
        </div>
        <img class="cons_img" src="<?php echo $tplUri; ?>/assets/images/cons-img.png" alt="" />
    </div>
    <div class="cons_header_desc">
        <p>Невялікі тэкст пра бяспеку. Прастора – гэта адкрытая платформа, дзе беларусы могуць <br />удзельнічаць у распрацоўцы законаў, рэформаў і Канстытуцыі новай Беларусі.</p>
    </div>
</div>

<div class="cons_articles container">
    <div class="articles_list_block">
        <div class="articles_list_block_top">
            <div class="nav_search">
                <img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
                <input id="tocSearch" type="text" placeholder="Пошук па Зместу..." />
            </div>
            <div class="info"><img src="<?php echo $tplUri; ?>/assets/icons/info.svg" alt="" /></div>
        </div>
        <div class="articles_list">
            <h3>Змест</h3>
            <?php foreach ($sections as $secIdx => $sec) : ?>
            <div class="articles_list_item<?php echo $secIdx === 0 ? ' articles_list_item_active' : ''; ?>">
                <div class="articles_list_item_title" data-target="<?php echo htmlspecialchars($sec['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <p><?php echo htmlspecialchars($sec['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <img src="<?php echo $tplUri; ?>/assets/icons/article-icon.svg" alt="" />
                </div>
                <?php if (!empty($sec['articles'])) : ?>
                <div class="articles_list_item_desc">
                  <?php foreach ($sec['articles'] as $article) : ?>
                    <p data-target="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($article['title'], ENT_QUOTES, 'UTF-8'); ?></p>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="article_block" id="constitution-content">
        <?php foreach ($sections as $sec) : ?>
        <?php foreach ($sec['articles'] as $articleIndex => $article) : ?>
        <div class="article_block_item" id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>">
            <?php if ($articleIndex === 0) : ?>
            <div class="article_chapter_title" id="<?php echo htmlspecialchars($sec['id'], ENT_QUOTES, 'UTF-8'); ?>">
                <h2><?php echo $sec['titleHtml']; ?></h2>
                <?php if (!empty($sec['introHtml'])) : ?>
                <div class="article_chapter_intro"><?php echo $sec['introHtml']; ?></div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="article_block_item_title">
                <h4><?php echo $article['titleHtml']; ?></h4>
                <div class="article_reactions" data-article-id="<?php echo (int) $item->id; ?>" data-section-id="<?php echo htmlspecialchars($article['id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <div class="article_reaction" data-role="reaction-like"><img src="<?php echo $tplUri; ?>/assets/icons/like.svg" alt="" /><p data-role="count-like">0</p></div>
                    <div class="article_reaction" data-role="reaction-dislike"><img src="<?php echo $tplUri; ?>/assets/icons/dislike.svg" alt="" /><p data-role="count-dislike">0</p></div>
                    <div class="article_reaction"><img src="<?php echo $tplUri; ?>/assets/icons/comment.svg" alt="" /><p>0</p></div>
                    <div class="article_reaction article_commenting"><p><?php echo Text::_('Каментаваць'); ?></p></div>
                </div>
            </div>
            <div class="article_block_item_content">
                <?php echo $article['html']; ?>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endforeach; ?>
        <div class="article_block_info">
            <img src="<?php echo $tplUri; ?>/assets/icons/arc-info.svg" alt="" />
            <p>1. Чалавек, яго правы і свабоды з'яўляюцца найвышэйшай каштоўнасцю. Галоўны абавязак дзяржавы - прызнанне, выкананне, забеспячэнне і абарона правоў і свабод чалавека, стварэнне ўмоў для шчаслівага жыцця, свабоднага і годнага развіцця асобы.</p>
        </div>
    </div>

    <div class="comments_block">
        <div class="auth_comment">
            <input type="text" placeholder="Ананімны Карыстальнік" />
            <input type="text" placeholder="Дадаць пошту" />
            <input type="text" placeholder="На маю думку тут лепей казаць што..." />
            <div class="send_btn"><button class="main_btn">Каментаваць</button></div>
        </div>
    </div>
</div>

<div class="fixed_nav">
    <div class="fixed_nav_left">
        <div class="fixed_nav_item"><div><img src="<?php echo $tplUri; ?>/assets/icons/f-nav-1.svg" alt="" /></div><p>Змест</p></div>
        <div class="fixed_nav_item"><div><img src="<?php echo $tplUri; ?>/assets/icons/f-nav-2.svg" alt="" /></div><p>Дапамога</p></div>
    </div>
    <div class="fixed_nav_item"><div><img src="<?php echo $tplUri; ?>/assets/icons/f-nav-3.svg" alt="" /></div><p>Усе Каментары</p></div>
</div>

<script>
(function(){
    const toc = document.querySelector('.articles_list');
    const search = document.getElementById('tocSearch');
    const titleItems = toc ? toc.querySelectorAll('.articles_list_item_title') : [];
    const subItems = toc ? toc.querySelectorAll('.articles_list_item_desc p') : [];
    const articleContainer = document.getElementById('constitution-content');

    function scrollToId(id){
        const target = document.getElementById(id);
        if (!target) {
            return;
        }
        if (articleContainer) {
            const offset = target.getBoundingClientRect().top - articleContainer.getBoundingClientRect().top + articleContainer.scrollTop;
            articleContainer.scrollTo({ top: offset, behavior: 'smooth' });
        } else {
            window.scrollTo({ top: target.getBoundingClientRect().top + window.pageYOffset - 80, behavior: 'smooth' });
        }
    }

    titleItems.forEach(function(el){
        el.addEventListener('click', function(){ scrollToId(this.getAttribute('data-target')); });
    });
    subItems.forEach(function(el){
        el.addEventListener('click', function(){ scrollToId(this.getAttribute('data-target')); });
    });

    if (search) {
        search.addEventListener('input', function(){
            const q = this.value.toLowerCase();
            toc.querySelectorAll('.articles_list_item').forEach(function(li){
                const t = (li.querySelector('.articles_list_item_title p')?.textContent || '').toLowerCase();
                li.style.display = t.indexOf(q) !== -1 ? '' : 'none';
            });
        });
    }
    // Reactions with localStorage per article+section
    const reactionBlocks = document.querySelectorAll('.article_reactions');

    const extractRows = (json) => {
        if (!json) { return {}; }
        if (json.rows) { return json.rows; }
        if (json.data && json.data.rows) { return json.data.rows; }
        return {};
    };

    reactionBlocks.forEach(function(block){
        const aid = block.getAttribute('data-article-id');
        const sid = block.getAttribute('data-section-id');
        const likeCount = block.querySelector('[data-role="count-like"]');
        const dislikeCount = block.querySelector('[data-role="count-dislike"]');
        const likeButton = block.querySelector('[data-role="reaction-like"]');
        const dislikeButton = block.querySelector('[data-role="reaction-dislike"]');
        // initial fetch
        fetch(`index.php?option=com_ajax&plugin=reactions&format=json&group=ajax&task=get&article_id=${aid}&sections=${encodeURIComponent(sid)}`, { credentials: 'same-origin' })
            .then(r=>r.json()).then(json=>{
                if (json && json.ok === false && json.error) {
                    console.warn('Reactions fetch error', json.error);
                    return;
                }
                const rows = extractRows(json);
                const row = rows && rows[sid] ? rows[sid] : null;
                likeCount.textContent = row ? (row.likes ?? 0) : 0;
                dislikeCount.textContent = row ? (row.dislikes ?? 0) : 0;
            }).catch(()=>{});
        function vote(type){
            const form = new FormData();
            form.append('option','com_ajax');
            form.append('plugin','reactions');
            form.append('group','ajax');
            form.append('format','json');
            form.append('<?php echo Factory::getApplication()->getFormToken(); ?>', 1);
            form.append('task','vote');
            form.append('article_id', aid);
            form.append('section_id', sid);
            form.append('type', type);
            fetch('index.php', { method:'POST', body: form, credentials:'same-origin' })
                .then(r=>r.json()).then(json=>{
                    if (json && json.ok) {
                        if (typeof json.likes !== 'undefined') {
                            likeCount.textContent = json.likes;
                        }
                        if (typeof json.dislikes !== 'undefined') {
                            dislikeCount.textContent = json.dislikes;
                        }
                        return;
                    }
                    if (json && json.error === 'already_voted') {
                        alert('Вы ўжо галасавалі за гэты раздзел.');
                    }
                }).catch(()=>{});
        }
        if (likeButton) {
            likeButton.addEventListener('click', function(){ vote('like'); });
        }
        if (dislikeButton) {
            dislikeButton.addEventListener('click', function(){ vote('dislike'); });
        }
    });
})();
</script>
