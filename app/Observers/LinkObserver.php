<?php

namespace App\Observers;

use App\Models\AuditLog;
use App\Models\Link;
use Illuminate\Support\Facades\Auth;

class LinkObserver
{
    public function saved(Link $link): void
    {
        $this->refreshSearchBlob($link);
    }

    public function created(Link $link): void
    {
        $this->audit($link, 'created', null, $link->getAttributes());
    }

    public function updated(Link $link): void
    {
        $changes = $link->getChanges();

        // search_blob is derived, not authored - an entry in the audit log
        // every time it is recomputed would bury the changes a human made.
        unset($changes['search_blob'], $changes['updated_at'],
            $changes['click_count'], $changes['share_open_count']);

        if ($changes === []) {
            return;
        }

        $this->audit(
            $link,
            'updated',
            array_intersect_key($link->getOriginal(), $changes),
            $changes,
        );
    }

    public function deleted(Link $link): void
    {
        $this->audit($link, 'deleted', $link->getOriginal(), null);
    }

    public function restored(Link $link): void
    {
        $this->audit($link, 'restored', null, $link->getAttributes());
    }

    /**
     * Recompute the materialised FULLTEXT haystack.
     *
     * Written quietly and only when it actually changed: a plain save() here
     * would re-fire this same observer and recurse.
     */
    public function refreshSearchBlob(Link $link): void
    {
        $blob = $link->buildSearchBlob();

        if ($blob === $link->search_blob) {
            return;
        }

        $link->search_blob = $blob;
        $link->saveQuietly();
    }

    private function audit(Link $link, string $event, ?array $old, ?array $new): void
    {
        AuditLog::create([
            'user_id' => Auth::id(),
            'auditable_type' => $link->getMorphClass(),
            'auditable_id' => $link->getKey(),
            'event' => $event,
            'old_values' => $old,
            'new_values' => $new,
        ]);
    }
}
