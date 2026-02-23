<?php
// Helpers to render vendor theme settings as CSS variables
function parse_theme_settings($raw) {
    if (!$raw) return [];
    if (is_string($raw)) {
        $j = json_decode($raw, true);
        if (is_array($j)) return $j;
        return [];
    }
    if (is_array($raw)) return $raw;
    return [];
}

function render_theme_style(array $settings) {
    // defaults
    $primary = $settings['primary_color'] ?? '#007bff';
    $accent = $settings['accent_color'] ?? '#6610f2';
    $bg = $settings['background_color'] ?? '#ffffff';
    $radius = isset($settings['border_radius']) ? (int)$settings['border_radius'] : 6;
    $columns = isset($settings['columns']) ? (int)$settings['columns'] : 3;

    $css = "<style>:root{--sd-primary:{$primary};--sd-accent:{$accent};--sd-bg:{$bg};--sd-radius:{$radius}px;--sd-columns:{$columns};}
    .store-theme .card{border-radius:var(--sd-radius);} .store-theme a.btn{background:var(--sd-primary);border-color:var(--sd-primary);}
    </style>";
    return $css;
}

// Load vendor theme settings by slug or id using PDO and render if found
function load_vendor_theme_settings($pdo, $bySlug = null, $byId = null) {
    if ($bySlug) {
        $s = $pdo->prepare('SELECT theme_settings FROM vendors WHERE slug = ? LIMIT 1');
        $s->execute([$bySlug]);
        $r = $s->fetchColumn();
        return parse_theme_settings($r);
    }
    if ($byId) {
        $s = $pdo->prepare('SELECT theme_settings FROM vendors WHERE id = ? LIMIT 1');
        $s->execute([$byId]);
        $r = $s->fetchColumn();
        return parse_theme_settings($r);
    }
    return [];
}

function maybe_render_theme_from_request() {
    if (!isset($GLOBALS['pdo'])) return '';
    $pdo = $GLOBALS['pdo'];
    $settings = [];
    if (!empty($_GET['vendor_slug'])) {
        $settings = load_vendor_theme_settings($pdo, $_GET['vendor_slug'], null);
    } elseif (!empty($_GET['vendor_id'])) {
        $settings = load_vendor_theme_settings($pdo, null, intval($_GET['vendor_id']));
    }
    if (!empty($settings)) {
        echo render_theme_style($settings);
    }
}

// Currency helper: reads currency preference from GET or cookie, defaults to ARS
function current_currency() {
    if (isset($_GET['currency']) && $_GET['currency'] === 'USD') return 'USD';
    if (isset($_COOKIE['currency']) && $_COOKIE['currency'] === 'USD') return 'USD';
    return 'ARS';
}

// Render a small currency selector form that posts to set_currency.php and redirects back
function render_currency_toggle() {
    $cur = current_currency();
    $redirect = htmlspecialchars($_SERVER['REQUEST_URI'] ?? '/');
    $html = '<form method="post" action="/public/set_currency.php" style="display:inline-block;margin-left:1rem">'
          . '<input type="hidden" name="redirect" value="'.$redirect.'">'
          . '<label style="display:inline-block;margin:0 0.5rem 0 0">Moneda</label>'
          . '<select name="currency" onchange="this.form.submit()">'
          . '<option value="ARS"'.($cur==='ARS' ? ' selected' : '').'>ARS</option>'
          . '<option value="USD"'.($cur==='USD' ? ' selected' : '').'>USD</option>'
          . '</select></form>';
    return $html;
}
