<?php

use SilverStripe\View\Parsers\ShortcodeParser;
use ChristopherBolt\RetinaContentImages\Shortcodes\RetinaImageShortcodeProvider;

$parser = ShortcodeParser::get('default');
$parser->unregister('image');
$parser->register('image', [RetinaImageShortcodeProvider::class, 'handle_shortcode']);

?>