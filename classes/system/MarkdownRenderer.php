<?php

namespace classes\system;

class MarkdownRenderer {

    public static function render(string $markdown): string {
        $renderer = new self();
        return $renderer->renderMarkdown($markdown);
    }

    private function renderMarkdown(string $markdown): string {
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);
        $lines = explode("\n", $markdown);
        $html = [];
        $paragraph = [];
        $blockquote = [];
        $codeLines = [];
        $codeLanguage = '';
        $inCode = false;
        $listMode = null;

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph === []) {
                return;
            }
            $text = trim(implode(' ', $paragraph));
            if ($text !== '') {
                $html[] = '<p>' . $this->renderInlineMarkdown($text) . '</p>';
            }
            $paragraph = [];
        };

        $flushBlockquote = function () use (&$blockquote, &$html): void {
            if ($blockquote === []) {
                return;
            }
            $chunks = [];
            foreach ($blockquote as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                $chunks[] = '<p>' . $this->renderInlineMarkdown($line) . '</p>';
            }
            if ($chunks !== []) {
                $html[] = '<blockquote>' . implode('', $chunks) . '</blockquote>';
            }
            $blockquote = [];
        };

        $closeList = function () use (&$listMode, &$html): void {
            if ($listMode === 'ul') {
                $html[] = '</ul>';
            } elseif ($listMode === 'ol') {
                $html[] = '</ol>';
            }
            $listMode = null;
        };

        foreach ($lines as $line) {
            $trimmed = rtrim($line);

            if ($inCode) {
                if (preg_match('/^```/', $trimmed)) {
                    $html[] = '<pre><code' . ($codeLanguage !== '' ? ' class="language-' . htmlspecialchars($codeLanguage, ENT_QUOTES) . '"' : '') . '>'
                        . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES)
                        . '</code></pre>';
                    $codeLines = [];
                    $codeLanguage = '';
                    $inCode = false;
                    continue;
                }
                $codeLines[] = $line;
                continue;
            }

            if (preg_match('/^```([a-zA-Z0-9_+-]+)?\s*$/', $trimmed, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                $closeList();
                $inCode = true;
                $codeLanguage = trim((string) ($matches[1] ?? ''));
                $codeLines = [];
                continue;
            }

            if (trim($trimmed) === '') {
                $flushParagraph();
                $flushBlockquote();
                $closeList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                $closeList();
                $level = strlen((string) $matches[1]);
                $text = trim((string) $matches[2]);
                $slug = htmlspecialchars($this->slugify($text), ENT_QUOTES);
                $html[] = "<h{$level} id=\"{$slug}\">" . $this->renderInlineMarkdown($text) . "</h{$level}>";
                continue;
            }

            if (preg_match('/^\>\s?(.*)$/', $trimmed, $matches)) {
                $flushParagraph();
                $closeList();
                $blockquote[] = (string) ($matches[1] ?? '');
                continue;
            }

            if (preg_match('/^[-*]\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                if ($listMode !== 'ul') {
                    $closeList();
                    $html[] = '<ul>';
                    $listMode = 'ul';
                }
                $html[] = '<li>' . $this->renderInlineMarkdown((string) $matches[1]) . '</li>';
                continue;
            }

            if (preg_match('/^\d+\.\s+(.+)$/', $trimmed, $matches)) {
                $flushParagraph();
                $flushBlockquote();
                if ($listMode !== 'ol') {
                    $closeList();
                    $html[] = '<ol>';
                    $listMode = 'ol';
                }
                $html[] = '<li>' . $this->renderInlineMarkdown((string) $matches[1]) . '</li>';
                continue;
            }

            if (preg_match('/^---+$/', trim($trimmed))) {
                $flushParagraph();
                $flushBlockquote();
                $closeList();
                $html[] = '<hr>';
                continue;
            }

            $paragraph[] = trim($line);
        }

        $flushParagraph();
        $flushBlockquote();
        $closeList();

        if ($inCode) {
            $html[] = '<pre><code' . ($codeLanguage !== '' ? ' class="language-' . htmlspecialchars($codeLanguage, ENT_QUOTES) . '"' : '') . '>'
                . htmlspecialchars(implode("\n", $codeLines), ENT_QUOTES)
                . '</code></pre>';
        }

        return implode("\n", $html);
    }

    private function renderInlineMarkdown(string $text): string {
        $escaped = htmlspecialchars($text, ENT_QUOTES);
        $escaped = preg_replace('/\[(.+?)\]\((https?:\/\/[^\s)]+)\)/', '<a href="$2" target="_blank" rel="noopener">$1</a>', $escaped);
        $escaped = preg_replace('/\[(.+?)\]\((\/[^\s)]+)\)/', '<a href="$2">$1</a>', $escaped);
        $escaped = preg_replace('/`([^`]+)`/', '<code>$1</code>', $escaped);
        $escaped = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $escaped);
        $escaped = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $escaped);
        return (string) $escaped;
    }

    private function slugify(string $text): string {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9а-яё]+/iu', '-', $text);
        $text = trim((string) $text, '-');
        return $text !== '' ? $text : 'section';
    }
}
