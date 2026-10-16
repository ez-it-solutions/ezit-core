<?php
/**
 * EzIT_Admin — one "Ez IT" top-level menu opening a tabbed dashboard
 * (EzIT Tabs component, same component family as the Ez Church portal).
 * Legacy submenu slugs (ezit-licenses et al.) redirect to ?page=ezit-core&tab=…
 * so old bookmarks and the sidebar still land in the right panel.
 */

if (! defined('ABSPATH')) {
    exit;
}

class EzIT_Admin
{
    private EzIT_Core $core;

    public function __construct(EzIT_Core $core)
    {
        $this->core = $core;

        add_action('admin_menu', [$this, 'menu']);
        add_action('admin_init', [$this, 'legacy_redirect']);
        add_filter('submenu_file', [$this, 'submenu_highlight']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_notices', [$this, 'notices']);

        add_action('admin_post_ezit_activate',   [$this, 'handle_activate']);
        add_action('admin_post_ezit_deactivate', [$this, 'handle_deactivate']);
        add_action('admin_post_ezit_settings',   [$this, 'handle_settings']);
        add_action('admin_post_ezit_switch_theme', [$this, 'handle_switch_theme']);
        add_action('admin_post_ezit_credit',     [$this, 'handle_credit']);
    }

    // ── menu ────────────────────────────────────────────────────────

    public function menu(): void
    {
        // Ez IT bolt — inline SVG so no dashicon dependency.
        $icon = 'data:image/svg+xml;base64,' . base64_encode(
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24">'
            . '<path d="M13.4 2 4.6 13.4h6.2L9.4 22 19.4 9.6h-6.2z" fill="#76b900"/></svg>'
        );

        add_menu_page(
            __('Ez IT Solutions', 'ezit-core'),
            __('Ez IT Solutions', 'ezit-core'),
            'manage_options',
            'ezit-core',
            [$this, 'page_dashboard'],
            $icon,
            58
        );
        add_submenu_page('ezit-core', __('Dashboard', 'ezit-core'), __('Dashboard', 'ezit-core'),
            'manage_options', 'ezit-core', [$this, 'page_dashboard']);
        add_submenu_page('ezit-core', __('Themes', 'ezit-core'), __('Themes', 'ezit-core'),
            'manage_options', 'ezit-themes', '__return_null');
        add_submenu_page('ezit-core', __('Licenses', 'ezit-core'), __('Licenses', 'ezit-core'),
            'manage_options', 'ezit-licenses', '__return_null');
        add_submenu_page('ezit-core', __('Marketplace', 'ezit-core'), __('Marketplace', 'ezit-core'),
            'manage_options', 'ezit-marketplace', '__return_null');
        add_submenu_page('ezit-core', __('Settings', 'ezit-core'), __('Settings', 'ezit-core'),
            'manage_options', 'ezit-settings', '__return_null');
        add_submenu_page('ezit-core', __('About', 'ezit-core'), __('About', 'ezit-core'),
            'manage_options', 'ezit-about', '__return_null');
    }

    /** Map of legacy submenu slugs → dashboard tab ids. */
    private static function legacy_map(): array
    {
        return [
            'ezit-themes'      => 'themes',
            'ezit-licenses'    => 'licenses',
            'ezit-marketplace' => 'marketplace',
            'ezit-settings'    => 'settings',
            'ezit-about'       => 'about',
        ];
    }

    /**
     * Legacy slugs bounce to the canonical tab URL. Runs at admin_init —
     * page callbacks can't redirect (admin-header has already sent headers).
     */
    public function legacy_redirect(): void
    {
        $page = sanitize_key($_GET['page'] ?? ''); // phpcs:ignore
        $map  = self::legacy_map();
        if (isset($map[$page])) {
            wp_safe_redirect(admin_url('admin.php?page=ezit-core&tab=' . $map[$page]));
            exit;
        }
        // Canonical tab ids — old ?tab=overview links 301 to ?tab=dashboard
        // so bookmarked URLs self-heal instead of showing a stale param.
        if ($page === 'ezit-core'
            && sanitize_key($_GET['tab'] ?? '') === 'overview') { // phpcs:ignore
            wp_safe_redirect(add_query_arg('tab', 'dashboard'), 301);
            exit;
        }
    }

    /** Sidebar: highlight the submenu entry matching the active tab. */
    public function submenu_highlight(?string $submenu_file): ?string
    {
        if (sanitize_key($_GET['page'] ?? '') === 'ezit-core') { // phpcs:ignore
            $tab = sanitize_key($_GET['tab'] ?? 'dashboard');   // phpcs:ignore
            $slug = array_search($tab, self::legacy_map(), true);
            if ($slug) {
                return $slug;
            }
        }
        return $submenu_file;
    }

    public function assets(string $hook): void
    {
        if (! str_contains($hook, 'ezit')) {
            return;
        }
        // filemtime versions — asset edits always bust browser/CDN cache.
        wp_enqueue_style('ezt-tabs', EZIT_CORE_ASSETS . 'ezt/tabs.css', [],
            filemtime(EZIT_CORE_PATH . 'assets/ezt/tabs.css'));
        wp_enqueue_script('ezt-tabs', EZIT_CORE_ASSETS . 'ezt/tabs.js', [],
            filemtime(EZIT_CORE_PATH . 'assets/ezt/tabs.js'), true);
        wp_enqueue_style('ezit-admin', EZIT_CORE_ASSETS . 'admin.css', ['ezt-tabs'],
            filemtime(EZIT_CORE_PATH . 'assets/admin.css'));
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
        wp_add_inline_script('wp-color-picker',
            'jQuery(function($){ $(".ezit-color").wpColorPicker(); });'
            . 'document.addEventListener("click",function(e){'
            . 'var b=e.target.closest("[data-ezit-copy]");if(!b)return;'
            . 'e.preventDefault();e.stopPropagation();'
            . 'var d=b.closest("details"),pre=d&&d.querySelector("pre");'
            . 'var sel=b.getAttribute("data-ezit-copy");'
            . 'if(!pre&&sel&&sel.length>1){pre=document.querySelector(sel);}'
            . 'if(!pre)return;'
            . 'if(d){d.open=true;}'
            . '(navigator.clipboard?navigator.clipboard.writeText(pre.textContent):Promise.reject())'
            . '.then(function(){b.textContent="Copied!";setTimeout(function(){b.textContent="Copy"},1500);})'
            . '.catch(function(){var r=document.createRange();r.selectNodeContents(pre);'
            . 'var s=getSelection();s.removeAllRanges();s.addRange(r);});'
            . '});'
            // Busy spinner on every submit button — one click only.
            . 'document.addEventListener("submit",function(e){'
            . 'var b=e.submitter||e.target.querySelector("button[type=submit],button:not([type])");'
            . 'if(!b||b.disabled)return;'
            . 'b.classList.add("is-busy");b.disabled=true;'
            . '});'
            // Settings subnav: the tabs component rewrites ?section= via
            // replaceState — mirror it into the hidden field on submit so
            // the save returns to the panel being edited.
            . 'document.addEventListener("submit",function(e){'
            . 'var f=e.target;if(!f||f.id!=="ezit-settings-form")return;'
            . 'var h=f.querySelector("input[name=section]");if(!h)return;'
            . 'var s=new URLSearchParams(location.search).get("section");'
            . 'if(s)h.value=s;'
            . '});'
            // Revoked licenses: enable Activate only once the code changes.
            . 'document.addEventListener("input",function(e){'
            . 'if(e.target.name!=="license_code")return;'
            . 'var f=e.target.closest("form[data-requires-change]");if(!f)return;'
            . 'var b=f.querySelector("button[type=submit],button:not([type])");'
            . 'if(b)b.disabled=(e.target.value.trim()===e.target.defaultValue.trim());'
            . '});'
            // Color picker escapes ALL clipping/containing-block
            // ancestors by living on <body> while open — a transformed
            // panel or overflow card can't clip or offset it.
            . 'document.addEventListener("click",function(e){'
            . 'var b=e.target.closest(".wp-color-result");if(!b)return;'
            . 'var c=b.closest(".wp-picker-container");if(!c)return;'
            . 'var pos=function(){'
            . 'var h=c.querySelector(".wp-picker-holder")||document.querySelector("body>.wp-picker-holder");'
            . 'if(!h||!h.offsetHeight)return;'
            . 'if(h.parentElement!==document.body)document.body.appendChild(h);'
            . 'var anchor=c.querySelector(".wp-picker-input-wrap");'
            . 'var r=(anchor&&anchor.offsetHeight?anchor:b).getBoundingClientRect();'
            . 'var hr=h.getBoundingClientRect();'
            . 'var top=r.bottom+6;'
            . 'if(top+hr.height>window.innerHeight-8)top=Math.max(8,r.top-hr.height-6);'
            . 'var left=r.left;'
            . 'if(left+hr.width>window.innerWidth-8)left=Math.max(8,r.right-hr.width);'
            . 'h.style.position="fixed";h.style.left=left+"px";h.style.right="auto";'
            . 'h.style.top=top+"px";h.style.bottom="auto";'
            . '};'
            . 'setTimeout(pos,50);setTimeout(pos,320);'
            . '});');
    }

    // ── page ────────────────────────────────────────────────────────

    public function page_dashboard(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'ezit-core'));
        }

        $licensing  = $this->core->licensing();
        $products   = $this->core->products();
        $licensed   = 0;
        $unlicensed = 0;
        foreach ($products as $slug => $p) {
            if ($licensing->is_active($slug)) {
                $licensed++;
            } elseif ($p['product_id'] !== '') {
                $unlicensed++;
            }
        }
        $stats = [
            'products'   => count($products),
            'licensed'   => $licensed,
            'unlicensed' => $unlicensed,
        ];

        $dir = EZIT_CORE_PATH . 'templates/admin/tabs/';

        // Per-panel theme vars — the component mirrors them onto the tabs.
        $light = '--panel-fg:#14151f; --panel-muted:#5c5f6b; --panel-btn-bg:#14151f;'
               . ' --panel-btn-fg:#ffffff; --panel-progress:#76b900;';
        $tabs = [
            'dashboard' => [
                'label'    => __('Dashboard', 'ezit-core'),
                'template' => $dir . 'dashboard.php',
                'vars'     => '--panel-bg:#191919; --panel-fg:#ffffff; --panel-muted:rgba(255,255,255,.65);'
                            . ' --panel-btn-bg:#76b900; --panel-btn-fg:#191919;'
                            . ' --panel-deco:rgba(118,185,0,.2); --panel-progress:#76b900;',
            ],
            'themes' => [
                'label'    => __('Themes', 'ezit-core'),
                'template' => $dir . 'themes.php',
                'vars'     => '--panel-bg:#f5f2ec; --panel-deco:rgba(20,21,31,.06);' . $light,
            ],
            'licenses' => [
                'label'    => __('Licenses', 'ezit-core'),
                'template' => $dir . 'licenses.php',
                'badge'    => $unlicensed ?: null,
                'vars'     => '--panel-bg:#f3f8e9; --panel-deco:rgba(118,185,0,.12);' . $light,
            ],
            'marketplace' => [
                'label'    => __('Marketplace', 'ezit-core'),
                'template' => $dir . 'marketplace.php',
                'vars'     => '--panel-bg:#eef0f6; --panel-deco:rgba(20,21,31,.06);' . $light,
            ],
            'settings' => [
                'label'    => __('Settings', 'ezit-core'),
                'template' => $dir . 'settings.php',
                'vars'     => '--panel-bg:#eef0f2; --panel-deco:rgba(20,21,31,.05);' . $light,
            ],
            'about' => [
                'label'    => __('About', 'ezit-core'),
                'template' => $dir . 'about.php',
                'vars'     => '--panel-bg:#191919; --panel-fg:#ffffff; --panel-muted:rgba(255,255,255,.65);'
                            . ' --panel-btn-bg:#76b900; --panel-btn-fg:#191919;'
                            . ' --panel-deco:rgba(118,185,0,.2); --panel-progress:#76b900;',
            ],
        ];

        $active = sanitize_key($_GET['tab'] ?? 'dashboard'); // phpcs:ignore
        if ($active === 'overview') {
            $active = 'dashboard'; // legacy URL alias
        }
        if (! isset($tabs[$active])) {
            $active = 'dashboard';
        }

        // Site Guide checklist — real progress from site state.
        $guide = [
            ['label' => __('Welcome aboard', 'ezit-core'),              'done' => true],
            ['label' => __('Register your first product', 'ezit-core'), 'done' => $stats['products'] > 0],
            ['label' => __('Activate a license', 'ezit-core'),          'done' => $licensed > 0],
            ['label' => __('License every product', 'ezit-core'),       'done' => $stats['products'] > 0 && $unlicensed === 0],
            ['label' => __('Browse the marketplace', 'ezit-core'),      'done' => false],
        ];

        $core  = $this->core;
        $flash = $this->flash();
        include EZIT_CORE_PATH . 'templates/admin/main.php';
    }

    // ── handlers ────────────────────────────────────────────────────

    public function handle_activate(): void
    {
        $this->guard('ezit_license', 'ezit_license_nonce');
        $res = $this->core->licensing()->activate(
            sanitize_key($_POST['slug'] ?? ''),
            sanitize_text_field(wp_unslash($_POST['license_code'] ?? '')),
            sanitize_text_field(wp_unslash($_POST['client_name'] ?? ''))
        );
        $this->redirect('licenses', $res['status'] ? 'activated' : 'error', $res['message']);
    }

    public function handle_deactivate(): void
    {
        $this->guard('ezit_license', 'ezit_license_nonce');
        $res = $this->core->licensing()->deactivate(sanitize_key($_POST['slug'] ?? ''));
        $this->redirect('licenses', $res['status'] ? 'deactivated' : 'error', $res['message']);
    }

    public function handle_settings(): void
    {
        $this->guard('ezit_settings', 'ezit_settings_nonce');
        $url = esc_url_raw(wp_unslash($_POST['ezit_api_url'] ?? ''));
        update_option('ezit_api_url', $url !== '' ? untrailingslashit($url) : '', false);
        $layout = sanitize_key($_POST['ezit_gate_layout'] ?? 'generic');
        if (! isset(EzIT_Gate::layouts()[$layout])) {
            $layout = 'generic';
        }
        $countdown = sanitize_text_field(wp_unslash($_POST['ezit_gate_countdown'] ?? ''));
        $mode = sanitize_key($_POST['ezit_gate_mode'] ?? 'off');
        if (! in_array($mode, ['off', 'licensed', 'maintenance'], true)) {
            $mode = 'off';
        }
        update_option(EzIT_Gate::OPTION, [
            'mode'       => $mode,
            'enabled'    => $mode !== 'off' ? 1 : 0, // legacy key kept in sync
            'layout'     => $layout,
            'uc_title'   => sanitize_text_field(wp_unslash($_POST['ezit_gate_title'] ?? '')),
            'uc_message' => sanitize_textarea_field(wp_unslash($_POST['ezit_gate_message'] ?? '')),
            'accent'     => sanitize_hex_color(wp_unslash($_POST['ezit_gate_accent'] ?? '')) ?: '',
            'bg'         => esc_url_raw(wp_unslash($_POST['ezit_gate_bg'] ?? '')),
            'logo'       => esc_url_raw(wp_unslash($_POST['ezit_gate_logo'] ?? '')),
            'countdown'  => $countdown !== '' ? gmdate('c', strtotime($countdown)) : '',
        ], false);

        update_option(EzIT_Login::OPTION, [
            'logo'     => esc_url_raw(wp_unslash($_POST['ezit_login_logo'] ?? '')),
            'accent'   => sanitize_hex_color(wp_unslash($_POST['ezit_login_accent'] ?? '')) ?: '',
            'bg_color' => sanitize_hex_color(wp_unslash($_POST['ezit_login_bg_color'] ?? '')) ?: '',
            'bg_image' => esc_url_raw(wp_unslash($_POST['ezit_login_bg_image'] ?? '')),
            'message'  => sanitize_textarea_field(wp_unslash($_POST['ezit_login_message'] ?? '')),
        ], false);

        $section = sanitize_key(wp_unslash($_POST['section'] ?? ''));

        // Alert slots — only write on the alerts section save, or an
        // unchecked "Show" toggle on another section would clear it.
        if ($section === 'alerts') {
            $posted = (array) ($_POST['ezit_slots'] ?? []); // phpcs:ignore
            $clean  = [];
            foreach (EzIT_Alerts::declared_slots() as $key => $slot) {
                $in          = (array) ($posted[$key] ?? []);
                $clean[$key] = [
                    'on'     => ! empty($in['on']) ? 1 : 0,
                    'label'  => sanitize_text_field(wp_unslash($in['label'] ?? '')),
                    'detail' => sanitize_text_field(wp_unslash($in['detail'] ?? '')),
                    'cta'    => sanitize_text_field(wp_unslash($in['cta'] ?? '')),
                    'url'    => sanitize_text_field(wp_unslash($in['url'] ?? '')),
                    'start'  => sanitize_text_field(wp_unslash($in['start'] ?? '')),
                    'end'    => sanitize_text_field(wp_unslash($in['end'] ?? '')),
                ];
            }
            update_option(EzIT_Alerts::OPTION, $clean, false);
        }

        // Site Studio — theme-mod writes for declared fields.
        if ($section === 'studio') {
            $posted = (array) ($_POST['ezit_studio'] ?? []); // phpcs:ignore
            foreach (EzIT_Studio::schemas() as $slug => $schema) {
                EzIT_Studio::save($slug, $schema, (array) ($posted[$slug] ?? []));
            }
        }

        // Email log — the section's "Clear log" button submits here.
        if ($section === 'maillog' && isset($_POST['ezit_clear_maillog'])) {
            EzIT_Mail_Log::clear();
        }

        // Return the user to the section they were editing.
        $sec = sanitize_key(wp_unslash($_POST['section'] ?? ''));
        $this->redirect('settings', 'saved', '', $sec !== '' ? ['section' => $sec] : []);
    }

    /** Switch the site's active theme — same flow as Appearance → Themes. */
    public function handle_switch_theme(): void
    {
        if (! current_user_can('switch_themes')) {
            wp_die(esc_html__('Permission denied.', 'ezit-core'));
        }
        check_admin_referer('ezit_switch_theme', 'ezit_theme_nonce');
        $stylesheet = sanitize_key(wp_unslash($_POST['stylesheet'] ?? ''));
        $theme      = wp_get_theme($stylesheet);
        if ($stylesheet !== '' && $theme->exists()) {
            switch_theme($stylesheet);
            $this->redirect('themes', 'switched');
        }
        $this->redirect('themes', 'error', __('Theme not found.', 'ezit-core'));
    }

    /**
     * Footer credit — saved as theme mods so it travels with the theme
     * (Customizer-compatible) and works for any Ez IT product theme.
     */
    public function handle_credit(): void
    {
        if (! current_user_can('edit_theme_options')) {
            wp_die(esc_html__('Permission denied.', 'ezit-core'));
        }
        check_admin_referer('ezit_credit', 'ezit_credit_nonce');
        set_theme_mod(EzIT_Themes::CREDIT_ON, isset($_POST['credit_enabled']) ? 1 : 0);
        set_theme_mod(EzIT_Themes::CREDIT_TXT,
            sanitize_text_field(wp_unslash($_POST['credit_text'] ?? '')));
        $this->redirect('themes', 'saved', '', ['section' => 'credit']);
    }

    // ── helpers ─────────────────────────────────────────────────────

    /** Admin notice nudging toward licensing — outside our own pages. */
    public function notices(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && str_contains($screen->id ?? '', 'ezit')) {
            return; // our own pages show status inline
        }
        foreach ($this->core->products() as $slug => $p) {
            if ($p['product_id'] !== '' && ! $this->core->licensing()->is_active($slug)) {
                printf(
                    '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                    sprintf(
                        /* translators: %s: product name */
                        esc_html__('“%s” is not licensed — updates and support are paused.', 'ezit-core'),
                        esc_html($p['name'])
                    ),
                    esc_url(admin_url('admin.php?page=ezit-core&tab=licenses')),
                    esc_html__('Activate a license', 'ezit-core')
                );
            }
        }
    }

    private function guard(string $action, string $nonce_field): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'ezit-core'));
        }
        check_admin_referer($action, $nonce_field);
    }

    private function redirect(string $tab, string $code, string $message = '', array $extra = []): void
    {
        $args = array_merge(
            ['page' => 'ezit-core', 'tab' => $tab, 'ezit_msg' => $code],
            $extra
        );
        if ($message !== '') {
            $args['ezit_detail'] = $message;
        }
        wp_safe_redirect(add_query_arg($args, admin_url('admin.php')));
        exit;
    }

    /** Flash markup for the dashboard — returned, not echoed, so the
     *  template can place it inside the layout. */
    private function flash(): string
    {
        $code = sanitize_key($_GET['ezit_msg'] ?? ''); // phpcs:ignore
        if ($code === '') {
            return '';
        }
        $ok = in_array($code, ['activated', 'deactivated', 'saved', 'switched'], true);
        $detail = sanitize_text_field(wp_unslash($_GET['ezit_detail'] ?? ''));
        $label = [
            'activated'   => __('License activated.', 'ezit-core'),
            'deactivated' => __('License deactivated.', 'ezit-core'),
            'saved'       => __('Settings saved.', 'ezit-core'),
            'switched'    => __('Theme switched.', 'ezit-core'),
            'error'       => __('Something went wrong.', 'ezit-core'),
        ][$code] ?? $code;
        // Server detail supersedes the generic label (avoids "X. X succeeded.").
        $text = $detail !== '' ? $detail : $label;
        $icon = $ok
            ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/></svg>'
            : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5m0 3h.01"/></svg>';
        return sprintf(
            '<div class="ezit-flash %s" role="status">%s<p>%s</p></div>',
            $ok ? 'is-ok' : 'is-err',
            $icon,
            esc_html($text)
        );
    }
}
