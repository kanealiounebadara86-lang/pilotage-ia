<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;

/**
 * Trace automatiquement les créations/modifications/suppressions d'un modèle
 * dans audit_logs (cahier des charges section 12 : "pourquoi le stock de ce
 * produit a diminué de 50 unités ?" doit être traçable). À ajouter avec
 * `use Auditable;` sur les modèles dont l'historique compte pour le métier.
 *
 * Ne journalise que les actions faites par un utilisateur authentifié —
 * les seeders et scripts système ne polluent pas l'audit.
 */
trait Auditable
{
    protected static function bootAuditable(): void
    {
        static::created(function ($model) {
            $model->writeAuditLog('create', null, $model->getAttributes());
        });

        static::updated(function ($model) {
            $model->writeAuditLog('update', $model->getOriginal(), $model->getChanges());
        });

        static::deleted(function ($model) {
            $model->writeAuditLog('delete', $model->getOriginal(), null);
        });
    }

    protected function writeAuditLog(string $action, ?array $before, ?array $after): void
    {
        if (! auth()->check()) {
            return;
        }

        $sensitiveFields = ['password', 'remember_token'];
        $before = $before ? array_diff_key($before, array_flip($sensitiveFields)) : $before;
        $after = $after ? array_diff_key($after, array_flip($sensitiveFields)) : $after;

        AuditLog::create([
            'company_id' => $this->company_id ?? auth()->user()->company_id,
            'user_id' => auth()->id(),
            'action' => $action,
            'subject_type' => static::class,
            'subject_id' => $this->getKey(),
            'before' => $before,
            'after' => $after,
            'ip_address' => request()?->ip(),
        ]);
    }
}
