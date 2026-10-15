<?php
/** Run using wp --skip-themes eval-file. No persistent writes or theme switch. */
use Emonks\Websitebeheer\Agency;
use Emonks\Websitebeheer\Workspace;
$checks = 0;
$expect = static function ($ok, $message) use (&$checks) { if (!$ok) throw new RuntimeException($message); $checks++; };
$original = get_current_user_id();
try {
    $expect(!class_exists('ThemeWebsiteContent'), 'Run this test with --skip-themes.');
    if (!did_action('after_setup_theme')) do_action('after_setup_theme');
    $admin = get_users(['role' => 'administrator', 'number' => 1, 'fields' => 'ID'])[0];
    wp_set_current_user($admin);
    $expect(Workspace::allowed(), 'Administrator cannot access the workspace.');
    $guides = Workspace::guides();
    $expect(!isset($guides['websitegegevens']) && !isset($guides['sjablonen']), 'Skeletor-only guides appear without Skeletor.');
    $copy = json_encode($guides, JSON_UNESCAPED_UNICODE);
    foreach (['Website-inhoud', 'Alle uitsnedes opslaan', 'Sjabloon toevoegen', 'laatste 100 wijzigingen', 'Standaard van de website'] as $term) $expect(!str_contains($copy, $term), 'Theme instructions leak: ' . $term);
    $expect(isset(Workspace::types()['post']), 'Regular WordPress posts are missing.');
    foreach (Workspace::actions() as $action) $expect(!str_contains($action['url'], 'website-inhoud'), 'Theme-only dashboard link appears.');
    foreach (array_keys($guides) as $slug) foreach (Workspace::guide_actions($slug) as $action) $expect(!str_contains($action['url'], 'skeletor-'), 'Theme-only guide link appears.');
    $profile = Agency::profile();
    $expect(isset($profile['name'], $profile['support_active']), 'Profile failed without ACF or the theme.');
    $expect(has_action('wp_dashboard_setup'), 'Plugin has not registered its dashboard.');
    $workspace = new Workspace();
    ob_start(); $workspace->dashboard(); $html = ob_get_clean();
    $expect(str_contains($html, 'Snel aanpassen'), 'Standalone dashboard cannot render.');
    $expect(!str_contains($html, 'Websitegegevens aanpassen'), 'Standalone dashboard exposes theme settings.');
    require_once ABSPATH . 'wp-admin/includes/template.php';
    $agency = new Agency();
    ob_start(); $agency->page(); $settings = ob_get_clean();
    $expect(!str_contains($settings, 'Onderaan de website') && str_contains($settings, '[credit]" value='), 'Theme settings remain visible or the saved credit is not retained.');
    wp_set_current_user(0);
    $expect(!Workspace::allowed() && Workspace::actions() === [], 'Logged-out visitor receives editor actions.');
    echo "Standalone Websitebeheer: $checks checks passed without theme or ACF integration.\n";
} finally { wp_set_current_user($original); }
