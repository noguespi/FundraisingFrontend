<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\EdgeToEdge\Routes;

use Symfony\Component\HttpKernel\Client;
use WMDE\Fundraising\DonationContext\DonationAcceptedEventHandler;
use WMDE\Fundraising\Frontend\Factories\FunFunFactory;
use WMDE\Fundraising\DonationContext\Tests\Data\ValidDonation;
use WMDE\Fundraising\Frontend\Tests\EdgeToEdge\WebRouteTestCase;
use WMDE\Fundraising\Frontend\Tests\Fixtures\FixedTokenGenerator;

/**
 * @licence GNU GPL v2+
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class DonationAcceptedRouteTest extends WebRouteTestCase {

	const WRONG_UPDATE_TOKEN = 'Wrong update token';
	const CORRECT_UPDATE_TOKEN = 'Correct update token';

	public function testGivenInvalidUpdateToken_errorIsReturned(): void {
		$this->createEnvironment( [], function( Client $client, FunFunFactory $factory ): void {
			$existingDonationId = $this->storeDonation( $factory );

			$client->request(
				'GET',
				'/donation-accepted',
				[
					'update_token' => self::WRONG_UPDATE_TOKEN,
					'donation_id' => (string)$existingDonationId,
				]
			);

			$this->assertJsonSuccessResponse(
				[ 'status' => 'ERR', 'message' => DonationAcceptedEventHandler::AUTHORIZATION_FAILED ],
				$client->getResponse()
			);
		} );
	}

	private function storeDonation( FunFunFactory $factory ): int {
		$factory->setDonationTokenGenerator( new FixedTokenGenerator( self::CORRECT_UPDATE_TOKEN ) );

		$donation = ValidDonation::newDirectDebitDonation();
		$factory->getDonationRepository()->storeDonation( $donation );

		return $donation->getId();
	}

	public function testGivenKnownIdAndValidUpdateToken_successIsReturned(): void {
		$this->createEnvironment( [], function( Client $client, FunFunFactory $factory ): void {

			$existingDonationId = $this->storeDonation( $factory );

			$client->request(
				'GET',
				'/donation-accepted',
				[
					'update_token' => self::CORRECT_UPDATE_TOKEN,
					'donation_id' => (string)$existingDonationId,
				]
			);

			$this->assertJsonSuccessResponse(
				[ 'status' => 'OK' ],
				$client->getResponse()
			);
		} );
	}

}
