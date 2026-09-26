<?php
/**
 * Mimosa 表情包配置
 *
 * 用户可以在这里自行增删表情包分组。
 * 每个分组结构：
 * array(
 *     'groupname'   => '分组名称',
 *     'list'        => array( 表情项... ),
 *     'description' => '分组说明（可选）',
 * )
 *
 * 每个表情项结构：
 * array(
 *     'type' => 'sticker',
 *     'code' => '唯一代码',
 *     'src'  => '图片地址',
 * )
 */

if (!defined('ABSPATH')) exit;

global $mimosa_emotion_list;

// 主题资源目录，避免下面重复写 get_template_directory_uri()
$mimosa_theme_uri = get_template_directory_uri();

$mimosa_emotion_list = array(

    // ========== 魔女 ==========
    array(
        'groupname'   => '魔女',
        'list'        => array(
            array('type' => 'sticker', 'code' => 'sabbat_1', 'src' => $mimosa_theme_uri . '/stickers/sabbat/1.jpg'),
            array('type' => 'sticker', 'code' => 'sabbat_2', 'src' => $mimosa_theme_uri . '/stickers/sabbat/2.jpg'),
            array('type' => 'sticker', 'code' => 'sabbat_3', 'src' => $mimosa_theme_uri . '/stickers/sabbat/3.jpg'),
            array('type' => 'sticker', 'code' => 'sabbat_4', 'src' => $mimosa_theme_uri . '/stickers/sabbat/4.jpg'),
            array('type' => 'sticker', 'code' => 'sabbat_5', 'src' => $mimosa_theme_uri . '/stickers/sabbat/5.jpg'),
            array('type' => 'sticker', 'code' => 'sabbat_6', 'src' => $mimosa_theme_uri . '/stickers/sabbat/6.jpg'),
        ),
        'description' => 'from『魔女的夜宴』',
    ),

    // ========== 柏田 ==========
    array(
        'groupname'   => '柏田',
        'list'        => array(
            array('type' => 'sticker', 'code' => 'kashioda_1', 'src' => $mimosa_theme_uri . '/stickers/kashioda/1.jpg'),
            array('type' => 'sticker', 'code' => 'kashioda_2', 'src' => $mimosa_theme_uri . '/stickers/kashioda/2.jpg'),
            array('type' => 'sticker', 'code' => 'kashioda_3', 'src' => $mimosa_theme_uri . '/stickers/kashioda/3.jpg'),
            array('type' => 'sticker', 'code' => 'kashioda_4', 'src' => $mimosa_theme_uri . '/stickers/kashioda/4.jpg'),
            array('type' => 'sticker', 'code' => 'kashioda_5', 'src' => $mimosa_theme_uri . '/stickers/kashioda/5.jpg'),
            array('type' => 'sticker', 'code' => 'kashioda_6', 'src' => $mimosa_theme_uri . '/stickers/kashioda/6.jpg'),
            array('type' => 'sticker', 'code' => 'kashioda_7', 'src' => $mimosa_theme_uri . '/stickers/kashioda/7.jpg'),
        ),
        'description' => 'from『不动声色的柏田与喜形于色的太田』',
    ),

    // ========== 小恐龙 ==========
    array(
        'groupname'   => '小恐龙',
        'list'        => array(
            array('type' => 'sticker', 'code' => 'dinosaur_1',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/1.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_2',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/2.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_3',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/3.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_4',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/4.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_5',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/5.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_6',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/6.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_7',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/7.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_8',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/8.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_9',  'src' => $mimosa_theme_uri . '/stickers/dinosaur/9.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_10', 'src' => $mimosa_theme_uri . '/stickers/dinosaur/10.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_11', 'src' => $mimosa_theme_uri . '/stickers/dinosaur/11.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_12', 'src' => $mimosa_theme_uri . '/stickers/dinosaur/12.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_13', 'src' => $mimosa_theme_uri . '/stickers/dinosaur/13.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_14', 'src' => $mimosa_theme_uri . '/stickers/dinosaur/14.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_15', 'src' => $mimosa_theme_uri . '/stickers/dinosaur/15.jpg'),
            array('type' => 'sticker', 'code' => 'dinosaur_16', 'src' => $mimosa_theme_uri . '/stickers/dinosaur/16.jpg'),
        ),
        'description' => '',
    ),

    // ========== 花! ==========
    array(
        'groupname'   => '花!',
        'list'        => array(
            array('type' => 'sticker', 'code' => 'flower_1',  'src' => $mimosa_theme_uri . '/stickers/flower/1.jpg'),
            array('type' => 'sticker', 'code' => 'flower_2',  'src' => $mimosa_theme_uri . '/stickers/flower/2.jpg'),
            array('type' => 'sticker', 'code' => 'flower_3',  'src' => $mimosa_theme_uri . '/stickers/flower/3.jpg'),
            array('type' => 'sticker', 'code' => 'flower_4',  'src' => $mimosa_theme_uri . '/stickers/flower/4.jpg'),
            array('type' => 'sticker', 'code' => 'flower_5',  'src' => $mimosa_theme_uri . '/stickers/flower/5.jpg'),
            array('type' => 'sticker', 'code' => 'flower_6',  'src' => $mimosa_theme_uri . '/stickers/flower/6.jpg'),
            array('type' => 'sticker', 'code' => 'flower_7',  'src' => $mimosa_theme_uri . '/stickers/flower/7.jpg'),
            array('type' => 'sticker', 'code' => 'flower_8',  'src' => $mimosa_theme_uri . '/stickers/flower/8.jpg'),
            array('type' => 'sticker', 'code' => 'flower_9',  'src' => $mimosa_theme_uri . '/stickers/flower/9.jpg'),
            array('type' => 'sticker', 'code' => 'flower_10', 'src' => $mimosa_theme_uri . '/stickers/flower/10.jpg'),
            array('type' => 'sticker', 'code' => 'flower_11', 'src' => $mimosa_theme_uri . '/stickers/flower/11.jpg'),
            array('type' => 'sticker', 'code' => 'flower_12', 'src' => $mimosa_theme_uri . '/stickers/flower/12.jpg'),
            array('type' => 'sticker', 'code' => 'flower_13', 'src' => $mimosa_theme_uri . '/stickers/flower/13.jpg'),
            array('type' => 'sticker', 'code' => 'flower_14', 'src' => $mimosa_theme_uri . '/stickers/flower/14.jpg'),
        ),
        'description' => 'Source: github.com/k4yt3x/flowerhd',
    ),

    // ========== 有希 ==========
    array(
        'groupname'   => '有希',
        'list'        => array(
            array('type' => 'sticker', 'code' => 'yuki_1',  'src' => $mimosa_theme_uri . '/stickers/yuki/1.png'),
            array('type' => 'sticker', 'code' => 'yuki_2',  'src' => $mimosa_theme_uri . '/stickers/yuki/2.png'),
            array('type' => 'sticker', 'code' => 'yuki_3',  'src' => $mimosa_theme_uri . '/stickers/yuki/3.png'),
            array('type' => 'sticker', 'code' => 'yuki_4',  'src' => $mimosa_theme_uri . '/stickers/yuki/4.png'),
            array('type' => 'sticker', 'code' => 'yuki_5',  'src' => $mimosa_theme_uri . '/stickers/yuki/5.png'),
            array('type' => 'sticker', 'code' => 'yuki_6',  'src' => $mimosa_theme_uri . '/stickers/yuki/6.png'),
            array('type' => 'sticker', 'code' => 'yuki_7',  'src' => $mimosa_theme_uri . '/stickers/yuki/7.png'),
            array('type' => 'sticker', 'code' => 'yuki_8',  'src' => $mimosa_theme_uri . '/stickers/yuki/8.png'),
            array('type' => 'sticker', 'code' => 'yuki_9',  'src' => $mimosa_theme_uri . '/stickers/yuki/9.png'),
            array('type' => 'sticker', 'code' => 'yuki_10', 'src' => $mimosa_theme_uri . '/stickers/yuki/10.png'),
            array('type' => 'sticker', 'code' => 'yuki_11', 'src' => $mimosa_theme_uri . '/stickers/yuki/11.png'),
            array('type' => 'sticker', 'code' => 'yuki_12', 'src' => $mimosa_theme_uri . '/stickers/yuki/12.png'),
            array('type' => 'sticker', 'code' => 'yuki_13', 'src' => $mimosa_theme_uri . '/stickers/yuki/13.png'),
            array('type' => 'sticker', 'code' => 'yuki_14', 'src' => $mimosa_theme_uri . '/stickers/yuki/14.png'),
            array('type' => 'sticker', 'code' => 'yuki_15', 'src' => $mimosa_theme_uri . '/stickers/yuki/15.png'),
            array('type' => 'sticker', 'code' => 'yuki_16', 'src' => $mimosa_theme_uri . '/stickers/yuki/16.png'),
            array('type' => 'sticker', 'code' => 'yuki_17', 'src' => $mimosa_theme_uri . '/stickers/yuki/17.png'),
            array('type' => 'sticker', 'code' => 'yuki_18', 'src' => $mimosa_theme_uri . '/stickers/yuki/18.png'),
            array('type' => 'sticker', 'code' => 'yuki_19', 'src' => $mimosa_theme_uri . '/stickers/yuki/19.png'),
            array('type' => 'sticker', 'code' => 'yuki_20', 'src' => $mimosa_theme_uri . '/stickers/yuki/20.png'),
            array('type' => 'sticker', 'code' => 'yuki_21', 'src' => $mimosa_theme_uri . '/stickers/yuki/21.png'),
            array('type' => 'sticker', 'code' => 'yuki_22', 'src' => $mimosa_theme_uri . '/stickers/yuki/22.png'),
            array('type' => 'sticker', 'code' => 'yuki_23', 'src' => $mimosa_theme_uri . '/stickers/yuki/23.png'),
            array('type' => 'sticker', 'code' => 'yuki_24', 'src' => $mimosa_theme_uri . '/stickers/yuki/24.png'),
            array('type' => 'sticker', 'code' => 'yuki_25', 'src' => $mimosa_theme_uri . '/stickers/yuki/25.png'),
            array('type' => 'sticker', 'code' => 'yuki_26', 'src' => $mimosa_theme_uri . '/stickers/yuki/26.png'),
            array('type' => 'sticker', 'code' => 'yuki_27', 'src' => $mimosa_theme_uri . '/stickers/yuki/27.png'),
            array('type' => 'sticker', 'code' => 'yuki_28', 'src' => $mimosa_theme_uri . '/stickers/yuki/28.png'),
        ),
        'description' => 'from『邻座的艾莉同学』',
    ),

);