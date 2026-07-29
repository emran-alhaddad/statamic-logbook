<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Http\Middleware\LogCpPageViews;
use EmranAlhaddad\StatamicLogbook\Support\AuditActionPresenter;
use EmranAlhaddad\StatamicLogbook\Support\SettingsRepository;
use PHPUnit\Framework\TestCase;

/**
 * The component palette is shared by the settings tiles and the audit
 * listing so a component is the same colour in both. These tests fail if
 * the two ever drift apart.
 */
final class ComponentPaletteTest extends TestCase
{
    public function test_every_settings_event_group_has_a_colour_and_icon(): void
    {
        foreach (array_keys(SettingsRepository::groupLabels()) as $group) {
            $this->assertArrayHasKey(
                $group,
                AuditActionPresenter::GROUP_COMPONENTS,
                "Event group '{$group}' has no entry in the shared component palette"
            );
        }
    }

    public function test_every_tracked_page_has_a_colour_and_icon(): void
    {
        foreach (array_keys(SettingsRepository::VIEW_PAGES) as $page) {
            $this->assertArrayHasKey(
                $page,
                AuditActionPresenter::GROUP_COMPONENTS,
                "Tracked page '{$page}' has no entry in the shared component palette"
            );
        }
    }

    public function test_every_page_view_subject_type_resolves_to_a_component(): void
    {
        foreach (LogCpPageViews::ROUTES as $route => [$type, , ]) {
            $cmp = AuditActionPresenter::component($type);

            $this->assertNotNull($cmp['group'], "Subject type '{$type}' (from {$route}) falls back to the neutral component");
        }
    }

    public function test_subject_groups_only_point_at_known_components(): void
    {
        foreach (AuditActionPresenter::SUBJECT_GROUPS as $type => $group) {
            $this->assertArrayHasKey(
                $group,
                AuditActionPresenter::GROUP_COMPONENTS,
                "Subject type '{$type}' maps to unknown component '{$group}'"
            );
        }
    }

    public function test_an_unknown_subject_type_gets_the_neutral_component(): void
    {
        $cmp = AuditActionPresenter::component('something_new');

        $this->assertNull($cmp['group']);
        $this->assertSame('zinc', $cmp['tint']);
        $this->assertNotSame('', $cmp['icon']);
    }

    /**
     * Log levels and mutation verbs must not share a chip variant — an
     * `info` log rendering in the amber "update" style was a real bug.
     */
    public function test_log_level_and_mutation_verbs_use_distinct_variants(): void
    {
        $this->assertSame('delete', AuditActionPresenter::variant('statamic.entry.deleted'));
        $this->assertSame('create', AuditActionPresenter::variant('statamic.entry.created'));
        $this->assertSame('update', AuditActionPresenter::variant('statamic.entry.updated'));
        $this->assertSame('auth', AuditActionPresenter::variant('statamic.user.login'));
        $this->assertSame('view', AuditActionPresenter::variant('statamic.entry.viewed'));
    }

    public function test_failed_auth_attempts_are_flagged_not_coloured_like_a_success(): void
    {
        foreach (['statamic.user.loginFailed', 'statamic.user.twoFactorFailed'] as $action) {
            $this->assertSame('fail', AuditActionPresenter::variant($action), "{$action} should read as a failure");
        }

        // …while the successful counterparts stay on the calm auth colour.
        $this->assertSame('auth', AuditActionPresenter::variant('statamic.user.login'));
        $this->assertSame('auth', AuditActionPresenter::variant('statamic.user.twoFactorEnabled'));
    }

    /**
     * Colour alone is not an accessible signal — each verb must also carry a
     * distinct icon, so the badges stay distinguishable in greyscale.
     */
    public function test_each_verb_has_its_own_icon(): void
    {
        $icons = [];
        foreach ([
            'statamic.entry.created', 'statamic.entry.updated', 'statamic.entry.deleted',
            'statamic.user.login', 'statamic.user.loginFailed', 'statamic.entry.viewed',
        ] as $action) {
            $icon = AuditActionPresenter::icon($action);
            $this->assertNotSame('', $icon, "{$action} has no badge icon");
            $icons[] = $icon;
        }

        $this->assertSame($icons, array_unique($icons), 'Two different verbs share the same badge icon');
    }

    public function test_page_views_are_never_coloured_as_mutations(): void
    {
        foreach (LogCpPageViews::ROUTES as [$type, , ]) {
            $this->assertSame(
                'view',
                AuditActionPresenter::variant("statamic.{$type}.viewed"),
                "A page view for '{$type}' is coloured as a mutation"
            );
        }
    }
}
