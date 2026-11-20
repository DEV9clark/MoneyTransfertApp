<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

/**
 * Journal d'audit : qui a fait quoi, quand, depuis où.
 * Ne doit jamais faire échouer l'opération métier.
 */
class AuditLogger
{
    public static function log(string $action, ?Model $subject = null, array $meta = [], $userId = null): void
    {
        try {
            $request = request();
            $actor = auth()->user();
            // audit_logs.user_id référence les agents/admins ; un client est tracé dans meta.
            if ($actor instanceof \App\Models\Client) {
                $meta['client_id'] = $actor->id;
                $actor = null;
            }
            AuditLog::create([
                'user_id' => $userId ?? optional($actor)->id,
                'action' => $action,
                'subject_type' => $subject ? get_class($subject) : null,
                'subject_id' => $subject ? $subject->getKey() : null,
                'ip_address' => $request ? $request->ip() : null,
                'user_agent' => $request ? substr((string) $request->userAgent(), 0, 255) : null,
                'meta' => $meta ?: null,
            ]);
        } catch (\Throwable $e) {
            Log::warning('[Audit] échec écriture', ['action' => $action, 'error' => $e->getMessage()]);
        }
    }
}
