window.addEventListener('alpine:init', () => {
    Alpine.data('hyvaCheckoutWhitelabelPaymentMethod', () => ({
        methodCode: '',
        sdkUrl: '',
        configurationId: '',
        integrationMode: '',
        handler: null,
        isInitialized: false,
        validationResolver: null,

        init() {
            // Extract method-specific config from the DOM element (CSP Safe)
            if (this.$el && this.$el.dataset.config) {
                let config = JSON.parse(this.$el.dataset.config);
                this.methodCode = config.methodCode || '';
                this.sdkUrl = config.javascriptUrl;
                this.configurationId = config.configurationId;
                this.integrationMode = config.integrationMode;
                this.cspNonce = config.cspNonce;
                
                this.initComponent();
            }
        },

        // CSP Helper Methods
        get isIframeMode() {
            return this.integrationMode === 'iframe';
        },

        initComponent() {

            // Pre-placement validation logic using Hyva Checkout validation API.
            if (typeof hyvaCheckout !== 'undefined' && hyvaCheckout.validation) {
                hyvaCheckout.validation.register('payment:wallee_' + this.methodCode, () => {
                    if (this.isActive && this.isIframeMode && this.handler) {
                        return new Promise((resolve) => {
                            this.validationResolver = resolve;
                            this.handler.validate();
                        });
                    }
                    return true;
                });
            }

            // Post-placement submission logic.
            window.addEventListener('order:place:success', (event) => {
                this.handlePostPlaceOrder(event);
            });

            // Load the SDK and initialize the handler robustly
            this.loadSdkAndInitialize();
        },

        loadSdkAndInitialize() {
            const registry = window.__walleeHandlers = window.__walleeHandlers || {};

            new Promise((resolve, reject) => {
                // If script already exists, wait for it or use global
                let existingScript = document.querySelector('script[src="' + this.sdkUrl + '"]');
                if (existingScript) {
                    if (window.IframeCheckoutHandler || window.LightboxCheckoutHandler) {
                        resolve(window.IframeCheckoutHandler || window.LightboxCheckoutHandler);
                        return;
                    }
                    existingScript.addEventListener('load', () => resolve(window.IframeCheckoutHandler || window.LightboxCheckoutHandler));
                    existingScript.addEventListener('error', reject);
                    return;
                }

                // Inject script with CSP Nonce
                let script = document.createElement('script');
                script.type = 'text/javascript';
                script.src = this.sdkUrl;
                script.async = true;
                if (this.cspNonce) {
                    script.setAttribute('nonce', this.cspNonce);
                }
                script.onload = () => resolve(window.IframeCheckoutHandler || window.LightboxCheckoutHandler);
                script.onerror = reject;
                document.head.appendChild(script);
            }).then((SdkHandler) => {
                if (SdkHandler) {
                    this.initializeHandler(SdkHandler, registry);
                }
            }).catch((error) => {
                console.error('Wallee SDK Load failed:', error);
            });
        },

        initializeHandler(SdkHandler, registry) {
            if (this.isIframeMode) {
                this.handler = SdkHandler(this.configurationId);
                registry[this.configurationId] = this.handler;
                this.createIframe();
            } else if (this.integrationMode === 'lightbox') {
                this.handler = window.LightboxCheckoutHandler;
                registry[this.configurationId] = this.handler;
            }
            this.isInitialized = true;
        },

        createIframe() {
            if (this.handler && this.isIframeMode) {
                let containerId = 'wallee-iframe-' + this.methodCode;
                
                this.handler.create(containerId, (validationResult) => {
                    if (this.validationResolver) {
                        if (validationResult.success) {
                            this.validationResolver(true);
                        } else {
                            console.error(`[WhitelabelMachineName][${this.methodCode}] Validation failed. Errors:`, validationResult.errors);
                            this.handleValidationError(validationResult.errors);
                            this.validationResolver(false);
                        }
                        this.validationResolver = null;
                    }
                });
            } else {
                console.warn(`[WhitelabelMachineName][${this.methodCode}] createIframe skipped. Handler exists? ${!!this.handler}. IntegrationMode: ${this.integrationMode}`);
            }
        },

        refreshIframe() {
            if (this.isIframeMode && this.handler) {
                const container = document.getElementById('wallee-iframe-' + this.methodCode);
                if (container) {
                    container.innerHTML = '';
                    this.createIframe();
                } else {
                    console.warn(`[WhitelabelMachineName][${this.methodCode}] container NOT found in DOM. Cannot refresh.`);
                }
            }
        },

        handlePostPlaceOrder(event) {
            if (!this.isActive || !this.isInitialized || !this.handler) {
                return;
            }

            // Mark the payment as in-flight so RestoreCartOnCartPage can reactivate the quote
            // if the SDK redirects externally (e.g. TWINT, 3DS) and the customer presses back.
            document.cookie = 'wallee_restore_pending=1; path=/; samesite=lax';

            if (this.isIframeMode) {
                // Order exists in Magento database. Submit the IFrame now.
                this.handler.submit();
            } else if (this.integrationMode === 'lightbox') {
                // Start Lightbox payment for the newly created order.
                this.handler.startPayment(this.configurationId, () => {
                    console.error('[WhitelabelMachineName] Payment failed or cancelled in lightbox mode.');
                });
            }
        },

        handleValidationError(errors) {
            console.warn('WhitelabelMachineName validation failed:', errors);
            window.dispatchEvent(new CustomEvent('checkout:message:error', {
                detail: "Payment validation failed."
            }));
        },

        get isActive() {
            // Check if this payment method is currently selected in the frontend via Magewire.
            if (typeof Magewire !== 'undefined') {
                const methodsComponent = Magewire.find('checkout.payment.methods');
                return methodsComponent && methodsComponent.get('method') === this.methodCode;
            }
            return false;
        }
    }));
});
