<?php
/**
 * Plugin Name: WooCommerce Flat Mega Menu
 * Plugin URI: https://github.com/hanmlx/woo-flat-menu
 * Description: Auto-generate a flat grid mega menu from WooCommerce product categories. Replicates the PrestaShop Hummingbird menu layout.
 * Version: 1.1.0
 * Author: hanmlx
 * Text Domain: woo-flat-menu
 *
 * 结构：
 *   一级分类 → 顶栏横向排列
 *   Hover 一级 → 全宽下拉面板
 *   面板内 → 二级分类 3列网格平铺，每列下方列出三级分类
 *   无 Tab、无左右分栏
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Flat_Menu {

    const VERSION = '1.1.0';
    const OPTION_KEY = 'woo_flat_menu_settings';

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('woo_flat_menu', [$this, 'render_menu_shortcode']);
        add_action('init', [$this, 'register_menu_location']);
        // 后台设置页
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        // 产品分类增删时清除缓存
        add_action('created_product_cat', [$this, 'clear_cache']);
        add_action('edited_product_cat', [$this, 'clear_cache']);
        add_action('delete_product_cat', [$this, 'clear_cache']);
    }

    /**
     * 注册菜单位置
     */
    public function register_menu_location() {
        register_nav_menus([
            'woo-flat-menu' => __('WooCommerce Flat Mega Menu', 'woo-flat-menu'),
        ]);
    }

    /**
     * 加载前端 CSS 和 JS + 注入动态颜色
     */
    public function enqueue_assets() {
        $base = plugin_dir_url(__FILE__);
        wp_enqueue_style('woo-flat-menu', $base . 'css/flat-menu.css', [], self::VERSION);
        wp_enqueue_script('woo-flat-menu', $base . 'js/flat-menu.js', [], self::VERSION, true);

        // 注入动态颜色变量
        $settings = $this->get_settings();
        $color = $settings['accent_color'];
        $custom_css = ":root { --wfm-accent: {$color}; }";
        wp_add_inline_style('woo-flat-menu', $custom_css);
    }

    /**
     * 后台加载颜色选择器
     */
    public function enqueue_admin_assets($hook) {
        if ('toplevel_page_woo-flat-menu' !== $hook) {
            return;
        }
        wp_enqueue_style('wp-color-picker');
        wp_enqueue_script('wp-color-picker');
    }

    // =================================================================
    //  后台设置
    // =================================================================

    public function add_admin_page() {
        add_menu_page(
            'Flat Menu 设置',
            'Flat Menu',
            'manage_options',
            'woo-flat-menu',
            [$this, 'render_admin_page'],
            'dashicons-menu-alt',
            58
        );
    }

    public function register_settings() {
        register_setting('woo_flat_menu_group', self::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    public function sanitize_settings($input) {
        $clean = [
            'selected_cats'      => [],
            'excluded_subcats'   => [],
            'accent_color'       => '#2563eb',
            'hide_empty'         => 0,
            'submenu_width'      => 'content',
        ];

        // 一级分类勾选
        if (!empty($input['selected_cats']) && is_array($input['selected_cats'])) {
            foreach ($input['selected_cats'] as $term_id) {
                $term_id = intval($term_id);
                if ($term_id > 0 && term_exists($term_id, 'product_cat')) {
                    $clean['selected_cats'][] = $term_id;
                }
            }
        }

        // 二级分类排除
        if (!empty($input['excluded_subcats']) && is_array($input['excluded_subcats'])) {
            foreach ($input['excluded_subcats'] as $term_id) {
                $term_id = intval($term_id);
                if ($term_id > 0 && term_exists($term_id, 'product_cat')) {
                    $clean['excluded_subcats'][] = $term_id;
                }
            }
        }

        // 颜色
        if (!empty($input['accent_color'])) {
            $color = sanitize_hex_color($input['accent_color']);
            if ($color) {
                $clean['accent_color'] = $color;
            }
        }

        // 隐藏空分类
        $clean['hide_empty'] = !empty($input['hide_empty']) ? 1 : 0;

        // 下拉宽度
        if (!empty($input['submenu_width']) && in_array($input['submenu_width'], ['full', 'content'])) {
            $clean['submenu_width'] = $input['submenu_width'];
        }

        $this->clear_cache();

        return $clean;
    }

    private function get_settings() {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }
        return wp_parse_args($opts, [
            'selected_cats'    => [],
            'excluded_subcats' => [],
            'accent_color'     => '#2563eb',
            'hide_empty'       => 0,
            'submenu_width'    => 'content',
        ]);
    }

    /**
     * 渲染设置页
     */
    public function render_admin_page() {
        $settings  = $this->get_settings();
        $selected  = $settings['selected_cats'];
        $excluded  = $settings['excluded_subcats'];
        $color     = $settings['accent_color'];
        $hide_empty = $settings['hide_empty'];
        $width     = $settings['submenu_width'];

        $level1_cats = get_terms([
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ]);

        $all_selected = empty($selected);
        ?>
        <div class="wrap">
            <h1>Flat Menu 设置</h1>

            <form method="post" action="options.php">
                <?php settings_fields('woo_flat_menu_group'); ?>

                <!-- 外观设置 -->
                <h2 class="title">外观设置</h2>
                <table class="form-table">
                    <tr>
                        <th>主题色</th>
                        <td>
                            <input type="text"
                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[accent_color]"
                                value="<?php echo esc_attr($color); ?>"
                                class="wfm-color-picker" />
                            <p class="description">用于链接 hover、下划线、边框等。默认 #2563eb（蓝色）。</p>
                        </td>
                    </tr>
                    <tr>
                        <th>下拉面板宽度</th>
                        <td>
                            <label>
                                <input type="radio"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[submenu_width]"
                                    value="full"
                                    <?php checked($width, 'full'); ?> />
                                <strong>Full（全宽）</strong>
                                <span class="description">面板横跨整个屏幕宽度</span>
                            </label>
                            <br />
                            <label style="margin-top:8px;display:inline-block;">
                                <input type="radio"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[submenu_width]"
                                    value="content"
                                    <?php checked($width, 'content'); ?> />
                                <strong>Content（内容宽度）</strong>
                                <span class="description">面板最大宽度 1200px，居中显示</span>
                            </label>
                        </td>
                    </tr>
                    <tr>
                        <th>隐藏空分类</th>
                        <td>
                            <label>
                                <input type="checkbox"
                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[hide_empty]"
                                    value="1"
                                    <?php checked($hide_empty, 1); ?> />
                                <strong>隐藏没有产品的子分类</strong>
                            </label>
                            <p class="description">勾选后，产品数量为 0 的二级和三级分类不会显示在菜单中。</p>
                        </td>
                    </tr>
                </table>

                <!-- 一级分类 -->
                <h2 class="title">一级分类（顶栏）</h2>
                <table class="form-table">
                    <tr>
                        <th>显示哪些分类？</th>
                        <td>
                            <label>
                                <input type="checkbox" id="wfm-select-all" <?php checked($all_selected); ?> />
                                <strong>显示全部</strong>
                                <span class="description">（勾选此项 = 忽略下面的勾选，显示所有一级分类）</span>
                            </label>
                            <hr style="margin: 12px 0;" />
                            <div style="display:flex;flex-wrap:wrap;gap:12px 24px;">
                                <?php if (is_wp_error($level1_cats) || empty($level1_cats)): ?>
                                    <p>没有找到产品分类。先去 WooCommerce → 产品 → 分类 里添加一些。</p>
                                <?php else: ?>
                                    <?php foreach ($level1_cats as $cat1): ?>
                                        <label style="display:inline-flex;align-items:flex-start;gap:6px;min-width:200px;">
                                            <input type="checkbox"
                                                name="<?php echo esc_attr(self::OPTION_KEY); ?>[selected_cats][]"
                                                value="<?php echo esc_attr($cat1->term_id); ?>"
                                                class="wfm-cat-checkbox"
                                                <?php checked(in_array($cat1->term_id, $selected)); ?> />
                                            <span><?php echo esc_html($cat1->name); ?></span>
                                        </label>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                </table>

                <!-- 二级分类排除 -->
                <?php if (!is_wp_error($level1_cats) && !empty($level1_cats)): ?>
                    <h2 class="title">二级分类排除（可选）</h2>
                    <p>勾选要从下拉面板中隐藏的二级分类。不勾选 = 显示全部。</p>
                    <table class="form-table">
                        <?php foreach ($level1_cats as $cat1): ?>
                            <?php
                            $level2 = get_terms([
                                'taxonomy'   => 'product_cat',
                                'hide_empty' => false,
                                'parent'     => $cat1->term_id,
                                'orderby'    => 'menu_order',
                                'order'      => 'ASC',
                            ]);
                            if (is_wp_error($level2) || empty($level2)) {
                                continue;
                            }
                            ?>
                            <tr>
                                <th><?php echo esc_html($cat1->name); ?></th>
                                <td>
                                    <div style="display:flex;flex-wrap:wrap;gap:8px 20px;">
                                        <?php foreach ($level2 as $cat2): ?>
                                            <label style="display:inline-flex;align-items:center;gap:4px;">
                                                <input type="checkbox"
                                                    name="<?php echo esc_attr(self::OPTION_KEY); ?>[excluded_subcats][]"
                                                    value="<?php echo esc_attr($cat2->term_id); ?>"
                                                    <?php checked(in_array($cat2->term_id, $excluded)); ?> />
                                                <span><?php echo esc_html($cat2->name); ?></span>
                                            </label>
                                        <?php endforeach; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </table>
                <?php endif; ?>

                <?php submit_button('保存设置'); ?>
            </form>

            <hr />
            <h3>使用方法</h3>
            <p>Shortcode：</p>
            <code>[woo_flat_menu]</code>

            <script>
            jQuery(function($){
                // 颜色选择器
                $('.wfm-color-picker').wpColorPicker();

                // "显示全部" 互斥逻辑
                $('#wfm-select-all').on('change', function(){
                    var checked = $(this).is(':checked');
                    $('.wfm-cat-checkbox').prop('checked', false).prop('disabled', checked);
                });
                if ($('#wfm-select-all').is(':checked')) {
                    $('.wfm-cat-checkbox').prop('disabled', true);
                }
                $('.wfm-cat-checkbox').on('change', function(){
                    if ($(this).is(':checked')) {
                        $('#wfm-select-all').prop('checked', false);
                        $('.wfm-cat-checkbox').prop('disabled', false);
                    }
                });
            });
            </script>
        </div>
        <?php
    }

    public function clear_cache() {
        delete_transient('woo_flat_menu_tree');
    }

    // =================================================================
    //  Shortcode
    // =================================================================

    public function render_menu_shortcode($atts = []) {
        return $this->build_menu_html();
    }

    // =================================================================
    //  分类树获取（带设置过滤）
    // =================================================================

    private function get_category_tree() {
        $cached = get_transient('woo_flat_menu_tree');
        if (false !== $cached) {
            return $cached;
        }

        $settings   = $this->get_settings();
        $selected   = $settings['selected_cats'];
        $excluded   = $settings['excluded_subcats'];
        $hide_empty = !empty($settings['hide_empty']);

        $tree = [];

        $level1_args = [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false, // 一级始终显示
            'parent'     => 0,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ];

        if (!empty($selected)) {
            $level1_args['include'] = $selected;
            $level1_args['orderby'] = 'include';
        }

        $level1 = get_terms($level1_args);

        if (is_wp_error($level1) || empty($level1)) {
            return $tree;
        }

        foreach ($level1 as $cat1) {
            $node1 = [
                'id'        => $cat1->term_id,
                'name'      => $cat1->name,
                'slug'      => $cat1->slug,
                'url'       => get_term_link($cat1),
                'thumbnail' => '',
                'children'  => [],
            ];

            $thumb_id = get_term_meta($cat1->term_id, 'thumbnail_id', true);
            if ($thumb_id) {
                $node1['thumbnail'] = wp_get_attachment_url($thumb_id);
            }

            // 二级分类 — hide_empty 根据设置
            $level2 = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => $hide_empty,
                'parent'     => $cat1->term_id,
                'orderby'    => 'menu_order',
                'order'      => 'ASC',
                'exclude'    => $excluded,
            ]);

            if (!is_wp_error($level2) && !empty($level2)) {
                foreach ($level2 as $cat2) {
                    $node2 = [
                        'id'       => $cat2->term_id,
                        'name'     => $cat2->name,
                        'slug'     => $cat2->slug,
                        'url'      => get_term_link($cat2),
                        'children' => [],
                    ];

                    // 三级分类 — hide_empty 根据设置
                    $level3 = get_terms([
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => $hide_empty,
                        'parent'     => $cat2->term_id,
                        'orderby'    => 'menu_order',
                        'order'      => 'ASC',
                    ]);

                    if (!is_wp_error($level3) && !empty($level3)) {
                        foreach ($level3 as $cat3) {
                            $node2['children'][] = [
                                'id'   => $cat3->term_id,
                                'name' => $cat3->name,
                                'slug' => $cat3->slug,
                                'url'  => get_term_link($cat3),
                            ];
                        }
                    }

                    $node1['children'][] = $node2;
                }
            }

            $tree[] = $node1;
        }

        set_transient('woo_flat_menu_tree', $tree, HOUR_IN_SECONDS);

        return $tree;
    }

    // =================================================================
    //  HTML 构建
    // =================================================================

    private function build_menu_html() {
        $tree = $this->get_category_tree();

        if (empty($tree)) {
            return '<!-- WooCommerce Flat Menu: 没有找到产品分类 -->';
        }

        $settings = $this->get_settings();
        $width_class = $settings['submenu_width'] === 'full' ? ' wfm-nav--full' : '';

        $html = '<nav class="wfm-nav' . $width_class . '" aria-label="Main navigation">';
        $html .= '<ul class="wfm-nav__list">';

        foreach ($tree as $cat1) {
            $has_children = !empty($cat1['children']);
            $html .= '<li class="wfm-nav__item' . ($has_children ? ' wfm-nav__item--has-children' : '') . '">';

            $html .= '<div class="wfm-nav__item-wrapper">';
            $html .= '<a class="wfm-nav__link" href="' . esc_url($cat1['url']) . '" data-depth="1">';
            $html .= esc_html($cat1['name']);
            $html .= '</a>';

            if ($has_children) {
                $html .= '<button class="wfm-nav__toggle" type="button" aria-expanded="false" aria-label="Open ' . esc_attr($cat1['name']) . ' submenu"></button>';
            }
            $html .= '</div>';

            if ($has_children) {
                $html .= $this->build_submenu($cat1);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }

    private function build_submenu($cat1) {
        $html = '<div class="wfm-submenu" role="menu" aria-label="' . esc_attr($cat1['name']) . ' submenu">';
        $html .= '<div class="wfm-submenu__container">';
        $html .= '<div class="wfm-submenu__content">';
        $html .= '<div class="wfm-submenu__grid">';

        foreach ($cat1['children'] as $cat2) {
            $html .= '<div class="wfm-submenu__col">';
            $html .= '<a class="wfm-submenu__col-title" href="' . esc_url($cat2['url']) . '" data-depth="2">';
            $html .= esc_html($cat2['name']);
            $html .= '</a>';

            if (!empty($cat2['children'])) {
                $html .= '<ul class="wfm-submenu__col-list">';
                foreach ($cat2['children'] as $cat3) {
                    $html .= '<li>';
                    $html .= '<a href="' . esc_url($cat3['url']) . '" data-depth="3">';
                    $html .= esc_html($cat3['name']);
                    $html .= '</a>';
                    $html .= '</li>';
                }
                $html .= '</ul>';
            }

            $html .= '</div>';
        }

        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }
}

new Woo_Flat_Menu();
