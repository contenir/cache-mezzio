<?php

declare(strict_types=1);

namespace Contenir\Cache\Mezzio\Tests\Unit;

use Contenir\Cache\Mezzio\SessionInspector;
use Contenir\Cache\Mezzio\Tests\TestAsset\Session\DataSession;
use Contenir\Cache\Mezzio\Tests\TestAsset\Session\FakeSession;
use Contenir\Cache\Mezzio\Tests\Trait\ServerRequestTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('session')]
final class SessionInspectorTest extends TestCase
{
    use ServerRequestTrait;

    /**
     * @return array<string, array{array<string, mixed>, bool}>
     */
    public static function userProvider(): array
    {
        return [
            'no session'                  => [[], false],
            'session object with user'    => [['session' => new FakeSession(['user_id' => 7])], true],
            'session object without user' => [['session' => new FakeSession(['cart' => 1])], false],
            'session object answers has'  => [['session' => new FakeSession(['user_id' => null])], true],
            'toArray object with user'    => [['session' => new DataSession(['user_id' => 7])], true],
            'array with user'             => [['session' => ['user_id' => 7]], true],
            'array with a null user'      => [['session' => ['user_id' => null]], false],
        ];
    }

    /**
     * @return array<string, array{array<string, mixed>, array<string, string>, array<array-key, mixed>}>
     */
    public static function valuesProvider(): array
    {
        return [
            'no session'                    => [[], [], []],
            'array attribute'               => [['session' => ['cart' => 2]], [], ['cart' => 2]],
            'every session value'           => [
                ['session' => ['cart' => 2, 'lang' => 'en']],
                [],
                ['cart' => 2, 'lang' => 'en'],
            ],
            'session object'                => [['session' => new FakeSession(['cart' => 3])], [], ['cart' => 3]],
            'object exposing toArray'       => [['session' => new DataSession(['cart' => 4])], [], ['cart' => 4]],
            'cookie only'                   => [[], ['SID' => 'abc'], ['id' => 'abc']],
            'empty session falls to cookie' => [['session' => new FakeSession()], ['SID' => 'abc'], ['id' => 'abc']],
            'empty cookie is no session'    => [[], ['SID' => ''], []],
            'another cookie is no session'  => [[], ['PHPSESSID' => 'abc'], []],
            'unusable attribute'            => [['session' => 'not a session'], [], []],
        ];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    #[Test]
    #[DataProvider('userProvider')]
    public function detectsALoggedInUser(array $attributes, bool $expected): void
    {
        static::assertSame($expected, (new SessionInspector())->hasUser($this->request(attributes: $attributes)));
    }

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, string> $cookies
     * @param array<array-key, mixed> $expected
     */
    #[Test]
    #[DataProvider('valuesProvider')]
    public function identifiesTheVisitorsSession(array $attributes, array $cookies, array $expected): void
    {
        $request = $this->request(
            cookies: $cookies,
            attributes: $attributes,
        );

        static::assertSame($expected, (new SessionInspector('SID'))->values($request));
    }
}
