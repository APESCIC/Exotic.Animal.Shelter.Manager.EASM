<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThemeContrastTest extends TestCase
{
    private const WCAG_AA_TEXT = 4.5;

    /**
     * Foreground token, background token.
     *
     * @var list<array{0: string, 1: string}>
     */
    private const TEXT_PAIRS = [
        ['text', 'bg'],
        ['text', 'surface'],
        ['text', 'surface-alt'],
        ['text-muted', 'bg'],
        ['text-muted', 'surface'],
        ['text-muted', 'surface-alt'],
        ['primary', 'bg'],
        ['primary', 'surface'],
        ['primary', 'surface-alt'],
        ['on-primary', 'primary'],
        ['on-primary', 'primary-hover'],
        ['success-text', 'success-bg'],
        ['danger-text', 'danger-bg'],
        ['warning-text', 'warning-bg'],
        ['info-text', 'info-bg'],
        ['info-text', 'surface'],
        ['neutral-text', 'neutral-bg'],
    ];

    /**
     * @return array<string, array{0: string}>
     */
    public static function themes(): array
    {
        return [
            'light' => [':root {'],
            'dark' => [':root[data-theme="dark"] {'],
        ];
    }

    #[DataProvider('themes')]
    public function test_text_colour_pairs_meet_wcag_aa(string $selector): void
    {
        $tokens = $this->tokens($selector);

        foreach (self::TEXT_PAIRS as [$foreground, $background]) {
            $this->assertArrayHasKey($foreground, $tokens, "Missing --color-{$foreground}");
            $this->assertArrayHasKey($background, $tokens, "Missing --color-{$background}");

            $ratio = $this->contrast($tokens[$foreground], $tokens[$background]);

            $this->assertGreaterThanOrEqual(
                self::WCAG_AA_TEXT,
                $ratio,
                sprintf('%s: --color-%s on --color-%s is %.2f:1', $selector, $foreground, $background, $ratio),
            );
        }
    }

    /**
     * @return array<string, string>
     */
    private function tokens(string $selector): array
    {
        $css = file_get_contents(dirname(__DIR__, 2).'/public/css/easm.css');
        $this->assertIsString($css);

        $start = strpos($css, $selector);
        $this->assertNotFalse($start, "Selector {$selector} not found in easm.css");

        $block = substr($css, $start, strpos($css, '}', $start) - $start);

        preg_match_all('/--color-([a-z-]+):\s*(#[0-9a-fA-F]{6})\s*;/', $block, $matches, PREG_SET_ORDER);

        $tokens = [];
        foreach ($matches as $match) {
            $tokens[$match[1]] = $match[2];
        }

        return $tokens;
    }

    private function contrast(string $a, string $b): float
    {
        $la = $this->luminance($a);
        $lb = $this->luminance($b);

        return (max($la, $lb) + 0.05) / (min($la, $lb) + 0.05);
    }

    private function luminance(string $hex): float
    {
        $channels = array_map(
            fn (string $pair): float => hexdec($pair) / 255,
            str_split(ltrim($hex, '#'), 2),
        );

        $linear = array_map(
            fn (float $c): float => $c <= 0.03928 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4,
            $channels,
        );

        return 0.2126 * $linear[0] + 0.7152 * $linear[1] + 0.0722 * $linear[2];
    }
}
