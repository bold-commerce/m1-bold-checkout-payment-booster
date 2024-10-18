const ExpressPay = async config => (async config => {
    'use strict';

    const requiredConfigFields = [
        'epsApiUrl',
        'epsStaticApiUrl',
        'shopDomain',
        'quoteData',
        'boldCheckoutData',
        'formKey',
        'regions'
    ];
    const defaultConfig = {
        paymentsContainer: 'express-pay-container',
        epsApiUrl: '',
        epsStaticApiUrl: '',
        shopDomain: '',
        quoteData: {},
        boldCheckoutData: {},
        formKey: '',
        regions: {},
        createOrderUri: '/checkoutpaymentbooster/expresspay/createOrder',
        updateOrderUri: '/checkoutpaymentbooster/expresspay/updateOrder',
    };
    const callbacks = {
        /**
         * @param {String} paymentType
         * @param {Object} paymentPayload
         * @returns {Promise<Object>}
         * @throws Error
         */
        onCreatePaymentOrder: async (paymentType, paymentPayload) => {
            let createOrderResponse;
            let createOrderResult;
            let errorMessage;

            try {
                createOrderResponse = await fetch(
                    config.createOrderUri,
                    {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest'
                        },
                        body: JSON.stringify(
                            {
                                form_key: config.formKey,
                                quote_id: config.quoteData.entity_id,
                                gateway_id: String(paymentPayload.gateway_id)
                            }
                        )
                    }
                );
            } catch (error) {
                console.error('Could not create Express Pay order.', error);

                throw error;
            }

            try {
                createOrderResult = await createOrderResponse.json();
            } catch (syntaxError) {
                createOrderResult = {};
            }

            if (!createOrderResponse.ok) {
                if (createOrderResult.hasOwnProperty('error')) {
                    errorMessage = createOrderResult.error;
                } else {
                    errorMessage = `${createOrderResponse.status} ${createOrderResponse.statusText}`;
                }

                console.error('Could not create Express Pay order.', createOrderResult);

                throw new Error(errorMessage);
            }

            if (!createOrderResult.hasOwnProperty('order_id')) {
                errorMessage = 'Create Express Pay Order API did not return an order id.';

                console.error(errorMessage);

                throw new Error(errorMessage);
            }

            expressPayOrderId = createOrderResult.order_id;

            return {
                payment_data: {
                    id: expressPayOrderId
                }
            };
        },
        /**
         * @param {String} paymentType
         * @param {Object} paymentInformation
         * @returns {Promise<void>}
         */
        onUpdatePaymentOrder: async (paymentType, paymentInformation) => {
            // Check if shipping address in `paymentInformation` has changes
            // Update Magento Quote with address changes
            // Call `updateOrder` endpoint on Wallet Pay API
            console.debug('paymentType', paymentType);
            console.debug('paymentInformation', paymentInformation);

            updateMagentoAddress('shipping', paymentInformation.payment_data.shipping_address);
            parseShippingMethodsFromHtml();
        },
        onApprovePaymentOrder: async (paymentType, paymentInformation, paymentPayload) => {
            // Trigger order placement logic
            debugger
            console.debug('paymentType', paymentType);
            console.debug('paymentInformation', paymentInformation);
            console.debug('paymentPayload', paymentPayload);
        }
    };
    let boldPayments;
    let expressPayOrderId;
    let shippingMethodsHtml = '';
    let shippingMethods = [];

    /**
     * @param {Object} config
     * @returns {String[]}
     */
    const validateConfig = config => {
        const errors = [];
        const missingConfigFields = [];

        requiredConfigFields.forEach(field => {
            if (!config.hasOwnProperty(field) || config[field].length === 0) {
                missingConfigFields.push(field);
            }
        });

        if (missingConfigFields.length > 0) {
            errors.push(
                `Please provide values for the following configuration fields: ${missingConfigFields.join(', ')}`
            );
        }

        return errors;
    };

    /**
     * @param {String} src
     * @param {Object} attributes
     * @returns {Promise<void>}
     */
    const loadScript = async (src, attributes = {}) => {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');

            script.src = src;
            script.async = true;
            script.onload = resolve;
            script.onerror = reject;

            if (attributes.constructor === Object) {
                Object.keys(attributes).forEach((key) => {
                    script.setAttribute(key, attributes[key]);
                });
            }

            document.head.appendChild(script);
        });
    };

    /**
     * @param {String} addressType
     * @param {Object} addressData
     * @returns {Promise<void>}
     */
    const updateMagentoAddress = async (addressType, addressData) => {
        const magentoAddress = {
            form_key: config.formKey,
            address_type: addressType,
            quote_id: config.quoteData.entity_id,
            email: addressData.email ?? null,
            firstname: addressData.first_name ?? 'Unknown',
            lastname: addressData.last_name ?? 'Person',
            street1: addressData.address_line1 ?? '0 Unprovided St',
            street2: addressData.address_line2 ?? null,
            city: addressData.city,
            postcode: addressData.postal_code,
            country_id: addressData.country_code,
            telephone: addressData.phone ?? '5555551234',
        };
        let countryRegions = {};
        let regionId;
        let updateAddressResult;
        let updateAddressResponse;

        if (config.regions.hasOwnProperty(addressData.country_code)) {
            countryRegions = config.regions[addressData.country_code];
        }

        for (regionId in countryRegions) {
            if (countryRegions[regionId].code !== addressData.state) {
                continue;
            }

            magentoAddress.region = countryRegions[regionId].code;
            magentoAddress.region_id = regionId;

            break;
        }

        try {
            updateAddressResult = await fetch(
                addressType === 'shipping' ? config.saveShippingUrl : config.saveBillingUrl,
                {
                    method: 'POST',
                    body: Object.keys(magentoAddress)
                        .reduce(
                            (formData, key) => {
                                let name;

                                if (magentoAddress[key] === null) {
                                    return formData;
                                }

                                if (key === 'form_key') {
                                    name = key;
                                } else if (key.startsWith('street')) {
                                    name = `${addressType}[street][]`;
                                } else {
                                    name = `${addressType}[${key}]`;
                                }

                                formData.append(name, magentoAddress[key]);

                                return formData;
                            },
                            new FormData()
                        )
                }
            );
        } catch (error) {
            console.error(`Could not update Magento ${addressType} address for Express Pay order.`, error);

            return;
        }

        try {
            updateAddressResponse = await updateAddressResult.json();
        } catch (error) {
            console.error(
                `Could not process response received from updating Magento ${addressType} address for Express Pay order.`,
                error
            );

            return;
        }

        console.debug(updateAddressResponse);

        if (updateAddressResponse.hasOwnProperty('error') && updateAddressResponse.error) {
            console.error(
                `Could not update Magento ${addressType} address for Express Pay order.`,
                updateAddressResponse.message
            );
        }

        if (addressType === 'shipping') {
            shippingMethodsHtml = updateAddressResponse.update_section.html;
        }
    };

    /**
     * @returns void
     */
    const parseShippingMethodsFromHtml = () => {
        debugger;
        let domParser;
        let shippingMethodsDocument;

        if (shippingMethodsHtml.length === 0) {
            return;
        }

        domParser = new DOMParser();
        shippingMethodsDocument = domParser.parseFromString(shippingMethodsHtml, 'text/html');

        shippingMethodsDocument.querySelectorAll('dl > dt')
            .forEach(
                (dt) => {
                    const shippingMethod = {
                        name: dt.innerText,
                        code: null,
                        currencySymbol: null,
                        price: null
                    };
                    const dd = dt.nextElementSibling;
                    let methodInput;
                    let priceSpan;
                    let priceParts;

                    if (dd === null || dd.tagName !== 'DD') {
                        return;
                    }

                    methodInput = dd.querySelector('input[name=shipping_method]');

                    if (methodInput !== null) {
                        shippingMethod.code = methodInput.value;
                    }

                    priceSpan = dd.querySelector('.price');

                    if (priceSpan !== null) {
                        priceParts = priceSpan.innerText.match(/^([^\d,.]+)([\d,.]+)$/);
                        shippingMethod.currencySymbol = priceParts[1];
                        shippingMethod.price = priceParts[2];
                    }

                    shippingMethods.push(shippingMethod);
                }
            );

        console.debug(shippingMethods);
    };

    /**
     * @returns {Promise<void>}
     */
    const initializePaymentsSdk = async () => {
        let sdkConfiguration;

        await loadScript(config.epsStaticApiUrl + '/js/payments_sdk.js');

        sdkConfiguration = {
            eps_url: config.epsApiUrl,
            eps_bucket_url: config.epsStaticApiUrl,
            group_label: config.shopDomain,
            trace_id: config.boldCheckoutData.public_order_id,
            payment_gateways: [
                {
                    gateway_id: Number(config.boldCheckoutData.flow_settings.eps_gateway_id),
                    auth_token: config.boldCheckoutData.flow_settings.eps_auth_token,
                    currency: config.quoteData.quote_currency_code,
                }
            ],
            callbacks: callbacks
        }
        boldPayments = new window.bold.Payments(sdkConfiguration);
    };

    /**
     * @returns {Promise<void>}
     */
    const initialize = async () => {
        const validationErrors = validateConfig(config);

        if (validationErrors.length > 0) {
            console.error('Could not initialize Express Pay due to the following errors: ' + validationErrors);
        }

        config = {...defaultConfig, ...config};

        await initializePaymentsSdk();
    };

    await initialize();

    return {
        /**
         * @returns {Promise<void>}
         */
        render: async () => {
            await boldPayments.renderWalletPayments(config.paymentsContainer);
        }
    };
})(config);
