<?php

namespace Xmon\SonataMediaProviderVideoBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

/**
 * This is the class that validates and merges configuration from your app/config files
 *
 * To learn more see {@link http://symfony.com/doc/current/cookbook/bundles/extension.html#cookbook-bundles-extension-config-class}
 */
class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritDoc}
     */
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('xmon_video');

        $rootNode
                ->children()
                    ->scalarNode('ffmpeg_binary')
                        ->isRequired()
                    ->end()
                    ->scalarNode('ffprobe_binary')
                        ->isRequired()
                    ->end()
                    ->scalarNode('binary_timeout')
                        ->defaultValue(60)
                    ->end()
                    ->scalarNode('threads_count')
                        ->defaultValue(4)
                    ->end()
                    ->integerNode('image_frame')
                        ->info('second from extract image.')
                        ->cannotBeEmpty()
                        ->defaultValue(10)
                    ->end()
                ->end();

        // Here you should define the parameters that are allowed to
        // configure your bundle. See the documentation linked above for
        // more information on that topic.

        return $treeBuilder;
    }
}
