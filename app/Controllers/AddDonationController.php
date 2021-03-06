<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\App\Controllers;

use Silex\Application;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use WMDE\Euro\Euro;
use WMDE\Fundraising\DonationContext\Domain\Model\DonationTrackingInfo;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationRequest;
use WMDE\Fundraising\DonationContext\UseCases\AddDonation\AddDonationResponse;
use WMDE\Fundraising\Frontend\BucketTesting\Logging\Events\DonationCreated;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\Frontend\Infrastructure\AmountParser;
use WMDE\Fundraising\PaymentContext\Domain\Model\BankData;
use WMDE\Fundraising\PaymentContext\Domain\Model\Iban;
use WMDE\Fundraising\PaymentContext\Domain\Model\PaymentMethod;
use WMDE\FunValidators\ConstraintViolation;

/**
 * @license GNU GPL v2+
 */
class AddDonationController {

	private $app;
	private $ffFactory;

	public function handle( FunFunFactory $ffFactory, Request $request, Application $app ): Response {
		$this->app = $app;
		$this->ffFactory = $ffFactory;
		if ( !$this->isSubmissionAllowed( $request ) ) {
			return new Response( $this->ffFactory->newSystemMessageResponse( 'donation_rejected_limit' ) );
		}

		$addDonationRequest = $this->createDonationRequest( $request );
		$responseModel = $this->ffFactory->newAddDonationUseCase()->addDonation( $addDonationRequest );

		if ( !$responseModel->isSuccessful() ) {
			$this->logValidationErrors( $responseModel->getValidationErrors() );
			return new Response(
				$this->ffFactory->newDonationFormViolationPresenter()->present(
					$responseModel->getValidationErrors(),
					$addDonationRequest,
					$this->newTrackingInfoFromRequest( $request )
				)
			);
		}

		$this->logSelectedBuckets( $responseModel );
		$this->sendTrackingDataIfNeeded( $request, $responseModel );
		$this->resetSessionState();

		return $this->newHttpResponse( $responseModel );
	}

	private function sendTrackingDataIfNeeded( Request $request, AddDonationResponse $responseModel ) {
		if ( $request->get( 'mbt', '' ) !== '1' || !$responseModel->getDonation()->hasExternalPayment() ) {
			return;
		}

		$trackingCode = explode( '/', $request->attributes->get( 'trackingCode' ) );
		$campaign = $trackingCode[0];
		$keyword = $trackingCode[1] ?? '';

		$this->ffFactory->getPageViewTracker()->trackPaypalRedirection( $campaign, $keyword, $request->getClientIp() );
	}

	private function newHttpResponse( AddDonationResponse $responseModel ): Response {
		switch( $responseModel->getDonation()->getPaymentMethodId() ) {
			case PaymentMethod::DIRECT_DEBIT:
			case PaymentMethod::BANK_TRANSFER:
				return new RedirectResponse(
					$this->ffFactory->getUrlGenerator()->generateAbsoluteUrl(
						'show-donation-confirmation',
						[
							'id' => $responseModel->getDonation()->getId(),
							'accessToken' => $responseModel->getAccessToken()
						]
					)
				);
				break;
			case PaymentMethod::PAYPAL:
				return new RedirectResponse(
					$this->ffFactory->newPayPalUrlGeneratorForDonations()->generateUrl(
						$responseModel->getDonation()->getId(),
						$responseModel->getDonation()->getAmount(),
						$responseModel->getDonation()->getPaymentIntervalInMonths(),
						$responseModel->getUpdateToken(),
						$responseModel->getAccessToken()
					)
				);
				break;
			case PaymentMethod::SOFORT:
				return new RedirectResponse(
					$this->ffFactory->newSofortUrlGeneratorForDonations()->generateUrl(
						$responseModel->getDonation()->getId(),
						$responseModel->getDonation()->getPayment()->getPaymentMethod()->getBankTransferCode(),
						$responseModel->getDonation()->getAmount(),
						$responseModel->getUpdateToken(),
						$responseModel->getAccessToken()
					)
				);
				break;
			case PaymentMethod::CREDIT_CARD:
				return new RedirectResponse(
					$this->ffFactory->newCreditCardPaymentUrlGenerator()->buildUrl( $responseModel )
				);
				break;
			default:
				throw new \LogicException( 'This code should not be reached' );
		}
	}

	private function createDonationRequest( Request $request ): AddDonationRequest {
		$donationRequest = new AddDonationRequest();

		$donationRequest->setAmount( $this->getEuroAmountFromString( $request->get( 'betrag', '' ) ) );

		$donationRequest->setPaymentType( $request->get( 'zahlweise', '' ) );
		$donationRequest->setInterval( intval( $request->get( 'periode', 0 ) ) );

		$donationRequest->setDonorType( $request->get( 'addressType', '' ) );
		$donationRequest->setDonorSalutation( $request->get( 'salutation', '' ) );
		$donationRequest->setDonorTitle( $request->get( 'title', '' ) );
		$donationRequest->setDonorCompany( $request->get( 'companyName', '' ) );
		$donationRequest->setDonorFirstName( $request->get( 'firstName', '' ) );
		$donationRequest->setDonorLastName( $request->get( 'lastName', '' ) );
		$donationRequest->setDonorStreetAddress( $this->filterAutofillCommas( $request->get( 'street', '' ) ) );
		$donationRequest->setDonorPostalCode( $request->get( 'postcode', '' ) );
		$donationRequest->setDonorCity( $request->get( 'city', '' ) );
		$donationRequest->setDonorCountryCode( $request->get( 'country', '' ) );
		$donationRequest->setDonorEmailAddress( $request->get( 'email', '' ) );

		if ( $request->get( 'zahlweise', '' ) === PaymentMethod::DIRECT_DEBIT ) {
			$donationRequest->setBankData( $this->getBankDataFromRequest( $request ) );
		}

		$donationRequest->setTracking( $request->attributes->get( 'trackingCode' ) );
		$donationRequest->setOptIn( $request->get( 'info', '' ) );
		$donationRequest->setSource( $request->attributes->get( 'trackingSource' ) );
		$donationRequest->setTotalImpressionCount( intval( $request->get( 'impCount', 0 ) ) );
		$donationRequest->setSingleBannerImpressionCount( intval( $request->get( 'bImpCount', 0 ) ) );
		$donationRequest->setOptsIntoDonationReceipt( $request->request->getBoolean( 'donationReceipt', true ) );

		return $donationRequest;
	}

	private function getBankDataFromRequest( Request $request ): BankData {
		$bankData = new BankData();
		$bankData->setIban( new Iban( $request->get( 'iban', '' ) ) )
			->setBic( $request->get( 'bic', '' ) )
			->setAccount( $request->get( 'konto', '' ) )
			->setBankCode( $request->get( 'blz', '' ) )
			->setBankName( $request->get( 'bankname', '' ) );

		if ( $bankData->isComplete() ) {
			return $bankData->freeze()->assertNoNullFields();
		}

		if ( $bankData->hasIban() ) {
			$bankData = $this->newBankDataFromIban( $bankData->getIban() );
		} elseif ( $bankData->hasCompleteLegacyBankData() ) {
			$bankData = $this->newBankDataFromAccountAndBankCode( $bankData->getAccount(), $bankData->getBankCode() );
		}
		return $bankData->freeze()->assertNoNullFields();
	}

	private function newBankDataFromIban( Iban $iban ): BankData {
		return $this->ffFactory->newBankDataConverter()->getBankDataFromIban( $iban );
	}

	private function newBankDataFromAccountAndBankCode( string $account, string $bankCode ): BankData {
		return $this->ffFactory->newBankDataConverter()->getBankDataFromAccountData( $account, $bankCode );
	}

	private function getEuroAmountFromString( string $amount ): Euro {
		$locale = 'de_DE'; // TODO: make this configurable for multilanguage support
		try {
			return Euro::newFromFloat( ( new AmountParser( $locale ) )->parseAsFloat( $amount ) );
		} catch ( \InvalidArgumentException $ex ) {
			return Euro::newFromCents( 0 );
		}

	}

	private function isSubmissionAllowed( Request $request ): bool {
		$lastSubmission = $request->cookies->get( ShowDonationConfirmationController::SUBMISSION_COOKIE_NAME, '' );
		if ( $lastSubmission === '' ) {
			return true;
		}

		$minNextTimestamp =
			\DateTime::createFromFormat( ShowDonationConfirmationController::TIMESTAMP_FORMAT, $lastSubmission )
			->add( new \DateInterval( $this->ffFactory->getDonationTimeframeLimit() ) );

		if ( $minNextTimestamp > new \DateTime() ) {
			return false;
		}

		return true;
	}

	private function newTrackingInfoFromRequest( Request $request ): DonationTrackingInfo {
		$tracking = new DonationTrackingInfo();
		$tracking->setSingleBannerImpressionCount( intval( $request->get( 'bImpCount', 0 ) ) );
		$tracking->setTotalImpressionCount( intval( $request->get( 'impCount', 0 ) ) );

		return $tracking;
	}

	/**
	 * Safari and Chrome concatenate street autofill values (e.g. house number and street name) with a comma.
	 * This method removes the commas.
	 *
	 * @param string $value
	 * @return string
	 */
	private function filterAutofillCommas( string $value ): string {
		return trim( preg_replace( ['/,/', '/\s{2,}/'], [' ', ' '], $value ) );
	}

	private function logSelectedBuckets( AddDonationResponse $responseModel ): void {
		$this->ffFactory->getBucketLogger()->writeEvent(
			new DonationCreated( $responseModel->getDonation()->getId() ),
			...$this->ffFactory->getSelectedBuckets()
		);
	}

	/**
	 * Reset session data to prevent old donations from changing the application output due to old data leaking into the new session
	 */
	private function resetSessionState(): void {
		$this->app['session']->set(
			UpdateDonorController::ADDRESS_CHANGE_SESSION_KEY,
			false
		);
	}

	/**
	 * @param ConstraintViolation[] $constraintViolations
	 */
	private function logValidationErrors( array $constraintViolations ): void {
		$formattedConstraintViolations = [];
		foreach ( $constraintViolations as $constraintViolation ) {
			$formattedConstraintViolations['validation_errors'][] = sprintf(
				'Validation field "%s" with value "%s" failed with: %s',
				$constraintViolation->getSource(),
				$constraintViolation->getValue(),
				$this->ffFactory->getTranslator()->trans( $constraintViolation->getMessageIdentifier() )
			);
		}
		$this->ffFactory->getLogger()->warning(
			'Unexpected server-side form validation errors.',
			$formattedConstraintViolations
		);
	}
}