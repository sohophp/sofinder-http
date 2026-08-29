<?php

declare(strict_types=1);

namespace SohoPHP\SoFinder\Http;

/** Canonical framework-neutral route surface shared by every HTTP bridge. */
final class EndpointCatalog
{
    /** @return list<EndpointDefinition> */
    public static function all(): array
    {
        $routes = [
            ['sofinder_browser', '/browser', 'GET'],
            ['sofinder_asset', '/assets/{file}', 'GET'],
            ['sofinder_api_config', '/api/config', 'GET'],
            ['sofinder_api_capabilities', '/api/capabilities', 'GET'],
            ['sofinder_health', '/health', 'GET'],
            ['sofinder_liveness', '/live', 'GET'],
            ['sofinder_metrics', '/metrics', 'GET'],
            ['sofinder_security_status', '/api/security/status', 'GET'],
            ['sofinder_api_entries', '/api/entries', 'GET'],
            ['sofinder_api_asset_resolve', '/api/assets/resolve', 'GET'],
            ['sofinder_api_asset_search', '/api/assets/search', 'GET'],
            ['sofinder_api_asset_get', '/api/assets/{id}', 'GET'],
            ['sofinder_api_asset_update', '/api/assets/{id}/metadata', 'PATCH'],
            ['sofinder_api_asset_usage_list', '/api/assets/{id}/usages', 'GET'],
            ['sofinder_api_asset_usage_put', '/api/assets/{id}/usages/{referenceId}', 'PUT'],
            ['sofinder_api_asset_usage_remove', '/api/assets/{id}/usages/{referenceId}', 'DELETE'],
            ['sofinder_api_asset_delete_check', '/api/assets/delete-check', 'POST'],
            ['sofinder_api_asset_session_create', '/api/assets/access-sessions', 'POST'],
            ['sofinder_api_asset_session_revoke', '/api/assets/access-sessions/{id}', 'DELETE'],
            ['sofinder_asset_session_content', '/asset-session/{token}/{assetId}', 'GET'],
            ['sofinder_api_folder', '/api/folders', 'POST'],
            ['sofinder_api_upload', '/api/uploads', 'POST'],
            ['sofinder_api_chunk_upload', '/api/uploads/chunks', 'POST'],
            ['sofinder_api_chunk_cancel', '/api/uploads/chunks/{id}', 'DELETE'],
            ['sofinder_api_chunk_status', '/api/uploads/chunks/{id}', 'GET'],
            ['sofinder_api_rename', '/api/entries/rename', 'PATCH'],
            ['sofinder_api_copy', '/api/entries/copy', 'POST'],
            ['sofinder_api_move', '/api/entries/move', 'POST'],
            ['sofinder_api_delete', '/api/entries', 'DELETE'],
            ['sofinder_api_batch', '/api/entries/batch', 'POST'],
            ['sofinder_api_batch_rename', '/api/entries/batch-rename', 'POST'],
            ['sofinder_api_download', '/api/download', 'GET'],
            ['sofinder_api_content', '/api/content', 'GET'],
            ['sofinder_api_signed_url', '/api/signed-url', 'GET'],
            ['sofinder_signed_content', '/signed/{token}', 'GET'],
            ['sofinder_api_checksum', '/api/checksum', 'GET'],
            ['sofinder_api_text_preview', '/api/preview/text', 'GET'],
            ['sofinder_document_preview', '/api/preview/document', 'GET'],
            ['sofinder_document_preview_job_create', '/api/preview/document/jobs', 'POST'],
            ['sofinder_document_preview_job_status', '/api/preview/document/jobs/{id}', 'GET'],
            ['sofinder_api_trash', '/api/trash', 'GET'],
            ['sofinder_api_trash_restore', '/api/trash/{id}/restore', 'POST'],
            ['sofinder_api_trash_delete', '/api/trash/{id}', 'DELETE'],
            ['sofinder_image_thumbnail', '/api/images/thumbnail', 'GET'],
            ['sofinder_image_info', '/api/images/info', 'GET'],
            ['sofinder_image_variant', '/api/images/variant', 'GET'],
            ['sofinder_image_edit', '/api/images/edit', 'PATCH'],
            ['sofinder_image_batch', '/api/images/batch', 'PATCH'],
            ['sofinder_archive_download', '/api/archive', 'POST'],
            ['sofinder_metadata_get', '/api/metadata', 'GET'],
            ['sofinder_metadata_update', '/api/metadata', 'PATCH'],
            ['sofinder_quick_upload', '/compat/ckeditor4/upload', 'POST'],
        ];
        $requirements = [
            'sofinder_asset' => ['file' => '[A-Za-z0-9._-]+\.(?:js|css)'],
            'sofinder_api_asset_get' => ['id' => '[a-f0-9-]{36}'],
            'sofinder_api_asset_update' => ['id' => '[a-f0-9-]{36}'],
            'sofinder_api_asset_usage_list' => ['id' => '[a-f0-9-]{36}'],
            'sofinder_api_asset_usage_put' => ['id' => '[a-f0-9-]{36}', 'referenceId' => '[A-Za-z0-9._:-]{1,160}'],
            'sofinder_api_asset_usage_remove' => ['id' => '[a-f0-9-]{36}', 'referenceId' => '[A-Za-z0-9._:-]{1,160}'],
            'sofinder_api_asset_session_revoke' => ['id' => '[a-f0-9]{32}'],
            'sofinder_asset_session_content' => ['token' => '[A-Za-z0-9._-]{76}', 'assetId' => '[a-f0-9-]{36}'],
            'sofinder_api_chunk_cancel' => ['id' => '[a-zA-Z0-9_-]{16,80}'],
            'sofinder_api_chunk_status' => ['id' => '[a-zA-Z0-9_-]{16,80}'],
            'sofinder_signed_content' => ['token' => '[A-Za-z0-9_-]+\.[A-Za-z0-9_-]+'],
            'sofinder_document_preview_job_status' => ['id' => '[a-f0-9]{48}'],
            'sofinder_api_trash_restore' => ['id' => '[a-f0-9]{32}'],
            'sofinder_api_trash_delete' => ['id' => '[a-f0-9]{32}'],
        ];
        $public = ['sofinder_liveness', 'sofinder_signed_content', 'sofinder_asset_session_content'];

        return array_map(
            static fn (array $route): EndpointDefinition => new EndpointDefinition(
                $route[0],
                $route[1],
                [$route[2]],
                $requirements[$route[0]] ?? [],
                in_array($route[0], $public, true),
            ),
            $routes,
        );
    }

    public static function get(string $name): EndpointDefinition
    {
        foreach (self::all() as $endpoint) {
            if ($endpoint->name === $name) {
                return $endpoint;
            }
        }

        throw new \InvalidArgumentException(sprintf('Unknown SoFinder endpoint "%s".', $name));
    }
}
