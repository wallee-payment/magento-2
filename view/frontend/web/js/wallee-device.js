/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author wallee AG (http://www.wallee.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
define([
	'jquery',
	'mage/cookies'
], function(
	$
) {
	'use strict';
	
	function loadScript(options, identifier){
		if (options.scriptUrl && identifier) {
			$.getScript(options.scriptUrl + identifier);
		}
	}
	
	return function(options){
		var sessionIdentifier = $.mage.cookies.get('wallee_device_id');
		if (sessionIdentifier) {
			loadScript(options, sessionIdentifier);
		} else {
			$.getJSON(options.identifierUrl).fail(function (jqXHR) {
                throw new Error(jqXHR);
            }).done(function(sessionIdentifier){
            	$.mage.cookies.set('wallee_device_id', sessionIdentifier, { path: '/' });
            	loadScript(options, sessionIdentifier);
            });
		}
		
		
	}
});