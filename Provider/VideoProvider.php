<?php

namespace Xmon\SonataMediaProviderVideoBundle\Provider;

//use Symfony\Component\HttpFoundation\File\UploadedFile;
//use Symfony\Component\HttpFoundation\Response;
//use Symfony\Component\HttpFoundation\StreamedResponse;
//use Symfony\Component\Form\FormBuilder;
//use Symfony\Component\Form\Form;
//use Sonata\AdminBundle\Validator\ErrorElement;
//use Sonata\MediaBundle\Entity\BaseMedia as Media;
//use Gaufrette\Adapter\Local;
use Sonata\MediaBundle\Provider\FileProvider;
use Sonata\MediaBundle\Model\MediaInterface;
use Sonata\MediaBundle\Resizer\ResizerInterface;
use Sonata\CoreBundle\Model\Metadata;
use Sonata\MediaBundle\CDN\CDNInterface;
use Sonata\MediaBundle\Generator\GeneratorInterface;
use Sonata\MediaBundle\Thumbnail\ThumbnailInterface;
use Sonata\MediaBundle\Metadata\MetadataBuilderInterface;
use Sonata\AdminBundle\Form\FormMapper;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\NotNull;
use Doctrine\ORM\EntityManager;
use Gaufrette\Filesystem;
use FFMpeg\FFMpeg;
use FFMpeg\FFProbe;
use FFMpeg\Coordinate\TimeCode;
use FFMpeg\Coordinate\Dimension;
use FFMpeg\Format\Video;
use Symfony\Component\DependencyInjection\ContainerInterface as Container;
use GetId3\GetId3Core as GetId3;

class VideoProvider extends FileProvider {

    protected $allowedExtensions;
    protected $allowedMimeTypes;
    protected $metadata;
    protected $getId3;
    protected $ffprobe;
    protected $ffmpeg;
    protected $container;
    protected $configImageFrame;
    protected $configVideoWidth;
    protected $configMp4;
    protected $configOgg;
    protected $configWebm;
    protected $entityManager;
    protected $thumbnail;

    /**
     * @param string                                                $name
     * @param \Gaufrette\Filesystem                                 $filesystem
     * @param \Sonata\MediaBundle\CDN\CDNInterface                  $cdn
     * @param \Sonata\MediaBundle\Generator\GeneratorInterface      $pathGenerator
     * @param \Sonata\MediaBundle\Thumbnail\ThumbnailInterface      $thumbnail
     * @param array                                                 $allowedExtensions
     * @param array                                                 $allowedMimeTypes
     * @param \Sonata\MediaBundle\Resizer\ResizerInterface          $resizer
     * @param \Sonata\MediaBundle\Metadata\MetadataBuilderInterface $metadata
     * @param \FFMpeg\FFMpeg                                        $FFMpeg
     * @param \FFMpeg\FFProbe                                       $FFProbe
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param \Doctrine\ORM\EntityManager $entityManager
     */
    public function __construct($name, Filesystem $filesystem, CDNInterface $cdn, GeneratorInterface $pathGenerator, ThumbnailInterface $thumbnail, array $allowedExtensions = array(), array $allowedMimeTypes = array(), ResizerInterface $resizer, MetadataBuilderInterface $metadata = null, FFMpeg $FFMpeg, FFProbe $FFProbe, Container $container, EntityManager $entityManager) {

        parent::__construct($name, $filesystem, $cdn, $pathGenerator, $thumbnail, $allowedExtensions, $allowedMimeTypes, $metadata);

        $this->allowedExtensions = $allowedExtensions;
        $this->allowedMimeTypes = $allowedMimeTypes;
        $this->metadata = $metadata;
        $this->resizer = $resizer;
        $this->getId3 = new GetId3;
        $this->ffprobe = $FFProbe;
        $this->ffmpeg = $FFMpeg;
        $this->container = $container;
        $this->em = $entityManager;
        $this->thumbnail = $thumbnail;

        // configuración
        $this->configImageFrame = $this->container->getParameter('xmon_ffmpeg.image_frame');
        $this->configVideoWidth = $this->container->getParameter('xmon_ffmpeg.video_width');
        $this->configMp4 = $this->container->getParameter('xmon_ffmpeg.mp4');
        $this->configOgg = $this->container->getParameter('xmon_ffmpeg.ogg');
        $this->configWebm = $this->container->getParameter('xmon_ffmpeg.webm');
    }

    /**
     * {@inheritdoc}
     */
    public function buildCreateForm(FormMapper $formMapper) {
        $formMapper->add('binaryContent', 'file', array(
            'constraints' => array(
                new NotBlank(),
                new NotNull(),
            )
        ));

        $formMapper->add('thumbnailCapture', 'integer', array(
            'mapped'        => false,
            'required'      => false,
            'label'         => 'Thumbnail generator (set value in seconds)',
        ));
    }

    /**
     * {@inheritdoc}
     */
    public function buildEditForm(FormMapper $formMapper) {
        parent::buildEditForm($formMapper);

        $formMapper->add('thumbnailCapture', 'integer', array(
            'mapped'        => false,
            'required'      => false,
            'label'         => 'Thumbnail generator (set value in seconds)',
        ));
    }

    /**
     * {@inheritdoc}
     */
    protected function doTransform(MediaInterface $media) {

        parent::doTransform($media);

        if (!is_object($media->getBinaryContent()) && !$media->getBinaryContent()) {
            return;
        }

        $stream = $this->ffprobe
                ->streams($media->getBinaryContent()->getRealPath())
                ->videos()
                ->first();

        //$framecount = $stream->get('nb_frames');
        $duration = $stream->get('duration');
        $height = $stream->get('height');
        $width = $stream->get('width');

        /*
        // para recuperar las dimensiones de los vídeos codificados
        // las calculo aquí para guardarlas en la tabla, en lugar de 
        // las dimensiones reales del vídeo original
        // estoy hay que eliminarlo en el momento en el que consiga pasar variables
        // a las plantillas twig     
        $width = $this->configVideoWidth;
        $height = round($this->configVideoWidth * $heightOriginal / $widthOriginal);
         */

        if ($media->getBinaryContent()) {
            $media->setContentType($media->getBinaryContent()->getMimeType());
            $media->setSize($media->getBinaryContent()->getSize());
            $media->setWidth($width);
            $media->setHeight($height);
            $media->setLength($duration);
        }

        $media->setProviderName($this->name);
        $media->setProviderStatus(MediaInterface::STATUS_OK);
    }

    /**
     * @throws \RuntimeException
     *
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     *
     * @return
     */
    protected function fixBinaryContent(MediaInterface $media) {
        if ($media->getBinaryContent() === null) {
            return;
        }

        // if the binary content is a filename => convert to a valid File
        if (!$media->getBinaryContent() instanceof File) {
            if (!is_file($media->getBinaryContent())) {
                throw new \RuntimeException('The file does not exist : ' . $media->getBinaryContent());
            }

            $binaryContent = new File($media->getBinaryContent());

            $media->setBinaryContent($binaryContent);
        }
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     *
     * @return string
     */
    protected function generateReferenceName(MediaInterface $media) {
        return sha1($media->getName() . rand(11111, 99999)) . '.' . $media->getBinaryContent()->guessExtension();
    }

    /**
     * {@inheritdoc}
     */
    public function getProviderMetadata() {
        return new Metadata($this->getName(), $this->getName() . '.description', false, 'SonataMediaBundle', array('class' => 'fa fa fa-video-camera'));
    }

    /**
     * {@inheritdoc}
     */
    public function requireThumbnails()
    {
        return $this->getResizer() !== null;
    }

    /**
     * {@inheritdoc}
     */
    public function generateThumbnails(MediaInterface $media, $ext = 'jpg') {
        $this->generateReferenceImage($media);

        if (!$this->requireThumbnails()) {
            return;
        }

        $referenceImage = $this->getReferenceImage($media);

        foreach ($this->getFormats() as $format => $settings) {
            if (substr($format, 0, strlen($media->getContext())) == $media->getContext() || $format === 'admin') {
                $this->getResizer()->resize(
                        $media, $referenceImage, $this->getFilesystem()->get($this->generateThumbsPrivateUrl($media, $format, $ext), true), $ext, $settings
                );
            }
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateVideos(MediaInterface $media) {

        // obtengo la ruta del archivo original
        $source = sprintf('%s/%s/%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePath($media), $media->getProviderReference());

        // determino las dimensiones del vídeo
        $height = round($this->configVideoWidth * $media->getHeight() / $media->getWidth());
        
        // corrección para que el alto no sea impar, si es impar PETA ffmpeg
        if($height % 2 != 0){
            $height = $height-1;
        }

        $video = $this->ffmpeg->open($source);
        $video
                ->filters()
                ->resize(new Dimension($this->configVideoWidth, $height))
                ->synchronize();

        if ($this->configMp4) {
            // genero los nombres de archivos de cada uno de los formatos
            $pathMp4 = sprintf('%s/%s/videos_mp4_%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePath($media), $media->getId().'.mp4');
            $mp4 = preg_replace('/\.[^.]+$/', '.' . 'mp4', $pathMp4);
            $video->save(new Video\X264(), $mp4);
            $media->setProviderMetadata(['filename_mp4' => $mp4]);
        }

        if ($this->configOgg) {
            $pathOgg = sprintf('%s/%s/videos_ogg_%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePath($media), $media->getId().'.ogg');
            $ogg = preg_replace('/\.[^.]+$/', '.' . 'ogg', $pathOgg);
            $video->save(new Video\Ogg(), $ogg);
        }

        if ($this->configWebm) {
            $pathWebm = sprintf('%s/%s/videos_webm_%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePath($media), $media->getId().'.webm');
            $webm = preg_replace('/\.[^.]+$/', '.' . 'webm', $pathWebm);
            $video->save(new Video\WebM(), $webm);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function generateThumbsPrivateUrl($media, $format, $ext = 'jpg') {
        return sprintf('%s/thumb_%s_%s.%s', $this->generatePath($media), $media->getId(), $format, $ext
        );
    }

    /**
     * {@inheritdoc}
     */
    public function generatePrivateUrl(MediaInterface $media, $format) {
        $path = $this->generateUrl($media, $format);

        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function generatePublicUrl(MediaInterface $media, $format) {
        $path = $this->generateUrl($media, $format);

        return $this->getCdn()->getPath($path, $media->getCdnIsFlushable());
    }

    private function generateUrl(MediaInterface $media, $format){
        if ($format == 'reference') {
            $path = sprintf('%s/%s', $this->generatePath($media), $media->getProviderReference());
        } elseif ($format == 'admin') {
            $path = sprintf('%s/%s', $this->generatePath($media), str_replace($this->getExtension($media), 'jpg', $media->getProviderReference()));
        } elseif ($format == 'thumb_admin') {
            $path = sprintf('%s/thumb_%d_%s.jpg', $this->generatePath($media), $media->getId(), 'admin');
        } elseif ($format == 'videos_ogg') {
            $path = sprintf('%s/%s_%s', $this->generatePath($media), $format, str_replace($media->getExtension(), 'ogg', $media->getId().'.ogg'));
        } elseif ($format == 'videos_webm') {
            $path = sprintf('%s/%s_%s', $this->generatePath($media), $format, str_replace($media->getExtension(), 'webm', $media->getId().'.webm' ));
        } elseif ($format == 'videos_mp4') {
            $path = sprintf('%s/%s_%s', $this->generatePath($media), $format, str_replace($media->getExtension(), 'mp4', $media->getId().'.mp4'));
        } else {
            $path = sprintf('%s/thumb_%d_%s.jpg',
                $this->generatePath($media),
                $media->getId(),
                $format
            );
        }
        return $path;
    }

    /**
     * {@inheritdoc}
     */
    public function getHelperProperties(MediaInterface $media, $format, $options = array()) {
        if ($format == 'reference') {
            $box = $media->getBox();
        } else {
            $resizerFormat = $this->getFormat($format);
            if ($resizerFormat === false) {
                throw new \RuntimeException(sprintf('The image format "%s" is not defined.
                        Is the format registered in your ``sonata_media`` configuration?', $format));
            }

            $box = $this->resizer->getBox($media, $resizerFormat);
        }

        return array_merge(array(
            'id' => key_exists("id", $options) ? $options["id"] : $media->getId(),
            'alt' => $media->getName(),
            'title' => $media->getName(),
            'thumbnail' => $this->getReferenceImage($media),
            'src' => $this->generatePublicUrl($media, $format),
            'file' => $this->generatePublicUrl($media, $format),
            'realref' => $media->getProviderReference(),
            'width' => $box->getWidth(),
            'height' => $box->getHeight(),
            'duration' => $media->getLength(),
            'video_mp4' => $this->generatePublicUrl($media, "videos_mp4"),
            'video_ogg' => $this->generatePublicUrl($media, "videos_ogg"),
            'video_webm' => $this->generatePublicUrl($media, "videos_webm")
                ), $options);
    }

    /**
     * {@inheritdoc}
     */
    public function getReferenceImage(MediaInterface $media) {
        return $this->getFilesystem()->get(sprintf('%s/%s', $this->generatePath($media), str_replace($this->getExtension($media), 'jpg', $media->getProviderReference())), true);
    }

    /**
     * {@inheritdoc}
     */
    public function generateReferenceImage(MediaInterface $media) {

        $path = sprintf(
                '%s/%s/%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePath($media), $media->getProviderReference()
        );

        $stream = $this->ffprobe
                ->streams($path)
                ->videos()
                ->first();

        /* $framecount = $stream->get('nb_frames');
          $duration = $stream->get('duration');
          $height = $stream->get('height');
          $width = $stream->get('width'); */

        $video = $this->ffmpeg->open($path);

        if (!$media->getProviderReference()) {
            $media->setProviderReference($this->generateReferenceName($media));
        }

        // recojo el punto de extracción de la imagen definido en la configuración
        $seconds_extract = $this->configImageFrame;
        // conocemos la duración del vídeo
        $duration = $stream->get('duration');

        // compruebo que el punto de extracción está dentro de la duración del video
        // si no está dentro, entonces calculo la mitad de la duración
        if ($seconds_extract > $duration) {
            $seconds_extract = $duration / 2;
        }

        $timecode = TimeCode::fromSeconds($seconds_extract);
        $frame = $video->frame($timecode);

        if (!$frame) {
            echo "Thumbnail Generation Failed";
            exit;
        }

        $thumnailPath = sprintf(
                '%s/%s/%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePath($media), str_replace(
                        $this->getExtension($media), 'jpg', $media->getProviderReference()
                )
        );

        $frame->save($thumnailPath);
    }

    private function updateConfigFrameValue($media){
        $uniqid = $this->container->get('request')->query->get('uniqid');
        $formData = $this->container->get('request')->request->get($uniqid);

        if (!empty($formData['thumbnailCapture'])) {
            if ($formData['thumbnailCapture'] <= round($media->getLength())) {
                $this->configImageFrame = $formData['thumbnailCapture'];
            }
        }
    }

    private function setProviderMetadataAvailableVideoFormat(MediaInterface $media){
        $this->updateConfigFrameValue($media);
        $metadata = $media->getProviderMetadata('filename');

        // genero los nombres de archivos de cada uno de los formatos
        if ($this->configMp4) {
             $metadata['mp4_available'] = true;
        }
        if ($this->configOgg) {
             $metadata['ogg_available'] = true;
        }
        if ($this->configWebm) {
             $metadata['webm_available'] = true;
        }

        $media->setProviderMetadata($metadata);
    }

    private function getAvailableFormatToUpdateOrDelete(){
        if ($this->configMp4) {
            $this->addFormat('videos_mp4', 'mp4');
        }
        if ($this->configOgg) {
            $this->addFormat('videos_ogg', 'ogg');
        }
        if ($this->configWebm) {
            $this->addFormat('videos_webm', 'webm');
        }
        $this->addFormat('reference', 'reference');
        $this->addFormat('thumb_admin', 'thumb_admin');
    }

    /**
     * {@inheritdoc}
     */
    public function prePersist(MediaInterface $media) {
        if (!$media->getBinaryContent()) {
            return;
        }

        $this->setProviderMetadataAvailableVideoFormat($media);
    }

    /**
     * {@inheritdoc}
     */
    public function postPersist(MediaInterface $media) {
        if (!$media->getBinaryContent()) {
            return;
        }

        $this->setFileContents($media);

        $this->generateThumbnails($media);

        $this->generateVideos($media);
    }

    /**
     * {@inheritdoc}
     */
    public function preRemove(MediaInterface $media) {
        
        // arreglo para eliminar la relación del video con la galería
        if ($galleryHasMedias = $media->getGalleryHasMedias()) {
            foreach ($galleryHasMedias as $galleryHasMedia) {
                $this->em->remove($galleryHasMedia);
            }
        }

        $this->getAvailableFormatToUpdateOrDelete();

        $path = $this->getReferenceImage($media)->getKey();

        if ($this->getFilesystem()->has($path)) {
            $this->getFilesystem()->delete($path);
        }

        if ($this->requireThumbnails()) {
            $this->thumbnail->delete($this, $media);
        }
    }

    /**
     * {@inheritdoc}
     */
    public function postRemove(MediaInterface $media) {
        // QUIZÁS el lugar donde eliminar los archivos
    }

    /**
     * {@inheritdoc}
     */
    public function preUpdate(MediaInterface $media) {
        if (!$media->getBinaryContent()) {
            return;
        }

        $this->setProviderMetadataAvailableVideoFormat($media);
    }

    /**
     * {@inheritdoc}
     */
    public function postUpdate(MediaInterface $media) {
        if (!$media->getBinaryContent() instanceof \SplFileInfo) {
            return;
        }

        // Delete the current file from the FS
        $oldMedia = clone $media;
        $oldMedia->setProviderReference($media->getPreviousProviderReference());

        $this->getAvailableFormatToUpdateOrDelete();

        if ($this->getFilesystem()->has($oldMedia)) {
            $this->getFilesystem()->delete($oldMedia);
        }

        if ($this->requireThumbnails()) {
            $this->thumbnail->delete($this, $oldMedia);
        }

        $this->fixBinaryContent($media);

        $this->setFileContents($media);

        $this->generateThumbnails($media);

        $this->generateVideos($media);
    }

    /**
     * {@inheritdoc}
     */
    public function updateMetadata(MediaInterface $media, $force = false) {
        $file = sprintf('%s/%s/%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePath($media), $media->getProviderReference());
        $fileinfos = new ffmpeg_movie($file, false);

        $img_par_s = $fileinfos->getFrameCount() / $fileinfos->getDuration();

        // Récupère l'image
        $frame = $fileinfos->getFrame(15 * $img_par_s);

        //$media->setContentType($media->getProviderReference()->getMimeType());
        $media->setContentType(mime_content_type($file));
        $media->setSize(filesize($file));

        $media->setWidth($frame->getWidth());
        $media->setHeight($frame->getHeight());
        $media->setLength($fileinfos->getDuration());

        $media->setMetadataValue('bitrate', $fileinfos->getBitRate());
    }

    /**
     * @param \Sonata\MediaBundle\Model\MediaInterface $media
     *
     * @return string the file extension for the $media, or the $defaultExtension if not available
    */
    protected function getExtension(MediaInterface $media) {
        $ext = $media->getExtension();
        if (!is_string($ext) || strlen($ext) < 2) {
            $ext = "mp4";
        }
        return $ext;
    } 

    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////
    // CON PROBLEMAS
    ////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////


    /**
     * {@inheritdoc}
      public function getDownloadResponse(MediaInterface $media, $format, $mode, array $headers = array()) {

      // build the default headers
      $headers = array_merge(array(
      'Content-Type' => $media->getContentType(),
      'Content-Disposition' => sprintf('attachment; filename="%s"', $media->getMetadataValue('filename')),
      ), $headers);

      if (!in_array($mode, array('http', 'X-Sendfile', 'X-Accel-Redirect'))) {
      throw new \RuntimeException('Invalid mode provided');
      }

      if ($mode == 'http') {
      $provider = $this;

      return new StreamedResponse(function() use ($provider, $media, $format) {
      if ($format == 'reference') {
      echo $provider->getReferenceFile($media)->getContent();
      } else {
      echo $provider->getFilesystem()->get($provider->generatePrivateUrl($media, $format))->getContent();
      }
      }, 200, $headers);
      }

      if (!$this->getFilesystem()->getAdapter() instanceof \Sonata\MediaBundle\Filesystem\Local) {
      throw new \RuntimeException('Cannot use X-Sendfile or X-Accel-Redirect with non \Sonata\MediaBundle\Filesystem\Local');
      }

      $headers[$mode] = sprintf('%s/%s', $this->getFilesystem()->getAdapter()->getDirectory(), $this->generatePrivateUrl($media, $format)
      );

      return new Response('', 200, $headers);
      }
     */
    /**
     * {@inheritdoc}
      public function getReferenceFile(MediaInterface $media) {
      return $this->getFilesystem()->get(sprintf('%s/%s', $this->generatePath($media), $media->getProviderReference()), true);
      }
     */
    /*
     * NO SE USA
     * public function getReferencePath(MediaInterface $media, $format) {
      return $this->getFilesystem()->get(sprintf('%s/%s_%s', $this->generatePath($media), $format, $media->getProviderReference()), true);
      } */
    /**
    public function setLogger($logger) {
        $this->logger = $logger;
    }*/
}
