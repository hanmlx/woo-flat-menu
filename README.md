# WooCommerce Flat Mega Menu

自动从 WooCommerce 产品分类生成平铺网格菜单，移植自 PrestaShop Hummingbird v3-patch。

## 效果

- 一级分类 → 顶栏横向排列
- Hover/click 一级 → 全宽下拉面板
- 面板内 → 二级分类 3列网格平铺，每列下方列出三级分类
- 无 Tab、无左右分栏
- 响应式：3列 → 2列(平板) → 1列(手机)

## 安装

1. 把 `woo-flat-menu` 文件夹压缩成 `woo-flat-menu.zip`
2. WordPress 后台 → 插件 → 安装插件 → 上传插件 → 选择 zip
3. 启用插件

## 使用

### 方法 A：Shortcode（最简单）

在任意页面/文章/主题位置插入：
```
[woo_flat_menu]
```

### 方法 B：Blocksy / Astra / Kadence Header Builder

在主题的 Header Builder 中添加一个 HTML 区块或 Shortcode 区块，填入 `[woo_flat_menu]`。

### 方法 C：主题文件中直接调用

在 `header.php` 或主题模板中：
```php
<?php echo do_shortcode('[woo_flat_menu]'); ?>
```

## 分类排序

在 WordPress 后台 → 产品 → 分类 下，通过拖拽排序插件（如 Category Order and Taxonomy Terms Order）来调整分类的显示顺序。

插件默认按 `menu_order` 排序。

## 自定义样式

编辑 `css/flat-menu.css` 来调整颜色、间距等。主色调变量：
- 强调色：`#25b9d7`（青色）
- 文字色：`#1a1a1a`（黑色）/ `#555555`（灰色）
- 边框色：`#eeeeee`

## 文件结构

```
woo-flat-menu/
├── woo-flat-menu.php    主插件文件（分类查询 + HTML 输出）
├── css/
│   └── flat-menu.css    样式（移植自 v3-patch menu-beautify.css）
├── js/
│   └── flat-menu.js     交互逻辑（hover/click 下拉）
└── README.md
```

## 与 PrestaShop 版的对应关系

| PrestaShop                          | WooCommerce                        |
|-------------------------------------|------------------------------------|
| `ps_mainmenu.tpl` (Smarty 模板)     | `woo-flat-menu.php` (PHP 模板)     |
| `menu-beautify.css`                 | `flat-menu.css`                    |
| `ps_mainmenu` 模块 JS (hover 行为)  | `flat-menu.js`                     |
| PrestaShop 分类树 (自动)            | WooCommerce `product_cat` (自动)   |

## 注意事项

1. 需要 WooCommerce 已安装并有产品分类
2. 插件自动读取 3 级分类（一级 → 二级 → 三级）
3. 如果有四级分类，当前版本不显示（和 PrestaShop 版一致）
4. 下拉面板使用 `position: absolute`，确保 header 区域有 `position: relative`
