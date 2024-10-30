<?php

class TurboTranslationsNotification
{
    private $mode = "html";

    /**
     * @param String [$mode="html"] Mode of message.
     */
    public function __construct($mode = "html")
    {
        $this->mode = $mode;
    }

    /**
     * @param Number $postId Id of translated post.
     */
    public function translationIsReady($postId)
    {
        $receipent = get_option("admin_email");
        $subject = __(
            "Twoje tłumaczenie zostało zrealizowane!",
            "turbotranslations"
        );

        $link = $this->getEditPostLink($postId);

        $message = "<h1>" . __("Witaj!", "turbotranslations") . "</h1>";
        $message .=
            "<p>" .
            __("Twoje tłumaczenie zostało zrealizowane.", "turbotranslations") .
            "</p>";
        $message .=
            "<p>" .
            __(
                "Możesz zobaczyć przetłumaczony post w swoim panelu administracyjnym, klikając w poniższy link:",
                "turbotranslations"
            ) .
            "</p>";
        $message .= '<p><a href="' . $link . '">' . $link . "</a></p>";
        $message .=
            "<p>" .
            __(
                "Wiadomość została wygenerowana automatycznie, prosimy na nią nie odpowiadać.",
                "turbotranslations"
            ) .
            "</p>";

        wp_mail($receipent, $subject, $message, $this->getEmailHeaders());
    }

    private function getEmailHeaders()
    {
        $headers = ["Content-Type: text/html; charset=UTF-8"];
        return $headers;
    }

    private function getEditPostLink($id)
    {
        $post = get_post($id);
        if (!$post) {
            return;
        }

        $action = "&amp;action=edit";

        $post_type_object = get_post_type_object($post->post_type);
        if (!$post_type_object) {
            return;
        }

        if ($post_type_object->_edit_link) {
            $link = admin_url(
                sprintf($post_type_object->_edit_link . $action, $post->ID)
            );
        } else {
            $link = "";
        }

        /**
         * Filters the post edit link.
         *
         * @since 2.3.0
         *
         * @param string $link    The edit link.
         * @param int    $post_id Post ID.
         * @param string $context The link context. If set to 'display' then ampersands
         *                        are encoded.
         */
        return apply_filters("get_edit_post_link", $link, $post->ID, "display");
    }
}
