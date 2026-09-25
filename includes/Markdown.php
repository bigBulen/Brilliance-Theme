<?php
/**
 * 简化的 Markdown 解析器（支持评论常用语法）
 * 如果需要完整功能，可替换为 Parsedown 库
 */

class Mimosa_Markdown {
    
    public function parse($text) {
        // 转义 HTML 特殊字符（基础安全）
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
        
        // 图片：![alt](url) 或 ![alt](url "title")
        $text = preg_replace_callback(
            '/!\[([^\]]*)\]\(([^\s\)]+)(?:\s+"([^"]*)")?\)/',
            function($matches) {
                $alt = $matches[1];
                $url = $matches[2];
                $title = isset($matches[3]) ? ' title="' . htmlspecialchars($matches[3]) . '"' : '';
                return '<img src="' . htmlspecialchars($url) . '" alt="' . htmlspecialchars($alt) . '"' . $title . ' class="comment-img js-comment-img" loading="lazy">';
            },
            $text
        );
        
        // 链接：[text](url)
        $text = preg_replace_callback(
            '/\[([^\]]+)\]\(([^\s\)]+)\)/',
            function($matches) {
                return '<a href="' . htmlspecialchars($matches[2]) . '" target="_blank" rel="noopener noreferrer">' . $matches[1] . '</a>';
            },
            $text
        );
        
        // 粗体：**text** 或 __text__
        $text = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = preg_replace('/__(.+?)__/', '<strong>$1</strong>', $text);
        
        // 斜体：*text* 或 _text_
        $text = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $text);
        $text = preg_replace('/_(.+?)_/', '<em>$1</em>', $text);
        
        // 行内代码：`code`
        $text = preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        
        // 删除线：~~text~~
        $text = preg_replace('/~~(.+?)~~/', '<del>$1</del>', $text);
        
        // 代码块：```language\ncode\n```
        $text = preg_replace_callback(
            '/```(\w*)\n(.*?)\n```/s',
            function($matches) {
                $lang = $matches[1] ? ' class="language-' . htmlspecialchars($matches[1]) . '"' : '';
                return '<pre><code' . $lang . '>' . htmlspecialchars($matches[2]) . '</code></pre>';
            },
            $text
        );
        
        // 引用：> text
        $text = preg_replace('/^&gt;\s*(.+)$/m', '<blockquote>$1</blockquote>', $text);
        
        // 无序列表：- item 或 * item
        $text = preg_replace_callback(
            '/(?:^[-*]\s+.+$\n?)+/m',
            function($matches) {
                $items = preg_replace('/^[-*]\s+(.+)$/m', '<li>$1</li>', $matches[0]);
                return '<ul>' . $items . '</ul>';
            },
            $text
        );
        
        // 有序列表：1. item
        $text = preg_replace_callback(
            '/(?:^\d+\.\s+.+$\n?)+/m',
            function($matches) {
                $items = preg_replace('/^\d+\.\s+(.+)$/m', '<li>$1</li>', $matches[0]);
                return '<ol>' . $items . '</ol>';
            },
            $text
        );
        
        // 标题：### text
        $text = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $text);
        $text = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $text);
        $text = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $text);
        $text = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $text);
        $text = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $text);
        $text = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $text);
        
        // 换行：两个空格 + 换行 或 两个换行
        $text = str_replace("  \n", "<br>\n", $text);
        $text = preg_replace('/\n\n+/', "</p>\n<p>", $text);
        
        // 包裹段落
        if (strpos($text, '<p>') === false && strpos($text, '<pre>') === false) {
            $text = '<p>' . $text . '</p>';
        }
        
        return $text;
    }
}
