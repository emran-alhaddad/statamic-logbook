<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector;
use EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

/**
 * Covers the create/update/publish classification in
 * {@see StatamicAuditSubscriber::handle()} — the logic that decides whether
 * a save is recorded as `statamic.entry.created`, `.updated`, `.published`
 * or `.unpublished`.
 *
 * Fakes mirror Statamic's real shape: at `EntrySaved` dispatch time the
 * entry still carries its pre-save `original` (Entry::save() calls
 * syncOriginal() *after* the event), which is what we diff against.
 */
final class StatamicAuditSubscriberHandleTest extends TestCase
{
    protected function setUp(): void
    {
        $container = new Container;
        $container->instance('config', new Repository(['logbook' => ['audit_logs' => [
            // Mirrors the shipped config default.
            'ignore_fields' => ['updated_at', 'created_at', 'date', 'uri', 'slug', 'remember_token', 'password', 'last_login'],
            'max_value_length' => 2000,
        ]]]));
        // The subscriber decorates every row with actor context via
        // auth()/request(); bind just enough for those helpers to resolve.
        $container->instance(\Illuminate\Contracts\Auth\Factory::class, new class implements \Illuminate\Contracts\Auth\Guard
        {
            public function check(): bool
            {
                return false;
            }

            public function guest(): bool
            {
                return true;
            }

            public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
            {
                return null;
            }

            public function id()
            {
                return null;
            }

            public function validate(array $credentials = []): bool
            {
                return false;
            }

            public function hasUser(): bool
            {
                return false;
            }

            public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
            {
            }
        });
        $container->instance('request', \Illuminate\Http\Request::create('/cp'));
        Container::setInstance($container);
    }

    protected function tearDown(): void
    {
        Container::setInstance(null);
    }

    public function test_saving_an_existing_entry_records_an_update_with_a_real_diff(): void
    {
        $rows = $this->dispatch(
            'Statamic\\Events\\EntrySaved',
            $this->entry(
                original: ['slug' => 'hello', 'published' => true, 'title' => 'Hello'],
                current: ['slug' => 'hello', 'published' => true, 'title' => 'Hello world'],
            )
        );

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.entry.updated', $rows[0]['action']);
        $this->assertSame(['title' => ['from' => 'Hello', 'to' => 'Hello world']], $rows[0]['changes']);
    }

    public function test_entry_created_records_a_creation_and_suppresses_the_following_save(): void
    {
        // Statamic dispatches EntryCreated then EntrySaved for a new entry.
        // That must yield exactly one row, labelled "created".
        $event = $this->entry(original: [], current: ['slug' => 'new', 'published' => false]);

        $rows = $this->dispatch('Statamic\\Events\\EntryCreated', $event, reset: true);
        $rows = array_merge($rows, $this->dispatch('Statamic\\Events\\EntrySaved', $event, reset: false));

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.entry.created', $rows[0]['action']);
    }

    /**
     * Regression: statamic/eloquent-driver objects never call syncOriginal(),
     * so their getOriginal() is permanently empty. Treating that as "this is
     * new" logged "User created" on every single save.
     */
    public function test_an_empty_original_is_not_mistaken_for_a_creation(): void
    {
        $rows = $this->dispatch(
            'Statamic\\Events\\UserSaved',
            $this->userWithModelChanges(['email' => 'new@example.com'])
        );

        $this->assertCount(1, $rows);
        $this->assertNotSame('statamic.user.created', $rows[0]['action']);
        $this->assertSame('statamic.user.updated', $rows[0]['action']);
        $this->assertSame(['email'], array_keys($rows[0]['changes']));
    }

    public function test_a_timestamp_only_save_is_not_recorded(): void
    {
        $rows = $this->dispatch(
            'Statamic\\Events\\UserSaved',
            $this->userWithModelChanges(['updated_at' => '2026-07-26 00:00:00'])
        );

        $this->assertSame([], $rows);
    }

    /**
     * When the model reports no changes at all it did not take part in the
     * save — some Statamic eloquent repositories persist through a different
     * instance than model() returns. We must record the act (with no diff)
     * rather than swallow it; treating this as "nothing changed" dropped real
     * collection renames entirely.
     */
    public function test_an_untracked_model_still_records_the_act_without_a_diff(): void
    {
        $rows = $this->dispatch('Statamic\\Events\\UserSaved', $this->userWithModelChanges([]));

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.user.saved', $rows[0]['action']);
        $this->assertNull($rows[0]['changes']);
    }

    /**
     * Regression: Statamic re-saves the acting user as a side effect of
     * ordinary work (remember-me rotation, CP preference writes), which
     * tagged a contentless "User saved" row onto unrelated actions such as
     * creating an entry.
     */
    public function test_a_contentless_self_save_is_not_recorded(): void
    {
        $this->actAs('user-1');

        $rows = $this->dispatch('Statamic\\Events\\UserSaved', $this->userWithModelChanges([]));

        $this->assertSame([], $rows);
    }

    public function test_saving_a_different_user_is_still_recorded(): void
    {
        $this->actAs('some-admin');

        $rows = $this->dispatch('Statamic\\Events\\UserSaved', $this->userWithModelChanges([]));

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.user.saved', $rows[0]['action']);
    }

    /**
     * A self-save that genuinely changed something is still audited — only
     * the contentless ones are dropped.
     */
    public function test_a_self_save_with_real_changes_is_recorded(): void
    {
        $this->actAs('user-1');

        $rows = $this->dispatch(
            'Statamic\\Events\\UserSaved',
            $this->userWithModelChanges(['email' => 'changed@example.com'])
        );

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.user.updated', $rows[0]['action']);
    }

    /**
     * Regression: Statamic saves the collection tree INSIDE an entry
     * save/delete (append-on-create, prune-on-delete), so creating a page
     * in a structured collection produced an extra "Structure reordered"
     * row for the same click.
     */
    public function test_a_tree_save_inside_an_entry_operation_is_suppressed(): void
    {
        $tree = new class(new class
        {
            public function handle(): string
            {
                return 'pages';
            }
        }) {
            public function __construct(public object $tree)
            {
            }
        };

        // Depth > 0 == an entry save/delete is in flight.
        $rows = $this->dispatch('Statamic\\Events\\CollectionTreeSaved', $tree, reset: true, depth: 1);
        $this->assertSame([], $rows);

        // A standalone reorder (depth 0) is still recorded.
        $rows = $this->dispatch('Statamic\\Events\\CollectionTreeSaved', $tree, reset: true, depth: 0);
        $this->assertCount(1, $rows);
        $this->assertSame('statamic.tree.saved', $rows[0]['action']);
    }

    /**
     * Regression (eloquent driver + structured collections): the driver's
     * order/parent jobs re-save an entry INSIDE its own save, producing a
     * stray "Entry saved" row before the real "Entry created".
     */
    public function test_a_nested_entry_save_is_suppressed(): void
    {
        $event = $this->entry(
            original: ['slug' => 'a', 'published' => true, 'title' => 'A'],
            current: ['slug' => 'a', 'published' => true, 'title' => 'B'],
        );

        // Depth 2 == this save is running inside another entry op.
        $rows = $this->dispatch('Statamic\\Events\\EntrySaved', $event, reset: true, depth: 2);
        $this->assertSame([], $rows);

        // Depth 1 == this IS the user's save.
        $rows = $this->dispatch('Statamic\\Events\\EntrySaved', $event, reset: true, depth: 1);
        $this->assertCount(1, $rows);
        $this->assertSame('statamic.entry.updated', $rows[0]['action']);
    }

    /**
     * Regression: impersonation events fire after the guard already runs as
     * the impersonated user, so the actor must come from the event's
     * impersonator — not auth() — and the subject must be the impersonated
     * user, not a subject-less 'statamic' row.
     */
    public function test_impersonation_records_impersonator_as_actor_and_victim_as_subject(): void
    {
        $mkUser = fn (string $id, string $email) => new class($id, $email)
        {
            public function __construct(private string $id, private string $email)
            {
            }

            public function id(): string
            {
                return $this->id;
            }

            public function email(): string
            {
                return $this->email;
            }
        };

        $event = new class($mkUser('admin-1', 'admin@example.com'), $mkUser('victim-2', 'victim@example.com'))
        {
            public function __construct(public object $impersonator, public object $impersonated)
            {
            }
        };

        $rows = $this->dispatch('Statamic\\Events\\ImpersonationStarted', $event);

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.user.impersonated', $rows[0]['action']);
        $this->assertSame('user', $rows[0]['subject_type']);
        $this->assertSame('victim-2', $rows[0]['subject_id']);
        $this->assertSame('admin-1', $rows[0]['user_id']);
        $this->assertSame('admin@example.com', $rows[0]['user_email']);
    }

    /**
     * Regression: TwoFactorAuthenticationFailed carries no save at all, so
     * the generic path fabricated a phantom "User saved" row. It must map
     * to its own action.
     */
    public function test_failed_two_factor_maps_to_its_own_action(): void
    {
        $user = new class
        {
            public function id(): string
            {
                return 'u-1';
            }

            public function email(): string
            {
                return 'someone@example.com';
            }
        };

        $event = new class($user) {
            public function __construct(public object $user)
            {
            }
        };

        $rows = $this->dispatch('Statamic\\Events\\TwoFactorAuthenticationFailed', $event);

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.user.twoFactorFailed', $rows[0]['action']);
    }

    public function test_a_non_stringable_title_blanks_the_title_instead_of_losing_the_row(): void
    {
        $entry = new class
        {
            public function id(): string
            {
                return 'entry-arr';
            }

            public function slug(): string
            {
                return 'weird';
            }

            public function get(string $key, $fallback = null)
            {
                // Malformed data: title is an array.
                return $key === 'title' ? ['not', 'a', 'string'] : $fallback;
            }
        };

        $event = new class($entry) {
            public function __construct(public object $entry)
            {
            }
        };

        $rows = $this->dispatch('Statamic\\Events\\EntryDeleted', $event);

        $this->assertCount(1, $rows);
        $this->assertSame('statamic.entry.deleted', $rows[0]['action']);
        $this->assertSame('weird', $rows[0]['subject_title']);
    }

    public function test_auth_events_record_sign_in_activity(): void
    {
        $subscriber = $this->newSubscriberCapturing($rows);

        $handleAuth = new \ReflectionMethod($subscriber, 'handleAuth');
        $handleAuth->setAccessible(true);

        // Failed attempt with an unknown email: no user object, only credentials.
        $failed = new class
        {
            public $user = null;

            public array $credentials = ['email' => 'attacker@example.com', 'password' => 'x'];

            public string $guard = 'web';
        };
        $handleAuth->invoke($subscriber, 'statamic.user.loginFailed', $failed);

        $this->assertSame('statamic.user.loginFailed', $rows[0]['action']);
        $this->assertSame('attacker@example.com', $rows[0]['subject_handle']);
        $this->assertNull($rows[0]['subject_id']);
    }

    /**
     * @param  list<array<string,mixed>>|null  $rows
     */
    private function newSubscriberCapturing(?array &$rows): StatamicAuditSubscriber
    {
        $rows = [];

        $recorder = new class($rows) extends AuditRecorder
        {
            /** @param list<array<string,mixed>> $rows */
            public function __construct(public array &$rows)
            {
            }

            public function record(array $payload): void
            {
                $this->rows[] = $payload;
            }
        };

        return new StatamicAuditSubscriber($recorder, new ChangeDetector);
    }

    /** Bind an authenticated actor with the given id. */
    private function actAs(string $id): void
    {
        Container::getInstance()->instance(
            \Illuminate\Contracts\Auth\Factory::class,
            new class($id) implements \Illuminate\Contracts\Auth\Guard
            {
                public function __construct(private string $id)
                {
                }

                public function check(): bool
                {
                    return true;
                }

                public function guest(): bool
                {
                    return false;
                }

                public function user(): ?\Illuminate\Contracts\Auth\Authenticatable
                {
                    return new class($this->id) implements \Illuminate\Contracts\Auth\Authenticatable
                    {
                        public function __construct(public string $id)
                        {
                        }

                        public function getAuthIdentifierName()
                        {
                            return 'id';
                        }

                        public function getAuthIdentifier()
                        {
                            return $this->id;
                        }

                        public function getAuthPasswordName()
                        {
                            return 'password';
                        }

                        public function getAuthPassword()
                        {
                            return '';
                        }

                        public function getRememberToken()
                        {
                            return null;
                        }

                        public function setRememberToken($value): void
                        {
                        }

                        public function getRememberTokenName()
                        {
                            return 'remember_token';
                        }
                    };
                }

                public function id()
                {
                    return $this->id;
                }

                public function validate(array $credentials = []): bool
                {
                    return true;
                }

                public function hasUser(): bool
                {
                    return true;
                }

                public function setUser(\Illuminate\Contracts\Auth\Authenticatable $user): void
                {
                }
            }
        );
    }

    public function test_remember_token_churn_is_not_recorded(): void
    {
        // Statamic's UserProvider re-saves the user whenever Laravel rotates
        // the remember-me token — that must not produce an audit row.
        $rows = $this->dispatch(
            'Statamic\\Events\\UserSaved',
            $this->userWithModelChanges(['remember_token' => 'abc123'])
        );

        $this->assertSame([], $rows);
    }

    public function test_publish_and_draft_transitions_get_their_own_actions(): void
    {
        $published = $this->dispatch('Statamic\\Events\\EntrySaved', $this->entry(
            original: ['slug' => 'a', 'published' => false],
            current: ['slug' => 'a', 'published' => true],
        ));
        $this->assertSame('statamic.entry.published', $published[0]['action']);

        $drafted = $this->dispatch('Statamic\\Events\\EntrySaved', $this->entry(
            original: ['slug' => 'a', 'published' => true],
            current: ['slug' => 'a', 'published' => false],
        ));
        $this->assertSame('statamic.entry.unpublished', $drafted[0]['action']);
    }

    public function test_a_save_that_changed_nothing_is_not_recorded(): void
    {
        $rows = $this->dispatch('Statamic\\Events\\EntrySaved', $this->entry(
            original: ['slug' => 'a', 'published' => true],
            current: ['slug' => 'a', 'published' => true],
        ));

        $this->assertSame([], $rows);
    }

    public function test_deletion_records_without_a_diff(): void
    {
        $rows = $this->dispatch('Statamic\\Events\\EntryDeleted', $this->entry(
            original: ['slug' => 'a', 'published' => true],
            current: ['slug' => 'a', 'published' => true],
        ));

        $this->assertSame('statamic.entry.deleted', $rows[0]['action']);
        $this->assertNull($rows[0]['changes']);
    }

    public function test_saving_events_are_ignored(): void
    {
        $rows = $this->dispatch('Statamic\\Events\\EntrySaving', $this->entry(
            original: ['slug' => 'a'],
            current: ['slug' => 'b'],
        ));

        $this->assertSame([], $rows);
    }

    public function test_verbless_security_events_get_readable_actions(): void
    {
        $event = new class
        {
            public function __construct(public ?object $user = null)
            {
            }
        };

        $rows = $this->dispatch('Statamic\\Events\\UserPasswordChanged', $event);
        $this->assertSame('statamic.user.passwordChanged', $rows[0]['action']);

        $rows = $this->dispatch('Statamic\\Events\\TwoFactorAuthenticationEnabled', $event);
        $this->assertSame('statamic.user.twoFactorEnabled', $rows[0]['action']);
    }

    public function test_roles_and_user_groups_are_not_flattened_to_statamic(): void
    {
        $role = new class
        {
            public function handle(): string
            {
                return 'editor';
            }

            public function title(): string
            {
                return 'Editor';
            }
        };

        $event = new class($role) {
            public function __construct(public object $role)
            {
            }
        };

        $rows = $this->dispatch('Statamic\\Events\\RoleSaved', $event);
        $this->assertSame('statamic.role.saved', $rows[0]['action']);
        $this->assertSame('role', $rows[0]['subject_type']);
        $this->assertSame('Editor', $rows[0]['subject_title']);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function dispatch(string $eventClass, object $event, bool $reset = true, int $depth = 0): array
    {
        if ($reset) {
            // Align the subscriber's request-scope marker with the current
            // test request so handle()'s per-request reset does not clobber
            // the state we inject here.
            $req = Container::getInstance()->make('request');

            foreach ([
                'created' => [],
                'suppressNextSaved' => [],
                'entryOpDepth' => $depth,
                'scopeId' => spl_object_id($req),
            ] as $name => $value) {
                $prop = new \ReflectionProperty(StatamicAuditSubscriber::class, $name);
                $prop->setAccessible(true);
                $prop->setValue(null, $value);
            }
        }

        $rows = [];

        $recorder = new class($rows) extends AuditRecorder
        {
            /** @param list<array<string,mixed>> $rows */
            public function __construct(public array &$rows)
            {
            }

            public function record(array $payload): void
            {
                $this->rows[] = $payload;
            }
        };

        $subscriber = new StatamicAuditSubscriber($recorder, new ChangeDetector);

        $handle = new \ReflectionMethod($subscriber, 'handle');
        $handle->setAccessible(true);
        $handle->invoke($subscriber, $eventClass, $event);

        return $rows;
    }

    /**
     * Fake user in the statamic/eloquent-driver shape: HasDirtyState methods
     * exist but `original` is never synced, so the real state lives on the
     * wrapped Eloquent model.
     *
     * @param  array<string, mixed>  $modelChanges  what the last save wrote
     */
    private function userWithModelChanges(array $modelChanges): object
    {
        $user = new class($modelChanges)
        {
            /** @param array<string, mixed> $changes */
            public function __construct(private array $changes)
            {
            }

            public function getOriginal($key = null, $fallback = null)
            {
                return $key === null ? [] : $fallback;
            }

            /** @return array<string, mixed> */
            public function getCurrentDirtyStateAttributes(): array
            {
                return [];
            }

            public function model(): object
            {
                return new class($this->changes)
                {
                    /** @param array<string, mixed> $changes */
                    public function __construct(private array $changes)
                    {
                    }

                    /** @return array<string, mixed> */
                    public function getChanges(): array
                    {
                        return $this->changes;
                    }
                };
            }

            public function id(): string
            {
                return 'user-1';
            }

            public function email(): string
            {
                return 'someone@example.com';
            }
        };

        return new class($user) {
            public function __construct(public object $user)
            {
            }
        };
    }

    /**
     * Fake entry with Statamic's HasDirtyState surface.
     *
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $current
     */
    private function entry(array $original, array $current): object
    {
        $entry = new class($original, $current)
        {
            /**
             * @param  array<string, mixed>  $original
             * @param  array<string, mixed>  $current
             */
            public function __construct(private array $original, private array $current)
            {
            }

            public function getOriginal($key = null, $fallback = null)
            {
                return $key === null ? $this->original : ($this->original[$key] ?? $fallback);
            }

            /** @return array<string, mixed> */
            public function getCurrentDirtyStateAttributes(): array
            {
                return $this->current;
            }

            public function id(): string
            {
                return 'entry-1';
            }

            public function slug(): string
            {
                return (string) ($this->current['slug'] ?? '');
            }

            public function get(string $key, $fallback = null)
            {
                return $this->current[$key] ?? $fallback;
            }
        };

        return new class($entry) {
            public function __construct(public object $entry)
            {
            }
        };
    }
}
