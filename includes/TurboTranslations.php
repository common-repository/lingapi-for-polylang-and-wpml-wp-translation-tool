<?php
// TODO:
//  - displaying database as a table
use Symfony\Component\Intl\Languages;
require_once "TurboTranslationsPolylang.php";
require_once "TurboTranslationsWPML.php";
require_once "TurboTranslationsNotification.php";
/**
 * Main class for managing TurboTranslations
 */
class TurboTranslations{

  private static $metaSeparator = '||||';
  private static $basicUri = "https://api.turbotlumaczenia.pl";

  private static $bearerToken = false;
  private static $multiLanguagesPlugin = false;
  private static $current = 'pl';

  public static function __constructStatic() {
    if( static::isPolylang() ){
      static::$multiLanguagesPlugin = 'TurboTranslationsPolylang';
      static::$current = pll_current_language( 'slug' );
    }
    if( static::isWPML() ){
        static::$multiLanguagesPlugin = 'TurboTranslationsWPML';
        static::$current = apply_filters( 'wpml_current_language', NULL );
      }
  }

  /**
   * Initializes TurboTranslations
   */
  public static function init() {
    static::initDatabase();
    add_action('admin_notices', [__CLASS__, 'registerAdminError']);
    add_action('admin_menu', [__CLASS__, 'registerAdminMenu']);
    if(static::isPolylang() || static::isWPML()) {
      $post_types = get_post_types();
      $permitted = ['post', 'attachments', 'wp_block', 'user_request', 'oembed_cache', 'customize_changeset', 'custom_css', 'nav_menu_item', 'revision'];
      add_filter( 'manage_posts_columns',  [__CLASS__, 'addColumnToPost'] );
      add_action( 'manage_posts_custom_column', [__CLASS__, 'manageCustomColumns'], 5, 2 );
      foreach( $post_types as $post_type ){
        if( !in_array( $post_type, $permitted ) ){
          add_filter( "manage_{$post_type}_posts_columns",  [__CLASS__, 'addColumnToPost'] );
          add_action( "manage_{$post_type}_posts_custom_column", [__CLASS__, 'manageCustomColumns'], 5, 2 );
        }
      }
    }
  }
  public static function isPolylang() {
    return function_exists('pll_the_languages');
  }
  public static function isWPML() {
    return class_exists( 'SitePress' ) ;
  }
  /**
   * Register admin menu
   */
  public static function registerAdminMenu(){
    add_menu_page(
      __('LingAPI', 'turbotranslations'),
      __('LingAPI', 'turbotranslations'),
      'manage_options',
      'turbotranslations',
      [__CLASS__, 'renderAdminMenu'],
      'dashicons-admin-site'
     );

     add_action('admin_init', [__CLASS__, 'registerSettings'] );
  }
  public static function registerAdminError() {
    if( !static::isPolylang() && !static::isWPML()):
      ?>
        <div class="notice error my-acf-notice is-dismissible" >
        <p>
        <?php _e("Turbotranslations Plugin will not work properly without WPML or Polylang. Please install one of these plugin.",
        'turbotranslations'); ?>
        </p>
    </div>

  <?php
  endif;
  }

  /**
   * Register admin settings
   */
  public static function registerSettings() {
    register_setting( 'turbotranslations_options', 'tt_api_key' );
    register_setting( 'turbotranslations_options', 'tt_default_post_status' );
  }

  /**
   * Renders admin menu
   */
  public static function renderAdminMenu(){
    ?>
    <div class="wrap">
      <h1><?php _e('LingAPI', 'turbotranslations');?></h1>
      <?php if( get_option('tt_api_key') ): ?>
      <p><?php _e('Plugin aktywowany', 'turbotranslations');?></p>
      <?php endif;?>
      <form method="post" action="options.php">
        <?php settings_fields( 'turbotranslations_options' ); ?>
        <?php do_settings_sections( 'turbotranslations_options' ); ?>
        <table class="form-table">
          <tr valign="top">
            <th scope="row"><?php _e('Klucz API', 'turbotranslations');?></th>
            <td><input style="min-width: 400px" type="password" name="tt_api_key" value="<?php echo esc_attr( get_option('tt_api_key') ); ?>" /></td>
          </tr>
          <tr valign="top">
            <th scope="row"><?php _e('Domyślny status przetłumaczonego wpisu', 'turbotranslations');?></th>
            <td>
              <select name="tt_default_post_status">
                <option value="draft" <?php if(esc_attr( get_option('tt_default_post_status') ) === 'draft'):?>selected<?php endif;?>><?php echo get_post_status_object( 'draft' )->label;?></option>
                <option value="publish" <?php if(esc_attr( get_option('tt_default_post_status') ) === 'publish'):?>selected<?php endif;?>><?php echo get_post_status_object( 'publish' )->label;?></option>
              </select>
          </tr>
        </table>
        <?php submit_button(); ?>
      </form>
    </div>
    <?php
  }

  /**
   * Adds TurboTranslations column to posts
   * @param Array $columns Array of columns
   */
  public static function addColumnToPost( $columns ) {
    $columns['turbotranslations'] = __('LingAPI', 'turbotranslations');

    return $columns;
  }

  /**
   * Displays turbotranslations api
   *
   * @param String $column_name name of the column
   * @param Number $post_id Id of the post
   */
  public static function manageCustomColumns( $column_name, $post_id ){
    if( $column_name == 'turbotranslations' ){
      $status = get_post_meta($post_id, 'turbotranslations_status');
      if( !get_option('tt_api_key') )
        return _e('Nie wprowadzono klucza api.', 'turbotranslations');

      if( $status )
        return _e($status, 'turbotranslations');
        if(function_exists('pll_get_post_translations')) {
          $translations = pll_get_post_translations( $post_id );
          $main_lang = pll_default_language();
          if(!array_key_exists($main_lang, $translations)){
            return '';
          }
          $post_id = $translations[$main_lang];
        }
        if(class_exists( 'SitePress' )) {
          $master_post = (int) apply_filters( 'wpml_master_post_from_duplicate', $post_id );
          $post_id = $master_post > 0 ? $master_post : $post_id;
        }
      $lang = static::isPolylang()
      ? pll_get_post_language( $post_id )
      : wpml_get_language_information($post_id);
      $lang = is_array($lang) ? $lang['language_code'] : $lang;
      $langName = Languages::getName($lang,'en' );
      $currentChunk = explode('-',get_bloginfo('language'));
      $current = $currentChunk[0];
      $currentSlug = static::$current ? static::$current : $current;
      $currentSlug === 'all' ? $currentSlug = $current : '';
      $currentLang = Languages::getName( $currentSlug , 'en');

      echo '<div class="turbotranslations-wrapper"
      data-base-url="' . admin_url('admin-ajax.php') . '"
      data-post-id="' . $post_id . '"
      data-language-name="' .$langName. '"
      data-language-i18n="'.$currentLang.'"
      >';
      require TURBOTRANSLATIONS_DIR . "/partials/modal.php";
      echo '</div>';

    }
  }

  public static function getCategories() {
    $cached = get_option( 'turbotranslations_categories_cache' );
    $cached_date = get_option( 'turbotranslations_categories_cache_date' )
    ? get_option( 'turbotranslations_categories_cache_date' )
    : date('Ymd');

    $call_api = !$cached_date ? true : strtotime( $cached_date ) < strtotime( '-7 days' );

    if( !$cached || $call_api ){
      $response = static::apiRequest( static::$basicUri . '/api/v1/category');

      update_option( 'turbotranslations_categories_cache', $response );
      update_option( 'turbotranslations_categories_cache_date', date('Ymd') );
    }else{
      $response = $cached;
    }

    return json_decode( $response['body'] );
  }

  public static function getLanguagePairs() {
    $cached = get_option( 'turbotranslations_pairs_cache' );
    $cached_date = get_option( 'turbotranslations_pairs_cache_date' ) ? get_option( 'turbotranslations_pairs_cache_date' ) : date('Ymd');

    $call_api = !$cached_date ? true : strtotime( $cached_date ) < strtotime( '-7 days' );
    if( !$cached || $call_api ){
      $response = static::apiRequest( static::$basicUri . '/api/v1/language-pairs');
      if($response['code'] === 200){
        update_option( 'turbotranslations_pairs_cache', $response );
        update_option( 'turbotranslations_pairs_cache_date', date('Ymd') );
      }
    }else{
      $response = $cached;
    }

    $languages = json_decode( $response['body'] );

     $available = function_exists('pll_languages_list')
     ? pll_languages_list()
     : apply_filters( 'wpml_active_languages', NULL, 'orderby=id&order=desc' );
      $availableLanguags = [];
      foreach( $available as $lang){
       $lang = is_array($lang) ? $lang['code'] : $lang;
       $availableLanguags[] = Languages::getName( $lang, 'en');
      }
      if( count( $availableLanguags ) > 0 ){
        $languages = array_filter( $languages, function( $lang ) use ( $availableLanguags ){
          return in_array( $lang->lang_to->name, $availableLanguags );
        });
      }
    return $languages;
  }

  public static function getLanguageId( $name ){
    $pairs = static::getLanguagePairs();
    $id = null;
    foreach( $pairs as $lang ){
      if( $lang->lang_from->name == $name){
        $id = $lang->lang_from->id;
        return $id;
        break;
      }elseif( $lang->lang_to->name == $name ){
        $id = $lang->lang_to->id;
        return $id;
        break;
      }
    }
  }

  public static function getLanguageSlug( $id ){
    $pairs = static::getLanguagePairs();
    $name = null;
    foreach( $pairs as $lang ){
      if( $lang->lang_from->id == $id){
        $name = $lang->lang_from->name;
        break;
      }elseif( $lang->lang_to->id == $id ){
        $name = $lang->lang_to->name;
        break;
      }
    }
    $availableLanguages = function_exists('pll_languages_list')
    ? pll_languages_list()
    : apply_filters( 'wpml_active_languages', NULL) ;
    $slug = null;
    foreach( $availableLanguages as $lang ){
        $lang = is_array($lang) ? $lang['code'] : $lang;
      if( $name == Languages::getName( $lang, 'en') ){
        $slug = $lang;
        break;
      }
    }
    return $slug;
  }

  public static function getOrders() {
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders');
    return json_decode( $response['body'] );
  }

  public static function getOrderDetails( $orderId ) {
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders/' . $orderId );
    return json_decode( $response['body'] );
  }

  public static function getJobsList( $orderId ) {
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders/' . $orderId . '/jobs' );
    return json_decode( $response['body'] );
  }

  public static function getJobDetails( $orderId, $jobId ) {
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders/' . $orderId . '/jobs/' . $jobId );
    return json_decode( $response['body'] );
  }

  public static function completeOrder( $orderId ){
    $body = ['id' => $orderId];
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders/' . $orderId . '/close', 'POST', $body);
    return json_decode( $response['body'] );
  }

  private static function createOrder( $body ){
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders/', 'POST', $body );
    return json_decode( $response['body'] );
  }

  private static function createJob( $order_id, $body ){
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders/' . $order_id . '/jobs', 'POST', $body );
    return json_decode( $response['body'] );
  }

  public static function getOrderQuote( $order_id ){
    $response = static::apiRequest( static::$basicUri . '/api/v1/orders/' . $order_id . '/quote', 'POST' );
    return json_decode( $response['body'] );
  }

  public static function createNewOrder( $post_id, $order_body ){

    $uuid = uniqid();
    $key = hash( "sha256", $uuid );

    // WARNING: clean this up while building a production version
    $order_body->callback_url = ( defined('DEVELOPMENT') ) ? 'https://webhook.site/7c3ae121-31c6-4532-94d7-6fd74e4b281b/' : get_rest_url() . 'turbotranslations/v2/callback?t=' . $key;
    $order_body->comment = "[Source: lingAPI for Polylang & WPML - WP translation tool ]";
    $order = static::createOrder( $order_body );
    if( property_exists( $order, 'message') && ( $order->message == 'ERROR_AUTH_CHECK_TOKEN_FAIL' || $order->message == 'UNAUTHORIZED' ) ) return $order;

    $body = new stdClass();
    $body->content = static::preparePostToTranslate( $post_id );
    $job = static::createJob( $order->id, $body );
    if( property_exists( $job, 'message') && ( $job->message == 'ERROR_AUTH_CHECK_TOKEN_FAIL' || $job->message == 'UNAUTHORIZED' ) ) return $job;
    $quote = static::getOrderQuote( $order->id );

    if( property_exists( $quote, 'message') && ($quote->message == 'ERROR_AUTH_CHECK_TOKEN_FAIL' || $quote->message == 'UNAUTHORIZED' ) ) return $quote;
    // check why following code causes error
    if( $quote->id ){
      $data = [
        'post_id' => $post_id,
        'source_language_id' => $order_body->source_lang->id,
        'source_language_slug' => static::getLanguageSlug( $order_body->source_lang->id ),
        'target_language_id' => $order_body->target_lang->id,
        'target_language_slug' => static::getLanguageSlug( $order_body->target_lang->id ),
        'price_net' => $quote->price->net->currency_amount_decimal,
        'price_gross' => $quote->price->gross->currency_amount_decimal,
        'tax' => $quote->price->vat->currency_amount_decimal,
        'currency_code' => $quote->price->net->currency_code,
        'word_count' => $quote->words,
        'source_text' => $body->content,
        'turbotranslations_order_id' => $quote->id,
        'translation_hash' => $key
      ];
      static::createTranslationEvaluationRequest( $data );
    }

    return $quote;
  }

  public static function publishTranslatedContent( $hash ){
    global $wpdb;
    $sql = "SELECT * FROM {$wpdb->prefix}turbotranslations WHERE translation_hash = %s";
    $order = $wpdb->get_row( $wpdb->prepare( $sql, $hash ) );

    $notification = new TurboTranslationsNotification();

    $jobs = static::getJobsList( $order->turbotranslations_order_id );
    foreach( $jobs as $job ){
      $details = static::getJobDetails( $order->turbotranslations_order_id, $job->Id );
      $translatedId = static::$multiLanguagesPlugin::createTranslatedContent( $order->post_id, $details->Content, $order->target_language_slug );
      if( strlen( $details->Translation ) > 0 ){
        $translatedId = static::$multiLanguagesPlugin::createTranslatedContent( $order->post_id, $details->Translation, $order->target_language_slug );
    }

      if( $translatedId ){
        $notification->translationIsReady($translatedId);
      }
    }
    $res = $wpdb->update("{$wpdb->prefix}turbotranslations",
      ['translated_post_id' => $translatedId],
      ['translation_hash' => $hash],
      ['%d'],
      ['%s']
    );
    return $details;
  }

  /**
   * Creates database for TurboTranslations
   */
  private static function initDatabase() {
    global $wpdb;

    $table_name = $wpdb->prefix . 'turbotranslations';

    $sql = "CREATE TABLE IF NOT EXISTS {$table_name}(
      translation_id INT PRIMARY KEY AUTO_INCREMENT,
      post_id INT,
      translated_post_id INT,
      price_net DECIMAL(10,2) DEFAULT NULL,
      tax DECIMAL(10,2) DEFAULT NULL,
      price_gross DECIMAL(10,2) DEFAULT NULL,
      currency_code VARCHAR(3) DEFAULT 'PLN',
      status ENUM('draft', 'quote', 'rejected', 'pending', 'translated') DEFAULT 'draft',
      evaluation_date DATETIME DEFAULT CURRENT_TIMESTAMP,
      translation_date DATETIME DEFAULT NULL,
      source_language_id INT DEFAULT NULL,
      source_language_slug VARCHAR(4) NOT NULL,
      target_language_id INT DEFAULT NULL,
      target_language_slug VARCHAR(4) NOT NULL,
      word_count INT DEFAULT NULL,
      source_text TEXT DEFAULT NULL,
      target_text TEXT DEFAULT NULL,
      turbotranslations_order_id INT NOT NULL,
      translation_uuid VARCHAR(36) DEFAULT NULL,
      translation_hash VARCHAR(256) NOT NULL
    )";
    $trigger = "CREATE TRIGGER before_insert_turbotranslation
      BEFORE INSERT ON {$table_name}
      FOR EACH ROW
      SET new.translation_uuid = uuid();
    ";

    $wpdb->get_results( $sql );
  }

  /**
   * Creates translation request
   *
   * @param Array  $data
   * @param Number $data['post_id']
   * @param String $data['source_language']
   * @param String $data['target_language']
   * @param Number $data['word_count']
   * @param String $data['source_text']
   *
   * @return Number Inserted id
   */
  public static function createTranslationEvaluationRequest($data){
    global $wpdb;

    $table_name = $wpdb->prefix . 'turbotranslations';
    $wpdb->insert( $table_name, $data);
    return $wpdb->insert_id;
  }

  /**
   * Helper function to prepare string to translation
   *
   * @param Number $post_id
   */
  public static function preparePostToTranslate( $post_id ){

    $post = get_post($post_id);
    $content = static::untranslatableText('post_title');
    $content .= $post->post_title;
    $content .= static::untranslatableText('post_content');
    $content .= $post->post_content;
    //adds post metadata
    $meta = get_post_meta($post_id);

    $content .= static::untranslatableText( 'post_meta' );

    foreach( $meta as $key => $property ){
      if( substr( $key,0,1 ) !== '_' ){
        $content .= static::untranslatableText( $key ) . '==' . implode('[mcon]', $property);
        $content .= static::$metaSeparator;
      }
    }

    return $content;
  }

  /**
   * Adds brackets from string
   *
   * @param String $string string to add brackets
   *
   * @return String
   */
  private static function untranslatableText( $string ){
    return "{{{{$string}}}}";
  }

  /**
   * Removes brackets from string
   *
   * @param String $string string to remove brackets
   *
   * @return String
   */
  private static function parseUntranslatableText( $string ){
    return str_replace('{{{', '', str_replace('}}}', '', $string ) );
  }

  /**
   * Parse translated post to array with content and post meta
   *
   * @param String $content translted content
   *
   * @return Array   $post
   */
  public static function parseTranslatedPost( $content ) {
    $post = explode( static::untranslatableText( 'post_title' ), $content );
    $post_parts = explode( static::untranslatableText( 'post_content' ), $post[1] );
    $content = explode( static::untranslatableText( 'post_meta' ), $post_parts[1] );

    $post = [
      'title' => $post_parts[0],
      'content' => $content[0],
      'meta' => []
    ];

    $meta = explode( static::$metaSeparator, $content[1] );

    foreach( $meta as $value ){
      $ep = explode( '==', $value );

      if( count($ep) == 2 ){
        $post['meta'][ static::parseUntranslatableText( $ep[0] ) ] = explode('[mcon]', $ep[1]);
      }

    }
    return $post;

  }

  /**
   * Duplicates a post & its meta and it returns the new duplicated Post ID
   * @param  [int] $post_id The Post you want to clone
   * @return [int] The duplicated Post ID
   */
  public static function createDuplicatedPost($post_id) {
    $title   = get_the_title($post_id);
    $oldpost = get_post($post_id);
    $post    = array(
      'post_title' => $title,
      'post_status' => get_option('tt_default_post_status') ? get_option('tt_default_post_status') : 'draft',
      'post_type' => $oldpost->post_type,
      'post_author' => 1
    );
    $new_post_id = wp_insert_post($post);
    // Copy post metadata
    $data = get_post_custom($post_id);

    foreach ( $data as $key => $values) {
      foreach ($values as $value) {
        add_post_meta( $new_post_id, $key, $value );
      }
    }
    return $new_post_id;
  }

  private static function getBearerToken(){
    $token = get_option('tt_api_key');
    $body = [
      "authorization_code" => $token
    ];

    $data = [
      'headers' => [
        'Content-Type' => 'application/json'
      ],
      'body' => json_encode( $body )
    ];

    $response = wp_remote_post( static::$basicUri . '/api/v1/auth/login', $data );

    return json_decode(  wp_remote_retrieve_body( $response ) );
  }

  private static function apiRequest( $url, $method = "GET", $body = false ){

    if( !static::$bearerToken) static::$bearerToken = static::getBearerToken();
    if( property_exists( static::$bearerToken, 'message') && static::$bearerToken->message == 'ERROR_AUTH_CHECK_TOKEN_FAIL' ) return json_encode( $bearerToken );

    $auth = static::$bearerToken;

    $data = [
      'method'  => $method,
      'headers' => [
        'Content-Type' => 'application/json',
        'Authorization' => "{$auth->token_type} {$auth->access_token}"
      ]
    ];
    if( $body ){
      $data['body'] = json_encode( $body );
    }
    $response = wp_remote_request($url, $data);

    if( !is_array( $response ) ){
      throw new Exception('Wrong response body.');
      error_log( $response->get_error_message() );
    }
    return $response;
  }
}
