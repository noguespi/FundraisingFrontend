<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Integration\Validation;

use WMDE\Fundraising\Entities\Address;
use WMDE\Fundraising\Entities\Subscription;
use WMDE\Fundraising\Frontend\Infrastructure\NullDomainNameValidator;
use WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionDuplicateValidator;
use WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionValidator;
use WMDE\Fundraising\Frontend\SubscriptionContext\Tests\Fixtures\InMemorySubscriptionRepository;
use WMDE\Fundraising\Frontend\Tests\Unit\Validation\ValidatorTestCase;
use WMDE\Fundraising\Frontend\Validation\AllowedValuesValidator;
use WMDE\Fundraising\Frontend\Validation\EmailValidator;
use WMDE\Fundraising\Frontend\Validation\TextPolicyValidator;
use WMDE\Fundraising\Frontend\Validation\ValidationResult;

/**
 * @covers WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionValidator
 *
 * @license GNU GPL v2+
 * @author Gabriel Birke < gabriel.birke@wikimedia.de >
 */
class SubscriptionValidatorTest extends ValidatorTestCase {

	private function getMockTextPolicyValidator() {
		$mock = $this->createMock( TextPolicyValidator::class );
		$mock->method( 'hasHarmlessContent' )
			->willReturn( true );
		return $mock;
	}

	private function getMockDuplicateValidator() {
		$mock = $this->getMockBuilder( \WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionDuplicateValidator::class )
			->disableOriginalConstructor()->getMock();

		$mock->method( 'validate' )->willReturn( new ValidationResult() );
		return $mock;
	}

	private function createAddress( string $saluation, string $firstName, string $lastName ): Address {
		$address = new Address();
		$address->setSalutation( $saluation );
		$address->setFirstName( $firstName );
		$address->setLastName( $lastName );
		$address->setTitle( '' );
		$address->setCompany( '' );
		$address->setAddress( '' );
		$address->setCity( '' );
		$address->setPostcode( '' );
		return $address;
	}

	public function testEmailIsValidated() {
		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$subscriptionValidator = new \WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionValidator(
			$mailValidator,
			$this->getMockTextPolicyValidator(),
			$this->getMockDuplicateValidator(),
			new AllowedValuesValidator( [''] )
		);
		$subscription = new Subscription();
		$subscription->setAddress( $this->createAddress( 'Herr', 'Nyan', 'Cat' ) );
		$subscription->setEmail( 'this is not a mail addess' );
		$this->assertConstraintWasViolated( $subscriptionValidator->validate( $subscription ), 'email' );
	}

	public function testFirstNameIsValidated() {
		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$subscriptionValidator = new SubscriptionValidator(
			$mailValidator,
			$this->getMockTextPolicyValidator(),
			$this->getMockDuplicateValidator(),
			new AllowedValuesValidator( [''] )
		);
		$subscription = new Subscription();
		$subscription->setAddress( $this->createAddress( 'Herr', '', 'Cat' ) );
		$subscription->setEmail( 'nyan@meow.com' );
		$this->assertConstraintWasViolated( $subscriptionValidator->validate( $subscription ), 'firstName' );
	}

	public function testLastNameIsValidated() {
		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$subscriptionValidator = new \WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionValidator(
			$mailValidator,
			$this->getMockTextPolicyValidator(),
			$this->getMockDuplicateValidator(),
			new AllowedValuesValidator( [''] )
		);
		$subscription = new Subscription();
		$subscription->setAddress( $this->createAddress( 'Herr', 'Nyan', '' ) );
		$subscription->setEmail( 'nyan@meow.com' );
		$this->assertConstraintWasViolated( $subscriptionValidator->validate( $subscription ), 'lastName' );
	}

	public function testSalutationIsValidated() {
		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$subscriptionValidator = new SubscriptionValidator(
			$mailValidator,
			$this->getMockTextPolicyValidator(),
			$this->getMockDuplicateValidator(),
			new AllowedValuesValidator( [''] )
		);
		$subscription = new Subscription();
		$subscription->setAddress( $this->createAddress( '', 'Nyan', 'Cat' ) );
		$subscription->setEmail( 'nyan@meow.com' );
		$this->assertConstraintWasViolated( $subscriptionValidator->validate( $subscription ), 'salutation' );
	}

	public function testGivenBadWords_subscriptionIsStillValid() {
		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$policyValidator = $this->createMock( TextPolicyValidator::class );
		$policyValidator->method( 'hasHarmlessContent' )
			->willReturn( false );
		$subscriptionValidator = new SubscriptionValidator(
			$mailValidator,
			$policyValidator,
			$this->getMockDuplicateValidator(),
			new AllowedValuesValidator( [''] )
		);
		$subscription = new Subscription();
		$subscription->setAddress( $this->createAddress( 'Herr', 'Nyan', 'Cat' ) );
		$subscription->setEmail( 'nyan@meow.com' );
		$this->assertTrue( $subscriptionValidator->validate( $subscription )->isSuccessful() );
	}

	public function testGivenBadWords_needsModerationIsTrue() {
		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$policyValidator = $this->createMock( TextPolicyValidator::class );
		$policyValidator->method( 'hasHarmlessContent' )
			->willReturn( false );
		$subscriptionValidator = new \WMDE\Fundraising\Frontend\SubscriptionContext\Validation\SubscriptionValidator(
			$mailValidator,
			$policyValidator,
			$this->getMockDuplicateValidator(),
			new AllowedValuesValidator( [''] )
		);
		$subscription = new Subscription();
		$subscription->setAddress( $this->createAddress( 'Herr', 'Nyan', 'Cat' ) );
		$subscription->setEmail( 'nyan@meow.com' );
		$this->assertTrue( $subscriptionValidator->needsModeration( $subscription ) );
	}

	public function testDuplicateSubscriptionIsValidated() {
		$subscription = new Subscription();
		$subscription->setAddress( $this->createAddress( 'Herr', 'Nyan', 'Cat' ) );
		$subscription->setEmail( 'nyan@meow.com' );
		$subscription->setCreatedAt( new \DateTime( '5 minutes ago' ) );

		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$repository = new InMemorySubscriptionRepository();
		$repository->storeSubscription( $subscription );
		$duplicateValidator = new SubscriptionDuplicateValidator( $repository, new \DateTime( '30 minutes ago' ) );
		$subscriptionValidator = new SubscriptionValidator(
			$mailValidator,
			$this->getMockTextPolicyValidator(),
			$duplicateValidator,
			new AllowedValuesValidator( [''] )
		);
		$this->assertConstraintWasViolated(
			$subscriptionValidator->validate( $subscription ),
			SubscriptionDuplicateValidator::SOURCE_NAME
		);
	}

	public function testHonorificIsValidated() {
		$subscription = new Subscription();
		$address = $this->createAddress( 'Herr', 'Nyan', 'Cat' );
		$address->setTitle( 'Overlord' );
		$subscription->setAddress( $address );
		$subscription->setEmail( 'nyan@meow.com' );

		$mailValidator = new EmailValidator( new NullDomainNameValidator() );
		$subscriptionValidator = new SubscriptionValidator(
			$mailValidator,
			$this->getMockTextPolicyValidator(),
			$this->getMockDuplicateValidator(),
			new AllowedValuesValidator( ['', 'Dr.', 'Prof.'] )
		);
		$this->assertConstraintWasViolated( $subscriptionValidator->validate( $subscription ), 'title' );
	}
}
