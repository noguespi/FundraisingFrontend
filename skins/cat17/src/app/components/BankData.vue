<template>
	<div>
		<div v-bind:class="classesIBAN">
			<input type="text" id="iban" name="iban" :placeholder="labels.iban" v-model="iban" v-on:input="handleIbanChange">
			<label for="iban">{{ labels.iban }}</label>
		</div>
		<div v-bind:class="classesBIC">
			<input type="text" id="bic" name="bic" :placeholder="labels.bic" v-model="bic" :disabled="writableBIC" v-on:input="handleBicChange">
			<label for="bic">{{ labels.bic }}</label>
		</div>

		<span id="bank-name">{{ bankName }}</span>
		<input type="hidden" name="bankname" id="field-bank-name" :value="bankName" />

		<span class="error-text" v-show="hasError">{{ errorText }}</span>
	</div>
</template>

<script>
	export default {
		name: 'bank-data',
		props: {
			changeIban: 'Function',
			changeBic: 'Function',
			changeBankDataValidity: 'Function',
			validateBankData: 'Function',
			iban: 'String',
			bic: 'String',
			isValid: 'Boolean'
		},
		data: function() {
			//TODO Translate these strings
			return {
				bankName: '',
				errorText: '',
				hasError: false,
				isValidBic: true,
				bicFilled: false,
				focusOut: false,
				bicFocusOut: false,
			}
		},
		methods: {
			handleIbanChange( evt ) {
				this.changeIban( evt.target.value );
				this.validate();
			},
			handleBicChange( evt ) {
				this.changeBic( evt.target.value );
				this.validate();
			},
			validate() {
				if( this.iban === '' || (this.bic === '' && !this.looksLikeIBAN() ) ) {
					return this.changeBankDataValidity( { status: 'INCOMPLETE', iban: this.iban, bic: this.bic } ) ;
				}
				return this.validateBankData( this.iban, this.bic, this.looksLikeIBAN() )
					.then( this.changeBankDataValidity )
					.catch( () => {
						this.errorText = 'An error has occurred. Please reload the page and try again.'; // TODO translate
						this.hasError = true;
					} );
			},
			isFieldEmpty: function () {
				return this.iban === '';
			},
			isBicEmpty: function () {
				return this.bic === '';
			},
			looksLikeIBAN: function () {
				return /^[A-Z]+([0-9]+)?$/.test( this.iban );
			},
			looksLikeBankAccountNumber: function () {
				return /^\d+$/.test( this.iban );
			},
			fillBIC: function () {
				if ( this.isValid && !this.isFieldEmpty() && this.looksLikeIBAN() ) {
					this.bic =  this.validBankData.bic;
				}
				this.focusOut = true;
			},
		},
		computed: {
			labels() {
				if ( this.looksLikeIBAN() ) {
					return {
						'iban': 'IBAN',
						'bic': 'BIC'
					};
				}
				if ( this.looksLikeBankAccountNumber() ) {
					return {
						'iban': 'Kontonummer',
						'bic': 'Bankleitzahl'
					};
				}
				return {
					'iban': 'IBAN / Kontonummer',
					'bic': 'BIC / Bankleitzahl'
				};
			},
			writableBIC() {
				return /^(DE).*$/.test( this.iban );
			},
			classesIBAN() {
				return {
					'field-grp': true,
					'field-iba': true,
					'field-labeled': true,
					'invalid': !this.isValid,
					'valid': this.isValid && !this.isFieldEmpty() && this.focusOut
				}
			},
			classesBIC() {
				return {
					'field-grp': true,
					'field-iba': true,
					'field-labeled': true,
					'invalid': ( !this.isValid && this.focusOut ) || ( !this.isValidBic && this.bicFocusOut && this.focusOut ),
					'valid': this.isValid && !this.isFieldEmpty() && ( ( this.looksLikeIBAN() && this.focusOut ) || ( this.isValidBic && this.focusOut && !this.isBicEmpty() ) )
				}
			}
		},
	}

</script>
