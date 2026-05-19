/**
 * wallee Magento 2
 *
 * This Magento 2 extension enables to process payments with wallee (https://www.wallee.com).
 *
 * @package Wallee_Payment
 * @author wallee AG (https://www.wallee.com)
 * @license http://www.apache.org/licenses/LICENSE-2.0  Apache Software License (ASL 2.0)

 */
(function (root, factory) {
    if (typeof define === 'function' && define.amd) {
        define([], factory);
    } else {
        root.WhitelabelMachineNameSDKLoader = factory();
    }
}(typeof self !== 'undefined' ? self : this, function () {
    'use strict';

    /**
     * SDK Loader utility to load WhitelabelMachineName JS components.
     */
    var WhitelabelMachineNameSDKLoader = {
        /**
         * Loads the SDK script from the given URL.
         *
         * @param {string} url - The URL of the SDK script to load.
         * @returns {Promise} A promise that resolves when the script is loaded.
         */
        load: function (url) {
            return new Promise(function (resolve, reject) {
                // Check if a script with the same src already exists to prevent duplicate loading.
                var existingScript = document.querySelector('script[src="' + url + '"]');
                if (existingScript) {
                    // Check if the SDK is already active in the global window object.
                    if (window.IframeCheckoutHandler || window.LightboxCheckoutHandler) {
                        resolve(window.IframeCheckoutHandler || window.LightboxCheckoutHandler);
                        return;
                    }

                    // If the script exists but handlers are not yet available, wait for current load.
                    existingScript.addEventListener('load', function () {
                        resolve(window.IframeCheckoutHandler || window.LightboxCheckoutHandler);
                    });
                    existingScript.addEventListener('error', function (error) {
                        reject(error);
                    });
                    return;
                }

                // Create and inject the new script tag.
                var script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = url;
                script.async = true;

                if (window.checkoutConfig && window.checkoutConfig.wallee && window.checkoutConfig.wallee.cspNonce) {
                    script.setAttribute('nonce', window.checkoutConfig.wallee.cspNonce);
                }

                script.onload = function () {
                    // The SDK provides either an Iframe or Lightbox handler as a global.
                    resolve(window.IframeCheckoutHandler || window.LightboxCheckoutHandler);
                };

                script.onerror = function (error) {
                    reject(error);
                };

                document.head.appendChild(script);
            });
        }
    };

    return WhitelabelMachineNameSDKLoader;
}));
