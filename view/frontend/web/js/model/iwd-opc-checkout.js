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
	'underscore',
	'Magento_Checkout/js/model/quote',
	'uiRegistry',
	'Magento_Checkout/js/action/set-shipping-information',
	'Magento_Checkout/js/model/address-converter',
	'Magento_Customer/js/model/customer',
	'Magento_Checkout/js/checkout-data',
	'Magento_Checkout/js/action/select-shipping-address'
], function(
	_,
	quote,
	registry,
	setShippingInformationAction,
	addressConverter,
	customer,
	checkoutData,
	selectShippingAddress
){
	'use strict';
	return {
		getShippingAddress: function(){
			if (registry.get('checkout.steps.shipping-step.shippingAddress').isAddressFormVisible()) {
				return addressConverter.formAddressDataToQuoteAddress(
					registry.get('checkout.steps.shipping-step.shippingAddress').source.get('shippingAddress')
				);
			} else {
				return quote.shippingAddress();
			}
		},
		
		storeShippingAddress: function(){
			if (registry.get('checkout.steps.shipping-step.shippingAddress').isAddressFormVisible()) {
				var shippingAddress,
					addressData;
				shippingAddress = quote.shippingAddress();
				addressData = addressConverter.formAddressDataToQuoteAddress(
					registry.get('checkout.steps.shipping-step.shippingAddress').source.get('shippingAddress')
				);

				for (var field in addressData) {
					if (addressData.hasOwnProperty(field) &&
							shippingAddress.hasOwnProperty(field) &&
							typeof addressData[field] !== 'function' &&
							_.isEqual(shippingAddress[field], addressData[field])
					) {
							shippingAddress[field] = addressData[field];
					} else if (typeof addressData[field] !== 'function' &&
							!_.isEqual(shippingAddress[field], addressData[field])) {
							shippingAddress = addressData;
							break;
					}
				}

				if (customer.isLoggedIn()) {
					shippingAddress.save_in_address_book = registry.get('checkout.steps.shipping-step.shippingAddress').saveInAddressBook ? 1 : 0;
				}

				checkoutData.setNeedEstimateShippingRates(false);
				selectShippingAddress(shippingAddress);
				if (customer.isLoggedIn()) {
					checkoutData.setNewCustomerShippingAddress(shippingAddress);
				}

				checkoutData.setNeedEstimateShippingRates(true);
			}
		},
		
		validateAddresses: function(){
			return this.validateShippingAddress() && this.validateBillingAddress();
		},

		validateShippingAddress: function(){
			var shippingAddress = registry.get('checkout.steps.shipping-step.shippingAddress');
			return shippingAddress.validateShippingInformation(false);
		},

		validateBillingAddress: function(){
			var billingAddress = this.getBillingAddressForm();

			if (!billingAddress || billingAddress.isAddressSameAsShipping() || !billingAddress.isAddressFormVisible()) {
				return true;
			}

			return billingAddress.validateFields(false);
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
		}
	};
});