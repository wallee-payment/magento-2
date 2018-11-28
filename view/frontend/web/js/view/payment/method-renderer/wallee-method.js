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
	'Magento_Checkout/js/view/payment/default',
	'rjsResolver',
	'Magento_Checkout/js/model/full-screen-loader'
], function(
	$,
	Component,
	resolver,
	fullScreenLoader
){
	'use strict';
	return Component.extend({
		defaults: {
    		template: 'Wallee_Payment/payment/form'
		},
		redirectAfterPlaceOrder: false,
		submitDisabled: false,
		
		/**
		 * @override
		 */
		initialize: function(){
			this._super();
			
			resolver((function(){
				if (this.isChecked() == this.getCode()) {
					this.createHandler();
				}
			}).bind(this));
		},
        
		getFormId: function(){
			return this.getCode() + '-payment-form';
		},
		
		getConfigurationId: function(){
			return window.checkoutConfig.payment[this.getCode()].configurationId;
		},
		
		createHandler: function(){
			if (this.handler) {
				$('button.checkout').prop('disabled', this.submitDisabled);
			} else if (typeof window.IframeCheckoutHandler != 'undefined') {
				fullScreenLoader.startLoader();
				this.handler = window.IframeCheckoutHandler(this.getConfigurationId());
				this.handler.setEnableSubmitCallback(function(){
					$('button.checkout').prop('disabled', false);
					this.submitDisabled = false;
				}.bind(this));
				this.handler.setDisableSubmitCallback(function(){
					$('button.checkout').prop('disabled', true);
					this.submitDisabled = true;
				}.bind(this));
				this.handler.create(this.getFormId(), (function(validationResult){
					if (validationResult.success) {
						this.placeOrder();
					} else {
						$('html, body').animate({ scrollTop: $('#' + this.getCode()).offset().top - 20 });
						for (var i = 0; i < validationResult.errors.length; i++) {
							this.messageContainer.addErrorMessage({
								message: validationResult.errors[i]
							});
						}
					}
				}).bind(this), function(){
					fullScreenLoader.stopLoader();
				});
			}
		},
		
		selectPaymentMethod: function(){
			var result = this._super();
			this.createHandler();
			return result;
		},
		
        validateIframe: function(){
        	if (this.handler) {
        		this.handler.validate();
        	} else {
        		this.placeOrder();
        	}
        },
        
        afterPlaceOrder: function(){
        	if (this.handler) {
        		this.handler.submit();
        	} else {
        		fullScreenLoader.startLoader();
                window.location.replace(window.checkoutConfig.wallee.paymentPageUrl + "&paymentMethodConfigurationId=" + this.getConfigurationId());
        	}
        }
	});
});