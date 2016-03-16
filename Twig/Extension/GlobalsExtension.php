<?php

/*
 * aqui defino todas las variables globales
 * para poder recoger en cualquier plantilla twig del bundle
 */

namespace Xmon\SonataMediaProviderVideoBundle\Twig\Extension;

/**
 * Description of GlobalsExtension
 *
 * @author Juanjo García <juanjogarcia@editartgroup.com>
 */

class GlobalsExtension extends \Twig_Extension {

    public function __construct($width) {
        $this->width = $width;
    }

    public function getGlobals() {

        return array(
            'width' => $this->width
        );
    }

    public function getName() {
        return 'SonataMediaProviderVideoBundle:GlobalsExtension';
    }

}
