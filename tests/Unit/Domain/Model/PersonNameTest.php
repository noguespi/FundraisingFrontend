<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Tests\Unit\Domain\Model;

use WMDE\Fundraising\Frontend\Domain\Model\PersonName;

/**
 * @covers WMDE\Fundraising\Frontend\Domain\Model\PersonName
 *
 * @license GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 */
class PersonNameTest extends \PHPUnit_Framework_TestCase {

	/**
	 * @dataProvider privatePersonProvider
	 */
	public function testGivenPersonName_determineFullNameReturnsFullName( $expectedValue, $data ) {
		$personName = PersonName::newPrivatePersonName();

		$personName->setCompanyName( $data['company'] );
		$personName->setFirstName( $data['firstName'] );
		$personName->setLastName( $data['lastName'] );
		$personName->setTitle( $data['title'] );

		$this->assertSame( $expectedValue, $personName->getFullName() );
	}

	public function privatePersonProvider() {
		return [
			[
				'Ebenezer Scrooge',
				[
					'title' => '',
					'firstName' => 'Ebenezer',
					'lastName' => 'Scrooge',
					'company' => ''
				]
			],
			[
				'Sir Ebenezer Scrooge',
				[
					'title' => 'Sir',
					'firstName' => 'Ebenezer',
					'lastName' => 'Scrooge',
					'company' => ''
				]
			],
			[
				'Prof. Dr. Friedemann Schulz von Thun',
				[
					'title' => 'Prof. Dr.',
					'firstName' => 'Friedemann',
					'lastName' => 'Schulz von Thun',
					'company' => ''
				]
			],
			[
				'Hank Scorpio, Globex Corp.',
				[
					'title' => '',
					'firstName' => 'Hank',
					'lastName' => 'Scorpio',
					'company' => 'Globex Corp.'
				]
			],
			[
				'Evil Hank Scorpio, Globex Corp.',
				[
					'title' => 'Evil',
					'firstName' => 'Hank',
					'lastName' => 'Scorpio',
					'company' => 'Globex Corp.'
				]
			]
		];
	}

}