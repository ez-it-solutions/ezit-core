<?php
/**
 * EzIT_Customizer — top-level Customizer sections for Ez IT options.
 *
 * Curated, client-safe controls bound to Core's options (type 'option'),
 * so edits made here and in Ez IT → Settings stay in sync. Keys are the
 * same array keys each module's OPTION bag uses.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Customizer
{
    public function __construct()
    {
        add_action('customize_register', [$this, 'register']);
    }

    public function register(WP_Customize_Manager $wp): void
    {
        /* ── Login page — top level, next to the theme's own sections ─── */
        $wp->add_section('ezit_login', [
            'title'       => __('Login page (Ez IT)', 'ezit-core'),
            'description' => __('Branding for wp-login.php — the same options as Ez IT → Settings → Login page.', 'ezit-core'),
            'priority'    => 160,
        ]);

        $wp->add_setting(EzIT_Login::OPTION . '[logo]', [
            'type'              => 'option',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp->add_control(EzIT_Login::OPTION . '[logo]', [
            'section'     => 'ezit_login',
            'label'       => __('Logo image URL', 'ezit-core'),
            'description' => __('Empty = auto-detect (Customizer logo, then the theme’s own logo asset).', 'ezit-core'),
            'type'        => 'url',
        ]);

        $wp->add_setting(EzIT_Login::OPTION . '[accent]', [
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);
        $wp->add_control(new WP_Customize_Color_Control($wp, EzIT_Login::OPTION . '[accent]', [
            'section'     => 'ezit_login',
            'label'       => __('Accent color', 'ezit-core'),
            'description' => __('Button, links, and the form’s top bar.', 'ezit-core'),
        ]));

        $wp->add_setting(EzIT_Login::OPTION . '[bg_color]', [
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_hex_color',
        ]);
        $wp->add_control(new WP_Customize_Color_Control($wp, EzIT_Login::OPTION . '[bg_color]', [
            'section' => 'ezit_login',
            'label'   => __('Background color', 'ezit-core'),
        ]));

        $wp->add_setting(EzIT_Login::OPTION . '[bg_image]', [
            'type'              => 'option',
            'sanitize_callback' => 'esc_url_raw',
        ]);
        $wp->add_control(new WP_Customize_Image_Control($wp, EzIT_Login::OPTION . '[bg_image]', [
            'section'     => 'ezit_login',
            'label'       => __('Background image', 'ezit-core'),
            'description' => __('Full-bleed photo behind the card — empty = the theme’s registered background.', 'ezit-core'),
        ]));

        $wp->add_setting(EzIT_Login::OPTION . '[message]', [
            'type'              => 'option',
            'sanitize_callback' => 'sanitize_textarea_field',
        ]);
        $wp->add_control(EzIT_Login::OPTION . '[message]', [
            'section'     => 'ezit_login',
            'label'       => __('Message above the form', 'ezit-core'),
            'description' => __('Optional note for people signing in.', 'ezit-core'),
            'type'        => 'textarea',
        ]);
    }
}
