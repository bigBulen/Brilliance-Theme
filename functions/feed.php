<?php
/**
 * 首页混排流逻辑
 * 文章(post) + 说说(shuoshuo) + ACGN作品(brilliance_acgn_items)
 * ACGN 按 finished_at 时间插入混排；置顶文章优先排最前。
 */

if (!defined('ABSPATH')) exit;

/**
 * 获取首页混排数据
 *
 * 分页规则：只按 post/shuoshuo 计数，ACGN 不参与分页计数。
 * ACGN 折叠规则：每页只展示一张 ACGN 卡，其余折叠在展开条之后。
 *
 * @param int $paged 页码，0 则自动从 query_var 读取
 * @return array {
 *   items: array,   // 当页混排条目
 *   paged: int,
 *   per_page: int,
 *   total: int,     // 仅 post+shuoshuo 总数（用于分页）
 * }
 */
function mimosa_get_home_feed($paged = 0) {
    global $apex_media_list;

    if ($paged <= 0) {
        $paged = max(1, (int) get_query_var('paged'), (int) get_query_var('page'));
    }

    $per_page = max(1, (int) get_option('posts_per_page', 10));

    // ── 1. 查询所有 post + shuoshuo（全量，用于处理置顶+时间排序后再分页）
    $posts_query = new WP_Query(array(
        'post_type'           => array('post', 'shuoshuo'),
        'posts_per_page'      => -1,
        'post_status'         => 'publish',
        'orderby'             => 'date',
        'order'               => 'DESC',
        'ignore_sticky_posts' => false,
        'no_found_rows'       => false,
    ));

    $all_posts = $posts_query->posts;

    // ── 2. 组建带时间戳的 posts 流（置顶文章仅影响排序，不改变显示日期）
    // 只有 post 类型才能置顶，shuoshuo 不参与置顶。
    $sticky_ids = array_filter(
        (array) get_option('sticky_posts', array()),
        function ($id) { 
            return get_post_status($id) === 'publish' && get_post_type($id) === 'post';
        }
    );
    $sticky_rank = 0;

    $posts_stream = array();
    $processed_posts = array();

    foreach ($all_posts as $feed_post) {
        if (!($feed_post instanceof WP_Post)) {
            continue;
        }

        $post_type = get_post_type($feed_post);
        $unique_key = $post_type . ':' . (int) $feed_post->ID;
        if (isset($processed_posts[$unique_key])) {
            continue;
        }
        $processed_posts[$unique_key] = true;

        // 首页的发布日期与排序基准始终使用 wp_posts.post_date，并按站点时区解析。
        $published_timestamp = (int) get_post_timestamp($feed_post, 'date');
        $sort_timestamp = $published_timestamp;

        $is_sticky_post = $post_type === 'post' && in_array($feed_post->ID, $sticky_ids, true);
        if ($is_sticky_post) {
            $sort_timestamp = PHP_INT_MAX - $sticky_rank++;
        }

        $posts_stream[] = array(
            'kind'         => 'post',
            'type'         => $post_type,
            'ts'           => $sort_timestamp,
            'published_ts' => $published_timestamp,
            'is_sticky'    => $is_sticky_post,
            'post'         => $feed_post,
        );
    }

    $post_total = count($posts_stream);

    // 按置顶权重/发布时间降序；同一秒发布时按 ID 降序稳定排序。
    usort($posts_stream, function ($a, $b) {
        if ($a['ts'] === $b['ts']) {
            return (int) $b['post']->ID <=> (int) $a['post']->ID;
        }
        return $b['ts'] <=> $a['ts'];
    });

    // ── 3. 分页截取 post 流
    $start      = ($paged - 1) * $per_page;
    $page_posts = array_slice($posts_stream, $start, $per_page);

    if (empty($page_posts)) {
        return array(
            'items'    => array(),
            'paged'    => $paged,
            'per_page' => $per_page,
            'total'    => $post_total,
        );
    }

    $range_posts = array_values(array_filter($page_posts, function ($item) {
        return empty($item['is_sticky']);
    }));
    if (!$range_posts) {
        $range_posts = $page_posts;
    }
    $published_timestamps = array_column($range_posts, 'published_ts');
    $page_max_ts = max($published_timestamps);
    $page_min_ts = min($published_timestamps);
    $is_first_page = $paged === 1;
    $is_last_page = ($start + count($page_posts)) >= $post_total;

    // ── 4. 取当前页时间范围内的 ACGN 条目
    $media_on_page = array();
    if ($apex_media_list instanceof Apex_Media_List) {
        $all_media = $apex_media_list->media_query(array(
            'show_on_home_feed' => true,
            'order_by'          => 'finished_at',
            'order'             => 'DESC',
            'limit'             => 0,
            'offset'            => 0,
        ));

        foreach ($all_media as $m) {
            $ts = $apex_media_list->resolve_finished_timestamp($m);
            // 首页接收比最新文章更新的媒体，末页接收比最早文章更早的媒体；中间页严格按时间段归属，避免跨页重复。
            $within_upper_bound = $is_first_page || $ts <= $page_max_ts;
            $within_lower_bound = $is_last_page || $ts > $page_min_ts;
            if ($within_upper_bound && $within_lower_bound) {
                $media_on_page[] = array(
                    'kind'  => 'media',
                    'type'  => $m['type'] ?? 'media',
                    'ts'    => $ts,
                    'media' => $m,
                );
            }
        }
    }

    // ── 5. 合并并排序
    $combined = array_merge($page_posts, $media_on_page);
    usort($combined, function ($a, $b) {
        if ($a['ts'] === $b['ts']) {
            // 同时间：media 排在 post 后面
            $a_id = $a['kind'] === 'media' ? (int) ($a['media']['id'] ?? 0) : (int) ($a['post']->ID ?? 0);
            $b_id = $b['kind'] === 'media' ? (int) ($b['media']['id'] ?? 0) : (int) ($b['post']->ID ?? 0);
            return $b_id <=> $a_id;
        }
        return $b['ts'] <=> $a['ts'];
    });

    return array(
        'items'    => $combined,
        'paged'    => $paged,
        'per_page' => $per_page,
        'total'    => $post_total,
    );
}

/**
 * 渲染首页混排，含 ACGN 折叠逻辑
 *
 * 折叠规则：每页 ACGN 卡只展示第一张，其余折叠在展开条后；
 * 点击展开后，其余卡插入到它们原本在流中的位置（DOM 中已预先渲染，默认隐藏）。
 *
 * @param array $feed  mimosa_get_home_feed() 返回值
 */
function mimosa_render_home_feed($feed) {
    global $post;

    if (empty($feed['items'])) {
        echo '<div class="feed-empty"><p>暂无内容。</p></div>';
        return;
    }

    $original_post = $post;
    $items           = $feed['items'];
    $paged           = (int) $feed['paged'];
    $group_id        = 'media-collapse-p' . $paged;
    $media_count     = 0;

    // 先统计本页 ACGN 卡数量，用于展开条文案
    foreach ($items as $item) {
        if ($item['kind'] === 'media') {
            $media_count++;
        }
    }

    $media_shown    = 0;   // 已展示（未折叠）的 ACGN 卡数
    $collapse_rendered = false;

    foreach ($items as $item) {
        if ($item['kind'] === 'media') {
            if ($media_shown === 0) {
                // 第一张 ACGN 卡：正常渲染
                mimosa_render_media_card($item['media']);
                $media_shown++;

                // 如果后面还有更多 ACGN，渲染展开条
                if ($media_count > 1 && !$collapse_rendered) {
                    $remaining = $media_count - 1;
                    ?>
                    <div class="media-collapse-bar js-media-collapse-bar"
                         data-group="<?php echo esc_attr($group_id); ?>">
                        <span class="media-collapse-bar__text">
                            本页还有 <strong><?php echo intval($remaining); ?></strong> 条作品记录
                        </span>
                        <button class="media-collapse-bar__btn js-media-expand-btn"
                                type="button"
                                data-group="<?php echo esc_attr($group_id); ?>">
                            展开
                        </button>
                    </div>
                    <?php
                    $collapse_rendered = true;
                }
            } else {
                // 第 2 张及以后：包裹在折叠容器中，默认隐藏
                ?>
                <div class="media-collapsed-item js-media-collapsed-item"
                     data-group="<?php echo esc_attr($group_id); ?>">
                    <?php mimosa_render_media_card($item['media']); ?>
                </div>
                <?php
                $media_shown++;
            }

        } else {
            $feed_post = $item['post'];
            if (!($feed_post instanceof WP_Post)) {
                continue;
            }

            // setup_postdata() 本身不会替换全局 $post；模板标签依赖该全局值。
            $post = $feed_post;
            setup_postdata($post);

            if ($item['type'] === 'shuoshuo') {
                get_template_part('template-parts/content-shuoshuo-preview');
            } else {
                get_template_part('template-parts/content-preview');
            }
        }
    }

    $post = $original_post;
    if ($post instanceof WP_Post) {
        setup_postdata($post);
    } else {
        wp_reset_postdata();
    }
}

/**
 * 渲染单个 ACGN 媒体卡
 */
function mimosa_render_media_card($media_item) {
    if (empty($media_item)) return;
    set_query_var('mimosa_media_item', $media_item);
    get_template_part('template-parts/content-media-preview');
}
