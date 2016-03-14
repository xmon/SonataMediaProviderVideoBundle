SonataMediaProviderVideoBundle
==============================

The ``SonataMediaProviderVideoBundle`` extends providers [SonataMediaBundle](https://github.com/sonata-project/SonataMediaBundle), 
creates a new video ``provider`` for uploading videos, generate thumbnail and use FFmpeg.

This Bundle is based on [sergeym/VideoBundle](https://github.com/sergeym/VideoBundle), 
this Fork and the rest of the Forks of the [main project](https://github.com/maerianne/MaesboxVideoBundle) 
appear to be abandoned and I have made many changes, so I decided to 
create a new functional and documented project.

## Requirements

You need install [ffmpeg](https://www.ffmpeg.org/) in your server.

## Installation

### First you need install [phansys/getid3](https://github.com/phansys/GetId3) dependency
```sh
$ php composer.phar require phansys/getid3:~2.1@dev
```
There are a problem installing from composer.json of this bundle, [issue #16](https://github.com/phansys/GetId3/issues/16)

### Install this bundle
```sh
$ php composer.phar require xmon/sonata-media-provider-video-bundle 
```

## Add VideoBundle to your application kernel
```php
// app/AppKernel.php
public function registerBundles()
{
    return array(
        // ...
        new Xmon\SonataMediaProviderVideoBundle\XmonSonataMediaProviderVideoBundle(),
        // ...
    );
}
```

## Configuration example

fter installing the bundle, make sure you configure these parameters

```yaml
xmon_sonata_media_provider_video:
    ffmpeg_binary: "/usr/local/bin/ffmpeg" # Required, ffmpeg binary path
    ffprobe_binary: "/usr/local/bin/ffprobe" # Required, ffprobe binary path
    binary_timeout: 60 # Optional, default 60
    threads_count: 4 # Optional, default 4
    config:
        image_frame: 5 # Optional, default 10, Can not be empty. Where the second image capture
        video_width: 640 # Optional, default 640, Can not be empty. Video proportionally scaled to this width
    formats:
        mp4: true # Optional, default true, generate MP4 format
        ogg: true # Optional, default true, generate OGG format
        webm: true # Optional, default true, generate WEBM format
```

### Credits

 - Thanks to all contributors who participated in the initial Forks of this project. Especially with the main Fork [(maerianne/MaesboxVideoBundle)](https://github.com/maerianne/MaesboxVideoBundle) and Fork [(sergeym/VideoBundle)](https://github.com/sergeym/VideoBundle) I used to continue my development.
 - Thanks other proyect required by this one:
	 - [SonataMediaBundle](https://github.com/sonata-project/SonataMediaBundle).
	 - [GetId3](https://github.com/phansys/GetId3)
	 - [PHP FFmpeg](https://github.com/PHP-FFMpeg/PHP-FFMpeg)
 - It has been used [videojs](http://videojs.com/) plugin such as video player in the administration