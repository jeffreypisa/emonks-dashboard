<?php
namespace Emonks\Websitebeheer;

/** The agency identity is deliberately separate from the customer's visual identity. */
final class Agency {
    public const OPTION = 'skeletor_agency';
    public const SLUG = 'website-bureau';

    public function __construct() {
        add_action('admin_menu', [$this, 'menu'], 1100);
        add_action('admin_init', [$this, 'register']);
        add_filter('admin_footer_text', [$this, 'admin_footer']);
        add_action('login_enqueue_scripts', [$this, 'login_styles']);
        add_filter('login_headerurl', static fn() => self::profile()['website'] ?: home_url('/'));
        add_filter('login_headertext', static fn() => self::profile()['name']);
    }

    private static function text($value): string { return is_scalar($value) ? sanitize_text_field((string) $value) : ''; }
    private static function url($value): string { return esc_url_raw(self::text($value), ['http', 'https']); }

    public static function sanitize($input): array {
        $input = is_array($input) ? $input : [];
        $legacy = !isset($input['version']);
        $preset = $input['profile'] ?? null;
        if ($legacy && $preset === 'branding') $input['name'] = 'Branding';
        elseif ($legacy && $preset === 'emonks') $input['name'] = 'emonks';
        $default = $legacy && (!$input || $preset === 'emonks');
        $defaults = [
            'name' => 'emonks', 'website' => 'https://emonks.nl/', 'email' => 'jef@emonks.nl',
            'contact_person' => 'Jeffrey Pisa', 'office_hours' => 'Kantoortijden',
        ];
        if ($default) foreach ($defaults as $key => $value) {
            if (empty($input[$key])) $input[$key] = $value;
        }
        $mode = in_array($input['credit'] ?? '', ['auto', 'custom', 'hidden'], true) ? $input['credit'] : apply_filters('websitebeheer_default_credit_mode', 'custom');
        $logo = absint(is_scalar($input['logo'] ?? 0) ? ($input['logo'] ?? 0) : 0);
        if ($logo && !wp_attachment_is_image($logo)) $logo = 0;
        return [
            'version' => 2,
            'name' => self::text($input['name'] ?? '') ?: 'Je webbureau',
            'logo' => $logo,
            'logo_hidden' => !empty($input['logo_hidden']) ? 1 : 0,
            'accent' => sanitize_hex_color(self::text($input['accent'] ?? '')) ?: '#235c56',
            'website' => self::url($input['website'] ?? ''),
            'email' => sanitize_email(self::text($input['email'] ?? '')),
            'contact_person' => self::text($input['contact_person'] ?? ''),
            'office_hours' => self::text($input['office_hours'] ?? ''),
            'support_active' => in_array($input['support_active'] ?? false, [true, 1, '1'], true),
            'help_url' => self::url($input['help_url'] ?? ''),
            'support_note' => sanitize_textarea_field(is_scalar($input['support_note'] ?? '') ? (string) ($input['support_note'] ?? '') : ''),
            'credit' => $mode,
        ];
    }

    public static function sanitize_saved($input): array {
        // Fixed fields are authoritative, including intentionally empty contact details.
        return self::sanitize($input);
    }

    public static function profile(): array {
        $saved = get_option(self::OPTION, []);
        $profile = self::sanitize($saved);
        $profile['logo_url'] = $profile['logo'] ? (wp_get_attachment_image_url($profile['logo'], 'medium') ?: '') : '';
        if (!$profile['logo_url'] && !$profile['logo_hidden'] && strtolower($profile['name']) === 'emonks') $profile['logo_url'] = WEBSITEBEHEER_URL . 'assets/emonks-color.svg';
        return $profile;
    }

    /** null means keep the existing text and link; an empty credit means hide only the credit. */
    public static function credit(): ?array {
        $p = self::profile();
        return match ($p['credit']) {
            'custom' => null,
            'hidden' => ['text' => '', 'link' => []],
            default => ['text' => 'Website door ' . $p['name'], 'link' => $p['website'] ? ['url' => $p['website'], 'title' => 'Website door ' . $p['name'], 'target' => '_blank'] : []],
        };
    }

    public static function mark(): string {
        $p = self::profile();
        return $p['logo_url'] ? '<img class="sw-agency-logo" src="' . esc_url($p['logo_url']) . '" alt="' . esc_attr($p['name']) . '">' : '<span class="sw-agency-name">' . esc_html($p['name']) . '</span>';
    }

    public function menu(): void {
        add_options_page('Bureau & whitelabel', 'Bureau & whitelabel', 'manage_options', self::SLUG, [$this, 'page']);
    }

    public function register(): void {
        register_setting(self::OPTION, self::OPTION, ['type' => 'array', 'sanitize_callback' => [self::class, 'sanitize_saved'], 'show_in_rest' => false]);
    }

    public function admin_footer($text): string {
        if (!is_admin() || is_network_admin() || is_user_admin()) return (string) $text;
        $p = self::profile();
        return 'Websitebeheer · ' . ($p['website'] ? '<a href="' . esc_url($p['website']) . '" target="_blank" rel="noopener noreferrer">' . esc_html($p['name']) . '</a>' : esc_html($p['name']));
    }

    public function login_styles(): void {
        $p = self::profile();
        wp_enqueue_style('websitebeheer-login', WEBSITEBEHEER_URL . 'assets/workspace-login.css', [], WEBSITEBEHEER_VERSION);
        $css = 'body.login{--sw-accent:' . $p['accent'] . ';}';
        if ($p['logo_url']) $css .= '.login h1 a{background-image:url(' . wp_json_encode(esc_url_raw($p['logo_url']), JSON_HEX_TAG | JSON_HEX_AMP) . ');}';
        else $css .= '.login h1 a{background:none;text-indent:0;width:auto;height:auto;font-size:28px;font-weight:700;color:#172e2b;}';
        wp_add_inline_style('websitebeheer-login', $css);
    }

    private function input(string $key, string $label, string $value, string $type = 'text', string $hint = ''): void {
        echo '<div class="sw-field"><label for="agency-' . esc_attr($key) . '">' . esc_html($label) . '</label><input id="agency-' . esc_attr($key) . '" name="' . self::OPTION . '[' . esc_attr($key) . ']" type="' . esc_attr($type) . '" value="' . esc_attr($value) . '"' . ($hint ? ' aria-describedby="agency-' . esc_attr($key) . '-hint"' : '') . '>';
        if ($hint) echo '<p id="agency-' . esc_attr($key) . '-hint" class="sw-muted">' . esc_html($hint) . '</p>';
        echo '</div>';
    }

    public function page(): void {
        if (!current_user_can('manage_options')) wp_die('Je hebt geen toegang tot deze instellingen.');
        $p = self::profile();
        ?>
        <div class="wrap sw-workspace">
            <header class="sw-header"><div><p class="sw-eyebrow">Websitebeheer</p><h1>Bureau & whitelabel</h1><p>De afzender van het beheer, de hulp en het inlogscherm. De huisstijl van je klant blijft apart.</p></div><?php echo self::mark(); ?></header>
            <form action="options.php" method="post" class="sw-settings">
                <?php settings_fields(self::OPTION); ?>
                <section class="sw-card"><h2>Wie beheert deze website?</h2>
                    <input type="hidden" name="<?php echo self::OPTION; ?>[version]" value="2">
                    <?php $this->input('name', 'Naam van je bureau', $p['name'], 'text', 'Pas deze gegevens aan voor het bureau dat deze website beheert.'); ?>
                    <div class="sw-field"><label for="agency-logo">Bureaulogo</label><input type="hidden" id="agency-logo" name="<?php echo self::OPTION; ?>[logo]" value="<?php echo (int) $p['logo']; ?>"><input type="hidden" id="agency-logo-hidden" name="<?php echo self::OPTION; ?>[logo_hidden]" value="<?php echo (int) $p['logo_hidden']; ?>"><div id="agency-logo-preview"><?php echo self::mark(); ?></div><div class="sw-actions"><button type="button" class="button" data-agency-logo>Kies logo</button><button type="button" class="button" data-agency-logo-remove>Gebruik bureaunaam</button></div><p class="sw-muted">Gebruik een logo voor een lichte achtergrond. Je kunt een eigen logo kiezen of alleen de bureaunaam tonen.</p></div>
                    <?php $this->input('accent', 'Accentkleur', $p['accent'], 'color', 'Een subtiel accent in het dashboard en op de inlogpagina. Teksten en knoppen behouden hun leesbare contrast.'); ?>
                </section>
                <section class="sw-card"><h2>Contact & hulp</h2><p>Deze gegevens verschijnen in het hulpblok voor je klant.</p>
                    <div class="sw-field"><label for="agency-support_active">Supportcontract actief</label><div class="sw-toggle">
                        <input type="hidden" name="<?php echo self::OPTION; ?>[support_active]" value="0">
                        <input type="checkbox" role="switch" id="agency-support_active" name="<?php echo self::OPTION; ?>[support_active]" value="1" <?php checked($p['support_active']); ?> aria-describedby="agency-support-hint"><span class="sw-toggle-state" aria-hidden="true"></span>
                    </div><p class="sw-muted" id="agency-support-hint">Ja: het hulpblok toont dat er een actief supportcontract is. Nee: ondersteuning is op aanvraag. De mailknop en handleidingen blijven beschikbaar. Dit wijzigt alleen de vermelding; het sluit geen contract af.</p></div>
                    <?php $this->input('website', 'Website van het bureau', $p['website'], 'url'); ?>
                    <?php $this->input('email', 'Support-e-mailadres', $p['email'], 'email'); ?>
                    <?php $this->input('contact_person', 'Contactpersoon', $p['contact_person']); ?>
                    <?php $this->input('office_hours', 'Bereikbaarheid', $p['office_hours']); ?>
                    <?php $this->input('help_url', 'Aanvullende handleiding of helpcentrum', $p['help_url'], 'url', 'Optioneel. De ingebouwde handleidingen blijven beschikbaar.'); ?>
                    <div class="sw-field"><label for="agency-support_note">Aanvullende informatie</label><textarea id="agency-support_note" name="<?php echo self::OPTION; ?>[support_note]" rows="3"><?php echo esc_textarea($p['support_note']); ?></textarea><p class="sw-muted">Optioneel: extra informatie voor je klant.</p></div>
                </section>
                <input type="hidden" name="<?php echo self::OPTION; ?>[credit]" value="<?php echo esc_attr($p['credit']); ?>"><?php do_action('websitebeheer_agency_settings', $p); ?>
                <div class="sw-save"><?php submit_button('Bureauprofiel opslaan', 'primary', 'submit', false); ?><span class="sw-muted">Wijzigingen gelden na opslaan voor beheer, hulp en inloggen.</span></div>
            </form>
        </div>
        <?php
    }
}
