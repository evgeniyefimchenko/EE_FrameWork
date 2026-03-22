<?php

namespace custom\docs;

class ProjectDocsRepository {

    public function getManifestPath(): string {
        return ENV_CUSTOM_PATH . 'docs' . ENV_DIRSEP . 'manifest.json';
    }

    public function getDocsDirectory(): string {
        return ENV_CUSTOM_PATH . 'docs' . ENV_DIRSEP;
    }

    public function loadManifest(): array {
        $manifestPath = $this->getManifestPath();
        if (!is_readable($manifestPath)) {
            return [];
        }

        $raw = @file_get_contents($manifestPath);
        if ($raw === false || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function resolveDocumentFile(string $fileName): string {
        $fileName = trim($fileName);
        if ($fileName === '') {
            return '';
        }

        $docsDir = realpath($this->getDocsDirectory());
        $resolvedPath = realpath($this->getDocsDirectory() . $fileName);
        if ($docsDir === false || $resolvedPath === false) {
            return '';
        }
        if (!str_starts_with($resolvedPath, $docsDir) || !is_readable($resolvedPath)) {
            return '';
        }

        return $resolvedPath;
    }

    public function readDocumentHtml(string $fileName): string {
        $resolvedPath = $this->resolveDocumentFile($fileName);
        if ($resolvedPath === '') {
            return '';
        }

        $contents = @file_get_contents($resolvedPath);
        if ($contents === false) {
            return '';
        }

        $extension = strtolower((string) pathinfo($resolvedPath, PATHINFO_EXTENSION));
        return match ($extension) {
            'md', 'markdown' => $this->renderMarkdown($contents),
            'txt', 'log', 'conf' => '<pre><code>' . htmlspecialchars($contents, ENT_QUOTES) . '</code></pre>',
            default => $contents,
        };
    }

    public function getDocumentUpdatedAt(string $fileName): string {
        $resolvedPath = $this->resolveDocumentFile($fileName);
        if ($resolvedPath === '') {
            return '';
        }

        $mtime = @filemtime($resolvedPath);
        return $mtime ? date('Y-m-d H:i', $mtime) : '';
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
        $placeholders = [];
        $text = preg_replace_callback('/`([^`]+)`/', function (array $matches) use (&$placeholders): string {
            $key = '__CODE_' . count($placeholders) . '__';
            $placeholders[$key] = '<code>' . htmlspecialchars((string) ($matches[1] ?? ''), ENT_QUOTES) . '</code>';
            return $key;
        }, $text) ?? $text;

        $escaped = htmlspecialchars($text, ENT_QUOTES);
        $escaped = preg_replace_callback('/\[(.+?)\]\((.+?)\)/', function (array $matches): string {
            $label = htmlspecialchars_decode((string) ($matches[1] ?? ''), ENT_QUOTES);
            $url = htmlspecialchars_decode((string) ($matches[2] ?? ''), ENT_QUOTES);
            $safeUrl = $this->sanitizeUrl($url);
            return '<a href="' . htmlspecialchars($safeUrl, ENT_QUOTES) . '">' . htmlspecialchars($label, ENT_QUOTES) . '</a>';
        }, $escaped) ?? $escaped;
        $escaped = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $escaped) ?? $escaped;
        $escaped = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $escaped) ?? $escaped;

        foreach ($placeholders as $key => $replacement) {
            $escaped = str_replace(htmlspecialchars($key, ENT_QUOTES), $replacement, $escaped);
            $escaped = str_replace($key, $replacement, $escaped);
        }

        return $escaped;
    }

    private function sanitizeUrl(string $url): string {
        $url = trim($url);
        if ($url === '') {
            return '#';
        }
        if (preg_match('~^(https?:|mailto:|/|#)~i', $url)) {
            return $url;
        }
        return '#';
    }

    private function slugify(string $value): string {
        $value = strtolower(trim($value));
        $value = preg_replace('~[^a-z0-9а-яё\\-_]+~ui', '-', $value) ?? '';
        $value = trim($value, '-_');
        return $value !== '' ? $value : 'section';
    }
}
