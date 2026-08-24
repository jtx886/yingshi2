/* ===== Jay影视 - 主JavaScript ===== */

(function() {
    'use strict';

    // 通用AJAX请求
    function ajax(url, options) {
        options = options || {};
        return fetch(url, Object.assign({
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }, options)).then(function(r) {
            return r.json().catch(function() { return r.text(); });
        });
    }

    // 收藏/取消收藏
    function toggleFavorite(btn, movieId, movieName, poster, type, year, rating) {
        var icon = btn.querySelector('.heart-icon');
        ajax('<?php echo BASE_URL; ?>/api/favorite.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'toggle',
                movie_id: movieId,
                movie_name: movieName,
                poster: poster,
                type: type,
                year: year,
                rating: rating
            }),
            headers: { 'Content-Type': 'application/json' }
        }).then(function(res) {
            if (res && res.code === 200) {
                if (res.data && res.data.favorited) {
                    btn.classList.add('active');
                    showToast('已添加到收藏', 'success');
                } else {
                    btn.classList.remove('active');
                    showToast('已取消收藏', 'info');
                }
            } else if (res && res.code === 401) {
                window.location.href = '<?php echo BASE_URL; ?>/login.php?need_login=1';
            } else {
                showToast(res ? res.message : '操作失败', 'error');
            }
        });
    }

    // Toast提示
    function showToast(msg, type) {
        type = type || 'info';
        var container = document.querySelector('.toast-container');
        if (!container) {
            container = document.createElement('div');
            container.className = 'toast-container';
            document.body.appendChild(container);
        }
        var toast = document.createElement('div');
        toast.className = 'toast';
        var colors = {success: '#10b981', error: '#ef4444', info: '#3b82f6', warning: '#f59e0b'};
        toast.style.borderLeft = '4px solid ' + (colors[type] || colors.info);
        toast.textContent = msg;
        container.appendChild(toast);
        setTimeout(function() {
            toast.style.transition = 'all 0.3s';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120%)';
            setTimeout(function() { toast.remove(); }, 300);
        }, 3000);
    }

    // Hero banner轮播
    function initHeroCarousel() {
        var dots = document.querySelectorAll('.hero-dot');
        var banners = document.querySelectorAll('[data-hero-index]');
        if (!dots.length) return;
        var current = 0;
        function show(index) {
            current = index;
            dots.forEach(function(d, i) {
                d.classList.toggle('active', i === index);
            });
            banners.forEach(function(b, i) {
                b.style.display = i === index ? 'block' : 'none';
            });
        }
        dots.forEach(function(dot, idx) {
            dot.addEventListener('click', function() { show(idx); });
        });
        setInterval(function() {
            show((current + 1) % dots.length);
        }, 5000);
    }

    // 搜索表单防抖
    function initSearch() {
        var forms = document.querySelectorAll('.search-box');
        forms.forEach(function(form) {
            var input = form.querySelector('.search-input');
            if (!input) return;
            input.addEventListener('keypress', function(e) {
                if (e.key === 'Enter' && !input.value.trim()) {
                    e.preventDefault();
                }
            });
        });
    }

    // 用户菜单点击外部关闭
    function initUserMenu() {
        document.addEventListener('click', function(e) {
            var menus = document.querySelectorAll('.user-menu.active');
            menus.forEach(function(menu) {
                if (!menu.contains(e.target)) {
                    menu.classList.remove('active');
                }
            });
        });
    }

    // 记住我复选框
    function initCustomCheckboxes() {
        document.querySelectorAll('label:has(input[type="checkbox"]), .modal-dont-show').forEach(function(label) {
            var checkbox = label.querySelector('input[type="checkbox"]');
            if (!checkbox) return;
            label.addEventListener('click', function(e) {
                if (e.target.tagName === 'INPUT') return;
                e.preventDefault();
                checkbox.checked = !checkbox.checked;
            });
        });
    }

    // 展开/收起回复
    function initReplyExpand() {
        document.querySelectorAll('[data-expand-replies]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var targetId = btn.getAttribute('data-expand-replies');
                var hiddenReplies = document.querySelectorAll('[data-feedback="' + targetId + '"][data-collapsed="true"]');
                var expanded = btn.getAttribute('data-expanded') === 'true';
                if (expanded) {
                    hiddenReplies.forEach(function(r) { r.style.display = 'none'; });
                    btn.setAttribute('data-expanded', 'false');
                    btn.innerHTML = '展开剩余 ' + hiddenReplies.length + ' 条回复 ▾';
                } else {
                    hiddenReplies.forEach(function(r) { r.style.display = ''; });
                    btn.setAttribute('data-expanded', 'true');
                    btn.innerHTML = '收起回复 ▴';
                }
            });
        });
    }

    // 点赞反馈
    function initLikeButtons() {
        document.querySelectorAll('[data-like-feedback]').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var id = btn.getAttribute('data-like-feedback');
                ajax('<?php echo BASE_URL; ?>/api/feedback.php', {
                    method: 'POST',
                    body: JSON.stringify({ action: 'like', feedback_id: id }),
                    headers: { 'Content-Type': 'application/json' }
                }).then(function(res) {
                    if (res && res.code === 200) {
                        var countEl = btn.querySelector('.like-count');
                        if (countEl) countEl.textContent = res.data.likes;
                        btn.classList.toggle('liked', res.data.liked);
                        btn.style.color = res.data.liked ? 'var(--primary-light)' : '';
                    } else if (res && res.code === 401) {
                        window.location.href = '<?php echo BASE_URL; ?>/login.php?need_login=1';
                    } else {
                        showToast(res ? res.message : '操作失败', 'error');
                    }
                });
            });
        });
    }

    // 初始化 - 对已有按钮绑定收藏事件
    function initFavorites() {
        document.querySelectorAll('[data-favorite]').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var data = JSON.parse(btn.getAttribute('data-favorite'));
                toggleFavorite(btn, data.id, data.name, data.poster, data.type, data.year, data.rating);
            });
        });
    }

    // 电影卡片点击跳转
    function initMovieCards() {
        document.querySelectorAll('[data-movie-link]').forEach(function(card) {
            card.addEventListener('click', function(e) {
                if (e.target.closest('[data-favorite]') || e.target.closest('.card-favorite')) return;
                var link = card.getAttribute('data-movie-link');
                if (link) window.location.href = link;
            });
            card.style.cursor = 'pointer';
        });
    }

    // DOM Ready
    document.addEventListener('DOMContentLoaded', function() {
        initHeroCarousel();
        initSearch();
        initUserMenu();
        initCustomCheckboxes();
        initReplyExpand();
        initLikeButtons();
        initFavorites();
        initMovieCards();
    });

    // 暴露给全局
    window.JayMovie = {
        showToast: showToast,
        toggleFavorite: toggleFavorite,
        ajax: ajax
    };
})();
