<?php

/*
 * aqui defino todas las variables globales
 * para poder recoger en cualquier plantilla twig del bundle
 */

namespace Xmon\SonataMediaProviderVideoBundle\Twig\Extension;

use Sonata\MediaBundle\Provider\Pool;

/**
 * Description of GlobalsExtension
 *
 * @author Juanjo García <juanjogarcia@editartgroup.com>
 */

class GlobalsExtension extends \Twig_Extension {

    protected $mediaService;
    
    public function getFilters()
    {
        return array(
            new \Twig_SimpleFilter('video_mp4', array($this, 'videoFormatMp4')),
            new \Twig_SimpleFilter('video_ogg', array($this, 'videoFormatOgg')),
            new \Twig_SimpleFilter('video_webm', array($this, 'videoFormatWebm'))
        );
    }

    /**
     * 
     * @param type $width
     * @param Pool $mediaService
     */
    public function __construct($width, Pool $mediaService) {
        $this->width = $width;
        $this->mediaService = $mediaService;
    }

    public function videoFormatMp4($media)
    {
        $provider = $this
            ->getMediaService()
            ->getProvider($media->getProviderName());
        
        return $provider->generatePublicUrl($media, "videos_mp4");
    }
    
    public function videoFormatOgg($media)
    {
        $provider = $this
            ->getMediaService()
            ->getProvider($media->getProviderName());
        
        return $provider->generatePublicUrl($media, "videos_ogg");
    }
    
    public function videoFormatWebm($media)
    {
        $provider = $this
            ->getMediaService()
            ->getProvider($media->getProviderName());
        
        return $provider->generatePublicUrl($media, "videos_webm");
    }

    public function getGlobals() {

        return array(
            'width' => $this->width
        );
    }

    /**
     * @return \Sonata\MediaBundle\Provider\Pool
     */
    public function getMediaService()
    {
        return $this->mediaService;
    }

    public function getName() {
        return 'SonataMediaProviderVideoBundle:GlobalsExtension';
    }

}
