# Ryzom Map Tiles

Bmsite Ryzom map tile image generator.

## Usage

On linux, you can simply run

```
$ bin/gen.sh
```

That will create `atys` and `atys_sp` map tiles for `world` map mode with all languages.

## Map tiles

```
$ php bin/bmmaps.php bmmaps:tiles --mapmode world --mapname atys --with-map --with-city --mapdir resources/maps/atys
```

* `--mapmode` which coordinate system to use (region placement), `world` or `server`
* `--mapname` name you give for tiles
* `--with-map` will generate tiles using `world.png` or zone png files in `server` mapmode
* `--with-city` will use city map images for last few zoom level
* `--mapdir` tells where to find full map images, eg `world.png`. For `satellite` images, you need to use `resources/maps/atys_sp` for example

Tiles are saved to `tiles/<mapmode>/<mapname>` directory.

## Language tiles

```
$ php bin/bmmaps.php bmmaps:tiles --mapmode world --lang en,fr,de
```

* `--lang` takes one or multiple languages separated by comma, en,fr,de,es,ru

Tiles are save to `tiles/<mapmode>/lang_<language>`

## License

	Copyright (c) 2014 Meelis Mägi <nimetu@gmail.com>

	Ryzom Map Tiles is free software; you can redistribute it and/or modify
	it under the terms of the GNU Lesser General Public License as published by
	the Free Software Foundation; either version 3 of the License, or
	(at your option) any later version.

[LGPLv3](http://opensource.org/licenses/LGPL-3.0)

