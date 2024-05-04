<?php

namespace Durlecode\EJSParser;

use DOMDocument;
use DOMText;
use Masterminds\HTML5;

class Parser
{
    /**
     * @var Config
     */
    protected $config;
    
    /**
     * @var StdClass
     */
    protected $data;

    /**
     * @var DOMDocument
     */
    protected $dom;

    /**
     * @var HTML5
     */
    protected $html5;

    /**
     * @var string
     */
    protected $prefix;

    public function __construct(string $data)
    {
        $this->config = new Config();

        $this->prefix = $this->config->getPrefix();

        $this->data = json_decode($data);

        $this->dom = new DOMDocument(1.0, 'UTF-8');

        $this->html5 = new HTML5([
            'target_document' => $this->dom,
            'disable_html_ns' => true
        ]);
    }

    static function parse($data)
    {
        return new static($data);
    }

    /**
     * @return string
     */
    public function getPrefix(): string
    {
        return !empty($this->prefix) ? $this->prefix : $this->config->getPrefix();
    }

    /**
     * @param string $prefix
     */
    public function setPrefix(string $prefix): void
    {
        $this->prefix = $prefix;
    }

    public function getTime()
    {
        return isset($this->data->time) ? $this->data->time : null;
    }

    public function getVersion()
    {
        return isset($this->data->version) ? $this->data->version : null;
    }

    public function getBlocks()
    {
        return isset($this->data->blocks) ? $this->data->blocks : null;
    }

    public function toHtml()
    {
        $this->init();

        return html_entity_decode($this->dom->saveHTML());
    }

    /**
     * @throws ParserException
     */
    protected function init()
    {
        if (!$this->hasBlocks()) throw new ParserException('No blocks to parse !');
        foreach ($this->data->blocks as $block) {
            $method = 'parse'.ucfirst($block->type);
            if (method_exists($this, $method)) {
                $this->{$method}($block);
            } else {
                throw new ParserException('Unknow block '.$block->type.' !');
            }
        }
    }

    protected function hasBlocks()
    {
        return count($this->data->blocks) !== 0;
    }

    protected function addClass($type, $alignment = false, $style = false)
    {
        $class[] = $this->prefix.'-'.$type;
        
        if ($alignment) {
            $class[] = $this->prefix.'_'.$alignment;
        }

        if ($style) {
            $styles = explode(' ', $style);
            foreach ($styles as $v) {
                $class[] = $this->prefix.'_'.$v;
            }            
        }
        
        return implode(' ', $class);
    }

    private function parseHeader($block)
    {
        $text = new DOMText($block->data->text);

        $alignment = isset($block->data->alignment) ? $block->data->alignment : false;

        $class = $this->addClass($block->type, $alignment);

        $header = $this->dom->createElement('h' . $block->data->level);

        $header->setAttribute('class', $class);

        $header->appendChild($text);

        $this->dom->appendChild($header);
    }

    private function parseDelimiter($block)
    {
        $node = $this->dom->createElement('hr');

        $node->setAttribute('class', $this->addClass($block->type));

        $this->dom->appendChild($node);
    }

    private function parseCode($block)
    {
        $pre = $this->dom->createElement('pre');

        $pre->setAttribute('class', $this->addClass($block->type));

        $code = $this->dom->createElement('code');

        $content = new DOMText($block->data->code);

        $code->appendChild($content);

        $pre->appendChild($code);

        $this->dom->appendChild($pre);
    }

    private function parseParagraph($block)
    {
        $alignment = isset($block->data->alignment) ? $block->data->alignment : false;

        $class = $this->addClass($block->type, $alignment);

        $node = $this->dom->createElement('p');

        $node->setAttribute('class', $class);

        $node->appendChild($this->html5->loadHTMLFragment($block->data->text));

        $this->dom->appendChild($node);
    }

    private function parseEmbed($block)
    {
        $figure = $this->dom->createElement('figure');
        
        $class = $this->addClass($block->type, false, $block->data->service);

        $figure->setAttribute('class', $class);

        switch ($block->data->service) {
            case 'youtube':

                $attrs = [
                    'width' => $block->data->width,
                    'height' => $block->data->height,
                    'src' => $block->data->embed,
                    'allow' => 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share',
                    'allowfullscreen' => true
                ];

                break;
            // case 'codepen' || 'gfycat':
            default:

                $attrs = [
                    'height' => $block->data->height,
                    'src' => $block->data->embed,
                ];
        }

        $figure->appendChild($this->createIframe($attrs));

        if ($block->data->caption) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function createIframe(array $attrs)
    {
        $iframe = $this->dom->createElement('iframe');

        foreach ($attrs as $key => $attr) $iframe->setAttribute($key, $attr);

        return $iframe;
    }

    private function parseRaw($block)
    {
        $class = $this->addClass($block->type);

        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', $class);

        $wrapper->appendChild($this->html5->loadHTMLFragment($block->data->html));

        $this->dom->appendChild($wrapper);
    }

    private function parseList($block)
    {
        $class = $this->addClass($block->type, false, $block->data->style);

        $list = null;

        switch ($block->data->style) {
            case 'ordered':
                $list = $this->dom->createElement('ol');
                break;
            default:
                $list = $this->dom->createElement('ul');
                break;
        }

        foreach ($block->data->items as $item) {
            $li = $this->dom->createElement('li');
            $li->appendChild($this->html5->loadHTMLFragment($item));
            $list->appendChild($li);
        }

        $list->setAttribute('class', $class);

        $this->dom->appendChild($list);
    }

    private function parseWarning($block)
    {
        $title = new DOMText($block->data->title);
        $message = new DOMText($block->data->message);

        $class = $this->addClass($block->type);

        $wrapper = $this->dom->createElement('div');

        $wrapper->setAttribute('class', $class);

        $textWrapper = $this->dom->createElement('div');
        $titleWrapper = $this->dom->createElement('h4');

        $titleWrapper->appendChild($title);
        $messageWrapper = $this->dom->createElement('p');

        $messageWrapper->appendChild($message);

        $textWrapper->appendChild($titleWrapper);
        $textWrapper->appendChild($messageWrapper);

        $icon = $this->dom->createElement('i');

        $wrapper->appendChild($icon);
        $wrapper->appendChild($textWrapper);

        $this->dom->appendChild($wrapper);
    }

    private function parseAlert($block)
    {
        $alignment = isset($block->data->align) ? $block->data->align : false;

        $style = isset($block->data->type) ? $block->data->type : false;

        $class = $this->addClass($block->type, $alignment, $style);

        $node = $this->dom->createElement('p');

        $node->setAttribute('class', $class);

        $node->appendChild(new DOMText($block->data->message));

        $this->dom->appendChild($node);
    }

    private function parseImage($block)
    {
        $figure = $this->dom->createElement('figure');

        $attrs = [];

        $caption = (!empty($block->data->caption)) ? $block->data->caption : '';

        if ($block->data->withBorder) $attrs[] = "withborder";
        if ($block->data->withBackground) $attrs[] = "withbackground";
        if ($block->data->stretched) $attrs[] = "stretched";

        $style = (count($attrs) > 0) ? implode(' ', $attrs) : false;

        $class = $this->addClass($block->type, false, $style);

        $figure->setAttribute('class', $class);

        $img = $this->dom->createElement('img');

        $img->setAttribute('src', $block->data->file->url);
        $img->setAttribute('alt', $caption);
        
        $figure->appendChild($img);

        if (!empty($caption)) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseStandardImage($block)
    {
        $figure = $this->dom->createElement('figure');

        $attrs = [];

        $caption = (!empty($block->data->caption)) ? $block->data->caption : '';

        if ($block->data->withBorder) $attrs[] = "withborder";
        if ($block->data->withBackground) $attrs[] = "withbackground";
        if ($block->data->stretched) $attrs[] = "stretched";

        $style = (count($attrs) > 0) ? implode(' ', $attrs) : false;

        $class = $this->addClass($block->type, false, $style);

        $figure->setAttribute('class', $class);

        $img = $this->dom->createElement('img');

        $img->setAttribute('src', $block->data->url);
        $img->setAttribute('alt', $caption);
        
        $figure->appendChild($img);

        if (!empty($caption)) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseQuote($block)
    {
        $alignment = isset($block->data->alignment) ? $block->data->alignment : false;

        $class = $this->addClass($block->type, $alignment);

        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', $class);

        $blockquote = $this->dom->createElement('blockquote');

        $blockquote->appendChild($this->html5->loadHTMLFragment($block->data->text));
        $figure->appendChild($blockquote);

        if ($block->data->caption) {
            $figCaption = $this->dom->createElement('figcaption');
            $figCaption->appendChild($this->html5->loadHTMLFragment($block->data->caption));
            $figure->appendChild($figCaption);
        }

        $this->dom->appendChild($figure);
    }

    private function parseTable($block)
    {
        $style = !empty($block->data->withHeadings) ? 'withheadings' : false;

        $class = $this->addClass($block->type, false, $style);

        $table = $this->dom->createElement('table');
        $table->setAttribute('class', $class);

        $dataset = $block->data->content;

        if (!empty($block->data->withHeadings)) {
            $tr_top = $this->dom->createElement('tr');
            $thead = $this->dom->createElement('thead');
            // $tbody = $this->dom->createElement('tbody');
            $thead->appendChild($tr_top);
            $table->appendChild($thead);
            // $table->appendChild($tbody);

            foreach ($block->data->content[0] as $head) {
                $th = $this->dom->createElement('th', $head);
                $tr_top->appendChild($th);
            }

            // $dataset = $block->data->content;
            unset($dataset[0]);
        }

        $tbody = $this->dom->createElement('tbody');
        $table->appendChild($tbody);

        foreach ($dataset as $data) {
            $tr = $this->dom->createElement('tr');
            foreach ($data as $item) {
                $td = $this->dom->createElement('td', $item);
                $tr->appendChild($td);
            }
            $tbody->appendChild($tr);
        }

        $this->dom->appendChild($table);
    }

    private function parseLinkTool($block)
    {
        $figure = $this->dom->createElement('figure');
        $figure->setAttribute('class', $this->addClass($block->type));

        $site_name = !empty($block->data->meta->site_name) ? $block->data->meta->site_name : parse_url($block->data->link, PHP_URL_HOST);

        $link = $this->dom->createElement('a');
        $link->setAttribute('href', $block->data->link);
        $link->setAttribute('target', '_blank');

        $img = $this->dom->createElement('img');
        $img->setAttribute('src', $block->data->meta->image->url);
        $img->setAttribute('alt', '');

        $link->appendChild($img);

        $link_title = $this->dom->createElement('p');
        $link_title->setAttribute('class', "{$this->prefix}_title");
        $link_title->appendChild($this->html5->loadHTMLFragment($block->data->meta->title));
        $link->appendChild($link_title);

        $link_description = $this->dom->createElement('p');
        $link_description->setAttribute('class', "{$this->prefix}_description");
        $link_description->appendChild($this->html5->loadHTMLFragment($block->data->meta->description));
        $link->appendChild($link_description);

        $link_name = $this->dom->createElement('p');
        $link_name->setAttribute('class', "{$this->prefix}_sitename");
        $link_name->appendChild($this->html5->loadHTMLFragment($site_name));
        $link->appendChild($link_name);

        $figure->appendChild($link);

        $this->dom->appendChild($figure);
    }
}
