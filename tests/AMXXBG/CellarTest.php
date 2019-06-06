<?php

namespace AMXXBG\Tests;

use AMXXBG\Cellar;
use PHPUnit\Framework\TestCase;

class CellarTest extends TestCase
{
    const SECRET = 'our-secret';

    protected $cellar;

    protected function setUp()
    {
        $this->cellar = new Cellar(self::SECRET);
    }

    public function test_it_is_initializable()
    {
        $this->assertInstanceOf(Cellar::class, $this->cellar);
    }

    public function test_it_generates_token_strings_in_expected_format()
    {
        $this->assertRegExp('/^[A-Za-z0-9+\/]{16}-[0-9]+-[A-Za-z0-9+\/]{40}$/', $this->cellar->generate());
    }

    public function test_it_generates_new_random_tokens_each_time()
    {
        $tokenString = $this->cellar->generate();

        $this->assertNotEquals($tokenString, $this->cellar->generate());
    }

    public function test_it_generates_tokens_with_default_expiry_if_no_expiry_passed()
    {
        $expiryToken = new Cellar(self::SECRET, ['lifetime' => 2]);
        $tokenString = $expiryToken->generate();

        $this->assertFalse($expiryToken->hasExpired($tokenString));
    }

    public function test_it_validates_token_within_expiry_time()
    {
        $tokenString = $this->cellar->generate(2);

        $this->assertTrue($this->cellar->isValid($tokenString));
    }

    public function test_it_does_not_validate_token_after_expiry_time()
    {
        $tokenString = $this->cellar->generate(0);

        $this->assertFalse($this->cellar->isValid($tokenString));
    }

    public function test_it_does_not_validate_tampered_token()
    {
        $tokenString = $this->cellar->generate(3600);
        $tokenString[\rand(0, \strlen($tokenString) - 1)] = '~';

        $this->assertFalse($this->cellar->isValid($tokenString));
    }

    public function test_it_does_not_validate_token_signed_with_different_secret()
    {
        $otherToken = new Cellar('first-secret');
        $otherTokenString = $otherToken->generate(2);

        $this->assertFalse($this->cellar->isValid($otherTokenString));
        $this->assertTrue($this->cellar->hasTampered($otherTokenString));
    }

    public function oldSecretDataProvider()
    {
        return [
            ['retired-not-valid', [], false],
            [null, [], true],
            ['oldest-secret', [], true],
            ['older-secret', [], true],
            ['new-secret', [
                'old_secrets' => [
                    null,
                    'oldest-secret',
                    'older-secret'
                ]
            ], true],
        ];
    }

    /**
     * @dataProvider oldSecretDataProvider
     */
    public function test_it_optionally_validates_token_signed_with_old_secret_during_rotation($secretString, $option, $expectedValue)
    {
        $cellar = new Cellar($secretString, $option);
        $newCellar = new Cellar('new-secret', [
            'old_secrets' => [
                null,
                'oldest-secret',
                'older-secret'
            ]
        ]);

        $this->assertSame($expectedValue, $newCellar->isValid($cellar->generate(10)));
    }

    public function test_it_handles_invalid_token_format_without_error()
    {
        $this->assertFalse($this->cellar->isValid('some random string'));
    }

    public function test_it_can_check_if_token_is_expired()
    {
        $this->assertFalse($this->cellar->hasExpired($this->cellar->generate(2)));
        $this->assertTrue($this->cellar->hasExpired($this->cellar->generate(0)));
    }

    public function test_it_can_check_if_token_is_tampered()
    {
        $this->assertFalse($this->cellar->hasTampered($this->cellar->generate()));
        $this->assertTrue($this->cellar->hasTampered('some random string'));
    }

    public function test_it_validates_token_signed_with_additional_parameters()
    {
        $tokenString = $this->cellar->generate(3600, ['email' => 'test@123.456.com']);

        $this->assertTrue($this->cellar->isValid($tokenString, ['email' => 'test@123.456.com']));
        $this->assertFalse($this->cellar->hasTampered($tokenString, ['email' => 'test@123.456.com']));
    }

    public function test_it_does_not_validate_token_signed_with_additional_parameters_if_not_provided_to_verify()
    {
        $token = $this->cellar->generate(3600, ['email' => 'test@123.456.com']);

        $this->assertFalse($this->cellar->isValid($token));
        $this->assertTrue ($this->cellar->hasTampered($token));
    }

    public function test_it_does_not_validate_token_signed_with_additional_parameters_if_tampered()
    {
        $token = $this->cellar->generate(3600, ['email' => 'test@123.456.com']);

        $this->assertFalse($this->cellar->isValid($token, ['email' => 'bad@bad.com']));
        $this->assertTrue($this->cellar->hasTampered($token, ['email' => 'bad@bad.com']));
    }

    public function test_it_does_not_validate_token_that_had_no_additional_parameters_if_tampered()
    {
        $token = $this->cellar->generate(3600);

        $this->assertFalse($this->cellar->isValid($token, ['email' => 'bad@bad.com']));
        $this->assertTrue($this->cellar->hasTampered($token, ['email' => 'bad@bad.com']));
    }

    public function test_it_validates_token_with_out_of_sequence_additional_parameters()
    {
        $token = $this->cellar->generate(
            3600,
            ['email' => 'test@123.456.com', 'stuff' => 'whatever']
        );

        $this->assertTrue($this->cellar->isValid($token, ['stuff' => 'whatever', 'email' => 'test@123.456.com']));
        $this->assertFalse($this->cellar->hasTampered($token, ['stuff' => 'whatever', 'email' => 'test@123.456.com']));
    }

}
