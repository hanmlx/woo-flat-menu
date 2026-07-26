<?php
/**
 * Plugin Name: WooCommerce Flat Mega Menu
 * Plugin URI: https://github.com/nexomi/woo-flat-menu
 * Description: Auto-generate a flat grid mega menu from WooCommerce product categories. Replicates the PrestaShop Hummingbird menu layout.
 * Version: 1.0.0
 * Author: hanmlx
 * Text Domain: woo-flat-menu
 *
 * 结构：
 *   一级分类 → 顶栏横向排列
 *   Hover 一级 → 全宽下拉面板
 *   面板内 → 二级分类 3列网格平铺，每列下方列出三级分类
 *   无 Tab、无左右分栏
 *
 * v1.1.0: 后台可勾选要显示的分类（一级+二级），不勾选则显示全部
 */

if (!defined('ABSPATH')) {
    exit;
}

class Woo_Flat_Menu {

    const VERSION = '1.0.0';
    const OPTION_KEY = 'woo_flat_menu_settings';

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'enqueue_assets']);
        add_shortcode('woo_flat_menu', [$this, 'render_menu_shortcode']);
        add_action('init', [$this, 'register_menu_location']);
        // 后台设置页
        add_action('admin_menu', [$this, 'add_admin_page']);
        add_action('admin_init', [$this, 'register_settings']);
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
     * 加载 CSS 和 JS
     */
    public function enqueue_assets() {
        $base = plugin_dir_url(__FILE__);
        wp_enqueue_style('woo-flat-menu', $base . 'css/flat-menu.css', [], self::VERSION);
        wp_enqueue_script('woo-flat-menu', $base . 'js/flat-menu.js', [], self::VERSION, true);
    }

    // =================================================================
    //  后台设置
    // =================================================================

    /**
     * 添加设置页
     */
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

    /**
     * 注册设置
     */
    public function register_settings() {
        register_setting('woo_flat_menu_group', self::OPTION_KEY, [
            'sanitize_callback' => [$this, 'sanitize_settings'],
        ]);
    }

    /**
     * 清理/格式化保存的数据
     */
    public function sanitize_settings($input) {
        $clean = [
            'selected_cats' => [],
            'excluded_subcats' => [],
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

        $this->clear_cache();

        return $clean;
    }

    /**
     * 获取设置（带默认值）
     */
    private function get_settings() {
        $opts = get_option(self::OPTION_KEY, []);
        if (!is_array($opts)) {
            $opts = [];
        }
        return wp_parse_args($opts, [
            'selected_cats'    => [],  // 空 = 显示全部一级分类
            'excluded_subcats' => [],  // 空 = 不排除任何二级分类
        ]);
    }

    /**
     * 渲染设置页
     */
    public function render_admin_page() {
        $settings  = $this->get_settings();
        $selected  = $settings['selected_cats'];
        $excluded  = $settings['excluded_subcats'];

        // 获取所有一级分类
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
            <p>勾选要在菜单中显示的分类。不勾选任何一级分类 = 显示全部。</p>

            <form method="post" action="options.php">
                <?php settings_fields('woo_flat_menu_group'); ?>

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
            <p>在主题 Header Builder 或模板中插入 shortcode：</p>
            <code>[woo_flat_menu]</code>
            <p>或 PHP 直接调用：</p>
            <code>&lt;?php echo do_shortcode('[woo_flat_menu]'); ?&gt;</code>

            <script>
            jQuery(function($){
                // "显示全部" 互斥逻辑
                $('#wfm-select-all').on('change', function(){
                    var checked = $(this).is(':checked');
                    $('.wfm-cat-checkbox').prop('checked', false).prop('disabled', checked);
                });
                // 初始状态
                if ($('#wfm-select-all').is(':checked')) {
                    $('.wfm-cat-checkbox').prop('disabled', true);
                }
                // 单独勾选某个分类时，取消"显示全部"
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

    /**
     * 清除分类缓存
     */
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
        // 尝试缓存
        $cached = get_transient('woo_flat_menu_tree');
        if (false !== $cached) {
            return $cached;
        }

        $settings = $this->get_settings();
        $selected = $settings['selected_cats'];   // 要显示的一级分类 ID
        $excluded = $settings['excluded_subcats']; // 要排除的二级分类 ID

        $tree = [];

        // 一级分类查询
        $level1_args = [
            'taxonomy'   => 'product_cat',
            'hide_empty' => false,
            'parent'     => 0,
            'orderby'    => 'menu_order',
            'order'      => 'ASC',
        ];

        // 如果勾选了特定分类，用 include 过滤
        if (!empty($selected)) {
            $level1_args['include'] = $selected;
            // include 时按 include 数组顺序排列
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

            // 缩略图
            $thumb_id = get_term_meta($cat1->term_id, 'thumbnail_id', true);
            if ($thumb_id) {
                $node1['thumbnail'] = wp_get_attachment_url($thumb_id);
            }

            // 二级分类
            $level2 = get_terms([
                'taxonomy'   => 'product_cat',
                'hide_empty' => false,
                'parent'     => $cat1->term_id,
                'orderby'    => 'menu_order',
                'order'      => 'ASC',
                'exclude'    => $excluded, // 排除被勾选的二级分类
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

                    // 三级分类
                    $level3 = get_terms([
                        'taxonomy'   => 'product_cat',
                        'hide_empty' => false,
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

        // 缓存 1 小时
        set_transient('woo_flat_menu_tree', $tree, HOUR_IN_SECONDS);

        return $tree;
    }

    // =================================================================
    //  HTML 构建（和 PrestaShop v3 一致）
    // =================================================================

    private function build_menu_html() {
        $tree = $this->get_category_tree();

        if (empty($tree)) {
            return '<!-- WooCommerce Flat Menu: 没有找到产品分类 -->';
        }

        $html = '<nav class="wfm-nav" aria-label="Main navigation">';
        $html .= '<ul class="wfm-nav__list">';

        foreach ($tree as $cat1) {
            $has_children = !empty($cat1['children']);
            $html .= '<li class="wfm-nav__item' . ($has_children ? ' wfm-nav__item--has-children' : '') . '">';

            // 一级分类链接
            $html .= '<div class="wfm-nav__item-wrapper">';
            $html .= '<a class="wfm-nav__link" href="' . esc_url($cat1['url']) . '" data-depth="1">';
            $html .= esc_html($cat1['name']);
            $html .= '</a>';

            if ($has_children) {
                $html .= '<button class="wfm-nav__toggle" type="button" aria-expanded="false" aria-label="Open ' . esc_attr($cat1['name']) . ' submenu"></button>';
            }
            $html .= '</div>';

            // 下拉面板
            if ($has_children) {
                $html .= $this->build_submenu($cat1);
            }

            $html .= '</li>';
        }

        $html .= '</ul>';
        $html .= '</nav>';

        return $html;
    }

    /**
     * 构建二级+三级分类面板（网格平铺）
     */
    private function build_submenu($cat1) {
        $html = '<div class="wfm-submenu" role="menu" aria-label="' . esc_attr($cat1['name']) . ' submenu">';
        $html .= '<div class="wfm-submenu__container">';
        $html .= '<div class="wfm-submenu__content">';
        $html .= '<div class="wfm-submenu__grid">';

        foreach ($cat1['children'] as $cat2) {
            $html .= '<div class="wfm-submenu__col">';

            // 二级分类标题
            $html .= '<a class="wfm-submenu__col-title" href="' . esc_url($cat2['url']) . '" data-depth="2">';
            $html .= esc_html($cat2['name']);
            $html .= '</a>';

            // 三级分类列表
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

            $html .= '</div>'; // .wfm-submenu__col
        }

        $html .= '</div>'; // .wfm-submenu__grid
        $html .= '</div>'; // .wfm-submenu__content
        $html .= '</div>'; // .wfm-submenu__container
        $html .= '</div>'; // .wfm-submenu

        return $html;
    }
}

new Woo_Flat_Menu();
