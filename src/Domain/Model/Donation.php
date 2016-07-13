<?php

declare( strict_types = 1 );

namespace WMDE\Fundraising\Frontend\Domain\Model;

use RuntimeException;

/**
 * @licence GNU GPL v2+
 * @author Kai Nissen < kai.nissen@wikimedia.de >
 * @author Jeroen De Dauw < jeroendedauw@gmail.com >
 */
class Donation {

	const STATUS_NEW = 'N'; // status for direct debit
	const STATUS_PROMISE = 'Z'; // status for bank transfer
	const STATUS_EXTERNAL_INCOMPLETE = 'X'; // status for external payments
	const STATUS_EXTERNAL_BOOKED = 'B'; // status for external payments
	const STATUS_MODERATION = 'P';
	const STATUS_CANCELLED = 'D';

	const OPTS_INTO_NEWSLETTER = true;
	const DOES_NOT_OPT_INTO_NEWSLETTER = false;

	const NO_APPLICANT = null;

	private $id;
	private $status;
	private $donor;
	private $payment;
	private $optsIntoNewsletter;
	private $comment;

	/**
	 * TODO: move out of Donation
	 */
	private $trackingInfo;

	public function __construct( int $id = null, string $status, Donor $donor = null, DonationPayment $payment,
		bool $optsIntoNewsletter, DonationTrackingInfo $trackingInfo, DonationComment $comment = null ) {

		$this->id = $id;
		$this->status = $status;
		$this->donor = $donor;
		$this->payment = $payment;
		$this->optsIntoNewsletter = $optsIntoNewsletter;
		$this->trackingInfo = $trackingInfo;
		$this->comment = $comment;
	}

	/**
	 * @return int|null
	 */
	public function getId() {
		return $this->id;
	}

	/**
	 * @param int $id
	 * @throws \RuntimeException
	 */
	public function assignId( int $id ) {
		if ( $this->id !== null && $this->id !== $id ) {
			throw new \RuntimeException( 'Id cannot be changed after initial assignment' );
		}

		$this->id = $id;
	}

	public function getStatus(): string {
		return $this->status;
	}

	public function getAmount(): Euro {
		return $this->payment->getAmount();
	}

	public function getPaymentIntervalInMonths(): int {
		return $this->payment->getIntervalInMonths();
	}

	public function getPaymentType(): string {
		return $this->payment->getPaymentMethod()->getType();
	}

	/**
	 * Returns the Donor or null for anonymous donations.
	 *
	 * @return Donor|null
	 */
	public function getDonor() {
		return $this->donor;
	}

	/**
	 * Returns the DonationComment or null for when there is none.
	 *
	 * @return DonationComment|null
	 */
	public function getComment() {
		return $this->comment;
	}

	public function addComment( DonationComment $comment ) {
		if ( $this->hasComment() ) {
			throw new RuntimeException( 'Can only add a single comment to a donation' );
		}

		$this->comment = $comment;
	}

	public function getPayment(): DonationPayment {
		return $this->payment;
	}

	public function getPaymentMethod(): PaymentMethod {
		return $this->payment->getPaymentMethod();
	}

	public function getOptsIntoNewsletter(): bool {
		return $this->optsIntoNewsletter;
	}

	/**
	 * @throws RuntimeException
	 */
	public function cancel() {
		if ( $this->getPaymentType() !== PaymentType::DIRECT_DEBIT ) {
			throw new RuntimeException( 'Can only cancel direct debit' );
		}

		if ( !$this->statusIsCancellable() ) {
			throw new RuntimeException( 'Can only cancel new donations' );
		}

		$this->status = self::STATUS_CANCELLED;
	}

	/**
	 * @throws RuntimeException
	 */
	public function confirmBooked() {
		if ( !$this->hasExternalPayment() ) {
			throw new RuntimeException( 'Only external payments can be confirmed as booked' );
		}

		if ( !$this->statusAllowsForBooking() ) {
			throw new RuntimeException( 'Only incomplete donations can be confirmed as booked' );
		}

		if ( $this->hasComment() && ( $this->needsModeration() || $this->isCancelled() ) ) {
			$this->makeCommentPrivate();
		}

		$this->status = self::STATUS_EXTERNAL_BOOKED;
	}

	private function makeCommentPrivate() {
		$this->comment = new DonationComment(
			$this->comment->getCommentText(),
			false,
			$this->comment->getAuthorDisplayName()
		);
	}

	public function hasComment(): bool {
		return $this->comment !== null;
	}

	private function statusAllowsForBooking(): bool {
		return $this->isIncomplete() || $this->needsModeration() || $this->isCancelled();
	}

	public function markForModeration() {
		$this->status = self::STATUS_MODERATION;
	}

	public function getTrackingInfo(): DonationTrackingInfo {
		return $this->trackingInfo;
	}

	/**
	 * @param PayPalData $payPalData
	 * @throws RuntimeException
	 */
	public function addPayPalData( PayPalData $payPalData ) {
		$paymentMethod = $this->payment->getPaymentMethod();

		if ( !( $paymentMethod instanceof PayPalPayment ) ) {
			throw new RuntimeException( 'Cannot set PayPal data on a non PayPal payment' );
		}

		$paymentMethod->addPayPalData( $payPalData );
	}

	/**
	 * @param CreditCardTransactionData $creditCardData
	 * @throws RuntimeException
	 */
	public function addCreditCardData( CreditCardTransactionData $creditCardData ) {
		$paymentMethod = $this->payment->getPaymentMethod();

		if ( !( $paymentMethod instanceof CreditCardPayment ) ) {
			throw new RuntimeException( 'Cannot set credit card transaction data on a non credit card payment' );
		}

		$paymentMethod->addCreditCardTransactionData( $creditCardData );
	}

	private function statusIsCancellable(): bool {
		return $this->status === self::STATUS_NEW || $this->status === self::STATUS_MODERATION;
	}

	public function hasExternalPayment(): bool {
		return in_array( $this->getPaymentType(), [ PaymentType::PAYPAL, PaymentType::CREDIT_CARD ] );
	}

	private function isIncomplete(): bool {
		return $this->status === self::STATUS_EXTERNAL_INCOMPLETE;
	}

	public function needsModeration(): bool {
		return $this->status === self::STATUS_MODERATION;
	}

	public function isBooked(): bool {
		return $this->status === self::STATUS_EXTERNAL_BOOKED;
	}

	public function isCancelled(): bool {
		return $this->status === self::STATUS_CANCELLED;
	}

}
