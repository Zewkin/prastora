<?php
/**
 * Language switcher helper and renderer.
 */

use Joomla\CMS\Factory;
use Joomla\CMS\Language\LanguageHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\Registry\Registry;

defined('_JEXEC') or die;

if (!function_exists('prastoraGetLanguageOptions')) {
	/**
	 * Collects available languages and their links using Joomla's languages module helper when possible.
	 *
	 * @return array<int, array{code:string, sef:string, title:string, active:bool, link:string}>
	 */
	function prastoraGetLanguageOptions(): array
	{
		static $cache;

		if ($cache !== null) {
			return $cache;
		}

		$app       = Factory::getApplication();
		$languages = [];
		$list      = [];

		try {
			$module = $app->bootModule('mod_languages', 'site');
			$params = new Registry($module->params ?? '{}');
			$helper = $module->getHelper('LanguagesHelper');
			$list   = $helper->getLanguages($params);
		} catch (\Throwable $exception) {
			$list = [];
		}

		if (empty($list)) {
			$list = LanguageHelper::getLanguages();

			foreach ($list as $item) {
				$langCode             = $item->lang_code ?? $item->language ?? '';
				$item->lang_code      = $langCode;
				$item->sef            = $item->sef ?? ($langCode ? strtolower(substr($langCode, 0, 2)) : '');
				$item->title_native   = $item->title_native ?? $item->title ?? $langCode;
				$item->active         = $langCode === $app->getLanguage()->getTag();
				$item->link           = Route::_('index.php?lang=' . ($item->sef ?? ''));
			}
		}

		foreach ($list as $item) {
			$code = $item->lang_code ?? '';

			if ($code === '') {
				continue;
			}

			$languages[] = [
				'code'   => $code,
				'sef'    => (string) ($item->sef ?? ''),
				'title'  => (string) ($item->title_native ?? $item->title ?? $code),
				'active' => !empty($item->active),
				'link'   => (string) ($item->link ?? '#'),
			];
		}

		return $cache = $languages;
	}
}

if (!function_exists('prastoraGetLanguageLabel')) {
	/**
	 * Returns a short label for the provided language code.
	 */
	function prastoraGetLanguageLabel(string $code, string $fallback = ''): string
	{
		static $map = [
			'be-BY' => 'BEL',
			'ru-RU' => 'RUS',
			'en-GB' => 'ENG',
		];

		if (isset($map[$code])) {
			return $map[$code];
		}

		if ($fallback !== '') {
			return $fallback;
		}

		$normalized = strtoupper(str_replace(['_', '-'], '', $code));

		return substr($normalized, 0, 3);
	}
}

if (!function_exists('prastoraRenderLanguageSwitcher')) {
	/**
	 * Outputs the language switcher markup (button + dropdown).
	 * For mobile contexts (ending with '-mobile' or equal to 'mobile'), renders as individual buttons.
	 */
	function prastoraRenderLanguageSwitcher(string $context = ''): void
	{
		$app         = Factory::getApplication();
		$currentTag  = $app->getLanguage()->getTag();
		$currentLabel = prastoraGetLanguageLabel($currentTag);
		$languages   = prastoraGetLanguageOptions();

		// Check if this is a mobile context (render as buttons instead of dropdown)
		$isMobile = $context === 'mobile' || str_ends_with($context, '-mobile');

		if (empty($languages)) {
			echo '<span class="nav_btn lang_toggler" aria-hidden="true">' . htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8') . '</span>';

			return;
		}

		// Mobile: render as individual buttons
		if ($isMobile) {
			echo '<div class="lang_buttons_mobile">';

			foreach ($languages as $language) {
				$optionLabel = prastoraGetLanguageLabel(
					$language['code'],
					strtoupper($language['sef'] ?: substr($language['code'], 0, 3))
				);
				$activeClass = $language['active'] ? ' is-active' : '';

				echo '<a class="nav_btn lang_btn_mobile' . $activeClass . '" href="' . htmlspecialchars($language['link'], ENT_QUOTES, 'UTF-8') . '">';
				echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8');
				echo '</a>';
			}

			echo '</div>';

			return;
		}

		// Desktop: render as dropdown
		$uid = 'lang-switcher-' . substr(md5($context . $currentTag . count($languages)), 0, 8);

		echo '<div class="lang_switcher" data-lang-switcher>';
		echo '<button class="nav_btn lang_toggler" type="button" data-lang-toggle aria-haspopup="true" aria-expanded="false" aria-controls="' . $uid . '">';
		echo htmlspecialchars($currentLabel, ENT_QUOTES, 'UTF-8');
		echo '</button>';
		echo '<div class="lang_dropdown" id="' . $uid . '" data-lang-dropdown hidden>';

		foreach ($languages as $language) {
			$optionLabel = prastoraGetLanguageLabel(
				$language['code'],
				strtoupper($language['sef'] ?: substr($language['code'], 0, 3))
			);
			$activeClass = $language['active'] ? ' is-active' : '';

			echo '<a class="lang_option' . $activeClass . '" href="' . htmlspecialchars($language['link'], ENT_QUOTES, 'UTF-8') . '">';
			echo '<span>' . htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8') . '</span>';
			echo '</a>';
		}

		echo '</div>';
		echo '</div>';
	}
}

if (!function_exists('prastoraRegisterScriptTranslations')) {
	function prastoraRegisterScriptTranslations(): void
	{
		static $loaded = false;

		if ($loaded) {
			return;
		}

		$loaded = true;

		$keys = [
			'TPL_PRASTORA_JS_REPLY_LABEL',
			'TPL_PRASTORA_JS_SELECTION_HINT',
			'TPL_PRASTORA_JS_REPLY_CONTEXT',
			'TPL_PRASTORA_JS_COMMENT_CONTEXT',
			'TPL_PRASTORA_JS_AUTHOR_ANONYMOUS',
			'TPL_PRASTORA_JS_ACTION_REPLY',
			'TPL_PRASTORA_JS_COMMENTS_ALL',
			'TPL_PRASTORA_JS_COMMENTS_FILTERED',
			'TPL_PRASTORA_JS_ERROR_USER',
			'TPL_PRASTORA_JS_ERROR_EMAIL',
			'TPL_PRASTORA_JS_ERROR_EMPTY',
			'TPL_PRASTORA_JS_ERROR_SELECTION',
			'TPL_PRASTORA_JS_ERROR_PARENT',
			'TPL_PRASTORA_JS_ERROR_CAPTCHA_OFF',
			'TPL_PRASTORA_JS_ERROR_CAPTCHA',
			'TPL_PRASTORA_JS_ERROR_GENERIC',
			'TPL_PRASTORA_JS_PENDING_NOTICE',
		];

		foreach ($keys as $key) {
			Text::script($key);
		}
	}
}

