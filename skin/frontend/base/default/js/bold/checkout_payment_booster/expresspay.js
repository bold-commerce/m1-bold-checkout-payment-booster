const ExpressPay = async config => (async config => {
    'use strict';

    let boldPayments;
    let shippingMethodsHtml = '';

    const requiredConfigFields = [
        'epsApiUrl',
        'epsStaticApiUrl',
        'shopDomain',
        'currency',
        'quoteId',
        'boldCheckoutData',
        'formKey',
        'regions',
    ];

    const defaultConfig = {
        paymentsContainer: 'express-pay-container',
        epsApiUrl: '',
        epsStaticApiUrl: '',
        shopDomain: '',
        currency: '',
        quoteId: 0,
        boldCheckoutData: {},
        formKey: '',
        regions: {},
        saveShippingUrl: '/checkout/onepage/saveShipping',
        saveShippingMethodUrl: '/checkout/onepage/saveShippingMethod',
        saveBillingUrl: '/checkout/onepage/saveBilling',
        savePaymentUrl: '/checkout/onepage/savePayment',
        saveOrderUrl: '/checkout/onepage/saveOrder',
        successUrl: '/checkout/onepage/success',
        createOrderUrl: '/checkoutpaymentbooster/expresspay/createOrder',
        updateOrderUrl: '/checkoutpaymentbooster/expresspay/updateOrder',
        getOrderUrl: '/checkoutpaymentbooster/expresspay/getOrder',
    };

    /**
     * @param {Object} config
     * @returns {String[]}
     */
    const validateConfig = config => {
        const errors = [];
        const missingConfigFields = [];

        requiredConfigFields.forEach(field => {
            if (!config.hasOwnProperty(field) || config[field] === null || config[field].length === 0) {
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
     * @param {String} scriptUrl
     * @param {Object} attributes
     * @returns {Promise<void>}
     */
    const loadScript = async (scriptUrl, attributes = {}) => {
        return new Promise((resolve, reject) => {
            const script = document.createElement('script');

            script.src = scriptUrl;
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
     * @param {Object} order
     * @param {Object} address
     * @returns {Object}
     */
    const convertExpressPayAddress = (order, address) => {
        address.first_name = order.first_name;
        address.last_name = order.last_name;
        address.state = address.province;
        address.country_code = address.country;
        address.email = order.email;

        delete address.province;
        delete address.country;

        return address;
    };

    /**
     * @param {*} object
     * @param {FormData} formData
     * @param {String} parentKey
     * @returns {FormData}
     */
    const convertObjectToFormData = (object, formData = null, parentKey = '') => {
        if (formData === null) {
            formData = new FormData();
        }

        if (object === null) {
            return formData;
        }

        if (typeof object === 'object') {
            Object.keys(object)
                .forEach(
                    key => {
                        convertObjectToFormData(
                            object[key],
                            formData,
                            parentKey.length > 0 ? `${parentKey}[${key}]` : key
                        );
                    }
                );

            return formData;
        }

        if (Array.isArray(object)) {
            Object.forEach(
                object,
                (value, key) => {
                    convertObjectToFormData(object[key], formData, parentKey.length > 0 ? `${parentKey}[${key}]` : key);
                }
            );

            return formData;
        }

        formData.append(parentKey, object);

        return formData;
    }

    /**
     * @returns {Object}
     */
    const parseShippingMethodsFromHtml = () => {
        let domParser;
        let shippingMethodsDocument;
        let shippingMethods = [];

        if (shippingMethodsHtml.length === 0) {
            return;
        }

        domParser = new DOMParser();
        shippingMethodsDocument = domParser.parseFromString(shippingMethodsHtml, 'text/html');

        shippingMethodsDocument.querySelectorAll('dl > dt')
            .forEach(
                (dt) => {
                    let methodInput;

                    const dd = dt.nextElementSibling;
                    const shippingMethod = {
                        name: dt.innerText,
                        code: null
                    };

                    if (dd === null || dd.tagName !== 'DD') {
                        return;
                    }

                    methodInput = dd.querySelector('input[name=shipping_method]');

                    if (methodInput !== null) {
                        shippingMethod.code = methodInput.value;
                    }

                    shippingMethods.push(shippingMethod);
                }
            );

        return shippingMethods;
    };

    /**
     * @param {String} gatewayId
     * @returns {Promise<String>}
     * @throws Error
     */
    const createExpressPayOrder = async gatewayId => {
        let createOrderResponse;
        let createOrderResult;
        let errorMessage;

        try {
            createOrderResponse = await fetch(
                config.createOrderUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(
                        {
                            form_key: config.formKey,
                            quote_id: config.quoteId,
                            gateway_id: gatewayId
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
            errorMessage = 'Create Express Pay Order API did not return an order ID.';

            console.error(errorMessage);

            throw new Error(errorMessage);
        }

        return createOrderResult.order_id;
    };

    /**
     * @param {String} gatewayId
     * @param {String} orderId
     * @returns {Promise<void>}
     * @throws Error
     */
    const updateExpressPayOrder = async (gatewayId, orderId) => {
        let updateOrderResponse;
        let updateOrderResult;
        let errorMessage;

        try {
            updateOrderResponse = await fetch(
                config.updateOrderUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(
                        {
                            form_key: config.formKey,
                            quote_id: config.quoteId,
                            gateway_id: gatewayId,
                            order_id: orderId,
                        }
                    )
                }
            );
        } catch (error) {
            console.error('Could not update Express Pay order.', error);

            throw error;
        }

        try {
            updateOrderResult = await updateOrderResponse.json();
        } catch (syntaxError) {
            updateOrderResult = {};
        }

        if (!updateOrderResponse.ok) {
            if (updateOrderResult.hasOwnProperty('error')) {
                errorMessage = updateOrderResult.error;
            } else {
                errorMessage = `${updateOrderResponse.status} ${updateOrderResponse.statusText}`;
            }

            console.error('Could not update Express Pay order.', updateOrderResult);

            throw new Error(errorMessage);
        }
    };

    /**
     * @param {String} orderId
     * @param {String} gatewayId
     * @returns {Promise<Object>}
     * @throws Error
     */
    const getExpressPayOrder = async (orderId, gatewayId) => {
        let getOrderResponse;
        let getOrderResult;
        let errorMessage;

        try {
            getOrderResponse = await fetch(
                config.getOrderUrl,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: JSON.stringify(
                        {
                            form_key: config.formKey,
                            order_id: orderId,
                            gateway_id: gatewayId
                        }
                    )
                }
            );
        } catch (error) {
            console.error('Could not retrieve Express Pay order.', error);

            throw error;
        }

        try {
            getOrderResult = await getOrderResponse.json();
        } catch (syntaxError) {
            getOrderResult = {};
        }

        if (!getOrderResponse.ok) {
            if (getOrderResult.hasOwnProperty('error')) {
                errorMessage = getOrderResult.error;
            } else {
                errorMessage = `${getOrderResponse.status} ${getOrderResponse.statusText}`;
            }

            console.error('Could not retrieve Express Pay order.', getOrderResult);

            throw new Error(errorMessage);
        }

        return getOrderResult;
    };

    /**
     * @param {String} addressType
     * @param {Object} addressData
     * @returns {Promise<void>}
     * @throws Error
     */
    const updateMagentoAddress = async (addressType, addressData) => {
        let countryRegions = {};
        let regionId;
        let updateAddressResponse;
        let updateAddressResponseData;

        const magentoAddress = {
            form_key: config.formKey,
            [addressType]: {
                address_type: addressType,
                quote_id: config.quoteId,
                email: addressData.email ?? null,
                firstname: addressData.first_name ?? 'Unknown',
                lastname: addressData.last_name ?? 'Person',
                street: [
                    addressData.address_line_1 ?? addressData.address_line1 ?? '0 Unprovided St',
                    addressData.address_line_2 ?? addressData.address_line2 ?? null
                ],
                city: addressData.city,
                postcode: addressData.postal_code,
                country_id: addressData.country_code,
                telephone: addressData.phone ?? '5555551234',
            }
        };

        if (config.regions.hasOwnProperty(addressData.country_code)) {
            countryRegions = config.regions[addressData.country_code];
        }

        for (regionId in countryRegions) {
            if (countryRegions[regionId].code !== addressData.state) {
                continue;
            }

            magentoAddress[addressType].region = countryRegions[regionId].code;
            magentoAddress[addressType].region_id = regionId;

            break;
        }

        try {
            updateAddressResponse = await fetch(
                addressType === 'shipping' ? config.saveShippingUrl : config.saveBillingUrl,
                {
                    method: 'POST',
                    body: convertObjectToFormData(magentoAddress)
                }
            );
        } catch (error) {
            console.error(`Could not update Magento ${addressType} address for Express Pay order.`, error);

            throw error;
        }

        try {
            updateAddressResponseData = await updateAddressResponse.json();
        } catch (syntaxError) {
            updateAddressResponseData = {};
        }

        if (updateAddressResponseData.hasOwnProperty('error') && updateAddressResponseData.error) {
            console.error(
                `Could not update Magento ${addressType} address for Express Pay order.`,
                updateAddressResponseData.message
            );

            throw new Error(updateAddressResponseData.message);
        }

        if (addressType === 'shipping') {
            shippingMethodsHtml = updateAddressResponseData.update_section.html;
        }
    };

    /**
     * @param {Object|null} shippingOptions
     * @returns {Promise<void>}
     * @throws Error
     */
    const updateMagentoShippingMethod = async shippingOptions => {
        let selectedShippingMethod;
        let shippingMethodFormData;
        let updateShippingMethodResponse;
        let updateShippingMethodResult;

        const shippingMethods = parseShippingMethodsFromHtml();

        if (shippingMethods.length === 0) {
            return;
        }

        if (shippingOptions === null) {
            selectedShippingMethod = shippingMethods[0];
        } else {
            selectedShippingMethod = shippingMethods.find(shippingMethod => shippingMethod.code === shippingOptions.id);
        }

        if (selectedShippingMethod === undefined) {
            return;
        }

        shippingMethodFormData = new FormData();

        shippingMethodFormData.append('form_key', config.formKey);
        shippingMethodFormData.append('shipping_method', selectedShippingMethod.code);

        try {
            updateShippingMethodResponse = await fetch(
                config.saveShippingMethodUrl,
                {
                    method: 'POST',
                    body: shippingMethodFormData
                }
            );
        } catch (error) {
            console.error(`Could not update Magento shipping method for Express Pay order.`, error);

            throw error;
        }

        try {
            updateShippingMethodResult = await updateShippingMethodResponse.json();
        } catch (error) {
            updateShippingMethodResult = {};
        }

        if (updateShippingMethodResult.hasOwnProperty('error') && updateShippingMethodResult.error) {
            console.error(
                `Could not update Magento shipping method for Express Pay order.`,
                updateShippingMethodResult.message
            );

            throw new Error(updateShippingMethodResult.message);
        }
    };

    /**
     * @param {String} orderId
     * @returns {Promise<void>}
     * @throws Error
     */
    const placeMagentoOrder = async orderId => {
        let placeOrderResponse;
        let placeOrderResult;

        const orderData = {
            form_key: config.formKey,
            payment: {
                method: 'bold',
                additional_data: {
                    order_id: orderId
                }
            }
        };

        try {
            placeOrderResponse = await fetch(
                config.saveOrderUrl,
                {
                    method: 'POST',
                    body: convertObjectToFormData(orderData)
                }
            );
        } catch (error) {
            console.error('Could not place Express Pay order in Magento.', error);

            throw error;
        }

        /*if (placeOrderResponse.redirected) {
            window.location.href = encodeURI(placeOrderResponse.url);

            return;
        }*/

        try {
            placeOrderResult = await placeOrderResponse.json();
        } catch (error) {
            placeOrderResult = {};
        }

        if (placeOrderResult.hasOwnProperty('error_messages')) {
            let errorMessage;

            console.error(
                'Could not process response from placing Express Pay order in Magento.',
                placeOrderResult.error_messages
            );

            if (Array.isArray(placeOrderResult.error_messages)) {
                errorMessage = placeOrderResult.error_messages.join('\n')
            } else {
                errorMessage = placeOrderResult.error_messages;
            }

            alert(errorMessage.stripTags().toString());

            throw new Error(errorMessage);
        }

        window.location.href = encodeURI(config.successUrl);
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
                    currency: config.currency,
                }
            ],
            callbacks: {
                /**
                 * @param {String} paymentType
                 * @param {Object} paymentPayload
                 * @returns {Promise<Object>}
                 * @throws Error
                 */
                onCreatePaymentOrder: async (paymentType, paymentPayload) => {
                    const expressPayOrderId = await createExpressPayOrder(String(paymentPayload.gateway_id));

                    return {
                        payment_data: {
                            id: expressPayOrderId
                        }
                    };
                },
                /**
                 * @param {String} paymentType
                 * @param {Object} paymentPayload
                 * @returns {Promise<void>}
                 */
                onUpdatePaymentOrder: async (paymentType, paymentPayload) => {
                    await updateMagentoAddress('shipping', paymentPayload.payment_data.shipping_address);
                    await updateMagentoShippingMethod(paymentPayload.payment_data.shipping_options);
                    await updateExpressPayOrder(paymentPayload.gateway_id, paymentPayload.payment_data.order_id);
                },
                /**
                 * @param {String} paymentType
                 * @param {Object} paymentInformation
                 * @param {Object} paymentPayload
                 * @returns {Promise<void>}
                 * @throws Error
                 */
                onApprovePaymentOrder: async (paymentType, paymentInformation, paymentPayload) => {
                    const expressPayOrder = await getExpressPayOrder(
                        paymentPayload.payment_data.order_id,
                        paymentPayload.gateway_id
                    );

                    await updateMagentoAddress(
                        'shipping',
                        convertExpressPayAddress(expressPayOrder, expressPayOrder.shipping_address)
                    );
                    await updateMagentoAddress(
                        'billing',
                        convertExpressPayAddress(expressPayOrder, expressPayOrder.billing_address)
                    );
                    await placeMagentoOrder(paymentPayload.payment_data.order_id);
                }
            }
        };
        boldPayments = new window.bold.Payments(sdkConfiguration);
    };

    /**
     * @returns {Promise<void>}
     */
    const initialize = async () => {
        const validationErrors = validateConfig(config);
        const expressPayContainer = document.getElementById(
            config.paymentsContainer ?? defaultConfig.paymentsContainer
        );

        if (validationErrors.length > 0) {
            if (expressPayContainer !== null) {
                expressPayContainer.style.display = 'none';
            }

            console.error('Could not initialize Express Pay.', validationErrors);
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
