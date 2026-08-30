<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

use SohoPHP\SoFinder\Contract\CsrfTokenProviderInterface;
use SohoPHP\SoFinder\Contract\EndpointUrlGeneratorInterface;
use SohoPHP\SoFinder\Contract\RoleAuthorizationInterface;
use SohoPHP\SoFinder\Contract\WorkspaceOptionProviderInterface;
use SohoPHP\SoFinder\Exception\SoFinderException;
use SohoPHP\SoFinder\Exception\NotFoundException;
use SohoPHP\SoFinder\Feature\FeaturePolicy;
use SohoPHP\SoFinder\FileManager;
use SohoPHP\SoFinder\Value\RequestContext;
use SohoPHP\SoFinder\Value\Theme;
use SohoPHP\SoFinder\Workspace\WorkspaceProvider;

/** Framework-neutral browser bootstrap document and configuration policy. */
final class BrowserPage
{
    /**
     * @param array<string,mixed> $ui
     * @param list<string> $securityStatusRoles
     * @param list<string> $pickerAllowedOrigins
     */
    public function __construct(
        private readonly FileManager $files,
        private readonly EndpointUrlGeneratorInterface $urls,
        private readonly CsrfTokenProviderInterface $csrf,
        private readonly string $assetVersion,
        private readonly Theme $theme,
        private readonly array $ui,
        private readonly FeaturePolicy $features = new FeaturePolicy(),
        private readonly ?RoleAuthorizationInterface $authorization = null,
        private readonly array $securityStatusRoles = [],
        private readonly array $pickerAllowedOrigins = [],
        private readonly ?WorkspaceProvider $workspaces = null,
        private readonly ?WorkspaceOptionProviderInterface $workspaceOptions = null,
        private readonly bool $pickerLockResource = true,
    ) {
    }

    public function render(RequestContext $request): string
    {
        $resources = $this->files->resources();
        $language = $this->language($request);
        $pickerRequestId = $this->pickerRequestId($this->string($request->query('pickerRequestId')));
        $selectMode = $request->query('CKEditorFuncNum') !== null || $this->boolean($request->query('select'));
        $mode = $this->enum($request, 'uiMode', ['auto', 'manager', 'picker'], (string) ($this->ui['mode'] ?? 'auto'));
        $resolvedMode = $mode === 'auto' ? ($selectMode ? 'picker' : 'manager') : $mode;
        $resource = $this->string($request->query('type'));
        $lockResource = $this->override($request, 'resourceLock', $this->pickerLockResource);
        $pickerResource = $resolvedMode === 'picker' && $resource !== '' && $lockResource ? $resource : null;
        if ($pickerResource !== null && !in_array($pickerResource, array_column($resources, 'name'), true)) {
            throw new NotFoundException('The requested picker resource type does not exist or is not accessible.');
        }
        $config = [
            'apiBase' => $this->urls->generate('sofinder_api_config'),
            'csrfToken' => $this->csrf->token($request),
            'language' => $language,
            'resource' => $resource,
            'pickerResource' => $pickerResource,
            'initialPath' => $this->safePath($this->string($request->query('path'))),
            'selectMode' => $selectMode,
            'selectionKind' => in_array($this->string($request->query('selection')), ['file', 'image'], true) ? $this->string($request->query('selection')) : 'any',
            'ckeditorFunction' => (int) $request->query('CKEditorFuncNum', 0),
            'pickerRequestId' => $pickerRequestId,
            'pickerOrigin' => $pickerRequestId === '' ? '' : $this->pickerOrigin($request),
            'theme' => $this->theme->values(),
            'featureDefaults' => ['folderTree' => (bool) ($this->ui['folder_tree'] ?? false)],
            'featureAvailability' => $this->features->browserAvailability(),
            'securityStatusAvailable' => $this->features->enabled('security_status') && ($this->securityStatusRoles === [] || ($this->authorization !== null && array_filter($this->securityStatusRoles, $this->authorization->isGranted(...)) !== [])),
            'uiDefaults' => [
                'scale' => (string) ($this->ui['scale'] ?? 'standard'),
                'uploadConflictStrategy' => (string) ($this->ui['upload_conflict_strategy'] ?? 'ask'),
                'lowercaseUploadExtensions' => (bool) ($this->ui['lowercase_upload_extensions'] ?? true),
                'mode' => $resolvedMode,
                'header' => $this->override($request, 'uiHeader', (bool) ($this->ui['header'] ?? true)),
                'logo' => $this->override($request, 'uiLogo', (bool) ($this->ui['logo'] ?? true)),
                'search' => $this->override($request, 'uiSearch', (bool) ($this->ui['search'] ?? true)),
                'languageSwitcher' => $this->override($request, 'uiLanguage', (bool) ($this->ui['language_switcher'] ?? true)),
                'viewSwitcher' => $this->override($request, 'uiView', (bool) ($this->ui['view_switcher'] ?? true)),
                'fullTools' => $this->enum($request, 'uiTools', ['common', 'full'], 'common') === 'full',
            ],
            'workspace' => $this->workspace($request),
        ];
        $encoded = htmlspecialchars(json_encode($config, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        $version = rawurlencode($this->assetVersion);
        $css = htmlspecialchars($this->urls->generate('sofinder_asset', ['file' => 'sofinder.css']) . '?v=' . $version, ENT_QUOTES, 'UTF-8');
        $js = htmlspecialchars($this->urls->generate('sofinder_asset', ['file' => 'sofinder.js']) . '?v=' . $version, ENT_QUOTES, 'UTF-8');

        return "<!doctype html>\n<html lang=\"{$language}\">\n<head>\n  <meta charset=\"utf-8\">\n  <meta name=\"viewport\" content=\"width=device-width,initial-scale=1\">\n  <meta name=\"robots\" content=\"noindex,nofollow\">\n  <title>SoFinder</title>\n  <link rel=\"stylesheet\" href=\"{$css}\">\n</head>\n<body>\n  <div id=\"sofinder-root\" data-config=\"{$encoded}\"></div>\n  <noscript>SoFinder requires JavaScript.</noscript>\n  <script type=\"module\" src=\"{$js}\"></script>\n</body>\n</html>";
    }

    /** @return array<string,mixed>|null */
    private function workspace(RequestContext $request): ?array
    {
        if ($this->workspaces === null) return null;
        $current = $this->workspaces->current($request);
        $options = $this->workspaceOptions?->options($request, $current) ?? [];
        $options = array_values(array_filter($options, $this->validWorkspaceOption(...)));

        return $current->jsonSerialize() + ['options' => $options];
    }

    /** @param array{id?:mixed,label?:mixed,url?:mixed} $option */
    private function validWorkspaceOption(array $option): bool
    {
        if (!is_string($option['id'] ?? null) || !is_string($option['label'] ?? null) || !is_string($option['url'] ?? null)) return false;
        $path = parse_url($option['url'], PHP_URL_PATH);
        return preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D', $option['id']) === 1 && trim($option['label']) !== '' && strlen($option['label']) <= 100
            && is_string($path) && str_starts_with($path, '/') && !str_starts_with($path, '//') && preg_match('#(?:^|/)\.\.(?:/|$)|[\x00-\x1F\x7F]#', $path) !== 1
            && parse_url($option['url'], PHP_URL_SCHEME) === null && parse_url($option['url'], PHP_URL_HOST) === null;
    }

    private function language(RequestContext $request): string
    {
        $language = strtolower($this->string($request->query('lang')));
        if (in_array($language, ['en', 'zh-cn', 'zh-tw'], true)) return $language;
        $preferred = str_replace('_', '-', strtolower(explode(',', $request->header('Accept-Language'))[0] ?? ''));
        return preg_match('/^zh-(tw|hk|mo)|^zh-hant/', $preferred) === 1 ? 'zh-tw' : (str_starts_with($preferred, 'zh') ? 'zh-cn' : 'en');
    }

    /** @param list<string> $allowed */
    private function enum(RequestContext $request, string $name, array $allowed, string $fallback): string
    {
        $value = $this->string($request->query($name));
        return in_array($value, $allowed, true) ? $value : $fallback;
    }

    private function override(RequestContext $request, string $name, bool $fallback): bool
    {
        $value = $request->query($name);
        return $value === '1' ? true : ($value === '0' ? false : $fallback);
    }

    private function safePath(string $path): string
    {
        return $path === '' || strlen($path) > 2048 || preg_match('//u', $path) !== 1 || preg_match('/[\x00-\x1F\x7F]/u', $path) === 1 ? '' : str_replace('\\', '/', trim($path, '/'));
    }

    private function pickerRequestId(string $value): string { return preg_match('/^[A-Za-z0-9-]{16,80}$/D', $value) === 1 ? $value : ''; }

    private function pickerOrigin(RequestContext $request): string
    {
        $requested = rtrim($this->string($request->query('pickerOrigin')), '/');
        if ($requested === '' || $requested === $request->schemeAndHost) return $request->schemeAndHost;
        if (in_array($requested, $this->pickerAllowedOrigins, true)) return $requested;
        throw new SoFinderException('The requested picker origin is not allowed.', 'invalid_picker_origin', 400);
    }

    private function string(mixed $value): string { return is_scalar($value) || $value instanceof \Stringable ? (string) $value : ''; }
    private function boolean(mixed $value): bool { return filter_var($value, FILTER_VALIDATE_BOOL); }
}
