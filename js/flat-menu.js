/* ============================================
   WooCommerce Flat Mega Menu - 交互逻辑
   - 桌面端：hover 展开/收起
   - 移动端/平板：click 展开/收起
   - 点击外部关闭
   ============================================ */

(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var nav = document.querySelector('.wfm-nav');
        if (!nav) return;

        var items = nav.querySelectorAll('.wfm-nav__item--has-children');
        var isTouch = ('ontouchstart' in window) || (navigator.maxTouchPoints > 0);
        var closeTimer = null;

        items.forEach(function (item) {
            var toggle = item.querySelector('.wfm-nav__toggle');

            // 点击切换（移动端 + 桌面端点击箭头）
            if (toggle) {
                toggle.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var isActive = item.classList.contains('active');

                    // 关闭所有其他
                    items.forEach(function (other) {
                        if (other !== item) {
                            other.classList.remove('active');
                            var t = other.querySelector('.wfm-nav__toggle');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        }
                    });

                    if (isActive) {
                        item.classList.remove('active');
                        toggle.setAttribute('aria-expanded', 'false');
                    } else {
                        item.classList.add('active');
                        toggle.setAttribute('aria-expanded', 'true');
                    }
                });
            }

            // 桌面端 hover
            if (!isTouch) {
                item.addEventListener('mouseenter', function () {
                    clearTimeout(closeTimer);
                    items.forEach(function (other) {
                        if (other !== item) {
                            other.classList.remove('active');
                            var t = other.querySelector('.wfm-nav__toggle');
                            if (t) t.setAttribute('aria-expanded', 'false');
                        }
                    });
                    item.classList.add('active');
                    if (toggle) toggle.setAttribute('aria-expanded', 'true');
                });

                item.addEventListener('mouseleave', function () {
                    closeTimer = setTimeout(function () {
                        item.classList.remove('active');
                        if (toggle) toggle.setAttribute('aria-expanded', 'false');
                    }, 150);
                });
            }
        });

        // 点击外部关闭
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target)) {
                items.forEach(function (item) {
                    item.classList.remove('active');
                    var t = item.querySelector('.wfm-nav__toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            }
        });

        // ESC 关闭
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') {
                items.forEach(function (item) {
                    item.classList.remove('active');
                    var t = item.querySelector('.wfm-nav__toggle');
                    if (t) t.setAttribute('aria-expanded', 'false');
                });
            }
        });
    });
})();
