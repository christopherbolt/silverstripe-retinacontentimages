<?php

namespace ChristopherBolt\RetinaContentImages\Shortcodes;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Assets\Shortcodes\ImageShortcodeProvider;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Assets\Image;
use SilverStripe\Assets\Storage\AssetStore;
use SilverStripe\View\HTML;
use SilverStripe\Core\Flushable;
use SilverStripe\View\Parsers\ShortcodeHandler;
use SilverStripe\View\Parsers\ShortcodeParser;
use SilverStripe\Core\Config\Config;

/**
 * Class RetinaImageShortcodeProvider
 *
 */
class RetinaImageShortcodeProvider extends ImageShortcodeProvider implements ShortcodeHandler, Flushable
{

    /**
     * Gets the list of shortcodes provided by this handler
     *
     * @return mixed
     */
    public static function get_shortcodes()
    {
        return array('image');
    }

    /**
     * Replace"[image id=n]" shortcode with an image reference.
     * Permission checks will be enforced by the file routing itself.
     *
     * @param array $args Arguments passed to the parser
     * @param string $content Raw shortcode
     * @param ShortcodeParser $parser Parser
     * @param string $shortcode Name of shortcode used to register this handler
     * @param array $extra Extra arguments
     * @return string Result of the handled shortcode
     */
    public static function handle_shortcode($args, $content, $parser, $shortcode, $extra = array())
    {
		
		// Chris bolt, added to ensure this has a different cache key to the default parser
		$args['data-retina'] = 1;
		
		$cache = static::getCache();
        $cacheKey = static::getCacheKey($args);

        $item = $cache->get($cacheKey);
        if ($item) {
            /** @var AssetStore $store */
            $store = Injector::inst()->get(AssetStore::class);
            if (!empty($item['filename'])) {
                $store->grant($item['filename'], $item['hash']);
            }
            return $item['markup'];
        }

        // Find appropriate record, with fallback for error handlers
        $record = static::find_shortcode_record($args, $errorCode);
        if ($errorCode) {
            $record = static::find_error_record($errorCode);
        }
        if (!$record) {
            return null; // There were no suitable matches at all.
        }

        // Check if a resize is required
        $src = $record->Link();
		// Chris bolt, added srcset init
		$srcset = null;
        if ($record instanceof Image) {
            $width = isset($args['width']) ? $args['width'] : null;
            $height = isset($args['height']) ? $args['height'] : null;
            $hasCustomDimensions = ($width && $height);
            if ($hasCustomDimensions && (($width != $record->getWidth()) || ($height != $record->getHeight()))) {
 				// Chris Bolt, new resize formula
				$sizes = Config::inst()->get(self::class,'srcset');
				if (isset($sizes['1x'])) {
					$resized = $record->ResizedImage($width*$sizes['1x'], $height*$sizes['1x']);
				} else {
					$resized = $record->ResizedImage($width, $height);
				}
                if ($resized) {
                    $src = $resized->getURL();
					$srcsetArr = array();
					foreach($sizes as $attr => $magnifier) {
						if ($magnifier==1) {
							$srcsetArr[] = $src.' '.$attr;
						} else if ($retina = $record->ResizedImage($width*$magnifier, $height*$magnifier)) {
							$srcsetArr[] = $retina->getURL().' '.$attr;
						}
					}
					$srcset = implode(', ', $srcsetArr);
                }
            }
        }
		
		// Chris bolt, ensure that the retina arg is not added to html output
		unset($args['data-retina']);

        // Build the HTML tag
        $attrs = array_merge(
            // Set overrideable defaults
            ['src' => '', 'alt' => $record->Title],
            // Use all other shortcode arguments
            $args,
            // But enforce some values
            ['id' => '', 'src' => $src]
        );
		
		// Chris bolt add srcset attribute
		if ($srcset)  $attrs['srcset'] = $srcset;
		
        // Clean out any empty attributes
        $attrs = array_filter($attrs, function ($v) {
            return (bool)$v;
        });

        $markup = HTML::createTag('img', $attrs);

        // cache it for future reference
        $cache->set($cacheKey, [
            'markup' => $markup,
            'filename' => $record instanceof File ? $record->getFilename() : null,
            'hash' => $record instanceof File ? $record->getHash() : null,
        ]);

        return $markup;
    }

}
