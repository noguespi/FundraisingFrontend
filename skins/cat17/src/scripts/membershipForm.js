$( function () {
  /** global: WMDE */

  if ($('body#membership').length == 0) {
    return;
  }

  var initData = $( '#init-form.membership' ),
    store = WMDE.Store.createMembershipStore(
      WMDE.createInitialStateFromViolatedFields( initData.data( 'violatedFields' ), {} )
    ),
    actions = WMDE.Actions;

  WMDE.StoreUpdates.connectComponentsToStore(
    [
      //MemberShipType
      WMDE.Components.createRadioComponent( store, $( 'input[name="membership_type"]' ), 'membershipType' ),

      //Amount and periodicity
      WMDE.Components.createAmountComponent( store, $( '#amount-typed' ), $( 'input[name="amount-grp"]' ), $( '#amount-hidden' ) ),
      WMDE.Components.createRadioComponent( store, $( 'input[name="periode"]' ), 'paymentIntervalInMonths' ),

      //Personal data
      WMDE.Components.createRadioComponent( store, $( 'input[name="addressType"]' ), 'addressType' ),
      //Personal Data
      WMDE.Components.createSelectMenuComponent( store, $( '#treatment' ), 'salutation' ),
      WMDE.Components.createSelectMenuComponent( store, $( '#title' ), 'title' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#first-name' ), 'firstName' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#surname' ), 'lastName' ),
      WMDE.Components.createTextComponent( store, $( '#email' ), 'email' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#street' ), 'street' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#post-code' ), 'postcode' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#city' ), 'city' ),
      WMDE.Components.createSelectMenuComponent( store, $( '#country' ), 'country' ),

      //Company Data
      WMDE.Components.createValidatingTextComponent( store, $( '#company-name' ), 'companyName' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#contact-person' ), 'contactPerson' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#email-company' ), 'email' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#adress-company' ), 'street' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#post-code-company' ), 'postcode' ),
      WMDE.Components.createValidatingTextComponent( store, $( '#city-company' ), 'city' ),
      WMDE.Components.createSelectMenuComponent( store, $( '#country-company' ), 'country' ),

      //Payment Data
      WMDE.Components.createRadioComponent( store, $( 'input[name="payment-info"]' ), 'paymentType' ),
      WMDE.Components.createBankDataComponent( store, {
        ibanElement: $( '#iban' ),
        bicElement: $( '#bic' ),
        accountNumberElement: $( '#account-number' ),
        bankCodeElement: $( '#bank-code' ),
        bankNameFieldElement: $( '#field-bank-name' ),
        bankNameDisplayElement: $( '#bank-name' ),
      } ),
      WMDE.Components.createValidatingCheckboxComponent( store, $( '#confirm_sepa' ), 'confirmSepa' )

    ],
    store,
    'membershipFormContent'
  );

  WMDE.StoreUpdates.connectValidatorsToStore(
    function ( initialValues ) {
      return [
        WMDE.ValidationDispatchers.createFeeValidationDispatcher(
          WMDE.FormValidation.createFeeValidator( initData.data( 'validate-fee-url' ) ),
          initialValues
        ),
        WMDE.ValidationDispatchers.createAddressValidationDispatcher(
          WMDE.FormValidation.createAddressValidator(
            initData.data( 'validate-address-url' ),
            WMDE.FormValidation.DefaultRequiredFieldsForAddressType
          ),
          initialValues
        ),
        WMDE.ValidationDispatchers.createEmailValidationDispatcher(
          WMDE.FormValidation.createEmailAddressValidator( initData.data( 'validate-email-address-url' ) ),
          initialValues
        ),
        WMDE.ValidationDispatchers.createBankDataValidationDispatcher(
          WMDE.FormValidation.createBankDataValidator(
            initData.data( 'validate-iban-url' ),
            initData.data( 'generate-iban-url' )
          ),
          initialValues
        ),
        WMDE.ValidationDispatchers.createSepaConfirmationValidationDispatcher(
          WMDE.FormValidation.createSepaConfirmationValidator(),
          initialValues
        )
      ];
    },
    store,
    initData.data( 'initial-form-values' ),
    'membershipFormContent'
  );

  // Connect view handlers to changes in specific parts in the global state, designated by 'stateKey'
  WMDE.StoreUpdates.connectViewHandlersToStore(
    [
      {
        viewHandler: WMDE.View.createFormPageVisibilityHandler( {
          personalData: $( "#personalDataPage" ),
          bankConfirmation: $( '#bankConfirmationPage' )
        } ),
        stateKey: 'formPagination'
      },
      {
        viewHandler: WMDE.View.createErrorBoxHandler( $( '#validation-errors' ), {
          amount: 'Betrag',
          salutation: 'Anrede',
          title: 'Titel',
          firstName: 'Vorname',
          lastName: 'Nachname',
          companyName: 'Firma',
          street: 'Straße',
          postcode: 'PLZ',
          city: 'Ort',
          country: 'Land',
          email: 'E-Mail',
          dateOfBirth: 'Geburtsdatum',
          phone: 'Telefonnummer',
          iban: 'IBAN',
          bic: 'BIC',
          accountNumber: 'Kontonummer',
          bankCode: 'Bankleitzahl',
          confirmSepa: 'SEPA-Lastschrift'
        } ),
        stateKey: 'membershipInputValidation'
      },
      {
        viewHandler: WMDE.View.createSimpleVisibilitySwitcher( $( '#finishFormSubmit' ), /^PPL$|^$/ ),
        stateKey: 'membershipFormContent.paymentType'
      },
      {
        viewHandler: WMDE.View.createSimpleVisibilitySwitcher( $( '#continueFormSubmit' ), 'BEZ' ),
        stateKey: 'membershipFormContent.paymentType'
      },
      {
        viewHandler: WMDE.View.createRecurrentPaypalNoticeHandler(
          WMDE.View.Animator.createSlidingElementAnimator( $( '.notice-ppl-recurrent' ) )
        ),
        stateKey: 'membershipFormContent'
      },
      {
        viewHandler: WMDE.View.createSlidingVisibilitySwitcher( $( '.fields-direct-debit' ), 'BEZ' ),
        stateKey: 'membershipFormContent.paymentType'
      },
      {
        viewHandler: WMDE.View.createSlidingVisibilitySwitcher( $( '.slide-sepa' ), 'sepa' ),
        stateKey: 'membershipFormContent.debitType'
      },
      {
        viewHandler: WMDE.View.createSlidingVisibilitySwitcher( $( '.slide-non-sepa' ), 'non-sepa' ),
        stateKey: 'membershipFormContent.debitType'
      },
      {
        viewHandler: WMDE.View.createSlidingVisibilitySwitcher( $( '.person-name' ), 'person' ),
        stateKey: 'membershipFormContent.addressType'
      },
      {
        viewHandler: WMDE.View.createSlidingVisibilitySwitcher( $( '.company-name' ), 'firma' ),
        stateKey: 'membershipFormContent.addressType'
      },
      {
        viewHandler: WMDE.View.createSimpleVisibilitySwitcher( $( '#address-type-2' ).parent(), 'sustaining' ),
        stateKey: 'membershipFormContent.membershipType'
      },
      {
        viewHandler: WMDE.View.createFeeOptionSwitcher( [ $( '#amount-1' ), $( '#amount-2' ), $( '#amount-3' ), $( '#amount-4' ), $( '#amount-5' ), $( '#amount-6' ), $( '#amount-7' ) ], { person: 24, firma: 100 } ),
        stateKey: 'membershipFormContent'
      },
      {
        viewHandler: WMDE.View.createPaymentSummaryDisplayHandler(
          $( '.interval-text' ),
          $( '.amount .text'),
          $( '.payment-method .text'),
          {
            '0': 'einmalig',
            '1': 'monatlich',
            '3': 'quartalsweise',
            '6': 'halbjährlich',
            '12': 'jährlich'
          },
          {
            'BEZ': 'Lastschrift',
            'UEB': 'Überweisung',
            'MCP': 'Kreditkarte',
            'PPL': 'PayPal',
            'SUB': 'Sofortüberweisung'
          },
          WMDE.CurrencyFormatter.createCurrencyFormatter( 'de' ),
          $('.periodicity-icon'),
          {
            '0': 'icon-unique',
            '1': 'icon-repeat_1',
            '3': 'icon-repeat_3',
            '6': 'icon-repeat_6',
            '12': 'icon-repeat_12'
          },
          $('.payment-icon'),
          {
            'PPL': 'icon-paypal',
            'MCP': 'icon-credit_card2',
            'BEZ': 'icon-SEPA-2',
            'UEB': 'icon-ubeiwsung-1'
          },
          $('.amount .info-detail'),
          {
            '0': 'Ihr Konto wird einmal belastet.',
            '1': 'Ihr Konto wird jeden Monat belastet.<br />Ihre monatliche Spende können Sie jederzeit fristlos per E-Mail an spenden@wikimedia.de stornieren.',
            '3': 'Ihr Konto wird alle drei Monate belastet.<br />Ihre vierteljahrliche Spende können Sie jederzeit fristlos per E-Mail an spenden@wikimedia.de stornieren.',
            '6': 'Ihr Konto wird alle sechs Monate belastet.<br />Ihre halbjahrliche Spende können Sie jederzeit fristlos per E-Mail an spenden@wikimedia.de stornieren.',
            '12': 'Ihr Konto wird jährlich belastet.<br />Ihre jährliche Spende können Sie jederzeit fristlos per E-Mail an spenden@wikimedia.de stornieren.'
          },
          $('.payment-method .info-detail'),
          {
            'PPL': 'Nach der Möglichkeit der Adressangabe werden Sie zu PayPal weitergeleitet, wo Sie die Spende abschließen müssen.',
            'MCP': 'Nach der Möglichkeit der Adressangabe werden Sie zu unserem Partner Micropayment weitergeleitet, wo Sie Ihre Kreditkarteninformationen eingeben können.',
            'BEZ': 'Ich ermächtige die gemeinnützige Wikimedia Fördergesellschaft mbH (Gläubiger-ID: DE25ZZZ00000448435) Zahlungen von meinem Konto mittels Lastschrift einzuziehen. Zugleich weise ich mein Kreditinstitut an, die von der gemeinnützigen Wikimedia Fördergesellschaft mbH auf mein Konto gezogenen Lastschriften einzulösen. <br />Ich kann innerhalb von acht Wochen, beginnend mit dem Belastungsdatum, die Erstattung des belasteten Betrages verlangen. Es gelten dabei die mit meinem Kreditinstitut vereinbarten Bedingungen.',
            'UEB': 'On the conclusion of the donation process, you will be provided with the Wikimedia bank data so you can transfer the money.'
          },
          $('.address-icon'),
          {
            'person': 'icon-account_circle',
            'firma': 'icon-work',
            'anonym': 'icon-visibility_off'
          },
          $('.donator-type .text'),
          {
            'person': 'Privat',
            'firma': 'Firma',
            'anonym': 'Anonymous'
          },
          $('.donator-type .info-detail'),
          $('.frequency .text'),
          {
            '0': 'Einmalig',
            '1': 'Monatlich',
            '3': 'Vierteljährlich',
            '6': 'Halbjährlich',
            '12': 'Jährlich'
          },
          $('.member-type .text'),
          {
            'sustaining': 'Förder',
            'active': 'Aktive'
          },
          $('.membership-type-icon'),
          {
            'sustaining': 'icon-favorite',
            'active': 'icon-flash_on'
          },
          $('.member-type .info-detail'),
          {
            'sustaining': 'Sie erhalten regelmäßige Informationen über die Arbeit des Vereins.',
            'active': 'Sie bringen sich aktiv im Verein und haben ein Stimmrecht auf der Mitglieder- versammlung.'
          }
        ),
        stateKey: 'membershipFormContent'
      },
      {
        viewHandler: WMDE.View.createDisplayAddressHandler( {
          fullName: $( '#membership-confirm-name' ),
          street: $( '#membership-confirm-street' ),
          postcode: $( '#membership-confirm-postcode' ),
          city: $( '#membership-confirm-city' ),
          country: $( '#membership-confirm-country' ),
          email: $( '#membership-confirm-mail' )
        } ),
        stateKey: 'membershipFormContent'
      },
      {
        viewHandler: WMDE.View.createBankDataDisplayHandler(
          $( '#membership-confirm-iban' ),
          $( '#membership-confirm-bic' ),
          $( '#membership-confirm-bankname' )
        ),
        stateKey: 'membershipFormContent'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#first-name' ) ),
        stateKey: 'membershipInputValidation.firstName'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#surname' ) ),
        stateKey: 'membershipInputValidation.lastName'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#contact-person' ) ),
        stateKey: 'membershipInputValidation.contactPerson'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '.street' ) ),
        stateKey: 'membershipInputValidation.street'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '.post-code' ) ),
        stateKey: 'membershipInputValidation.postcode'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '.city' ) ),
        stateKey: 'membershipInputValidation.city'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '.email' ) ),
        stateKey: 'membershipInputValidation.email'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#company-name' ) ),
        stateKey: 'membershipInputValidation.companyName'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#date-of-birth' ) ),
        stateKey: 'membershipInputValidation.dateOfBirth'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#phone' ) ),
        stateKey: 'membershipInputValidation.phoneNumber'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#iban' ) ),
        stateKey: 'membershipInputValidation.iban'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#bic' ) ),
        stateKey: 'membershipInputValidation.bic'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#account-number' ) ),
        stateKey: 'membershipInputValidation.accountNumber'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '#bank-code' ) ),
        stateKey: 'membershipInputValidation.bankCode'
      },
      {
        viewHandler: WMDE.View.createFieldValueValidityIndicator( $( '.amount-input' ) ),
        stateKey: 'membershipInputValidation.amount'
      }
    ],
    store
  );

  // Validity checks for different form parts

  function displayErrorBox() {
    $( '#validation-errors' ).show();
    $( 'html, body' ).animate( { scrollTop: $( '#validation-errors' ).offset().top } );
  }

  function addressIsValid() {
    return store.getState().validity.address;
  }

  function bankDataIsValid() {
    var state = store.getState();
    return state.membershipFormContent.paymentType !== 'BEZ' ||
    (
      state.membershipInputValidation.bic.dataEntered && state.membershipInputValidation.bic.isValid &&
      state.membershipInputValidation.iban.dataEntered && state.membershipInputValidation.iban.isValid
    ) ||
    (
      state.membershipInputValidation.accountNumber.dataEntered && state.membershipInputValidation.accountNumber.isValid &&
      state.membershipInputValidation.bankCode.dataEntered && state.membershipInputValidation.bankCode.isValid
    );
  }

  function formDataIsValid() {
    var validity = store.getState().validity;
    var state = store.getState();
    //console.log(validity.paymentData + " " + addressIsValid() + " " + bankDataIsValid());
    return validity.paymentData && state.membershipFormContent.membershipType && addressIsValid() && bankDataIsValid();
  }


  function triggerValidityCheckForPersonalDataPage() {
    var formContent = store.getState().membershipFormContent;

    if ( !addressIsValid() ) {
      if ( formContent.addressType === 'person' ) {
        store.dispatch( actions.newMarkEmptyFieldsInvalidAction(
          [ 'salutation', 'firstName', 'lastName', 'street', 'postcode', 'city', 'email' ],
          [ 'companyName' ]
        ) );
      } else if ( formContent.addressType === 'firma' ) {
        store.dispatch( actions.newMarkEmptyFieldsInvalidAction(
          [ 'companyName', 'street', 'postcode', 'city', 'email' ],
          [ 'firstName', 'lastName', 'salutation' ]
        ) );
      }
    }

    if ( !bankDataIsValid() ) {
      store.dispatch( actions.newMarkEmptyFieldsInvalidAction(
        [ 'iban', 'bic' ]
      ) );
    }

    if ( !store.getState().validity.amount ) {
      store.dispatch( actions.newMarkEmptyFieldsInvalidAction( [ 'amount' ] ) );
    }
  }

  function hasInvalidFields() {
    var invalidFields = false;
    $.each( store.getState().membershipInputValidation, function( key, value ) {
      if ( value.isValid === false ) {
        invalidFields = true;
      }
    } );

    return invalidFields;
  }

  handleGroupValidations = function () {
    var state = store.getState();

    //1st Group Amount & Periodicity
    var memberType = $('.member-type'),
      amount = $('.amount'),
      paymentMethod = $('.payment-method'),
      donatorType = $('.donator-type');

    if (state.membershipFormContent.membershipType) {
      memberType.addClass('completed').removeClass('disabled invalid');
      donatorType.removeClass('disabled');
    }

    if (state.membershipFormContent.paymentIntervalInMonths >= 0) {
      amount.addClass('completed').removeClass('disabled invalid');
      paymentMethod.removeClass('disabled');
      if (state.membershipInputValidation.amount.dataEntered && !state.membershipInputValidation.amount.isValid) {
        amount.removeClass('completed').addClass('invalid');
        amount.find('.periodicity-icon').removeClass().addClass('periodicity-icon icon-error');
      }
    }
    else {
      paymentMethod.addClass('disabled');
    }

    if (state.membershipFormContent.paymentType) {
      paymentMethod.addClass('completed').removeClass('disabled invalid');
      if (
        (
        state.membershipFormContent.debitType == 'sepa' &&
        state.membershipInputValidation.iban.dataEntered && !state.membershipInputValidation.iban.isValid ||
        state.membershipInputValidation.bic.dataEntered && !state.membershipInputValidation.bic.isValid
        )
        ||
        (state.membershipFormContent.debitType == 'non-sepa' &&
        state.membershipInputValidation.bankCode.dataEntered && !state.membershipInputValidation.bankCode.isValid ||
        state.membershipInputValidation.accountNumber.dataEntered && !state.membershipInputValidation.accountNumber.isValid
        )
      ){
        paymentMethod.addClass('invalid');
        paymentMethod.find('.payment-icon').removeClass().addClass('payment-icon icon-error');
      }
      else {
        paymentMethod.removeClass('invalid');
      }
    }
    else {
      donatorType.addClass('disabled');
    }

    if (state.membershipFormContent.addressType) {
      donatorType.addClass('completed').removeClass('disabled invalid');
      var validators = state.membershipInputValidation;
      if (
        state.membershipFormContent.addressType == 'person' &&
        (
        (validators.email.dataEntered && !validators.email.isValid) ||
        (validators.city.dataEntered && !validators.city.isValid) ||
        (validators.firstName.dataEntered && !validators.firstName.isValid) ||
        (validators.lastName.dataEntered && !validators.lastName.isValid) ||
        (validators.street.dataEntered && !validators.street.isValid) ||
        (validators.postcode.dataEntered && !validators.postcode.isValid) ||
        (validators.salutation.dataEntered && !validators.salutation.isValid) ||
        (validators.firstName.dataEntered && !validators.firstName.isValid)
        )
        ||
        state.membershipFormContent.addressType == 'firma' &&
        (
        (validators.contactPerson.dataEntered && !validators.contactPerson.isValid) ||
        (validators.companyName.dataEntered && !validators.companyName.isValid) ||
        (validators.firstName.dataEntered && !validators.firstName.isValid) ||
        (validators.email.dataEntered && !validators.email.isValid) ||
        (validators.city.dataEntered && !validators.city.isValid) ||
        (validators.street.dataEntered && !validators.street.isValid) ||
        (validators.postcode.dataEntered && !validators.postcode.isValid)
        )){
        donatorType.addClass('invalid');
        donatorType.find('.payment-icon').removeClass().addClass('payment-icon icon-error');
      }
    }


    if (formDataIsValid()) {
      $('form input[type="submit"]').removeClass('btn-unactive');
    }
    else {
      $('form input[type="submit"]').addClass('btn-unactive');
    }
  };
  $('input').on('click, change', WMDE.StoreUpdates.makeEventHandlerWaitForAsyncFinish( handleGroupValidations, store ) );
  setInterval(handleGroupValidations, 1000);

  $('input[name="membership_type"]').on('click', function () {
    if ($(this).val() == 'active') {
      $('#company').parent().addClass('disabled');
      $('.wrap-field.firma').removeClass('selected');
      $('.wrap-field.firma .wrap-info .info-text').removeClass('opened');
      $('.wrap-field.personal').addClass('selected');
      $('.wrap-field.personal .wrap-info .info-text').addClass('opened');
    }
    else {
      $('#company').parent().removeClass('disabled');
    }
  });

  $('form').on('submit', function () {
    triggerValidityCheckForPersonalDataPage();
    handleGroupValidations();

    if (formDataIsValid()) {
      return true;
    }
    return false;
  });

  $("#amount-typed").on('keypress', function (event) {
    var _element = $(this),
      keyCode = event.keyCode || event.which,
      keysAllowed = [44, 46, 48, 49, 50, 51, 52, 53, 54, 55, 56, 57, 0, 8, 9, 13];

    if ($.inArray(keyCode, keysAllowed) === -1 && event.ctrlKey === false) {
      event.preventDefault();
    }

    if ((keyCode == 44 || keyCode == 46) && $('#amount-typed').val().indexOf('.') > 0) {
      event.preventDefault();
    }

    if (keyCode == 44) {
      setTimeout(
        function () {
          $('#amount-typed').val(
            $('#amount-typed').val().replace(',','.')
          );
        }, 10);
    }
  });

  // Initialize form pages
  store.dispatch( actions.newAddPageAction( 'personalData' ) );
  store.dispatch( actions.newAddPageAction( 'bankConfirmation' ) );

  // Set initial form values
  store.dispatch( actions.newInitializeContentAction( initData.data( 'initial-form-values' ) ) );

} );