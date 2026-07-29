<?php

declare(strict_types=1);

namespace EmranAlhaddad\StatamicLogbook\Tests\Unit;

use EmranAlhaddad\StatamicLogbook\Audit\AuditRecorder;
use EmranAlhaddad\StatamicLogbook\Audit\ChangeDetector;
use EmranAlhaddad\StatamicLogbook\Audit\StatamicAuditSubscriber;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StatamicAuditSubscriberTest extends TestCase
{
    #[DataProvider('operationCases')]
    public function test_it_normalizes_event_class_to_operation(string $eventClass, string $expected): void
    {
        $subscriber = new StatamicAuditSubscriber(
            recorder: $this->createMock(AuditRecorder::class),
            detector: $this->createMock(ChangeDetector::class)
        );

        $method = new \ReflectionMethod($subscriber, 'operationFromEventClass');
        $method->setAccessible(true);

        $result = $method->invoke($subscriber, $eventClass);
        $this->assertSame($expected, $result);
    }

    public static function operationCases(): array
    {
        return [
            ['Statamic\\Events\\UserCreated', 'created'],
            ['Statamic\\Events\\UserDeleted', 'deleted'],
            ['Statamic\\Events\\UserSaved', 'updated'],
            ['Statamic\\Events\\UserBlueprintFound', 'event'],
        ];
    }
}
