<?php
defined('_JEXEC') or die;

use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\Component\Commentmoderation\Administrator\Extension\CommentmoderationComponent;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;

return new class () implements ServiceProviderInterface {
    public function register(Container $container)
    {
        \JLoader::registerNamespace('Joomla\\Component\\Commentmoderation', JPATH_ADMINISTRATOR . '/components/com_commentmoderation/src');

        $container->registerServiceProvider(new MVCFactory('\\Joomla\\Component\\Commentmoderation'));
        $container->registerServiceProvider(new ComponentDispatcherFactory('\\Joomla\\Component\\Commentmoderation'));

        $container->set(
            ComponentInterface::class,
            function (Container $container) {
                $component = new CommentmoderationComponent($container->get(ComponentDispatcherFactoryInterface::class));
                $component->setMVCFactory($container->get(MVCFactoryInterface::class));

                return $component;
            }
        );
    }
};
