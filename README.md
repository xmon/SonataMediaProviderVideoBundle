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
$ php composer.phar require xmon/video-bundle 
```

## Add VideoBundle to your application kernel
```php
// app/AppKernel.php
public function registerBundles()
{
    return array(
        // ...
        new Maesbox\VideoBundle\MaesboxVideoBundle(),
        // ...
    );
}
```

## Configuration example

fter installing the bundle, make sure you configure these parameters

```yaml
maesbox_video:
    ffmpeg_binary: "/usr/local/bin/ffmpeg" # Required, ffmpeg binary path
    ffprobe_binary: "/usr/local/bin/ffprobe" # Required, ffprobe binary path
    binary_timeout: 60 # Optional, default 60
    threads_count: 4 # Optional, default 4
    config:
        image_frame: 10 # Optional, default 10, Can not be empty, second from extract image
        mp4: true #default true, generate MP4 format
        ogg: true #default true, generate OGG format
        webm: true #default true, generate WEBM format
```