START TRANSACTION;

INSERT INTO `jo8cq_content` (`title`,`alias`,`introtext`,`fulltext`,`state`,`catid`,`created`,`created_by`,`language`,`access`,`modified`,`modified_by`)
SELECT 'Канстытуцыя Новай Беларусі','constitution','','<h4>Артыкул 1. Рэспубліка Беларусь</h4>',1,2,NOW(),585,'*',1,NOW(),585
WHERE NOT EXISTS (SELECT 1 FROM `jo8cq_content` WHERE `alias`='constitution');

SET @aid := (SELECT `id` FROM `jo8cq_content` WHERE `alias`='constitution' ORDER BY `id` DESC LIMIT 1);
SET @cc  := (SELECT `extension_id` FROM `jo8cq_extensions` WHERE `element`='com_content' AND `type`='component' LIMIT 1);

SET @maxrgt := (SELECT COALESCE(MAX(`rgt`),0) FROM `jo8cq_menu` WHERE `menutype`='mainmenu');
SET @lft := @maxrgt + 1; SET @rgt := @maxrgt + 2;

INSERT INTO `jo8cq_menu` (`menutype`,`title`,`alias`,`note`,`path`,`link`,`type`,`published`,`parent_id`,`level`,`component_id`,`browserNav`,`access`,`template_style_id`,`params`,`lft`,`rgt`,`home`,`language`,`client_id`)
SELECT 'mainmenu','Канстытуцыя','constitution','', 'constitution', CONCAT('index.php?option=com_content&view=article&id=', @aid), 'component', 1, 1, 1, @cc, 0, 1, 0, '', @lft, @rgt, 0, '*', 0
WHERE NOT EXISTS (SELECT 1 FROM `jo8cq_menu` WHERE `menutype`='mainmenu' AND `alias`='constitution');

COMMIT;
