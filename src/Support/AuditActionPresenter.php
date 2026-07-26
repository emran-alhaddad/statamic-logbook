<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Support;

/**
 * Purely presentational layer over raw audit `action` strings + the
 * `changes` JSON column. No database writes happen here — the raw
 * event strings remain intact on disk (e.g. `statamic.user.updated`)
 * and this class maps them to human labels at render time.
 *
 * That keeps two promises:
 *
 *   1. Historical rows stay queryable by machine name — `?action=
 *      statamic.user.updated` keeps working.
 *   2. Humanised labels can be retuned at any time without a data
 *      migration.
 */
class AuditActionPresenter
{
    /**
     * Known action strings → short human-readable verb phrase.
     *
     * Keep these concise — they render inside a chip. Longer context
     * lives in the subject cell.
     *
     * @var array<string, string>
     */
    private const LABELS = [
        // --- Auth (Statamic fires these via its User pipeline) --------------
        'statamic.user.login'                => 'Signed in',
        'statamic.user.loggedIn'             => 'Signed in',
        'statamic.user.logout'               => 'Signed out',
        'statamic.user.loggedOut'            => 'Signed out',
        'statamic.user.registered'           => 'User registered',
        'statamic.user.passwordChanged'      => 'Password changed',
        'statamic.user.passwordReset'        => 'Password reset',
        'statamic.user.loginFailed'          => 'Failed sign-in',
        'statamic.user.impersonated'         => 'User impersonated',
        'statamic.user.impersonationEnded'   => 'Impersonation ended',
        'statamic.user.emailChanged'         => 'Email changed',
        'statamic.user.twoFactorEnabled'     => 'Two-factor enabled',
        'statamic.user.twoFactorDisabled'    => 'Two-factor disabled',
        'statamic.user.twoFactorFailed'      => 'Failed two-factor attempt',
        'statamic.user.recoveryCodeUsed'     => 'Recovery code used',

        // --- User lifecycle -------------------------------------------------
        'statamic.user.created'  => 'User created',
        'statamic.user.saved'    => 'User saved',
        'statamic.user.updated'  => 'User updated',
        'statamic.user.saving'   => 'User saving',
        'statamic.user.deleted'  => 'User deleted',
        'statamic.user.restored' => 'User restored',
        'statamic.user.event'    => 'User event',

        // --- Entries --------------------------------------------------------
        'statamic.entry.created'      => 'Entry created',
        'statamic.entry.saved'        => 'Entry saved',
        'statamic.entry.updated'      => 'Entry updated',
        'statamic.entry.saving'       => 'Entry saving',
        'statamic.entry.deleted'      => 'Entry deleted',
        'statamic.entry.published'    => 'Entry published',
        'statamic.entry.unpublished'  => 'Entry moved to draft',
        'statamic.entry.restored'     => 'Entry restored',

        // --- Terms / taxonomies --------------------------------------------
        'statamic.term.created'      => 'Term created',
        'statamic.term.saved'        => 'Term saved',
        'statamic.term.updated'      => 'Term updated',
        'statamic.term.deleted'      => 'Term deleted',
        'statamic.term.published'    => 'Term published',
        'statamic.term.unpublished'  => 'Term moved to draft',
        'statamic.TermSaved'     => 'Term saved',
        'statamic.TermSaving'    => 'Term saving',
        'statamic.LocalizedTermSaved' => 'Localised term saved',

        'statamic.taxonomy.created' => 'Taxonomy created',
        'statamic.taxonomy.saved'   => 'Taxonomy saved',
        'statamic.taxonomy.updated' => 'Taxonomy updated',
        'statamic.taxonomy.deleted' => 'Taxonomy deleted',

        // --- Roles / user groups (permissions — high audit value) -----------
        'statamic.role.created'      => 'Role created',
        'statamic.role.saved'        => 'Role saved',
        'statamic.role.updated'      => 'Role updated',
        'statamic.role.deleted'      => 'Role deleted',
        'statamic.usergroup.created' => 'User group created',
        'statamic.usergroup.saved'   => 'User group saved',
        'statamic.usergroup.updated' => 'User group updated',
        'statamic.usergroup.deleted' => 'User group deleted',

        // --- Globals / nav / collections ------------------------------------
        'statamic.globals.created' => 'Global set created',
        'statamic.globals.saved'   => 'Global set saved',
        'statamic.globals.updated' => 'Global set updated',
        'statamic.globals.deleted' => 'Global set deleted',

        'statamic.global_variables.saved'   => 'Globals edited',
        'statamic.global_variables.updated' => 'Globals edited',
        'statamic.global_variables.created' => 'Globals created',
        'statamic.global_variables.deleted' => 'Globals deleted',

        'statamic.nav.created'  => 'Nav created',
        'statamic.nav.saved'    => 'Nav saved',
        'statamic.nav.updated'  => 'Nav updated',
        'statamic.nav.deleted'  => 'Nav deleted',
        'statamic.NavBlueprintFound' => 'Nav blueprint loaded',

        'statamic.collection.created' => 'Collection created',
        'statamic.collection.saved'   => 'Collection saved',
        'statamic.collection.updated' => 'Collection updated',
        'statamic.collection.deleted' => 'Collection deleted',

        // --- Structure trees (page ordering / nav arrangement) --------------
        'statamic.tree.created' => 'Tree created',
        'statamic.tree.saved'   => 'Structure reordered',
        'statamic.tree.updated' => 'Structure reordered',
        'statamic.tree.deleted' => 'Tree deleted',

        // --- Schema: blueprints & fieldsets ---------------------------------
        'statamic.blueprint.created' => 'Blueprint created',
        'statamic.blueprint.saved'   => 'Blueprint saved',
        'statamic.blueprint.updated' => 'Blueprint updated',
        'statamic.blueprint.deleted' => 'Blueprint deleted',
        'statamic.blueprint.reset'   => 'Blueprint reset',
        'statamic.fieldset.created'  => 'Fieldset created',
        'statamic.fieldset.saved'    => 'Fieldset saved',
        'statamic.fieldset.updated'  => 'Fieldset updated',
        'statamic.fieldset.deleted'  => 'Fieldset deleted',
        'statamic.fieldset.reset'    => 'Fieldset reset',

        // --- Sites / addons ---------------------------------------------------
        'statamic.site.created' => 'Site created',
        'statamic.site.saved'   => 'Site saved',
        'statamic.site.updated' => 'Site updated',
        'statamic.site.deleted' => 'Site deleted',
        'statamic.addon.saved'   => 'Addon settings saved',
        'statamic.addon.updated' => 'Addon settings saved',

        // --- Assets ---------------------------------------------------------
        'statamic.asset.uploaded' => 'Asset uploaded',
        'statamic.asset.created'  => 'Asset created',
        'statamic.asset.saved'    => 'Asset saved',
        'statamic.asset.updated'  => 'Asset updated',
        'statamic.asset.deleted'  => 'Asset deleted',
        'statamic.asset.replaced' => 'Asset replaced',

        'statamic.asset_folder.saved'      => 'Asset folder saved',
        'statamic.asset_folder.deleted'    => 'Asset folder deleted',
        'statamic.asset_container.created' => 'Asset container created',
        'statamic.asset_container.saved'   => 'Asset container saved',
        'statamic.asset_container.updated' => 'Asset container updated',
        'statamic.asset_container.deleted' => 'Asset container deleted',

        // --- Forms & submissions ---------------------------------------------
        'statamic.form.submitted' => 'Form submitted',
        'statamic.form.created'   => 'Form created',
        'statamic.form.saved'     => 'Form saved',
        'statamic.form.updated'   => 'Form updated',
        'statamic.form.deleted'   => 'Form deleted',
        'statamic.submission.deleted' => 'Submission deleted',

        // --- Catch-all fallback used when no specific mapping matched ------
        'statamic.statamic.event' => 'Statamic event',
    ];

    /**
     * Visual severity variant for the chip styling. Maps to the
     * `.lb-chip--{variant}` CSS classes already shipped.
     */
    public static function variant(string $action): string
    {
        $a = strtolower($action);

        // Destructive actions — red.
        if (str_contains($a, 'deleted') || str_ends_with($a, '.delete')) {
            return 'delete';
        }
        // Creation — green.
        if (str_contains($a, 'created') || str_contains($a, 'uploaded') || str_contains($a, 'registered')) {
            return 'create';
        }
        // Auth events — indigo/violet (info-variant).
        if (str_contains($a, 'login') || str_contains($a, 'logout') || str_contains($a, 'loggedin') || str_contains($a, 'loggedout')
            || str_contains($a, 'password') || str_contains($a, 'impersonat') || str_contains($a, 'twofactor')) {
            return 'auth';
        }
        // Updates / saves — amber.
        if (str_contains($a, 'updated') || str_contains($a, 'saved') || str_contains($a, 'published') || str_contains($a, 'restored')) {
            return 'update';
        }

        return 'muted';
    }

    /**
     * Return the human-readable label for an action. Falls back to a
     * humanised split of the machine name.
     */
    public static function label(?string $action): string
    {
        $action = (string) ($action ?? '');
        if ($action === '') {
            return '—';
        }
        if (isset(self::LABELS[$action])) {
            return self::LABELS[$action];
        }

        // Fallback: statamic.user.updated → User updated
        $clean = preg_replace('/^statamic\./', '', $action) ?? $action;
        $parts = array_values(array_filter(preg_split('/[._\s]+/', $clean) ?? []));

        if (empty($parts)) {
            return $action;
        }

        // Capitalise the first token, lowercase+humanise the rest.
        $head = ucfirst(self::humanise($parts[0]));
        $tail = array_slice($parts, 1);
        $tailStr = trim(self::humanise(implode(' ', $tail)));

        return trim($head . ' ' . $tailStr);
    }

    /**
     * Short "what changed" hint rendered inline under the action chip.
     *
     * Parses the `changes` JSON column (shape: `{field: {from, to}}`)
     * and returns up to $maxFields entries formatted as
     * "name · Draft → Published, slug · old → new" — each value
     * truncated to $maxLen chars. Returns null if there's nothing
     * usable to show.
     */
    public static function changeSummary(?string $changesJson, int $maxFields = 2, int $maxLen = 32): ?string
    {
        if (! is_string($changesJson) || $changesJson === '') {
            return null;
        }

        $changes = json_decode($changesJson, true);
        if (! is_array($changes) || empty($changes)) {
            return null;
        }

        $ignore = ['updated_at', 'created_at'];
        $fragments = [];
        $count = 0;
        $total = 0;

        foreach ($changes as $field => $pair) {
            $total++;
            if (in_array($field, $ignore, true)) {
                continue;
            }
            if ($count >= $maxFields) {
                continue;
            }
            if (! is_array($pair) || (! array_key_exists('from', $pair) && ! array_key_exists('to', $pair))) {
                continue;
            }

            $from = self::stringify($pair['from'] ?? null, $maxLen);
            $to   = self::stringify($pair['to'] ?? null, $maxLen);

            if ($from === '' && $to === '') {
                continue;
            }

            // For created: there's no meaningful "from".
            if ($from === '' || $from === '(empty)') {
                $fragments[] = self::humanise($field).' · set to '.$to;
            } elseif ($to === '' || $to === '(empty)') {
                $fragments[] = self::humanise($field).' · cleared';
            } else {
                $fragments[] = self::humanise($field).' · '.$from.' → '.$to;
            }

            $count++;
        }

        if (empty($fragments)) {
            return null;
        }

        $remaining = $total - count($fragments);
        $line = implode(', ', $fragments);
        if ($remaining > 0) {
            $line .= '  +'.$remaining.' more';
        }

        return $line;
    }

    /**
     * Return the FQCN-ish icon key for an action, for callers that want
     * to swap out SVG marks per variant. Currently only used for
     * presentational parity — callers may ignore.
     */
    public static function icon(string $action): string
    {
        return match (self::variant($action)) {
            'delete' => 'trash',
            'create' => 'plus',
            'auth'   => 'key',
            'update' => 'edit',
            default  => 'dot',
        };
    }

    /**
     * Coerce a changes value into a short display string. Arrays /
     * objects collapse to "{n items}" to avoid dumping huge payloads
     * into the table cell.
     */
    private static function stringify(mixed $v, int $maxLen): string
    {
        if ($v === null) {
            return '(empty)';
        }
        if (is_bool($v)) {
            return $v ? 'true' : 'false';
        }
        if (is_array($v)) {
            return '{'.count($v).' items}';
        }
        if (is_object($v)) {
            return '{object}';
        }

        $s = (string) $v;
        $s = trim(preg_replace('/\s+/', ' ', $s) ?? '');
        if ($s === '') {
            return '(empty)';
        }
        if (mb_strlen($s) > $maxLen) {
            return mb_substr($s, 0, $maxLen - 1).'…';
        }

        return $s;
    }

    private static function humanise(string $v): string
    {
        $v = str_replace(['_', '-', '.'], ' ', $v);
        $v = preg_replace('/([a-z])([A-Z])/', '$1 $2', $v) ?? $v;
        $v = mb_strtolower(trim($v));

        return $v;
    }
}
