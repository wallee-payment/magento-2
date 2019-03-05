/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
define('wallee_checkout_adapter', [
	'jquery',
	'underscore',
	'Magento_Checkout/js/model/quote',
	'uiRegistry',
	'Magento_Ui/js/lib/validation/validator',
	'Magento_Customer/js/model/customer',
	'Magento_Checkout/js/model/address-converter',
	'Magento_Checkout/js/action/select-shipping-address'
], function(
	$,
	_,
	quote,
	registry,
	validator,
	customer,
	addressConverter,
	selectShippingAddress
){
	'use strict';
	return {
		canHideErrors: true,
		
		getShippingAddress: function(){
			var shippingComponent = registry.get('checkout.steps.shipping-step.shippingAddress');
			
			if (shippingComponent.isFormInline) {
                return addressConverter.formAddressDataToQuoteAddress(registry.get('checkoutProvider').shippingAddress);
			} else {
				return quote.shippingAddress();
			}
		},

		storeShippingAddress: function(){
			var shippingComponent = registry.get('checkout.steps.shipping-step.shippingAddress');
			
			if (shippingComponent.isFormInline) {
                var address = addressConverter.formAddressDataToQuoteAddress(registry.get('checkoutProvider').shippingAddress);
                selectShippingAddress(address);
			}
		},

		validateAddresses: function(){
			var self = this,
				shippingComponent = registry.get('checkout.steps.shipping-step.shippingAddress'),
				billingAddressComponent = this.getBillingAddressForm(),
				loginFormSelector = 'form[data-role=email-with-possible-login]',
				emailValidationResult = customer.isLoggedIn(),
				shippingAddressValidationResult = true,
				billingAddressValidationResult = true;

			if (this.canHideErrors) {
				if ($(loginFormSelector + ' input[name=username]').hasClass('mage-error')) {
					this.canHideErrors = false;
				}
				if (shippingComponent.isFormInline && shippingComponent.source.get('params.invalid') === true) {
					this.canHideErrors = false;
				}
				if (billingAddressComponent && !billingAddressComponent.isAddressSameAsShipping() && billingAddressComponent.isAddressFormVisible() && billingAddressComponent.source.get('params.invalid') === true) {
					this.canHideErrors = false;
				}
			}

			if (!quote.shippingMethod()) {
				return false;
			}

			if (!customer.isLoggedIn()) {
				$(loginFormSelector).validation();
				emailValidationResult = Boolean($(loginFormSelector + ' input[name=username]').valid());
				if (this.canHideErrors) {
					$(loginFormSelector).validate().resetForm();
				}
			}

			if (shippingComponent.isFormInline) {
				shippingComponent.source.set('params.invalid', false);
				shippingComponent.triggerShippingDataValidateEvent();

				if (shippingComponent.source.get('params.invalid')) {
					if (this.canHideErrors) {
						var shippingAddress = shippingComponent.source.get('shippingAddress');
						shippingAddress = _.extend({
							region_id: '',
							region_id_input: '',
							region: ''
						}, shippingAddress);
						_.each(shippingAddress, function (value, index) {
							self.hideErrorForElement('checkout.steps.shipping-step.shippingAddress.shipping-address-fieldset', value, index);
						});
					}
					shippingComponent.source.set('params.invalid', false);
					shippingAddressValidationResult = false;
				}
			}

			if (billingAddressComponent && !billingAddressComponent.isAddressSameAsShipping() && billingAddressComponent.isAddressFormVisible()) {
				billingAddressComponent.source.set('params.invalid', false);
				billingAddressComponent.source.trigger(billingAddressComponent.dataScopePrefix + '.data.validate');

				if (billingAddressComponent.source.get(billingAddressComponent.dataScopePrefix + '.custom_attributes')) {
					billingAddressComponent.source.trigger(billingAddressComponent.dataScopePrefix + '.custom_attributes.data.validate');
				}

				if (billingAddressComponent.source.get('params.invalid')) {
					if (this.canHideErrors) {
						var billingAddress = billingAddressComponent.source.get(billingAddressComponent.dataScopePrefix);
						billingAddress = _.extend({
							region_id: '',
							region_id_input: '',
							region: ''
						}, billingAddress);
						_.each(billingAddress, function (value, index) {
							self.hideErrorForElement(billingAddressComponent.name, value, index);
						});
						billingAddressComponent.source.set('params.invalid', false);
					}
					billingAddressValidationResult = false;
				}
			}

			return emailValidationResult && shippingAddressValidationResult && billingAddressValidationResult;
		},

		getBillingAddressForm: function(){
			var billingAddress;
			if (quote.isVirtual()) {
				billingAddress = registry.get('checkout.steps.billing-step-virtual.billing-address-form');
			} else {
				billingAddress = registry.get('checkout.steps.billing-step.payment.billing-address-form');
				if (!billingAddress && quote.paymentMethod()) {
					billingAddress = registry.get('checkout.steps.billing-step.payment.payments-list.' + quote.paymentMethod().method + '-form');
				}
			}
			return billingAddress;
		},

		hideErrorForElement: function (component, value, index) {
			var self = this;
			if (typeof(value) === 'object') {
				_.each(value, function (childValue, childIndex) {
					var newIndex = (index === 'custom_attributes' ? childIndex : index + '.' + childIndex);
					self.hideErrorForElement(component, childValue, newIndex);
				})
			}

			var fieldObj = registry.get(component + '.' + index);
			if (fieldObj) {
				if (typeof (fieldObj.error) === 'function') {
					fieldObj.error(false);
				}
			}
		}
	};
});