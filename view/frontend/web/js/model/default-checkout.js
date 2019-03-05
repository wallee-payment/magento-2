/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com/).
 *
 * @package Wallee_Payment
 * @author customweb GmbH (http://www.customweb.com/)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)
 */
define([], function(){
	'use strict';
	return {
		getShippingAddress: function(){},
		
		storeShippingAddress: function(){},
		
		validateAddresses: function(){
			return true;
		}
	};
});