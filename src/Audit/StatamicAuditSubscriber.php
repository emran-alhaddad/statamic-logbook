<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Audit;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;

/**
 * Subscribes to curated Statamic mutation events and records audit rows.
 *
 * Event list resolution happens in three layers, in precedence order
 * (later layers extend earlier ones):
 *
 *   1. {@see EventMap::curatedEvents()} — per-major default allow-list.
 *   2. `config('logbook.audit_logs.events')` — user-added allow-list.
 *   3. (optional) Discovery scan of `vendor/statamic/cms/src/Events`
 *      when `logbook.audit_logs.discover_events` is true.
 *
 * The resolved list is then filtered through:
 *
 *   - {@see class_exists()} — drops events that do not exist in the
 *     running Statamic major (safe across 3/4/5/6 without fatals).
 *   - `config('logbook.audit_logs.exclude_events')` merged with
 *     {@see EventMap::excludedEvents()} — hard block-list.
 *
 * The handler code path intentionally avoids a strict `use` import of
 * {@see \Statamic\Entries\Entry}, because that would trigger autoload
 * at first class reference. We use `instanceof` against the fully-
 * qualified name which PHP resolves without autoloading when the
 * operand is null / non-object — and guards around property access.
 */
class StatamicAuditSubscriber
{
    /**
     * Events whose class name does not follow the `Noun` + `Created|Saved|
     * Deleted` convention, so the generic parser cannot derive a sensible
     * action from it. Without this map they all collapse into
     * `statamic.user.event` / `statamic.statamic.event`.
     *
     * @var array<string, string> event basename => action string
     */
    private const FIXED_ACTIONS = [
        'ImpersonationStarted' => 'statamic.user.impersonated',
        'ImpersonationEnded' => 'statamic.user.impersonationEnded',
        'UserPasswordChanged' => 'statamic.user.passwordChanged',
        'TwoFactorAuthenticationEnabled' => 'statamic.user.twoFactorEnabled',
        'TwoFactorAuthenticationDisabled' => 'statamic.user.twoFactorDisabled',
        'TwoFactorAuthenticationFailed' => 'statamic.user.twoFactorFailed',
        'TwoFactorRecoveryCodeReplaced' => 'statamic.user.recoveryCodeUsed',
        'AssetUploaded' => 'statamic.asset.uploaded',
        'AssetReplaced' => 'statamic.asset.replaced',
        'AssetReuploaded' => 'statamic.asset.replaced',
        'FormSubmitted' => 'statamic.form.submitted',
        'SubmissionDeleted' => 'statamic.submission.deleted',
        'BlueprintReset' => 'statamic.blueprint.reset',
        'FieldsetReset' => 'statamic.fieldset.reset',
        // A scheduled entry going live is a publish, not a generic event.
        'EntryScheduleReached' => 'statamic.entry.published',
    ];

    /**
     * Event property name => canonical subject type used in the action
     * string. Ordered — the first property present on the event wins.
     *
     * @var array<string, string>
     */
    private const SUBJECT_PROPS = [
        'entry' => 'entry',
        'newAsset' => 'asset',
        'asset' => 'asset',
        'folder' => 'asset_folder',
        'container' => 'asset_container',
        'submission' => 'submission',
        'term' => 'term',
        'taxonomy' => 'taxonomy',
        'nav' => 'nav',
        'tree' => 'tree',
        'collection' => 'collection',
        'form' => 'form',
        'blueprint' => 'blueprint',
        'fieldset' => 'fieldset',
        'site' => 'site',
        'settings' => 'addon',
        'role' => 'role',
        'group' => 'usergroup',
        'user' => 'user',
        'globalSet' => 'globals',
        'globals' => 'globals',
        // GlobalVariables is the per-site content, GlobalSet is its config.
        // Separate types because both fire when a set is created, and a
        // merged type made one act read as two identical rows.
        'variables' => 'global_variables',
    ];

    /**
     * Laravel auth events → audit action. Sign-in/out activity is core
     * audit signal and Statamic does not wrap these in its own events.
     *
     * @var array<class-string, string>
     */
    private const AUTH_EVENTS = [
        'Illuminate\\Auth\\Events\\Login' => 'statamic.user.login',
        'Illuminate\\Auth\\Events\\Logout' => 'statamic.user.logout',
        'Illuminate\\Auth\\Events\\Failed' => 'statamic.user.loginFailed',
        'Illuminate\\Auth\\Events\\PasswordReset' => 'statamic.user.passwordReset',
    ];

    /**
     * Subject keys for which a "created" row has already been recorded
     * this request. Never consumed — prevents any second creation row
     * for the same subject, whatever event produces it.
     *
     * A `*Created` event is the primary creation signal, but it is NOT
     * reliable: under statamic/eloquent-driver + structured collections,
     * EntryCreated can be skipped entirely (the driver's isNew check
     * misfires), and the entry's `getOriginal()` is permanently empty.
     * The fallback signal is the wrapped model's `wasRecentlyCreated`.
     *
     * @var array<string, true>
     */
    private static array $created = [];

    /**
     * Subject keys whose NEXT `*Saved` row must be suppressed because the
     * paired `*Created` for the same act was just recorded. Consumed on use.
     *
     * @var array<string, true>
     */
    private static array $suppressNextSaved = [];

    private const CREATED_CAP = 500;

    /**
     * Depth of entry saves/deletes currently in flight. Statamic nests
     * saves: the eloquent driver's order/parent jobs re-save an entry
     * INSIDE its own save, and multisite propagation saves localizations
     * inside the originating save.
     *
     * Used two ways:
     *  - depth ≥ 1 ⇒ tree writes are bookkeeping of an entry op, not acts;
     *  - an EntrySaved/EntryDeleted arriving at depth ≥ 2 ⇒ a nested
     *    side-effect save (order/uri/parent columns, localization copies),
     *    not a user act — one CP click yields one row.
     *
     * ponytail: a save aborted between Saving and Saved leaks +1 until the
     * next request resets the scope. Worst case: one suppressed row in
     * that same request.
     */
    private static int $entryOpDepth = 0;

    /**
     * Same idea for global sets: GlobalSet::save() saves its localizations
     * (GlobalVariablesSaved) INSIDE the set save, so creating/renaming a
     * set would add a stray "Globals edited" row. A standalone content
     * edit saves only the variables and is still recorded.
     */
    private static int $globalSetOpDepth = 0;

    /**
     * spl_object_id of the request the static state above belongs to.
     * Long-running workers (Octane, queue) reuse the process across
     * requests; comparing the current request instance lets us reset
     * per-request state without a terminate hook.
     */
    private static int $scopeId = -1;

    public function __construct(
        private AuditRecorder $recorder,
        private ChangeDetector $detector
    ) {
    }

    /**
     * Wire event listeners for every resolved, existing event class.
     */
    public function subscribe(): void
    {
        if (! (bool) config('logbook.audit_logs.enabled', true)) {
            return;
        }

        $excludedMap = array_fill_keys($this->excludedEventClasses(), true);

        foreach ($this->eventsToListen() as $eventClass) {
            if (! is_string($eventClass) || $eventClass === '') {
                continue;
            }
            if (isset($excludedMap[$eventClass])) {
                continue;
            }
            if (! class_exists($eventClass)) {
                continue;
            }

            Event::listen($eventClass, function ($event) use ($eventClass): void {
                // Audit logging must never break the host app. We introspect
                // arbitrary Statamic objects (get()/title()/model() may not
                // exist or may throw), so anything that goes wrong here is
                // swallowed rather than bubbled into the user's save.
                try {
                    $this->handle($eventClass, $event);
                } catch (\Throwable) {
                    // Intentionally silent — see above.
                }
            });
        }

        $this->subscribeEntryOpMarkers();
        $this->subscribeAuthEvents();

        // Queue workers keep one Request instance for the whole process, so
        // the spl_object_id-based scope check never fires there. Each job is
        // its own scope: without this, the created-set leaks across jobs and
        // a delete-then-recreate of the same handle in a later job would be
        // silently dropped.
        if (class_exists(\Illuminate\Queue\Events\JobProcessing::class)) {
            Event::listen(\Illuminate\Queue\Events\JobProcessing::class, static function (object $e): void {
                // Sync-queue jobs run INLINE inside the current operation
                // (the eloquent driver dispatches order/uri jobs mid-save);
                // resetting for those would wipe the suppression state in
                // the middle of the very save it protects.
                if (($e->connectionName ?? null) !== 'sync') {
                    self::$scopeId = -1;
                }
            });
        }
    }

    /**
     * Track how many entry saves/deletes are in flight so writes that
     * happen inside them (tree saves, nested bookkeeping re-saves) are
     * recognised as side effects, not user acts. Registered
     * unconditionally (outside the allow/exclude resolution) so the
     * counter stays balanced even when the user excludes entry events
     * from recording.
     *
     * Only `*Saving`/`*Deleting` increment (never `*Creating` — a new
     * entry fires Creating AND Saving, which would double-count the outer
     * save and make it look nested). Decrements are registered AFTER the
     * recording listeners, so at `EntrySaved` time the depth still
     * includes the finishing save's own frame.
     */
    private function subscribeEntryOpMarkers(): void
    {
        $inc = function (): void {
            $this->resetScopeIfNewRequest();
            self::$entryOpDepth = min(self::$entryOpDepth + 1, 50);
        };
        $dec = function (): void {
            $this->resetScopeIfNewRequest();
            self::$entryOpDepth = max(self::$entryOpDepth - 1, 0);
        };

        foreach (['Statamic\\Events\\EntrySaving', 'Statamic\\Events\\EntryDeleting'] as $class) {
            if (class_exists($class)) {
                Event::listen($class, $inc);
            }
        }
        foreach (['Statamic\\Events\\EntrySaved', 'Statamic\\Events\\EntryDeleted'] as $class) {
            if (class_exists($class)) {
                Event::listen($class, $dec);
            }
        }

        $gInc = function (): void {
            $this->resetScopeIfNewRequest();
            self::$globalSetOpDepth = min(self::$globalSetOpDepth + 1, 50);
        };
        $gDec = function (): void {
            $this->resetScopeIfNewRequest();
            self::$globalSetOpDepth = max(self::$globalSetOpDepth - 1, 0);
        };
        foreach (['Statamic\\Events\\GlobalSetSaving', 'Statamic\\Events\\GlobalSetDeleting'] as $class) {
            if (class_exists($class)) {
                Event::listen($class, $gInc);
            }
        }
        foreach (['Statamic\\Events\\GlobalSetSaved', 'Statamic\\Events\\GlobalSetDeleted'] as $class) {
            if (class_exists($class)) {
                Event::listen($class, $gDec);
            }
        }
    }

    /**
     * Record sign-in/out activity from Laravel's auth events.
     */
    private function subscribeAuthEvents(): void
    {
        if (! (bool) config('logbook.audit_logs.auth_events', true)) {
            return;
        }

        foreach (self::AUTH_EVENTS as $class => $action) {
            if (! class_exists($class)) {
                continue;
            }

            Event::listen($class, function ($event) use ($action): void {
                try {
                    $this->handleAuth($action, $event);
                } catch (\Throwable) {
                    // Never break authentication because auditing failed.
                }
            });
        }
    }

    private function handleAuth(string $action, object $event): void
    {
        $user = $event->user ?? null;

        $id = is_object($user)
            ? (method_exists($user, 'id') ? (string) $user->id() : (string) ($user->id ?? ''))
            : '';
        $email = is_object($user)
            ? (method_exists($user, 'email') ? (string) $user->email() : (string) ($user->email ?? ''))
            : '';

        // Failed logins carry no user object when the email is unknown —
        // record the attempted identifier instead.
        if ($email === '' && is_array($event->credentials ?? null)) {
            $email = (string) ($event->credentials['email'] ?? '');
        }

        $meta = ['guard' => (string) ($event->guard ?? '')];
        if (property_exists($event, 'remember')) {
            $meta['remember'] = (bool) $event->remember;
        }

        $this->record([
            'action' => $action,
            'subject_type' => 'user',
            'subject_id' => $id !== '' ? $id : null,
            'subject_handle' => $email !== '' ? $email : null,
            'subject_title' => $email !== '' ? $email : null,
            'changes' => null,
            'meta' => $meta,
        ]);
    }

    /**
     * Resolve the effective event allow-list for the running Statamic.
     *
     * Order:
     *   - curated defaults (unless `use_curated_defaults` is false)
     *   - user-configured additions
     *   - discovered events when `discover_events` is true
     *
     * @return list<string>
     */
    private function eventsToListen(): array
    {
        $useCurated = (bool) config('logbook.audit_logs.use_curated_defaults', true);

        $curated = $useCurated ? EventMap::curatedEvents() : [];

        $configured = array_values(array_filter(
            (array) config('logbook.audit_logs.events', []),
            static fn ($e): bool => is_string($e) && $e !== ''
        ));

        $discovered = (bool) config('logbook.audit_logs.discover_events', false)
            ? $this->discoverEvents()
            : [];

        return array_values(array_unique(array_merge($curated, $configured, $discovered)));
    }

    /**
     * Exclude list: EventMap's per-major exclude + user-configured extra.
     *
     * @return list<string>
     */
    private function excludedEventClasses(): array
    {
        $curated = EventMap::excludedEvents();

        $configured = array_values(array_filter(
            (array) config('logbook.audit_logs.exclude_events', []),
            static fn ($e): bool => is_string($e) && $e !== ''
        ));

        return array_values(array_unique(array_merge($curated, $configured)));
    }

    /**
     * Discover event classes by scanning the installed statamic/cms package.
     *
     * Only called when `logbook.audit_logs.discover_events` is true.
     *
     * @return list<string>
     */
    private function discoverEvents(): array
    {
        if (! function_exists('base_path')) {
            return [];
        }

        $vendorEventsDir = base_path('vendor/statamic/cms/src/Events');
        $events = [];

        if (! is_dir($vendorEventsDir)) {
            return [];
        }

        foreach (File::glob($vendorEventsDir . '/*.php') as $file) {
            $class = 'Statamic\\Events\\' . pathinfo($file, PATHINFO_FILENAME);
            if (class_exists($class)) {
                $events[] = $class;
            }
        }

        return array_values(array_unique($events));
    }

    private function handle(string $eventClass, object $event): void
    {
        $this->resetScopeIfNewRequest();

        $name = class_basename($eventClass);

        // `*Saving` / `*Creating` / `*Deleting` / `*Registering` fire
        // pre-flight, before the write is committed. Auditing them produces
        // a duplicate row for every real mutation, so in-progress events are
        // skipped even if a user opted into them via config. (No terminal
        // Statamic event ends in "ing".)
        if (str_ends_with($name, 'ing')) {
            return;
        }

        // Impersonation carries its own actor/subject pair and needs both
        // recorded explicitly: the event fires AFTER the guard has switched
        // to the impersonated user, so auth()->user() would name the victim
        // as the actor.
        if ($name === 'ImpersonationStarted' || $name === 'ImpersonationEnded') {
            $this->recordImpersonation($name, $event, $eventClass);

            return;
        }

        $subject = $this->inferSubject($event);
        $object = $subject['object'];

        // Events whose name carries no derivable verb get a fixed action.
        if (isset(self::FIXED_ACTIONS[$name])) {
            $this->write(self::FIXED_ACTIONS[$name], $subject, null, $eventClass, $object);

            return;
        }

        $operation = $this->operationFromEventClass($eventClass);
        $key = $subject['type'].':'.($subject['id'] ?? $subject['handle'] ?? spl_object_id($event));

        // Tree writes that happen inside an entry save/delete are that
        // operation's bookkeeping (Statamic appends to / prunes the
        // collection tree mid-save). The entry row is the act; a second
        // "Structure reordered" row for the same click is noise. Standalone
        // reorders save only the tree and still get recorded.
        if ($subject['type'] === 'tree' && self::$entryOpDepth > 0) {
            return;
        }

        // Same for global variables written inside a set save/delete.
        if ($subject['type'] === 'global_variables' && self::$globalSetOpDepth > 0) {
            return;
        }

        // An entry Saved/Deleted arriving while ANOTHER entry op is in
        // flight (depth ≥ 2: this save's own frame plus an enclosing one)
        // is a side-effect re-save — the eloquent driver's order/uri/parent
        // jobs, or multisite localization propagation. One click, one row.
        if ($subject['type'] === 'entry'
            && ($operation === 'updated' || $operation === 'deleted')
            && self::$entryOpDepth >= 2) {
            // If this nested Saved was the pair of a *Created recorded at
            // this depth, consume the flag now — leaving it armed would
            // swallow the entry's next legitimate save in this request.
            unset(self::$suppressNextSaved[$key]);

            return;
        }

        // `*Created` is the primary creation signal. Remember it so the
        // `*Saved` that Statamic dispatches immediately afterwards does not
        // add a second row for the same act.
        if ($operation === 'created') {
            // A second *Created for the same subject in one request is a
            // paired event, not a second creation.
            if (isset(self::$created[$key])) {
                return;
            }
            $this->rememberCreated($key, suppressNextSaved: true);
        } elseif ($operation === 'updated') {
            // The *Saved paired with a *Created we already recorded.
            if (isset(self::$suppressNextSaved[$key])) {
                unset(self::$suppressNextSaved[$key]);

                return;
            }
        }

        $changes = null;

        if ($operation === 'deleted' || $object === null) {
            // Nothing to diff.
        } elseif ($operation === 'created') {
            $changes = $this->createdChanges($this->afterState($object));
        } else {
            [$before, $after] = $this->beforeAfter($object);

            if ($before === null) {
                // No usable before-state (no dirty-state API, or an
                // eloquent-driver object whose original was never synced).
                // Record the act without inventing a diff, and say "saved"
                // rather than claiming an update we cannot confirm.
                if ($this->isSelfSave($subject)) {
                    return;
                }

                $operation = 'saved';
            } else {
                $changes = $this->detector->diff($before, $after);

                // A save that changed nothing is not worth a row.
                if ($changes === []) {
                    return;
                }

                $operation = $this->publishTransition($before, $after) ?? 'updated';
            }
        }

        $this->write(
            'statamic.'.$subject['type'].'.'.$operation,
            $subject,
            $changes,
            $eventClass,
            $object
        );
    }

    /**
     * Resolve the pre-save and post-save state of a subject.
     *
     * Two sources, in order:
     *
     *   1. Statamic's own dirty state. At `*Saved` dispatch time the object
     *      still holds its pre-save `original` (syncOriginal() runs after the
     *      event), so this is the true "before". Used by flat-file entries,
     *      terms, collections and assets.
     *   2. The underlying Eloquent model, for statamic/eloquent-driver
     *      objects — their Statamic `original` is never synced and stays
     *      empty, but the model tracks changes correctly.
     *
     * @return array{0: array<string,mixed>|null, 1: array<string,mixed>}
     */
    private function beforeAfter(object $object): array
    {
        if (method_exists($object, 'getOriginal') && method_exists($object, 'getCurrentDirtyStateAttributes')) {
            $original = (array) $object->getOriginal();

            if ($original !== []) {
                return [
                    $this->normalize($original),
                    $this->normalize($object->getCurrentDirtyStateAttributes()),
                ];
            }
        }

        $model = method_exists($object, 'model') ? $object->model() : null;

        // getChanges() holds what the last save actually wrote. Eloquent
        // re-syncs getOriginal() during save, so the pre-save values are gone
        // by the time we run — the field list is what we can honestly report.
        //
        // A NON-empty getChanges() means this model instance took part in the
        // save (Eloquent always records at least `updated_at`). A completely
        // empty one means it did not — some Statamic eloquent repositories
        // persist through a different instance than the one `model()` hands
        // back, and treating that as "nothing changed" silently swallowed
        // real edits (collection renames, most notably).
        if (is_object($model) && method_exists($model, 'getChanges')) {
            $changed = (array) $model->getChanges();

            if ($changed !== []) {
                unset($changed['updated_at']);

                // Only timestamps moved — not worth a row.
                if ($changed === []) {
                    return [[], []];
                }

                return [
                    $this->normalize(array_fill_keys(array_keys($changed), null)),
                    $this->normalize($changed),
                ];
            }
        }

        // ponytail: no before-state reachable, so we record the act with no
        // diff rather than inventing one. Costs us no-op suppression for
        // these subjects; revisit only if phantom rows actually show up.
        return [null, $this->afterState($object)];
    }

    /**
     * @return array<string, mixed>
     */
    private function afterState(object $object): array
    {
        if (method_exists($object, 'getCurrentDirtyStateAttributes')) {
            return $this->normalize($object->getCurrentDirtyStateAttributes());
        }

        $model = method_exists($object, 'model') ? $object->model() : null;
        if (is_object($model) && method_exists($model, 'attributesToArray')) {
            return $this->normalize((array) $model->attributesToArray());
        }

        return [];
    }

    private function rememberCreated(string $key, bool $suppressNextSaved): void
    {
        if (count(self::$created) < self::CREATED_CAP) {
            self::$created[$key] = true;
        }
        if ($suppressNextSaved) {
            self::$suppressNextSaved[$key] = true;
        }
    }

    /**
     * Reset per-request static state when the request instance changes.
     * In classic PHP-FPM the process dies per request and this never
     * fires; under Octane/queue workers it prevents the created-set and
     * the entry-op counter from leaking across requests.
     */
    private function resetScopeIfNewRequest(): void
    {
        $rid = (function_exists('request') && is_object($r = request())) ? spl_object_id($r) : 0;

        if ($rid !== self::$scopeId) {
            self::$scopeId = $rid;
            self::$created = [];
            self::$suppressNextSaved = [];
            self::$entryOpDepth = 0;
            self::$globalSetOpDepth = 0;
        }
    }

    /**
     * True when the signed-in user is saving their own user record and we
     * found nothing to report.
     *
     * Statamic re-saves the acting user as a side effect of ordinary work —
     * remember-me token rotation, CP preference writes, session activity —
     * which produced a contentless "User saved" row alongside unrelated
     * actions like creating an entry. A row with no diff, on yourself, says
     * nothing an auditor can use.
     *
     * Deliberately narrow: an admin saving *someone else's* user record is
     * still recorded even without a diff, because that is a real
     * administrative act. Only the self-save no-op is dropped.
     *
     * ponytail: this means a self-save whose only change is a role/group
     * pivot write (which never shows up in the model's getChanges()) goes
     * unrecorded. Listen for RoleSaved/UserGroupSaved to cover permission
     * changes, or drop `user` from the check if you need every save.
     *
     * @param  array{type:string, object:?object, id:?string, handle:?string, title:?string}  $subject
     */
    private function isSelfSave(array $subject): bool
    {
        if ($subject['type'] !== 'user' || $subject['id'] === null) {
            return false;
        }

        $actor = function_exists('auth') ? auth()->user() : null;

        return $actor !== null && (string) ($actor->id ?? '') === (string) $subject['id'];
    }

    /**
     * Detect a publish-state flip so the action reads "published" /
     * "unpublished" instead of a generic "updated".
     *
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     */
    private function publishTransition(array $before, array $after): ?string
    {
        if (! array_key_exists('published', $before) || ! array_key_exists('published', $after)) {
            return null;
        }
        if ($before['published'] === $after['published']) {
            return null;
        }

        return $after['published'] ? 'published' : 'unpublished';
    }

    /**
     * @param  array{type:string, object:?object, id:?string, handle:?string, title:?string}  $subject
     * @param  array<string, array{from: mixed, to: mixed}>|null  $changes
     */
    private function write(string $action, array $subject, ?array $changes, string $eventClass, ?object $object): void
    {
        $meta = [
            'raw_event' => class_basename($eventClass),
            'event_class' => $eventClass,
        ];

        // Entry-shaped subjects carry useful locating context.
        if ($object !== null && method_exists($object, 'collectionHandle')) {
            $meta['collection'] = $object->collectionHandle();
        }
        if ($object !== null && method_exists($object, 'site')) {
            $meta['site'] = is_object($object->site()) ? $object->site()?->handle() : null;
        }
        if ($object !== null && method_exists($object, 'uri')) {
            $meta['uri'] = $object->uri();
        }

        $this->record([
            'action' => $action,
            'subject_type' => $subject['type'],
            'subject_id' => $subject['id'],
            'subject_handle' => $subject['handle'],
            'subject_title' => $subject['title'],
            'changes' => $changes,
            'meta' => $meta,
        ]);
    }

    private function operationFromEventClass(string $eventClass): string
    {
        $name = class_basename($eventClass);
        if (str_ends_with($name, 'Deleted')) {
            return 'deleted';
        }
        if (str_ends_with($name, 'Created')) {
            return 'created';
        }
        if (str_ends_with($name, 'Saved')) {
            return 'updated';
        }

        return 'event';
    }

    /**
     * @return array{type:string, object:?object, id:?string, handle:?string, title:?string}
     */
    private function inferSubject(object $event): array
    {
        foreach (self::SUBJECT_PROPS as $prop => $type) {
            if (! property_exists($event, $prop)) {
                continue;
            }
            $obj = $event->$prop ?? null;
            if (! is_object($obj)) {
                continue;
            }

            $id = method_exists($obj, 'id')
                ? self::str($obj->id())
                : (method_exists($obj, 'handle') ? self::str($obj->handle()) : null);

            $handle = method_exists($obj, 'slug')
                ? self::str($obj->slug())
                : (method_exists($obj, 'handle') ? self::str($obj->handle()) : null);

            // self::str guards every cast: a malformed value (array title
            // from a bad import, a multi-value field named "title") must
            // blank the field, not throw and lose the whole row to the
            // listener's silent catch.
            $title = method_exists($obj, 'get')
                ? self::str($obj->get('title'))
                : '';
            if ($title === '' && method_exists($obj, 'title')) {
                $title = self::str($obj->title());
            }
            if ($title === '' && method_exists($obj, 'email')) {
                $title = self::str($obj->email());
            }

            return [
                'type' => $type,
                'object' => $obj,
                'id' => $id !== '' ? $id : null,
                'handle' => $handle !== '' ? $handle : null,
                'title' => $title !== '' ? $title : ($handle !== '' && $handle !== null ? $handle : null),
            ];
        }

        return ['type' => 'statamic', 'object' => null, 'id' => null, 'handle' => null, 'title' => null];
    }

    /**
     * Cast a value to string only when that cannot throw; everything
     * non-stringable becomes ''.
     */
    private static function str(mixed $v): string
    {
        if (is_string($v)) {
            return $v;
        }
        if (is_scalar($v) || $v instanceof \Stringable) {
            return (string) $v;
        }

        return '';
    }

    private function record(array $payload): void
    {
        $req = function_exists('request') ? request() : null;

        // An explicit actor (impersonation) wins over the auth guard.
        if (! array_key_exists('user_id', $payload)) {
            $user = function_exists('auth') ? auth()->user() : null;
            $payload['user_id'] = $user ? (string) ($user->id ?? null) : null;
            $payload['user_email'] = $user ? ($user->email ?? null) : null;
        }

        $payload['ip'] = $req?->ip();
        $payload['user_agent'] = $req?->userAgent();

        $this->recorder->record($payload);
    }

    /**
     * Impersonation events fire after the guard already runs as the
     * impersonated user, so both parties come from the event itself:
     * the impersonator is the actor, the impersonated user the subject.
     */
    private function recordImpersonation(string $name, object $event, string $eventClass): void
    {
        $who = function (?object $u): array {
            if ($u === null) {
                return ['id' => null, 'email' => null];
            }
            $id = method_exists($u, 'id') ? (string) $u->id() : (string) ($u->id ?? '');
            $email = method_exists($u, 'email') ? (string) $u->email() : (string) ($u->email ?? '');

            return ['id' => $id !== '' ? $id : null, 'email' => $email !== '' ? $email : null];
        };

        $actor = $who(is_object($event->impersonator ?? null) ? $event->impersonator : null);
        $target = $who(is_object($event->impersonated ?? null) ? $event->impersonated : null);

        $this->record([
            'action' => self::FIXED_ACTIONS[$name],
            'subject_type' => 'user',
            'subject_id' => $target['id'],
            'subject_handle' => $target['email'],
            'subject_title' => $target['email'],
            'changes' => null,
            'meta' => [
                'raw_event' => $name,
                'event_class' => $eventClass,
                'impersonator' => $actor['email'],
                'impersonated' => $target['email'],
            ],
            'user_id' => $actor['id'],
            'user_email' => $actor['email'],
        ]);
    }

    private function normalize(array $data): array
    {
        // PARTIAL_OUTPUT_ON_ERROR keeps a field with an unencodable value
        // (a resource, a recursive reference) from nuking the whole
        // snapshot — losing one field beats losing the entire diff.
        $encoded = json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR
        );
        if (! is_string($encoded)) {
            return [];
        }
        $decoded = json_decode($encoded, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @param  array<string, mixed>  $after
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function createdChanges(array $after): array
    {
        // Same field filter as ChangeDetector::diff — timestamps and other
        // bookkeeping columns are no more interesting on creation.
        $ignore = array_fill_keys((array) config('logbook.audit_logs.ignore_fields', []), true);

        $changes = [];
        foreach ($after as $k => $v) {
            if (isset($ignore[$k])) {
                continue;
            }
            $changes[$k] = ['from' => null, 'to' => $v];
        }

        return $changes;
    }
}
