<?php

declare(strict_types=1);

namespace Verstka\Sdk\Session;

use Verstka\Sdk\Exception\VerstkaApiError;

/** Optional legacy input; validation performs no network requests. */
final class LegacyArticle
{
    public static function validate(array $article): array
    {
        if (array_diff(array_keys($article), ['desktop_html', 'mobile_html', 'mobile_breakpoint', 'images_hostname', 'fontscss_url'])) {
            throw new VerstkaApiError('Unknown legacy_article field');
        }
        $count = 0;
        foreach (['desktop_html', 'mobile_html'] as $field) {
            $html = $article[$field] ?? null;
            if ($html === null) {
                continue;
            }
            if (!is_string($html) || trim($html) === '' || strlen($html) > 5 * 1024 * 1024 || !preg_match('//u', $html)) {
                throw new VerstkaApiError('Invalid legacy HTML; maximum 5 MiB UTF-8 per variant');
            }
            ++$count;
        }
        $breakpoint = $article['mobile_breakpoint'] ?? null;
        if ($count === 0 || ($count === 2 && $breakpoint === null)) {
            throw new VerstkaApiError('Legacy HTML is required; a pair also requires mobile_breakpoint');
        }
        if ($breakpoint !== null && ((!is_int($breakpoint) && !is_float($breakpoint)) || !is_finite((float) $breakpoint) || $breakpoint <= 0)) {
            throw new VerstkaApiError('Invalid legacy mobile_breakpoint');
        }
        foreach (['images_hostname', 'fontscss_url'] as $field) {
            $value = $article[$field] ?? null;
            if ($value === null) {
                continue;
            }
            if (!is_string($value) || $value === '' || preg_match('/\s/u', $value)) {
                throw new VerstkaApiError('Invalid legacy URL');
            }
            if ($field === 'images_hostname' && strpos($value, '://') === false) {
                $value = 'https://' . preg_replace('~^//~', '', $value);
            }
            $url = parse_url($value);
            if ($url === false || !in_array($url['scheme'] ?? '', ['http', 'https'], true) || empty($url['host']) || isset($url['user']) || isset($url['pass'])) {
                throw new VerstkaApiError('Invalid legacy URL');
            }
            if ($field === 'images_hostname' && (!in_array($url['path'] ?? '', ['', '/'], true) || isset($url['query']) || isset($url['fragment']))) {
                throw new VerstkaApiError('images_hostname must be an origin');
            }
        }
        return array_filter($article, static fn ($value) => $value !== null);
    }

    public static function requireAcknowledgement(array $data): void
    {
        $id = $data['legacy_attempt_id'] ?? null;
        if (($data['legacy_import_accepted'] ?? null) !== true || !is_string($id) || !preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/iD', $id)) {
            throw new VerstkaApiError('Backend did not acknowledge legacy import; no fallback request was sent');
        }
    }
}
