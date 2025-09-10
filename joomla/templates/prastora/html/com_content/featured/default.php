<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Uri\Uri;

$app = Factory::getApplication();
$doc = $app->getDocument();
$template = $app->getTemplate();
$tplUri = rtrim(Uri::root(true), '/') . '/templates/' . $template;

// Подключаем Swiper (CSS/JS)
$doc->addStyleSheet('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css');
$doc->addScript('https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js');
?>
<div class="header_wrapper">
	<header class="main_header container">
		<nav>
			<div class="nav_wrapper">
				<div class="nav_left">
					<a href="/" class="logo"><img src="<?php echo $tplUri; ?>/assets/logo.svg" alt="" /></a>
					<div class="nav_menu">
						<a href="/constitution"><p>Канстытуцыя</p></a>
						<a href="/projects"><p>Праекты</p></a>
						<a href="/news"><p>Навіны</p></a>
						<a href="/about-us"><p>Пра нас</p></a>
						<a href="/faq"><p>FAQ</p></a>
					</div>
				</div>
				<div class="nav_right">
					<div class="nav_search">
						<img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
						<input type="text" placeholder="Пошук..." />
					</div>
					<button class="nav_btn">Donate</button>
					<button class="nav_btn lang_toggler">BEL</button>
				</div>
				<img onclick="toggleMenu()" class="mob_menu_btn" src="<?php echo $tplUri; ?>/assets/icons/mob-menu.svg" alt="" />
			</div>
		</nav>

		<div class="mob_menu">
			<div class="mob_exit">
				<img onclick="toggleMenu()" src="<?php echo $tplUri; ?>/assets/icons/exit.svg" alt="" />
			</div>
			<div class="mob_menu_content">
				<div class="nav_menu">
					<a href="/constitution"><p>Канстытуцыя</p></a>
					<a href="/projects"><p>Праекты</p></a>
					<a href="/news"><p>Навіны</p></a>
					<a href="/about-us"><p>Пра нас</p></a>
					<a href="/faq"><p>FAQ</p></a>
				</div>
				<div class="nav_search">
					<img src="<?php echo $tplUri; ?>/assets/icons/lupa.svg" alt="" />
					<input type="text" placeholder="Пошук..." />
				</div>
				<div class="mob_menu_actions">
					<button class="nav_btn">Donate</button>
					<button class="nav_btn lang_toggler">BEL</button>
				</div>
			</div>
		</div>

		<div class="header_content">
			<div class="header_main_title">
				<h2>мы верым што толькі разам мы</h2>
				<h1>Пабудуем Новую Беларусь</h1>
			</div>
			<div class="header_sec_content">
				<p>
					Далучайся да «Прасторы» – платформы, дзе твой голас фарміруе
					будучыню нашай краіны. Удзельнічай у змястоўных абмеркаваннях
					канстытуцыі Новай Беларусі альбо праектаў законаў, прапануй свае
					праўкі ў рэформах і сачы за апошнімі навінамі заканадаўства.
				</p>
				<div class="header_links">
					<a href="#"><div></div><span>Абмеркаваць Канстытуцыю</span></a>
					<a href="#"><div></div><span>Абмеркаваць Праекты</span></a>
				</div>
				<h5>
					Беражыце сябе і сваю інфармацыю — прытрымлівайцеся простых
					правілаў анлайн-бяспекі.
				</h5>
			</div>
		</div>
		<div class="header_btm">
			<p>
				Беражыце сябе і сваю інфармацыю — прытрымлівайцеся простых правілаў
				анлайн-бяспекі.
			</p>
			<div class="header_socials">
				<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" /></a>
				<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /></a>
				<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/yt.svg" alt="" /></a>
			</div>
		</div>
	</header>
</div>

<section class="moto_block container">
	<img src="<?php echo $tplUri; ?>/assets/images/moto-img.png" alt="" />
	<div class="moto_block_desc">
		<img src="<?php echo $tplUri; ?>/assets/icons/logo-icon.svg" alt="" />
		<p>Новая Беларусь — гэта не нешта абстрактнае. Гэта – мы.</p>
		<p>
			Мы стварылі гэтую прастору, каб вярнуць грамадству права голасу. Каб
			кожны мог свабодна, без страху і разам абмяркоўваць будучыню краіны –
			і ўплываць на яе.
		</p>
		<p>
			Наша мэта – пабудаваць сучасную, дэмакратычную Беларусь, дзе закон
			абараняе людзей, а ўлада – абмежаваная і адказная.
		</p>
	</div>
</section>

<div class="how_it_works">
	<div class="container">
		<div class="how_it_work_title">
			<h2>Як гэта працуе?</h2>
			<div class="how_it_work_descs">
				<p>
					На «Прасторы» вы можаце далучыцца да абмеркавання законаў,
					рэформаў і Канстытуцыі – адкрыта, ананімна і без складаных
					рэгістрацый.
				</p>
				<div>
					<img src="<?php echo $tplUri; ?>/assets/icons/Vector.svg" alt="" />
					<p>
						Усё, што вам трэба – гэта жаданне і зацікаўленасць сказаць сваё
						слова. Мы зрабілі працэс максімальна простым і зразумелым.
					</p>
				</div>
			</div>
		</div>
		<div class="howtw_cards">
			<div class="howtw_card">
				<div class="howtw_card_img">
					<img src="<?php echo $tplUri; ?>/assets/icons/howt-1.svg" alt="" />
				</div>
				<p>
					Выдзеліце тэкст і націсніце «плюс» альбо пакіньце каментар да ўсей
					часткі тэксту.
				</p>
				<span>Каб пакінуць свой каментар або прапанову, проста выдзеліце патрэбны фрагмент тэксту – і вы ўбачыце іконку “+”. Націсніце на яе, каб дадаць каментар канкрэтнага фрагмента. Таксама вы можаце пакінуць свой каментар да усяго кавалка тэксту націснув на “каментаваць”.</span>
			</div>
			<div class="howtw_card">
				<div class="howtw_card_img">
					<img src="<?php echo $tplUri; ?>/assets/icons/howt-2.svg" alt="" />
				</div>
				<p>Пакіньце сваю прапанову або каментар</p>
				<span>У адкрытым полі вы можаце ўвесці свой тэкст. Гэта можа быць удакладненне, ідэя, крытычнае заўвага або проста пытанне. Будзьце канкрэтнымі і зразумелымі – гэта дапамагае іншым хутчэй зарыентавацца.</span>
				<br />
				<br />
				<span>Калі вы пакінулі каментар да выдзеленага фрагмента, вы можаце вярнуцца да яго, націснуўшы на каментар або навёўшы на яго курсор.</span>
			</div>
			<div class="howtw_card">
				<div class="howtw_card_img">
					<img src="<?php echo $tplUri; ?>/assets/icons/howt-3.svg" alt="" />
				</div>
				<p>Абмяркуйце прапановы з іншымі ўдзельнікамі!</p>
				<span>Вы можаце адказваць на іншыя каментары, ставіць лайкі або дзяліцца сваім поглядам. Гэта адкрытае грамадскае абмеркаванне, і ваш голас – важны. Усё на самай справе так проста – і працуе!</span>
				<br />
				<br />
				<span>Калі ў вас засталісь пытанні то вы можаце знайсці адказы на старонцы FAQ, альбо напісаць нам!</span>
			</div>
		</div>

		<div class="swiper howt_car">
			<div class="swiper-wrapper">
				<div class="swiper-slide">
					<div class="howtw_card">
						<div class="howtw_card_img">
							<img src="<?php echo $tplUri; ?>/assets/icons/howt-1.svg" alt="" />
						</div>
						<p>Выдзеліце тэкст і націсніце «плюс» альбо пакіньце каментар да ўсей часткі тэксту.</p>
						<span>Каб пакінуць свой каментар або прапанову, проста выдзеліце патрэбны фрагмент...</span>
					</div>
				</div>
				<div class="swiper-slide">
					<div class="howtw_card">
						<div class="howtw_card_img">
							<img src="<?php echo $tplUri; ?>/assets/icons/howt-2.svg" alt="" />
						</div>
						<p>Пакіньце сваю прапанову або каментар</p>
						<span>У адкрытым полі вы можаце ўвесці свой тэкст...</span>
					</div>
				</div>
				<div class="swiper-slide">
					<div class="howtw_card">
						<div class="howtw_card_img">
							<img src="<?php echo $tplUri; ?>/assets/icons/howt-3.svg" alt="" />
						</div>
						<p>Абмяркуйце прапановы з іншымі ўдзельнікамі!</p>
						<span>Вы можаце адказваць на іншыя каментары...</span>
					</div>
				</div>
			</div>
		</div>

		<div class="swiper_pagination howt_car_pag"></div>
	</div>

	<div class="howt_btm">
		<div class="howt_btm_desc">
			<h4>Звярніце ўвагу: лічбавая бяспека – перадусім</h4>
			<p>У акне для каментарыяў вы можаце ўвесці свой e-mail, каб атрымаць апавяшчэнне пра адказ або статус вашага паведамлення. Гэта не абавязкова.</p>
			<p>Увядзіце пошту толькі калі ўпэўнены, што для вас гэта бяспечна. Мы клапоцімся пра вашу ананімнасць і даем магчымасць пакідаць каментары без аўтарызацыі.</p>
			<p>Памятайце: ваша бяспека – галоўнае.</p>
		</div>
	</div>
</div>

<section class="last_projects container">
	<div class="block_title"><h2>Апошнія праекты</h2></div>
	<div class="last_project_cards">
		<div class="last_project_card">
			<img class="last_project_card_bg" src="<?php echo $tplUri; ?>/assets/images/project-bg.svg" alt="" />
			<img src="<?php echo $tplUri; ?>/assets/icons/project-icon.svg" alt="" />
			<div class="last_project_card_descs">
				<div>
					<p>Канстытуцыя Новай Беларусі (першая версія)</p>
					<span>Невялікае апісанне праекта . Прастора – гэта адкрытая платформа, дзе беларусы могуць удзельнічаць у распрацоўцы законаў, рэформаў і Канстытуцыі новай Беларусі.</span>
				</div>
				<a href="/news"><img src="<?php echo $tplUri; ?>/assets/icons/link.svg" alt="" /></a>
			</div>
			<div class="last_project_card_slice"></div>
		</div>
		<div class="last_project_card">
			<img class="last_project_card_bg" src="<?php echo $tplUri; ?>/assets/images/project-bg.svg" alt="" />
			<img src="<?php echo $tplUri; ?>/assets/icons/project-icon.svg" alt="" />
			<div class="last_project_card_descs">
				<div>
					<p>Канстытуцыя Новай Беларусі</p>
					<span>Невялікае апісанне праекта . Прастора – гэта адкрытая платформа, дзе беларусы могуць удзельнічаць у распрацоўцы законаў, рэформаў і Канстытуцыі новай Беларусі.</span>
				</div>
				<a href="/news"><img src="<?php echo $tplUri; ?>/assets/icons/link.svg" alt="" /></a>
			</div>
			<div class="last_project_card_slice"></div>
		</div>
	</div>
	<div class="swiper last_project_car">
		<div class="swiper-wrapper">
			<div class="swiper-slide"><div class="last_project_card"></div></div>
			<div class="swiper-slide"><div class="last_project_card"></div></div>
		</div>
	</div>
	<div class="swiper_pagination last_project_car_pag"></div>
	<div class="corner_btn">
		<a href="#"><button class="line_btn">Больш праектаў <img src="<?php echo $tplUri; ?>/assets/icons/arrow-top-right.svg" alt="" /></button></a>
	</div>
</section>

<section class="news_block container">
	<div class="block_title"><h2>Навіны</h2></div>
	<div class="news_cards">
		<?php
			$db = Joomla\CMS\Factory::getDbo();
			$query = $db->getQuery(true)
				->select(['a.id','a.title','a.alias','a.introtext','a.catid','a.publish_up','a.images'])
				->from('#__content AS a')
				->where('a.state = 1')
				->order('a.publish_up DESC')
				->setLimit(3);
			$db->setQuery($query);
			$rows = $db->loadObjectList() ?: [];
			foreach ($rows as $row) {
				$images = json_decode($row->images ?: '{}');
				$link  = Joomla\CMS\Router\Route::_(Joomla\Component\Content\Site\Helper\RouteHelper::getArticleRoute($row->id . ':' . $row->alias, (int)$row->catid));
				// Определяем фон карточки из intro image или плейсхолдер
				$bg = $tplUri . '/assets/images/news-img.png';
				if (!empty($images->image_intro)) {
					$introPath = $images->image_intro;
					if (strpos($introPath, 'http') === 0 || strpos($introPath, '//') === 0 || strpos($introPath, '/') === 0) {
						$bg = $introPath;
					} else {
						$bg = rtrim(Joomla\CMS\Uri\Uri::root(true), '/') . '/' . ltrim($introPath, '/');
					}
				}
		?>
		<div class="news_card" style="background-image:url('<?php echo htmlspecialchars($bg, ENT_QUOTES, 'UTF-8'); ?>'); background-size:cover; background-position:center;">
			<div class="news_card_desc">
				<h4><a href="<?php echo $link; ?>"><?php echo htmlspecialchars($row->title, ENT_QUOTES, 'UTF-8'); ?></a></h4>
				<p><?php echo JHtml::_('date', $row->publish_up, 'Y-m-d'); ?></p>
				<span><a href="<?php echo $link; ?>"><?php echo strip_tags($row->introtext); ?></a></span>
			</div>
			<a href="<?php echo $link; ?>" class="card_link"><img src="<?php echo $tplUri; ?>/assets/icons/link-red.svg" alt="" /></a>
		</div>
		<?php } ?>
	</div>
	<div class="swiper news_cards_car">
		<div class="swiper-wrapper">
			<div class="swiper-slide"><div class="news_card news_card1"></div></div>
			<div class="swiper-slide"><div class="news_card news_card2"></div></div>
			<div class="swiper-slide"><div class="news_card news_card3"></div></div>
		</div>
	</div>
	<div class="swiper_pagination news_cards_car_pag"></div>
	<div class="corner_btn">
		<a href="/news"><button class="line_btn">Усе Навіны <img src="<?php echo $tplUri; ?>/assets/icons/arrow-top-right.svg" alt="" /></button></a>
	</div>
</section>

<div class="video_block">
	<div class="video_block_wrapper">
		<div class="video_player">
			<div class="video_play_btn"><img src="<?php echo $tplUri; ?>/assets/icons/play.svg" alt="" /></div>
		</div>
		<div class="video_block_desc">
			<div>
				<p>Назва Відэа</p>
				<span>Невялікае апісанне відэа. Прастора – гэта адкрытая платформа, дзе беларусы могуць удзельнічаць у распрацоўцы законаў, рэформаў і Канстытуцыі новай Беларусі.</span>
			</div>
			<button class="line_btn">YouTube <img src="<?php echo $tplUri; ?>/assets/icons/arrow-top-right.svg" alt="" /></button>
		</div>
		<div class="video_copyright"><p>*Беражыце сябе і сваю інфармацыю — прытрымлівайцеся простых правілаў анлайн-бяспекі.</p></div>
	</div>
</div>

<section class="partners_block container">
	<div class="block_title"><h2>Партнеры Праекта</h2></div>
	<div class="partners_list">
		<a href="#"><img src="<?php echo $tplUri; ?>/assets/images/partner-1.png" alt="" /></a>
		<a href="#"><img src="<?php echo $tplUri; ?>/assets/images/partner-2.png" alt="" /></a>
		<a href="#"><img src="<?php echo $tplUri; ?>/assets/images/partner-1.png" alt="" /></a>
		<a href="#"><img src="<?php echo $tplUri; ?>/assets/images/partner-2.png" alt="" /></a>
	</div>
</section>

<footer>
	<div class="footer_wrapper container">
		<div class="footer_main">
			<div class="footer_main_left">
				<a href="/" class="footer_logo">
					<img src="<?php echo $tplUri; ?>/assets/icons/project-icon.svg" alt="" />
				</a>
				<div class="footer_email">
					<h4>Ёсць пытанні або думкі?</h4>
					<p>Напішыце нам – мы заўсёды гатовыя <br />выслухаць і адказаць.</p>
					<a href="mailto:hello@prastora.online"><span>hello@prastora.online</span></a>
				</div>
				<div class="footer_contacts">
					<h4>Кантакты:</h4>
					<a href="mailto:hello@prastora.online">
						<img src="<?php echo $tplUri; ?>/assets/icons/mail.svg" alt="" />
						<p>hello@prastora.online</p>
					</a>
					<a href="https://t.me/konstituciya_bel">
						<img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" />
						<p>@konstituciya_bel</p>
					</a>
					<a href="#">
						<img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" />
						<p>Канстытуцыя Рэспублікі Беларусь</p>
					</a>
				</div>
			</div>
			<div class="header_socials">
				<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/fb.svg" alt="" /></a>
				<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/tg.svg" alt="" /></a>
				<a href="#"><img src="<?php echo $tplUri; ?>/assets/icons/yt.svg" alt="" /></a>
			</div>
		</div>

		<div class="footer_btm">
			<p>© 2021, Светлана Тихановская</p>
			<a href="/privacy-policy"><p>Privacy Policy</p></a>
		</div>
	</div>
</footer>

<script>
	new Swiper('.howt_car', { slidesPerView: 1, spaceBetween: 10, pagination: { el: '.howt_car_pag', clickable: true } });
	new Swiper('.last_project_car', { slidesPerView: 1, spaceBetween: 10, pagination: { el: '.last_project_car_pag', clickable: true } });
	new Swiper('.news_cards_car', { slidesPerView: 1, spaceBetween: 10, pagination: { el: '.news_cards_car_pag', clickable: true } });
</script>
