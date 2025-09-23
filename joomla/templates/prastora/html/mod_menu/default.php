<?php
defined('_JEXEC') or die;

/** @var array $list */
/** @var Joomla\Registry\Registry $params */

// Render top-level items as flat links to match design
echo '<div class="nav_menu">';
foreach ($list as $item) {
    if ((int) ($item->level ?? 1) !== 1) {
        continue;
    }
    $link  = $item->flink ?? ($item->link ?? '#');
    $title = $item->title ?? '';
    echo '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '"><p>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</p></a>';
}
echo '</div>';



