<?php
namespace Emonks\Websitebeheer;

/** A task-oriented dashboard and contextual, agency-neutral customer documentation. */
final class Workspace {
    public const HELP = 'website-hulp';

    public function __construct() {
        add_action('admin_menu', [$this, 'menu']);
        add_action('wp_dashboard_setup', [$this, 'dashboard_setup'], 100);
        add_action('admin_notices', [$this, 'screen_content'], 20);
        add_action('admin_enqueue_scripts', [$this, 'assets']);
        add_action('current_screen', [$this, 'context_help']);
    }

    public static function allowed(): bool {
        return (bool) apply_filters('websitebeheer_allowed', current_user_can('edit_posts') || current_user_can('edit_pages'));
    }

    public static function help_url(string $guide = ''): string {
        return admin_url('admin.php?page=' . self::HELP . ($guide ? '&guide=' . rawurlencode($guide) : ''));
    }

    public function menu(): void {
        if (self::allowed()) add_menu_page('Hulp & handleidingen', 'Handleidingen', 'read', self::HELP, [$this, 'help_page'], 'dashicons-sos', 61);
    }

    public function dashboard_setup(): void {
        if (!self::allowed() || is_network_admin()) return;
        remove_action('welcome_panel', 'wp_welcome_panel');
        foreach (['dashboard_primary', 'dashboard_quick_press'] as $id) remove_meta_box($id, 'dashboard', 'side');
        foreach (['dashboard_activity', 'dashboard_right_now'] as $id) remove_meta_box($id, 'dashboard', 'normal');
        // Site Health, plugin widgets and native actionable notices stay available.
    }

    public function assets(): void {
        $screen = get_current_screen();
        if (!$screen || is_network_admin() || is_user_admin()) return;
        $page = sanitize_key($_GET['page'] ?? '');
        if (!(($screen->id === 'dashboard' && self::allowed()) || in_array($page, [self::HELP, Agency::SLUG], true))) return;
        $path = 'assets/workspace.css';
        wp_enqueue_style('websitebeheer-workspace', WEBSITEBEHEER_URL . $path, [], WEBSITEBEHEER_VERSION);
        wp_add_inline_style('websitebeheer-workspace', '.sw-workspace{--sw-accent:' . Agency::profile()['accent'] . ';}');
        if ($screen->id === 'dashboard') wp_add_inline_style('websitebeheer-workspace', '.index-php #wpbody-content>.wrap>h1,.index-php #welcome-panel{display:none}.index-php #dashboard-widgets-wrap{margin-top:24px}');
        if ($page === Agency::SLUG && current_user_can('manage_options')) {
            wp_enqueue_media();
            $js = 'assets/agency.js';
            wp_enqueue_script('websitebeheer-agency', WEBSITEBEHEER_URL . $js, ['jquery', 'media-views'], WEBSITEBEHEER_VERSION, true);
            wp_localize_script('websitebeheer-agency', 'websitebeheerAgency', ['defaultLogo' => WEBSITEBEHEER_URL . 'assets/emonks-color.svg']);
        }
    }

    /** Only registered, visible, public content that this user can actually edit. */
    public static function types(): array {
        return array_filter(apply_filters('websitebeheer_content_types', get_post_types(['public' => true, 'show_ui' => true], 'objects')), static function ($type) {
            return $type->name !== 'attachment' && $type->show_in_menu && current_user_can($type->cap->edit_posts);
        });
    }

    public static function actions(): array {
        $actions = [];
        $front = (int) get_option('page_on_front');
        if (get_option('show_on_front') === 'page' && $front && current_user_can('edit_post', $front)) {
            $actions[] = ['title' => 'Homepage bewerken', 'text' => 'Pas het eerste beeld van je website aan.', 'url' => get_edit_post_link($front, 'raw'), 'icon' => 'admin-home'];
        }
        foreach (self::types() as $type) {
            if ($type->name !== 'page') continue;
            $actions[] = ['title' => $type->labels->name . ' beheren', 'text' => $type->name === 'page' ? 'Werk teksten en pagina’s bij.' : 'Bekijk en bewerk je ' . mb_strtolower($type->labels->name) . '.', 'url' => admin_url('edit.php?post_type=' . $type->name), 'icon' => 'edit-page'];
        }
        if (current_user_can('upload_files')) $actions[] = ['title' => 'Afbeeldingen beheren', 'text' => 'Upload of vind een afbeelding in je mediabibliotheek.', 'url' => admin_url('upload.php'), 'icon' => 'format-image'];
        return apply_filters('websitebeheer_actions', $actions);
    }

    public function screen_content(): void {
        $screen = get_current_screen();
        if (!$screen || !self::allowed() || is_network_admin() || is_user_admin()) return;
        if ($screen->id === 'dashboard') { $this->dashboard(); return; }
        $guide = apply_filters('websitebeheer_screen_guide', $this->screen_guide($screen), $screen);
        if ($guide && isset(self::guides()[$guide])) echo '<div class="notice notice-info"><p>Even hulp nodig? <a href="' . esc_url(self::help_url($guide)) . '">' . esc_html(self::guides()[$guide]['title']) . '</a></p></div>';
    }

    private function screen_guide($screen): string {
        if ($screen->base === 'nav-menus') return 'menus';
        if (in_array($screen->base, ['upload', 'media'], true) || $screen->post_type === 'attachment') return 'afbeeldingen';
        if (in_array($screen->base, ['post', 'edit'], true) && isset(self::types()[$screen->post_type])) return $screen->post_type === 'page' ? 'paginas' : 'content';
        return '';
    }

    public function context_help($screen): void {
        if (!self::allowed() || is_network_admin()) return;
        $guide = apply_filters('websitebeheer_screen_guide', $this->screen_guide($screen), $screen);
        if (!$guide || !isset(self::guides()[$guide])) return;
        $screen->add_help_tab(['id' => 'website-guide', 'title' => 'Hulp bij je website', 'content' => '<p>Een korte uitleg bij dit onderdeel, met stappen en een controle van het resultaat.</p><p><a href="' . esc_url(self::help_url($guide)) . '">' . esc_html(self::guides()[$guide]['title']) . '</a></p>']);
    }

    private static function action_link(string $url, string $label, string $class = 'sw-button'): void {
        echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
    }

    public function dashboard(): void {
        $user = wp_get_current_user();
        $name = $user->first_name ?: $user->display_name;
        ?>
        <div class="sw-workspace sw-dashboard">
            <header class="sw-header"><div><h1>Welkom terug<?php echo $name ? ', ' . esc_html($name) : ''; ?>.</h1><p>Wat wil je vandaag aanpassen?</p></div></header>
            <div class="sw-dashboard-grid"><div class="sw-main">
                <section aria-labelledby="sw-quick"><div class="sw-section-heading"><h2 id="sw-quick">Snel aanpassen</h2><span class="sw-muted">Direct naar de juiste plek</span></div>
                    <div class="sw-shortcuts"><?php foreach (self::actions() as $action) { ?><a class="sw-shortcut" href="<?php echo esc_url($action['url']); ?>"><span class="dashicons dashicons-<?php echo esc_attr($action['icon']); ?>" aria-hidden="true"></span><span><strong><?php echo esc_html($action['title']); ?></strong><span><?php echo esc_html($action['text']); ?></span></span><span class="sw-arrow" aria-hidden="true">↗</span></a><?php } ?></div>
                    <?php $more = self::types(); unset($more['page']); if ($more) { ?><div class="sw-more"><h3>Meer inhoud beheren</h3><div class="sw-content-links"><?php foreach ($more as $type) self::action_link(admin_url('edit.php?post_type=' . $type->name), $type->labels->name); ?></div></div><?php } ?>
                </section>
                <section class="sw-card" aria-labelledby="sw-recent"><h2 id="sw-recent">Verder waar je gebleven was</h2><p class="sw-muted">Recent bijgewerkte inhoud die je kunt bewerken.</p><?php $this->recent(); ?></section>
            </div><aside class="sw-aside">
                <?php $this->support(); ?>
                <section class="sw-card sw-start"><p class="sw-eyebrow">Een goede start</p><h2>Je eerste aanpassing</h2><p>Van een tekst wijzigen tot het resultaat bekijken. In een paar duidelijke stappen.</p><?php self::action_link(self::help_url('eerste-aanpassing'), 'Bekijk de uitleg'); ?><a class="sw-text-link" href="<?php echo esc_url(self::help_url()); ?>">Alle handleidingen →</a></section>
            </aside></div>
        </div>
        <?php
    }

    public static function recent_posts(): array {
        $types = array_keys(self::types());
        if (!$types) return [];
        $query = new \WP_Query(['post_type' => $types, 'post_status' => ['publish', 'draft', 'pending', 'future', 'private'], 'posts_per_page' => 30, 'orderby' => 'modified', 'order' => 'DESC', 'no_found_rows' => true]);
        $visible = [];
        foreach ($query->posts as $post) {
            if (!current_user_can('edit_post', $post->ID)) continue;
            if (in_array($post->post_status, ['draft', 'pending'], true) && (int) $post->post_author !== get_current_user_id()) continue;
            $visible[] = $post;
            if (count($visible) === 5) break;
        }
        return $visible;
    }

    private function recent(): void {
        $posts = self::recent_posts();
        if (!$posts) { echo '<p>Hier verschijnt je recente werk zodra er inhoud beschikbaar is. Begin met een van de snelkoppelingen hierboven.</p>'; return; }
        echo '<ul class="sw-recent">';
        foreach ($posts as $post) {
            $status = get_post_status_object($post->post_status);
            echo '<li><a href="' . esc_url(get_edit_post_link($post->ID, 'raw')) . '"><strong>' . esc_html(get_the_title($post) ?: 'Zonder titel') . '</strong><span class="sw-muted">' . esc_html(get_post_type_object($post->post_type)->labels->singular_name) . ' · ' . esc_html(get_the_modified_date('j M Y', $post)) . '</span></a><span class="sw-status">' . esc_html($status->label) . '</span></li>';
        }
        echo '</ul>';
    }

    private function support(): void {
        $p = Agency::profile();
        echo '<section class="sw-card sw-support"><p class="sw-eyebrow">Je aanspreekpunt</p>' . Agency::mark() . '<h2>Hulp nodig?</h2>';
        echo '<p class="sw-support-status">' . ($p['support_active'] ? 'Supportcontract actief' : 'Support op aanvraag') . '</p>';
        echo '<p>' . ($p['support_active'] ? 'Je hebt een actief supportcontract. Mail je vraag aan ' . esc_html($p['name']) . '.' : 'Neem contact op om de mogelijkheden en eventuele kosten van ondersteuning te bespreken.') . '</p>';
        if ($p['contact_person']) echo '<p class="sw-contact-person"><strong>' . esc_html($p['contact_person']) . '</strong></p>';
        if ($p['office_hours']) echo '<p class="sw-muted">Bereikbaar: ' . esc_html($p['office_hours']) . '</p>';
        if ($p['support_note']) echo '<p class="sw-support-note">' . nl2br(esc_html($p['support_note'])) . '</p>';
        echo '<div class="sw-actions">';
        if ($p['email']) self::action_link('mailto:' . $p['email'], 'Mail ' . $p['name']);
        elseif ($p['website']) self::action_link($p['website'], 'Naar ' . $p['name']);
        else echo '<p class="sw-muted">Neem contact op met je vaste aanspreekpunt bij ' . esc_html($p['name']) . '.</p>';
        if ($p['help_url']) self::action_link($p['help_url'], 'Extra hulp van je bureau', 'sw-text-link');
        echo '</div></section>';
    }

    public static function guides(): array {
        $guides = apply_filters('websitebeheer_guides', require __DIR__ . '/customer-guides.php');
        $guides = array_filter($guides, static fn($guide) => !isset($guide['capability']) || current_user_can($guide['capability']));
        if (!current_user_can('edit_theme_options') || wp_is_block_theme() || !get_registered_nav_menus()) unset($guides['menus']);
        if (!current_user_can('upload_files')) unset($guides['afbeeldingen']);
        $types = self::types();
        if (!isset($types['page'])) unset($guides['paginas']);
        unset($types['page']);
        if (!$types) unset($guides['content']);
        return $guides;
    }

    public static function guide_actions(string $guide): array {
        $links = [];
        if (in_array($guide, ['eerste-aanpassing', 'paginas', 'herstellen'], true)) {
            foreach (self::types() as $type) $links[] = ['url' => admin_url('edit.php?post_type=' . $type->name), 'title' => 'Open ' . mb_strtolower($type->labels->name)];
        }
        if ($guide === 'content') foreach (self::types() as $type) {
            if ($type->name === 'page') continue;
            $links[] = ['url' => admin_url('edit.php?post_type=' . $type->name), 'title' => 'Open ' . mb_strtolower($type->labels->name)];
        }
        if ($guide === 'menus' && current_user_can('edit_theme_options')) $links[] = ['url' => admin_url('nav-menus.php'), 'title' => 'Open menu’s'];
        if ($guide === 'afbeeldingen' && current_user_can('upload_files')) $links[] = ['url' => admin_url('upload.php'), 'title' => 'Open mediabibliotheek'];
        return apply_filters('websitebeheer_guide_actions', $links, $guide);
    }

    public function help_page(): void {
        if (!self::allowed()) wp_die('Je hebt geen toegang tot deze handleidingen.');
        $guides = self::guides();
        $selected = sanitize_key($_GET['guide'] ?? '');
        $guide = $guides[$selected] ?? null;
        ?>
        <div class="wrap sw-workspace">
            <header class="sw-header"><div><h1>Hulp & handleidingen</h1><p>Een kleine aanpassing begint met een duidelijke uitleg.</p></div><?php echo Agency::mark(); ?></header>
            <?php if ($selected && !$guide) echo '<div class="notice notice-info inline"><p>Deze handleiding is hier niet beschikbaar. Kies hieronder een onderwerp dat bij jouw website past.</p></div>'; ?>
            <?php if ($guide) { ?>
                <div class="sw-help-layout"><nav class="sw-guide-nav" aria-label="Handleidingen"><a href="<?php echo esc_url(self::help_url()); ?>">← Alle handleidingen</a><?php foreach ($guides as $slug => $item) echo '<a href="' . esc_url(self::help_url($slug)) . '"' . ($slug === $selected ? ' aria-current="page"' : '') . '>' . esc_html($item['title']) . '</a>'; ?></nav>
                    <article class="sw-card sw-article"><a class="sw-article-back" href="<?php echo esc_url(self::help_url()); ?>">← Alle handleidingen</a><p class="sw-eyebrow"><?php echo esc_html($guide['duration']); ?> leestijd</p><h2><?php echo esc_html($guide['title']); ?></h2><p class="sw-lead"><?php echo esc_html($guide['intro']); ?></p>
                        <ol class="sw-steps"><?php foreach ($guide['steps'] as [$title, $body]) echo '<li><h3>' . esc_html($title) . '</h3><p>' . esc_html($body) . '</p></li>'; ?></ol>
                        <div class="sw-check"><h3>Controleer het resultaat</h3><p><?php echo esc_html($guide['check']); ?></p></div>
                        <p class="sw-muted"><?php echo esc_html($guide['note']); ?></p><div class="sw-actions"><?php foreach (self::guide_actions($selected) as $action) self::action_link($action['url'], $action['title']); ?></div>
                    </article>
                </div>
            <?php } else { ?>
                <div class="sw-dashboard-grid"><section class="sw-guides" aria-label="Kies een handleiding"><?php foreach ($guides as $slug => $item) { ?><a class="sw-card sw-guide-card" href="<?php echo esc_url(self::help_url($slug)); ?>"><span class="sw-eyebrow"><?php echo esc_html($item['duration']); ?> leestijd</span><h2><?php echo esc_html($item['title']); ?></h2><p><?php echo esc_html($item['intro']); ?></p><span class="sw-text-link">Bekijk de stappen <span aria-hidden="true">→</span></span></a><?php } ?></section><aside class="sw-aside"><?php $this->support(); ?></aside></div>
            <?php } ?>
        </div>
        <?php
    }
}
