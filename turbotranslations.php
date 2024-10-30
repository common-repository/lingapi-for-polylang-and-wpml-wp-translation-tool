<?php
/**
  * lingAPI for Polylang & WPML - WP translation tool
  *
  * @package           LingAPI Plugin
  * @author            Kodefix
  * @license           GPL-3.0-or-later
  *
  * @wordpress-plugin
  * Plugin Name:       lingAPI for Polylang & WPML - WP translation tool
  * Description:       lingAPI for Polylang & WPML to wtyczka, dzięki której stworzysz dwu- lub wielojęzyczną stronę na Wordpressie w jeszcze wygodniejszy sposób. Jest doskonałym uzupełnieniem wtyczek Polylang lub WPML i znacząco roszrzesza ich możliwości.
  * Version:           1.0.5
  * Requires at least: 5.4
  * Requires PHP:      7.0
  * Author:            Kodefix
  * Author URI:        https://kodefix.pl/?utm_source=lingapi&utm_medium=plugin&utm_campaign=created_plugins
  * Text Domain:       turbotranslations
  * Domain Path:       /lang
  * License:           GPL v3 or later
  * License URI:       https://www.gnu.org/licenses/gpl-3.0.txt
  *
  * Copyright 2021- Kodefix
  *
  * This program is free software: you can redistribute it and/or modify
  * it under the terms of the GNU General Public License as published by
  * the Free Software Foundation, either version 3 of the License, or
  * (at your option) any later version.
  *
  * This program is distributed in the hope that it will be useful,
  * but WITHOUT ANY WARRANTY; without even the implied warranty of
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  * GNU General Public License for more details.
  *
  * You should have received a copy of the GNU General Public License
  * along with this program. If not, see <https://www.gnu.org/licenses/>.
  */

defined( 'ABSPATH' ) or die( 'No script kiddies please!' );

define( 'TURBOTRANSLATIONS_DIR', plugin_dir_path( __FILE__ ) );

require_once plugin_dir_path( __FILE__ ) . '/vendor/autoload.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/TurboTranslations.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/TurboTranslationsAjax.php';
require_once plugin_dir_path( __FILE__ ) . '/includes/TurboTranslationsRest.php';

add_filter( 'http_request_timeout', 'changeRequestTime' );
function changeRequestTime($time) {
  return 30;
}
add_action('init', function() {
  TurboTranslations::__constructStatic();
  TurboTranslationsAjax::init();
  TurboTranslations::init();
});

add_action( 'rest_api_init', function(){
  new TurboTranslationsRest();
});
