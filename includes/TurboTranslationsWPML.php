<?php
require_once "TurboTranslations.php";
require_once ( ABSPATH . "wp-includes/pluggable.php" );

class TurboTranslationsWPML {

    public static function createTranslatedContent($post_id, $content, $lang)
    {
        $body = TurboTranslations::parseTranslatedPost($content);

        $post_type = get_post_type($post_id);
        //ensure that our post is in native editor mode
        update_post_meta($post_id, '_wpml_post_translation_editor_native', 'yes');

        $post_translated_args = array(
            'post_title'    => $body['title'],
            'post_content'  => $body["content"],
            'post_status'   => get_option('tt_default_post_status') ? get_option('tt_default_post_status') : 'draft',
            'post_type'     => $post_type
        );

        $new_post_id = wp_insert_post($post_translated_args);
        update_post_meta($new_post_id, '_wpml_post_translation_editor_native', 'yes');

        // https://wpml.org/wpml-hook/wpml_element_type/
        $wpml_element_type = apply_filters( 'wpml_element_type', $post_type );

        // get the language info of the original post
        // https://wpml.org/wpml-hook/wpml_element_language_details/
        $get_language_args = array('element_id' => $post_id, 'element_type' => $post_type );
        $original_post_language_info = apply_filters( 'wpml_element_language_details', null, $get_language_args );

        $set_language_args = array(
            'element_id'            => $new_post_id,
            'element_type'          => $wpml_element_type,
            'trid'                  => $original_post_language_info->trid,
            'language_code'         => $lang,
            'source_language_code'  => $original_post_language_info->language_code
        );

        do_action( 'wpml_set_element_language_details', $set_language_args );
        return $new_post_id;
    }
}
