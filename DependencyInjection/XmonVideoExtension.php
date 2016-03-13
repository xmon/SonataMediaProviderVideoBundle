<?php

namespace Xmon\SonataMediaProviderVideoBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\DependencyInjection\Loader;

/**
 * This is the class that loads and manages your bundle configuration
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html}
 */
class XmonVideoExtension extends Extension
{
    /**
     * {@inheritDoc}
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new Loader\YamlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('providers.yml');
        
        $loader = new Loader\XmlFileLoader($container, new FileLocator(__DIR__.'/../Resources/config'));
        $loader->load('services.xml');

        $container->setParameter('xmon_ffmpeg.binary', $config['ffmpeg_binary']);
        $container->setParameter('xmon_ffprobe.binary', $config['ffprobe_binary']);
        $container->setParameter('xmon_ffmpeg.binary_timeout', $config['binary_timeout']);
        $container->setParameter('xmon_ffmpeg.threads_count', $config['threads_count']);
        $container->setParameter('xmon_ffmpeg.image_frame', $config['config.image_frame']);
        $container->setParameter('xmon_ffmpeg.mp4', $config['config.mp4']);
        $container->setParameter('xmon_ffmpeg.ogg', $config['config.ogg']);
        $container->setParameter('xmon_ffmpeg.webm', $config['config.webm']);
        
    }
}
