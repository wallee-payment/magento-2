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
	'jquery',
	'uiComponent',
	'Magento_Checkout/js/model/payment/renderer-list'
], function(
	$,
	Component,
	rendererList
) {
	'use strict';
	
	// Loads the wallee Javascript File
	if (window.checkoutConfig.wallee.javascriptUrl) {
		$.getScript(window.checkoutConfig.wallee.javascriptUrl);
	}
	
	// Loads the wallee Lightbox File
	if (window.checkoutConfig.wallee.lightboxUrl) {
		$.getScript(window.checkoutConfig.wallee.lightboxUrl);
	}
	
	// Registers the wallee payment methods
	$.each(window.checkoutConfig.payment, function(code){
		if (code.indexOf('wallee_payment_') === 0) {
			rendererList.push({
			    type: code,
			    component: 'Wallee_Payment/js/view/payment/method-renderer/wallee-method'
			});
		}
	});
	
	return Component.extend({});
});