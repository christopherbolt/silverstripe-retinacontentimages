# Retina Content Images #
This simple module adds a srcset attribute to all images added in the HTML Editor so that content images look sexy on retina screens.
Just install, run /dev/build/ and your images will now look sharp and awesome.

## Requirements ##
SS 4.x

### Installation ###
```
composer require christopherbolt/silverstripe-retinacontentimages
```

Run /dev/build/ after install or update.

### Configuration ###
You may configure the image sizes that are added to the srcset attribute by adding a .yml config file to your project. The configuration looks like this:
```
---
Name: mysiteretinacontentimages
After: retinacontentimages
---
ChristopherBolt\RetinaContentImages\Shortcodes\RetinaImageShortcodeProvider:
  srcset:
    '1x': 1
    '2x': 2
	'3x': 3
```

The key value is the argument that will appear in the srcset attribute. This can be a screen resolution multiplier or a screen width, refer to the srcset attribute documentation for more info. The value is a multiplier for how many times larger than the display width/height the image should be enlarged to. Usually this would be the same as your resolution multiplier but it does not have to be. You can add as many or as few srcset options as you like. If your list includes a '1x' option then this will be the image used in the src attribute. Otherwise an image the size of the display width/height will be used as per the normal SilverStripe behaviour.

### Legacy Browser Support ###
If you require the srcset attribute to function on older browsers without native support for the srcset attribute then consider using a polyfill such as Picturefill:
https://github.com/scottjehl/picturefill/