<?php
/**
 * 友链管理后台页面
 */

if (!defined('ABSPATH')) exit;

function mimosa_friendlinks_admin_menu() {
    // 独立顶级菜单，不再挂在「Brilliance 设置」下
    add_menu_page(
        '友链管理',
        '友链管理',
        'manage_options',
        'mimosa-friendlinks',
        'mimosa_friendlinks_admin_page',
        'dashicons-admin-links',
        61
    );
}
add_action('admin_menu', 'mimosa_friendlinks_admin_menu');

function mimosa_friendlinks_handle_actions() {
    if (!current_user_can('manage_options')) {
        return;
    }

    $action = $_POST['mimosa_friendlink_action'] ?? $_GET['action'] ?? '';
    
    if ($action === 'add' && check_admin_referer('mimosa_friendlink_add')) {
        $name = sanitize_text_field($_POST['link_name'] ?? '');
        $url = esc_url_raw($_POST['link_url'] ?? '');
        $description = sanitize_text_field($_POST['link_description'] ?? '');
        $image = esc_url_raw($_POST['link_image'] ?? '');
        
        if (empty($name) || empty($url)) {
            return array('error' => '名称和链接地址不能为空');
        }
        
        $link_id = wp_insert_link(array(
            'link_name' => $name,
            'link_url' => $url,
            'link_description' => $description,
            'link_image' => $image,
            'link_visible' => 'Y',
        ));
        
        if (is_wp_error($link_id)) {
            return array('error' => '添加失败：' . $link_id->get_error_message());
        }
        
        return array('success' => '友链已添加');
    }
    
    if ($action === 'edit' && check_admin_referer('mimosa_friendlink_edit')) {
        $link_id = absint($_POST['link_id'] ?? 0);
        $name = sanitize_text_field($_POST['link_name'] ?? '');
        $url = esc_url_raw($_POST['link_url'] ?? '');
        $description = sanitize_text_field($_POST['link_description'] ?? '');
        $image = esc_url_raw($_POST['link_image'] ?? '');
        
        if (!$link_id || empty($name) || empty($url)) {
            return array('error' => '名称和链接地址不能为空');
        }
        
        $result = wp_update_link(array(
            'link_id' => $link_id,
            'link_name' => $name,
            'link_url' => $url,
            'link_description' => $description,
            'link_image' => $image,
        ));
        
        if (is_wp_error($result)) {
            return array('error' => '更新失败：' . $result->get_error_message());
        }
        
        return array('success' => '友链已更新');
    }
    
    if ($action === 'delete' && isset($_GET['link_id']) && check_admin_referer('mimosa_friendlink_delete_' . absint($_GET['link_id']))) {
        $link_id = absint($_GET['link_id']);
        wp_delete_link($link_id);
        return array('success' => '友链已删除');
    }
    
    return null;
}

function mimosa_friendlinks_admin_page() {
    if (!current_user_can('manage_options')) {
        wp_die('无权限');
    }
    
    $message = mimosa_friendlinks_handle_actions();
    $edit_link = null;
    
    if (isset($_GET['edit'])) {
        $edit_id = absint($_GET['edit']);
        $edit_link = get_bookmark($edit_id);
    }
    
    $friendlinks = get_bookmarks(array(
        'orderby' => 'name',
        'order' => 'ASC',
    ));
    ?>
    <div class="wrap">
        <h1>Brilliance友链管理</h1>
        
        <?php if ($message): ?>
            <?php if (isset($message['success'])): ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html($message['success']); ?></p></div>
            <?php endif; ?>
            <?php if (isset($message['error'])): ?>
            <div class="notice notice-error is-dismissible"><p><?php echo esc_html($message['error']); ?></p></div>
            <?php endif; ?>
        <?php endif; ?>
        
        <div style="display: grid; grid-template-columns: 1fr 400px; gap: 2rem; margin-top: 1.5rem;">
            <!-- 友链列表 -->
            <div class="mimosa-friendlinks-list">
                <h2>当前友链</h2>
                <?php if (empty($friendlinks)): ?>
                <p>暂无友链</p>
                <?php else: ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th style="width: 60px;">头像</th>
                            <th>名称</th>
                            <th>链接</th>
                            <th>描述</th>
                            <th style="width: 120px;">操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($friendlinks as $link): ?>
                        <tr>
                            <td>
                                <?php if ($link->link_image): ?>
                                <img src="<?php echo esc_url($link->link_image); ?>" alt="" style="width: 48px; height: 48px; object-fit: cover; border-radius: 4px;">
                                <?php else: ?>
                                <div style="width: 48px; height: 48px; background: #ddd; border-radius: 4px; display: flex; align-items: center; justify-content: center; color: #666; font-weight: bold;">
                                    <?php echo esc_html(mb_substr($link->link_name, 0, 1)); ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><strong><?php echo esc_html($link->link_name); ?></strong></td>
                            <td><a href="<?php echo esc_url($link->link_url); ?>" target="_blank"><?php echo esc_html($link->link_url); ?></a></td>
                            <td><?php echo esc_html($link->link_description); ?></td>
                            <td>
                                <a href="<?php echo esc_url(add_query_arg('edit', $link->link_id)); ?>" class="button button-small">编辑</a>
                                <a href="<?php echo esc_url(wp_nonce_url(add_query_arg(array('action' => 'delete', 'link_id' => $link->link_id)), 'mimosa_friendlink_delete_' . $link->link_id)); ?>" 
                                   class="button button-small" 
                                   onclick="return confirm('确定要删除这个友链吗？');">删除</a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
            
            <!-- 添加/编辑表单 -->
            <div class="mimosa-friendlink-form">
                <h2><?php echo $edit_link ? '编辑友链' : '添加友链'; ?></h2>
                <form method="post" action="">
                    <?php if ($edit_link): ?>
                    <?php wp_nonce_field('mimosa_friendlink_edit'); ?>
                    <input type="hidden" name="link_id" value="<?php echo esc_attr($edit_link->link_id); ?>">
                    <input type="hidden" name="mimosa_friendlink_action" value="edit">
                    <?php else: ?>
                    <?php wp_nonce_field('mimosa_friendlink_add'); ?>
                    <input type="hidden" name="mimosa_friendlink_action" value="add">
                    <?php endif; ?>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="link_name">名称 *</label></th>
                            <td><input type="text" id="link_name" name="link_name" class="regular-text" required
                                       value="<?php echo $edit_link ? esc_attr($edit_link->link_name) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="link_url">链接地址 *</label></th>
                            <td><input type="url" id="link_url" name="link_url" class="regular-text" required
                                       value="<?php echo $edit_link ? esc_url($edit_link->link_url) : ''; ?>"></td>
                        </tr>
                        <tr>
                            <th><label for="link_description">描述</label></th>
                            <td><textarea id="link_description" name="link_description" rows="3" class="regular-text"><?php echo $edit_link ? esc_textarea($edit_link->link_description) : ''; ?></textarea></td>
                        </tr>
                        <tr>
                            <th><label for="link_image">头像 URL</label></th>
                            <td><input type="url" id="link_image" name="link_image" class="regular-text"
                                       value="<?php echo $edit_link ? esc_url($edit_link->link_image) : ''; ?>"></td>
                        </tr>
                    </table>
                    
                    <p class="submit">
                        <button type="submit" class="button button-primary"><?php echo $edit_link ? '更新友链' : '添加友链'; ?></button>
                        <?php if ($edit_link): ?>
                        <a href="<?php echo esc_url(remove_query_arg('edit')); ?>" class="button">取消编辑</a>
                        <?php endif; ?>
                    </p>
                </form>
            </div>
        </div>
    </div>
    <?php
}
