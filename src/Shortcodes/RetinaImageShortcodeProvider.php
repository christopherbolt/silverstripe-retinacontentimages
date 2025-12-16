<?php

namespace ChristopherBolt\RetinaContentImages\Shortcodes;

use Psr\SimpleCache\CacheInterface;
use SilverStripe\Assets\File;
use SilverStripe\Assets\Image;
use SilverStripe\Core\Flushable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\View\Parsers\ShortcodeHandler;
use SilverStripe\View\Parsers\ShortcodeParser;

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
    public static function handle_shortcode($args, $content, $parser, $shortcode, $extra = [])
    {
	
		// Chris bolt, added to ensure this has a different cache key to the default parser
		$args['data-retina'] = 1;

        $cache = static::getCache();
        $cacheKey = static::getCacheKey($args, $content);
        $cachedMarkup = static::getCachedMarkup($cache, $cacheKey, $args);
        if ($cachedMarkup) {
            return $cachedMarkup;
        }

        // Find appropriate record, with fallback for error handlers
        $fileFound = true;
        $record = static::find_shortcode_record($args, $errorCode);
        if ($errorCode) {
            $fileFound = false;
            $record = static::find_error_record($errorCode);
        }
        if (!$record) {
            return null; // There were no suitable matches at all.
        }

        // Grant access to file if necessary
        if (static::getGrant($record)) {
            $record->grantFile();
        }

        // Check if a resize is required
        $manipulatedRecord = $record;
        $width = null;
        $height = null;
        // Chris bolt, added srcset init
		$srcset = null;
        if ($record instanceof Image) {
            $width = isset($args['width']) ? (int) $args['width'] : null;
            $height = isset($args['height']) ? (int) $args['height'] : null;

            // Resize the image if custom dimensions are provided
            $hasCustomDimensions = ($width && $height);
            if ($hasCustomDimensions && (($width != $record->getWidth()) || ($height != $record->getHeight()))) {
                // Chris Bolt, new resize formula
                $sizes = Config::inst()->get(self::class,'srcset');
				if (isset($sizes['1x'])) {
					$resized = $manipulatedRecord->ResizedImage($width*$sizes['1x'], $height*$sizes['1x']);
				} else {
					$resized = $manipulatedRecord->ResizedImage($width, $height);
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

                // Make sure that the resized image actually returns an image
                if ($resized) {
                    $manipulatedRecord = $resized;
                }
            }

            // If only one of width or height is provided, explicitly unset the other
            if ($width && !$height) {
                $args['height'] = false;
            } elseif (!$width && $height) {
                $args['width'] = false;
            }
        }
		
		// Chris bolt, ensure that the retina arg is not added to html output
		unset($args['data-retina']);

        // Set lazy loading attribute
        if (!empty($args['loading'])) {
            $loading = strtolower($args['loading']);
            unset($args['loading']);
            $manipulatedRecord = $manipulatedRecord->LazyLoad($loading !== 'eager');
        }

        // Build the HTML tag
        $attrs = array_merge(
            // Set overrideable defaults ('alt' must be present regardless of contents)
            ['src' => '', 'alt' => ''],
            // Use all other shortcode arguments
            $args,
            // But enforce some values
            ['id' => '', 'src' => '']
        );

        // Chris bolt add srcset attribute
		if ($srcset)  $attrs['srcset'] = $srcset;

        // If file was not found then use the Title value from static::find_error_record() for the alt attr
        if (!$fileFound) {
            $attrs['alt'] = $record->Title;
        }

        // Clean out any empty attributes (aside from alt) and anything not whitelisted
        $whitelist = static::config()->get('attribute_whitelist');
        foreach ($attrs as $key => $value) {
            if (in_array($key, $whitelist) && (strlen(trim($value ?? '')) || in_array($key, ['alt', 'width', 'height']))) {
                $manipulatedRecord = $manipulatedRecord->setAttribute($key, html_entity_decode($value));
            }
        }

        // We're calling renderWith() with an explicit template in case someone wants to use a custom template
        $markup = $manipulatedRecord->renderWith(ImageShortcodeProvider::class . '_Image');

        // cache it for future reference
        if ($fileFound) {
            $cache->set($cacheKey, [
                'markup' => $markup,
                'filename' => $record instanceof File ? $record->getFilename() : null,
                'hash' => $record instanceof File ? $record->getHash() : null,
            ]);
        }

        return $markup;
    }
}
