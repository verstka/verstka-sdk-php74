# verstka/sdk-php74

PHP 7.4 SDK for [Verstka](https://verstka.org) API v2. Open the editor, handle signatures, callback processing, ZIP download, and media/font persistence through a storage adapter.

For PHP 8.2+ use [`verstka/sdk`](https://github.com/verstka/verstka-sdk-php) (includes Laravel and Symfony integrations).

## Installation

```bash
composer require verstka/sdk-php74
```

Requirements:

| Requirement | Value |
| --- | --- |
| PHP | 7.4 – 8.1 |
| Extensions | `ext-json`, `ext-hash`, `ext-zip` |
| HTTP client | Guzzle 7 |

## Configuration

Usually `apiKey`, `apiSecret`, and a public `callbackUrl` are enough:

```php
use Verstka\Sdk\Config\VerstkaConfig;

$config = new VerstkaConfig(
    'verstka-api-key',                              // apiKey
    'verstka-api-secret',                           // apiSecret
    'https://site.example/verstka/callback'         // callbackUrl
);
```

`callbackUrl` must be reachable by Verstka from the internet. Verstka calls it when the user saves in the editor.

Optional settings:

```php
$config = new VerstkaConfig(
    'verstka-api-key',                              // apiKey
    'verstka-api-secret',                           // apiSecret
    'https://site.example/verstka/callback',        // callbackUrl
    'https://api.r2.verstka.org/integration',       // apiUrl
    200 * 1024 * 1024,                              // maxContentSize
    60.0,                                           // requestTimeout
    120.0,                                          // downloadTimeout
    false                                           // debug
);
```

| Parameter | Default | Description |
| --- | --- | --- |
| `apiUrl` | `https://api.r2.verstka.org/integration` | Verstka API base URL |
| `maxContentSize` | `200 * 1024 * 1024` (200 MiB) | Max ZIP download size in bytes |
| `requestTimeout` | `60.0` | HTTP timeout for `session/open` (seconds) |
| `downloadTimeout` | `120.0` | HTTP timeout for content ZIP download (seconds) |
| `debug` | `false` | Include `debug_info` in callback responses |

## Main methods

| Method | Purpose |
| --- | --- |
| `VerstkaClient::getEditorUrl(...)` | Opens a session via `POST /session/open` and returns the editor URL. |
| `VerstkaClient::processMaterialCallback(...)` | Handles `article_saved`: signature, ZIP, media, `onFinalize`. |
| `VerstkaClient::processFontsCallback(...)` | Handles `site_fonts_updated`: signature, fonts ZIP, font files, manifests. |
| `StorageAdapter::saveMedia(...)` | Saves a file from `vms_media/*` and returns a public URL. |
| `StorageAdapter::saveFontFile(...)` | Saves a font file and returns a public URL. |
| `StorageAdapter::saveFontsManifest(...)` | Saves `vms_fonts.css` or `vms_fonts.json` and returns a URL. |
| `LocalStorageAdapter` | Filesystem reference storage adapter. |
| `CallbackDispatcher::dispatch(...)` | Routes a single callback endpoint by `event` field. |
| `CallbackDispatcher::mapException(...)` | Maps SDK exceptions to HTTP error responses. |
| `SignatureService::signMaterial(...)` | Builds HMAC for `material_id:url`. |
| `SignatureService::verifySignature(...)` | Verifies HMAC. |
| `UrlBuilder::buildAuthorizedContentUrl(...)` | Adds `api_key` and `material_id` to `content_url`. |

## 1. Open editor from admin

In the admin UI, use a link that opens in a new tab and points to your backend route:

```html
<a href="/admin/verstka/edit?post=123" target="_blank" rel="noopener noreferrer">
  Edit in Verstka
</a>
```

The backend route checks permissions, loads the article, gets `editorUrl` via the SDK, and redirects:

```php
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Config\VerstkaConfig;

function openVerstkaEditor(int $articleId, User $user, VerstkaConfig $config): void
{
    $article = ArticleRepository::find($articleId);

    if (!UserPolicy::canEdit($user, $article)) {
        throw new RuntimeException('Access denied');
    }

    $client = new VerstkaClient($config);

    $editorUrl = $client->getEditorUrl(
        (string) $article->id,
        $article->vms_json,
        []
    );

    header('Location: ' . $editorUrl, true, 302);
    exit;
}
```

For a first-time article, omit `vmsJson` or pass `null`. For re-editing, pass the last saved `vmsJson` so the editor opens the current version.

Both `vmsJson` and `metadata` accept an array or a JSON string. The SDK sends `metadata` as a JSON object and automatically adds `version: "php_<sdk-version>"`.

## 2. StorageAdapter

The SDK downloads the ZIP and extracts files, but does not know where your site stores media. Implement `StorageAdapter` to save files and return public URLs.

```php
use Verstka\Sdk\Storage\StorageAdapter;

final class CmsStorage implements StorageAdapter
{
    public function saveMedia(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string {
        return $this->saveToCdn("articles/$materialId/$filename", $tempPath);
    }

    public function saveFontFile(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string {
        return $this->saveToCdn("verstka/fonts/$filename", $tempPath);
    }

    public function saveFontsManifest(
        string $filename,
        string $tempPath,
        string $materialId,
        array $metadata
    ): string {
        return $this->saveToCdn("verstka/fonts/$filename", $tempPath);
    }
}
```

Each method must return a URL accessible to site visitors. The SDK replaces `dummy-*` placeholders in `vmsHtml`, `vmsJson['assets'][*]['clientUrl']`, `vms_fonts.css`, and the `fonts` tree.

For local development, use `LocalStorageAdapter`:

```php
use Verstka\Sdk\Storage\LocalStorageAdapter;

$storage = new LocalStorageAdapter(
    '/var/www/uploads',
    'https://cdn.example.com/uploads'
);
```

## 3. Material callback

When the user clicks Save in Verstka, your backend receives a callback. The SDK verifies the signature, downloads the ZIP, saves media via `StorageAdapter`, replaces temporary file URLs with public ones, and calls your `onFinalize` hook.

```php
use Verstka\Sdk\Client\VerstkaClient;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;

$client = new VerstkaClient($config);
$storage = new CmsStorage();

$result = $client->processMaterialCallback(
    $requestPayload,
    $_SERVER['HTTP_X_VERSTKA_SIGNATURE'] ?? '',
    $storage,
    function (ContentFinalizeContext $ctx): ContentFinalizeResult {
        ArticleRepository::saveVerstkaContent(
            $ctx->materialId,
            $ctx->vmsHtml,
            $ctx->vmsJson,
            $ctx->metadata
        );

        return new ContentFinalizeResult(true, $ctx->vmsJson);
    }
);

header('Content-Type: application/json');
echo json_encode($result->toResponse());
```

`ContentFinalizeContext` fields:

| Field | Description |
| --- | --- |
| `materialId` | CMS material ID |
| `metadata` | Metadata from the callback |
| `vmsJson` | Publication JSON with updated `clientUrl` values |
| `vmsHtml` | Publication HTML with replaced temporary URLs |
| `savedMediaUrls` | Map `{filename: public_url}` |

Save the updated `vmsJson` from the callback — pass it to `getEditorUrl` on the next editor open.

Response format (`toResponse()`):

```json
{"rc": 1, "rm": "Saved successfully", "data": {"vms_json": {...}}}
```

`rc: 1` means success, `rc: 0` means failure or rejection.

## 4. Fonts callback

When site fonts change in Verstka, a separate `site_fonts_updated` event is sent.

```php
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;

$result = $client->processFontsCallback(
    $requestPayload,
    $_SERVER['HTTP_X_VERSTKA_SIGNATURE'] ?? '',
    $storage,
    function (FontsFinalizeContext $ctx): FontsFinalizeResult {
        SiteSettings::set('verstka_fonts_css_url', $ctx->cssUrl);
        SiteSettings::set('verstka_fonts_json_url', $ctx->jsonUrl);

        return new FontsFinalizeResult(true, $ctx->fonts);
    }
);
```

`onFinalize` for fonts may be omitted if saving files via `storage` and returning the updated `fonts` tree to Verstka is enough.

## 5. Single callback endpoint

Use `CallbackDispatcher` to handle both material and fonts callbacks on one route:

```php
use Verstka\Sdk\Integration\CallbackDispatcher;
use Verstka\Sdk\Finalize\ContentFinalizeContext;
use Verstka\Sdk\Finalize\ContentFinalizeResult;
use Verstka\Sdk\Finalize\FontsFinalizeContext;
use Verstka\Sdk\Finalize\FontsFinalizeResult;

try {
    $response = CallbackDispatcher::dispatch(
        $client,
        $requestPayload,
        $_SERVER['HTTP_X_VERSTKA_SIGNATURE'] ?? '',
        $storage,
        function (ContentFinalizeContext $ctx): ContentFinalizeResult {
            ArticleRepository::saveVerstkaContent(
                $ctx->materialId,
                $ctx->vmsHtml,
                $ctx->vmsJson,
                $ctx->metadata
            );
            return new ContentFinalizeResult(true, $ctx->vmsJson);
        },
        function (FontsFinalizeContext $ctx): FontsFinalizeResult {
            SiteSettings::set('verstka_fonts_css_url', $ctx->cssUrl);
            return new FontsFinalizeResult(true, $ctx->fonts);
        }
    );

    header('Content-Type: application/json');
    echo json_encode($response);
} catch (\Throwable $e) {
    $error = CallbackDispatcher::mapException($e);
    http_response_code($error->status);
    header('Content-Type: application/json');
    echo json_encode($error->toArray());
}
```

The dispatcher checks `$payload['event']`: `site_fonts_updated` routes to the fonts flow, everything else to the material flow.

## Metadata

`metadata` is an optional array or JSON string passed when opening the editor. Verstka stores it in the session and returns it in the callback.

Useful keys:

- CMS user or section IDs
- `timeLimitedAuthToken` for extra permission checks in `onPreSave`
- `customContainers` for custom editor containers
- `webhook_auth_user` / `webhook_auth_password` for callback authorization (see below)

```php
$editorUrl = $client->getEditorUrl(
    (string) $article->id,
    $article->vms_json,
    [
        'editor_user_id' => (string) $user->id,
        'section_id' => (string) $article->section_id,
        'timeLimitedAuthToken' => $cmsToken,
        'customContainers' => [],
    ]
);
```

Service keys `version_id`, `version_cdate`, `user_email`, `user_ip` are added or updated by Verstka in the callback. Do not use `metadata` to store secrets or unnecessary PII.

Full reference: [`metadata`](https://docs.r2.verstka.org/ru/dev/api-integration.md#metadata).

## PreSave hooks

`processMaterialCallback` and `processFontsCallback` accept an optional `onPreSave` hook. It runs after signature verification but before ZIP download — use it for permission checks, locks, or quotas.

```php
use Verstka\Sdk\Finalize\ContentPreSaveContext;
use Verstka\Sdk\Finalize\PreSaveDecision;

$result = $client->processMaterialCallback(
    $payload,
    $signature,
    $storage,
    $onFinalize,
    function (ContentPreSaveContext $ctx): PreSaveDecision {
        if (!UserPolicy::canSave($ctx->metadata['timeLimitedAuthToken'] ?? null, $ctx->materialId)) {
            return new PreSaveDecision(false, 'Access denied');
        }

        return new PreSaveDecision(true);
    }
);
```

If `allow` is `false`, the ZIP is not downloaded, no files are written, and Verstka receives `rc: 0`.

## Callback authorization

If your callback URL is protected by Basic Auth or a Bearer token, pass credentials in `metadata` when opening the editor.

Basic Auth:

```php
$client->getEditorUrl('42', null, [
    'webhook_auth_user' => 'callback-user',
    'webhook_auth_password' => 'callback-password',
]);
```

Bearer token:

```php
$client->getEditorUrl('42', null, [
    'webhook_auth_user' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...',
]);
```

Verstka adds the `Authorization` header to the outgoing callback. Details: [Callback Authorization](https://docs.r2.verstka.org/ru/dev/api-integration.md#callback-authorization).

## Exceptions

| Exception | When |
| --- | --- |
| `VerstkaSignatureError` | Invalid `X-Verstka-Signature` |
| `VerstkaCallbackDataError` | Missing or invalid callback fields |
| `VerstkaContentTooLargeError` | ZIP exceeds `maxContentSize` |
| `VerstkaApiError` | HTTP error from Verstka API (`statusCode` property) |
| `VerstkaVmsJsonError` | Invalid `vmsJson` passed to `getEditorUrl` |
| `VerstkaMetadataJsonError` | Invalid `metadata` passed to `getEditorUrl` |

Use `CallbackDispatcher::mapException($e)` to convert exceptions to HTTP status codes and JSON error bodies in your callback handler.

## Documentation

Full integration guide (Russian):

- [API integration](https://docs.r2.verstka.org/ru/dev/api-integration.md)
- [Site integration](https://docs.r2.verstka.org/ru/dev/site-integration.md)

## License

MIT — see [LICENSE](LICENSE).
