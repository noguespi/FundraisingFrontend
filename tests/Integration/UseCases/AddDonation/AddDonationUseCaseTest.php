<?php


namespace WMDE\Fundraising\Frontend\Tests\Integration\UseCases\AddDonation;

use PHPUnit_Framework_MockObject_MockObject;
use WMDE\Fundraising\Frontend\Domain\Model\PersonalInfo;
use WMDE\Fundraising\Frontend\Domain\Repositories\DonationRepository;
use WMDE\Fundraising\Frontend\Domain\Model\PaymentType;
use WMDE\Fundraising\Frontend\MailAddress;
use WMDE\Fundraising\Frontend\ReferrerGeneralizer;
use WMDE\Fundraising\Frontend\TemplateBasedMailer;
use WMDE\Fundraising\Frontend\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\Frontend\UseCases\AddDonation\AddDonationUseCase;
use WMDE\Fundraising\Frontend\Validation\ConstraintViolation;
use WMDE\Fundraising\Frontend\Validation\DonationValidator;
use WMDE\Fundraising\Frontend\Validation\ValidationResult;

/**
 * @covers WMDE\Fundraising\Frontend\UseCases\AddDonation\AddDonationUseCase
 *
 * @license GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class AddDonationUseCaseTest extends \PHPUnit_Framework_TestCase {

	public function testValidationSucceeds_successResponseIsCreated() {
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			new ReferrerGeneralizer( 'http://foo.bar', [] ),
			$this->newMailer()
		);

		$this->assertTrue( $useCase->addDonation( $this->newMinimumDonationRequest() )->isSuccessful() );
	}

	/**
	 * @return TemplateBasedMailer|PHPUnit_Framework_MockObject_MockObject
	 */
	private function newMailer(): TemplateBasedMailer {
		return $this->getMockBuilder( TemplateBasedMailer::class )
			->disableOriginalConstructor()
			->getMock();
	}

	private function newRepository(): DonationRepository {
		return $this->getMock( DonationRepository::class );
	}

	public function testValidationFails_responseObjectContainsViolations() {
		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			new ReferrerGeneralizer( 'http://foo.bar', [] ),
			$this->newMailer()
		);

		$result = $useCase->addDonation( $this->newMinimumDonationRequest() );
		$this->assertEquals( [ new ConstraintViolation( 'foo', 'bar' ) ], $result->getValidationErrors() );
	}

	private function getSucceedingValidatorMock(): DonationValidator {
		$validator = $this->getMockBuilder( DonationValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$validator->method( 'validate' )->willReturn( new ValidationResult() );

		return $validator;
	}

	private function getFailingValidatorMock( ConstraintViolation $violation ): DonationValidator {
		$validator = $this->getMockBuilder( DonationValidator::class )
			->disableOriginalConstructor()
			->getMock();

		$validator->method( 'validate' )->willReturn( new ValidationResult( $violation ) );

		return $validator;
	}

	private function newMinimumDonationRequest(): AddDonationRequest {
		$donationRequest = new AddDonationRequest();
		$donationRequest->setPaymentType( PaymentType::DIRECT_DEBIT );
		return $donationRequest;
	}

	public function testGivenInvalidRequest_noConfirmationEmailIsSend() {
		$mailer = $this->newMailer();

		$mailer->expects( $this->never() )->method( 'sendMail' );

		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getFailingValidatorMock( new ConstraintViolation( 'foo', 'bar' ) ),
			new ReferrerGeneralizer( 'http://foo.bar', [] ),
			$mailer
		);

		$useCase->addDonation( $this->newMinimumDonationRequest() );
	}


	public function testGivenValidRequest_confirmationEmailIsSend() {
		$mailer = $this->newMailer();

		$mailer->expects( $this->once() )
			->method( 'sendMail' )
			->with(
				$this->equalTo( new MailAddress( 'foo@bar.baz' ) ),
				$this->callback( function( $value ) {
					$this->assertInternalType( 'array', $value );
					// TODO: assert parameters
					return true;
				} )
			);

		$useCase = new AddDonationUseCase(
			$this->newRepository(),
			$this->getSucceedingValidatorMock(),
			new ReferrerGeneralizer( 'http://foo.bar', [] ),
			$mailer
		);

		$request = $this->newMinimumDonationRequest();
		$personalInfo = new PersonalInfo();
		$personalInfo->setEmailAddress( 'foo@bar.baz' );
		$request->setPersonalInfo( $personalInfo );

		$useCase->addDonation( $request );
	}

}