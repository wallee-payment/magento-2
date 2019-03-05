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
	'Magento_Checkout/js/model/full-screen-loader',
	'Magento_Checkout/js/model/payment/method-list',
	'mage/url',
	'Magento_Checkout/js/model/quote',
	'Wallee_Payment/js/model/address-handler'
], function(
	$,
	Component,
	fullScreenLoader,
	methodList,
	urlBuilder,
	quote,
	addressHandler
){
	'use strict';
	return Component.extend({
		defaults: {
			template: 'Wallee_Payment/payment/form'
		},
		redirectAfterPlaceOrder: false,
		submitDisabled: false,
		loadingIframe: false,
		addressHandler: null,
		
		/**
		 * @override
		 */
		initialize: function(){
			this._super();

			this.addressHandler = addressHandler(this.isActive.bind(this), this.createHandler.bind(this));
		},
		
		getFormId: function(){
			return this.getCode() + '-payment-form';
		},
		
		getConfigurationId: function(){
			return window.checkoutConfig.payment[this.getCode()].configurationId;
		},
		
		isActive: function(){
			return quote.paymentMethod() ? quote.paymentMethod().method == this.getCode() : null;
		},
		
		isShowDescription: function(){
			return window.checkoutConfig.payment[this.getCode()].showDescription;
		},
		
		getDescription: function(){
			return window.checkoutConfig.payment[this.getCode()].description;
		},
		
		isShowImage: function(){
			return window.checkoutConfig.payment[this.getCode()].showImage;
		},
		
		getImageUrl: function(){
			return window.checkoutConfig.payment[this.getCode()].imageUrl;
		},
		
		createHandler: function(){
			if (this.handler) {
				$('button.checkout').prop('disabled', this.submitDisabled);
			} else if (typeof window.IframeCheckoutHandler != 'undefined' && this.isActive() && this.addressHandler.validateAddresses()) {
				this.loadingIframe = true;
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
						if (validationResult.errors) {
							for (var i = 0; i < validationResult.errors.length; i++) {
								this.messageContainer.addErrorMessage({
									message: this.stripHtml(validationResult.errors[i])
								});
							}
						}
					}
				}).bind(this), (function(){
					fullScreenLoader.stopLoader();
					this.loadingIframe = false;
				}).bind(this));
			}
		},
		
		selectPaymentMethod: function(){
			var result = this._super();
			this.addressHandler.updateAddresses();
			return result;
		},
		
		validateIframe: function(){
			if (this.loadingIframe) {
				return;
			}
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
				if (window.checkoutConfig.wallee.paymentPageUrl) {
					window.location.replace(window.checkoutConfig.wallee.paymentPageUrl + "&paymentMethodConfigurationId=" + this.getConfigurationId());
				} else {
					window.location.replace(urlBuilder.build("wallee_payment/checkout/failure"));
				}
			}
		},
		
		stripHtml: function(input){
			return $('<div>' + input + '</div>').text();
		}
	});
});