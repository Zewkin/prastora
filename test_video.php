<?php
define('_JEXEC', 1);
define('JPATH_BASE', __DIR__ . '/joomla');
require JPATH_BASE . '/includes/defines.php';
require JPATH_BASE . '/includes/framework.php';

use Joomla\CMS\Factory;
use Joomla\CMS\Cache\CacheControllerFactoryInterface;
use Joomla\CMS\Http\HttpFactory;

Factory::getApplication('site');

function prastoraFetchLatestVideo(string $channelId, int $cacheMinutes = 60): array
{
    $channelId = trim($channelId);
    if ($channelId === '') {
        return [];
    }
    $cacheMinutes = max(5, $cacheMinutes);
    try {
        $cacheFactory = Factory::getContainer()->get(CacheControllerFactoryInterface::class);
        $cache = $cacheFactory->createCacheController('callback', ['defaultgroup' => 'tpl_prastora_video', 'lifetime' => $cacheMinutes * 60]);
        $callback = function (string $channelId) {
            $http = HttpFactory::getHttp();
            $feedUrl = 'https://www.youtube.com/feeds/videos.xml?channel_id=' . rawurlencode($channelId);
            $response = $http->get($feedUrl);
            if ($response->code !== 200 || empty($response->body)) {
                return [];
            }
            $xml = @simplexml_load_string($response->body);
            if (!$xml) {
                return [];
            }
            $xml->registerXPathNamespace('yt', 'http://www.youtube.com/xml/schemas/2015');
            $xml->registerXPathNamespace('media', 'http://search.yahoo.com/mrss/');
            $entry = $xml->entry[0] ?? null;
            if (!$entry) {
                return [];
            }
            $yt = $entry->children('yt', true);
            $media = $entry->children('media', true);
            $group = $media ? $media->group : null;

            $videoId = isset($yt->videoId) ? (string) $yt->videoId : '';
            if ($videoId === '') {
                return [];
            }

            $title = (string) ($entry->title ?? '');
            $description = '';
            if ($group && isset($group->description)) {
                $description = (string) $group->description;
            } elseif (isset($entry->summary)) {
                $description = (string) $entry->summary;
            }

            $thumbnail = '';
            if ($group && isset($group->thumbnail)) {
                $thumbAttributes = $group->thumbnail[0]->attributes();
                if ($thumbAttributes && isset($thumbAttributes['url'])) {
                    $thumbnail = (string) $thumbAttributes['url'];
                }
            }

            $videoUrl = '';
            if (isset($entry->link)) {
                foreach ($entry->link as $link) {
                    $attrs = $link->attributes();
                    if ($attrs && (string) $attrs['rel'] === 'alternate') {
                        $videoUrl = (string) $attrs['href'];
                        break;
                    }
                }
            }
            if ($videoUrl === '') {
                $videoUrl = 'https://www.youtube.com/watch?v=' . $videoId;
            }

            $channelUrl = '';
            if (isset($entry->author) && isset($entry->author->uri)) {
                $channelUrl = (string) $entry->author->uri;
            }

            return [
                'videoId' => $videoId,
                'title' => $title,
                'description' => $description,
                'thumbnail' => $thumbnail,
                'url' => $videoUrl,
                'channelUrl' => $channelUrl,
            ];
        };
        $data = $cache->call($callback, [$channelId]);
        if (!is_array($data)) {
            $data = [];
        }
    } catch (\Throwable $e) {
        file_put_contents(__DIR__.'/test_video_error.log', $e->getMessage());
        $data = [];
    }
    return $data;
}

$result = prastoraFetchLatestVideo('UCcCJ5p6hQi1n-xaYkSMILXg', 60);
var_dump($result);
