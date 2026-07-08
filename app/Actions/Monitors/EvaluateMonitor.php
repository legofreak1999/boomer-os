<?php

namespace App\Actions\Monitors;

use App\Models\Monitor;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Symfony\Component\CssSelector\CssSelectorConverter;
use Symfony\Component\CssSelector\Exception\SyntaxErrorException;

class EvaluateMonitor
{
    public function __invoke(Monitor $monitor, Response $response): bool
    {
        $config = $monitor->check_config ?? [];

        return match ($monitor->check_type) {
            Monitor::CHECK_TEXT_CONTAINS => $this->textContains($response->body(), $config),
            Monitor::CHECK_CSS_SELECTOR => $this->cssSelector($response->body(), $config),
            Monitor::CHECK_REGEX => $this->regex($response->body(), $config),
            Monitor::CHECK_HTTP_STATUS => $response->status() === (int) ($config['expected_status'] ?? 0),
            default => throw new \InvalidArgumentException("Unknown check_type: {$monitor->check_type}"),
        };
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function textContains(string $body, array $config): bool
    {
        $needle = (string) ($config['needle'] ?? '');
        if ($needle === '') {
            return false;
        }

        return ($config['case_sensitive'] ?? false)
            ? str_contains($body, $needle)
            : stripos($body, $needle) !== false;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function cssSelector(string $body, array $config): bool
    {
        $selector = (string) ($config['selector'] ?? '');
        if ($selector === '') {
            return false;
        }

        $dom = new DOMDocument;
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML($body, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();

        if (! $loaded) {
            return false;
        }

        try {
            $xpath = (new CssSelectorConverter)->toXPath($selector);
        } catch (SyntaxErrorException) {
            throw new \InvalidArgumentException("Invalid CSS selector: {$selector}");
        }

        $nodes = (new DOMXPath($dom))->query($xpath);
        if ($nodes === false || $nodes->length === 0) {
            return false;
        }

        $expectedText = $config['expected_text'] ?? null;
        if ($expectedText === null || $expectedText === '') {
            return true;
        }

        $actual = trim($nodes->item(0)?->textContent ?? '');

        return mb_strtolower($actual) === mb_strtolower((string) $expectedText);
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function regex(string $body, array $config): bool
    {
        $pattern = (string) ($config['pattern'] ?? '');
        if ($pattern === '') {
            return false;
        }

        $result = @preg_match($pattern, $body);
        if ($result === false) {
            throw new \InvalidArgumentException('Invalid regex pattern: '.(preg_last_error_msg() ?: 'unknown error'));
        }

        return $result === 1;
    }
}
