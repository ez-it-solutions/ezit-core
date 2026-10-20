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
        // Priority 5 — ahead of legacy_redirect so a pending forced
        // release wins over the tab bounce and we hop once, not twice.
        add_action('admin_init', [$this, 'intercept_forced'], 5);
        add_action('admin_init', [$this, 'legacy_redirect']);
        add_filter('submenu_file', [$this, 'submenu_highlight']);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('admin_notices', [$this, 'notices']);

        add_action('admin_post_ezit_activate',   [$this, 'handle_activate']);
        add_action('admin_post_ezit_deactivate', [$this, 'handle_deactivate']);
        add_action('admin_post_ezit_settings',   [$this, 'handle_settings']);
        add_action('admin_post_ezit_switch_theme', [$this, 'handle_switch_theme']);
        add_action('admin_post_ezit_credit',     [$this, 'handle_credit']);
        add_action('admin_post_ezit_market_install', [$this, 'handle_market_install']);
        add_action('wp_ajax_ezit_maillog',       [$this, 'handle_maillog']);
        add_action('wp_ajax_ezit_forced_apply',  [$this, 'handle_forced_apply']);
        add_action('admin_post_ezit_maillog_view', [$this, 'handle_maillog_view']);
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
        // Deep-link straight into Settings → Email log.
        add_submenu_page('ezit-core', __('Email log', 'ezit-core'), __('Email log', 'ezit-core'),
            'manage_options', 'ezit-mail-log', '__return_null');
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
        if ($page === 'ezit-mail-log') {
            wp_safe_redirect(admin_url('admin.php?page=ezit-core&tab=settings&section=email-log'));
            exit;
        }
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

    /**
     * The reliable trigger for forced releases. WP-Cron only runs on a
     * hit, so a quiet site could sit on a forced release forever — the
     * update showed in the list but never installed. The moment an
     * admin opens ANY Ez IT page we swap straight to the full-screen
     * updater instead; no tabs, just progress. Runs at admin_init
     * because page callbacks can't redirect (headers already sent).
     */
    public function intercept_forced(): void
    {
        if (wp_doing_ajax() || wp_doing_cron()
            || ! current_user_can('manage_options')) {
            return;
        }
        $page = sanitize_key($_GET['page'] ?? ''); // phpcs:ignore
        if (strpos($page, 'ezit') !== 0) {
            return;
        }
        // Already on the updater screen — let it render and run.
        if ($page === 'ezit-core'
            && sanitize_key($_GET['tab'] ?? '') === 'updating') { // phpcs:ignore
            return;
        }
        if ($this->core->updater()->pending_forced()) {
            wp_safe_redirect(admin_url('admin.php?page=ezit-core&tab=updating'));
            exit;
        }
    }

    /** Sidebar: highlight the submenu entry matching the active tab. */
    public function submenu_highlight(?string $submenu_file): ?string
    {
        if (sanitize_key($_GET['page'] ?? '') === 'ezit-core') { // phpcs:ignore
            $tab = sanitize_key($_GET['tab'] ?? 'dashboard');   // phpcs:ignore
            // Email log lives inside the Settings tab — its own sidebar
            // item wins the highlight while we're on that section.
            if ($tab === 'settings'
                && sanitize_key($_GET['section'] ?? '') === 'email-log') { // phpcs:ignore
                return 'ezit-mail-log';
            }
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
        wp_enqueue_script('ezit-admin', EZIT_CORE_ASSETS . 'admin.js', [],
            filemtime(EZIT_CORE_PATH . 'assets/admin.js'), true);
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
            // data-busy-label swaps the text ("Update" → "Updating…").
            . 'document.addEventListener("submit",function(e){'
            . 'var b=e.submitter||e.target.querySelector("button[type=submit],button:not([type])");'
            . 'if(!b||b.disabled)return;'
            . 'var t=b.getAttribute("data-busy-label");'
            . 'if(t)b.textContent=t;'
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
        $unlicensed_themes = 0;
        foreach ($products as $slug => $p) {
            if (! empty($p['self']) || $licensing->is_active($slug)) {
                $licensed++;
            } elseif ($p['product_id'] !== '') {
                $unlicensed++;
                if (($p['type'] ?? 'theme') === 'theme') {
                    $unlicensed_themes++;
                }
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
                'badge'    => $unlicensed_themes ?: null,
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
        // Full-screen updater — intercept_forced() routes here when a
        // forced release is armed. No tabs; a direct visit with nothing
        // pending just renders its all-clear state.
        if ($active === 'updating') {
            $core   = $this->core;
            $flash  = $this->flash();
            $forced = $this->core->updater()->pending_forced();
            include EZIT_CORE_PATH . 'templates/admin/updating.php';
            return;
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
        $old_mode = EzIT_Gate::settings()['mode'] ?? 'off';
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

        // Mode transition changes what guests see — flag a full purge so
        // pages cached under the old mode can't outlive it (consumed on
        // the next front-end request by EzIT_Licensing).
        if ($mode !== $old_mode) {
            set_transient('ezit_purge_pending', 1, HOUR_IN_SECONDS);
        }

        // Emergency override — only evaluated on an explicit gate-section
        // save. Every section shares this one form, so ezit_ov_* arrives on
        // every POST; an empty/absent section means the server panel was
        // viewed (JS keeps the hidden field in sync) — evaluating 'off'
        // there would silently disarm an armed override.
        $save_section = sanitize_key(wp_unslash($_POST['section'] ?? ''));
        if ($save_section === 'gate') {
            $ov_mode = sanitize_key(wp_unslash($_POST['ezit_ov_mode'] ?? 'off'));
            $ov_ttl  = (int) ($_POST['ezit_ov_ttl'] ?? 24);
            $ov_ttl  = in_array($ov_ttl, [4, 24, 72, 168], true) ? $ov_ttl : 24;
            $ov_code = (string) wp_unslash($_POST['ezit_ov_code'] ?? '');
            $current = EzIT_Gate::override();

            if ($ov_mode === 'off') {
                delete_option('ezit_gate_override');
                if ($current['mode'] !== 'off') {
                    set_transient('ezit_purge_pending', 1, HOUR_IN_SECONDS);
                }
            } elseif (! in_array($ov_mode, ['open', 'closed'], true)) {
                $ov_mode = 'off';
            } elseif ($ov_mode === $current['mode'] && $ov_code === '') {
                // Same armed mode, no code entered — leave the existing
                // override (and its original expiry) untouched. Without
                // this, every routine settings save would demand the code.
            } elseif (! EzIT_Gate::recovery_code_valid($this->core, $ov_code)) {
                $this->redirect('settings', 'error',
                    __('Invalid recovery code — find it on the license server under Products → Recovery code.', 'ezit-core'),
                    ['section' => 'gate']);
            } else {
                update_option('ezit_gate_override', [
                    'mode'  => $ov_mode,
                    'until' => time() + $ov_ttl * HOUR_IN_SECONDS,
                    'by'    => wp_get_current_user()->user_login,
                ], false);
                set_transient('ezit_purge_pending', 1, HOUR_IN_SECONDS);
            }
        }

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
                    'mode'   => sanitize_key($in['mode'] ?? 'structured'),
                    'label'  => sanitize_text_field(wp_unslash($in['label'] ?? '')),
                    'detail' => sanitize_text_field(wp_unslash($in['detail'] ?? '')),
                    'cta'    => sanitize_text_field(wp_unslash($in['cta'] ?? '')),
                    'url'    => sanitize_text_field(wp_unslash($in['url'] ?? '')),
                    'custom' => wp_kses_post(wp_unslash((string) ($in['custom'] ?? ''))),
                    'bg'     => sanitize_hex_color(wp_unslash((string) ($in['bg'] ?? ''))) ?: '',
                    'color'  => sanitize_hex_color(wp_unslash((string) ($in['color'] ?? ''))) ?: '',
                    'start'  => sanitize_text_field(wp_unslash($in['start'] ?? '')),
                    'end'    => sanitize_text_field(wp_unslash($in['end'] ?? '')),
                ];
            }
            update_option(EzIT_Alerts::OPTION, $clean, false);

            // Outage banner guest visibility — same section-gated write
            // (unchecked checkbox POSTs nothing → 0).
            update_option('ezit_outage_banner_guests',
                ! empty($_POST['ezit_outage_guests']) ? 1 : 0, false);

            // Guest copy: preset key | slot:<declared-slot> | custom.
            $msg_mode = (string) ($_POST['ezit_outage_msg_mode'] ?? 'default');
            $msg_mode = strpos($msg_mode, 'slot:') === 0
                ? 'slot:' . sanitize_key(substr($msg_mode, 5))
                : sanitize_key($msg_mode);
            update_option('ezit_outage_guest_msg', $msg_mode, false);
            update_option('ezit_outage_guest_msg_custom',
                sanitize_text_field(wp_unslash((string) ($_POST['ezit_outage_msg_custom'] ?? ''))),
                false);
        }

        // Forced-update preferences — section-gated like alerts, so a
        // save from another panel can't silently re-enable them.
        if ($section === 'updates') {
            $posted = array_map('sanitize_key',
                (array) ($_POST['ezit_ignore_forced'] ?? [])); // phpcs:ignore
            update_option('ezit_ignore_forced',
                array_values(array_filter($posted)), false);
        }

        // Site Studio — theme-mod writes for declared fields.
        if ($section === 'studio') {
            $posted = (array) ($_POST['ezit_studio'] ?? []); // phpcs:ignore
            foreach (EzIT_Studio::schemas() as $slug => $schema) {
                EzIT_Studio::save($slug, $schema, (array) ($posted[$slug] ?? []));
            }
        }

        // Email log — the section's "Clear log" button submits here.
        if ($section === 'email-log' && isset($_POST['ezit_clear_maillog'])) {
            EzIT_Mail_Log::clear();
        }

        // Return the user to the section they were editing.
        $sec = sanitize_key(wp_unslash($_POST['section'] ?? ''));
        $this->redirect('settings', 'saved', '', $sec !== '' ? ['section' => $sec] : []);
    }

    /**
     * The "Updating…" screen's apply call — runs the armed release
     * through WP's own upgrader and hands back the step log so the
     * page can render real checkmarks.
     */
    public function handle_forced_apply(): void
    {
        if (! check_ajax_referer('ezit_forced_apply', 'nonce', false)
            || ! current_user_can('manage_options')) {
            wp_send_json_error(
                ['message' => __('Permission denied.', 'ezit-core')], 403);
        }
        $updater = $this->core->updater();
        $pending = $updater->pending_forced();
        if (! $pending) {
            wp_send_json_success([
                'steps'   => [],
                'back'    => admin_url('admin.php?page=ezit-core'),
                'message' => __('Nothing to update — everything is current.', 'ezit-core'),
            ]);
        }

        require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
        // Collecting skin — the upgrader's own feedback becomes the
        // checklist on the progress screen.
        $skin = new class extends WP_Upgrader_Skin {
            /** @var string[] */
            public array $msgs = [];
            public function header() {}
            public function footer() {}
            public function error($errors) {
                $this->msgs[] = is_wp_error($errors)
                    ? $errors->get_error_message() : (string) $errors;
            }
            public function feedback($string, ...$args) {
                $map = [
                    'downloading_package' => 'Downloading the update package',
                    'unpack_package'      => 'Unpacking the update',
                    'installing_package'  => 'Installing the new version',
                    'remove_old'          => 'Removing the previous version',
                    'clear_destination'   => 'Preparing the install location',
                    'up_to_date'          => 'The update could not be located',
                    'download_failed'     => 'Downloading the package failed',
                    'copy_failed'         => 'Could not copy the new files into place',
                    'process_failed'      => 'Update failed',
                    'process_success'     => 'Update installed successfully',
                ];
                $this->msgs[] = $map[$string]
                    ?? ucfirst(str_replace('_', ' ', (string) $string));
            }
        };

        $result = $updater->apply_update(
            (string) $pending['slug'], (string) $pending['type'], $skin);

        if ($result === true) {
            wp_send_json_success([
                'steps'   => $skin->msgs,
                'back'    => admin_url('admin.php?page=ezit-core'),
                'message' => sprintf(
                    /* translators: %s: product name */
                    __('%s updated successfully.', 'ezit-core'),
                    $pending['name']),
            ]);
        }
        wp_send_json_error([
            'steps'   => $skin->msgs,
            'message' => $result instanceof WP_Error
                ? $result->get_error_message()
                : __('The update could not be completed.', 'ezit-core'),
        ]);
    }

    /** Fresh rows for the Email log section — the tabs swap client-side,
     *  so the panel needs live data rather than a stale render. */
    public function handle_maillog(): void
    {
        if (! current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'denied'], 403);
        }
        wp_send_json_success(['rows' => array_slice(EzIT_Mail_Log::formatted(), 0, 20)]);
    }

    /** Email preview — the stored body rendered as its own document.
     *  The modal iframe and the "open in new window" link both use it. */
    public function handle_maillog_view(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('Permission denied.', 'ezit-core'));
        }
        $entry = EzIT_Mail_Log::entry((int) ($_GET['i'] ?? -1));
        if ($entry === null || (string) ($entry['body'] ?? '') === '') {
            wp_die(esc_html__('No stored body for this entry.', 'ezit-core'));
        }
        status_header(200);
        header('Content-Type: text/html; charset=UTF-8');

        // ?full=1 → standalone inbox page (the "open in new window"
        // link). Plain mode stays a bare body for the modal iframe.
        if (! empty($_GET['full'])) {
            printf(
                '<!DOCTYPE html><html><head><meta charset="utf-8">'
                . '<meta name="viewport" content="width=device-width,initial-scale=1">'
                . '<title>%s</title>'
                . '<style>body{margin:0;background:#f0f0f1;font:13px/1.5 -apple-system,"Segoe UI",Roboto,sans-serif}'
                . '.mv-head{background:#14151f;color:#eef0f2;padding:12px 18px;font-weight:700;letter-spacing:.02em}'
                . '.mv-meta{display:grid;grid-template-columns:auto 1fr;gap:4px 16px;margin:0;padding:14px 22px;'
                . 'background:#fff;border-bottom:1px solid #dcdfe9}'
                . '.mv-meta dt{color:#787c82;font-weight:600}.mv-meta dd{margin:0;color:#14151f;word-break:break-word}'
                . '</style></head><body>'
                . '<div class="mv-head">%s</div>'
                . '<dl class="mv-meta">'
                . '<dt>%s</dt><dd>%s</dd>'
                . '<dt>%s</dt><dd>%s</dd>'
                . '<dt>%s</dt><dd>%s</dd>'
                . '</dl>',
                esc_html((string) ($entry['subject'] ?: __('Email preview', 'ezit-core'))),
                esc_html__('Email log — Ez IT Solutions', 'ezit-core'),
                esc_html__('From', 'ezit-core'),    esc_html(($entry['from'] ?? '') !== '' ? $entry['from'] : '—'),
                esc_html__('To', 'ezit-core'),      esc_html(($entry['to'] ?? '') !== '' ? $entry['to'] : '—'),
                esc_html__('Sent', 'ezit-core'),    esc_html((string) ($entry['time'] ?? ''))
            );
        }
        echo $entry['body']; // phpcs:ignore WordPress.Security.EscapeOutput — stored HTML mail
        if (! empty($_GET['full'])) {
            echo '</body></html>';
        }
        exit;
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
            // Registered-but-unlicensed products UC-gate the front end —
            // switching to one would take the site down for guests.
            if ($this->core->product($stylesheet) !== null
                && ! $this->core->licensing()->is_active($stylesheet)) {
                $this->redirect('themes', 'error',
                    __('That theme\'s license isn\'t active — activation is disabled until it\'s licensed or its product is re-enabled.', 'ezit-core'));
            }
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

    /**
     * Marketplace install / update — free listings install unlicensed;
     * already-registered products take the licensed update path inside
     * EzIT_Marketplace::install(). 'from' sends the admin back to the
     * tab the button lived on.
     */
    public function handle_market_install(): void
    {
        $this->guard('ezit_market_install', 'ezit_market_nonce');
        $res = (new EzIT_Marketplace($this->core))->install(
            sanitize_key($_POST['slug'] ?? ''),
            sanitize_text_field($_POST['product_id'] ?? '')
        );
        $tab = in_array($_POST['from'] ?? '', ['marketplace', 'themes'], true)
            ? sanitize_key($_POST['from']) : 'marketplace';
        $this->redirect($tab, $res['status'] ? 'installed' : 'error', $res['message']);
    }

    // ── helpers ─────────────────────────────────────────────────────

    /** Admin notice nudging toward licensing — outside our own pages. */
    public function notices(): void
    {
        if (! current_user_can('manage_options')) {
            return;
        }

        // Emergency override armed — never silent: a force-open gate is a
        // deliberately weakened state and must be impossible to forget.
        $ov = EzIT_Gate::override();
        if ($ov['mode'] !== 'off') {
            printf(
                '<div class="notice notice-warning"><p>%s <a href="%s">%s</a></p></div>',
                sprintf(
                    /* translators: 1: open|closed, 2: expiry time, 3: who armed it */
                    esc_html__('License gate override is ACTIVE — force-%1$s until %2$s (set by %3$s).', 'ezit-core'),
                    esc_html($ov['mode']),
                    esc_html(wp_date('M j, g:i a', (int) $ov['until'])),
                    esc_html($ov['by'] ?: 'admin')
                ),
                esc_url(admin_url('admin.php?page=ezit-core&tab=settings&section=gate')),
                esc_html__('Site gate settings', 'ezit-core')
            );
        }

        // Server answered with its maintenance switch on — distinct from
        // "unreachable" (stale_products covers the long-outage case).
        $server_state = $this->core->licensing()->server_state();
        if ($server_state === 'maintenance') {
            $this->outage_stripe(
                __('The Ez IT license server is in maintenance mode (answering 503 SERVER_UNAVAILABLE). Sites keep their last verdict — no front-end impact — and re-check on the server\'s Retry-After schedule.', 'ezit-core')
            );
        } elseif ($server_state === 'down') {
            $this->outage_stripe(
                __('The Ez IT license server is unreachable — licenses are holding their last verified verdicts, but this needs attention before the grace window expires.', 'ezit-core')
                . ' <a href="' . esc_url(admin_url('admin.php?page=ezit-core&tab=settings&section=gate')) . '">'
                . esc_html__('License status', 'ezit-core') . '</a>'
            );
        }

        // Verify-outage warning — a license can't be remotely re-checked
        // past the grace window. Last verdict still stands; nothing on the
        // front end changes — this only nags the admin to check the server.
        $stale = $this->core->licensing()->stale_products();
        if ($stale) {
            $names = [];
            foreach ($stale as $slug => $days) {
                $names[] = sprintf(
                    '%s (%d+ days)',
                    esc_html($this->core->product($slug)['name'] ?? $slug),
                    $days
                );
            }
            printf(
                '<div class="notice notice-warning"><p>%s</p></div>',
                sprintf(
                    /* translators: %s: product names with outage length */
                    esc_html__('The Ez IT license server has been unreachable for: %s. Sites keep their last verdict — no front-end impact — but renewals/revocations aren\'t syncing until connectivity returns.', 'ezit-core'),
                    implode(', ', $names)
                )
            );
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if ($screen && str_contains($screen->id ?? '', 'ezit')) {
            return; // our own pages show status inline
        }
        foreach ($this->core->products() as $slug => $p) {
            if ($p['product_id'] !== '' && empty($p['self'])
                && ! $this->core->licensing()->is_active($slug)) {
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

    /**
     * License-server outage stripe — same look as the front-end banner
     * (EzIT_Gate::outage_banner): full-width red bar, centered white text.
     * $html is pre-escaped; a License-status link may ride along.
     */
    private function outage_stripe(string $html): void
    {
        printf(
            '<div class="ezit-outage-stripe" role="alert"
                  style="margin:15px 20px 10px 0;padding:8px 14px;background:#b3483a;color:#fff;
                         text-align:center;font:600 13px/1.4 -apple-system,Segoe UI,Roboto,sans-serif;
                         box-shadow:0 2px 8px rgba(0,0,0,.25)">
                %s
             </div>
             <style>.ezit-outage-stripe a{color:#fff;text-decoration:underline}</style>',
            wp_kses_post($html)
        );
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
        $ok = in_array($code, ['activated', 'deactivated', 'saved', 'switched', 'installed'], true);
        $detail = sanitize_text_field(wp_unslash($_GET['ezit_detail'] ?? ''));
        $label = [
            'activated'   => __('License activated.', 'ezit-core'),
            'deactivated' => __('License deactivated.', 'ezit-core'),
            'saved'       => __('Settings saved.', 'ezit-core'),
            'switched'    => __('Theme switched.', 'ezit-core'),
            'installed'   => __('Done.', 'ezit-core'),
            'error'       => __('Something went wrong.', 'ezit-core'),
        ][$code] ?? $code;
        // Server detail supersedes the generic label (avoids "X. X succeeded.").
        $text = $detail !== '' ? $detail : $label;
        $icon = $ok
            ? '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m8.5 12.5 2.5 2.5 5-5.5"/></svg>'
            : '<svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v5m0 3h.01"/></svg>';
        return sprintf(
            '<div class="ezit-flash %s" role="status">%s<p>%s</p>'
            . '<button type="button" class="ezit-flash__close" aria-label="%s">'
            . '<svg viewBox="0 0 24 24" width="14" height="14" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" aria-hidden="true"><path d="m6 6 12 12M18 6 6 18"/></svg>'
            . '</button></div>',
            $ok ? 'is-ok' : 'is-err',
            $icon,
            esc_html($text),
            esc_attr__('Dismiss', 'ezit-core')
        );
    }
}
