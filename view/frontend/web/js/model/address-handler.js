/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
define([
	'underscore',
	'Magento_Checkout/js/model/quote',
	'Magento_Checkout/js/action/set-shipping-information',
	'Wallee_Payment/js/model/default-checkout'
], function(
	_,
	quote,
	setShippingInformationAction,
	defaultCheckoutAdapter,
	pluginCheckoutAdapter
){
	'use strict';
	return function(isActive, loadPaymentForm){
		var billingAddressCache = {},
			shippingAddressCache = {},
			updateTimeout,
			pluginCheckoutAdapter;
		
		function getCheckoutAdapter(){
			if (pluginCheckoutAdapter) {
				return pluginCheckoutAdapter;
			} else {
				return defaultCheckoutAdapter;
			}
		}
		
		function covertToCacheableAddress(address){
			var cacheableAddress = {};
			_.each(address, function(value, key){
				if (!_.isFunction(value)) {
					cacheableAddress[key] = value;
				}
			});
			return cacheableAddress;
		}
		
		function hasAddressesChanged(){
			return !_.isEqual(shippingAddressCache, covertToCacheableAddress(quote.shippingAddress())) || !_.isEqual(billingAddressCache, covertToCacheableAddress(quote.billingAddress()));
		}
		
		function storeAddresses(){
			return getCheckoutAdapter().storeAddresses();
		}
		
		function validateAddresses(){
			return getCheckoutAdapter().validateAddresses();
		}
		
		function updateAddresses(){
			if (isActive() && validateAddresses()) {
				setTimeout(function(){
					storeAddresses();
					if (hasAddressesChanged()) {
						setShippingInformationAction().done(function(){
							billingAddressCache = covertToCacheableAddress(quote.billingAddress());
							shippingAddressCache = covertToCacheableAddress(quote.shippingAddress());
							clearTimeout(updateTimeout);
							updateTimeout = setTimeout(loadPaymentForm, 1400);
							setTimeout(updateAddresses, 100);
						});
					} else {
						setTimeout(updateAddresses, 100);
					}
				}, 400);
			} else {
				setTimeout(updateAddresses, 100);
			}
		}
		
		if (require.specified('wallee_checkout_adapter')) {
			require(['wallee_checkout_adapter'], function(adapter){
				pluginCheckoutAdapter = adapter;
				updateAddresses();
			});
		} else {
			updateAddresses();
		}
		
		return {
			hasAddressesChanged: hasAddressesChanged,
			storeAddresses: storeAddresses,
			validateAddresses: validateAddresses
		};
	};
});