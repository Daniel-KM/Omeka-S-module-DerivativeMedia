Derivative Media Optimizer (module for Omeka S)
===============================================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Derivative Media] is a module for [Omeka S] that optimizes files for the web:
it creates derivative files from audio, video and pdf files that are streamable
and linearized (can be rendered before full loading), generally smaller for the
same quality, adapted for mobile or desktop, sized for slow or big connection,
and cross-browser compatible, including Safari. Multiple derivative files can be
created for each file. It works the same way Omeka does for images (large,
medium and square thumbnails).

The conversion uses [ffmpeg] and [ghostcript], two command-line tools that are
generally installed by default on most servers. The commands are customizable.


Installation
------------

This module requires the server packages `ffmpeg` and `ghostscript` (command `gs`)
installed on the server and available in the path.

First, install the required module [Log] and the optional module [Generic].

You can use the release zip to install it, or use and init the source.

* From the zip

Download the last release [DerivativeMedia.zip] from the list of releases and
uncompress it in the `modules` directory.

* From the source and for development:

If the module was installed from the source, rename the name of the folder of
the module to `DerivativeMedia`.

See general end user documentation for [Installing a module] and follow the
config instructions.


Usage
-----

### Configuration of commands

Set settings in the main settings page. Each row in the text area is one format.
The filepath is the left part of the row (`mp4/{filename}.mp4`) and the command
is the right part.

The default params allows to create five derivative files, two for audio, two
for video, and one for pdf. They are designed to keep the same quality than the
original file, and to maximize compatibility with old browsers and Apple Safari.
The webm one is commented (a "#" is prepended), because it is slow.

You can modify params as you want and remove or add new ones. They are adapted
for a recent Linux distribution with a recent version of ffmpeg. You may need to
change names of arguments and codecs on older versions.

For pdf, the html5 standard doesn't give the possibility to display multiple
sources for one link, so it's useless to multiply them.

Ideally, the params should mix compatibilities parameters for old browsers and
Apple Safari, improved parameters for modern browsers (vp9/webm), and different
qualities for low speed networks (128kB), and high speed networks (fiber).

Then, in the site item pages or in the admin media pages, all files will be
appended together in the html5 `<audio>` and `<video>` elements, so the browser
will choose the best one. For pdf, the derivative file will be used
automatically by modules [Universal Viewer] (via [IIIF Server]) and [Pdf Viewer].

### Bulk creation

You can convert existing files via the config form. This job is available in the
module [Bulk Check] too.

Note that the creation of derivative files is a slow and cpu-intensive process:
until two or three hours for a one hour video. You can use arguments `-preset veryslow`
or `-preset ultrafast` (mp4) or `-deadline best` or `-deadline realtime` (webm)
to speed or slow process, but faster means a lower ratio quality/size. See
[ffmpeg wiki] for more info about arguments for mp4, [ffmpeg wiki too] for webm,
and the [browser support table].

For mp4, important options (preset and tune) are [explained here].
For webm, important options (preset and tune) are [explained here].

In all cases, it is better to have original files that follow common standards.
Check if a simple fix like [this one] is enough before uploading files.

The default queries are (without `ffmpeg` or `gs` prepended, and output,
appended):

```
# Audio
mp3/{filename}.mp3   = -i {input} -c copy -c:a libmp3lame -qscale:a 2
ogg/{filename}.ogg   = -i {input} -c copy -vn -c:a libopus

# Video
webm/{filename}.webm = -i {input} -c copy -c:v libvpx-vp9 -crf 30 -b:v 0 -deadline realtime -pix_fmt yuv420p -c:a libopus
mp4/{filename}.mp4   = -i {input} -c copy -c:v libx264 -movflags +faststart -filter:v crop='floor(in_w/2)*2:floor(in_h/2)*2' -crf 22 -level 3 -preset medium -tune film -pix_fmt yuv420p -c:a libmp3lame -qscale:a 2

# Pdf

```

It's important to check the version of ffmpeg that is installed on the server,
because the options may be different or may have been changed.


### External preparation

Because conversion is cpu-intensive, they can be created on another computer,
then copied in the right place.

Here is an example of a one-line command to prepare all wav into mp3 of a
directory:

```sh
cd /my/dir; for filename in *.wav; do name=`echo "$filename" | cut -d'.' -f1`; basepath=${filename%.*}; basename=${basepath##*/}; echo "$basename.wav => $basename.mp3"; ffmpeg -i "$filename" -c copy -c:a libmp3lame -qscale:a 2 "${basename}.mp3"; done
```

After copy, Omeka should know that new derivative files exist, because it
doesn't check directories and formats each time a media is rendered. To record
the metadata, go to the config form and click "Store metadata".


TODO
----

- [ ] Adapt for any store, not only local one.
- [ ] Adapt for thumbnails.
- [ ] Adapt for models.
- [ ] Improve security of the command or limit access to super admin only (in config form anyway).


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

This software is governed by the CeCILL license under French law and abiding by
the rules of distribution of free software. You can use, modify and/ or
redistribute the software under the terms of the CeCILL license as circulated by
CEA, CNRS and INRIA at the following URL "http://www.cecill.info".

As a counterpart to the access to the source code and rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors have only limited liability.

In this respect, the user’s attention is drawn to the risks associated with
loading, using, modifying and/or developing or reproducing the software by the
user in light of its specific status of free software, that may mean that it is
complicated to manipulate, and that also therefore means that it is reserved for
developers and experienced professionals having in-depth computer knowledge.
Users are therefore encouraged to load and test the software’s suitability as
regards their requirements in conditions enabling the security of their systems
and/or data to be ensured and, more generally, to use and operate it in the same
conditions as regards security.

The fact that you are presently reading this means that you have had knowledge
of the CeCILL license and that you accept its terms.


Copyright
---------

* Copyright Daniel Berthereau, 2020


[Derivative Media]: https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia
[Omeka S]: https://omeka.org/s
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Generic]: https://gitlab.com/Daniel-KM/Omeka-S-module-Generic
[Log]: https://gitlab.com/Daniel-KM/Omeka-S-module-Log
[Bulk Check]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkCheck
[ffmpeg]: https://ffmpeg.org
[ffmpeg wiki]: https://trac.ffmpeg.org/wiki/Encode/H.264
[ffmpeg wiki too]: https://trac.ffmpeg.org/wiki/Encode/VP9
[explained here]: https://trac.ffmpeg.org/wiki/Encode/H.264#a2.Chooseapresetandtune
[ghostscript]: https://ffmpeg.org
[browser support table]: https://en.wikipedia.org/wiki/HTML5_video#Browser_support
[this one]: https://forum.omeka.org/t/mov-videos-not-playing-on-item-page-only-audio/11775/12
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
