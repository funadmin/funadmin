<?php

declare(strict_types=1);

namespace app\document\service;

/** 发布文档 JSON 到可分享 HTML；所有文本、URL 和属性均经过边界检查。 */
final class PublicContentRenderer
{
    private array $outline = [];

    public function render(array $content): array
    {
        $this->outline = [];
        $html = $this->node(ContentValidator::clean($content));
        return ['html'=>$html, 'outline'=>$this->outline];
    }

    private function node(array $node): string
    {
        $type = $node['type'];
        $attrs = $node['attrs'] ?? [];
        if ($type === 'text') {
            $text = ReaderPage::escape($node['text']);
            foreach ($node['marks'] ?? [] as $mark) {
                $text = match ($mark['type']) {
                    'bold'=>'<strong>'.$text.'</strong>', 'italic'=>'<em>'.$text.'</em>', 'strike'=>'<s>'.$text.'</s>',
                    'underline'=>'<u>'.$text.'</u>', 'code'=>'<code>'.$text.'</code>',
                    'link'=>$this->link($text, $mark['attrs']['href'] ?? ''), default=>$text,
                };
            }
            return $text;
        }
        if ($type === 'heading') {
            $level = max(1, min(6, (int)($attrs['level'] ?? 2)));
            $id = 'section-'.(count($this->outline)+1);
            $this->outline[] = ['id'=>$id,'level'=>$level,'title'=>ContentValidator::plainText($node)];
            return '<h'.$level.' id="'.$id.'">'.$this->children($node).'</h'.$level.'>';
        }
        if ($type === 'image') {
            $src = ContentValidator::safeUrl($attrs['src'] ?? null);
            return $src === null ? '' : '<img loading="lazy" src="'.ReaderPage::escape($src).'" alt="'.ReaderPage::escape($attrs['alt'] ?? '').'">';
        }
        if ($type === 'mermaid') return '<pre class="diagram-source"><code>'.ReaderPage::escape($attrs['source'] ?? '').'</code></pre>';
        $body = $this->children($node);
        return match ($type) {
            'doc'=>$body, 'paragraph'=>'<p>'.$body.'</p>', 'bulletList'=>'<ul>'.$body.'</ul>',
            'orderedList'=>'<ol>'.$body.'</ol>', 'listItem'=>'<li>'.$body.'</li>',
            'taskList'=>'<ul class="task-list">'.$body.'</ul>',
            'taskItem'=>'<li><span aria-hidden="true">'.(!empty($attrs['checked'])?'☑':'☐').'</span> '.$body.'</li>',
            'blockquote'=>'<blockquote>'.$body.'</blockquote>', 'hardBreak'=>'<br>', 'horizontalRule'=>'<hr>',
            'codeBlock'=>$this->codeBlock($body, $attrs),
            'table'=>'<div class="table-scroll"><table>'.$body.'</table></div>', 'tableRow'=>'<tr>'.$body.'</tr>',
            'tableCell','tableHeader'=>$this->cell($type === 'tableHeader'?'th':'td', $body, $attrs),
            'callout'=>'<aside class="callout">'.$body.'</aside>', default=>$body,
        };
    }

    private function codeBlock(string $body, array $attrs): string
    {
        $language = ReaderPage::escape(mb_substr((string)($attrs['language'] ?? 'code'), 0, 40));
        return '<div class="code-block" data-code-block><div class="code-toolbar"><span>'.$language.'</span>'
            .'<button type="button" data-copy-code aria-label="复制代码">'.ReaderPage::icon('copy').'<span data-copy-label>复制</span></button></div>'
            .'<pre><code>'.$body.'</code></pre></div>';
    }

    private function children(array $node): string
    {
        return implode('', array_map($this->node(...), $node['content'] ?? []));
    }

    private function link(string $text, mixed $href): string
    {
        $url = ContentValidator::safeUrl($href);
        return $url === null ? $text : '<a href="'.ReaderPage::escape($url).'" rel="noopener noreferrer">'.$text.'</a>';
    }

    private function cell(string $tag, string $body, array $attrs): string
    {
        $attributes = '';
        foreach (['colspan','rowspan'] as $key) {
            $span = max(1, min(20, (int)($attrs[$key] ?? 1)));
            if ($span > 1) $attributes .= ' '.$key.'="'.$span.'"';
        }
        return '<'.$tag.$attributes.'>'.$body.'</'.$tag.'>';
    }
}
