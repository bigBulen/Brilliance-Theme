<?php
/**
 * 后台主题设置页
 */

if (!defined('ABSPATH')) exit;

function mimosa_admin_menu() {
    add_menu_page(
        'Brilliance 主题设置',
        'Brilliance 设置',
        'manage_options',
        'mimosa-settings',
        'mimosa_settings_page',
        'dashicons-art',
        60
    );
}
add_action('admin_menu', 'mimosa_admin_menu');

function mimosa_register_settings() {
    $options = array(
        'mimosa_site_title', 'mimosa_banner_subtitle', 'mimosa_intro',
        'mimosa_avatar_url', 'mimosa_logo_attachment_id',
        'mimosa_banner_bg_light', 'mimosa_banner_bg_dark',
        'mimosa_banner_overlay_light', 'mimosa_banner_overlay_dark',
        'mimosa_banner_ambient_color', 'mimosa_banner_ambient_enable', 'mimosa_banner_ambient_mode',
        'mimosa_page_bg_dark_color', 'mimosa_page_bg_light_color',
        'mimosa_page_bg_dark_image', 'mimosa_page_bg_light_image',
        'mimosa_banner_wind_enable', 'mimosa_banner_wind_count',
        'mimosa_accent_color', 'mimosa_card_opacity',
        'brilliance_home_sidebar_enable', 'brilliance_home_sidebar_position',
        'mimosa_footer_custom_html',
        'mimosa_seo_description',
        'mimosa_search_enable', 'mimosa_rss_include_shuoshuo',
        'mimosa_acgn_cover_sideload_enable',
        'mimosa_impressions_slug', 'mimosa_impressions_center_text',
        'argon_mimosa_custom_post_types', 'argon_mimosa_book_map',
        'brilliance_wordcount_enable',
        // 评论设置
        'mimosa_comment_order', 'mimosa_comment_per_page',
        'mimosa_comment_fold_threshold', 'mimosa_comment_reply_depth',
        // Banner 赛博朋克和印章
        'mimosa_banner_title_zh', 'mimosa_banner_title_en',
        'mimosa_banner_subtitle_zh', 'mimosa_banner_subtitle_en',
        'mimosa_banner_stamps', 'mimosa_stamps_count', 'mimosa_stamps_size', 'mimosa_stamps_opacity',
        // 说说折叠
        'mimosa_shuoshuo_fold_threshold',
        // SEO 设置
        'brilliance_seo_site_name', 'brilliance_seo_default_image',
        'brilliance_seo_twitter_card', 'brilliance_seo_twitter_site',
        'brilliance_seo_keywords',
        // 可读性设置
        'brilliance_paragraph_spacing',
        // 状态监测采集方式
        'brilliance_status_collect_mode',
        'brilliance_site_notice',
        // Reaction 设置
        'mimosa_reactions_enable', 'mimosa_reactions_quick_emojis',
        // 相似推荐 / 文章底部说明
        'mimosa_related_enable', 'mimosa_related_basis', 'mimosa_related_count',
        'mimosa_post_extra_enable', 'mimosa_post_extra_license',
        // 评论验证码 / Gravatar / 贴纸反色
        'mimosa_captcha_enable', 'mimosa_gravatar_cdn', 'mimosa_stamp_invert',
    );
    foreach ($options as $opt) {
        register_setting('mimosa_settings_group', $opt);
    }
}
add_action('admin_init', 'mimosa_register_settings');

function mimosa_settings_page() {
    if (!current_user_can('manage_options')) wp_die('无权限');
    $saved = isset($_GET['settings-updated']);
    ?>
    <div class="wrap">
        <h1>Brilliance 主题设置</h1>
        <?php if ($saved): ?>
        <div class="notice notice-success is-dismissible"><p>设置已保存。</p></div>
        <?php endif; ?>

        <style>
            .brilliance-settings-form h2 {
                margin-top: 1.8em;
                padding-bottom: .35em;
                border-bottom: 1px solid #dcdcde;
            }
            .brilliance-settings-form h3 {
                margin-top: 1.5em;
                margin-bottom: .4em;
                font-size: 1.05em;
                color: #1d2327;
            }
            .brilliance-settings-form h2,
            .brilliance-settings-form h3 {
                scroll-margin-top: 60px;
            }
            .brilliance-settings-toc {
                position: fixed;
                top: 100px;
                right: 24px;
                width: 216px;
                max-height: calc(100vh - 140px);
                overflow-y: auto;
                padding: 14px 16px;
                background: #fff;
                border: 1px solid #dcdcde;
                border-radius: 8px;
                box-shadow: 0 2px 10px rgba(0, 0, 0, .06);
                font-size: 13px;
                z-index: 100;
            }
            .brilliance-settings-toc__title {
                font-weight: 600;
                margin-bottom: 8px;
                color: #1d2327;
            }
            .brilliance-settings-toc__list {
                margin: 0;
                padding: 0;
                list-style: none;
            }
            .brilliance-settings-toc__list a {
                display: block;
                padding: 4px 6px;
                border-radius: 4px;
                color: #2c3338;
                text-decoration: none;
                line-height: 1.45;
            }
            .brilliance-settings-toc__list a:hover {
                background: #f0f0f1;
            }
            .brilliance-settings-toc__list .toc-l1 > a {
                font-weight: 600;
                margin-top: 6px;
            }
            .brilliance-settings-toc__list .toc-l2 > a {
                padding-left: 18px;
                color: #646970;
            }
            .brilliance-settings-toc__list a.is-active {
                background: #f0f0f1;
                color: #2271b1;
            }
            @media (min-width: 1401px) {
                .brilliance-settings-form { max-width: calc(100% - 280px); }
            }
            @media (max-width: 1400px) {
                .brilliance-settings-toc { display: none; }
            }
        </style>

        <nav class="brilliance-settings-toc" aria-label="设置目录">
            <div class="brilliance-settings-toc__title">设置目录</div>
            <ul class="brilliance-settings-toc__list"></ul>
        </nav>

        <form class="brilliance-settings-form" method="post" action="options.php">
            <?php settings_fields('mimosa_settings_group'); ?>

            <h2>首页 Banner</h2>

            <h3>Banner 文本与头像</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_avatar_url">头像 URL</label></th>
                    <td><input type="url" id="mimosa_avatar_url" name="mimosa_avatar_url" class="regular-text"
                               value="<?php echo esc_attr(get_option('mimosa_avatar_url', '')); ?>"></td>
                </tr>
                <tr>
                    <th><label for="mimosa_intro">个人简介</label></th>
                    <td>
                        <textarea id="mimosa_intro" name="mimosa_intro" rows="3" class="large-text"><?php echo esc_textarea(get_option('mimosa_intro', '')); ?></textarea>
                        <p class="description">支持简单 HTML（&lt;strong&gt;、&lt;a&gt; 等）。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_title_zh">Banner 标题（最终显示）</label></th>
                    <td><input type="text" id="mimosa_banner_title_zh" name="mimosa_banner_title_zh" class="regular-text"
                               value="<?php echo esc_attr(get_option('mimosa_banner_title_zh', '')); ?>">
                        <p class="description">留空则使用站点名称</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_title_en">Banner 标题（初始文本）</label></th>
                    <td><input type="text" id="mimosa_banner_title_en" name="mimosa_banner_title_en" class="regular-text"
                               value="<?php echo esc_attr(get_option('mimosa_banner_title_en', '')); ?>">
                        <p class="description">赛博朋克翻译效果的初始文本</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_subtitle_zh">Banner 副标题（最终文本）</label></th>
                    <td><input type="text" id="mimosa_banner_subtitle_zh" name="mimosa_banner_subtitle_zh" class="regular-text"
                               value="<?php echo esc_attr(get_option('mimosa_banner_subtitle_zh', '')); ?>">
                        <p class="description">留空则使用站点副标题</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_subtitle_en">Banner 副标题（初始文本）</label></th>
                    <td><input type="text" id="mimosa_banner_subtitle_en" name="mimosa_banner_subtitle_en" class="regular-text"
                               value="<?php echo esc_attr(get_option('mimosa_banner_subtitle_en', '')); ?>">
                        <p class="description">赛博朋克翻译效果的初始文本</p>
                    </td>
                </tr>
            </table>

            <h3>Banner 背景图</h3>
            <p>光晕启用时优先展示光晕。光晕关闭后，才使用当前亮/暗模式下的 Banner 背景图。</p>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_banner_bg_dark">Banner暗色模式背景 URL</label></th>
                    <td><input type="url" id="mimosa_banner_bg_dark" name="mimosa_banner_bg_dark" class="regular-text"
                               value="<?php echo esc_attr(get_option('mimosa_banner_bg_dark', '')); ?>"></td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_bg_light">Banner浅色模式背景 URL</label></th>
                    <td><input type="url" id="mimosa_banner_bg_light" name="mimosa_banner_bg_light" class="regular-text"
                               value="<?php echo esc_attr(get_option('mimosa_banner_bg_light', '')); ?>"></td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_overlay_dark">暗色遮罩透明度</label></th>
                    <td>
                        <input type="number" id="mimosa_banner_overlay_dark" name="mimosa_banner_overlay_dark"
                               min="0" max="1" step="0.05" style="width:80px;"
                               value="<?php echo esc_attr(get_option('mimosa_banner_overlay_dark', '0.45')); ?>">
                        <p class="description">0 = 无遮罩，1 = 全黑，建议 0.3~0.6。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_overlay_light">浅色遮罩透明度</label></th>
                    <td><input type="number" id="mimosa_banner_overlay_light" name="mimosa_banner_overlay_light"
                               min="0" max="1" step="0.05" style="width:80px;"
                               value="<?php echo esc_attr(get_option('mimosa_banner_overlay_light', '0.25')); ?>"></td>
                </tr>
            </table>

            <h3>Banner 光晕</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_banner_ambient_enable">Banner 光晕效果</label></th>
                    <td>
                        <select id="mimosa_banner_ambient_enable" name="mimosa_banner_ambient_enable">
                            <option value="yes" <?php selected(get_option('mimosa_banner_ambient_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no" <?php selected(get_option('mimosa_banner_ambient_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">启用时光晕优先于 Banner 背景图显示。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_ambient_color">Banner 光晕主色</label></th>
                    <td>
                        <input type="color" id="mimosa_banner_ambient_color" name="mimosa_banner_ambient_color" value="<?php echo esc_attr(get_option('mimosa_banner_ambient_color', '#79c9a0')); ?>">
                        <p class="description">作为浅绿基础色，自动与深绿、青绿色叠加成 Banner 背景光晕。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_ambient_mode">Banner 光晕模式</label></th>
                    <td>
                        <select id="mimosa_banner_ambient_mode" name="mimosa_banner_ambient_mode">
                            <option value="dynamic" <?php selected(get_option('mimosa_banner_ambient_mode', 'static'), 'dynamic'); ?>>动态呼吸光晕</option>
                            <option value="static" <?php selected(get_option('mimosa_banner_ambient_mode', 'static'), 'static'); ?>>静态渐变光晕</option>
                        </select>
                        <p class="description">动态模式仅缓慢移动背景光晕，不影响文字或内容区域。</p>
                    </td>
                </tr>
            </table>

            <h3>Banner 风线</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_banner_wind_enable">Banner 风线效果</label></th>
                    <td>
                        <select id="mimosa_banner_wind_enable" name="mimosa_banner_wind_enable">
                            <option value="yes" <?php selected(get_option('mimosa_banner_wind_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no" <?php selected(get_option('mimosa_banner_wind_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">半透明风线将以平滑起伏的轨迹，不定时从左向右穿过 Banner。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_banner_wind_count">风线数量</label></th>
                    <td>
                        <input type="number" id="mimosa_banner_wind_count" name="mimosa_banner_wind_count" min="1" max="6" value="<?php echo esc_attr(get_option('mimosa_banner_wind_count', '3')); ?>" style="width:80px;">
                        <p class="description">可选 1–6 条；建议保持 3 条以获得克制的动态感。</p>
                    </td>
                </tr>
            </table>

            <h3>背景印章（贴纸）</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_banner_stamps">背景印章（URL列表）</label></th>
                    <td>
                        <textarea id="mimosa_banner_stamps" name="mimosa_banner_stamps" rows="5" class="large-text"><?php echo esc_textarea(get_option('mimosa_banner_stamps', '')); ?></textarea>
                        <p class="description">每行一个图片 URL，将随机分布在全页背景。建议使用透明 PNG。</p>
                        <p class="description">若设置了页面背景图片，背景印章将不生效。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_stamp_invert">贴纸自动反色</label></th>
                    <td>
                        <select id="mimosa_stamp_invert" name="mimosa_stamp_invert">
                            <option value="yes" <?php selected(get_option('mimosa_stamp_invert', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no"  <?php selected(get_option('mimosa_stamp_invert', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">开启后，暗色模式下对贴纸应用反色，防止纯黑贴纸在深色背景下不可见；亮色模式保持原样。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_stamps_count">印章显示数量</label></th>
                    <td>
                        <input type="number" id="mimosa_stamps_count" name="mimosa_stamps_count" min="0" max="20" value="<?php echo esc_attr(get_option('mimosa_stamps_count', '6')); ?>" style="width: 80px;">
                        <p class="description">从印章列表中随机选取多少个显示（默认 6，0 为禁用）</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_stamps_size">印章大小（像素）</label></th>
                    <td>
                        <input type="number" id="mimosa_stamps_size" name="mimosa_stamps_size" min="100" max="800" value="<?php echo esc_attr(get_option('mimosa_stamps_size', '280')); ?>" style="width: 80px;">
                        <p class="description">印章图片的宽度（默认 280px）</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_stamps_opacity">印章透明度</label></th>
                    <td>
                        <input type="number" id="mimosa_stamps_opacity" name="mimosa_stamps_opacity" min="0" max="1" step="0.01" value="<?php echo esc_attr(get_option('mimosa_stamps_opacity', '0.04')); ?>" style="width: 80px;">
                        <p class="description">0 = 完全透明，1 = 不透明（默认 0.04）</p>
                    </td>
                </tr>
            </table>

            <h2>外观与布局</h2>

            <h3>主题色</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_accent_color">强调色</label></th>
                    <td>
                        <input type="color" id="mimosa_accent_color" name="mimosa_accent_color"
                               value="<?php echo esc_attr(get_option('mimosa_accent_color', '#7c6af7')); ?>">
                        <p class="description">用于链接、按钮、高亮等元素。</p>
                    </td>
                </tr>
            </table>

            <h3>卡片透明度</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_card_opacity">卡片背景不透明度</label></th>
                    <td>
                        <input type="number" id="mimosa_card_opacity" name="mimosa_card_opacity"
                               min="0" max="1" step="0.05" style="width:100px;"
                               value="<?php echo esc_attr(get_option('mimosa_card_opacity', '1')); ?>">
                        <p class="description">作用于所有卡片背景（预览卡片、文章正文、评论、友链、ACGN 等）。1 = 完全不透明，0 = 完全透明。建议 0.7~1。</p>
                    </td>
                </tr>
            </table>

            <h3>页面背景</h3>
            <p>作为全站最底层背景。Banner 未启用光晕且当前模式未设置 Banner 背景图时，会直接透出此背景。</p>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_page_bg_dark_color">暗色模式背景色</label></th>
                    <td><input type="color" id="mimosa_page_bg_dark_color" name="mimosa_page_bg_dark_color" value="<?php echo esc_attr(get_option('mimosa_page_bg_dark_color', '#0f0f13')); ?>"></td>
                </tr>
                <tr>
                    <th><label for="mimosa_page_bg_light_color">浅色模式背景色</label></th>
                    <td><input type="color" id="mimosa_page_bg_light_color" name="mimosa_page_bg_light_color" value="<?php echo esc_attr(get_option('mimosa_page_bg_light_color', '#f5f5f8')); ?>"></td>
                </tr>
                <tr>
                    <th><label for="mimosa_page_bg_dark_image">暗色模式背景图片 URL</label></th>
                    <td><input type="url" id="mimosa_page_bg_dark_image" name="mimosa_page_bg_dark_image" class="regular-text" value="<?php echo esc_attr(get_option('mimosa_page_bg_dark_image', '')); ?>"></td>
                </tr>
                <tr>
                    <th><label for="mimosa_page_bg_light_image">浅色模式背景图片 URL</label></th>
                    <td><input type="url" id="mimosa_page_bg_light_image" name="mimosa_page_bg_light_image" class="regular-text" value="<?php echo esc_attr(get_option('mimosa_page_bg_light_image', '')); ?>"></td>
                </tr>
            </table>

            <h3>首页布局</h3>
            <table class="form-table">
                <tr>
                    <th><label for="brilliance_home_sidebar_enable">首页副栏</label></th>
                    <td>
                        <select id="brilliance_home_sidebar_enable" name="brilliance_home_sidebar_enable">
                            <option value="yes" <?php selected(get_option('brilliance_home_sidebar_enable', 'no'), 'yes'); ?>>启用</option>
                            <option value="no" <?php selected(get_option('brilliance_home_sidebar_enable', 'no'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">启用后首页显示副栏（小工具请在「外观 → 小工具」的「首页副栏」区域添加）。关闭时不显示副栏任何内容，即使已添加了小工具。移动端副栏内容会显示在顶部菜单展开区。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="brilliance_home_sidebar_position">副栏位置</label></th>
                    <td>
                        <select id="brilliance_home_sidebar_position" name="brilliance_home_sidebar_position">
                            <option value="right" <?php selected(get_option('brilliance_home_sidebar_position', 'right'), 'right'); ?>>右侧</option>
                            <option value="left" <?php selected(get_option('brilliance_home_sidebar_position', 'right'), 'left'); ?>>左侧</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h3>可读性</h3>
            <table class="form-table">
                <tr>
                    <th><label for="brilliance_paragraph_spacing">段落间距</label></th>
                    <td>
                        <input type="number" id="brilliance_paragraph_spacing" name="brilliance_paragraph_spacing" 
                               min="0.5" max="3" step="0.1" style="width:80px;"
                               value="<?php echo esc_attr(get_option('brilliance_paragraph_spacing', '1.5')); ?>">
                        <span> em</span>
                        <p class="description">文章/页面/说说正文段落间距（默认 1.5em）</p>
                    </td>
                </tr>
            </table>

            <h2>内容与交互</h2>

            <h3>文章详情</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_related_enable">相似推荐</label></th>
                    <td>
                        <select id="mimosa_related_enable" name="mimosa_related_enable">
                            <option value="yes" <?php selected(get_option('mimosa_related_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no"  <?php selected(get_option('mimosa_related_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">在文章内容下方、评论区上方展示相关文章推荐卡片。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_related_basis">推荐依据</label></th>
                    <td>
                        <select id="mimosa_related_basis" name="mimosa_related_basis">
                            <option value="category" <?php selected(get_option('mimosa_related_basis', 'category'), 'category'); ?>>分类</option>
                            <option value="tag" <?php selected(get_option('mimosa_related_basis', 'category'), 'tag'); ?>>标签</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_related_count">推荐数量</label></th>
                    <td>
                        <input type="number" id="mimosa_related_count" name="mimosa_related_count" min="1" max="12" value="<?php echo esc_attr(get_option('mimosa_related_count', '6')); ?>" style="width:80px;">
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_post_extra_enable">文章底部说明卡片</label></th>
                    <td>
                        <select id="mimosa_post_extra_enable" name="mimosa_post_extra_enable">
                            <option value="yes" <?php selected(get_option('mimosa_post_extra_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no"  <?php selected(get_option('mimosa_post_extra_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_post_extra_license">许可协议内容</label></th>
                    <td>
                        <textarea id="mimosa_post_extra_license" name="mimosa_post_extra_license" rows="4" class="large-text code"><?php echo esc_textarea(get_option('mimosa_post_extra_license', '')); ?></textarea>
                        <p class="description">支持 HTML。留空则默认显示「CC BY-SA 4.0」。</p>
                    </td>
                </tr>
            </table>

            <h3>评论设置</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_comment_order">评论排序</label></th>
                    <td>
                        <select id="mimosa_comment_order" name="mimosa_comment_order">
                            <option value="desc" <?php selected(get_option('mimosa_comment_order', 'desc'), 'desc'); ?>>最新的在上</option>
                            <option value="asc" <?php selected(get_option('mimosa_comment_order', 'desc'), 'asc'); ?>>最旧的在上</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_comment_per_page">每页评论数</label></th>
                    <td>
                        <input type="number" id="mimosa_comment_per_page" name="mimosa_comment_per_page" min="5" max="100" value="<?php echo esc_attr(get_option('mimosa_comment_per_page', 15)); ?>">
                        <p class="description">默认 15 条</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_comment_fold_threshold">长评论折叠阈值</label></th>
                    <td>
                        <input type="number" id="mimosa_comment_fold_threshold" name="mimosa_comment_fold_threshold" min="50" max="1000" value="<?php echo esc_attr(get_option('mimosa_comment_fold_threshold', 200)); ?>">
                        <p class="description">超过多少字自动折叠（默认 200）</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_comment_reply_depth">最大回复层级</label></th>
                    <td>
                        <select id="mimosa_comment_reply_depth" name="mimosa_comment_reply_depth">
                            <option value="2" <?php selected(get_option('mimosa_comment_reply_depth', 3), 2); ?>>2 层</option>
                            <option value="3" <?php selected(get_option('mimosa_comment_reply_depth', 3), 3); ?>>3 层</option>
                            <option value="4" <?php selected(get_option('mimosa_comment_reply_depth', 3), 4); ?>>4 层</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_captcha_enable">评论人机验证</label></th>
                    <td>
                        <select id="mimosa_captcha_enable" name="mimosa_captcha_enable">
                            <option value="yes" <?php selected(get_option('mimosa_captcha_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no"  <?php selected(get_option('mimosa_captcha_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">关闭后发表评论不再要求「角色发色」人机验证。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_gravatar_cdn">Gravatar CDN 地址</label></th>
                    <td>
                        <input type="url" id="mimosa_gravatar_cdn" name="mimosa_gravatar_cdn" class="regular-text" value="<?php echo esc_attr(get_option('mimosa_gravatar_cdn', 'https://cravatar.com')); ?>">
                        <p class="description">自定义头像镜像源，末尾不带斜杠。留空则使用官方 secure.gravatar.com。</p>
                    </td>
                </tr>
            </table>

            <h3>说说设置</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_shuoshuo_fold_threshold">长说说折叠阈值</label></th>
                    <td>
                        <input type="number" id="mimosa_shuoshuo_fold_threshold" name="mimosa_shuoshuo_fold_threshold" min="100" max="1000" value="<?php echo esc_attr(get_option('mimosa_shuoshuo_fold_threshold', 250)); ?>">
                        <p class="description">超过多少字自动折叠（默认 250）</p>
                    </td>
                </tr>
            </table>

            <h3>ACGN 设置</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_acgn_cover_sideload_enable">外链封面自动侧载</label></th>
                    <td>
                        <select id="mimosa_acgn_cover_sideload_enable" name="mimosa_acgn_cover_sideload_enable">
                            <option value="yes" <?php selected(get_option('mimosa_acgn_cover_sideload_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no" <?php selected(get_option('mimosa_acgn_cover_sideload_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">启用后，保存 ACGN 条目时会将非本站封面下载到媒体库，并执行来源 URL 与文件内容去重。下载失败不会阻止条目保存。关闭后只保存外链 URL，不进行下载或查重。</p>
                    </td>
                </tr>
            </table>

            <h3>印象集「Toward Our Dream」</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_impressions_slug">页面访问路径</label></th>
                    <td>
                        <input type="text" id="mimosa_impressions_slug" name="mimosa_impressions_slug" class="regular-text" value="<?php echo esc_attr(get_option('mimosa_impressions_slug', 'toward-our-dream')); ?>">
                        <p class="description">印象集访问地址：<code><?php echo esc_html(home_url('/' . get_option('mimosa_impressions_slug', 'toward-our-dream') . '/')); ?></code>。修改后保存会自动刷新重写规则。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_impressions_center_text">中心文字</label></th>
                    <td>
                        <input type="text" id="mimosa_impressions_center_text" name="mimosa_impressions_center_text" class="regular-text" value="<?php echo esc_attr(get_option('mimosa_impressions_center_text', 'Toward our Dream')); ?>">
                        <p class="description">星空正中央显示的标题文字。</p>
                    </td>
                </tr>
            </table>

            <h3>特色标题图（图片替换详情页文字标题）</h3>
            <p>用设计好的艺术字图片替换文章/页面的文字标题。发布文章时在文章 / 页面编辑器里，找到右侧「文档」面板里的 「特色标题图」 区块，上传一张设计好的横版艺术字图片。</p>
            <p>发布（或预览）详情页，原 h1 文字标题会被替换为该图片：水平填满卡片容器、居中对齐。</p>
            <p>如需恢复文字标题，「移除特色标题图」即可。</p></br>

            <h2>站点与 SEO</h2>

            <h3>功能开关</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_reactions_enable">文章/说说表态</label></th>
                    <td>
                        <select id="mimosa_reactions_enable" name="mimosa_reactions_enable">
                            <option value="yes" <?php selected(get_option('mimosa_reactions_enable', 'no'), 'yes'); ?>>启用</option>
                            <option value="no"  <?php selected(get_option('mimosa_reactions_enable', 'no'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">仅在文章和说说详情页的内容卡片底部显示表态入口。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_reactions_quick_emojis">快捷表态 Emoji</label></th>
                    <td>
                        <input type="text" id="mimosa_reactions_quick_emojis" name="mimosa_reactions_quick_emojis" class="large-text" value="<?php echo esc_attr(get_option('mimosa_reactions_quick_emojis', '❤️ 😂 😭 😮 🤔 👍 👀 🎉')); ?>">
                        <p class="description">用空格或逗号分隔。留空则使用默认 8 个快捷表情。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_search_enable">搜索功能</label></th>
                    <td>
                        <select id="mimosa_search_enable" name="mimosa_search_enable">
                            <option value="yes" <?php selected(get_option('mimosa_search_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no"  <?php selected(get_option('mimosa_search_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_rss_include_shuoshuo">RSS 包含说说</label></th>
                    <td>
                        <select id="mimosa_rss_include_shuoshuo" name="mimosa_rss_include_shuoshuo">
                            <option value="yes" <?php selected(get_option('mimosa_rss_include_shuoshuo', 'yes'), 'yes'); ?>>包含</option>
                            <option value="no"  <?php selected(get_option('mimosa_rss_include_shuoshuo', 'yes'), 'no'); ?>>不包含</option>
                        </select>
                    </td>
                </tr>
            </table>

            <h3>站点公告</h3>
            <table class="form-table">
                <tr>
                    <th><label for="brilliance_site_notice">公告内容</label></th>
                    <td>
                        <textarea id="brilliance_site_notice" name="brilliance_site_notice" rows="6" class="large-text code"><?php echo esc_textarea(get_option('brilliance_site_notice', '')); ?></textarea>
                        <p class="description">显示在首页副栏第一个卡片。支持基础 HTML（链接、加粗、换行等）。留空则隐藏公告卡片。</p>
                    </td>
                </tr>
            </table>

            <h3>状态监测（副栏小工具）</h3>
            <table class="form-table">
                <tr>
                    <th><label for="brilliance_status_collect_mode">采集方式</label></th>
                    <td>
                        <select id="brilliance_status_collect_mode" name="brilliance_status_collect_mode">
                            <option value="wpcron" <?php selected(get_option('brilliance_status_collect_mode', 'wpcron'), 'wpcron'); ?>>WP-Cron（默认，普通主机）</option>
                            <option value="cli" <?php selected(get_option('brilliance_status_collect_mode', 'wpcron'), 'cli'); ?>>WP-CLI + 系统 crontab（宝塔 / open_basedir 环境）</option>
                        </select>
                        <p class="description">
                            宝塔面板等会用 <code>open_basedir</code> 限制 PHP 读 <code>/proc</code>，导致 CPU/内存/网络采不到，此时选「WP-CLI」并在系统 crontab 添加：<br>
                            <code>*/5 * * * * cd /www/wwwroot/你的站点 &amp;&amp; /完整路径/wp brilliance status-collect --allow-root &gt;&gt; /dev/null 2&gt;&amp;1</code><br>
                            （<code>wp</code> 路径用 <code>which wp</code> 查到；切到该模式后务必保存设置以停用 WP-Cron）
                        </p>
                    </td>
                </tr>
            </table>

            <h3>字数统计（页脚）</h3>
            <table class="form-table">
                <tr>
                    <th><label for="brilliance_wordcount_enable">页脚字数统计</label></th>
                    <td>
                        <select id="brilliance_wordcount_enable" name="brilliance_wordcount_enable">
                            <option value="yes" <?php selected(get_option('brilliance_wordcount_enable', 'yes'), 'yes'); ?>>启用</option>
                            <option value="no"  <?php selected(get_option('brilliance_wordcount_enable', 'yes'), 'no'); ?>>关闭</option>
                        </select>
                        <p class="description">关闭后页脚不再显示字数统计（也不再执行统计计算）。</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="argon_mimosa_custom_post_types">统计的文章类型</label></th>
                    <td>
                        <input type="text" id="argon_mimosa_custom_post_types" name="argon_mimosa_custom_post_types"
                               class="regular-text"
                               value="<?php echo esc_attr(get_option('argon_mimosa_custom_post_types', 'post,shuoshuo')); ?>">
                        <p class="description">英文逗号分隔，例：<code>post,shuoshuo</code></p>
                    </td>
                </tr>
                <tr>
                    <th><label for="argon_mimosa_book_map">字数对比书籍映射（JSON）</label></th>
                    <td>
                        <textarea id="argon_mimosa_book_map" name="argon_mimosa_book_map" rows="5" class="large-text code"><?php echo esc_textarea(get_option('argon_mimosa_book_map', '')); ?></textarea>
                        <p class="description">例：<code>{"130000":"《老人与海》","200000":"《人类群星闪耀时》"}</code></p>
                        <p class="description">注意：字数统计结果有 12 小时 transient 缓存（<code>mimosa_site_word_count_filtered</code>）；书籍映射表为实时读取，改动立即生效。留空则在页脚提示前往此处添加映射表。</p>
                    </td>
                </tr>
                <tr>
                    <th>刷新缓存</th>
                    <td>
                        <button type="button" class="button" id="brilliance-wordcount-refresh"
                                data-nonce="<?php echo esc_attr(wp_create_nonce('brilliance_clear_wordcount_cache')); ?>">刷新字数统计缓存</button>
                        <span id="brilliance-wordcount-refresh-msg" style="margin-left:8px;color:#646970;"></span>
                        <p class="description">改动文章/说说内容后，可点此立即重算，无需等 12 小时缓存过期。修改「统计的文章类型」并保存时也会自动清空缓存。</p>
                    </td>
                </tr>
            </table>

            <h3>SEO 设置</h3>
            <table class="form-table">
                <tr>
                    <th><label for="brilliance_seo_site_name">网站名称（Open Graph）</label></th>
                    <td>
                        <input type="text" id="brilliance_seo_site_name" name="brilliance_seo_site_name" class="regular-text"
                               value="<?php echo esc_attr(get_option('brilliance_seo_site_name', get_bloginfo('name'))); ?>">
                        <p class="description">用于社交分享，留空则使用站点名称</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="mimosa_seo_description">默认网站描述</label></th>
                    <td>
                        <textarea id="mimosa_seo_description" name="mimosa_seo_description" rows="3" class="large-text"><?php echo esc_textarea(get_option('mimosa_seo_description', get_bloginfo('description'))); ?></textarea>
                        <p class="description">用于首页和没有描述的页面</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="brilliance_seo_default_image">默认分享图片 URL</label></th>
                    <td>
                        <input type="url" id="brilliance_seo_default_image" name="brilliance_seo_default_image" class="regular-text"
                               value="<?php echo esc_attr(get_option('brilliance_seo_default_image', '')); ?>">
                        <p class="description">文章没有特色图时的备用图片，留空则使用网站图标</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="brilliance_seo_twitter_card">Twitter Card 类型</label></th>
                    <td>
                        <select id="brilliance_seo_twitter_card" name="brilliance_seo_twitter_card">
                            <option value="summary" <?php selected(get_option('brilliance_seo_twitter_card', 'summary_large_image'), 'summary'); ?>>Summary</option>
                            <option value="summary_large_image" <?php selected(get_option('brilliance_seo_twitter_card', 'summary_large_image'), 'summary_large_image'); ?>>Summary Large Image</option>
                        </select>
                    </td>
                </tr>
                <tr>
                    <th><label for="brilliance_seo_twitter_site">Twitter 站点账号</label></th>
                    <td>
                        <input type="text" id="brilliance_seo_twitter_site" name="brilliance_seo_twitter_site" class="regular-text"
                               value="<?php echo esc_attr(get_option('brilliance_seo_twitter_site', '')); ?>" placeholder="@username">
                        <p class="description">Twitter/X 用户名，例：@brilliance</p>
                    </td>
                </tr>
                <tr>
                    <th><label for="brilliance_seo_keywords">关键词（Keywords）</label></th>
                    <td>
                        <textarea id="brilliance_seo_keywords" name="brilliance_seo_keywords" rows="3" class="large-text"><?php echo esc_textarea(get_option('brilliance_seo_keywords', '')); ?></textarea>
                        <p class="description">用于 <code>&lt;meta name="keywords"&gt;</code>，多个关键词用英文逗号分隔，留空则不输出</p>
                    </td>
                </tr>
            </table>

            <h3>页脚</h3>
            <table class="form-table">
                <tr>
                    <th><label for="mimosa_footer_custom_html">自定义页脚内容</label></th>
                    <td>
                        <textarea id="mimosa_footer_custom_html" name="mimosa_footer_custom_html" rows="12" class="large-text code"><?php echo esc_textarea(get_option('mimosa_footer_custom_html', '')); ?></textarea>
                        <p class="description">自由填写页脚附加 HTML 内容。支持常用标签及 &lt;style&gt;、&lt;script&gt;。留空则不显示。</p>
                    </td>
                </tr>
            </table>

            <?php submit_button('保存设置'); ?>
        </form>

        <script>
        (function () {
            var form = document.querySelector('.brilliance-settings-form');
            var list = document.querySelector('.brilliance-settings-toc__list');
            if (!form || !list) return;

            var heads = form.querySelectorAll('h2, h3');
            var items = [];
            for (var i = 0; i < heads.length; i++) {
                var h = heads[i];
                if (!h.id) h.id = 'brilliance-sec-' + i;
                var li = document.createElement('li');
                li.className = h.tagName === 'H2' ? 'toc-l1' : 'toc-l2';
                var a = document.createElement('a');
                a.href = '#' + h.id;
                a.textContent = h.textContent;
                li.appendChild(a);
                list.appendChild(li);
                items.push({ head: h, link: a });
            }
            if (!items.length) return;

            function setActive(link) {
                for (var k = 0; k < items.length; k++) {
                    items[k].link.classList.toggle('is-active', items[k].link === link);
                }
            }

            list.addEventListener('click', function (e) {
                var a = e.target && e.target.closest ? e.target.closest('a') : null;
                if (!a) return;
                var target = document.getElementById(a.getAttribute('href').slice(1));
                if (!target) return;
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                if (history.replaceState) history.replaceState(null, '', a.getAttribute('href'));
                setActive(a);
            });

            var ticking = false;
            function onScroll() {
                if (ticking) return;
                ticking = true;
                window.requestAnimationFrame(function () {
                    var current = items[0];
                    for (var k = 0; k < items.length; k++) {
                        if (items[k].head.getBoundingClientRect().top <= 80) current = items[k];
                    }
                    setActive(current.link);
                    ticking = false;
                });
            }
            window.addEventListener('scroll', onScroll, { passive: true });
            onScroll();
        })();
        </script>

        <script>
        (function () {
            var btn = document.getElementById('brilliance-wordcount-refresh');
            var msg = document.getElementById('brilliance-wordcount-refresh-msg');
            if (!btn || !msg || typeof ajaxurl === 'undefined') return;
            btn.addEventListener('click', function () {
                btn.disabled = true;
                msg.style.color = '#646970';
                msg.textContent = '刷新中…';
                var data = new FormData();
                data.append('action', 'brilliance_clear_wordcount_cache');
                data.append('nonce', btn.getAttribute('data-nonce'));
                fetch(ajaxurl, { method: 'POST', credentials: 'same-origin', body: data })
                    .then(function (r) { return r.json(); })
                    .then(function (res) {
                        if (res && res.success) {
                            msg.textContent = res.data || '缓存已刷新';
                            msg.style.color = '#008a20';
                        } else {
                            msg.textContent = (res && res.data) ? res.data : '刷新失败';
                            msg.style.color = '#d63638';
                        }
                    })
                    .catch(function () {
                        msg.textContent = '刷新失败';
                        msg.style.color = '#d63638';
                    })
                    .then(function () { btn.disabled = false; });
            });
        })();
        </script>
    </div>
    <?php
}
