Derivative Media (module for Omeka S)
==================================

[Derivative Media] is a module for [Omeka S] that creates cross-browser audio and
video derivative files via [ffmpeg].


Installation
------------

This module requires the package `ffmpeg` installed on the server and available
in the path.

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

Set settings in the main settings page. Each row is the text area is one format.
The filepath is the left part of the row (`mp4/{filename}.mp4`) and the ffmpeg
arguments are the right part.

The default params allows to create four derivative files, two for audio and
two for video. They are designed to keep the same quality than the original
file, and to maximize compatibility with old browsers and Apple Safari. The webm
one is commented (a "#" is prepended), because it is slow.

You can modify params as you want and remove or add new ones. They are adapted
for a recent Linux distribution with a recent version of ffmpeg. You may need to
change names of arguments and codecs on older versions.

Ideally, the params should mix compatibilities parameters for old browsers and
Apple Safari, improved parameters for modern browsers (vp9/webm), and different
qualities for low speed networks (128kB), and high speed networks (fiber).

Then in the site item pages or in the admin media pages, all files will be
appended together in the html5 `<audio>` and `<video>` elements, so the browser
will choose the best one.

You can convert existing files via the config form. This job is available in the
module [Bulk Check] too.

Note that the creation of derivative files is a slow and cpu-intensive process:
until two or three hours for a one hour video. You can use arguments `-preset veryslow`
or `-preset ultrafast` (mp4) or `-deadline best` or `-deadline realtime` (webm)
to speed or slow process, but faster means a lower ratio quality/size. See
[ffmpeg wiki] for more info about arguments for mp4, [ffmpeg wiki too] for webm,
and the [browser support table].

In all cases, it is better to have original files that follow common standards.
Check if a simple fix like [this one] is enough before uploading files.


TODO
----

- [ ] Adapt for any store, not only local one.


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
[browser support table]: https://en.wikipedia.org/wiki/HTML5_video#Browser_support
[this one]: https://forum.omeka.org/t/mov-videos-not-playing-on-item-page-only-audio/11775/12
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-DerivativeMedia/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
